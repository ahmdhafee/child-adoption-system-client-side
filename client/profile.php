<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

session_start();


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}


$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';


$user_name = 'User';
$user_reg_id = 'Not Set';
$profile_completion = 0;
$profile_photo = '';
$error_message = '';


$personal_info = [];
$family_info = [];
$preferences = [];
$account_info = [];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$user_id) {
        throw new Exception("User ID not found in session");
    }
    
 
    $user_stmt = $pdo->prepare("SELECT id, email, registration_id, created_at, last_login, profile_photo, profile_completion FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
    
    $user_reg_id = htmlspecialchars($user['registration_id'] ?? 'Not Set');
    $profile_completion = intval($user['profile_completion'] ?? 0);
    $profile_photo = $user['profile_photo'] ? htmlspecialchars($user['profile_photo']) : '';
    
  
    $app_stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ?");
    $app_stmt->execute([$user_id]);
    $application = $app_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($application) {
      
        if (!empty($application['partner1_name']) && !empty($application['partner2_name'])) {
            $user_name = htmlspecialchars($application['partner1_name'] . ' & ' . $application['partner2_name']);
        } elseif (!empty($application['partner1_name'])) {
            $user_name = htmlspecialchars($application['partner1_name']);
        } else {
            $user_name = htmlspecialchars($user['email']);
        }
        
      
        $personal_info = [
            'partner1_name' => htmlspecialchars($application['partner1_name'] ?? ''),
            'partner1_age' => htmlspecialchars($application['partner1_age'] ?? ''),
            'partner1_occupation' => htmlspecialchars($application['partner1_occupation'] ?? ''),
            'partner1_id' => htmlspecialchars($application['partner1_id'] ?? ''),
            'partner1_blood_group' => htmlspecialchars($application['partner1_blood_group'] ?? ''),
            'partner1_medical' => htmlspecialchars($application['partner1_medical'] ?? ''),
            'partner2_name' => htmlspecialchars($application['partner2_name'] ?? ''),
            'partner2_age' => htmlspecialchars($application['partner2_age'] ?? ''),
            'partner2_occupation' => htmlspecialchars($application['partner2_occupation'] ?? ''),
            'partner2_id' => htmlspecialchars($application['partner2_id'] ?? ''),
            'partner2_blood_group' => htmlspecialchars($application['partner2_blood_group'] ?? ''),
            'partner2_medical' => htmlspecialchars($application['partner2_medical'] ?? ''),
            'email' => htmlspecialchars($user['email'] ?? ''),
            'district' => htmlspecialchars($application['district'] ?? ''),
            'address' => htmlspecialchars($application['address'] ?? '')
        ];
        
        
        $family_info = [
            'marital_status' => 'Married',
            'district' => htmlspecialchars($application['district'] ?? ''),
            'address' => htmlspecialchars($application['address'] ?? '')
        ];
        
    } else {
      
        $user_name = htmlspecialchars($user['email']);
        $personal_info = [
            'email' => htmlspecialchars($user['email'] ?? '')
        ];
    }
    
    
    
    $preferences_stmt->execute([$user_id]);
    $user_preferences = $preferences_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_preferences) {
        $adoption_types = [];
        if (!empty($user_preferences['adoption_types'])) {
            $adoption_types = json_decode($user_preferences['adoption_types'], true) ?: [];
        }
        
        $preferences = [
            'min_age' => intval($user_preferences['min_age_pref'] ?? 0),
            'max_age' => intval($user_preferences['max_age_pref'] ?? 0),
            'gender_pref' => htmlspecialchars($user_preferences['gender_pref'] ?? ''),
            'sibling_groups' => htmlspecialchars($user_preferences['sibling_groups'] ?? ''),
            'special_needs' => htmlspecialchars($user_preferences['special_needs'] ?? ''),
            'specific_needs' => htmlspecialchars($user_preferences['specific_needs'] ?? ''),
            'additional_prefs' => htmlspecialchars($user_preferences['additional_prefs'] ?? ''),
            'adoption_types' => $adoption_types
        ];
    }
    
   
    $account_info = [
        'registration_id' => htmlspecialchars($user['registration_id'] ?? ''),
        'created_at' => date('F j, Y', strtotime($user['created_at'] ?? 'now')),
        'last_login' => $user['last_login'] ? date('F j, Y H:i', strtotime($user['last_login'])) : 'Never',
        'account_status' => 'Active',
        'two_factor' => false
    ];
    
} catch (PDOException $e) {
    error_log("Database error in profile.php: " . $e->getMessage());
    $error_message = "A system error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("General error in profile.php: " . $e->getMessage());
    $error_message = "An error occurred. Please try again.";
}





function getInitials($personal_info) {
    $initials = '';
    if (!empty($personal_info['partner1_name'])) {
        $initials .= substr($personal_info['partner1_name'], 0, 1);
    }
    if (!empty($personal_info['partner2_name'])) {
        $initials .= substr($personal_info['partner2_name'], 0, 1);
    }
    return $initials ?: 'FB';
}




