<?php
// client/vote.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?expired=true");
    exit();
}

$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$child_id = (int)($_GET['child_id'] ?? 0);

$message = '';
$type = 'error';

try {
    if ($user_id <= 0) throw new Exception("Invalid session.");
    if ($child_id <= 0) throw new Exception("Invalid child selected.");

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // ✅ Check application + docs + eligibility gates (same logic as children.php)
    $stmt = $pdo->prepare("
        SELECT a.status AS app_status,
               a.eligibility_checked, a.eligibility_passed
        FROM applications a
        WHERE a.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) throw new Exception("Application not found.");
    if (strtolower($app['app_status']) !== 'approved') throw new Exception("Your application is not approved.");
    if ((int)$app['eligibility_checked'] !== 1 || (int)$app['eligibility_passed'] !== 1) {
        throw new Exception("You must pass eligibility check first.");
    }

    // ✅ Docs approved?
    $stmtM = $pdo->prepare("
        SELECT COUNT(*) AS missing_count
        FROM document_requirements dr
        LEFT JOIN documents d
          ON d.requirement_id = dr.id
         AND d.user_id = ?
         AND d.status = 'approved'
        WHERE dr.is_required = 1
          AND d.id IS NULL
    ");
    $stmtM->execute([$user_id]);
    $missing_docs = (int)$stmtM->fetchColumn();
    if ($missing_docs > 0) {
        throw new Exception("Documents not fully approved. Missing approvals: {$missing_docs}");
    }

    // ✅ Child must be available
    $c = $pdo->prepare("SELECT id, name, status FROM children WHERE id = ? LIMIT 1");
    $c->execute([$child_id]);
    $child = $c->fetch(PDO::FETCH_ASSOC);

    if (!$child) throw new Exception("Child not found.");
    if (($child['status'] ?? '') !== 'available') throw new Exception("This child is not available for voting.");

    // ✅ Check if already voted (one vote per user)
    $v = $pdo->prepare("SELECT id, child_id FROM user_votes WHERE user_id = ? AND status = 'active' LIMIT 1");
    $v->execute([$user_id]);
    $existing = $v->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        throw new Exception("You already voted. You cannot vote again.");
    }

    // ✅ Insert vote (transaction)
    $pdo->beginTransaction();

    $ins = $pdo->prepare("INSERT INTO user_votes (user_id, child_id, status, vote_date) VALUES (?, ?, 'active', NOW())");
    $ins->execute([$user_id, $child_id]);

    $pdo->commit();

    $type = 'success';
    $message = "Vote submitted successfully for " . htmlspecialchars($child['name']) . ". You cannot change it.";

} catch (PDOException $e) {
    // If unique(user_id) triggers, show friendly message
    if ((int)$e->errorInfo[1] === 1062) {
        $message = "You already voted. You cannot vote again.";
    } else {
        error_log("vote.php PDO error: " . $e->getMessage());
        $message = "System error. Please try again.";
    }
} catch (Exception $e) {
    $message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vote | Family Bridge</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body{font-family:Segoe UI,Tahoma,Verdana,sans-serif;background:#f6f8fb;margin:0;padding:40px}
    .box{max-width:600px;margin:auto;background:#fff;border:1px solid #eee;border-radius:14px;padding:18px}
    .msg{padding:14px;border-radius:12px;font-weight:700}
    .ok{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .bad{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .btn{display:inline-flex;gap:8px;align-items:center;margin-top:14px;padding:10px 14px;border-radius:12px;border:0;cursor:pointer;text-decoration:none;font-weight:700}
    .btn-primary{background:#2c7be5;color:#fff}
    .btn-outline{background:#fff;border:1px solid #ddd;color:#111}
  </style>
</head>
<body>
  <div class="box">
    <h2><i class="fas fa-vote-yea"></i> Vote Result</h2>

    <div class="msg <?php echo ($type === 'success') ? 'ok' : 'bad'; ?>">
      <?php echo htmlspecialchars($message); ?>
    </div>

    <a class="btn btn-primary" href="children.php"><i class="fas fa-arrow-left"></i> Back to Children</a>
    <a class="btn btn-outline" href="index.php"><i class="fas fa-home"></i> Dashboard</a>
  </div>
</body>
</html>
