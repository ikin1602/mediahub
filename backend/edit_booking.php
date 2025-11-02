<?php
// backend/edit_booking.php
require __DIR__ . '/config.php';
require_login();

if (empty($_GET['id'])) { header("Location: my_bookings.php"); exit; }
$id = (int)$_GET['id'];

/* CSRF */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* Fetch the request and ensure it's this user's and still pending (initial load) */
$fetch = $pdo->prepare("
  SELECT ur.*, r.name AS resource_name, r.slug AS resource_slug
  FROM uidm_requests ur
  LEFT JOIN resources r ON r.id = ur.resource_id
  WHERE ur.id = ? AND ur.user_id = ?
  LIMIT 1
");
$fetch->execute([$id, $_SESSION['user']['id']]);
$req = $fetch->fetch();

if (!$req) {
  render_header('Ubah Permohonan');
  echo "<section class='section'><div class='alert'>Permohonan tidak ditemui atau bukan milik akaun anda.</div><p><a class='btn' href='my_bookings.php'>Kembali</a></p></section>";
  render_footer(); exit;
}
if ($req['status'] !== 'pending') {
  render_header('Ubah Permohonan');
  echo "<section class='section'><div class='alert'>Permohonan ini tidak boleh diubah kerana status semasa ialah '".htmlspecialchars($req['status'])."'.</div><p><a class='btn' href='my_bookings.php'>Kembali</a></p></section>";
  render_footer(); exit;
}

$successMsg = '';
$errorMsg = '';

/* Helper to decode items */
function decode_items(?string $json): array {
  if (!$json) return [];
  $arr = json_decode($json, true);
  return is_array($arr) ? $arr : [];
}

/* Map for equipment ids -> labels */
$equipMap = [
  'camera'        => 'Camera',
  'microphone'    => 'Microphone',
  'walkietalkie'  => 'Walkie Talkie',
  'speaker'       => 'Speaker',
  'pa_dku'        => 'PA System DKU',
  'pa_dsp'        => 'PA System DSP',
  'wirelessmic'   => 'Wireless Microphone',
  'lcd'           => 'LCD Projector',
];

/* Build current items index (name => qty) to prefill form */
$currentItems = [];
foreach (decode_items($req['items_json']) as $it) {
  $n = trim($it['name'] ?? $it['item'] ?? '');
  $q = (int)($it['quantity'] ?? $it['qty'] ?? 1);
  if ($n !== '') $currentItems[$n] = max(1, $q);
}

/* On POST: validate & update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
      throw new Exception('Sesi tidak sah. Sila muat semula halaman.');
    }

    $kind = $_POST['kind'] ?? 'internal';
    if (!in_array($kind, ['internal','external'], true)) $kind = 'internal';

    $full_name   = trim($_POST['fullName'] ?? '');
    $staff_id    = trim($_POST['staffId'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $officePhone = trim($_POST['officePhone'] ?? '');
    $mobilePhone = trim($_POST['mobilePhone'] ?? '');
    $purpose     = trim($_POST['purpose'] ?? '');

    if ($full_name === '' || $staff_id === '' || $email === '' || $department === '' ||
        $officePhone === '' || $mobilePhone === '' || $purpose === '') {
      throw new Exception('Sila lengkapkan semua medan wajib.');
    }

    $borrow_date = $_POST['borrowDate'] ?? '';
    $return_date = $_POST['returnDate'] ?? '';
    $location    = trim($_POST['location'] ?? '');

    if ($borrow_date === '' || $return_date === '' || $location === '') {
      throw new Exception('Tarikh pinjam, tarikh pulang dan lokasi adalah wajib.');
    }
    if (strtotime($return_date) < strtotime($borrow_date)) {
      throw new Exception('Tarikh pulang mesti selepas tarikh pinjam.');
    }

    // Equipment (at least one)
    $newItems = [];
    foreach ($equipMap as $idKey => $label) {
      if (isset($_POST[$idKey])) {
        $qty = max(1, (int)($_POST[$idKey.'_qty'] ?? 1));
        $newItems[] = ['name'=>$label, 'quantity'=>$qty];
      }
    }
    if (!$newItems) throw new Exception('Sila pilih sekurang-kurangnya satu peralatan.');

    $items_json = json_encode($newItems, JSON_UNESCAPED_UNICODE);

    // --- SAFER UPDATE with backticked column names ---
    $upd = $pdo->prepare("
      UPDATE `uidm_requests`
      SET 
        `kind`=?,
        `full_name`=?,
        `staff_id`=?,
        `email`=?,
        `department`=?,
        `office_phone`=?,
        `mobile_phone`=?,
        `purpose`=?,              -- backticked to ensure proper update
        `borrow_date`=?,
        `return_date`=?,
        `location`=?,
        `items_json`=?,
        `updated_at`=NOW()
      WHERE `id`=? AND `user_id`=? AND `status`='pending'
    ");
    $ok = $upd->execute([
      $kind, $full_name, $staff_id, $email, $department, $officePhone, $mobilePhone, $purpose,
      $borrow_date, $return_date, $location, $items_json,
      $id, $_SESSION['user']['id']
    ]);

    if (!$ok) {
      throw new Exception('Tidak dapat mengemas kini (ralat pangkalan data).');
    }

    if ($upd->rowCount() === 0) {
      // Could be: (a) values identical (no-op), (b) status changed by admin, or (c) not found / not owner.
      $chk = $pdo->prepare("SELECT `id`, `user_id`, `status` FROM `uidm_requests` WHERE `id`=? LIMIT 1");
      $chk->execute([$id]);
      $row = $chk->fetch();

      if (!$row) {
        throw new Exception('Permohonan tidak ditemui.');
      }
      if ((int)$row['user_id'] !== (int)$_SESSION['user']['id']) {
        throw new Exception('Permohonan ini bukan milik akaun anda.');
      }
      $curStatus = (string)$row['status'];

      if ($curStatus !== 'pending') {
        throw new Exception("Tidak dapat mengemas kini kerana status semasa ialah '".htmlspecialchars($curStatus, ENT_QUOTES, 'UTF-8')."'.");
      } else {
        // Still pending → it was just a no-op (same data)
        $successMsg = 'Tiada perubahan dibuat (data sama seperti sebelum ini).';
      }
    } else {
      $successMsg = 'Permohonan berjaya dikemas kini.';
    }

    // Refresh current data for the form after update
    $fetch->execute([$id, $_SESSION['user']['id']]);
    $req = $fetch->fetch();
    $currentItems = [];
    foreach (decode_items($req['items_json']) as $it) {
      $n = trim($it['name'] ?? $it['item'] ?? '');
      $q = (int)($it['quantity'] ?? $it['qty'] ?? 1);
      if ($n !== '') $currentItems[$n] = max(1, $q);
    }
  } catch (Throwable $e) {
    $errorMsg = $e->getMessage();
  }
}

/* Prefill helpers */
function sel($a,$b){ return (string)$a===(string)$b ? 'selected' : ''; }
function chk_item($label, $currentItems) { return array_key_exists($label, $currentItems) ? 'checked' : ''; }
function qty_item($label, $currentItems) { return (string)($currentItems[$label] ?? 1); }

render_header('Ubah Permohonan');
?>
<section class="section" style="max-width: 900px;">
  <div class="kicker">Ubah Permohonan</div>
  <h2>Permohonan #<?= (int)$req['id'] ?> — <?= htmlspecialchars($req['resource_name'] ?? 'Peralatan') ?></h2>

  <?php if (!empty($successMsg)): ?>
    <div class="alert success"><?= htmlspecialchars($successMsg) ?></div>
  <?php endif; ?>
  <?php if (!empty($errorMsg)): ?>
    <div class="alert"><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>

  <!-- ensure id stays in URL on submit -->
  <form method="post" action="edit_booking.php?id=<?= (int)$id ?>" class="form" id="editForm" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">

    <div class="grid-2">
      <div>
        <label>Jenis Permohonan</label>
        <select name="kind" required>
          <option value="internal" <?= sel($req['kind'],'internal') ?>>Di Dalam Politeknik</option>
          <option value="external" <?= sel($req['kind'],'external') ?>>Di Luar Politeknik</option>
        </select>
      </div>
      <div></div>
    </div>

    <div class="grid-2">
      <div>
        <label>Nama Pemohon</label>
        <input type="text" name="fullName" value="<?= htmlspecialchars($req['full_name']) ?>" required>
      </div>
      <div>
        <label>No. Staff</label>
        <input type="text" name="staffId" value="<?= htmlspecialchars($req['staff_id']) ?>" required>
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($req['email']) ?>" required>
      </div>
      <div>
        <label>Jabatan/Unit</label>
        <input type="text" name="department" value="<?= htmlspecialchars($req['department']) ?>" required>
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label>No. Tel Pejabat</label>
        <input type="tel" name="officePhone" value="<?= htmlspecialchars($req['office_phone']) ?>" required>
      </div>
      <div>
        <label>No. Tel Bimbit</label>
        <input type="tel" name="mobilePhone" value="<?= htmlspecialchars($req['mobile_phone']) ?>" required>
      </div>
    </div>

    <div>
      <label>Tujuan</label>
      <textarea name="purpose" rows="3" required><?= htmlspecialchars($req['purpose']) ?></textarea>
    </div>

    <fieldset class="card" style="padding:1rem">
      <legend style="font-weight:600">Peralatan Diperlukan</legend>
      <p class="meta" style="margin:.25rem 0 .75rem 0">Pilih sekurang-kurangnya satu peralatan dan nyatakan kuantiti.</p>

      <div class="grid-2">
        <?php foreach ($equipMap as $idKey => $label): 
          $checked = chk_item($label, $currentItems);
          $qty = qty_item($label, $currentItems);
        ?>
        <label class="check-chip" style="display:flex; align-items:center; gap:.5rem; border:1px solid #ddd; padding:.5rem; border-radius:.5rem">
          <input type="checkbox" name="<?= $idKey ?>" <?= $checked ?>>
          <span style="flex:1"><?= htmlspecialchars($label) ?></span>
          <input type="number" min="1" name="<?= $idKey ?>_qty" value="<?= htmlspecialchars($qty) ?>" style="width:80px">
        </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="grid-2">
      <div>
        <label>Tarikh Peminjaman</label>
        <input type="date" name="borrowDate" value="<?= htmlspecialchars($req['borrow_date']) ?>" required>
      </div>
      <div>
        <label>Tarikh Pemulangan</label>
        <input type="date" name="returnDate" value="<?= htmlspecialchars($req['return_date']) ?>" required>
      </div>
    </div>

    <div>
      <label>Lokasi Penggunaan</label>
      <input type="text" name="location" value="<?= htmlspecialchars($req['location']) ?>" required>
    </div>

    <div style="display:flex; gap:.5rem; justify-content:flex-end; margin-top:1rem">
      <a class="btn" href="my_bookings.php">Kembali</a>
      <button class="btn" type="submit">Simpan Perubahan</button>
    </div>
  </form>
</section>

<script>
// Minimal client-side checks mirroring server rules
document.getElementById('editForm').addEventListener('submit', function(e){
  const f = this;
  let errors = [];

  // required fields
  ['fullName','staffId','email','department','officePhone','mobilePhone','purpose','borrowDate','returnDate','location']
    .forEach(n => { const el = f.querySelector('[name="'+n+'"]'); if(!el || !String(el.value).trim()) errors.push(n); });

  // equipment: at least one checked
  const eqIds = ['camera','microphone','walkietalkie','speaker','pa_dku','pa_dsp','wirelessmic','lcd'];
  const anyChecked = eqIds.some(id => f.querySelector('input[name="'+id+'"]')?.checked);
  if (!anyChecked) errors.push('equipment');

  // date order
  const b = f.borrowDate.value, r = f.returnDate.value;
  if (b && r && new Date(r) < new Date(b)) errors.push('date');

  if (errors.length) {
    e.preventDefault();
    alert('Sila lengkapkan semua medan dan pastikan sekurang-kurangnya satu peralatan dipilih serta tarikh pulang selepas tarikh pinjam.');
  }
});
</script>

<?php render_footer(); ?>
