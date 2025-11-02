<?php
require __DIR__ . '/config.php';

$pdo->exec("
  CREATE TABLE IF NOT EXISTS uidm_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    nama_pemohon VARCHAR(150) NOT NULL,
    jabatan VARCHAR(150) NOT NULL,
    tel_pejabat VARCHAR(50) NULL,
    tel_bimbit VARCHAR(50) NULL,
    tujuan TEXT NOT NULL,
    mula DATETIME NOT NULL,
    tamat DATETIME NOT NULL,
    lokasi VARCHAR(255) NOT NULL,
    items_json JSON NOT NULL,
    status ENUM('submitted','review','approved','rejected','returned') DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    CONSTRAINT fk_uidm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

function post($k,$d=''){ return trim($_POST[$k] ?? $d); }

$nama     = post('nama_pemohon');
$jabatan  = post('jabatan');
$telP     = post('tel_pejabat');
$telB     = post('tel_bimbit');
$tujuan   = post('tujuan');
$mula     = post('mula');
$tamat    = post('tamat');
$lokasi   = post('lokasi');

$item_nama = $_POST['item_nama'] ?? [];
$item_qty  = $_POST['item_qty'] ?? [];
$item_info = $_POST['item_info'] ?? [];

$errors = [];
if (!$nama)    $errors[] = 'Nama Pemohon diperlukan';
if (!$jabatan) $errors[] = 'Jabatan/Unit diperlukan';
if (!$tujuan)  $errors[] = 'Tujuan diperlukan';
if (!$mula)    $errors[] = 'Tarikh & Masa Mula diperlukan';
if (!$tamat)   $errors[] = 'Tarikh & Masa Tamat diperlukan';
if (!$lokasi)  $errors[] = 'Lokasi diperlukan';

if (strtotime($tamat) <= strtotime($mula)) {
  $errors[] = 'Masa Tamat mesti selepas Masa Mula';
}

$items = [];
for ($i=0; $i < count($item_nama); $i++) {
  $nm = trim($item_nama[$i] ?? '');
  if ($nm === '') continue;
  $qt = max(1, (int)($item_qty[$i] ?? 1));
  $inf = trim($item_info[$i] ?? '');
  $items[] = ['nama'=>$nm, 'qty'=>$qt, 'info'=>$inf];
}
if (empty($items)) $errors[] = 'Sekurang-kurangnya satu peralatan diperlukan';

if ($errors) {
  render_header('Borang UIDM', '..');
  echo '<section class="section"><div class="alert">';
  foreach ($errors as $e) echo htmlspecialchars($e) . '<br>';
  echo '</div><p><a class="btn" href="../equipment.php">Kembali</a></p></section>';
  render_footer(); exit;
}

$user_id = is_logged_in() ? (int)$_SESSION['user']['id'] : null;

$stmt = $pdo->prepare("
  INSERT INTO uidm_requests
  (user_id, nama_pemohon, jabatan, tel_pejabat, tel_bimbit, tujuan, mula, tamat, lokasi, items_json)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
  $user_id, $nama, $jabatan, $telP, $telB, $tujuan, $mula, $tamat, $lokasi,
  json_encode($items, JSON_UNESCAPED_UNICODE)
]);

render_header('Permohonan Dihantar', '..');
echo '<section class="section"><div class="card" style="padding:1rem">';
echo '<h3>Permohonan anda telah dihantar.</h3>';
echo '<p>Status awal: <strong>submitted</strong>. Pihak pentadbir akan menyemak dan memberi maklum balas.</p>';
echo '<p><a class="btn" href="../equipment.php">Kembali ke Equipment</a></p>';
echo '</div></section>';
render_footer();
