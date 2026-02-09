<?php
// client/upload_document.php  ✅ FULL WORKING (secure upload + validation + insert)
// Works with your client/documents.php page.
//
// Needs:
// - ../uploads/ folder exists (outside client) OR it will try to create it
// - documents table has columns used below
// - required_documents table has allowed_formats,max_size_mb,category,is_active,is_required
//
// NOTE: This file ENDS with proper PHP closing flow (no HTML).

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: ../login.php?expired=true");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: documents.php");
  exit();
}

// CSRF
if (
  empty($_POST['csrf_token']) ||
  empty($_SESSION['csrf_token']) ||
  !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
  header("Location: documents.php?error=upload_failed");
  exit();
}

$user_id        = (int)($_SESSION['user_id'] ?? 0);
$requirement_id = (int)($_POST['requirement_id'] ?? 0);
$description    = trim((string)($_POST['description'] ?? ''));

if ($user_id <= 0 || $requirement_id <= 0) {
  header("Location: documents.php?error=upload_failed");
  exit();
}

// DB
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

function clean_original_name(string $name): string {
  $name = basename($name);
  $name = preg_replace('/[^\w\.\-]+/u', '_', $name); // letters numbers _ . -
  $name = preg_replace('/_+/', '_', $name);
  return substr($name, 0, 180);
}

function normalize_allowed(string $allowed): array {
  $arr = array_values(array_filter(array_map('trim', explode(',', strtolower($allowed)))));
  // allow "jpg" to accept jpeg too
  if (in_array('jpg', $arr, true) && !in_array('jpeg', $arr, true)) $arr[] = 'jpeg';
  return $arr;
}

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $username,
    $password,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );

  // ✅ 1) Get requirement (must be active + required)
  $r = $pdo->prepare("
    SELECT id, requirement_name, category, max_size_mb, allowed_formats, is_active, is_required
    FROM required_documents
    WHERE id = ? AND is_active = 1
    LIMIT 1
  ");
  $r->execute([$requirement_id]);
  $req = $r->fetch();

  if (!$req || (int)$req['is_required'] !== 1) {
    throw new Exception("Invalid/Inactive requirement.");
  }

  // ✅ 2) Lock: if already approved for this requirement, block re-upload
  $lock = $pdo->prepare("
    SELECT COUNT(*)
    FROM documents
    WHERE user_id = ? AND requirement_id = ? AND status = 'approved'
  ");
  $lock->execute([$user_id, $requirement_id]);
  if ((int)$lock->fetchColumn() > 0) {
    header("Location: documents.php?error=upload_failed");
    exit();
  }

  // ✅ 3) file exists
  if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new Exception("No file / upload error.");
  }

  $file = $_FILES['file'];

  // ✅ 4) size check
  $max_mb = (int)($req['max_size_mb'] ?? 10);
  if ($max_mb <= 0) $max_mb = 10;
  $max_bytes = $max_mb * 1024 * 1024;

  if ((int)$file['size'] <= 0 || (int)$file['size'] > $max_bytes) {
    throw new Exception("File size invalid.");
  }

  // ✅ 5) extension check
  $allowed_str = (string)($req['allowed_formats'] ?? 'pdf,jpg,jpeg,png');
  $allowed_arr = normalize_allowed($allowed_str);

  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if ($ext === '' || !in_array($ext, $allowed_arr, true)) {
    throw new Exception("Extension not allowed.");
  }

  // ✅ 6) MIME validation (content-based)
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime  = $finfo->file($file['tmp_name']);

  $mimeMap = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    // If you add more formats in required_documents, add their MIME types here too.
  ];

  if (isset($mimeMap[$ext]) && !in_array($mime, $mimeMap[$ext], true)) {
    throw new Exception("MIME mismatch.");
  }

  // ✅ 7) prepare upload folder
  // Preferred base folder: /uploads (outside client)
  $base_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';

  if (!is_dir($base_dir)) {
    if (!mkdir($base_dir, 0775, true)) {
      throw new Exception("Cannot create uploads folder.");
    }
  }

  // user folder
  $user_dir = $base_dir . DIRECTORY_SEPARATOR . $user_id;
  if (!is_dir($user_dir)) {
    if (!mkdir($user_dir, 0775, true)) {
      throw new Exception("Cannot create user upload folder.");
    }
  }

  // ✅ 8) safe names
  $original_name = clean_original_name((string)$file['name']);
  $new_file_name = "REQ{$requirement_id}_U{$user_id}_" . date('Ymd_His') . "_" . bin2hex(random_bytes(4)) . "." . $ext;

  $dest = $user_dir . DIRECTORY_SEPARATOR . $new_file_name;

  // ✅ 9) move file
  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    throw new Exception("move_uploaded_file failed.");
  }

  // DB path (web path)
  // If your project root serves "/uploads", keep this as-is.
  $db_path = "/uploads/{$user_id}/{$new_file_name}";

  // ✅ 10) Insert as pending
  $ins = $pdo->prepare("
    INSERT INTO documents
      (user_id, requirement_id, file_name, original_name, file_path, file_size, file_type, category, description, status, upload_date)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
  ");

  $ins->execute([
    $user_id,
    $requirement_id,
    $new_file_name,
    $original_name,
    $db_path,
    (int)$file['size'],
    $mime,
    $req['category'] ?? 'other',
    $description
  ]);

  header("Location: documents.php?uploaded=true");
  exit();

} catch (Exception $e) {
  error_log("upload_document.php error: " . $e->getMessage());
  header("Location: documents.php?error=upload_failed");
  exit();
}
