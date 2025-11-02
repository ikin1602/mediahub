<?php 
require __DIR__ . '/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
  $id = (int)$_POST['cancel_id'];
  $stmt = $pdo->prepare("
    UPDATE uidm_requests 
    SET status='cancelled' 
    WHERE id=? AND user_id=? AND status='pending'
  ");
  $stmt->execute([$id, $_SESSION['user']['id']]);
  header("Location: my_bookings.php"); 
  exit;
}

render_header('Permohonan Saya');

/** Convert items_json to readable list. */
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

/** Tarikh label */
function date_label(array $row): string {
  if ($row['kind'] === 'internal' || $row['kind'] === 'external') {
    $b = htmlspecialchars($row['borrow_date'] ?? '');
    $r = htmlspecialchars($row['return_date'] ?? '');
    if ($b && $r && $b !== $r) return $b . " → " . $r;
    return $b ?: ($r ?: '-');
  }
  return htmlspecialchars($row['booking_date'] ?? ($row['borrow_date'] ?? '-'));
}

/** Translate jenis */
function jenis_label(string $kind): string {
  return [
    'internal' => 'Pinjaman Dalaman',
    'external' => 'Pinjaman Luaran',
    'tecc'     => 'Tempahan Bilik TECC'
  ][$kind] ?? ucfirst($kind);
}

/** Translate status */
function status_label(string $st): string {
  return [
    'pending'        => 'Menunggu Kelulusan',
    'approved'       => 'Diluluskan',
    'rejected'       => 'Ditolak',
    'cancelled'      => 'Dibatalkan',
    'pending_return' => 'Menunggu Pulangan',
    'returned'       => 'Sudah Dipulangkan',
    'overdue'        => 'Lewat Pulang'
  ][$st] ?? ucfirst($st);
}
?>
<style>
  .table th, .table td { 
    text-align: left; 
    vertical-align: top; 
    padding: 10px;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 38px;
    min-width: 80px;
    padding: 0 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #fff !important;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  a.btn, button.btn { line-height: normal; }
  .btn:hover { opacity: 0.9; transform: translateY(-1px); }
  .btn-edit { background: linear-gradient(135deg, #3b82f6, #8b5cf6); }
  .btn-cancel { background: linear-gradient(135deg, #ef4444, #f97316); }

  /* Catatan styling (no box, clean text) */
  .note-text {
    color: #e5e7eb;
    font-size: .95rem;
    line-height: 1.5;
  }
  .note-dash { color:#8b949e; }
  .note-meta {
    color:#8b949e;
    font-size:.78rem;
    margin-top:.25rem;
    line-height: 1.4;
  }

  .note-meta strong {
    color: #f0f0f0;
  }

  /* Responsive table with horizontal scroll */
  @media (max-width: 768px) {
    .table { display: block; overflow-x: auto; white-space: nowrap; }
    .table th, .table td { white-space: normal; min-width: 120px; }
  }
</style>

<section class="section">
  <div class="kicker">Senarai Permohonan</div>
  <h2>Permohonan Saya</h2>
  <table class="table">
    <thead>
      <tr>
        <th>Jenis</th>
        <th>Sumber</th>
        <th>Tarikh</th>
        <th>Status</th>
        <th>Catatan</th>
        <th style="width: 180px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php
        $stmt = $pdo->prepare("
          SELECT 
            ur.id,
            ur.kind,
            ur.full_name,
            ur.status,
            ur.return_status,
            ur.borrow_date,
            ur.return_date,
            ur.location,
            ur.room,
            ur.booking_date,
            ur.start_time,
            ur.end_time,
            ur.items_json,
            ur.decision_reason,
            ur.decision_by,
            ur.decision_at,
            ur.updated_at,
            r.name AS resource_name
          FROM uidm_requests ur
          LEFT JOIN resources r ON r.id = ur.resource_id
          WHERE ur.user_id = ?
          ORDER BY ur.id DESC
        ");
        $stmt->execute([$_SESSION['user']['id']]);

        foreach ($stmt as $row):
          $date = date_label($row);

          // Sumber
          $itemsLabel = format_items_list($row['items_json'] ?? null);
          if ($itemsLabel !== '') {
            $resourceLabel = $itemsLabel;
          } elseif ($row['kind'] === 'tecc' && !empty($row['room'])) {
            $resourceLabel = $row['room'];
          } elseif (!empty($row['resource_name'])) {
            $resourceLabel = $row['resource_name'];
          } else {
            $resourceLabel = '-';
          }

          // Status text (add return date if returned)
          $statusText = status_label($row['status']);
          if ($row['status'] === 'approved' && $row['return_status'] === 'returned' && !empty($row['updated_at'])) {
            $returnDate = date('Y-m-d', strtotime($row['updated_at']));
            $statusText = "Dipulangkan: " . $returnDate;
          }

          // Catatan (justifikasi admin)
          $showNote = in_array($row['status'], ['approved','rejected'], true) && trim((string)$row['decision_reason']) !== '';
          $noteText = $showNote ? $row['decision_reason'] : '';
          $noteBy   = $showNote ? trim((string)($row['decision_by'] ?? '')) : '';
          $noteAt   = $showNote && !empty($row['decision_at']) ? date('Y-m-d H:i', strtotime($row['decision_at'])) : '';
      ?>
        <tr>
          <td><?= htmlspecialchars(jenis_label($row['kind'])) ?></td>
          <td><?= htmlspecialchars($resourceLabel) ?></td>
          <td><?= $date ?></td>
          <td><?= htmlspecialchars($statusText) ?></td>

          <td>
            <?php if ($showNote): ?>
              <div class="note-text"><?= nl2br(htmlspecialchars($noteText)) ?></div>
              <div class="note-meta">
                <?php if ($noteBy): ?>Oleh: <strong><?= htmlspecialchars($noteBy) ?></strong><br><?php endif; ?>
                <?php if ($noteAt): ?>Pada: <?= htmlspecialchars($noteAt) ?><?php endif; ?>
              </div>
            <?php else: ?>
              <span class="note-dash">—</span>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($row['status']==='pending'): ?>
              <div style="display:flex; gap:.5rem; flex-wrap: wrap;">
                <a class="btn btn-edit" href="edit_booking.php?id=<?= (int)$row['id'] ?>">Ubah</a>
                <form method="post" style="display:inline">
                  <button class="btn btn-cancel" name="cancel_id" value="<?= (int)$row['id'] ?>">Batal</button>
                </form>
              </div>
            <?php else: ?>
              <span class="meta" style="color:#888">Tiada tindakan</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
