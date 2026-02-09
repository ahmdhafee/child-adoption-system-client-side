<?php
session_start();


$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$error = '';


function db($host, $dbname, $username, $password) {
    return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}


function setLoginSession(array $user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['registration_id'] = $user['registration_id'];
    $_SESSION['user_status'] = $user['status'] ?? 'active';
    $_SESSION['logged_in'] = true;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db($host, $dbname, $username, $password);

        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password_input = $_POST['password'] ?? '';
        $rememberMe = !empty($_POST['rememberMe']);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (empty($password_input)) {
            $error = "Please enter your password.";
        } else {
            $stmt = $pdo->prepare("SELECT id, email, password, registration_id, COALESCE(status,'active') AS status, remember_token_hash, remember_token_expiry FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password_input, $user['password'])) {
                $error = "Invalid email or password.";
            } else {
                
                if (strtolower((string)$user['status']) !== 'active') {
                    $error = "Your account is not active. Please contact support.";
                } else {
                    setLoginSession($user);

                    
                    if ($rememberMe) {
                        $token = bin2hex(random_bytes(32)); 
                        $token_hash = hash('sha256', $token); 
                        $expiry = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); 

                        $upd = $pdo->prepare("UPDATE users SET remember_token_hash=?, remember_token_expiry=? WHERE id=?");
                        $upd->execute([$token_hash, $expiry, (int)$user['id']]);

                        
                        $cookie_value = $user['id'] . ':' . $token;

                       
                        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                        setcookie('family_bridge_remember', $cookie_value, [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'secure' => $isHttps,  // true only if HTTPS
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }

                    header("Location: client/index.php");
                    exit();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "System error. Please try again later.";
    }
}

if (empty($_SESSION['logged_in']) && !empty($_COOKIE['family_bridge_remember'])) {
    try {
        $parts = explode(':', $_COOKIE['family_bridge_remember'], 2);
        if (count($parts) === 2) {
            $cookie_user_id = (int)$parts[0];
            $token = $parts[1];
            $token_hash = hash('sha256', $token);

            $pdo = db($host, $dbname, $username, $password);

            $stmt = $pdo->prepare("SELECT id, email, password, registration_id, COALESCE(status,'active') AS status, remember_token_hash, remember_token_expiry FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$cookie_user_id]);
            $user = $stmt->fetch();

            $expired = empty($user['remember_token_expiry']) || (strtotime($user['remember_token_expiry']) < time());
            $match = !empty($user['remember_token_hash']) && hash_equals($user['remember_token_hash'], $token_hash);

            if ($user && !$expired && $match && strtolower((string)$user['status']) === 'active') {
                setLoginSession($user);
                header("Location: client/index.php");
                exit();
            } else {
              
                setcookie('family_bridge_remember', '', time() - 3600, '/');
                if (!empty($user['id'])) {
                    $pdo->prepare("UPDATE users SET remember_token_hash=NULL, remember_token_expiry=NULL WHERE id=?")->execute([(int)$user['id']]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="favlogo.png" type="logo">
    <link rel="stylesheet" href="style/login.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .server-error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:15px auto;max-width:520px;text-align:center;border:1px solid #f5c6cb;}
        .server-success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:15px auto;max-width:520px;text-align:center;border:1px solid #c3e6cb;}
    </style>
</head>
<body>
<?php include 'includes/header.php'?>

<section class="login-section">
  <div class="container">

    <?php if (!empty($error)): ?>
      <div class="server-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['registered']) && $_GET['registered'] === 'true'): ?>
      <div class="server-success"><i class="fas fa-check-circle"></i> Registration successful! Please log in.</div>
    <?php endif; ?>

    <?php if (!empty($_GET['logout']) && $_GET['logout'] === 'true'): ?>
      <div class="server-success"><i class="fas fa-check-circle"></i> You have been logged out.</div>
    <?php endif; ?>

    <?php if (!empty($_GET['expired']) && $_GET['expired'] === 'true'): ?>
      <div class="server-error"><i class="fas fa-exclamation-circle"></i> Your session expired. Please log in again.</div>
    <?php endif; ?>

    <div class="login-container">
      <div class="login-header">
        <i class="fas fa-user-shield"></i>
        <h1>Couple Login Portal</h1>
        <p>Secure access to your adoption journey</p>
      </div>

      <div class="login-body">
        <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
          <div class="form-group">
            <label for="email" class="required">Email Address</label>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="Enter your registered email" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            <div class="error-message" id="emailError">Please enter a valid email address</div>
          </div>

          <div class="form-group">
            <label for="password" class="required">Password</label>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Enter your password" required>
            <div class="error-message" id="passwordError">Please enter your password</div>
          </div>

          <div class="form-options">
            <div class="checkbox-group">
              <input type="checkbox" id="rememberMe" name="rememberMe">
              <label for="rememberMe">Remember me</label>
            </div>
            <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
          </div>

          <button type="submit" class="btn btn-primary" id="loginButton">
            <i class="fas fa-sign-in-alt"></i> Login to Your Portal
          </button>
        </form>

        <div class="login-divider"><span>OR</span></div>

        <div style="text-align:center;">
          <p>Don't have an account yet?</p>
          <a href="register.php" class="btn" style="background-color: var(--accent); margin-top: 15px;">
            <i class="fas fa-user-plus"></i> Start Registration
          </a>
        </div>

        <div class="login-footer">
          <p>By logging in, you agree to our <a href="#">Privacy Policy</a> and <a href="#">Terms of Service</a></p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php' ?>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
  let isValid = true;

  document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.form-control').forEach(el => el.style.borderColor = '#ddd');

  const email = document.getElementById('email');
  const password = document.getElementById('password');

  const emailError = document.getElementById('emailError');
  const passwordError = document.getElementById('passwordError');

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
    emailError.style.display = 'block';
    email.style.borderColor = '#dc3545';
    isValid = false;
  }

  if (!password.value.trim()) {
    passwordError.style.display = 'block';
    password.style.borderColor = '#dc3545';
    isValid = false;
  }

  if (!isValid) e.preventDefault();
});
</script>
</body>
</html>
