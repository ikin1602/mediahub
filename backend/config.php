<?php
/* ============================================
   backend/config.php
   ============================================ */

/* ---------------- DATABASE CONFIG ---------------- */
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'mediahub';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (Exception $e) {
  http_response_code(500);
  echo "Database connection failed. Please check backend/config.php. Error: " . htmlspecialchars($e->getMessage());
  exit;
}

/* ---------------- SESSION ---------------- */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ---------------- AUTH HELPERS ---------------- */
function is_logged_in() { return isset($_SESSION['user']); }
function is_admin()     { return is_logged_in() && (($_SESSION['user']['role'] ?? '') === 'admin'); }
function require_login(){ if (!is_logged_in()) { header("Location: login.php"); exit; } }
function require_admin(){ if (!is_admin())     { header("Location: login.php"); exit; } }

/* ---------------- LAYOUT HELPERS ---------------- */
function render_header($title = "MediaHub", $base = "..") {
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
  echo "<title>" . htmlspecialchars($title) . " · MEDIAHUB</title>";

  // Site CSS
  echo '<link rel="stylesheet" href="'. htmlspecialchars($base) .'/assets/styles.css">';

  // ----- FAVICONS (place files in /asset) -----
 // ----- High-Resolution Favicons -----
echo '<link rel="icon" type="image/png" sizes="192×192" href="'. htmlspecialchars($base) .'/asset/mlogo.png">';
echo '<link rel="apple-touch-icon" sizes="192×192" href="'. htmlspecialchars($base) .'/asset/mlogo.png">';

  echo '</head><body>';

  // Top Navigation
  echo '<nav class="nav"><div class="nav-inner">';
  echo '<a href="'. htmlspecialchars($base) .'/home.php" class="brand" style="display:flex;align-items:center;gap:8px;text-decoration:none;">';
  echo '<img src="'. htmlspecialchars($base) .'/asset/hublogo.png" alt="MediaHub Logo" style="width:100px;height:auto;border-radius:6px;">';
  echo '<span style="font-weight:700;letter-spacing:.5px;color:#4fd1c5;">MEDIAHUB</span>';
  echo '</a>';

  echo '<div class="navlinks">';
  // Public links (clean topbar – no "Alatan", no "Admin")
  echo '<a href="'. htmlspecialchars($base) .'/about.php">Tentang Kami</a>';
  echo '<a href="'. htmlspecialchars($base) .'/backend/senarai_tempahan.php">Senarai Tempahan</a>';

  // Member links
  if (is_logged_in() && !is_admin()) {
    echo '<a href="'. htmlspecialchars($base) .'/backend/my_bookings.php">Tempahan Saya</a>';
  }

  if (is_logged_in()) {
    if (is_admin()) {
      // Admin sees only these two
      echo '<a href="'. htmlspecialchars($base) .'/backend/admin_bookings.php">Permohonan</a>';
      echo '<a href="'. htmlspecialchars($base) .'/backend/admin_resources.php">Sumber</a>';
    }
    echo '<a class="btn" href="'. htmlspecialchars($base) .'/backend/logout.php">Log Keluar</a>';
  } else {
    echo '<a href="'. htmlspecialchars($base) .'/backend/login.php">Login</a>';
    echo '<a class="btn" href="'. htmlspecialchars($base) .'/backend/register.php">Daftar</a>';
  }

  echo '</div></div></nav><main class="container">';
}

function render_footer() {
  echo '</main><footer class="container section"><div class="meta">© ' . date('Y') . ' MediaHub</div></footer></body></html>';
}

