<?php
// client/index.php

require_once '../includes/auth_guard.php'; // ✅ session guard

// DB config
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_name = 'User';
$user_reg_id = 'Not Set';

$application_status = 'pending';
$available_children = 0;

$days_remaining = 30;
$overall_progress = 0;

$has_voted = false;
$vote_end_date = null;

$recent_activities = [];
$unread_notifications = 0;

$missing_docs = 0;
$docs_ok = false;

// Eligibility (never show score)
$eligibility_checked = false;
$eligibility_result = 'pending'; // eligible | not_eligible | pending

// Permissions
$can_view_children = false; // unlock Children page only after docs approved
$can_vote = false;

$error_message = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        session_unset();
        session_destroy();
        header("Location: ../login.php?expired=true");
        exit();
    }

    // ✅ Fetch user + application
    $stmt = $pdo->prepare("
        SELECT
            u.id, u.email, u.registration_id, u.created_at,
            a.husband_name, a.wife_name,
            a.status AS app_status,
            a.created_at AS app_created_at,
            a.eligibility_status,
            a.eligibility_result
        FROM users u
        LEFT JOIN applications a ON u.id = a.user_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: ../login.php?expired=true");
        exit();
    }

    // Display name
    if (!empty($user['husband_name']) && !empty($user['wife_name'])) {
        $user_name = htmlspecialchars($user['husband_name'] . ' & ' . $user['wife_name']);
    } elseif (!empty($user['husband_name'])) {
        $user_name = htmlspecialchars($user['husband_name']);
    } else {
        $user_name = htmlspecialchars($user['email']);
    }

    $user_reg_id = htmlspecialchars($user['registration_id'] ?? 'Not Set');
    $application_status = strtolower((string)($user['app_status'] ?? 'pending'));

    // ✅ Missing required docs count (must be approved)
    $stmtM = $pdo->prepare("
        SELECT COUNT(*) AS missing_count
        FROM required_documents rd
        LEFT JOIN documents d
          ON d.requirement_id = rd.id
         AND d.user_id = ?
         AND d.status = 'approved'
        WHERE rd.is_required = 1
          AND rd.is_active = 1
          AND d.id IS NULL
    ");
    $stmtM->execute([$user_id]);
    $missing_docs = (int)$stmtM->fetchColumn();
    $docs_ok = ($missing_docs === 0);

    // ✅ Has voted?
    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM user_votes WHERE user_id = ? AND status = 'active'");
    $stmt3->execute([$user_id]);
    $has_voted = ((int)$stmt3->fetchColumn()) > 0;

    // ✅ Eligibility status (optional; still not showing score)
    $raw_elig_status = strtolower((string)($user['eligibility_status'] ?? ''));
    $raw_elig_result = strtolower((string)($user['eligibility_result'] ?? ''));

    if ($raw_elig_status === 'checked') {
        $eligibility_checked = true;
        if (in_array($raw_elig_result, ['eligible', 'not_eligible'], true)) {
            $eligibility_result = $raw_elig_result;
        } else {
            $eligibility_result = 'pending';
        }
    }

    // ✅ Available children count (dashboard display only)
    $stmt2 = $pdo->query("SELECT COUNT(*) FROM children WHERE status = 'available'");
    $available_children = (int)$stmt2->fetchColumn();

    // ✅ Permissions
    $can_view_children = ($application_status === 'approved' && $docs_ok);
    $can_vote = ($can_view_children && !$has_voted);

    // ✅ Progress
    // 25 = Registered, 50 = Under review, 60 = Approved but docs missing, 75 = Approved + docs ok, 100 = Voted
    if ($application_status === 'approved') {
        $overall_progress = $docs_ok ? 75 : 60;
        if ($has_voted) $overall_progress = 100;
    } elseif ($application_status === 'pending' || $application_status === 'under_review') {
        $overall_progress = 50;
    } else {
        $overall_progress = 25;
    }

    // ✅ Voting days remaining (only if can vote)
    if ($can_vote) {
        $vote_start_date = $user['app_created_at'] ?: date('Y-m-d');
        $vote_end_date = date('Y-m-d', strtotime($vote_start_date . ' + 30 days'));
        $days_remaining = max(0, (int)floor((strtotime($vote_end_date) - time()) / (60 * 60 * 24)));
    } else {
        $days_remaining = $has_voted ? 0 : 30;
        $vote_end_date = null;
    }

    // Recent activities
    $stmt4 = $pdo->prepare("
        SELECT *
        FROM user_activities
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt4->execute([$user_id]);
    $recent_activities = $stmt4->fetchAll();

    // Unread notifications
    $stmt5 = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_activities
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt5->execute([$user_id]);
    $unread_notifications = (int)$stmt5->fetchColumn();

    // Update last login
    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $update_stmt->execute([$user_id]);

} catch (PDOException $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}

// Helpers (Eligibility shown as status, not score)
function eligibilityLabel(string $result, bool $checked): string {
    if (!$checked) return 'Not Checked';
    if ($result === 'eligible') return 'Eligible';
    if ($result === 'not_eligible') return 'Not Eligible';
    return 'Pending';
}
function eligibilityIconClass(string $result, bool $checked): string {
    if (!$checked) return 'pending';
    if ($result === 'eligible') return 'eligible';
    if ($result === 'not_eligible') return 'rejected';
    return 'pending';
}

// ✅ NEW: Display application status as "Documents Required" when approved but docs missing
function applicationStatusLabel(string $appStatus, bool $docsOk): string {
    if ($appStatus === 'approved' && !$docsOk) return 'Documents Required';
    return ucfirst($appStatus);
}
function applicationStatusIcon(string $appStatus, bool $docsOk): string {
    if ($appStatus === 'approved' && !$docsOk) return 'file-upload';
    if ($appStatus === 'approved') return 'check-circle';
    if ($appStatus === 'pending' || $appStatus === 'under_review') return 'clock';
    return 'times-circle';
}
function applicationStatusCss(string $appStatus, bool $docsOk): string {
    if ($appStatus === 'approved' && !$docsOk) return 'pending';
    return $appStatus;
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .system-alert { background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin:15px; text-align:center; border:1px solid #f5c6cb; }
        .mobile-menu-btn { display:none; background:none; border:none; font-size:24px; color:#333; cursor:pointer; padding:10px; }
        @media (max-width: 768px) {
            .mobile-menu-btn { display:block; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.active { transform: translateX(0); }
        }
        .action-btn.disabled{opacity:.55;pointer-events:none;filter:grayscale(40%);}
        .action-btn.vote-highlight{border:2px solid #f59e0b;}
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">

    <?php if (!empty($error_message)): ?>
      <div class="system-alert">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <div class="welcome-section">
      <div class="welcome-card">
        <h1>Welcome Back, <?php echo $user_name; ?>!</h1>
        <p>
          Your adoption journey is <?php echo (int)$overall_progress; ?>% complete.
          <?php if ($application_status === 'approved'): ?>
            Next step:
            <?php if (!$docs_ok): ?>
              Upload the mandatory documents and wait for admin approval.
            <?php elseif ($has_voted): ?>
              Wait for your appointment scheduling by admin.
            <?php else: ?>
              View anonymous child profiles and vote for ONE child.
            <?php endif; ?>
          <?php elseif ($application_status === 'pending' || $application_status === 'under_review'): ?>
            Your application is under review. We'll notify you once approved.
          <?php else: ?>
            Please complete your profile to proceed.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <!-- ✅ Mandatory documents alert after login (until approved) -->
    <?php if ($application_status === 'approved' && !$docs_ok): ?>
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
          <strong>Mandatory Documents Required:</strong>
          Please upload all mandatory documents and wait for admin approval.
          Missing required documents: <strong><?php echo (int)$missing_docs; ?></strong>.
          Go to <a href="documents.php" style="font-weight:700;">Documents</a>.
        </div>
      </div>
    <?php endif; ?>

    <!-- ✅ FIXED Status info block -->
    <?php if ($application_status !== 'approved'): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <div>
          <strong>Status:</strong> Your application is currently <strong><?php echo htmlspecialchars(ucfirst($application_status)); ?></strong>.
          Children profiles will unlock after approval + document verification.
        </div>
      </div>
    <?php elseif ($has_voted): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>
          <strong>Vote Submitted:</strong> You have cast your vote. You can no longer change your selection.
          The admin will schedule your appointment.
        </div>
      </div>
    <?php elseif ($can_vote && $days_remaining > 0): ?>
      <div class="alert alert-info">
        <i class="fas fa-vote-yea"></i>
        <div>
          <strong>Voting Open:</strong> You can vote for only ONE child.
          Voting ends in <strong><?php echo (int)$days_remaining; ?></strong> days.
        </div>
      </div>
    <?php endif; ?>

  

    <div class="quick-actions">
      <h2 class="section-title">
        <i class="fas fa-tasks"></i>
        Application Progress
      </h2>

      <div class="progress-container">
        <div class="progress-label">
          <span>Overall Progress</span>
          <span><?php echo (int)$overall_progress; ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: <?php echo (int)$overall_progress; ?>%;"></div>
        </div>
        <div class="progress-steps">
          <span class="step <?php echo $overall_progress >= 25 ? 'completed' : ''; ?>">Registration</span>
          <span class="step <?php echo $overall_progress >= 50 ? 'completed' : ''; ?>">Review</span>
          <span class="step <?php echo $overall_progress >= 75 ? 'completed' : ''; ?>">Approved + Docs</span>
          <span class="step <?php echo $overall_progress >= 100 ? 'completed' : ''; ?>">Voted</span>
        </div>
      </div>
    </div>

    <div class="quick-actions">
      <h2 class="section-title">
        <i class="fas fa-bolt"></i>
        Quick Actions
      </h2>

      <div class="actions-grid">

        <?php if ($can_view_children): ?>
          <a href="children.php" class="action-btn">
            <i class="fas fa-child"></i>
            <span>Browse Children (Anonymous Profiles)</span>
          </a>
        <?php else: ?>
          <a href="documents.php" class="action-btn">
            <i class="fas fa-lock"></i>
            <span>Browse Children (Locked - Upload & Approve Documents)</span>
          </a>
        <?php endif; ?>

        <a href="documents.php" class="action-btn">
          <i class="fas fa-upload"></i>
          <span>Upload Documents</span>
        </a>

        <a href="appointments.php" class="action-btn">
          <i class="fas fa-calendar-check"></i>
          <span>My Appointment</span>
        </a>

        <a href="profile.php" class="action-btn">
          <i class="fas fa-user-edit"></i>
          <span>Update Profile</span>
        </a>

        <?php if ($can_vote && $days_remaining > 0): ?>
          <a href="children.php" class="action-btn vote-highlight" id="voteNowBtn">
            <i class="fas fa-vote-yea"></i>
            <span>Vote for One Child (<?php echo (int)$days_remaining; ?> days left)</span>
          </a>
        <?php elseif ($has_voted): ?>
          <a href="#" class="action-btn disabled" onclick="return false;">
            <i class="fas fa-vote-yea"></i>
            <span>Vote Submitted ✓</span>
          </a>
        <?php else: ?>
          <a href="#" class="action-btn disabled" onclick="return false;">
            <i class="fas fa-vote-yea"></i>
            <span>Vote (Locked)</span>
          </a>
        <?php endif; ?>

      </div>
    </div>

    <div class="recent-activity">
      <h2 class="section-title">
        <i class="fas fa-bell"></i>
        Recent Activity
        <?php if ($unread_notifications > 0): ?>
          <span style="margin-left:10px;background:#ef4444;color:#fff;padding:3px 8px;border-radius:999px;font-size:12px;">
            <?php echo (int)$unread_notifications; ?> new
          </span>
        <?php endif; ?>
      </h2>

      <?php if (empty($recent_activities)): ?>
        <p style="padding: 10px 0; color:#666;">No recent activity yet.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($recent_activities as $act): ?>
            <div style="padding:12px;border:1px solid #eee;border-radius:10px;background:#fff;">
              <strong><?php echo htmlspecialchars($act['title'] ?? 'Activity'); ?></strong>
              <div style="color:#666;font-size:0.9rem;margin-top:4px;">
                <?php echo htmlspecialchars($act['message'] ?? ''); ?>
              </div>
              <div style="color:#999;font-size:0.8rem;margin-top:6px;">
                <?php echo htmlspecialchars($act['created_at'] ?? ''); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

</main>

</body>
</html>