$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/profile.css">
    <link rel="shortcut icon" href="../favlogo.png" type="logo">
    <style>
     
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
       
    </style>
</head>
<body>
   
    <header>
        <div class="container">
            <div class="header-content">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="mobile-menu-btn" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="index.php" class="logo-container">
                        <div class="logo-main">Family Bridge</div>
                        <div class="logo-sub">Client Portal</div>
                    </a>
                </div>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo getInitials($personal_info); ?></div>
                        <div class="user-details">
                            <h3><?php echo $user_name; ?></h3>
                            <p>Registration ID: <?php echo $user_reg_id; ?></p>
                        </div>
                    </div>
                    <button class="logout-btn" id="logoutBtn">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </header>


    <div class="main-layout">
  
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user"></i>
                    Profile Management
                </a>
                <a href="documents.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    Documents
                </a>
                <a href="children.php" class="nav-item">
                    <i class="fas fa-child"></i>
                    Available Children
                </a>
                <a href="status.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    Application Status
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    Appointments
                </a>
                
                <div class="sidebar-footer">
                    <p>Family Bridge Portal</p>
                </div>
            </nav>
        </aside>

      
        <main class="main-content">
           
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
            <?php endif; ?>
            
            
            <div class="profile-header">
                <div class="profile-title">
                    <h1>Profile Management</h1>
                    <p>Manage your personal information and adoption preferences</p>
                </div>
                
                <div class="profile-completion">
                    <div class="completion-header">
                        <h3>Profile Completion</h3>
                        <span class="completion-percentage"><?php echo $profile_completion; ?>%</span>
                    </div>
                    <div class="completion-bar">
                        <div class="completion-fill" style="width: <?php echo $profile_completion; ?>%"></div>
                    </div>
                </div>
            </div>

            
            <div class="profile-card">
                <div class="card-header">
                    <h2><i class="fas fa-id-card"></i> Profile Photo</h2>
                </div>
                <div class="card-body">
                    <div class="profile-photo-section">
                        <div class="profile-photo-container">
                            <?php if ($profile_photo): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($profile_photo); ?>" 
                                     alt="Profile Photo" class="profile-photo" id="profilePhoto">
                            <?php else: ?>
                                <div class="profile-photo" style="background: linear-gradient(135deg, #3498db, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold;">
                                    <?php echo getInitials($personal_info); ?>
                                </div>
                            <?php endif; ?>
                            <button class="photo-upload-btn" id="uploadPhotoBtn">
                                <i class="fas fa-camera"></i>
                            </button>
                            <input type="file" id="photoUpload" accept="image/*" style="display: none;">
                        </div>
                        <div class="photo-info">
                            <h3>Upload a Professional Photo</h3>
                            <p>Upload a clear, recent photo of both partners. This helps in the matching process.</p>
                            <p style="font-size: 0.8rem; color: var(--gray);">
                                <i class="fas fa-info-circle"></i>
                                Recommended: JPG or PNG, max 5MB
                            </p>
                        </div>
                    </div>
                </div>
            </div>

          
            

            
            <div class="tab-content active" id="personalTab">
                <div class="profile-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-circle"></i> Basic Information</h2>
                       
                    </div>
                    <div class="card-body">
                        <form id="personalForm">
                            <input type="hidden" name="action" value="update_personal">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <h3>Partner 1</h3>
                            <div class="row"><div class="label">Age</div><?= $app['partner1_age'] ?></div>
                            <div class="row"><div class="label">Occupation</div><?= $app['partner1_occupation'] ?></div>
                            <div class="row"><div class="label">Blood Group</div><?= $app['partner1_blood_group'] ?></div>
                            
                            <h3>Partner 2</h3>
                            <div class="row"><div class="label">Name</div><?= $app['partner2_name'] ?></div>
                            <div class="row"><div class="label">Age</div><?= $app['partner2_age'] ?></div>
                            <div class="row"><div class="label">Occupation</div><?= $app['partner2_occupation'] ?></div>
                            
                            <h3>Address</h3>
                            <div class="row"><div class="label">District</div><?= $app['district'] ?></div>
                            <div class="row"><div class="label">Address</div><?= $app['address'] ?></div>
                            
                            <h3>System Info</h3>
                            <div class="row"><div class="label">Eligibility Score</div><?= $app['eligibility_score'] ?></div>
                            <div class="row"><div class="label">Applied On</div><?= $app['created_at'] ?></div>
                            <div class="row"><div class="label">Name</div><?= $app['partner1_name'] ?></div>
                             
                        </form>
                    </div>
                </div>
            </div>
            
            
        </main>
    </div>

    <script>
       
            
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to logout?')) {
                        window.location.href = '../logout.php';
                    }
                });
            }
            
           
    </script>
</body>
</html>