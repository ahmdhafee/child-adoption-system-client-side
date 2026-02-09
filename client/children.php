<?php
// client/children.php

// --- Debug (keep OFF in production) ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- Logging ---
ini_set('log_errors', '1');
// safest: logs folder one level up from /client
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

// --- Auth guard (FIXED PATH) ---
// If auth_guard.php is inside client/includes/
$guard1 = __DIR__ . '/includes/auth_guard.php';
// If auth_guard.php is in root /includes/ (outside client)
$guard2 = dirname(__DIR__) . '/includes/auth_guard.php';

if (file_exists($guard1)) {
  require_once $guard1;
} elseif (file_exists($guard2)) {
  require_once $guard2;
} else {
  die("auth_guard.php not found. Checked:<br>" . htmlspecialchars($guard1) . "<br>" . htmlspecialchars($guard2));
}

// CSRF (needed for vote form)
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// DB
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_name = 'User';
$user_reg_id = 'Not Set';
$application_status = 'pending';

// Gate flags
$docs_ok = false;
$missing_docs = 0;        // number of docs not yet approved (for message)
$has_voted = false;
$can_view_children = false;

// Doc counts (for UI)
$total_docs = 0;
$approved_docs = 0;

// Children list (sanitized / anonymous)
$children = [];
$error_message = '';
$info_message = '';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  $user_id = (int)($_SESSION['user_id'] ?? 0);
  if ($user_id <= 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?expired=true");
    exit();
  }

  // ✅ Get user + latest application
  $stmt = $pdo->prepare("
    SELECT
      u.id, u.email, u.registration_id,
      a.husband_name, a.wife_name,
      LOWER(COALESCE(a.status,'pending')) AS app_status
    FROM users u
    LEFT JOIN applications a ON a.user_id = u.id
    WHERE u.id = ?
    ORDER BY COALESCE(a.id, 0) DESC
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

  if (!empty($user['husband_name']) && !empty($user['wife_name'])) {
    $user_name = e($user['husband_name'] . ' & ' . $user['wife_name']);
  } elseif (!empty($user['husband_name'])) {
    $user_name = e($user['husband_name']);
  } else {
    $user_name = e($user['email']);
  }

  $user_reg_id = e($user['registration_id'] ?? 'Not Set');
  $application_status = strtolower((string)($user['app_status'] ?? 'pending'));

  // ✅ Documents check (ONLY documents table) - ALL documents must be approved
  $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
  $stmtTotal->execute([$user_id]);
  $total_docs = (int)$stmtTotal->fetchColumn();

  $stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND status = 'approved'");
  $stmtApproved->execute([$user_id]);
  $approved_docs = (int)$stmtApproved->fetchColumn();

  // Unlock only if at least 1 document exists and ALL are approved
  $docs_ok = ($total_docs > 0 && $total_docs === $approved_docs);

  // For display: how many still not approved
  $missing_docs = max(0, $total_docs - $approved_docs);

  // ✅ Has voted? (SAFE: won't crash if user_votes missing)
  try {
    $stmtV = $pdo->prepare("SELECT COUNT(*) FROM user_votes WHERE user_id = ? AND status = 'active'");
    $stmtV->execute([$user_id]);
    $has_voted = ((int)$stmtV->fetchColumn()) > 0;
  } catch (Throwable $ex) {
    error_log("user_votes check skipped: " . $ex->getMessage());
    $has_voted = false;
  }

  // ✅ Gate rules
  $can_view_children = ($application_status === 'approved' && $docs_ok);

  if (!$can_view_children) {
    if ($application_status !== 'approved') {
      $info_message = "Your application is not approved yet. Children profiles will unlock after approval and document verification.";
    } elseif ($total_docs <= 0) {
      $info_message = "Please upload your documents first. Children profiles unlock only after ALL uploaded documents are approved.";
    } elseif (!$docs_ok) {
      $info_message = "All uploaded documents must be approved by admin. Approved: {$approved_docs} / {$total_docs}. Pending/Not approved: {$missing_docs}.";
    }
  } else {
    // ✅ Fetch children (ANONYMOUS view)
    // ✅ Fetch children (ANONYMOUS view)
$sql = "
SELECT
  id,
  child_code,
  age,
  gender,
  special_needs,
  added_at
FROM children
WHERE LOWER(status) = 'available'
ORDER BY id DESC
";
$children = $pdo->query($sql)->fetchAll();

if (empty($children)) {
$info_message = "No children profiles are available right now. Please check again later.";
}

  }

} catch (Throwable $e) {
  error_log("children.php error: " . $e->getMessage());
  $error_message = "System error. Please try again later.";

  // DEV (temporary): uncomment to see exact reason
  // $error_message = "System error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Children | Family Bridge Portal</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style/children.css">
  <link rel="shortcut icon" href="../favlogo.png" type="logo">

  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Segoe UI,Arial;}
    body{background:#f6f7fb;}
    .main-content{padding:18px;}
    .page-header{background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:16px;margin-bottom:14px;}
    .page-header h1{font-size:22px;margin-bottom:6px;}
    .page-header p{color:#6b7280;}
    .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;font-weight:800;font-size:12px;border:1px solid #e6e6e6;background:#fff;}
    .pill.ok{background:#dcfce7;border-color:#bbf7d0;color:#166534;}
    .pill.warn{background:#fff7ed;border-color:#fed7aa;color:#9a3412;}
    .pill.bad{background:#fee2e2;border-color:#fecaca;color:#991b1b;}
    .alerts{display:grid;gap:10px;margin:12px 0;}
    .alert{background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:12px;display:flex;gap:10px;align-items:flex-start;}
    .alert i{margin-top:2px;}
    .alert.error{background:#fee2e2;border-color:#fecaca;color:#991b1b;}
    .alert.info{background:#e0f2fe;border-color:#bae6fd;color:#075985;}
    .alert.success{background:#dcfce7;border-color:#bbf7d0;color:#166534;}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
    .card{background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:14px;}
    .card h3{font-size:16px;margin-bottom:8px;}
    .meta{color:#6b7280;font-size:13px;display:flex;gap:14px;flex-wrap:wrap;}
    .meta span{display:inline-flex;align-items:center;gap:7px;}
    .btnrow{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;}
    .btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:800;display:inline-flex;align-items:center;gap:8px;}
    .btn-primary{background:#2563eb;color:#fff;}
    .btn-outline{background:#fff;border:1px solid #e6e6e6;}
    .btn-disabled{background:#e5e7eb;color:#6b7280;cursor:not-allowed;}
    .empty{padding:30px;text-align:center;color:#6b7280;background:#fff;border:1px solid #e6e6e6;border-radius:14px;}
    @media (max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}
    @media (max-width:650px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

  <div class="page-header">
    <h1>Children Profiles</h1>
    <p>
      Children details are shown only after <strong>ALL</strong> uploaded documents are approved by admin.
      For privacy, profiles are displayed anonymously (no photo, no name, no district).
    </p>

    <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
      <span class="pill <?php echo ($application_status==='approved') ? 'ok' : 'warn'; ?>">
        <i class="fas fa-file-signature"></i> Application: <?php echo e(ucfirst($application_status)); ?>
      </span>

      <span class="pill <?php echo $docs_ok ? 'ok' : 'warn'; ?>">
        <i class="fas fa-file-circle-check"></i> Documents: <?php echo $docs_ok ? 'Approved' : 'Pending'; ?>
      </span>

      <span class="pill <?php echo ($total_docs>0 && $approved_docs===$total_docs) ? 'ok' : 'warn'; ?>">
        <i class="fas fa-list-check"></i> Doc Progress: <?php echo (int)$approved_docs; ?> / <?php echo (int)$total_docs; ?>
      </span>


      <span class="pill <?php echo $has_voted ? 'ok' : 'warn'; ?>">
        <i class="fas fa-vote-yea"></i> Vote: <?php echo $has_voted ? 'Submitted' : 'Not submitted'; ?>
      </span>

      
    </div>
  </div>

  <div class="alerts">
    <?php if (!empty($error_message)): ?>
      <div class="alert error">
        <i class="fas fa-exclamation-circle"></i>
        <div><strong>Error:</strong> <?php echo e($error_message); ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($info_message)): ?>
      <div class="alert info">
        <i class="fas fa-info-circle"></i>
        <div><?php echo e($info_message); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($docs_ok && $application_status === 'approved' && !$has_voted): ?>
      <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <div>
          <strong>Unlocked:</strong> You can now browse anonymous child profiles and vote for only one child from this page.
        </div>
      </div>
    <?php elseif ($has_voted): ?>
      <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <div>
          <strong>Vote Submitted:</strong> You already voted. You cannot vote again. Please wait for the appointment scheduled by admin.
          <a href="appointments.php" style="font-weight:900;color:inherit;text-decoration:underline;">View Appointment</a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!($application_status === 'approved' && $docs_ok)): ?>
    <div class="empty">
      <i class="fas fa-lock" style="font-size:42px;margin-bottom:10px;"></i>
      <div style="font-weight:900;margin-bottom:6px;">Children profiles are locked</div>
      <div style="max-width:720px;margin:0 auto;">
        <?php if ($application_status !== 'approved'): ?>
          Your application is not approved yet. Once approved, all your uploaded documents must also be approved by admin.
        <?php else: ?>
          All uploaded documents must be approved by admin to unlock children profiles.
          Approved: <strong><?php echo (int)$approved_docs; ?></strong> /
          <strong><?php echo (int)$total_docs; ?></strong>.
          Go to <a href="documents.php" style="font-weight:900;">Documents</a>.
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>

    <?php if (empty($children)): ?>
      <div class="empty">
        <i class="fas fa-child" style="font-size:42px;margin-bottom:10px;"></i>
        <div style="font-weight:900;margin-bottom:6px;">No children available</div>
        <div class="meta" style="justify-content:center;">Please check again later.</div>
      </div>
    <?php else: ?>

      <div class="grid">
        <?php foreach ($children as $c): ?>
          <?php
            $cid = (int)($c['id'] ?? 0);
            $age = $c['age'] ?? null;
            $gender = $c['gender'] ?? null;
            $notes = $c['special_notes'] ?? '';
            $created = $c['created_at'] ?? '';
            $code = 'CH-' . str_pad((string)$cid, 5, '0', STR_PAD_LEFT);
          ?>
          <div class="card">
            <h3><i class="fas fa-child"></i> Anonymous Profile: <?php echo e($code); ?></h3>

            <div class="meta">
              <span><i class="fas fa-cake-candles"></i> Age: <b><?php echo e(($age !== null && $age !== '') ? $age : 'N/A'); ?></b></span>
              <span><i class="fas fa-venus-mars"></i> Gender: <b><?php echo e(($gender !== null && $gender !== '') ? $gender : 'N/A'); ?></b></span>
              <?php if (!empty($created)): ?>
                <span><i class="fas fa-calendar"></i> Added: <b><?php echo e(substr((string)$created, 0, 10)); ?></b></span>
              <?php endif; ?>
            </div>

            <div style="margin-top:10px;color:#374151;font-size:13px;line-height:1.4;">
              <?php if (!empty($notes)): ?>
                <i class="fas fa-note-sticky"></i> <?php echo e($notes); ?>
              <?php else: ?>
                <i class="fas fa-shield-heart"></i> Confidential profile (limited public details)
              <?php endif; ?>
            </div>

            <div class="btnrow">
              <?php if ($has_voted): ?>
                <button class="btn btn-disabled" type="button" disabled>
                  <i class="fas fa-lock"></i> Vote locked (already submitted)
                </button>
              <?php else: ?>
                <form action="vote_child.php" method="POST" onsubmit="return confirm('Vote for this child? You can vote only once.');" style="display:inline;">
                  <input type="hidden" name="child_id" value="<?php echo (int)$cid; ?>">
                  <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
                  <button class="btn btn-primary" type="submit">
                    <i class="fas fa-vote-yea"></i> Vote for this child
                  </button>
                </form>
              <?php endif; ?>

              <a class="btn btn-outline" href="documents.php">
                <i class="fas fa-file-alt"></i> Documents
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="alert info" style="margin-top:14px;">
        <i class="fas fa-info-circle"></i>
        <div>
          <strong>Privacy Note:</strong> Photos, names, and districts are hidden to protect the child's identity.
          After voting, the admin will arrange your appointment.
        </div>
      </div>

    <?php endif; ?>
  <?php endif; ?>

</main>

<script>
document.getElementById('logoutBtn')?.addEventListener('click', () => {
  if (confirm('Are you sure you want to logout?')) window.location.href = '../logout.php';
});
</script>
</body>
</html>
