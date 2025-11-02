<?php
require __DIR__ . '/config.php';
require_admin();

/* ---------------- CSRF ---------------- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ---------------- Helpers ---------------- */
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

if (!function_exists('mh_kind_label')) {
  function mh_kind_label(string $kind): string {
    return [
      'internal' => 'Pinjaman Dalaman',
      'external' => 'Pinjaman Luaran',
      'tecc'     => 'Tempahan Bilik TECC',
      'facility' => 'Tempahan Fasiliti',
    ][$kind] ?? ucfirst($kind);
  }
}
if (!function_exists('mh_status_label')) {
  function mh_status_label(string $st): string {
    return [
      'pending'        => 'Menunggu Kelulusan',
      'approved'       => 'Diluluskan',
      'rejected'       => 'Ditolak',
      'cancelled'      => 'Dibatalkan',
      'pending_return' => 'Menunggu Pulangan',
      'returned'       => 'Sudah Dipulangkan',
      'overdue'        => 'Lewat Pulang',
      'na'             => 'Tiada',
    ][$st] ?? ucfirst($st);
  }
}
function time_range(?string $start, ?string $end): string {
  $s = $start ? substr($start, 0, 5) : '';
  $e = $end ? substr($end, 0, 5) : '';
  return trim($s . ($e ? "–$e" : ""));
}

