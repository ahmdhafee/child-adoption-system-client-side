<?php
// client/inquiries.php

require_once '../includes/auth_guard.php'; // ✅ session guard

// DB config
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?expired=true");
    exit();
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

// ---- helpers ----
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ---- connect db ----
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Inquiry page DB error: " . $e->getMessage());
    $error_message = "System temporarily unavailable. Please try again later.";
}

// ---- Detect inquiries columns & map fields (so it matches YOUR existing table) ----
$cols = [];
$map = [
    'id'         => null,
    'user_id'    => null,
    'subject'    => null,
    'message'    => null,
    'status'     => null,
    'admin_reply'=> null,
    'created_at' => null,
];

if (empty($error_message)) {
    try {
        $stCols = $pdo->query("SHOW COLUMNS FROM inquiries");
        foreach ($stCols->fetchAll() as $r) $cols[] = $r['Field'];

        $has = fn($c) => in_array($c, $cols, true);

        // primary id
        $map['id'] = $has('id') ? 'id' : ($has('inquiry_id') ? 'inquiry_id' : 'id');

        // user link
        $map['user_id'] = $has('user_id') ? 'user_id' : ($has('client_id') ? 'client_id' : 'user_id');

        // subject/title/topic
        $map['subject'] = $has('subject') ? 'subject' : ($has('title') ? 'title' : ($has('topic') ? 'topic' : null));

        // message/description/inquiry
        $map['message'] = $has('message') ? 'message' : ($has('description') ? 'description' : ($has('inquiry') ? 'inquiry' : null));

        // status/state
        $map['status'] = $has('status') ? 'status' : ($has('state') ? 'state' : null);

        // admin reply/reply/response
        $map['admin_reply'] = $has('admin_reply') ? 'admin_reply' : ($has('reply') ? 'reply' : ($has('response') ? 'response' : null));

        // created_at/date
        $map['created_at'] = $has('created_at') ? 'created_at' : ($has('created_date') ? 'created_date' : ($has('date') ? 'date' : null));

        // must have user_id column to work
        if (!$map['user_id'] || !$has($map['user_id'])) {
            $error_message = "Your inquiries table doesn't have a user link column (user_id/client_id).";
        }
    } catch (PDOException $e) {
        error_log("Inquiry table detect error: " . $e->getMessage());
        $error_message = "Inquiries table not found or cannot be read.";
    }
}

// ---- Submit inquiry ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {

    $csrf = $_POST['csrf_token'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error_message = "Invalid request (CSRF). Please refresh and try again.";
    } else {

        // keep names simple for form
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($map['subject'] && $subject === '') $error_message = "Subject is required.";
        if ($map['message'] && $message === '') $error_message = "Message is required.";

        if (empty($error_message)) {
            try {
                $fields = [];
                $values = [];

                // required user id
                $fields[] = $map['user_id'];
                $values[] = $user_id;

                // subject/message only if these columns exist
                if ($map['subject']) { $fields[] = $map['subject']; $values[] = $subject; }
                if ($map['message']) { $fields[] = $map['message']; $values[] = $message; }

                // default status (if exists)
                if ($map['status']) { $fields[] = $map['status']; $values[] = 'open'; }

                // created_at if exists but not auto-handled by DB
                // (we won't force it; many tables have DEFAULT CURRENT_TIMESTAMP)

                $ph = implode(',', array_fill(0, count($fields), '?'));
                $sql = "INSERT INTO inquiries (" . implode(',', $fields) . ") VALUES ($ph)";
                $ins = $pdo->prepare($sql);
                $ins->execute($values);

                $success_message = "Inquiry submitted successfully.";
            } catch (PDOException $e) {
                error_log("Inquiry insert error: " . $e->getMessage());
                $error_message = "Failed to submit inquiry. Please try again.";
            }
        }
    }
}

// ---- Fetch my inquiries ----
$my_inquiries = [];
$open_count = 0;
$reply_count = 0;

