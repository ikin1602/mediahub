<?php
// backend/book.php
require __DIR__ . '/config.php';
require_login();

/* ---------------- Utilities ---------------- */
function now_mysql() { return date('Y-m-d H:i:s'); }
function format_items_list(?string $json): ?string {
  if (!$json) return null;
  $arr = json_decode($json, true);
  if (!is_array($arr)) return null;
  $out = [];
  foreach ($arr as $it) {
    $name = trim($it['name'] ?? $it['item'] ?? '');
    $qty  = (int)($it['quantity'] ?? $it['qty'] ?? 1);
    if ($name !== '') $out[] = $name . ($qty ? " ($qty)" : "");
  }
  return $out ? implode(', ', $out) : null;
}

/* ---------------- Ensure table & columns exist ---------------- */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS uidm_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    kind ENUM('internal','external') NOT NULL DEFAULT 'internal',
    full_name VARCHAR(150) NOT NULL,
    staff_id VARCHAR(60) NULL,
    email VARCHAR(190) NULL,
    department VARCHAR(150) NULL,
    office_phone VARCHAR(50) NULL,
    mobile_phone VARCHAR(50) NULL,
    purpose TEXT NULL,
    borrow_date DATE NULL,
    return_date DATE NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    room VARCHAR(120) NULL,
    location VARCHAR(255) NULL,
    items_json LONGTEXT NULL,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    return_status ENUM('na','pending_return','returned','overdue') DEFAULT 'na',
    decision_reason TEXT NULL,
    decision_by VARCHAR(100) NULL,
    decision_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
try { $pdo->exec("ALTER TABLE uidm_requests ADD COLUMN IF NOT EXISTS start_time TIME NULL"); } catch(Throwable $e) {}
try { $pdo->exec("ALTER TABLE uidm_requests ADD COLUMN IF NOT EXISTS end_time TIME NULL"); } catch(Throwable $e) {}
try { $pdo->exec("ALTER TABLE uidm_requests ADD COLUMN IF NOT EXISTS room VARCHAR(120) NULL"); } catch(Throwable $e) {}

/* ---------------- Inventory & Facilities ---------------- */
/* Get live stock from resources */
function fetch_total_stock(PDO $pdo): array {
  $rows = $pdo->query("SELECT name, inventory FROM resources WHERE type='equipment'")->fetchAll();
  $out = [];
  foreach ($rows as $r) $out[$r['name']] = max(0, (int)$r['inventory']);
  return $out;
}
$TOTAL_STOCK = fetch_total_stock($pdo);

/* Checkbox IDs -> DB resource names (must match resources.name) */
$EQUIP_MAP = [
  'camera'        => 'Camera',
  'microphone'    => 'Microphone',
  'walkietalkie'  => 'Walkie Talkie',
  'speaker'       => 'Portable Speaker',
  'pa_dku'        => 'PA System DKU',
  'pa_dsp'        => 'PA System DSP',
  'wirelessmic'   => 'Wireless Microphone',
  'lcd'           => 'LCD Projector',
  'lcd_dku'       => 'LCD DKU',
  'lcd_dsp'       => 'LCD DSP',
];

/* Facilities (1 each) */
$FACILITIES = ['Bilik Podcast', 'Bilik Recording', 'TECC 1', 'TECC 2', 'TECC 3'];

/* ---------------- Availability helpers ---------------- */
function compute_availability(PDO $pdo, array $TOTAL_STOCK, string $borrow, string $return): array {
  $avail = $TOTAL_STOCK;
  $sql = "
    SELECT items_json
    FROM uidm_requests
    WHERE status IN ('pending','approved')
      AND (return_status IS NULL OR return_status <> 'returned')
      AND borrow_date <= ? AND return_date >= ?
  ";
  $q = $pdo->prepare($sql);
  $q->execute([$return, $borrow]);

  while ($row = $q->fetch()) {
    $json = $row['items_json'] ?? null;
    if (!$json) continue;
    $arr = json_decode($json, true);
    if (!is_array($arr)) continue;
    foreach ($arr as $it) {
      $name = trim($it['name'] ?? '');
      $qty  = (int)($it['quantity'] ?? 0);
      if ($name !== '' && $qty > 0 && isset($avail[$name])) {
        $avail[$name] -= $qty;
      }
    }
  }
  foreach ($avail as $k => $v) $avail[$k] = max(0, (int)$v);
  return $avail;
}

