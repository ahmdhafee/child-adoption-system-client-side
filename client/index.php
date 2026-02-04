<?php
session_start();

// Check if user is logged in
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
$eligibility_score = 0;
$application_status = 'Pending';
$available_children = 0;
$days_remaining = 30;
$overall_progress = 0;
$has_voted = false;
$vote_end_date = date('Y-m-d', strtotime('+15 days'));
$recent_activities = [];
$unread_notifications = 0;


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'] ?? 0;
   
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.registration_id, u.created_at,
                                  a.partner1_name, a.partner2_name, a.eligibility_score, 
                                  a.status as app_status, a.created_at as app_created_at
                           FROM users u 
                           LEFT JOIN applications a ON u.id = a.user_id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
       
        if (!empty($user['partner1_name']) && !empty($user['partner2_name'])) {
            $user_name = htmlspecialchars($user['partner1_name'] . ' & ' . $user['partner2_name']);
        } elseif (!empty($user['partner1_name'])) {
            $user_name = htmlspecialchars($user['partner1_name']);
        } else {
            $user_name = htmlspecialchars($user['email']);
        }
        
        $user_reg_id = htmlspecialchars($user['registration_id'] ?? 'Not Set');
        $eligibility_score = intval($user['eligibility_score'] ?? 0);
        $application_status = htmlspecialchars($user['app_status'] ?? 'Pending');
        
        
        if ($application_status === 'approved') {
            $overall_progress = 75;
        } elseif ($application_status === 'pending') {
            $overall_progress = 50;
        } else {
            $overall_progress = 25;
        }
        
    
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM children WHERE status = 'available'");
        $stmt2->execute();
        $children_count = $stmt2->fetch(PDO::FETCH_ASSOC);
        $available_children = $children_count['count'] ?? 0;
        
        
        $stmt3 = $pdo->prepare("SELECT COUNT(*) as count FROM user_votes WHERE user_id = ? AND status = 'active'");
        $stmt3->execute([$user_id]);
        $vote_count = $stmt3->fetch(PDO::FETCH_ASSOC);
        $has_voted = ($vote_count['count'] ?? 0) > 0;
        
      
        if (!$has_voted && $eligibility_score >= 75 && $application_status === 'approved') {
            $vote_start_date = $user['app_created_at'] ?? date('Y-m-d');
            $vote_end_date = date('Y-m-d', strtotime($vote_start_date . ' + 30 days'));
            $days_remaining = max(0, floor((strtotime($vote_end_date) - time()) / (60 * 60 * 24)));
        } else {
            $days_remaining = $has_voted ? 0 : 30;
        }
        
    
        $stmt4 = $pdo->prepare("SELECT * FROM user_activities 
                                WHERE user_id = ? 
                                ORDER BY created_at DESC 
                                LIMIT 5");
        $stmt4->execute([$user_id]);
        $recent_activities = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        
     
        $stmt5 = $pdo->prepare("SELECT COUNT(*) as count FROM user_activities 
                                WHERE user_id = ? AND is_read = FALSE");
        $stmt5->execute([$user_id]);
        $unread_count = $stmt5->fetch(PDO::FETCH_ASSOC);
        $unread_notifications = $unread_count['count'] ?? 0;
        
       
        $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update_stmt->execute([$user_id]);
        
    } else {
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}


if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
}


