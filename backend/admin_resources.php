<?php
require __DIR__ . '/config.php';
require_admin();

/* ---------------- CSRF ---------------- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

/* ---------------- Helpers ---------------- */
function slugify($text) {
  $text = strtolower(trim($text));
  // ganti ruang dan simbol → dash
  $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
  $text = trim($text, '-');
  // kekalkan hanya a-z 0-9 dan dash
  $text = preg_replace('~[^-a-z0-9]+~', '', $text);
  if ($text === '') {
    $text = 'item-' . substr(bin2hex(random_bytes(4)), 0, 6);
  }
  return $text;
}
function flash($msg) { $_SESSION['flash'] = $msg; }

/* ---------------- Create resource (equipment) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid request token."; exit;
  }

  $name      = trim($_POST['name'] ?? '');
  $inventory = (int)($_POST['inventory'] ?? 1);

  $errors = [];
  if ($name === '')   { $errors[] = "Nama diperlukan."; }
  if ($inventory < 1) { $errors[] = "Kuantiti mestilah sekurang-kurangnya 1."; }

  if (!$errors) {
    try {
      // jana slug unik
      $slug = slugify($name);

      $stmt = $pdo->prepare("SELECT COUNT(*) c FROM resources WHERE slug=?");
      $base = $slug;
      $i    = 2;
      while (true) {
        $stmt->execute([$slug]);
        if ((int)$stmt->fetch()['c'] === 0) break;
        $slug = $base . '-' . $i++;
      }

      // === FIX HERE: match your actual table columns ===
      // columns you REALLY have: slug, name, type, inventory
      $ins = $pdo->prepare("
        INSERT INTO resources (slug, name, type, inventory)
        VALUES (?, ?, 'equipment', ?)
      ");
      $ins->execute([$slug, $name, $inventory]);

      flash("Sumber “" . htmlspecialchars($name) . "” berjaya ditambah.");
      header("Location: admin_resources.php"); exit;

    } catch (Throwable $e) {
      $errors[] = "Gagal menambah sumber: " . htmlspecialchars($e->getMessage());
    }
  }
}

/* ---------------- Update quantity (single row) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_qty') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid request token."; exit;
  }
  $id  = (int)($_POST['id'] ?? 0);
  $qty = max(0, (int)($_POST['inventory'] ?? 0));

  if ($id > 0) {
    $upd = $pdo->prepare("UPDATE resources SET inventory=? WHERE id=?");
    $upd->execute([$qty, $id]);
    flash("Kuantiti dikemas kini.");
  }
  header("Location: admin_resources.php"); exit;
}

/* ---------------- Quick +1 / −1 (single row) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['inc','dec'], true)) {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400); echo "Invalid request token."; exit;
  }

  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $row = $pdo->prepare("SELECT inventory FROM resources WHERE id=?");
    $row->execute([$id]);
    if ($r = $row->fetch()) {
      $qty = (int)$r['inventory'];
      $qty = ($_POST['action'] === 'inc') ? ($qty + 1) : max(0, $qty - 1);
      $pdo->prepare("UPDATE resources SET inventory=? WHERE id=?")->execute([$qty, $id]);
      flash("Kuantiti dikemas kini.");
    }
  }
  header("Location: admin_resources.php"); exit;
}

/* ---------------- List resources (equipment only) ---------------- */
$stmt = $pdo->query("
  SELECT id, name, type, inventory, created_at
  FROM resources
  WHERE type='equipment'
  ORDER BY name ASC
");
$rows = $stmt->fetchAll();

render_header('Admin · Sumber Peralatan');
?>
<header class="section">
  <div class="kicker">Admin</div>
  <h2>Sumber Peralatan</h2>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>
</header>

<section class="section">
  <div class="grid-1">

    <!-- Add resource form -->
    <div class="card">
      <div class="body">
        <h3 style="margin:0 0 8px">Tambah Sumber</h3>

        <?php if (!empty($errors ?? [])): ?>
          <div class="alert">
            <?php foreach ($errors as $er) { echo htmlspecialchars($er) . "<br>"; } ?>
          </div>
        <?php endif; ?>

        <form method="post"
              style="display:grid;
                     grid-template-columns:1fr 140px 120px;
                     gap:8px;
                     align-items:end;
                     max-width:720px">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
          <input type="hidden" name="action" value="create">

          <div>
            <label for="name">Nama</label>
            <input
              id="name"
              name="name"
              value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
              required
              placeholder="cth: LCD DKU, LCD DSP, Kamera, Mikrofon">
          </div>

          <div>
            <label for="inventory">Kuantiti</label>
            <input
              id="inventory"
              name="inventory"
              type="number"
              min="1"
              value="<?= htmlspecialchars($_POST['inventory'] ?? '1') ?>"
              required>
          </div>

          <div>
            <button type="submit" class="btn" style="width:100%">Tambah</button>
          </div>
        </form>

        <p class="meta" style="margin-top:6px">
         Masukkan alatan yang ingin di tambah ke dalam sistem MediaHub.
        </p>
      </div>
    </div>

    <!-- List / manage equipment quantities -->
    <div class="card">
      <div class="body">
        <h3 style="margin:0 0 8px">Senarai Peralatan</h3>

        <table class="table">
          <thead>
            <tr>
              <th>Nama</th>
              <th style="width:240px">Kuantiti</th>
              <th style="width:140px">Dicipta</th>
              <th style="width:180px">Tindakan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>

                <td>
                  <form method="post" class="form-row" style="gap:6px">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                    <input type="hidden" name="action" value="update_qty">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                    <input
                      name="inventory"
                      type="number"
                      min="0"
                      value="<?= (int)$r['inventory'] ?>"
                      style="width:110px">

                    <button class="btn btn-outline" type="submit">Simpan</button>
                  </form>
                </td>

                <td><span class="meta"><?= htmlspecialchars($r['created_at']) ?></span></td>

                <td>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                    <input type="hidden" name="action" value="dec">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-outline" type="submit">-1</button>
                  </form>

                  <form method="post" style="display:inline;margin-left:6px">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                    <input type="hidden" name="action" value="inc">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn" type="submit">+1</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!$rows): ?>
          <p class="meta">Tiada sumber.</p>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>

<?php render_footer(); ?>