/* -------- Email helper -------- */
if (!function_exists('mh_send_decision_email')) {
  function mh_send_decision_email(array $req, string $newStatus, string $reason): void {
    if (empty($req['email'])) return;

    $jenis   = mh_kind_label($req['kind'] ?? '');
    $statusB = mh_status_label($newStatus);

    $itemsText = (function($json){
      if (!$json) return '';
      $arr = json_decode($json, true);
      if (!is_array($arr)) return '';
      $out = [];
      foreach ($arr as $it) {
        $name = trim($it['name'] ?? $it['item'] ?? '');
        $qty  = (int)($it['quantity'] ?? $it['qty'] ?? 1);
        if ($name !== '') $out[] = $name . ($qty ? " ($qty)" : "");
      }
      return $out ? implode(', ', $out) : '';
    })($req['items_json'] ?? null);

    $roomText = trim($req['room'] ?? '');
    if ($roomText !== '' && $itemsText !== '')      $itemStr = $roomText.' • '.$itemsText;
    elseif ($roomText !== '')                        $itemStr = $roomText;
    elseif ($itemsText !== '')                       $itemStr = $itemsText;
    else                                             $itemStr = '-';

    $tarikh  = htmlspecialchars($req['borrow_date'] ?? '');
    if (!empty($req['return_date']) && $req['return_date'] !== $req['borrow_date']) {
      $tarikh .= " → " . htmlspecialchars($req['return_date']);
    }
    $masa = time_range($req['start_time'] ?? null, $req['end_time'] ?? null);

    $subject = "[MediaHub] Permohonan #".($req['id'] ?? '')." – ".$statusB;

    $html = '
      <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;max-width:640px;margin:auto;padding:16px">
        <h2 style="margin:0 0 8px">Makluman Keputusan Permohonan</h2>
        <p style="margin:0 0 12px">Assalamualaikum/Salam sejahtera <strong>'.htmlspecialchars($req['full_name']??'Pengguna').'</strong>,</p>
        <p style="margin:0 12px 12px 0">Permohonan anda (<strong>ID #'.htmlspecialchars((string)($req['id']??'')) .'</strong>) kini berstatus
          <strong>'.htmlspecialchars($statusB).'</strong>.
        </p>

        <table style="width:100%;border-collapse:collapse;margin:12px 0">
          <tr>
            <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;width:35%"><strong>Jenis</strong></td>
            <td style="padding:8px;border:1px solid #e5e7eb">'.htmlspecialchars($jenis).'</td>
          </tr>
          <tr>
            <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb"><strong>Item/Bilik</strong></td>
            <td style="padding:8px;border:1px solid #e5e7eb">'.htmlspecialchars($itemStr).'</td>
          </tr>
          <tr>
            <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb"><strong>Tarikh</strong></td>
            <td style="padding:8px;border:1px solid #e5e7eb">'.htmlspecialchars($tarikh).'</td>
          </tr>'.
          ($masa !== '' ? '
          <tr>
            <td style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb"><strong>Masa</strong></td>
            <td style="padding:8px;border:1px solid #e5e7eb">'.htmlspecialchars($masa).'</td>
          </tr>' : '').
        '</table>

        <p style="margin:16px 0 8px"><strong>Sebab/Justifikasi</strong></p>
        <div style="white-space:pre-wrap;border:1px solid #e5e7eb;background:#f9fafb;padding:10px;border-radius:8px">'.
          nl2br(htmlspecialchars($reason)).'
        </div>

        <p style="margin:16px 0 0">Terima kasih.<br>MediaHub</p>
      </div>';

    send_mail($req['email'], $subject, $html);
  }
}

/* ---------------- Actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid request token."; exit;
  }

  $action       = $_POST['action'] ?? '';
  $id           = (int)($_POST['id'] ?? 0);
  $reason       = trim($_POST['reason'] ?? '');
  $decider_name = trim($_POST['decider_name'] ?? '');

  if ($id > 0 && in_array($action, ['approve','reject','mark_picked_up','mark_returned'], true)) {
    $q = $pdo->prepare("SELECT * FROM uidm_requests WHERE id=?");
    $q->execute([$id]);
    $req = $q->fetch();

    if ($req) {
      $now          = now_mysql();
      $sessionAdmin = $_SESSION['user']['name'] ?? 'Admin';
      $adminName    = $decider_name !== '' ? $decider_name : $sessionAdmin;

      if ($action === 'approve') {
        if ($decider_name === '' || $reason === '') {
          $_SESSION['flash'] = "Sila isi Nama Pegawai dan Sebab/Justifikasi untuk kelulusan.";
          header("Location: admin_bookings.php"); exit;
        }
        if ($req['status'] !== 'pending') {
          $_SESSION['flash'] = "Hanya permohonan status 'pending' boleh diluluskan.";
        } else {
          $pdo->prepare("
            UPDATE uidm_requests
            SET status='approved',
                return_status = CASE WHEN items_json IS NOT NULL AND items_json <> '' THEN 'pending_return' ELSE 'na' END,
                decision_reason=?, decision_by=?, decision_at=?, updated_at=?
            WHERE id=?
          ")->execute([$reason, $adminName, $now, $now, $id]);

          mh_send_decision_email($req, 'approved', $reason);
          $_SESSION['flash'] = "Permohonan #$id telah diluluskan.";
        }

      } elseif ($action === 'reject') {
        if ($decider_name === '' || $reason === '') {
          $_SESSION['flash'] = "Sila isi Nama Pegawai dan Sebab/Justifikasi untuk penolakan.";
          header("Location: admin_bookings.php"); exit;
        }
        if (!in_array($req['status'], ['pending','approved'], true)) {
          $_SESSION['flash'] = "Hanya 'pending' atau 'approved' boleh ditolak.";
        } elseif (!empty($req['pickup_at'])) {
          $_SESSION['flash'] = "Tidak boleh menolak selepas peralatan telah diambil.";
        } elseif ($req['status'] === 'approved' && $req['return_status'] === 'returned') {
          $_SESSION['flash'] = "Tidak boleh menolak permohonan yang telah dipulangkan.";
        } else {
          $pdo->prepare("
            UPDATE uidm_requests
            SET status='rejected',
                return_status='na',
                decision_reason=?, decision_by=?, decision_at=?, updated_at=?
            WHERE id=?
          ")->execute([$reason, $adminName, $now, $now, $id]);

          mh_send_decision_email($req, 'rejected', $reason);
          $_SESSION['flash'] = "Permohonan #$id telah ditolak.";
        }

      } elseif ($action === 'mark_picked_up') {
        if ($req['status'] !== 'approved') {
          $_SESSION['flash'] = "Hanya permohonan yang diluluskan boleh ditanda 'Telah Diambil'.";
        } else {
          $pdo->prepare("UPDATE uidm_requests SET pickup_at=?, return_status='pending_return', updated_at=? WHERE id=?")
              ->execute([$now, $now, $id]);
          $_SESSION['flash'] = "Permohonan #$id ditanda 'Telah Diambil'.";
        }

      } elseif ($action === 'mark_returned') {
        if ($req['status'] !== 'approved') {
          $_SESSION['flash'] = "Hanya permohonan yang diluluskan boleh ditanda 'Sudah Dipulangkan'.";
        } else {
          // NOTE: we rely on updated_at as return timestamp (used for 'Dipulangkan Lewat')
          $pdo->prepare("UPDATE uidm_requests SET return_status='returned', updated_at=? WHERE id=?")
              ->execute([$now, $id]);
          $_SESSION['flash'] = "Permohonan #$id ditanda 'Sudah Dipulangkan'.";
        }
      }
    }
    header("Location: admin_bookings.php"); exit;
  }
}

/* ---------------- Filters & export ---------------- */
$search = trim($_GET['q'] ?? '');
$kind   = trim($_GET['kind'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$export = (($_GET['export'] ?? '') === 'csv');

$sql = "SELECT * FROM uidm_requests WHERE 1=1";
$params = [];

if ($search !== '') {
  $sql .= " AND (full_name LIKE ? OR email LIKE ? OR staff_id LIKE ?)";
  $kw = "%$search%"; $params[]=$kw; $params[]=$kw; $params[]=$kw;
}
if ($kind !== '' && in_array($kind, ['internal','external','tecc','facility'], true)) {
  $sql .= " AND kind = ?"; $params[] = $kind;
}
if ($status !== '' && in_array($status, ['pending','approved','rejected','cancelled'], true)) {
  $sql .= " AND status = ?"; $params[] = $status;
}
if ($from !== '') { $sql .= " AND borrow_date >= ?"; $params[] = $from; }
if ($to   !== '') { $sql .= " AND borrow_date <= ?"; $params[] = $to; }

/* Sort: newest on top */
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ---------------- Dashboard stats ---------------- */
$totStatus = $pdo->query("SELECT status, COUNT(*) c FROM uidm_requests GROUP BY status")
                 ->fetchAll(PDO::FETCH_KEY_PAIR);

$pendingCount   = (int)($totStatus['pending']    ?? 0);
$approvedCount  = (int)($totStatus['approved']   ?? 0);
$rejectedCount  = (int)($totStatus['rejected']   ?? 0);
$cancelledCount = (int)($totStatus['cancelled']  ?? 0);

$waitingReturn = (int)$pdo->query("
  SELECT COUNT(*) c FROM uidm_requests
  WHERE status='approved' AND return_status='pending_return'
")->fetch()['c'];

$returnedCnt = (int)$pdo->query("
  SELECT COUNT(*) c FROM uidm_requests
  WHERE status='approved' AND return_status='returned'
")->fetch()['c'];

/* Count only CURRENTLY overdue (not yet returned) */
$overdueCount = (int)$pdo->query("
  SELECT COUNT(*) c
  FROM uidm_requests
  WHERE status='approved'
    AND return_status='pending_return'
    AND CONCAT(return_date,' ',COALESCE(end_time,'23:59:59')) < NOW()
")->fetch()['c'];

/* ---------------- Next pending helper ---------------- */
$nextPendingId = null;
if ($pendingCount > 0) {
  $np = $pdo->query("
    SELECT id FROM uidm_requests
    WHERE status='pending'
    ORDER BY borrow_date ASC, start_time ASC, created_at ASC
    LIMIT 1
  ")->fetch();
  $nextPendingId = $np['id'] ?? null;
}

/* ---------------- CSV export ---------------- */
if ($export) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=requests.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, [
    'ID','Kind','Full Name','Staff ID','Email','Department','Office Phone','Mobile Phone',
    'Purpose','Borrow Date','Return Date','Start','End','Room','Items','Location',
    'Status','Return Status','Created','Updated','Decision Reason','Decision By','Decision At','Pickup At'
  ]);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['id'],$r['kind'],$r['full_name'],$r['staff_id'],$r['email'],$r['department'],
      $r['office_phone'],$r['mobile_phone'],$r['purpose'],$r['borrow_date'],$r['return_date'],
      $r['start_time']?substr($r['start_time'],0,5):'', $r['end_time']?substr($r['end_time'],0,5):'',
      $r['room'], format_items_list($r['items_json'] ?? null), $r['location'],
      $r['status'], $r['return_status'], $r['created_at'], $r['updated_at'],
      $r['decision_reason'] ?? '', $r['decision_by'] ?? '', $r['decision_at'] ?? '', $r['pickup_at'] ?? ''
    ]);
  }
  fclose($out); exit;
}

render_header('Dashboard Permohonan');
?>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  table td, table th, table td span, table td a { color:#000 !important; }
  button.bg-emerald-600, button.bg-rose-600, button.bg-indigo-600 { color:#fff !important; }
  #bookingModal .modal-head{background:#0f172a}
  #bookingModal .modal-head h3, #bookingModal .modal-close{color:#fff}
  #bookingModal .modal-body{max-height:70vh; overflow-y:auto;}
  #bookingModal .modal-foot{background:#0f172a}
  #bookingModal .card .body{background:#0f172a;color:#e6edf3;border-radius:16px}
  #bookingModal .card .body .kicker{color:#cbd5e1}
  #bookingModal .badge{background:rgba(255,255,255,.12);color:#e6edf3}
  #bookingModal .badge .dot{background:#e6edf3}
  .mmodal{display:none}
  .mmodal.show{display:flex}

  /* prevent page scroll when any modal is open */
  body.modal-open{ overflow: hidden; }
</style>

<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-2">
      <h2 class="text-3xl font-bold text-gray-900">Dashboard Permohonan</h2>
      <p class="text-gray-600">Pengurusan dan pemantauan semua permohonan kemudahan politeknik</p>
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="mt-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-2">
          <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <?php
        $statCards = [
          ['Jumlah Permohonan', (int)$pdo->query("SELECT COUNT(*) c FROM uidm_requests")->fetch()['c'], 'blue'],
          ['Menunggu Kelulusan',$pendingCount,  'amber'],
          ['Diluluskan',        $approvedCount, 'emerald'],
          ['Ditolak',           $rejectedCount, 'rose'],
          ['Dibatalkan',        $cancelledCount,'gray'],
          ['Menunggu Pulangan', $waitingReturn, 'indigo'],
          ['Sudah Dipulangkan', $returnedCnt,   'sky'],
          ['Lewat Pulang',      $overdueCount,  'orange'],
        ];
        $icons = [
          'blue'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586l6.121 6.121V19a2 2 0 01-2 2z',
          'amber'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 0 0118 0z',
          'emerald'=>'M5 13l4 4L19 7',
          'rose'=>'M6 18L18 6M6 6l12 12',
          'gray'=>'M6 18L18 6M6 6l12 12',
          'indigo'=>'M3 12h18M5 12l2 7h10l2-7',
          'sky'=>'M4 7h16M4 17h16M7 7v10',
          'orange'=>'M12 9v2m0 4h.01M4.93 4.93l14.14 14.14'
        ];
      ?>
      <?php foreach ($statCards as [$label,$num,$color]): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
          <div class="flex items-center">
            <div class="bg-<?= $color ?>-100 p-2 rounded-lg">
              <svg class="w-5 h-5 text-<?= $color ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icons[$color] ?>"/>
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-xs font-medium text-gray-600"><?= htmlspecialchars($label) ?></p>
              <p class="text-2xl font-bold text-gray-900"><?= (int)$num ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick toolbar -->
    <?php if ($pendingCount > 0): ?>
      <div class="mb-6 bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between">
        <div class="text-sm text-gray-700">
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-amber-100 text-amber-800 mr-2">●</span>
          <?= $pendingCount ?> pending <?= $pendingCount === 1 ? 'request' : 'requests' ?>
        </div>
        <div class="flex gap-2">
          <?php if ($nextPendingId): ?>
            <button type="button"
              class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm open-decision"
              data-id="<?= (int)$nextPendingId ?>" data-action="approve">
              Approve next pending
            </button>
          <?php endif; ?>
          <a class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm" href="?<?= http_build_query(array_merge($_GET, ['status'=>'pending'])) ?>">Show pending only</a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
      <div class="grid md:grid-cols-6 gap-3">
        <div class="md:col-span-2">
          <label class="text-xs text-gray-600">Cari (nama/email/staff)</label>
          <input name="q" value="<?= htmlspecialchars($search) ?>" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg" placeholder="cth: Abdul / abdul@... / S12345">
        </div>
        <div>
          <label class="text-xs text-gray-600">Jenis</label>
          <select name="kind" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg">
            <option value="">Semua</option>
            <option value="internal" <?= $kind==='internal'?'selected':'' ?>>Permohonan Dalam</option>
            <option value="external" <?= $kind==='external'?'selected':'' ?>>Permohonan Luar</option>
            <option value="tecc"     <?= $kind==='tecc'    ?'selected':'' ?>>TECC</option>
            <option value="facility" <?= $kind==='facility'?'selected':'' ?>>Fasiliti</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-600">Status</label>
          <select name="status" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg">
            <option value="">Semua</option>
            <option value="pending"   <?= $status==='pending'  ?'selected':'' ?>>Pending</option>
            <option value="approved"  <?= $status==='approved' ?'selected':'' ?>>Approved</option>
            <option value="rejected"  <?= $status==='rejected' ?'selected':'' ?>>Rejected</option>
            <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-600">Dari (Tarikh Pinjam)</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
          <label class="text-xs text-gray-600">Hingga</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg">
        </div>
      </div>
      <div class="mt-3 flex gap-2">
        <button class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg">Guna Filter</button>
        <a class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50" href="admin_bookings.php">Reset</a>
        <a class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50" href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>">Export CSV</a>
      </div>
    </form>

    <!-- Table -->
    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Pemohon</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Jenis</th>
            <th class="px-4 py-3">Item/Bilik</th>
            <th class="px-4 py-3">Tarikh</th>
            <th class="px-4 py-3">Masa</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Pulangan</th>
            <th class="px-4 py-3">Tindakan</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($rows as $r): ?>
          <?php
            $st      = $r['status'];
            $rst     = $r['return_status'];
            $picked  = !empty($r['pickup_at']);

            $badgeStatus = [
              'pending'  =>'bg-amber-100 text-amber-800',
              'approved' =>'bg-emerald-100 text-emerald-800',
              'rejected' =>'bg-rose-100 text-rose-800',
              'cancelled'=>'bg-gray-200 text-gray-800',
            ][$st] ?? 'bg-gray-100 text-gray-800';

            // Calculate due datetime & late days
            $dueDate = $r['return_date'] ?: $r['borrow_date'];
            $dueTime = $r['end_time'] ?: '23:59:59';
            $dueStr  = trim($dueDate . ' ' . $dueTime);
            $dueTs   = $dueStr ? strtotime($dueStr) : null;
            $nowTs   = time();
            $daysLate = null;

            if ($st === 'approved' && $dueTs) {
              if ($rst === 'returned') {
                $retTs = strtotime($r['updated_at'] ?? '');
                if ($retTs && $retTs > $dueTs) $daysLate = (int)ceil(($retTs - $dueTs) / 86400);
              } else {
                if ($picked && $nowTs > $dueTs) $daysLate = (int)ceil(($nowTs - $dueTs) / 86400);
              }
            }

            if ($st === 'approved') {
              if (!$picked) {
                $pulanganText     = 'Belum Diambil';
                $badgeReturnClass = 'bg-slate-100 text-slate-800';
              } else {
                if ($rst === 'returned') {
                  if ($daysLate) {
                    $pulanganText     = "Dipulangkan Lewat ({$daysLate} hari)";
                    $badgeReturnClass = 'bg-red-100 text-red-800';
                  } else {
                    $pulanganText     = 'Sudah Dipulangkan';
                    $badgeReturnClass = 'bg-sky-100 text-sky-800';
                  }
                } else {
                  if ($daysLate) {
                    $pulanganText     = "Lewat ({$daysLate} hari)";
                    $badgeReturnClass = 'bg-red-100 text-red-800';
                  } else {
                    $pulanganText     = 'Menunggu Pulangan';
                    $badgeReturnClass = 'bg-indigo-100 text-indigo-800';
                  }
                }
              }
            } else {
              $pulanganText     = 'Tiada';
              $badgeReturnClass = 'bg-gray-200 text-gray-800';
            }

            // Composite item/room label
            $roomLabel  = trim($r['room'] ?? '');
            $itemsLabel = format_items_list($r['items_json'] ?? null) ?: '';
            if     ($roomLabel !== '' && $itemsLabel !== '') $itemLabel = $roomLabel.' • '.$itemsLabel;
            elseif ($roomLabel !== '')                       $itemLabel = $roomLabel;
            elseif ($itemsLabel !== '')                      $itemLabel = $itemsLabel;
            else                                             $itemLabel = (!empty($r['activity_type']) ? $r['activity_type'] : '—');

            $canApprove  = ($st === 'pending');
            $canReject   = ($st === 'pending') || ($st === 'approved' && !$picked && $rst !== 'returned');
            $showPickup  = ($st === 'approved' && !$picked);
            $showReturn  = ($st === 'approved' && $picked && $rst !== 'returned');
            $finished    = ($st === 'approved' && $rst === 'returned') || in_array($st, ['rejected','cancelled'], true);

            $displayName = trim($r['full_name'] ?? '') ?: ($r['email'] ?? '—');
          ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3"><?= (int)$r['id'] ?></td>

            <td class="px-4 py-3">
              <a href="#detail" class="text-indigo-600 hover:text-indigo-800 view-booking"
                 data-id="<?= (int)$r['id'] ?>"
                 data-user="<?= htmlspecialchars($displayName) ?>"
                 data-email="<?= htmlspecialchars($r['email']) ?>"
                 data-staff_no="<?= htmlspecialchars($r['staff_id'] ?? '') ?>"
                 data-department="<?= htmlspecialchars($r['department'] ?? '') ?>"
                 data-phone="<?= htmlspecialchars($r['mobile_phone'] ?? ($r['office_phone'] ?? '')) ?>"
                 data-kind="<?= htmlspecialchars($r['kind']) ?>"
                 data-resource="<?= htmlspecialchars($itemLabel) ?>"
                 data-borrow="<?= htmlspecialchars($r['borrow_date']) ?>"
                 data-return="<?= htmlspecialchars($r['return_date'] ?? '') ?>"
                 data-date="<?= htmlspecialchars($r['borrow_date']) ?><?= ($r['return_date'] && $r['return_date']!==$r['borrow_date']) ? ' → '.htmlspecialchars($r['return_date']) : '' ?>"
                 data-start="<?= htmlspecialchars($r['start_time']?substr($r['start_time'],0,5):'') ?>"
                 data-end="<?= htmlspecialchars($r['end_time']?substr($r['end_time'],0,5):'') ?>"
                 data-created="<?= htmlspecialchars($r['created_at']) ?>"
                 data-status="<?= htmlspecialchars($r['status']) ?>"
                 data-returnstatus="<?= htmlspecialchars($r['return_status']) ?>"
                 data-purpose="<?= htmlspecialchars($r['purpose']) ?>"
                 data-decider="<?= htmlspecialchars($r['decision_by'] ?? '') ?>"
                 data-decided_at="<?= htmlspecialchars($r['decision_at'] ?? '') ?>"
                 data-reason="<?= htmlspecialchars($r['decision_reason'] ?? '') ?>"
                 data-picked="<?= $picked ? '1' : '0' ?>"
                 data-duestr="<?= htmlspecialchars($dueStr) ?>"
                 data-updated="<?= htmlspecialchars($r['updated_at'] ?? '') ?>">
                <?= htmlspecialchars($displayName) ?>
              </a>
            </td>

            <td class="px-4 py-3"><?= htmlspecialchars($r['email']) ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars(mh_kind_label($r['kind'])) ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($itemLabel) ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($r['borrow_date']) ?></td>
            <td class="px-4 py-3">
              <?= htmlspecialchars($r['start_time']?substr($r['start_time'],0,5):'') ?>
              <?= $r['end_time'] ? '–'.htmlspecialchars(substr($r['end_time'],0,5)) : '' ?>
            </td>

            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $badgeStatus ?>">
                <?= htmlspecialchars(mh_status_label($st)) ?>
              </span>
            </td>

            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $badgeReturnClass ?>">
                <?= htmlspecialchars($pulanganText) ?>
              </span>
            </td>

            <td class="px-4 py-3">
              <?php if ($finished): ?>
                <span class="text-gray-500 text-xs">Selesai</span>
              <?php else: ?>
                <form method="post" class="flex flex-wrap gap-2 items-center row-form">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="">
                  <input type="hidden" name="reason" value="">
                  <input type="hidden" name="decider_name" value="">

                  <?php if ($canApprove): ?>
                    <button type="button" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded open-decision" data-action="approve" data-id="<?= (int)$r['id'] ?>">Approve</button>
                  <?php endif; ?>

                  <?php if ($canReject): ?>
                    <button type="button" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded open-decision" data-action="reject" data-id="<?= (int)$r['id'] ?>">Reject</button>
                  <?php endif; ?>

                  <?php if ($showPickup): ?>
                    <button type="button" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded quick-action" data-action="mark_picked_up">Telah Diambil</button>
                  <?php endif; ?>

                  <?php if ($showReturn): ?>
                    <button type="button" class="px-3 py-1.5 border border-gray-300 rounded hover:bg-gray-50 quick-action" data-action="mark_returned">Tandakan Dipulangkan</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$rows): ?>
        <div class="p-6 text-sm text-gray-600">Tiada rekod dijumpai dengan filter semasa.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Detail Modal (view only) -->
<div id="bookingModal" class="fixed inset-0 hidden items-start justify-center bg-black/40 z-[2147483000] pt-20 md:pt-24">
  <div class="modal-card w-[900px] max-w-[95%] rounded-xl overflow-hidden">
    <div class="modal-head px-5 py-3 flex items-center">
      <h3 class="text-lg font-semibold">Butiran Permohonan</h3>
      <button class="modal-close ml-auto text-xl leading-none">&times;</button>
    </div>
    <div class="modal-body p-5 space-y-3 bg-white">
      <div class="card"><div class="body p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="meta mb-1"><strong>ID Permohonan:</strong> <span id="m-id">-</span></div>
            <div class="meta"><strong>Status:</strong>
              <span id="m-status" class="badge inline-flex items-center px-2 py-0.5 rounded-full text-xs">
                <span class="dot w-1.5 h-1.5 rounded-full mr-1.5"></span><span id="m-status-text"></span>
              </span>
            </div>
          </div>
          <div>
            <div class="meta mb-1"><strong>Jenis:</strong> <span id="m-jenis">-</span></div>
            <div class="meta"><strong>Tarikh Mohon:</strong> <span id="m-created">-</span></div>
          </div>
        </div>
        <div class="meta mt-3">
          <strong>Status Pulangan:</strong>
          <span id="m-return-state" class="badge inline-flex items-center px-2 py-0.5 rounded-full text-xs">
            <span class="dot w-1.5 h-1.5 rounded-full mr-1.5"></span><span id="m-return-text">-</span>
          </span>
        </div>
        <div class="meta mt-3"><strong>Keputusan Oleh:</strong> <span id="m-decider">-</span></div>
        <div class="meta"><strong>Tarikh Keputusan:</strong> <span id="m-decided-at">-</span></div>
        <div class="meta"><strong>Sebab/Justifikasi:</strong>
          <div id="m-reason" class="mt-1 whitespace-pre-wrap">-</div>
        </div>
      </div></div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="card"><div class="body p-4">
          <div class="kicker font-semibold uppercase tracking-wide text-xs mb-2">Maklumat Pemohon</div>
          <div class="meta mb-1"><strong>Nama:</strong> <span id="m-user">-</span></div>
          <div class="meta mb-1"><strong>Email:</strong> <span id="m-email">-</span></div>
          <div class="meta optional" id="m-staff-wrap" style="display:none"><strong>No. Staff:</strong> <span id="m-staff"></span></div>
          <div class="meta optional" id="m-dept-wrap"  style="display:none"><strong>Jabatan:</strong> <span id="m-dept"></span></div>
          <div class="meta optional" id="m-phone-wrap" style="display:none"><strong>Telefon:</strong> <span id="m-phone"></span></div>
        </div></div>
        <div class="card"><div class="body p-4">
          <div class="kicker font-semibold uppercase tracking-wide text-xs mb-2">Butiran Permohonan</div>
          <div class="meta mb-1"><strong>Item/Bilik:</strong> <span id="m-resource">-</span></div>
          <div class="meta mb-1"><strong>Tarikh:</strong> <span id="m-date">-</span></div>
          <div class="meta"><strong>Masa:</strong> <span id="m-start">-</span> – <span id="m-end">-</span></div>
        </div></div>
      </div>

      <div class="card"><div class="body p-4">
        <div class="kicker font-semibold uppercase tracking-wide text-xs mb-2">Tujuan</div>
        <div id="m-purpose" class="meta">-</div>
      </div></div>
    </div>
    <div class="modal-foot px-5 py-3 flex items-center gap-2">
      <button class="px-4 py-2 rounded-lg bg-white text-gray-800" id="m-close">Tutup</button>
    </div>
  </div>
</div>

<!-- DECISION MODAL (Approve & Reject share this) -->
<div id="decisionModal" class="mmodal fixed inset-0 z-[2147483000] items-center justify-center bg-black/40">
  <div class="bg-white w-full max-w-lg rounded-xl shadow-xl border border-gray-200">
    <div class="px-5 py-4 border-b">
      <h3 id="dm-title" class="text-lg font-semibold text-gray-900">Catatan</h3>
      <p class="text-sm text-gray-500 mt-1">Sila isi Nama Pegawai dan sebab/justifikasi bagi kelulusan atau penolakan permohonan.</p>
    </div>
    <form id="decisionForm" method="post" class="p-5 space-y-4">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="id" id="dm-id" value="">
      <input type="hidden" name="action" id="dm-action" value="approve">
      <div>
        <label class="block text-sm font-medium text-gray-700">Nama Pegawai</label>
        <input type="text" name="decider_name" id="dm-name" class="mt-1 w-full px-3 py-2 border rounded-lg" placeholder="cth: Admin One" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Sebab/Justifikasi</label>
        <textarea name="reason" id="dm-reason" rows="4" class="mt-1 w-full px-3 py-2 border rounded-lg" placeholder="Maklumat ini akan dihantar kepada pemohon melalui emel." required></textarea>
      </div>
      <div class="flex items-center justify-end gap-2 pt-2 border-t">
        <button type="button" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white" id="dm-cancel" >Batal</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white">Hantar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  /* ----- Detail modal ----- */
  const modal=document.getElementById('bookingModal');
  const closeBtns=[document.querySelector('.modal-close'), document.getElementById('m-close')];
  const show=()=>{ 
    modal.classList.remove('hidden'); 
    modal.classList.add('flex'); 
    document.body.classList.add('modal-open');     // prevent background scroll
  };
  const hide=()=>{ 
    modal.classList.add('hidden'); 
    modal.classList.remove('flex'); 
    document.body.classList.remove('modal-open');  // restore scroll
  };
  function setText(id,val){ const el=document.getElementById(id); if(el) el.textContent=val||'-'; }
  function setShow(id,showit){ const el=document.getElementById(id); if(el) el.style.display=showit?'':'none'; }
  function badgeClassFor(st){ return { pending:'badge-pending', approved:'badge-approved', rejected:'badge-rejected', cancelled:'badge' }[st] || 'badge'; }
  function jenisFromKind(k){ return k==='tecc' ? 'Tempahan Bilik TECC' : (k==='external' ? 'Peminjaman Alatan Luar' : (k==='facility'?'Tempahan Fasiliti':'Peminjaman Dalaman')); }

  function calcDaysLate(dueStr, retStatus, updatedAt){
    if (!dueStr) return 0;
    const due = new Date(dueStr.replace(' ', 'T'));
    if (isNaN(due)) return 0;
    const compareTo = (retStatus === 'returned' && updatedAt)
      ? new Date((updatedAt||'').replace(' ', 'T'))
      : new Date();
    if (isNaN(compareTo)) return 0;
    const ms = compareTo - due;
    if (ms <= 0) return 0;
    return Math.ceil(ms / (1000*60*60*24));
  }

  document.querySelectorAll('.view-booking').forEach(a=>{
    a.addEventListener('click', e=>{
      e.preventDefault();
      const d=a.dataset, st=(d.status||'').toLowerCase();

      setText('m-id', d.id);
      setText('m-created', d.created);
      setText('m-jenis', jenisFromKind(d.kind));

      const statusEl=document.getElementById('m-status');
      statusEl.className='badge '+badgeClassFor(st);
      document.getElementById('m-status-text').textContent=st.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());

      setText('m-user', d.user);
      setText('m-email', d.email);
      if (d.staff_no){ setText('m-staff', d.staff_no); setShow('m-staff-wrap', true);} else setShow('m-staff-wrap', false);
      if (d.department){ setText('m-dept', d.department); setShow('m-dept-wrap', true);} else setShow('m-dept-wrap', false);
      if (d.phone){ setText('m-phone', d.phone); setShow('m-phone-wrap', true);} else setShow('m-phone-wrap', false);

      setText('m-resource', d.resource);
      setText('m-date', d.date);
      setText('m-start', d.start);
      setText('m-end', d.end);
      setText('m-purpose', d.purpose);

      setText('m-decider', d.decider || '-');
      setText('m-decided-at', d.decided_at || '-');
      document.getElementById('m-reason').textContent = d.reason || '-';

      const retStatus  = (st==='approved') ? (d.returnstatus||'pending_return') : 'na';
      const daysLate   = calcDaysLate(d.duestr||'', retStatus, d.updated||'');
      let retText;

      if (st !== 'approved') {
        retText = 'Tiada';
      } else if (retStatus === 'returned') {
        retText = daysLate ? `Dipulangkan Lewat (${daysLate} hari)` : 'Sudah Dipulangkan';
      } else if (d.picked === '0') {
        retText = 'Belum Diambil';
      } else {
        retText = daysLate ? `Lewat (${daysLate} hari)` : 'Menunggu Pulangan';
      }
      setText('m-return-text', retText);

      show();
    });
  });

  closeBtns.forEach(b=>b&&b.addEventListener('click', hide));
  modal.addEventListener('click', e=>{ if(e.target===modal) hide(); });

  /* ----- Decision modal (Approve & Reject) ----- */
  const decisionModal = document.getElementById('decisionModal');
  const decisionForm  = document.getElementById('decisionForm');
  const dmId          = document.getElementById('dm-id');
  const dmAction      = document.getElementById('dm-action');
  const dmTitle       = document.getElementById('dm-title');
  const dmName        = document.getElementById('dm-name');
  const dmReason      = document.getElementById('dm-reason');
  const dmCancel      = document.getElementById('dm-cancel');

  function openDecision(id, action){
    dmId.value = id;
    dmAction.value = action;
    dmTitle.textContent = action === 'approve' ? 'Luluskan Permohonan' : 'Tolak Permohonan';
    dmName.value = '';
    dmReason.value = '';
    decisionModal.classList.add('show');
    document.body.classList.add('modal-open');   // prevent background scroll
  }
  function closeDecision(){ 
    decisionModal.classList.remove('show'); 
    document.body.classList.remove('modal-open'); // restore scroll
  }

  document.querySelectorAll('.open-decision').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.dataset.id || this.closest('tr').querySelector('input[name="id"]')?.value;
      const action = this.dataset.action || 'approve';
      openDecision(id, action);
    });
  });
  dmCancel.addEventListener('click', function(e){ e.preventDefault(); closeDecision(); });
  decisionModal.addEventListener('click', function(e){ if(e.target===decisionModal) closeDecision(); });

  decisionForm.addEventListener('submit', function(e){
    if (!dmName.value.trim() || !dmReason.value.trim()){
      e.preventDefault(); alert('Nama Pegawai dan Sebab/Justifikasi diperlukan.'); return;
    }
    // submit normally
  });

  /* ----- Quick actions (no popup) for Telah Diambil / Dipulangkan ----- */
  document.querySelectorAll('.quick-action').forEach(btn=>{
    btn.addEventListener('click', function(){
      const form = this.closest('.row-form');
      form.querySelector('input[name="action"]').value = this.dataset.action;
      form.submit();
    });
  });
})();
</script>

<?php render_footer(); ?>
