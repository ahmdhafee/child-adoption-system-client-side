<?php
// client/vote_child.php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

// Auth guard
$guard1 = __DIR__ . '/includes/auth_guard.php';
$guard2 = dirname(__DIR__) . '/includes/auth_guard.php';

if (file_exists($guard1)) {
  require_once $guard1;
} elseif (file_exists($guard2)) {
  require_once $guard2;
} else {
  die("auth_guard.php not found.");
}

function redirect_to($url){
  header("Location: $url");
  exit();
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_to("children.php");
}

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
  redirect_to("children.php?err=csrf");
}

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$child_id = (int)($_POST['child_id'] ?? 0);

if ($user_id <= 0 || $child_id <= 0) {
  redirect_to("children.php?err=invalid");
}

// DB
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  $pdo->beginTransaction();

  // 1) Must have approved application
  $stmtApp = $pdo->prepare("
    SELECT LOWER(COALESCE(a.status,'pending')) AS app_status
    FROM applications a
    WHERE a.user_id = ?
    ORDER BY COALESCE(a.id,0) DESC
    LIMIT 1
  ");
  $stmtApp->execute([$user_id]);
  $app_status = strtolower((string)($stmtApp->fetchColumn() ?? 'pending'));

  if ($app_status !== 'approved') {
    $pdo->rollBack();
    redirect_to("children.php?err=not_approved");
  }

  // 2) ALL uploaded docs must be approved (documents table only)
  $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
  $stmtTotal->execute([$user_id]);
  $total_docs = (int)$stmtTotal->fetchColumn();

  $stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND status = 'approved'");
  $stmtApproved->execute([$user_id]);
  $approved_docs = (int)$stmtApproved->fetchColumn();

  if (!($total_docs > 0 && $total_docs === $approved_docs)) {
    $pdo->rollBack();
    redirect_to("children.php?err=docs_pending");
  }

  // 3) User can vote only once (active)
  $stmtV = $pdo->prepare("SELECT COUNT(*) FROM user_votes WHERE user_id = ? AND status = 'active'");
  $stmtV->execute([$user_id]);
  $already = (int)$stmtV->fetchColumn();

  if ($already > 0) {
    $pdo->rollBack();
    redirect_to("children.php?err=already_voted");
  }

  // 4) Child must exist and be available
  // (FOR UPDATE works best if children table is InnoDB; safe even if not)
  $stmtC = $pdo->prepare("SELECT id, status FROM children WHERE id = ? LIMIT 1 FOR UPDATE");
  $stmtC->execute([$child_id]);
  $child = $stmtC->fetch();

  if (!$child) {
    $pdo->rollBack();
    redirect_to("children.php?err=child_not_found");
  }

  if (strtolower((string)$child['status']) !== 'available') {
    $pdo->rollBack();
    redirect_to("children.php?err=child_not_available");
  }

  // 5) Insert vote (MATCHES your table columns)
  $stmtIns = $pdo->prepare("
    INSERT INTO user_votes (user_id, child_id, vote_date, status)
    VALUES (?, ?, NOW(), 'active')
  ");
  $stmtIns->execute([$user_id, $child_id]);

  // OPTIONAL: mark child as reserved to prevent others selecting same child
  // ⚠️ Only enable if your children.status supports 'reserved'
  // $stmtU = $pdo->prepare("UPDATE children SET status='reserved', updated_at=NOW() WHERE id=? LIMIT 1");
  // $stmtU->execute([$child_id]);

  $pdo->commit();

  redirect_to("children.php?success=voted");

} catch (Throwable $e) {
  try { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); } catch(Throwable $x){}
  error_log("vote_child.php error: " . $e->getMessage());
  redirect_to("children.php?err=system");
}
