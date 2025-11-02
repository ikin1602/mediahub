<?php
require __DIR__ . '/config.php';

$errors = [];

// Already logged in? send them where they belong.
if (is_logged_in()) {
  if (is_admin()) { header('Location: admin_bookings.php'); exit; }
  header('Location: ../home.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email       = strtolower(trim($_POST['email'] ?? ''));
  $password    = $_POST['password'] ?? '';
  $adminLogin  = isset($_POST['admin_login']); // <-- clicked Admin Login?

  // Check if login is for pre-made admin
  if ($email === 'admin1' && $password === 'admin123') {
    // Log in the admin directly
    session_regenerate_id(true);
    $_SESSION['user'] = [
      'id'    => 1,             // Hardcoded ID for the admin
      'name'  => 'Admin',       // Admin name
      'email' => 'admin1',      // Admin email/username
      'phone' => '',            // No phone for admin in this case
      'role'  => 'admin',       // Admin role
    ];

    header('Location: admin_bookings.php');
    exit;
  }

  if ($email === '' || $password === '') {
    $errors[] = 'Please enter both email and password.';
  } else {
    try {
      $stmt = $pdo->prepare(
        "SELECT id, name, email, phone, role, password_hash
         FROM users
         WHERE LOWER(email) = ?"
      );
      $stmt->execute([$email]);
      $user = $stmt->fetch();

      if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
          'id'    => $user['id'],
          'name'  => $user['name'],
          'email' => $user['email'],
          'phone' => $user['phone'],
          'role'  => $user['role'],
        ];

        // If "Admin Login" was clicked, require admin role and go to admin_bookings.php
        if ($adminLogin) {
          if (is_admin()) { header('Location: admin_bookings.php'); exit; }
          // Logged in but not an admin
          $errors[] = 'This account is not an admin.';
          // log them out to avoid half-logged-in state for non-admin trying admin
          session_unset(); session_destroy();
        } else {
          // Normal login: route by role
          if (is_admin()) { header('Location: admin_bookings.php'); exit; }
          header('Location: ../home.php'); exit;
        }
      } else {
        $errors[] = 'Invalid credentials.';
      }
    } catch (Exception $e) {
      $errors[] = 'Login failed: ' . htmlspecialchars($e->getMessage());
    }
  }
}

// From backend/, pass base=".."
render_header('Login', '..');
?>
<section class="grid-2 section">
  <form method="post" action="">
    <h2>Login</h2>

    <?php if ($errors): ?>
      <div class="alert">
        <?php foreach ($errors as $er) { echo htmlspecialchars($er) . "<br>"; } ?>
      </div>
    <?php endif; ?>

    <label for="email">Email</label>
    <input id="email" type="email" name="email" required autofocus>

    <label for="password">Password</label>
    <input id="password" type="password" name="password" required>

    <div style="display:flex; gap:.5rem; align-items:center; margin-top:.75rem;">
      <button class="btn" type="submit" name="user_login" value="1">Log Masuk</button>
    </div>

    <p class="meta" style="margin-top:.75rem">
      Tidak mempunyai akaun? <a href="register.php">Daftar Akaun</a>.
    </p>
  </form>

  <div class="section"></div>
</section>
<?php render_footer(); ?>