if (empty($error_message)) {
    try {
        $select = [];
        foreach (['id','user_id','subject','message','status','admin_reply','created_at'] as $k) {
            if (!empty($map[$k]) && in_array($map[$k], $cols, true)) {
                $select[] = $map[$k];
            }
        }
        if (empty($select)) $select = [$map['id']];

        $orderBy = $map['created_at'] ?: $map['id'];

        $q = $pdo->prepare("SELECT " . implode(',', $select) . " FROM inquiries WHERE {$map['user_id']} = ? ORDER BY $orderBy DESC");
        $q->execute([$user_id]);
        $my_inquiries = $q->fetchAll();

        // small stats
        foreach ($my_inquiries as $row) {
            $st = $map['status'] ? strtolower((string)($row[$map['status']] ?? 'open')) : 'open';
            if (in_array($st, ['open','pending','new','in_progress','in-progress'], true)) $open_count++;
            $rep = $map['admin_reply'] ? trim((string)($row[$map['admin_reply']] ?? '')) : '';
            if ($rep !== '') $reply_count++;
        }

    } catch (PDOException $e) {
        error_log("Inquiry fetch error: " . $e->getMessage());
        $error_message = "Could not load inquiries.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inquiries | Family Bridge Portal</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- ✅ use your existing css like dashboard style folder -->
    <link rel="stylesheet" href="style/index.css">
    <!-- If you have a separate inquiries css, you can add:
    <link rel="stylesheet" href="style/inquiries.css">
    -->

    <link rel="shortcut icon" href="../favlogo.png" type="logo">

    <style>
      /* small safe styles (won't break your css) */
      .form-card{background:#fff;border:1px solid #eee;border-radius:12px;padding:14px;margin-top:12px;}
      .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
      .form-col label{display:block;font-weight:700;margin-bottom:6px;}
      .form-control{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;outline:none;}
      textarea.form-control{min-height:120px;resize:vertical;}
      .btn{border:0;border-radius:10px;padding:10px 12px;font-weight:800;cursor:pointer;}
      .btn-primary{background:#2563eb;color:#fff;}
      .inquiry-item{background:#fff;border:1px solid #eee;border-radius:12px;padding:12px;margin-top:10px;}
      .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800;border:1px solid #e5e7eb;}
      .pill.open{background:#fff7ed;color:#9a3412;border-color:#fed7aa;}
      .pill.closed{background:#e5e7eb;color:#374151;border-color:#d1d5db;}
      .pill.resolved{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
      .pill.progress{background:#e0f2fe;color:#075985;border-color:#bae6fd;}
      .meta{color:#6b7280;font-size:12px;margin-top:6px;}
      .reply-box{margin-top:10px;padding:10px;border-radius:10px;border:1px dashed #e5e7eb;background:#f9fafb;}
      @media(max-width:768px){.form-row{grid-template-columns:1fr;}}
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">

    <?php if (!empty($error_message)): ?>
      <div class="system-alert">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo e($error_message); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div><?php echo e($success_message); ?></div>
      </div>
    <?php endif; ?>

    <div class="welcome-section">
      <div class="welcome-card">
        <h1>Inquiries</h1>
        <p>Send a message to Admin/Support and track replies here.</p>
      </div>
    </div>

    <!-- Quick stats like dashboard -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon pending">
          <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-info">
          <h3><?php echo (int)count($my_inquiries); ?></h3>
          <p>Total Inquiries</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon pending">
          <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-info">
          <h3><?php echo (int)$open_count; ?></h3>
          <p>Open / Pending</p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon eligible">
          <i class="fas fa-reply"></i>
        </div>
        <div class="stat-info">
          <h3><?php echo (int)$reply_count; ?></h3>
          <p>Replies Received</p>
        </div>
      </div>
    </div>

    <!-- Submit inquiry -->
    <div class="quick-actions">
      <h2 class="section-title">
        <i class="fas fa-paper-plane"></i>
        Submit New Inquiry
      </h2>

      <div class="form-card">
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

          <div class="form-row">
            <div class="form-col">
              <label for="subject">Subject</label>
              <input type="text" id="subject" name="subject" class="form-control" placeholder="Eg: Document upload issue" <?php echo ($map['subject'] ? 'required' : ''); ?>>
            </div>

            <div class="form-col">
              <label>Note</label>
              <input type="text" class="form-control" value="Admin will reply here after review" disabled>
            </div>
          </div>

          <div class="form-col" style="margin-top:12px;">
            <label for="message">Message</label>
            <textarea id="message" name="message" class="form-control" placeholder="Write your inquiry..." <?php echo ($map['message'] ? 'required' : ''); ?>></textarea>
          </div>

          <div style="margin-top:12px;">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-paper-plane"></i> Submit Inquiry
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- My inquiries list -->
    <div class="recent-activity">
      <h2 class="section-title">
        <i class="fas fa-list"></i>
        My Inquiries
      </h2>

      <?php if (empty($my_inquiries)): ?>
        <p style="padding: 10px 0; color:#666;">No inquiries yet.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($my_inquiries as $row): ?>
            <?php
              $id = $row[$map['id']] ?? '';
              $sub = $map['subject'] ? (string)($row[$map['subject']] ?? 'Inquiry') : 'Inquiry';
              $msg = $map['message'] ? (string)($row[$map['message']] ?? '') : '';
              $st  = $map['status'] ? strtolower((string)($row[$map['status']] ?? 'open')) : 'open';
              $rep = $map['admin_reply'] ? trim((string)($row[$map['admin_reply']] ?? '')) : '';
              $dt  = $map['created_at'] ? (string)($row[$map['created_at']] ?? '') : '';

              $pill = 'open';
              if (in_array($st, ['in_progress','in-progress','progress'], true)) $pill = 'progress';
              if ($st === 'resolved') $pill = 'resolved';
              if ($st === 'closed') $pill = 'closed';
            ?>
            <div class="inquiry-item">
              <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <strong>#<?php echo e($id); ?> — <?php echo e($sub); ?></strong>
                <span class="pill <?php echo e($pill); ?>">
                  <i class="fas fa-flag"></i> <?php echo e(strtoupper($st)); ?>
                </span>
              </div>

              <?php if ($dt !== ''): ?>
                <div class="meta"><i class="fas fa-calendar"></i> <?php echo e($dt); ?></div>
              <?php endif; ?>

              <?php if ($msg !== ''): ?>
                <div style="margin-top:8px;color:#111827;white-space:pre-wrap;line-height:1.45;">
                  <?php echo e($msg); ?>
                </div>
              <?php endif; ?>

              <?php if ($rep !== ''): ?>
                <div class="reply-box">
                  <strong><i class="fas fa-reply"></i> Admin Reply</strong>
                  <div style="margin-top:6px;white-space:pre-wrap;line-height:1.45;">
                    <?php echo e($rep); ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="meta" style="margin-top:10px;">
                  <i class="fas fa-clock"></i> Waiting for admin reply...
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

</main>

</body>
</html>
