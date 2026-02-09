<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

session_start();

// Client login check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root';
$password = '';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}

$user_name = 'User';
$user_reg_id = 'Not Set';

$appointments_data = [];
$upcoming_count = 0;
$scheduled_count = 0;
$completed_count = 0;
$cancelled_count = 0;

$flash = '';
$flash_type = 'info';

$has_voted = false;
$voted_child_id = null;

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Fetch client header info
    $stmt = $pdo->prepare("
        SELECT u.email, u.registration_id, a.partner1_name, a.partner2_name
        FROM users u
        LEFT JOIN applications a ON a.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }

    if (!empty($user['partner1_name']) && !empty($user['partner2_name'])) {
        $user_name = $user['partner1_name'] . ' & ' . $user['partner2_name'];
    } elseif (!empty($user['partner1_name'])) {
        $user_name = $user['partner1_name'];
    } else {
        $user_name = $user['email'];
    }

    $user_name = e($user_name);
    $user_reg_id = e($user['registration_id'] ?? 'Not Set');

    /**
     * ✅ 1) Check if user voted for ONE child
     * CHANGE THIS QUERY if your voting table name is different.
     * Expected: votes(user_id, child_id)
     */
    $v = $pdo->prepare("SELECT child_id FROM votes WHERE user_id = ? LIMIT 1");
    $v->execute([$user_id]);
    $voteRow = $v->fetch();

    if ($voteRow && !empty($voteRow['child_id'])) {
        $has_voted = true;
        $voted_child_id = (int)$voteRow['child_id'];
    }

    // ✅ If NOT voted, do NOT show appointments or allow actions
    if (!$has_voted) {
        // Still show page but with message (UI below uses this)
        $appointments_data = [];
    } else {

        // Handle client actions (confirm / cancel / reschedule request / type change request)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                $flash = "Invalid request (CSRF).";
                $flash_type = "error";
            } else {

                $action = $_POST['action'] ?? '';
                $appointment_id = (int)($_POST['appointment_id'] ?? 0);

                // Ensure appointment belongs to this user
                $own = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ? LIMIT 1");
                $own->execute([$appointment_id, $user_id]);
                $appt = $own->fetch();

                if (!$appt) {
                    $flash = "Appointment not found.";
                    $flash_type = "error";
                } else {

                    // Allowed to act only if scheduled/upcoming
                    $canAct = in_array(($appt['status'] ?? ''), ['scheduled', 'upcoming'], true);

                    if ($action === 'confirm') {

                        if (!$canAct) {
                            $flash = "You can only confirm scheduled/upcoming appointments.";
                            $flash_type = "error";
                        } else {
                            $upd = $pdo->prepare("UPDATE appointments SET confirmed = 1 WHERE id = ? AND user_id = ?");
                            $upd->execute([$appointment_id, $user_id]);
                            $flash = "Appointment confirmed successfully.";
                            $flash_type = "success";
                        }

                    } elseif ($action === 'cancel') {

                        if (!$canAct) {
                            $flash = "You can only cancel scheduled/upcoming appointments.";
                            $flash_type = "error";
                        } else {

                            $reason = trim($_POST['cancellation_reason'] ?? '');
                            $notes  = trim($_POST['cancellation_notes'] ?? '');

                            if ($reason === '') {
                                $flash = "Please select a cancellation reason.";
                                $flash_type = "error";
                            } else {
                                $upd = $pdo->prepare("
                                    UPDATE appointments
                                    SET status = 'cancelled',
                                        cancellation_reason = :reason,
                                        cancellation_notes = :notes,
                                        cancelled_date = NOW()
                                    WHERE id = :id AND user_id = :uid
                                ");
                                $upd->execute([
                                    ':reason' => $reason,
                                    ':notes'  => ($notes === '' ? null : $notes),
                                    ':id'     => $appointment_id,
                                    ':uid'    => $user_id
                                ]);
                                $flash = "Appointment cancelled.";
                                $flash_type = "success";
                            }
                        }

                    } elseif ($action === 'request_reschedule') {

                        if (!$canAct) {
                            $flash = "You can only request reschedule for scheduled/upcoming appointments.";
                            $flash_type = "error";
                        } else {

                            $new_date = trim($_POST['new_date'] ?? '');
                            $new_time = trim($_POST['new_time'] ?? '');
                            $msg = trim($_POST['message'] ?? '');

                            if ($new_date === '' || $new_time === '') {
                                $flash = "Please provide preferred new date and time.";
                                $flash_type = "error";
                            } else {
                                $old_notes = (string)($appt['appointment_notes'] ?? '');
                                $append = "\n\n[RESCHEDULE REQUEST by client on " . date('Y-m-d H:i') . "] Preferred: {$new_date} {$new_time}. Message: {$msg}";
                                $upd = $pdo->prepare("UPDATE appointments SET appointment_notes = :notes WHERE id = :id AND user_id = :uid");
                                $upd->execute([
                                    ':notes' => trim($old_notes . $append),
                                    ':id' => $appointment_id,
                                    ':uid' => $user_id
                                ]);
                                $flash = "Reschedule request sent. Admin/Chief will update your appointment.";
                                $flash_type = "success";
                            }
                        }

                    } elseif ($action === 'request_type_change') {

                        // ✅ NEW: request meeting type change (online <-> face-to-face)
                        if (!$canAct) {
                            $flash = "You can only request changes for scheduled/upcoming appointments.";
                            $flash_type = "error";
                        } else {

                            $new_type = trim($_POST['new_type'] ?? '');
                            $msg = trim($_POST['message'] ?? '');

                            $allowed_types = ['online', 'face-to-face'];

                            if (!in_array($new_type, $allowed_types, true)) {
                                $flash = "Invalid meeting type.";
                                $flash_type = "error";
                            } else {
                                $old_notes = (string)($appt['appointment_notes'] ?? '');
                                $append = "\n\n[TYPE CHANGE REQUEST by client on " . date('Y-m-d H:i') . "] Requested type: {$new_type}. Message: {$msg}";
                                $upd = $pdo->prepare("UPDATE appointments SET appointment_notes = :notes WHERE id = :id AND user_id = :uid");
                                $upd->execute([
                                    ':notes' => trim($old_notes . $append),
                                    ':id' => $appointment_id,
                                    ':uid' => $user_id
                                ]);

                                $flash = "Meeting type change request sent. Admin/Chief will update it.";
                                $flash_type = "success";
                            }
                        }

                    } else {
                        $flash = "Unknown action.";
                        $flash_type = "error";
                    }
                }
            }
        }

        // Fetch this user's appointments (only if voted)
        $appointments_stmt = $pdo->prepare("
            SELECT *
            FROM appointments
            WHERE user_id = ?
            ORDER BY appointment_date DESC, appointment_time DESC
        ");
        $appointments_stmt->execute([$user_id]);
        $appointments_data = $appointments_stmt->fetchAll();

        foreach ($appointments_data as $a) {
            switch ($a['status']) {
                case 'upcoming': $upcoming_count++; break;
                case 'scheduled': $scheduled_count++; break;
                case 'completed': $completed_count++; break;
                case 'cancelled': $cancelled_count++; break;
            }
        }
    }

} catch (Exception $e) {
    error_log("client appointments.php error: " . $e->getMessage());
    $appointments_data = [];
    $flash = "A system error occurred. Please try again.";
    $flash_type = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Appointments | Family Bridge Portal</title>
  <link rel="stylesheet" href="style/appointments.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:Segoe UI,Arial;}
    body{background:#f6f7fb;}

    .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:14px 0;}
    .card{background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:14px;}
    .stat{display:flex;align-items:center;gap:10px;}
    .stat i{font-size:20px;}
    .tabs{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;}
    .tabbtn{border:1px solid #e6e6e6;background:#fff;padding:10px 12px;border-radius:10px;cursor:pointer;}
    .tabbtn.active{background:#111827;color:#fff;border-color:#111827;}
    .list{display:grid;gap:12px;}
    .appt{background:#fff;border:1px solid #e6e6e6;border-radius:12px;padding:14px;}
    .appt-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;}
    .badge{padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;text-transform:capitalize;}
    .b-upcoming{background:#dcfce7;color:#166534;}
    .b-scheduled{background:#e0f2fe;color:#075985;}
    .b-completed{background:#ede9fe;color:#5b21b6;}
    .b-cancelled{background:#fee2e2;color:#991b1b;}
    .meta{color:#6b7280;font-size:13px;margin-top:6px;}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
    .btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;}
    .btn-primary{background:#2563eb;color:#fff;}
    .btn-warn{background:#f59e0b;color:#111827;}
    .btn-danger{background:#ef4444;color:#fff;}
    .btn-outline{background:#fff;border:1px solid #e6e6e6;}
    .msg{margin:14px 0;padding:12px;border-radius:10px;border:1px solid #e6e6e6;background:#fff;}
    .msg.success{background:#dcfce7;border-color:#bbf7d0;color:#166534;}
    .msg.error{background:#fee2e2;border-color:#fecaca;color:#991b1b;}
    .msg.info{background:#e0f2fe;border-color:#bae6fd;color:#075985;}
    .empty{padding:30px;text-align:center;color:#6b7280;}
    .modal{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:14px;}
    .modal.active{display:flex;}
    .modal-box{background:#fff;border-radius:14px;max-width:520px;width:100%;padding:16px;border:1px solid #e6e6e6;}
    .modal-head{display:flex;justify-content:space-between;align-items:center;}
    .close{background:none;border:0;font-size:22px;cursor:pointer;}
    label{font-weight:600;display:block;margin-top:10px;}
    input,select,textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:10px;margin-top:6px;}
    @media (max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<div class="container">

  

  <?php if ($flash): ?>
    <div class="msg <?php echo e($flash_type); ?>"><?php echo e($flash); ?></div>
  <?php endif; ?>

  <?php if (!$has_voted): ?>
    <div class="msg info">
      <i class="fas fa-info-circle"></i>
      You can view appointments only after voting for one child. Please go to <b>Available Children</b> and vote first.
    </div>

    <div class="card">
      <div class="empty" style="display:block;">
        <i class="fas fa-calendar-times" style="font-size:42px;margin-bottom:10px;"></i>
        <div style="font-weight:800;">No appointments available</div>
        <div class="meta">Appointments will appear after you vote for one child and the admin schedules a meeting.</div>
      </div>
    </div>

  <?php else: ?>

    <div class="stats">
      <div class="card"><div class="stat"><i class="fas fa-calendar-check"></i><div><div style="font-weight:800;font-size:20px;"><?php echo (int)$upcoming_count; ?></div><div class="meta">Upcoming</div></div></div></div>
      <div class="card"><div class="stat"><i class="fas fa-clock"></i><div><div style="font-weight:800;font-size:20px;"><?php echo (int)$scheduled_count; ?></div><div class="meta">Scheduled</div></div></div></div>
      <div class="card"><div class="stat"><i class="fas fa-check-circle"></i><div><div style="font-weight:800;font-size:20px;"><?php echo (int)$completed_count; ?></div><div class="meta">Completed</div></div></div></div>
      <div class="card"><div class="stat"><i class="fas fa-times-circle"></i><div><div style="font-weight:800;font-size:20px;"><?php echo (int)$cancelled_count; ?></div><div class="meta">Cancelled</div></div></div></div>
    </div>

    <div class="tabs">
      <button class="tabbtn active" data-tab="all">All</button>
      <button class="tabbtn" data-tab="upcoming">Upcoming</button>
      <button class="tabbtn" data-tab="scheduled">Scheduled</button>
      <button class="tabbtn" data-tab="completed">Completed</button>
      <button class="tabbtn" data-tab="cancelled">Cancelled</button>
    </div>

    <div class="card">
      <div class="list" id="list"></div>
      <div class="empty" id="empty" style="display:none;">
        <i class="fas fa-calendar-times" style="font-size:42px;margin-bottom:10px;"></i>
        <div style="font-weight:800;">No appointments</div>
        <div class="meta">Admin/Chief Officer will schedule appointments for you.</div>
      </div>
    </div>

  <?php endif; ?>

</div>

<!-- Cancel Modal -->
<div class="modal" id="cancelModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Cancel Appointment</h3>
      <button class="close" onclick="closeModal('cancelModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="appointment_id" id="cancel_appt_id">
      <label>Reason *</label>
      <select name="cancellation_reason" required>
        <option value="">Select reason</option>
        <option value="schedule-conflict">Schedule Conflict</option>
        <option value="emergency">Emergency</option>
        <option value="health-issue">Health Issue</option>
        <option value="travel">Travel</option>
        <option value="other">Other</option>
      </select>
      <label>Notes (optional)</label>
      <textarea name="cancellation_notes" rows="3"></textarea>
      <div class="actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeModal('cancelModal')">Back</button>
        <button type="submit" class="btn btn-danger">Confirm Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Reschedule Request Modal -->
<div class="modal" id="resModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Request Reschedule</h3>
      <button class="close" onclick="closeModal('resModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
      <input type="hidden" name="action" value="request_reschedule">
      <input type="hidden" name="appointment_id" id="res_appt_id">
      <label>Preferred New Date *</label>
      <input type="date" name="new_date" required>
      <label>Preferred New Time *</label>
      <input type="time" name="new_time" required>
      <label>Message (optional)</label>
      <textarea name="message" rows="3" placeholder="Explain why you need reschedule..."></textarea>
      <div class="actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeModal('resModal')">Back</button>
        <button type="submit" class="btn btn-warn">Send Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ✅ NEW: Type Change Request Modal -->
<div class="modal" id="typeModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Request Meeting Type Change</h3>
      <button class="close" onclick="closeModal('typeModal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
      <input type="hidden" name="action" value="request_type_change">
      <input type="hidden" name="appointment_id" id="type_appt_id">

      <label>New Meeting Type *</label>
      <select name="new_type" required>
        <option value="">Choose</option>
        <option value="online">Online Meeting</option>
        <option value="face-to-face">Face-to-Face Meeting</option>
      </select>

      <label>Message (optional)</label>
      <textarea name="message" rows="3" placeholder="Explain why you want to change the meeting type..."></textarea>

      <div class="actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeModal('typeModal')">Back</button>
        <button type="submit" class="btn btn-primary">Send Request</button>
      </div>
    </form>
  </div>
</div>

<script>
  const logoutBtn = document.getElementById('logoutBtn');
  logoutBtn?.addEventListener('click', () => {
    if (confirm('Are you sure you want to logout?')) window.location.href = '../logout.php';
  });

  const hasVoted = <?php echo $has_voted ? 'true' : 'false'; ?>;
  const data = <?php echo json_encode($appointments_data, JSON_UNESCAPED_UNICODE); ?>;

  function badgeClass(status){
    if(status==='upcoming') return 'b-upcoming';
    if(status==='scheduled') return 'b-scheduled';
    if(status==='completed') return 'b-completed';
    if(status==='cancelled') return 'b-cancelled';
    return 'b-scheduled';
  }

  function render(filter){
    if(!hasVoted) return;

    const list = document.getElementById('list');
    const empty = document.getElementById('empty');

    const items = (filter==='all') ? data : data.filter(a => a.status === filter);

    list.innerHTML = '';
    if(!items.length){
      empty.style.display = 'block';
      return;
    }
    empty.style.display = 'none';

    items.forEach(a => {
      const canAct = (a.status === 'scheduled' || a.status === 'upcoming');
      const confirmed = (parseInt(a.confirmed || 0) === 1);

      const meetingType = a.appointment_type || 'online';

      const div = document.createElement('div');
      div.className = 'appt';
      div.innerHTML = `
        <div class="appt-head">
          <div>
            <div style="font-weight:900;font-size:16px;">
              ${escapeHtml(a.title || a.appointment_type || 'Appointment')}
            </div>
            <div class="meta">
              <i class="fas fa-calendar"></i> ${escapeHtml(formatDate(a.appointment_date))} &nbsp; 
              <i class="fas fa-clock"></i> ${escapeHtml(formatTime(a.appointment_time))} &nbsp; 
              <i class="fas fa-hourglass-half"></i> ${escapeHtml(a.duration || '1 hour')}
            </div>

            <div class="meta">
              <i class="fas fa-video"></i> Meeting Type: <b>${escapeHtml(meetingType)}</b>
            </div>

            <div class="meta">
              <i class="fas fa-user-tie"></i> Caseworker: ${escapeHtml(a.caseworker || 'Not assigned')} &nbsp; • &nbsp;
              <i class="fas fa-location-dot"></i> ${escapeHtml(a.meeting_location || '')}
            </div>

            ${a.address ? `<div class="meta"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(a.address)}</div>` : ``}
            ${a.appointment_notes ? `<div class="meta"><i class="fas fa-note-sticky"></i> ${escapeHtml(a.appointment_notes)}</div>` : ``}

            <div class="meta">
              Confirmed: ${confirmed ? '<b style="color:#166534;">Yes</b>' : '<b style="color:#b45309;">No</b>'}
            </div>
          </div>

          <div class="badge ${badgeClass(a.status)}">${escapeHtml(a.status)}</div>
        </div>

        <div class="actions">
          ${canAct && !confirmed ? `
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
              <input type="hidden" name="action" value="confirm">
              <input type="hidden" name="appointment_id" value="${a.id}">
              <button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Confirm</button>
            </form>
          ` : ''}

          ${canAct ? `
            <button class="btn btn-primary" type="button" onclick="openTypeChange(${a.id})">
              <i class="fas fa-video"></i> Change Type
            </button>

            <button class="btn btn-warn" type="button" onclick="openReschedule(${a.id})">
              <i class="fas fa-calendar-alt"></i> Request Reschedule
            </button>

            <button class="btn btn-danger" type="button" onclick="openCancel(${a.id})">
              <i class="fas fa-times"></i> Cancel
            </button>
          ` : `
            <button class="btn btn-outline" type="button" disabled>No actions</button>
          `}
        </div>
      `;
      list.appendChild(div);
    });
  }

  function openCancel(id){
    document.getElementById('cancel_appt_id').value = id;
    openModal('cancelModal');
  }
  function openReschedule(id){
    document.getElementById('res_appt_id').value = id;
    openModal('resModal');
  }
  function openTypeChange(id){
    document.getElementById('type_appt_id').value = id;
    openModal('typeModal');
  }

  function openModal(id){ document.getElementById(id).classList.add('active'); }
  function closeModal(id){ document.getElementById(id).classList.remove('active'); }

  // Tabs
  document.querySelectorAll('.tabbtn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tabbtn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      render(btn.dataset.tab);
    });
  });

  // Initial
  render('all');

  // Helpers
  function formatDate(d){
    if(!d) return 'N/A';
    const dt = new Date(d);
    if(String(dt) === 'Invalid Date') return d;
    return dt.toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'});
  }
  function formatTime(t){
    if(!t) return '';
    const parts = String(t).split(':');
    const h = parseInt(parts[0] || '0', 10);
    const m = parts[1] || '00';
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hh = (h % 12) || 12;
    return `${hh}:${m} ${ampm}`;
  }
  function escapeHtml(str){
    return String(str ?? '').replace(/[&<>"']/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[s]));
  }
</script>
</body>
</html>