/* Room overlap check */
function facility_slot_taken(PDO $pdo, string $room, string $date, string $start, string $end): bool {
  $sql = "
    SELECT COUNT(*) c
    FROM uidm_requests
    WHERE room = ?
      AND borrow_date = ?
      AND status IN ('pending','approved')
      AND NOT (COALESCE(end_time,'00:00:00') <= ? OR COALESCE(start_time,'23:59:59') >= ?)
  ";
  $q = $pdo->prepare($sql);
  $q->execute([$room, $date, $start, $end]);
  return ((int)($q->fetch()['c'] ?? 0)) > 0;
}

/* ---------------- AJAX: stock pills ---------------- */
if (($_GET['ajax'] ?? '') === 'availability') {
  header('Content-Type: application/json; charset=utf-8');
  $borrow = trim($_GET['borrow'] ?? '');
  $return = trim($_GET['return'] ?? '');
  if ($borrow === '' || $return === '') { echo json_encode($TOTAL_STOCK); exit; }
  try {
    echo json_encode(compute_availability($pdo, $TOTAL_STOCK, $borrow, $return));
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

/* ---------------- Business rules ---------------- */
$minBorrowDate = (new DateTime('today'))->modify('+3 days')->format('Y-m-d');

/* ---------------- Handle POST ---------------- */
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $kind = $_POST['kind'] ?? 'internal';
    if (!in_array($kind, ['internal','external'], true)) $kind = 'internal';

    $full_name   = trim($_POST['fullName'] ?? $_SESSION['user']['name'] ?? '');
    $staff_id    = trim($_POST['staffId'] ?? '');
    $email       = trim($_POST['email'] ?? $_SESSION['user']['email'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $officePhone = trim($_POST['officePhone'] ?? '');
    $mobilePhone = trim($_POST['mobilePhone'] ?? $_SESSION['user']['phone'] ?? '');
    $confirm     = isset($_POST['confirmation']);

    // Peralatan-only fields (may be required depending on selection)
    $purpose     = trim($_POST['purpose'] ?? '');
    $borrow_date = trim($_POST['borrowDate'] ?? '');
    $return_date = trim($_POST['returnDate'] ?? '');
    $location    = trim($_POST['location'] ?? '');

    if ($full_name===''||$staff_id===''||$email===''||$department===''||
        $officePhone===''||$mobilePhone===''||!$confirm) {
      throw new Exception('Sila lengkapkan maklumat asas pemohon dan sahkan pengakuan.');
    }

    /* Equipment (optional) */
    $equip = [];
    foreach ($EQUIP_MAP as $id => $label) {
      if (isset($_POST[$id])) {
        $qty = max(1, (int)($_POST[$id.'_qty'] ?? 1));
        $equip[] = ['name' => $label, 'quantity' => $qty];
      }
    }
    $hasEquip = !empty($equip);

    /* Facility (optional) */
    $room       = null;
    $facDate    = null;
    $start_time = null;
    $end_time   = null;
    $facilityRaw = $_POST['facility'] ?? '';
    if ($facilityRaw !== '') {
      $room = trim($facilityRaw);
      if ($room !== '' && !in_array($room, $FACILITIES, true)) {
        throw new Exception('Fasiliti yang dipilih tidak sah.');
      }
    }

    if ($room) {
      $facDate    = trim($_POST['facilityDate'] ?? '');
      $start_time = trim($_POST['facilityStart'] ?? '');
      $end_time   = trim($_POST['facilityEnd'] ?? '');
      if ($facDate===''||$start_time===''||$end_time==='') {
        throw new Exception('Sila lengkapkan tarikh dan masa fasiliti.');
      }
      if (strtotime($facDate) < strtotime($minBorrowDate)) {
        throw new Exception('Tarikh fasiliti perlu sekurang-kurangnya 3 hari lebih awal (minimum: '.$minBorrowDate.').');
      }
      if (!preg_match('/^\d{2}:\d{2}/', $start_time) || !preg_match('/^\d{2}:\d{2}/', $end_time) || $end_time <= $start_time) {
        throw new Exception('Masa Tamat fasiliti mesti selepas Masa Mula.');
      }
      if (facility_slot_taken($pdo, $room, $facDate, $start_time, $end_time)) {
        $suggest = [];
        $d = new DateTime($facDate);
        for ($i=1; $i<=14 && count($suggest)<3; $i++) {
          $d->modify('+1 day');
          $day = $d->format('Y-m-d');
          if (!facility_slot_taken($pdo, $room, $day, $start_time, $end_time)) $suggest[] = $day;
        }
        $extra = $suggest ? (' Cadangan tarikh: '.implode(', ', $suggest).'.') : '';
        throw new Exception('Maaf, fasiliti tersebut telah ditempah pada masa tersebut.' . $extra);
      }
    }
    $hasFacility = ($room !== null && $room !== '');

    // Must choose at least one of them
    if (!$hasEquip && !$hasFacility) {
      throw new Exception('Sila pilih sekurang-kurangnya satu peralatan ATAU satu fasiliti (atau kedua-duanya).');
    }

    // If EQUIPMENT is selected, enforce peralatan rules & 3-day rule
    if ($hasEquip) {
      if ($purpose==='' || $borrow_date==='' || $return_date==='' || $location==='') {
        throw new Exception('Untuk peminjaman peralatan, sila isi Tujuan, Tarikh Peminjaman, Tarikh Pemulangan dan Lokasi.');
      }
      if (strtotime($borrow_date) < strtotime($minBorrowDate)) {
        throw new Exception('Tarikh Peminjaman (peralatan) mesti sekurang-kurangnya 3 hari lebih awal (minimum: '.$minBorrowDate.').');
      }
      if (strtotime($return_date) < strtotime($borrow_date)) {
        throw new Exception('Tarikh Pemulangan (peralatan) mesti pada/selepas Tarikh Peminjaman.');
      }

      // Stock check
      $available = compute_availability($pdo, $TOTAL_STOCK, $borrow_date, $return_date);
      foreach ($equip as $it) {
        $name = $it['name'];
        $qty  = (int)($it['quantity']);
        $left = (int)($available[$name] ?? 0);
        if ($qty > $left) {
          throw new Exception("Kuantiti \"$name\" melebihi stok tersedia. Diperlukan: $qty, Ada: $left.");
        }
      }
    } else {
      // No equipment: optionalize these fields
      $purpose     = ($purpose === '') ? null : $purpose;
      $borrow_date = ($borrow_date === '') ? null : $borrow_date;
      $return_date = ($return_date === '') ? null : $return_date;
      $location    = ($location === '') ? null : $location;
    }

    // If BOTH equipment & facility: keep them aligned to same borrow date
    if ($hasEquip && $hasFacility && $borrow_date && $facDate && $facDate !== $borrow_date) {
      throw new Exception('Jika menempah peralatan dan fasiliti bersama, Tarikh Fasiliti mesti sama dengan Tarikh Peminjaman.');
    }

    $items_json    = $hasEquip ? json_encode($equip, JSON_UNESCAPED_UNICODE) : null;
    $return_status = 'pending_return';

    $ins = $pdo->prepare("
      INSERT INTO uidm_requests
      (user_id, kind, full_name, staff_id, email, department, office_phone, mobile_phone, purpose,
       borrow_date, return_date, start_time, end_time, room, location, items_json, status, return_status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $ins->execute([
      $_SESSION['user']['id'], $kind, $full_name, $staff_id, $email, $department, $officePhone, $mobilePhone,
      $purpose ?: null,
      $borrow_date ?: ($hasFacility ? $facDate : null),
      $return_date ?: ($hasFacility ? $facDate : null),
      $start_time ?: null, $end_time ?: null,
      $room ?: null,
      $location ?: null,
      $items_json, 'pending', $return_status
    ]);

    $successMsg = 'Permohonan berjaya dihantar!';
  } catch (Throwable $e) {
    $errorMsg = $e->getMessage();
  }
}

?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Borang Peminjaman · MediaHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root{--bg:#0b1020;--card:#121932;--muted:#8892b0;}
    body{font-family:Inter,system-ui,Arial;background:var(--bg);color:#fff;min-height:100vh;margin:0;}
    .form-container{background-color:var(--card);border-radius:12px;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .text-muted{color:#9aa3b2}
    .dk-label{color:#e5e7eb}
    .check-chip{display:inline-flex;align-items:center;gap:.5rem;font-weight:600;color:#e5e7eb}
    .check-chip input[type=checkbox]{width:18px;height:18px;margin:0;accent-color:#6366f1}
    .stock-pill{display:inline-block;font-size:12px;line-height:1;padding:6px 10px;border-radius:9999px;font-weight:600;white-space:nowrap}
    .stock-pill.ok{background:#ecfeff;color:#075985;border:1px solid #a5f3fc}
    .stock-pill.low{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
    .stock-pill.none{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .input-dark{background:#0f172a;color:#e6edf3;border:1px solid #334155;border-radius:.5rem;padding:.5rem 1rem;width:100%}
    .input-dark:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.25)}
    .form-help{display:block;margin-top:6px;font-size:12px;color:#cbd5e1;line-height:1.25;}
    .radio-card{border:1px solid #334155;border-radius:.6rem;padding:.6rem 1rem;background:#0f172a;display:flex;align-items:center;gap:.6rem}
    .radio-card input{accent-color:#22c55e}
    .disabled-block{opacity:.45;pointer-events:none;filter:saturate(.2)}
    .section-title{font-size:1.125rem;font-weight:700;margin-top:1rem}
    .subtle{color:#9aa3b2;font-size:.875rem}
    .link{cursor:pointer;text-decoration:underline}

    /* === custom white calendar icon setup === */
    .date-wrap {
      position: relative;
      width: 100%;
    }
    .date-wrap .input-dark {
      padding-right: 2.5rem; /* room for icon */
      color-scheme: dark;    /* keeps text light in Firefox etc */
      -moz-appearance: textfield;
    }
    /* hide default picker icon in Chromium/WebKit but keep it clickable */
    .date-wrap .input-dark[type="date"]::-webkit-calendar-picker-indicator {
      opacity: 0;
      cursor: pointer;
      position: absolute;
      right: 0;
      width: 100%;
      height: 100%;
    }
    /* our visible white icon */
    .calendar-icon {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      color: #fff;
      pointer-events: none;
      opacity: 0.9;
    }
  </style>
</head>
<body>

  <nav class="bg-gradient-to-br from-blue-500 to-teal-400 shadow">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
      <a href="../home.php" class="text-xl font-bold text-white">MediaHub</a>
      <div class="text-sm text-white">Log masuk sebagai <span class="font-medium text-gray-200"><?= htmlspecialchars($_SESSION['user']['name']) ?></span></div>
    </div>
  </nav>

  <main class="max-w-6xl mx-auto px-4 py-10">
    <?php if ($successMsg): ?>
      <div class="mb-6 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
      <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="form-container">
      <h2 class="text-xl font-semibold text-white">Borang Peminjaman Alatan dan Fasiliti</h2>
      <p class="text-muted">Anda boleh membuat permohonan peminjaman dan tempahan <strong>peralatan</strong>, <strong>dan Fasiliti</strong></p>

      <form method="post" class="mt-8 space-y-8" id="applicationForm" novalidate>
        <input type="hidden" name="kind" value="internal" id="kind">

        <!-- Jenis (dalam / luar) -->
        <div class="grid md:grid-cols-2 gap-4">
          <label class="check-chip"><input type="checkbox" id="kind_external" onclick="setKind('external')"><span>Di Luar Politeknik</span></label>
          <label class="check-chip md:justify-self-end"><input type="checkbox" id="kind_internal" onclick="setKind('internal')" checked><span>Di Dalam Politeknik</span></label>
        </div>

        <!-- Maklumat pemohon -->
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium dk-label mb-2">Nama Pemohon</label><input type="text" name="fullName" value="<?= htmlspecialchars($_SESSION['user']['name']) ?>" required class="input-dark"></div>
          <div><label class="block text-sm font-medium dk-label mb-2">No. Staff</label><input type="text" name="staffId" required class="input-dark" placeholder="Contoh: S12345"></div>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium dk-label mb-2">Email</label><input type="email" name="email" value="<?= htmlspecialchars($_SESSION['user']['email']) ?>" required class="input-dark"></div>
          <div>
            <label class="block text-sm font-medium dk-label mb-2">Jabatan/Unit</label>
            <select name="department" required class="input-dark">
              <option value="">Pilih Jabatan</option>
              <?php
              $opts = [
                'Jabatan Kejuruteraan Mekanikal',
                'Jabatan Agroteknologi dan Bio-Industri',
                'Jabatan Perdagangan',
                'Jabatan Matematik, Sains Dan Komputer',
                'Jabatan Pengajian Am',
                'Lain-lain'
              ];
              foreach ($opts as $o) echo '<option>'.htmlspecialchars($o).'</option>';
              ?>
            </select>
          </div>
        </div>
        <div class="grid md:grid-cols-2 gap-4">
          <div><label class="block text-sm font-medium dk-label mb-2">No. Tel Pejabat</label><input type="tel" name="officePhone" required class="input-dark"></div>
          <div><label class="block text-sm font-medium dk-label mb-2">No. Tel Bimbit</label><input type="tel" name="mobilePhone" value="<?= htmlspecialchars($_SESSION['user']['phone'] ?? '') ?>" required class="input-dark"></div>
        </div>

        <!-- ===================== PERALATAN ===================== -->
        <section id="peralatan">
          <h3 class="section-title">Peralatan Yang Diperlukan</h3>
          <p class="subtle">Lengkapkan butiran peminjaman alatan.</p>

          <!-- Grid pilihan peralatan -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
            <?php foreach ($EQUIP_MAP as $id => $label): ?>
              <div class="flex flex-col p-3 border border-slate-700 rounded-lg bg-[#0f172a]">
                <div class="flex items-center gap-3">
                  <input type="checkbox" id="<?= $id ?>" name="<?= $id ?>" value="1" class="h-4 w-4 text-indigo-600 equip-cb" onclick="toggleQty('<?= $id ?>'); onEquipSelectionChanged();">
                  <label for="<?= $id ?>" class="text-sm font-medium text-white flex-1"><?= htmlspecialchars($label) ?></label>
                  <span id="pill_<?= $id ?>" class="stock-pill ok">Stock: –</span>
                  <input type="number" name="<?= $id ?>_qty" id="<?= $id ?>_qty" min="1" value="1" class="w-16 px-2 py-1 text-sm border rounded bg-white text-gray-900" disabled>
                </div>
                <small id="help_<?= $id ?>" class="text-xs text-gray-300 mt-1"></small>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Butiran Peralatan (only required if any equipment selected) -->
          <div id="equipDetails" class="mt-5 disabled-block">
            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium dk-label mb-2">Tarikh Peminjaman (Peralatan)</label>

                <div class="date-wrap">
                  <input
                    type="date"
                    name="borrowDate"
                    id="borrowDate"
                    min="<?= htmlspecialchars($minBorrowDate) ?>"
                    class="input-dark"
                    disabled
                  >
                  <svg class="calendar-icon" xmlns="http://www.w3.org/2000/svg" fill="none"
                       viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                </div>

                <span class="form-help">* Perlu ≥ 3 hari awal apabila meminjam peralatan.</span>
              </div>

              <div>
                <label class="block text-sm font-medium dk-label mb-2">Tarikh Pemulangan (Peralatan)</label>

                <div class="date-wrap">
                  <input
                    type="date"
                    name="returnDate"
                    id="returnDate"
                    class="input-dark"
                    disabled
                  >
                  <svg class="calendar-icon" xmlns="http://www.w3.org/2000/svg" fill="none"
                       viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                </div>

                <span class="form-help">* Mesti pada/selepas Tarikh Peminjaman.</span>
              </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4 mt-3">
              <div>
                <label class="block text-sm font-medium dk-label mb-2">Lokasi Penggunaan</label>
                <input type="text" name="location" id="location" class="input-dark" disabled>
              </div>
              <div>
                <label class="block text-sm font-medium dk-label mb-2">Tujuan</label>
                <textarea name="purpose" id="purpose" rows="3" class="input-dark" disabled></textarea>
              </div>
            </div>
          </div>
        </section>

        <!-- ===================== FASILITI ===================== -->
        <section id="fasiliti" class="mt-8">
          <h3 class="section-title">Fasiliti</h3>

          <div class="flex items-center justify-between mt-3">
            <div class="grid md:grid-cols-3 gap-3">
              <!-- Clear / none -->
              <label class="radio-card">
                <input type="radio" name="facility" value="" id="fac_none" checked>
                <span>Tiada Fasiliti</span>
              </label>

              <?php foreach ($FACILITIES as $fac): $id = strtolower(str_replace([' ','/'],['_','-'],$fac)); ?>
                <label class="radio-card">
                  <input type="radio" name="facility" value="<?= htmlspecialchars($fac) ?>" id="fac_<?= $id ?>">
                  <span><?= htmlspecialchars($fac) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <!-- Quick clear -->
            <button type="button" onclick="clearFacilitySelection()" class="text-sm link text-blue-300 hover:text-blue-200">
              Batalkan pilihan fasiliti
            </button>
          </div>

          <div class="grid md:grid-cols-3 gap-4 mt-4" id="facilityInputs" aria-disabled="true">
            <div>
              <label class="block text-sm font-medium dk-label mb-2">Tarikh Fasiliti</label>

              <div class="date-wrap">
                <input
                  type="date"
                  name="facilityDate"
                  id="facilityDate"
                  min="<?= htmlspecialchars($minBorrowDate) ?>"
                  class="input-dark"
                  disabled
                >
                <svg class="calendar-icon" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
              </div>

              <span class="form-help">* Perlu ≥ 3 hari awal. Jika meminjam peralatan, tarikh ini mesti sama.</span>
            </div>

            <div>
              <label class="block text-sm font-medium dk-label mb-2">Masa Mula</label>
              <input type="time" name="facilityStart" id="facilityStart" class="input-dark" disabled>
            </div>

            <div>
              <label class="block text-sm font-medium dk-label mb-2">Masa Tamat</label>
              <input type="time" name="facilityEnd" id="facilityEnd" class="input-dark" disabled>
              <span class="form-help">Masa tamat mesti selepas masa mula.</span>
            </div>
          </div>
        </section>

        <!-- Pengesahan -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-gray-900">
          <h4 class="text-lg font-semibold mb-4">PENGESAHAN/PERAKUAN PEMINJAM</h4>
          <div class="text-sm mb-4 leading-relaxed">
            <p class="mb-2">Dengan ini saya bersetuju:</p>
            <ul class="list-disc ml-5">
              <li>Menjaga peralatan dan menggunakannya untuk tujuan rasmi sahaja.</li>
              <li>Bertanggungjawab atas sebarang kerosakan/kehilangan akibat kecuaian.</li>
              <li>Memulangkan peralatan pada tarikh yang dipersetujui.</li>
            </ul>
          </div>
          <label class="inline-flex items-start">
            <input type="checkbox" name="confirmation" required class="mt-1 h-4 w-4 text-indigo-600">
            <span class="ml-3 text-sm">Saya bersetuju dengan syarat-syarat di atas dan mengesahkan maklumat adalah benar.</span>
          </label>
        </div>

        <div class="flex items-center justify-end gap-3">
          <a href="../home.php" class="px-6 py-2 border rounded-lg text-white border-slate-600 hover:bg-slate-800">Batal</a>
          <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow">Hantar Permohonan</button>
        </div>
      </form>
    </div>
  </main>

<script>
const MIN_BORROW = '<?= htmlspecialchars($minBorrowDate) ?>';
const EQUIP_IDS = <?= json_encode(array_keys($EQUIP_MAP)) ?>;

/* Toggle qty with checkbox */
function toggleQty(id){
  const cb  = document.getElementById(id);
  const qty = document.getElementById(id + '_qty');
  if (!cb || !qty) return;
  qty.disabled = !cb.checked;
  if (!cb.checked) qty.value = 1;
}

/* Kind toggle */
function setKind(val){
  const hidden = document.getElementById('kind');
  if(!hidden) return;
  hidden.value = val;
  const ext = document.getElementById('kind_external');
  const intl = document.getElementById('kind_internal');
  if(ext && intl){ ext.checked = (val === 'external'); intl.checked = (val === 'internal'); }
}

/* Helpers to enable/disable blocks */
function setBlockEnabled(blockEl, enabled){
  blockEl.classList.toggle('disabled-block', !enabled);
  blockEl.querySelectorAll('input,textarea,select').forEach(el=>{
    el.disabled = !enabled;
  });
}

/* Equipment selection watcher */
function anyEquipSelected(){
  return EQUIP_IDS.some(id => document.getElementById(id)?.checked);
}
function onEquipSelectionChanged(){
  const enabled = anyEquipSelected();
  const equipDetails = document.getElementById('equipDetails');
  if (equipDetails){
    setBlockEnabled(equipDetails, enabled);

    // Required only when equipment is selected
    const borrow = document.getElementById('borrowDate');
    const ret    = document.getElementById('returnDate');
    const loc    = document.getElementById('location');
    const purp   = document.getElementById('purpose');
    [borrow, ret, loc, purp].forEach(el => { if (el){ el.required = enabled; } });
    clampReturnMin();
    refreshAvailability(); // update pills once enabled
  }
}

/* Facility inputs enable/disable */
function facilityInputsEnabled(enabled){
  const wrap = document.getElementById('facilityInputs');
  if (!wrap) return;
  setBlockEnabled(wrap, enabled);
  wrap.querySelectorAll('input').forEach(el=>{
    el.required = !!enabled; // only required when a facility is selected
  });
}
function getSelectedFacility(){
  const r = document.querySelector('input[name="facility"]:checked');
  return r ? r.value : '';
}
function clearFacilitySelection(){
  document.querySelectorAll('input[name="facility"]').forEach(r=>{ r.checked = false; });
  const none = document.getElementById('fac_none');
  if (none) none.checked = true;
  facilityInputsEnabled(false);
}

/* Update stock pills (only meaningful when both dates present) */
async function refreshAvailability(){
  const b = document.getElementById('borrowDate')?.value || '';
  const r = document.getElementById('returnDate')?.value || '';
  if (!b || !r) {
    // show base/default
    EQUIP_IDS.forEach(id=>{
      const pill = document.getElementById('pill_' + id);
      if (pill){ pill.textContent = 'Stock: –'; pill.className = 'stock-pill ok'; }
      const help = document.getElementById('help_' + id);
      if (help) help.textContent = '';
      const cb  = document.getElementById(id);
      if (cb) cb.disabled = false;
    });
    return;
  }

  try {
    const qs = new URLSearchParams({ ajax:'availability', borrow:b, return:r });
    const res = await fetch(window.location.pathname + '?' + qs.toString(), { cache: 'no-store' });
    const data = await res.json();

    EQUIP_IDS.forEach(id => {
      const pill = document.getElementById('pill_' + id);
      const help = document.getElementById('help_' + id);
      const labelName = pill ? pill.previousElementSibling.textContent.trim() : '';
      const left = (labelName && data[labelName] !== undefined) ? data[labelName] : null;

      if (pill) {
        pill.classList.remove('ok','low','none');
        if (left === null) {
          pill.textContent = 'Stock: –';
          pill.classList.add('ok');
        } else {
          pill.textContent = 'Stock: ' + left;
          if (left > 10) pill.classList.add('ok');
          else if (left > 0) pill.classList.add('low');
          else pill.classList.add('none');
        }
      }

      const qty = document.getElementById(id + '_qty');
      const cb  = document.getElementById(id);
      if (qty && left !== null) {
        if (left <= 0) {
          cb.checked = false;
          cb.disabled = true;
          qty.value = 1;
          qty.disabled = true;
          if (help) help.textContent = 'Tiada stok untuk julat tarikh ini.';
        } else {
          cb.disabled = false;
          if (help) help.textContent = 'Maksimum tersedia: ' + left;
          if (parseInt(qty.value || '1', 10) > left) qty.value = left;
        }
      }
    });
  } catch (e) {
    console.error('availability error', e);
  }
}

/* Client-side validations & clamps */
document.addEventListener('DOMContentLoaded', function(){
  const form   = document.getElementById('applicationForm');
  const borrow = document.getElementById('borrowDate');
  const ret    = document.getElementById('returnDate');

  const facilityRadios = Array.from(document.querySelectorAll('input[name="facility"]'));
  const facilityDate = document.getElementById('facilityDate');
  const startTime    = document.getElementById('facilityStart');
  const endTime      = document.getElementById('facilityEnd');

  function clampReturnMin(){
    if (!ret) return;
    ret.min = (borrow && borrow.value) ? borrow.value : '';
    if (borrow && borrow.value && ret.value && ret.value < borrow.value) ret.value = borrow.value;
  }
  window.clampReturnMin = clampReturnMin; // so onEquipSelectionChanged() can call it

  function syncFacilityDateToBorrowIfBothSelected(){
    const hasFacility = getSelectedFacility() !== '';
    if (anyEquipSelected() && hasFacility && borrow && borrow.value && facilityDate && !facilityDate.disabled) {
      facilityDate.value = borrow.value;
    }
  }
  function validateFacilityTimes(showAlert){
    if (!startTime || !endTime || startTime.disabled || endTime.disabled) return true;
    startTime.setCustomValidity(''); endTime.setCustomValidity('');
    const s = startTime.value, e = endTime.value;
    if (s && e && e <= s) {
      const msg = 'Masa tamat mesti selepas masa mula.';
      endTime.setCustomValidity(msg);
      if (showAlert) alert(msg);
      return false;
    }
    return true;
  }

  // Init
  setBlockEnabled(document.getElementById('equipDetails'), false);
  facilityInputsEnabled(false);

  // Wire equipment change
  document.querySelectorAll('.equip-cb').forEach(cb=>{
    cb.addEventListener('change', onEquipSelectionChanged);
  });

  // Facility radios
  facilityRadios.forEach(r=>{
    r.addEventListener('change', ()=>{
      const hasFacility = getSelectedFacility() !== '';
      facilityInputsEnabled(hasFacility);
      syncFacilityDateToBorrowIfBothSelected();
    });
  });

  borrow && borrow.addEventListener('change', function(){
    clampReturnMin();
    refreshAvailability();
    syncFacilityDateToBorrowIfBothSelected();
  });
  ret && ret.addEventListener('change', function(){
    clampReturnMin();
    refreshAvailability();
  });
  ['change','blur'].forEach(evt=>{
    borrow && borrow.addEventListener(evt, refreshAvailability);
    ret && ret.addEventListener(evt, refreshAvailability);
  });

  startTime && startTime.addEventListener('change', () => validateFacilityTimes(false));
  endTime   && endTime.addEventListener('change',   () => validateFacilityTimes(false));

  form && form.addEventListener('submit', function(e){
    const errors = [];

    // Base required fields
    this.querySelectorAll('[required]').forEach(el=>{
      if (el.disabled) return;
      const isCheckbox = (el.type === 'checkbox');
      if ((isCheckbox && !el.checked) || (!isCheckbox && String(el.value).trim() === '')) {
        errors.push(el.name || 'field');
      }
    });

    // If equipment selected, ensure borrow/return logic
    if (anyEquipSelected()) {
      const b = borrow?.value || '';
      const r = ret?.value || '';
      if (!b || !r) errors.push('equip_dates_missing');
      if (b && r && r < b) errors.push('return_before_borrow');
      if (b && b < MIN_BORROW) errors.push('equip_min_3days');
    }

    // If facility selected, ensure times valid; if both selected, dates must match
    const hasFacility = getSelectedFacility() !== '';
    if (hasFacility) {
      if (facilityDate.value && anyEquipSelected() && borrow && borrow.value && facilityDate.value !== borrow.value) {
        errors.push('facility_must_equal_borrow');
      }
      if (!validateFacilityTimes(false)) errors.push('facility_time');
      if (facilityDate.value && facilityDate.value < MIN_BORROW) errors.push('facility_min_3days');
    }

    // Must pick at least one of the two groups
    if (!anyEquipSelected() && !hasFacility) errors.push('choose_one');

    if (errors.length) {
      e.preventDefault();
      let msg = 'Sila semak input anda.\n\nPastikan:\n';
      msg += '• Pilih sekurang-kurangnya peralatan atau fasiliti\n';
      msg += '• Jika pilih peralatan: isi Tarikh Peminjaman (≥ 3 hari awal), Tarikh Pemulangan, Lokasi dan Tujuan\n';
      msg += '• Jika pilih fasiliti: isi Tarikh & Masa, dan masa tamat selepas masa mula\n';
      msg += '• Jika pilih kedua-duanya: Tarikh Fasiliti mesti sama dengan Tarikh Peminjaman';
      alert(msg);
      return false;
    }
  });
});
</script>
</body>
</html>