$sidebar_active = false;
if (isset($_GET['menu_toggle'])) {
    $sidebar_active = $_GET['menu_toggle'] === 'open';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/index.css">
    <link rel="shortcut icon" href="../favlogo.png" type="logo">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .system-alert {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        
        .logout-confirm {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .logout-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .logout-buttons {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .logout-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-yes {
            background: #dc3545;
            color: white;
        }
        
        .logout-no {
            background: #6c757d;
            color: white;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #333;
            cursor: pointer;
            padding: 10px;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
 
   <?php include 'includes/header.php' ?>
   
  <?php include 'includes/sidebar.php'?>

  <div class="main-layout">

   
    <main class="main-content">
       
        <div class="welcome-section">
            <div class="welcome-card">
                <h1>Welcome Back, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <p>Your adoption journey is <?php echo $overall_progress; ?>% complete. 
                <?php if ($application_status === 'approved'): ?>
                    Next step: <?php echo $has_voted ? 'Wait for match proposal' : 'Review available children profiles'; ?>.
                <?php elseif ($application_status === 'pending'): ?>
                    Your application is under review. We'll notify you once approved.
                <?php else: ?>
                    Please complete your profile to proceed.
                <?php endif; ?>
                </p>
            </div>
        </div>

       
        <?php if ($has_voted): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Vote Submitted:</strong> You have successfully cast your vote. You can no longer change your selection.
                </div>
            </div>
        <?php elseif ($eligibility_score >= 75 && $application_status === 'approved' && $days_remaining > 0): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Reminder:</strong> Your eligibility score is <?php echo $eligibility_score; ?>%. 
                    You can now vote for a child. Voting period ends in <?php echo $days_remaining; ?> days.
                </div>
            </div>
        <?php elseif ($eligibility_score < 75): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Notice:</strong> Your eligibility score is <?php echo $eligibility_score; ?>% (minimum 75% required). 
                    Please contact support if you believe this is an error.
                </div>
            </div>
        <?php endif; ?>

      
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon <?php echo strtolower($application_status); ?>">
                    <i class="fas fa-<?php echo $application_status === 'approved' ? 'check-circle' : ($application_status === 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                </div>
                <div class="stat-info">
                    <h3 id="applicationStatus"><?php echo ucfirst($application_status); ?></h3>
                    <p>Application Status</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon <?php echo $eligibility_score >= 75 ? 'eligible' : 'pending'; ?>">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3 id="eligibilityScore"><?php echo $eligibility_score; ?>%</h3>
                    <p>Eligibility Score</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon children">
                    <i class="fas fa-child"></i>
                </div>
                <div class="stat-info">
                    <h3 id="availableChildren"><?php echo $available_children; ?></h3>
                    <p>Available Children</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon <?php echo $days_remaining > 7 ? 'eligible' : ($days_remaining > 0 ? 'pending' : 'inactive'); ?>">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3 id="daysRemaining"><?php echo $has_voted ? 'Voted' : $days_remaining; ?></h3>
                    <p><?php echo $has_voted ? 'Vote Status' : 'Days to Vote'; ?></p>
                </div>
            </div>
        </div>

       
        <div class="quick-actions">
            <h2 class="section-title">
                <i class="fas fa-tasks"></i>
                Application Progress
            </h2>
            
            <div class="progress-container">
                <div class="progress-label">
                    <span>Overall Progress</span>
                    <span><?php echo $overall_progress; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $overall_progress; ?>%;"></div>
                </div>
                <div class="progress-steps">
                    <span class="step <?php echo $overall_progress >= 25 ? 'completed' : ''; ?>">Registration</span>
                    <span class="step <?php echo $overall_progress >= 50 ? 'completed' : ''; ?>">Review</span>
                    <span class="step <?php echo $overall_progress >= 75 ? 'completed' : ''; ?>">Approval</span>
                    <span class="step <?php echo $overall_progress >= 100 ? 'completed' : ''; ?>">Matching</span>
                </div>
            </div>
        </div>

      
        <div class="quick-actions">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>
            
            <div class="actions-grid">
                <a href="children.php" class="action-btn">
                    <i class="fas fa-child"></i>
                    <span>Browse Children (<?php echo $available_children; ?>)</span>
                </a>
                
                <a href="documents.php" class="action-btn">
                    <i class="fas fa-upload"></i>
                    <span>Upload Documents</span>
                </a>
                
                <a href="appointments.php" class="action-btn">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Schedule Visit</span>
                </a>
                
                <a href="profile.php" class="action-btn">
                    <i class="fas fa-user-edit"></i>
                    <span>Update Profile</span>
                </a>
                
                <?php if (!$has_voted && $eligibility_score >= 75 && $application_status === 'approved' && $days_remaining > 0): ?>
                    <a href="vote.php" class="action-btn vote-highlight" id="voteNowBtn">
                        <i class="fas fa-vote-yea"></i>
                        <span>Cast Your Vote (<?php echo $days_remaining; ?> days left)</span>
                    </a>
                <?php elseif ($has_voted): ?>
                    <a href="#" class="action-btn disabled" onclick="return false;">
                        <i class="fas fa-vote-yea"></i>
                        <span>Vote Submitted ✓</span>
                    </a>
                <?php else: ?>
                    <a href="#" class="action-btn disabled" onclick="return false;">
                        <i class="fas fa-vote-yea"></i>
                        <span>Vote (Not Eligible)</span>
                    </a>
                <?php endif; ?>
                
                <a href="documents.php?action=download" class="action-btn">
                    <i class="fas fa-download"></i>
                    <span>Download Forms</span>
                </a>
            </div>
        </div>

       
        <div class="recent-activity">
            <h2 class="section-title">
                <i class="fas fa-bell"></i>
                Recent Activity
                
            </h2>
            
          
        </div>
    </main>
  </div>
</body>
</html>