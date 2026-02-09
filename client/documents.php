<?php
// client/documents.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');

require_once __DIR__ . '/auth_guard.php'; // must start session + logged_in check

// CSRF
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
$eligibility_result = 'pending'; // eligible | not_eligible | pending

$documents_data = [];
$required_docs = [];
$reqStatusMap = []; // requirement_id => map

$total_required = 0;
$required_approved = 0;
$required_missing = 0;

$approved_count = 0;
$pending_count = 0;
$rejected_count = 0;

$error_message = '';
$success_message = '';

$can_view_children = false;

// Helpers
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function getCategoryName(?string $category): string {
  $map = [
    'identity' => 'Identity Proof',
    'home-study' => 'Home Study',
    'medical' => 'Medical Reports',
    'legal' => 'Legal Documents',
    'financial' => 'Financial Documents',
    'other' => 'Other',
  ];
  $category = $category ?: 'other';
  return $map[$category] ?? $category;
}
function getDocumentIcon(?string $category): string {
  $map = [
    'identity' => 'fa-id-card',
    'home-study' => 'fa-home',
    'medical' => 'fa-file-medical',
    'legal' => 'fa-file-contract',
    'financial' => 'fa-file-invoice-dollar',
    'other' => 'fa-file',
  ];
  $category = $category ?: 'other';
  return $map[$category] ?? 'fa-file';
}
function getDocumentColor(?string $category): string {
  $map = [
    'identity' => '#4CAF50',
    'home-study' => '#FF9800',
    'medical' => '#8B4513',
    'legal' => '#2196F3',
    'financial' => '#5D8AA8',
    'other' => '#A9A9A6',
  ];
  $category = $category ?: 'other';
  return $map[$category] ?? '#A9A9A6';
}
function getStatusText(?string $status): string {
  $map = [
    'approved' => 'Approved',
    'pending' => 'Pending Review',
    'rejected' => 'Rejected',
    'uploaded' => 'Uploaded',
  ];
  $status = $status ?: 'pending';
  return $map[$status] ?? $status;
}
function formatFileSize($bytes): string {
  $bytes = (int)$bytes;
  if ($bytes <= 0) return '0 Bytes';
  $k = 1024;
  $sizes = ['Bytes', 'KB', 'MB', 'GB'];
  $i = (int)floor(log($bytes) / log($k));
  $i = min($i, count($sizes) - 1);
  return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
function badgeClassByState(string $state): string {
  if ($state === 'approved') return 'status-approved';
  if ($state === 'pending') return 'status-pending';
  if ($state === 'rejected') return 'status-rejected';
  return 'status-missing';
}
function stateLabel(string $state): string {
  if ($state === 'approved') return 'Approved';
  if ($state === 'pending') return 'Pending';
  if ($state === 'rejected') return 'Rejected (Re-upload required)';
  return 'Missing';
}
function eligibilityBadge(string $res): array {
  if ($res === 'eligible') return ['Eligible', 'status-approved'];
  if ($res === 'not_eligible') return ['Not Eligible', 'status-rejected'];
  return ['Pending', 'status-pending'];
}
function appBadge(string $st): array {
  $st = strtolower($st);
  if ($st === 'approved') return ['Approved', 'status-approved'];
  if ($st === 'rejected') return ['Rejected', 'status-rejected'];
  if ($st === 'under_review') return ['Under Review', 'status-pending'];
  return ['Pending', 'status-pending'];
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

  $user_id = (int)($_SESSION['user_id'] ?? 0);
  if ($user_id <= 0) {
    session_destroy();
    header("Location: ../login.php?expired=true");
    exit();
  }

  // ✅ Get user + latest application (based on YOUR table: husband_name/wife_name)
  $stmt = $pdo->prepare("
    SELECT
      u.id, u.email, u.registration_id,
      a.husband_name, a.wife_name,
      COALESCE(a.status,'pending') AS app_status,
      COALESCE(a.eligibility_result,'pending') AS eligibility_result
    FROM users u
    LEFT JOIN applications a ON u.id = a.user_id
    WHERE u.id = ?
    ORDER BY a.id DESC
    LIMIT 1
  ");
  $stmt->execute([$user_id]);
  $user = $stmt->fetch();

  if (!$user) {
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
  $eligibility_result = (string)($user['eligibility_result'] ?? 'pending');
  $application_status = strtolower((string)($user['app_status'] ?? 'pending'));

  // ✅ Required docs list (Chief Officer creates here)
  $req = $pdo->query("
    SELECT
      id, requirement_name, category, description,
      is_required, max_files, max_size_mb, allowed_formats,
      required_for, sort_order, is_active
    FROM required_documents
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
  ");
  $required_docs = $req->fetchAll();

  foreach ($required_docs as $r) {
    if ((int)$r['is_required'] === 1) $total_required++;
  }

  // ✅ Uploaded documents by user
  $documents_stmt = $pdo->prepare("
    SELECT d.*, rd.requirement_name
    FROM documents d
    LEFT JOIN required_documents rd ON rd.id = d.requirement_id
    WHERE d.user_id = ?
    ORDER BY d.upload_date DESC, d.id DESC
  ");
  $documents_stmt->execute([$user_id]);
  $documents_data = $documents_stmt->fetchAll();

  // Counts
  foreach ($documents_data as $document) {
    $st = (string)($document['status'] ?? '');
    if ($st === 'approved') $approved_count++;
    elseif ($st === 'pending') $pending_count++;
    elseif ($st === 'rejected') $rejected_count++;
  }

  // ✅ Build required status map
  foreach ($required_docs as $r) {
    if ((int)$r['is_required'] !== 1) continue;
    $rid = (int)$r['id'];
    $reqStatusMap[$rid] = [
      'hasApproved' => false,
      'hasPending' => false,
      'latestStatus' => null,
      'state' => 'missing',
    ];
  }

  foreach ($documents_data as $d) {
    $rid = (int)($d['requirement_id'] ?? 0);
    if ($rid <= 0 || !isset($reqStatusMap[$rid])) continue;

    $status = (string)($d['status'] ?? '');

    if ($status === 'approved') $reqStatusMap[$rid]['hasApproved'] = true;
    if ($status === 'pending')  $reqStatusMap[$rid]['hasPending']  = true;

    if ($reqStatusMap[$rid]['latestStatus'] === null) {
      $reqStatusMap[$rid]['latestStatus'] = $status;
    }
  }

  foreach ($required_docs as $r) {
    if ((int)$r['is_required'] !== 1) continue;
    $rid = (int)$r['id'];

    $state = 'missing';
    if (!empty($reqStatusMap[$rid]['hasApproved'])) $state = 'approved';
    elseif (!empty($reqStatusMap[$rid]['hasPending'])) $state = 'pending';
    elseif (($reqStatusMap[$rid]['latestStatus'] ?? '') === 'rejected') $state = 'rejected';

    $reqStatusMap[$rid]['state'] = $state;

    if ($state === 'approved') $required_approved++;
    else $required_missing++;
  }

  // ✅ Unlock children only when:
  // - app approved
  // - all mandatory docs approved
  $docs_ok = ($total_required > 0) ? ($required_missing === 0) : false; // if 0 required -> keep locked (safer)
  $can_view_children = ($application_status === 'approved' && $docs_ok);

  // messages
  if (!empty($_GET['uploaded']) && $_GET['uploaded'] === 'true') {
    $success_message = "Document uploaded successfully. Admin will review it.";
  }
  if (!empty($_GET['deleted']) && $_GET['deleted'] === 'true') {
    $success_message = "Document deleted successfully.";
  }
  if (!empty($_GET['error']) && $_GET['error'] === 'upload_failed') {
    $error_message = "Upload failed. Please check file size/type and try again.";
  }

} catch (Exception $e) {
  error_log("client/documents.php error: " . $e->getMessage());
  $error_message = "System error. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Center | Family Bridge Portal</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style/documents.css">
  <link rel="shortcut icon" href="../favlogo.png" type="logo">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
    .system-alert{background:#f8d7da;color:#721c24;padding:14px;border-radius:10px;margin:12px 0;border:1px solid #f5c6cb}
    .top-card{background:#fff;border:1px solid #eee;border-radius:12px;padding:14px;margin:14px 0}
    .top-row{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .btn-link{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;text-decoration:none;font-weight:800}
    .btn-link.primary{background:#2c7be5;color:#fff}
    .btn-link.disabled{background:#f3f4f6;color:#9ca3af;pointer-events:none}
    .hint{color:#666;font-size:.92rem;margin-top:6px}
    .status-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;font-weight:900;font-size:12px;border:1px solid #e6e6e6;background:#fff}
    .status-approved{background:#dcfce7;border-color:#bbf7d0;color:#166534}
    .status-pending{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
    .status-rejected{background:#fee2e2;border-color:#fecaca;color:#991b1b}
    .status-missing{background:#f3f4f6;border-color:#e5e7eb;color:#6b7280}
    .req-item{background:#fff;border:1px solid #eee;border-radius:12px;padding:14px;margin:10px 0;display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
    .req-left{display:flex;gap:12px}
    .req-ico{width:42px;height:42px;border-radius:12px;background:#f3f4f6;display:flex;align-items:center;justify-content:center}
    .req-ico i{font-size:18px;color:#111827}
    .small{color:#777;font-size:.9rem;margin-top:6px}
    .upload-box{background:#fff;border:1px solid #eee;border-radius:12px;padding:14px;margin:14px 0}
    .form-group{margin-top:10px}
    label{font-weight:800;display:block;margin-bottom:6px}
    select,input[type="file"],textarea{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:10px;outline:none}
    textarea{min-height:90px}
    .btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:900}
    .btn-success{background:#16a34a;color:#fff}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden}
    th,td{padding:12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;vertical-align:top}
    th{background:#f9fafb;font-weight:900}
    .actions a{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;border:1px solid #e5e7eb;text-decoration:none;color:#111827;margin-right:6px}
    .actions a.danger{border-color:#fecaca;color:#991b1b}
    .actions a.lock{background:#f3f4f6;color:#6b7280;pointer-events:none}
    @media(max-width:900px){table{display:block;overflow:auto}}
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">

  <div class="top-card">
    <div class="top-row">
      <div>
        <div style="font-weight:900;font-size:18px;"><i class="fas fa-file-alt"></i> Document Center</div>
        <div class="hint">
          Chief Officer creates the mandatory document list. You must upload each mandatory document.
          Admin will review and approve. Only after ALL mandatory documents are approved, you can view child profiles.
        </div>
      </div>

      <?php if ($can_view_children): ?>
        <a class="btn-link primary" href="children.php"><i class="fas fa-unlock"></i> Go to Children</a>
      <?php else: ?>
        <a class="btn-link disabled" href="#"><i class="fas fa-lock"></i> Children Locked</a>
      <?php endif; ?>
    </div>

    <?php
      [$eligText,$eligClass] = eligibilityBadge($eligibility_result);
      [$appText,$appClass] = appBadge($application_status);
      $percent = ($total_required > 0) ? (int)round(($required_approved / $total_required) * 100) : 0;
    ?>
    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <span class="status-badge <?php echo e($appClass); ?>"><i class="fas fa-file-signature"></i> Application: <?php echo e($appText); ?></span>
      <span class="status-badge <?php echo e($eligClass); ?>"><i class="fas fa-user-check"></i> Eligibility: <?php echo e($eligText); ?></span>
      <span class="status-badge <?php echo $percent===100 ? 'status-approved' : 'status-pending'; ?>">
        <i class="fas fa-clipboard-check"></i> Mandatory Approved: <?php echo (int)$required_approved; ?>/<?php echo (int)$total_required; ?> (<?php echo (int)$percent; ?>%)
      </span>
      <span style="color:#666;">Registration ID: <strong><?php echo $user_reg_id; ?></strong></span>
    </div>
  </div>

  <?php if ($error_message): ?>
    <div class="system-alert"><i class="fas fa-exclamation-circle"></i> <?php echo e($error_message); ?></div>
  <?php endif; ?>

  <?php if ($success_message): ?>
    <div class="status-badge status-approved" style="border-radius:12px;padding:12px 14px;margin:12px 0;">
      <i class="fas fa-check-circle"></i> <?php echo e($success_message); ?>
    </div>
  <?php endif; ?>

  <!-- ✅ NEW CLIENT MAIN MESSAGE -->
  <?php if ($total_required === 0): ?>
    <div class="status-badge status-pending" style="border-radius:12px;padding:12px 14px;margin:12px 0;">
      <i class="fas fa-info-circle"></i>
      Mandatory documents are not configured yet. Please contact the Chief Officer/Admin.
    </div>
  <?php elseif ($required_missing > 0): ?>
    <div class="status-badge status-pending" style="border-radius:12px;padding:12px 14px;margin:12px 0;">
      <i class="fas fa-upload"></i>
      <strong>Mandatory Upload Required:</strong>
      You must upload all mandatory documents. Missing/Not approved:
      <strong><?php echo (int)$required_missing; ?></strong>.
      After upload, wait for Admin approval.
    </div>
  <?php else: ?>
    <div class="status-badge status-approved" style="border-radius:12px;padding:12px 14px;margin:12px 0;">
      <i class="fas fa-check-circle"></i>
      All mandatory documents are approved. You can now view child profiles.
    </div>
  <?php endif; ?>

  <!-- ✅ Mandatory Checklist -->
  <div class="top-card">
    <div style="font-weight:900;margin-bottom:8px;"><i class="fas fa-clipboard-list"></i> Mandatory Documents Checklist</div>

    <?php if (empty($required_docs)): ?>
      <div class="status-badge status-pending" style="border-radius:12px;padding:12px 14px;">
        <i class="fas fa-info-circle"></i> No mandatory documents configured yet.
      </div>
    <?php else: ?>
      <?php foreach ($required_docs as $r): ?>
        <?php if ((int)$r['is_required'] !== 1) continue; ?>
        <?php
          $rid = (int)$r['id'];
          $state = $reqStatusMap[$rid]['state'] ?? 'missing';
        ?>
        <div class="req-item">
          <div class="req-left">
            <div class="req-ico">
              <i class="fas <?php echo e(getDocumentIcon($r['category'] ?? 'other')); ?>"></i>
            </div>
            <div>
              <div style="font-weight:900;"><?php echo e($r['requirement_name']); ?></div>
              <div class="small"><?php echo e($r['description'] ?? getCategoryName($r['category'] ?? 'other')); ?></div>
              <div class="small">
                Allowed: <strong><?php echo e($r['allowed_formats'] ?? 'pdf,jpg,jpeg,png'); ?></strong> |
                Max: <strong><?php echo (int)($r['max_size_mb'] ?? 10); ?>MB</strong>
              </div>
              <?php if ($state === 'rejected'): ?>
                <div class="small" style="color:#991b1b;font-weight:900;">
                  Rejected — please upload again.
                </div>
              <?php endif; ?>
            </div>
          </div>

          <span class="status-badge <?php echo e(badgeClassByState($state)); ?>">
            <?php echo e(stateLabel($state)); ?>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ✅ Upload Mandatory Document -->
  <div class="upload-box">
    <div style="font-weight:900;margin-bottom:6px;"><i class="fas fa-cloud-upload-alt"></i> Upload / Re-upload Mandatory Document</div>
    <div class="hint">Select a mandatory item, upload file, then wait for admin approval.</div>

    <form action="upload_document.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

      <div class="form-group">
        <label>Choose Mandatory Document *</label>
        <select name="requirement_id" id="requirementSelect" required>
          <option value="">Select...</option>
          <?php foreach ($required_docs as $r): ?>
            <?php
              if ((int)$r['is_active'] !== 1) continue;
              if ((int)$r['is_required'] !== 1) continue;

              $rid = (int)$r['id'];
              $state = $reqStatusMap[$rid]['state'] ?? 'missing';

              // disable only if approved
              $disabled = ($state === 'approved') ? 'disabled' : '';
              $allowed = strtolower((string)($r['allowed_formats'] ?? 'pdf,jpg,jpeg,png'));
              $maxmb = (int)($r['max_size_mb'] ?? 10);
            ?>
            <option
              value="<?php echo (int)$rid; ?>"
              <?php echo $disabled; ?>
              data-allowed="<?php echo e($allowed); ?>"
              data-maxmb="<?php echo (int)$maxmb; ?>"
            >
              <?php echo e($r['requirement_name']); ?> - <?php echo strtoupper($state); ?><?php echo ($state === 'approved') ? ' ✓' : ''; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="uploadHint">Select a requirement to see allowed formats and max size.</div>
      </div>

      <div class="form-group">
        <label>Choose File *</label>
        <input type="file" name="file" id="fileInput" required accept=".pdf,.jpg,.jpeg,.png">
      </div>

      <div class="form-group">
        <label>Description (optional)</label>
        <textarea name="description" placeholder="Any note for admin..."></textarea>
      </div>

      <button type="submit" class="btn btn-success">
        <i class="fas fa-upload"></i> Upload
      </button>
    </form>
  </div>

  <!-- ✅ Uploaded Documents Table -->
  <div class="top-card">
    <div class="top-row">
      <div style="font-weight:900;"><i class="fas fa-folder-open"></i> Your Uploaded Documents</div>
      <a class="btn-link" style="border:1px solid #e5e7eb;background:#fff;color:#111827;" href="documents.php">
        <i class="fas fa-sync-alt"></i> Refresh
      </a>
    </div>

    <div style="margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th>Document</th>
            <th>Mandatory Item</th>
            <th>Category</th>
            <th>Upload Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
        <?php if (empty($documents_data)): ?>
          <tr>
            <td colspan="6" style="padding:18px;color:#6b7280;">No documents uploaded yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($documents_data as $doc): ?>
            <?php $cat = (string)($doc['category'] ?? 'other'); ?>
            <tr>
              <td>
                <div style="display:flex;gap:10px;align-items:flex-start;">
                  <div style="width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;
                              background:<?php echo e(getDocumentColor($cat)); ?>20;color:<?php echo e(getDocumentColor($cat)); ?>;">
                    <i class="fas <?php echo e(getDocumentIcon($cat)); ?>"></i>
                  </div>
                  <div>
                    <div style="font-weight:900;"><?php echo e($doc['original_name'] ?? 'Document'); ?></div>
                    <div style="color:#6b7280;font-size:13px;">
                      <?php echo e($doc['file_name'] ?? ''); ?> • <?php echo e(formatFileSize($doc['file_size'] ?? 0)); ?>
                    </div>
                  </div>
                </div>
              </td>

              <td><?php echo e($doc['requirement_name'] ?? '—'); ?></td>
              <td><?php echo e(getCategoryName($cat)); ?></td>
              <td><?php echo e($doc['upload_date'] ?? ''); ?></td>

              <td>
                <span class="status-badge status-<?php echo e($doc['status'] ?? 'pending'); ?>">
                  <?php echo e(getStatusText($doc['status'] ?? 'pending')); ?>
                </span>
              </td>

              <td class="actions">
                <a href="download_document.php?id=<?php echo (int)$doc['id']; ?>" title="Download">
                  <i class="fas fa-download"></i>
                </a>

                <?php if (($doc['status'] ?? '') !== 'approved'): ?>
                  <a class="danger"
                     href="delete_document.php?id=<?php echo (int)$doc['id']; ?>"
                     onclick="return confirm('Delete this document?');"
                     title="Delete">
                    <i class="fas fa-trash"></i>
                  </a>
                <?php else: ?>
                  <a class="lock" href="#" onclick="return false;" title="Approved documents cannot be deleted">
                    <i class="fas fa-lock"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<script>
  // Dynamic accept based on allowed formats
  const sel = document.getElementById('requirementSelect');
  const fileInput = document.getElementById('fileInput');
  const hint = document.getElementById('uploadHint');

  function normalizeAccept(allowedStr){
    if(!allowedStr) return '.pdf,.jpg,.jpeg,.png';
    let arr = allowedStr.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
    if(arr.includes('jpg') && !arr.includes('jpeg')) arr.push('jpeg');
    if(!arr.length) return '.pdf,.jpg,.jpeg,.png';
    return arr.map(x => x.startsWith('.') ? x : '.'+x).join(',');
  }

  function updateHint(){
    const opt = sel?.options[sel.selectedIndex];
    if(!opt || !opt.dataset) return;

    const allowed = (opt.dataset.allowed || '').toLowerCase();
    const maxmb = opt.dataset.maxmb || '10';

    if(sel.value){
      hint.innerHTML = `Allowed formats: <strong>${allowed || 'pdf,jpg,jpeg,png'}</strong> | Max size: <strong>${maxmb}MB</strong>`;
      fileInput.accept = normalizeAccept(allowed);
    }else{
      hint.textContent = 'Select a requirement to see allowed formats and max size.';
      fileInput.accept = '.pdf,.jpg,.jpeg,.png';
    }
  }

  sel?.addEventListener('change', updateHint);
  updateHint();
</script>

</body>
</html>
