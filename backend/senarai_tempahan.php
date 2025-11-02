<?php
require __DIR__ . '/config.php';
// public page (no require_login)

render_header('Senarai Tempahan');

/* Helper: items_json -> "Camera (2), Wireless Microphone (1)" */
function format_items_list(?string $json): string {
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
}

/* Ambil pinjaman aktif: diluluskan & belum dipulangkan */
$stmt = $pdo->query("
  SELECT 
    r.id,
    u.name        AS user_name,
    r.items_json,
    r.room,
    r.borrow_date,
    r.return_date,
    r.start_time,
    r.end_time,
    r.return_status
  FROM uidm_requests r
  JOIN users u ON u.id = r.user_id
  WHERE r.status = 'approved'
    AND r.return_status IN ('pending_return','overdue')  -- masih dipinjam
  ORDER BY r.return_date ASC, r.id ASC
");
$rows = $stmt->fetchAll();
?>

<section class="section">
  <h2>Senarai Tempahan Aktif</h2>
  <p class="meta">Hanya pinjaman yang masih belum dipulangkan akan dipaparkan.</p>

  <?php if ($rows): ?>
    <div class="request-grid">
      <?php foreach ($rows as $r): ?>
        <?php
          $items = format_items_list($r['items_json'] ?? null);
          $room  = trim($r['room'] ?? '');
          // Gabung bilik + item jika kedua-duanya ada
          if ($room !== '' && $items !== '')      $resourceLabel = $room.' • '.$items;
          elseif ($room !== '')                   $resourceLabel = $room;
          elseif ($items !== '')                  $resourceLabel = $items;
          else                                    $resourceLabel = '-';

          $badgeClass = ($r['return_status']==='overdue') ? 'overdue' : 'pending_return';
          $badgeText  = ($r['return_status']==='overdue') ? 'Lewat Pulang' : 'Sedang Dipinjam';
        ?>
        <div class="request-card">
          <div class="request-header">
            <h4><?= htmlspecialchars($resourceLabel) ?></h4>
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span>
          </div>
          <p><strong>Peminjam:</strong> <?= htmlspecialchars($r['user_name']) ?></p>
          <p><strong>Tarikh Pulang:</strong> <?= htmlspecialchars($r['return_date'] ?? '-') ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="meta">Tiada tempahan aktif buat masa ini.</p>
  <?php endif; ?>
</section>

<?php render_footer(); ?>

<style>
/* ===== Grid layout ===== */
.request-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

/* ===== Each card ===== */
.request-card {
  background: #1d263f;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  transition: transform .2s ease, box-shadow .2s ease;
}
.request-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

/* ===== Header row ===== */
.request-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .8rem;
}
.request-header h4 {
  font-size: 1.05rem;
  color: #fff;
  margin: 0;
}

/* =====*
