<?php
require __DIR__ . '/config.php';
require_admin();

// Simple search by email or name
$q = trim($_GET['q'] ?? '');
$sql = "SELECT id, name, email, phone, role, created_at FROM users";
$params = [];
if ($q !== '') {
  $sql .= " WHERE name LIKE ? OR email LIKE ?";
  $params = ["%$q%", "%$q%"];
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

render_header('Admin · Users');
?>
<header class="section">
  <div class="kicker">Admin</div>
  <h2>Carian Maklumat Pengguna</h2>
  <p class="meta">Lihat pengguna yang berdaftar. Gunakan carian untuk menapis mengikut nama/emel.</p>
  <form method="get" class="form-row" style="max-width:520px;margin-top:12px">
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Carian menggunakan Email...">
    <button class="btn" type="submit">Lakukan Carian</button>
  </form>
</header>

<section class="section">
  <div class="card">
    <div class="body">
      <table class="table">
        <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>No.Telefon</th><th>Pengguna</th><th>Tarikh</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['phone']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$users): ?><p class="meta">Tiada pengguna.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php render_footer(); ?>