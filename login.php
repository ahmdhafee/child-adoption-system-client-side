<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root'; 
$password = ''; 


$error = '';
$success = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password_input = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['rememberMe']) ? 1 : 0;
        
        // Validate inputs
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (empty($password_input)) {
            $error = "Please enter your password.";
        } else {
           
            $stmt = $pdo->prepare("SELECT id, email, password, registration_id, COALESCE(status, 'active') as status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
               
                if (password_verify($password_input, $user['password'])) {
                    
                    if ($user) {
                      
                        if (password_verify($password_input, $user['password'])) {
                            // Set session variables
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['registration_id'] = $user['registration_id'];
                            $_SESSION['user_status'] = 'active'; 
                            $_SESSION['logged_in'] = true;
                            
                            
                            if ($rememberMe) {
                                $cookie_value = $user['id'] . ':' . hash('sha256', $user['password']);
                                setcookie('family_bridge_remember', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                            }
                            
                            header("Location: client/index.php");
                            exit();
                        } else {
                            $error = "Invalid email or password.";
                        }
                    } else {
                        $error = "Invalid email or password.";
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        }
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("SELECT id, email, password, registration_id, COALESCE(status, 'active') as status FROM users WHERE id = ?");
    } catch (Exception $e) {
        $error = "An unexpected error occurred. Please try again.";
    }
}



// Check for remember me cookie
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['family_bridge_remember'])) {
    try {
        $parts = explode(':', $_COOKIE['family_bridge_remember']);
        if (count($parts) === 2) {
            $user_id = $parts[0];
            $token_hash = $parts[1];
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT id, email, password, registration_id, status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && hash_equals(hash('sha256', $user['password']), $token_hash)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['registration_id'] = $user['registration_id'];
                $_SESSION['user_status'] = $user['status'];
                $_SESSION['logged_in'] = true;
                
                echo "Debug: Login successful, redirecting to Client/index.php";
                exit();

                header("Location: client/index.php");
                exit();
            } else {
                
                setcookie('family_bridge_remember', '', time() - 3600, '/');
            }
        }
    } catch (Exception $e) {
        
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
      
        .server-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px auto;
            max-width: 500px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        
        .server-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px auto;
            max-width: 500px;
            text-align: center;
            border: 1px solid #c3e6cb;
        }
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
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
                <div class="server-success">
                    <i class="fas fa-check-circle"></i> Registration successful! Please log in with your credentials.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['logout']) && $_GET['logout'] == 'true'): ?>
                <div class="server-success">
                    <i class="fas fa-check-circle"></i> You have been successfully logged out.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['expired']) && $_GET['expired'] == 'true'): ?>
                <div class="server-error">
                    <i class="fas fa-exclamation-circle"></i> Your session has expired. Please log in again.
                </div>
            <?php endif; ?>

            <div class="login-container">
                <div class="login-header">
                    <i class="fas fa-user-shield"></i>
                    <h1>Couple Login Portal</h1>
                    <p>Secure access to your adoption journey</p>
                </div>
                
                <div class="login-body">
                   
                    <div class="alert" id="loginAlert" style="display: none;">
                        <i id="alertIcon"></i>
                        <span id="alertMessage"></span>
                    </div>
                    
                    
                    <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your registered email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <div class="error-message" id="emailError">Please enter a valid email address</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="required">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                            <div class="error-message" id="passwordError">Please enter your password</div>
                        </div>
                        
                        <div class="form-options">
                            <div class="checkbox-group">
                                <input type="checkbox" id="rememberMe" name="rememberMe">
                                <label for="rememberMe">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password" id="forgotPassword">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="loginButton">
                            <i class="fas fa-sign-in-alt"></i> Login to Your Portal
                        </button>
                    </form>
                    
                    <div class="login-divider">
                        <span>OR</span>
                    </div>
                    
                    <div style="text-align: center;">
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
       
        document.querySelector('.mobile-menu-btn').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
        
       
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelector('.nav-links').classList.remove('active');
            });
        });
        
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            let isValid = true;
            
           
            document.querySelectorAll('.error-message').forEach(error => {
                error.style.display = 'none';
            });
            
            document.querySelectorAll('.form-control').forEach(input => {
                input.style.borderColor = '#ddd';
            });
            
            
            const email = document.getElementById('email');
            const emailError = document.getElementById('emailError');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!email.value.trim()) {
                emailError.textContent = 'Email is required';
                emailError.style.display = 'block';
                email.style.borderColor = '#dc3545';
                isValid = false;
            } else if (!emailRegex.test(email.value)) {
                emailError.textContent = 'Please enter a valid email address';
                emailError.style.display = 'block';
                email.style.borderColor = '#dc3545';
                isValid = false;
            }
            
           
            const password = document.getElementById('password');
            const passwordError = document.getElementById('passwordError');
            
            if (!password.value.trim()) {
                passwordError.textContent = 'Password is required';
                passwordError.style.display = 'block';
                password.style.borderColor = '#dc3545';
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
       
    </script>
</body>
</html>