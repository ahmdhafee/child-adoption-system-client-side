<?php
require_once 'includes/auth_guard.php';

$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
  header("Location: ../login.php?expired=true");
  exit();
}

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // Check application
  $stmt = $pdo->prepare("SELECT id, status, eligibility_score, eligibility_status, eligibility_result FROM applications WHERE user_id=? LIMIT 1");
  $stmt->execute([$user_id]);
  $app = $stmt->fetch();

  if (!$app || strtolower((string)$app['status']) !== 'approved') {
    header("Location: children.php");
    exit();
  }

  // Must have all required docs approved
  $stmtM = $pdo->prepare("
      SELECT COUNT(*)
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
  $missing = (int)$stmtM->fetchColumn();
  if ($missing > 0) {
    header("Location: children.php");
    exit();
  }

  // One-time only
  if (strtolower((string)$app['eligibility_status']) === 'checked') {
    header("Location: children.php");
    exit();
  }

  // ✅ Decide eligibility (you can replace logic with your full algorithm)
  $score = (int)($app['eligibility_score'] ?? 0);
  $result = ($score >= 75) ? 'eligible' : 'not_eligible';

  $upd = $pdo->prepare("UPDATE applications SET eligibility_status='checked', eligibility_result=?, eligibility_checked_at=NOW() WHERE id=?");
  $upd->execute([$result, (int)$app['id']]);

  header("Location: children.php");
  exit();

} catch (Exception $e) {
  error_log("submit_eligibility error: " . $e->getMessage());
  header("Location: children.php");
  exit();
}
