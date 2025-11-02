<?php
require __DIR__ . '/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $phone = trim($_POST['phone'] ?? '');

  if ($name === '') $errors[] = "Name is required.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
  if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";

  if (!$errors) {
    try {
      // check duplicate email
      $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
        $errors[] = "Email is already registered.";
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, 'user')");
        $stmt->execute([$name, $email, $hash, $phone]);
        $success = true;
      }
    } catch (Exception $e) {
      $errors[] = "Registration failed: " . htmlspecialchars($e->getMessage());
    }
  }
}

render_header('Register');
?>
<section class="grid-2 section">
  <form method="post" action="">
    <h2>Cipta Akaun</h2>
    <?php if ($success): ?>
      <div class="alert">Akaun telah dicipta! Anda kini boleh <a href="login.php">log masuk</a>.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert"><?php foreach ($errors as $er) echo htmlspecialchars($er) . "<br>"; ?></div>
    <?php endif; ?>
    <label>Nama Penuh</label>
    <input name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    <label>Nombor Telefon</label>
    <input name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    <label>Kata Laluan</label>
    <input type="password" name="password" required>
    <button class="btn" type="submit">Daftar</button>
  </form>
</section>
<?php render_footer(); ?>