/* ---------------- DEFAULT ADMIN CREATION ---------------- */
function ensure_default_admin(PDO $pdo): void {
  $defaultEmail = 'admin1@mediahub.com';
  $defaultPass  = 'admin123';
  $defaultName  = 'Admin One';
  $defaultPhone = '000-0000000';

  try {
    // confirm table exists
    $pdo->query('SELECT 1 FROM users LIMIT 1');

    // migrate old seeding email if needed
    $pdo->prepare('UPDATE users SET email = ? WHERE email = ?')->execute([$defaultEmail, 'admin1@example.com']);

    $stmt = $pdo->prepare('SELECT id, role, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$defaultEmail]);
    $user = $stmt->fetch();

    $newHash = password_hash($defaultPass, PASSWORD_DEFAULT);

    if (!$user) {
      $ins = $pdo->prepare('INSERT INTO users (name,email,password_hash,phone,role) VALUES (?,?,?,?,?)');
      $ins->execute([$defaultName, $defaultEmail, $newHash, $defaultPhone, 'admin']);
      return;
    }

    if (($user['role'] ?? '') !== 'admin') {
      $pdo->prepare('UPDATE users SET role = "admin" WHERE email = ?')->execute([$defaultEmail]);
    }

    if (!password_verify($defaultPass, $user['password_hash'])) {
      $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$newHash, $defaultEmail]);
    }
  } catch (Throwable $e) {
    error_log('ensure_default_admin: ' . $e->getMessage());
  }
}
ensure_default_admin($pdo);

/* ---------------- EMAIL HELPER (GMAIL SMTP) ---------------- */
function send_mail(string $to, string $subject, string $html): bool {
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    error_log("[MAIL] Invalid recipient: {$to}");
    return false;
  }

  // Gmail SMTP Settings (use your app password)
  $SMTP_HOST   = 'smtp.gmail.com';
  $SMTP_USER   = 'kinqim70@gmail.com';
  $SMTP_PASS   = 'flwn wcsa plaf gtbz'; // Gmail App Password
  $SMTP_PORT   = 587;
  $SMTP_SECURE = 'tls';
  $MAIL_FROM   = 'kinqim70@gmail.com';
  $MAIL_FROM_NAME = 'MediaHub';
  $ADMIN_EMAIL = ''; // optional BCC address

  // PHPMailer autoload (if present)
  $hasPHPMailer = false;
  foreach ([dirname(__DIR__).'/vendor/autoload.php', __DIR__.'/vendor/autoload.php'] as $auto) {
    if (is_file($auto)) { require_once $auto; $hasPHPMailer = true; break; }
  }

  if ($hasPHPMailer) {
    try {
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      // $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER; // enable for debugging

      $mail->isSMTP();
      $mail->Host       = $SMTP_HOST;
      $mail->SMTPAuth   = true;
      $mail->Username   = $SMTP_USER;
      $mail->Password   = $SMTP_PASS;
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = $SMTP_PORT;

      $mail->setFrom($MAIL_FROM, $MAIL_FROM_NAME);
      $mail->addAddress($to);
      if ($ADMIN_EMAIL && $to !== $ADMIN_EMAIL) {
        $mail->addBCC($ADMIN_EMAIL);
      }

      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body    = $html;
      $mail->AltBody = strip_tags($html);

      $mail->send();
      return true;

    } catch (Throwable $e) {
      error_log('[MAIL][SMTP ERROR] ' . $e->getMessage());
      return false;
    }
  }

  // Fallback to PHP mail()
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=UTF-8\r\n";
  $headers .= "From: ".sprintf('%s <%s>', $MAIL_FROM_NAME, $MAIL_FROM)."\r\n";
  $ok = mail($to, $subject, $html, $headers);
  if (!$ok) error_log('[MAIL][mail()] failed for '.$to.' subject='.$subject);
  return $ok;
}

/* ---------------- STATUS-BASED EMAIL SUBJECT ---------------- */
if (!function_exists('mediahub_mail_subject_for_status')) {
  function mediahub_mail_subject_for_status(string $status): string {
    $st = strtolower(trim($status));
    if ($st === 'approved') return 'Permohonan anda telah diluluskan';
    if ($st === 'rejected') return 'Permohonan anda tidak diluluskan';
    return 'Status permohonan MediaHub';
  }
}
