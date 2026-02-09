<?php
// client/profile.php  (READ-ONLY DETAILS + CHANGE PHONE + CHANGE PASSWORD)
// ✅ UPDATED to match your applications table: husband_* and wife_* (NO partner1/partner2)

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

session_start();

// ✅ Session guard
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?expired=true");
    exit();
}

// ✅ CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// DB config
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_name = 'User';
$user_reg_id = 'Not Set';
$error_message = '';

$user = null;
$application = null;

$personal_info = [];
$account_info  = [];

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function getInitials($husband, $wife, $email) {
    $initials = '';
    if (!empty($husband)) $initials .= mb_substr($husband, 0, 1);
    if (!empty($wife))    $initials .= mb_substr($wife, 0, 1);
    if ($initials === '' && !empty($email)) $initials = mb_substr($email, 0, 1);
    return strtoupper($initials ?: 'FB');
}

function formatEligibilityResult($v) {
    $v = (string)$v;
    if ($v === 'eligible') return 'Eligible';
    if ($v === 'not_eligible') return 'Not Eligible';
    if ($v === 'pending') return 'Pending';
    return $v ?: 'Pending';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        throw new Exception("User ID not found in session.");
    }

    // ✅ Get user basic data (phone column must exist)
    $user_stmt = $pdo->prepare("
        SELECT id, email, phone, registration_id, created_at, last_login, status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: ../login.php?expired=true");
        exit();
    }

    // ✅ Get application data (match your table columns)
    $app_stmt = $pdo->prepare("
        SELECT
            husband_name, husband_age, husband_occupation, husband_id, husband_blood_group, husband_medical,
            wife_name, wife_age, wife_occupation, wife_id, wife_blood_group, wife_medical,
            district, address,
            status, created_at,
            eligibility_status, eligibility_result
        FROM applications
        WHERE user_id = ?
        LIMIT 1
    ");
    $app_stmt->execute([$user_id]);
    $application = $app_stmt->fetch();

    // Display name
    $h = $application['husband_name'] ?? '';
    $w = $application['wife_name'] ?? '';

    if (!empty($h) && !empty($w)) {
        $user_name = e($h . ' & ' . $w);
    } elseif (!empty($h)) {
        $user_name = e($h);
    } else {
        $user_name = e($user['email']);
    }

    $user_reg_id = e($user['registration_id'] ?? 'Not Set');

    // Read-only info for UI
    $personal_info = [
        // Husband
        'husband_name'        => e($application['husband_name'] ?? ''),
        'husband_age'         => e($application['husband_age'] ?? ''),
        'husband_occupation'  => e($application['husband_occupation'] ?? ''),
        'husband_id'          => e($application['husband_id'] ?? ''),
        'husband_blood_group' => e($application['husband_blood_group'] ?? ''),
        'husband_medical'     => e($application['husband_medical'] ?? ''),

        // Wife
        'wife_name'        => e($application['wife_name'] ?? ''),
        'wife_age'         => e($application['wife_age'] ?? ''),
        'wife_occupation'  => e($application['wife_occupation'] ?? ''),
        'wife_id'          => e($application['wife_id'] ?? ''),
        'wife_blood_group' => e($application['wife_blood_group'] ?? ''),
        'wife_medical'     => e($application['wife_medical'] ?? ''),

        // Address
        'district'           => e($application['district'] ?? ''),
        'address'            => e($application['address'] ?? ''),

        // ✅ Eligibility (NO SCORE)
        'eligibility_status' => e($application['eligibility_status'] ?? 'pending'),
        'eligibility_result' => e($application['eligibility_result'] ?? 'pending'),

        // Application
        'application_status' => e($application['status'] ?? 'pending'),
        'applied_on'         => e($application['created_at'] ?? ''),

        // Contact
        'email'              => e($user['email'] ?? ''),
        'phone'              => e($user['phone'] ?? '')
    ];

    $account_info = [
        'registration_id' => e($user['registration_id'] ?? ''),
        'created_at'      => !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : '-',
        'last_login'      => !empty($user['last_login']) ? date('F j, Y H:i', strtotime($user['last_login'])) : 'Never',
        'account_status'  => e($user['status'] ?? 'active')
    ];

} catch (Exception $e) {
    error_log("profile.php error: " . $e->getMessage());
    $error_message = "An error occurred. Please try again.";
}
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
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        .row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #eee;flex-wrap:wrap}
        .row:last-child{border-bottom:0}
        .label{min-width:170px;color:#666;font-weight:600}
        .value{color:#111;flex:1}
        .alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;border:1px solid #eee;background:#fff}
        .alert-error{border-color:#f5c6cb;background:#f8d7da;color:#721c24}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
        .btn-primary{background:#2c7be5;color:#fff}
        .btn-primary:disabled{opacity:.6;cursor:not-allowed}
        .form-group{display:flex;flex-direction:column;gap:6px;margin:12px 0}
        .form-group input{padding:10px 12px;border:1px solid #ddd;border-radius:10px;outline:none}
        .form-group input:focus{border-color:#2c7be5}
        .small-note{font-size:.85rem;color:#666;margin-top:6px}
        .server-msg{margin-top:10px;font-weight:700}
        .server-ok{color:#0f5132}
        .server-bad{color:#842029}
        .pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-weight:800;font-size:12px;border:1px solid #e5e7eb;background:#fff}
        .pill-ok{background:#dcfce7;border-color:#bbf7d0;color:#166534}
        .pill-bad{background:#fee2e2;border-color:#fecaca;color:#991b1b}
        .pill-warn{background:#fef3c7;border-color:#fde68a;color:#92400e}
    </style>
</head>
<body>

<header>
    <div class="container">
        <div class="header-content">
            <div style="display:flex;align-items:center;gap:20px;">
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
                    <div class="user-avatar">
                        <?php echo e(getInitials($application['husband_name'] ?? '', $application['wife_name'] ?? '', $user['email'] ?? '')); ?>
                    </div>
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
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
            <a href="profile.php" class="nav-item active"><i class="fas fa-user"></i>Profile Management</a>
            <a href="documents.php" class="nav-item"><i class="fas fa-file-alt"></i>Documents</a>
            <a href="children.php" class="nav-item"><i class="fas fa-child"></i>Available Children</a>
            <a href="status.php" class="nav-item"><i class="fas fa-chart-line"></i>Application Status</a>
            <a href="appointments.php" class="nav-item"><i class="fas fa-calendar-check"></i>Appointments</a>

            <div class="sidebar-footer">
                <p>Family Bridge Portal</p>
            </div>
        </nav>
    </aside>

    <main class="main-content">

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Error:</strong> <?php echo e($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-title">
                <h1>Profile</h1>
                <p>View your details. You can update only phone number and password.</p>
            </div>
        </div>

        <!-- ✅ Read-only details -->
        <div class="profile-card">
            <div class="card-header">
                <h2><i class="fas fa-id-card"></i> Your Details</h2>
            </div>
            <div class="card-body">

                <h3 style="margin:10px 0;">Husband</h3>
                <div class="row"><div class="label">Name</div><div class="value"><?php echo $personal_info['husband_name']; ?></div></div>
                <div class="row"><div class="label">Age</div><div class="value"><?php echo $personal_info['husband_age']; ?></div></div>
                <div class="row"><div class="label">Occupation</div><div class="value"><?php echo $personal_info['husband_occupation']; ?></div></div>
                <div class="row"><div class="label">NIC</div><div class="value"><?php echo $personal_info['husband_id']; ?></div></div>
                <div class="row"><div class="label">Blood Group</div><div class="value"><?php echo $personal_info['husband_blood_group']; ?></div></div>
                <div class="row"><div class="label">Medical</div><div class="value"><?php echo $personal_info['husband_medical']; ?></div></div>

                <h3 style="margin:16px 0 10px;">Wife</h3>
                <div class="row"><div class="label">Name</div><div class="value"><?php echo $personal_info['wife_name']; ?></div></div>
                <div class="row"><div class="label">Age</div><div class="value"><?php echo $personal_info['wife_age']; ?></div></div>
                <div class="row"><div class="label">Occupation</div><div class="value"><?php echo $personal_info['wife_occupation']; ?></div></div>
                <div class="row"><div class="label">NIC</div><div class="value"><?php echo $personal_info['wife_id']; ?></div></div>
                <div class="row"><div class="label">Blood Group</div><div class="value"><?php echo $personal_info['wife_blood_group']; ?></div></div>
                <div class="row"><div class="label">Medical</div><div class="value"><?php echo $personal_info['wife_medical']; ?></div></div>

                <h3 style="margin:16px 0 10px;">Contact</h3>
                <div class="row"><div class="label">Email</div><div class="value"><?php echo $personal_info['email']; ?></div></div>
                <div class="row"><div class="label">Phone</div><div class="value"><?php echo $personal_info['phone']; ?></div></div>

                <h3 style="margin:16px 0 10px;">Address</h3>
                <div class="row"><div class="label">District</div><div class="value"><?php echo $personal_info['district']; ?></div></div>
                <div class="row"><div class="label">Address</div><div class="value"><?php echo $personal_info['address']; ?></div></div>

                <!-- ✅ System info (NO eligibility score) -->
                <h3 style="margin:16px 0 10px;">System Info</h3>
                <div class="row">
                  <div class="label">Eligibility</div>
                  <div class="value">
                    <?php
                      $er = $application['eligibility_result'] ?? 'pending';
                      $pill = ($er === 'eligible') ? 'pill-ok' : (($er === 'not_eligible') ? 'pill-bad' : 'pill-warn');
                    ?>
                    <span class="pill <?php echo e($pill); ?>"><?php echo e(formatEligibilityResult($er)); ?></span>
                    <span class="small-note" style="display:inline-block;margin-left:8px;">
                      (Status: <?php echo e($application['eligibility_status'] ?? 'pending'); ?>)
                    </span>
                  </div>
                </div>

                <div class="row"><div class="label">Application Status</div><div class="value"><?php echo ucfirst($personal_info['application_status']); ?></div></div>
                <div class="row"><div class="label">Applied On</div><div class="value"><?php echo $personal_info['applied_on']; ?></div></div>

                <!-- Optional: Account info -->
                <h3 style="margin:16px 0 10px;">Account</h3>
                <div class="row"><div class="label">Registration ID</div><div class="value"><?php echo e($account_info['registration_id']); ?></div></div>
                <div class="row"><div class="label">Created On</div><div class="value"><?php echo e($account_info['created_at']); ?></div></div>
                <div class="row"><div class="label">Last Login</div><div class="value"><?php echo e($account_info['last_login']); ?></div></div>
                <div class="row"><div class="label">Account Status</div><div class="value"><?php echo e($account_info['account_status']); ?></div></div>

            </div>
        </div>

        <!-- ✅ Update phone -->
        <div class="profile-card">
            <div class="card-header">
                <h2><i class="fas fa-phone"></i> Update Phone Number</h2>
            </div>
            <div class="card-body">
                <form id="phoneForm">
                    <input type="hidden" name="action" value="update_phone">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo $personal_info['phone']; ?>" placeholder="+94771234567" required>
                        <div class="small-note">Format: +94XXXXXXXXX or 07XXXXXXXX</div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="phoneBtn">
                        <i class="fas fa-save"></i> Save Phone
                    </button>

                    <div id="phoneMsg" class="server-msg"></div>
                </form>
            </div>
        </div>

        <!-- ✅ Change password -->
        <div class="profile-card">
            <div class="card-header">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="8" required>
                        <div class="small-note">Minimum 8 characters</div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-primary" id="passBtn">
                        <i class="fas fa-key"></i> Change Password
                    </button>

                    <div id="passMsg" class="server-msg"></div>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const logoutBtn = document.getElementById('logoutBtn');

menuToggle?.addEventListener('click', () => sidebar?.classList.toggle('active'));

logoutBtn?.addEventListener('click', () => {
  if (confirm('Are you sure you want to logout?')) window.location.href = '../logout.php';
});

async function postForm(form) {
  const formData = new FormData(form);
  const res = await fetch('profile_action.php', { method: 'POST', body: formData });
  return await res.json();
}

// Phone
const phoneForm = document.getElementById('phoneForm');
const phoneBtn  = document.getElementById('phoneBtn');
const phoneMsg  = document.getElementById('phoneMsg');

phoneForm?.addEventListener('submit', async (e) => {
  e.preventDefault();
  phoneBtn.disabled = true;
  phoneMsg.textContent = 'Saving...';
  phoneMsg.className = 'server-msg';

  try {
    const data = await postForm(phoneForm);
    if (data.ok) {
      phoneMsg.textContent = '✅ ' + data.message;
      phoneMsg.classList.add('server-ok');
    } else {
      phoneMsg.textContent = '❌ ' + (data.message || 'Failed');
      phoneMsg.classList.add('server-bad');
    }
  } catch {
    phoneMsg.textContent = '❌ Network error';
    phoneMsg.classList.add('server-bad');
  } finally {
    phoneBtn.disabled = false;
  }
});

// Password
const passForm = document.getElementById('passwordForm');
const passBtn  = document.getElementById('passBtn');
const passMsg  = document.getElementById('passMsg');

passForm?.addEventListener('submit', async (e) => {
  e.preventDefault();
  passBtn.disabled = true;
  passMsg.textContent = 'Updating...';
  passMsg.className = 'server-msg';

  try {
    const data = await postForm(passForm);
    if (data.ok) {
      passMsg.textContent = '✅ ' + data.message;
      passMsg.classList.add('server-ok');
      passForm.reset();
    } else {
      passMsg.textContent = '❌ ' + (data.message || 'Failed');
      passMsg.classList.add('server-bad');
    }
  } catch {
    passMsg.textContent = '❌ Network error';
    passMsg.classList.add('server-bad');
  } finally {
    passBtn.disabled = false;
  }
});
</script>

</body>
</html>
