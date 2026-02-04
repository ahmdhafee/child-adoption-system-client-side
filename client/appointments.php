<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}


$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root'; 
$password = ''; 


$user_name = 'User';
$user_reg_id = 'Not Set';
$appointments_data = [];
$upcoming_count = 0;
$scheduled_count = 0;
$completed_count = 0;
$cancelled_count = 0;


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.registration_id, 
                                  a.partner1_name, a.partner2_name
                           FROM users u 
                           LEFT JOIN applications a ON u.id = a.user_id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        
        if (!empty($user['partner1_name']) && !empty($user['partner2_name'])) {
            $user_name = htmlspecialchars($user['partner1_name'] . ' & ' . $user['partner2_name']);
        } elseif (!empty($user['partner1_name'])) {
            $user_name = htmlspecialchars($user['partner1_name']);
        } else {
            $user_name = htmlspecialchars($user['email']);
        }
        
        $user_reg_id = htmlspecialchars($user['registration_id'] ?? 'Not Set');
        
        
        $appointments_stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC");
        $appointments_stmt->execute([$user_id]);
        $appointments_data = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        foreach ($appointments_data as $appointment) {
            switch ($appointment['status']) {
                case 'upcoming':
                    $upcoming_count++;
                    break;
                case 'scheduled':
                    $scheduled_count++;
                    break;
                case 'completed':
                    $completed_count++;
                    break;
                case 'cancelled':
                    $cancelled_count++;
                    break;
            }
        }
        
    } else {
        
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Appointments page database error: " . $e->getMessage());
    $appointments_data = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/appointments.css">
    <link rel="shortcut icon" href="../favlogo.png" type="logo">
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
      
    </style>
</head>
<body>

<?php include 'includes/header.php' ?>

<?php include 'includes/sidebar.php'?>

     
        <main class="main-content">
           
            <div class="page-header">
                <h1>Appointments</h1>
                <p>Manage your adoption-related meetings and consultations</p>
            </div>

            
            <div class="appointment-stats">
                <div class="stat-card">
                    <div class="stat-icon upcoming">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="upcomingCount"><?php echo $upcoming_count; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon scheduled">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="scheduledCount"><?php echo $scheduled_count; ?></h3>
                        <p>Scheduled</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="completedCount"><?php echo $completed_count; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon cancelled">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="cancelledCount"><?php echo $cancelled_count; ?></h3>
                        <p>Cancelled</p>
                    </div>
                </div>
            </div>

            
            <div class="schedule-card">
                <div class="schedule-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <div class="schedule-content">
                    <h3>Schedule a New Appointment</h3>
                    <p>Book meetings with caseworkers, home visits, or consultations to move forward with your adoption process.</p>
                    <button class="btn btn-primary" id="scheduleAppointmentBtn">
                        <i class="fas fa-plus-circle"></i> Schedule New Appointment
                    </button>
                </div>
            </div>

           
            <div class="appointment-tabs">
                <button class="appointment-tab active" data-tab="upcoming">Upcoming</button>
                <button class="appointment-tab" data-tab="scheduled">Scheduled</button>
                <button class="appointment-tab" data-tab="completed">Completed</button>
                <button class="appointment-tab" data-tab="cancelled">Cancelled</button>
                <button class="appointment-tab" data-tab="calendar">Calendar View</button>
            </div>

           
            <div class="tab-content active" id="upcomingTab">
                <div class="appointments-list" id="upcomingAppointments">
                    
                </div>
                
                <div class="no-appointments" id="noUpcoming" style="<?php echo $upcoming_count == 0 ? '' : 'display: none;'; ?>">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Upcoming Appointments</h3>
                    <p>You don't have any upcoming appointments scheduled.</p>
                    <button class="btn btn-primary" id="scheduleFromUpcomingBtn">
                        <i class="fas fa-calendar-plus"></i> Schedule Appointment
                    </button>
                </div>
            </div>

           
            <div class="tab-content" id="scheduledTab">
                <div class="appointments-list" id="scheduledAppointments">
                    
                </div>
                
                <div class="no-appointments" id="noScheduled" style="<?php echo $scheduled_count == 0 ? '' : 'display: none;'; ?>">
                    <i class="fas fa-clock"></i>
                    <h3>No Scheduled Appointments</h3>
                    <p>You don't have any appointments waiting to be confirmed.</p>
                    <button class="btn btn-primary" id="scheduleFromScheduledBtn">
                        <i class="fas fa-calendar-plus"></i> Schedule Appointment
                    </button>
                </div>
            </div>

           
            <div class="tab-content" id="completedTab">
                <div class="appointments-list" id="completedAppointments">
                   
                </div>
                
                <div class="no-appointments" id="noCompleted" style="<?php echo $completed_count == 0 ? '' : 'display: none;'; ?>">
                    <i class="fas fa-history"></i>
                    <h3>No Completed Appointments</h3>
                    <p>You haven't completed any appointments yet.</p>
                </div>
            </div>

            
            <div class="tab-content" id="cancelledTab">
                <div class="appointments-list" id="cancelledAppointments">
                   
                </div>
                
                <div class="no-appointments" id="noCancelled" style="<?php echo $cancelled_count == 0 ? '' : 'display: none;'; ?>">
                    <i class="fas fa-ban"></i>
                    <h3>No Cancelled Appointments</h3>
                    <p>Great! You haven't cancelled any appointments.</p>
                </div>
            </div>

           
            <div class="tab-content" id="calendarTab">
                <div class="calendar-section">
                    <div class="calendar-header">
                        <h3><i class="fas fa-calendar-alt"></i> <span id="currentMonthYear"><?php echo date('F Y'); ?></span></h3>
                        <div class="calendar-nav">
                            <button class="btn btn-sm btn-outline" id="prevMonthBtn">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="btn btn-sm btn-outline" id="todayBtn">Today</button>
                            <button class="btn btn-sm btn-outline" id="nextMonthBtn">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="calendar-grid">
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                        
                 
                        <div id="calendarDays"></div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Tip:</strong> Click on a date with appointments to view details. 
                        Green dots indicate scheduled appointments.
                    </div>
                </div>
            </div>

           
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Important:</strong> Please arrive 10 minutes before your scheduled appointment time. 
                    Late arrivals may result in rescheduling. Cancellations require 24-hour notice.
                </div>
            </div>
        </main>
    </div>

    
    <div class="modal" id="scheduleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Schedule New Appointment</h3>
                <button class="modal-close" data-modal="scheduleModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="appointmentForm" method="POST" action="schedule_appointment.php">
                    <div class="form-group">
                        <label class="form-label required">Appointment Type</label>
                        <select class="form-control" id="appointmentType" name="appointment_type" required>
                            <option value="">Select Type</option>
                            <option value="home-study">Home Study Visit</option>
                            <option value="caseworker">Caseworker Meeting</option>
                            <option value="counseling">Adoption Counseling</option>
                            <option value="document-review">Document Review</option>
                            <option value="child-meeting">Child Introduction</option>
                            <option value="follow-up">Follow-up Meeting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Preferred Date</label>
                            <input type="date" class="form-control" id="appointmentDate" name="appointment_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Preferred Time</label>
                            <select class="form-control" id="appointmentTime" name="appointment_time" required>
                                <option value="">Select Time</option>
                                <option value="09:00">9:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="13:00">1:00 PM</option>
                                <option value="14:00">2:00 PM</option>
                                <option value="15:00">3:00 PM</option>
                                <option value="16:00">4:00 PM</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Preferred Caseworker</label>
                        <select class="form-control" id="preferredCaseworker" name="preferred_caseworker">
                            <option value="">Any Available Caseworker</option>
                            <option value="sarah-johnson">Officer Sarah Johnson</option>
                            <option value="david-wilson">Officer David Wilson</option>
                            <option value="emma-chen">Officer Emma Chen</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Meeting Location</label>
                        <select class="form-control" id="meetingLocation" name="meeting_location" required>
                            <option value="">Select Location</option>
                            <option value="office">Family Bridge Office</option>
                            <option value="home">Your Home</option>
                            <option value="video">Video Conference</option>
                            <option value="phone">Phone Call</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="appointmentNotes" name="appointment_notes" rows="4" placeholder="Please provide any additional information or special requirements..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="scheduleModal">Cancel</button>
                <button class="btn btn-success" id="submitAppointmentBtn">
                    <i class="fas fa-calendar-check"></i> Schedule Appointment
                </button>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div class="modal" id="appointmentDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detailsModalTitle">Appointment Details</h3>
                <button class="modal-close" data-modal="appointmentDetailsModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="appointmentDetailsContent">
                    <!-- Appointment details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="appointmentDetailsModal">Close</button>
                <button class="btn btn-primary" id="rescheduleBtn">
                    <i class="fas fa-calendar-alt"></i> Reschedule
                </button>
                <button class="btn btn-danger" id="cancelAppointmentBtn">
                    <i class="fas fa-times-circle"></i> Cancel Appointment
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Appointment</h3>
                <button class="modal-close" data-modal="cancelModal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--warning); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--dark); margin-bottom: 15px;">Are you sure you want to cancel this appointment?</h3>
                    <p style="color: var(--gray); margin-bottom: 20px;">
                        Appointment: <strong id="cancelAppointmentName">[Appointment Name]</strong><br>
                        Date: <strong id="cancelAppointmentDate">[Date]</strong> at <strong id="cancelAppointmentTime">[Time]</strong>
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Cancellation Policy:</strong> Cancellations require 24-hour notice. 
                            Late cancellations may affect your application timeline.
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <label class="form-label required">Cancellation Reason</label>
                        <select class="form-control" id="cancellationReason" required>
                            <option value="">Select Reason</option>
                            <option value="schedule-conflict">Schedule Conflict</option>
                            <option value="emergency">Emergency</option>
                            <option value="health-issue">Health Issue</option>
                            <option value="travel">Travel</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="cancellationNotes" rows="3" placeholder="Please provide additional details..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="cancelModal">Go Back</button>
                <button class="btn btn-danger" id="confirmCancelBtn">
                    <i class="fas fa-times-circle"></i> Confirm Cancellation
                </button>
            </div>
        </div>
    </div>

   

    <script>
        // Appointments JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize
            initAppointmentsPage();
            
            // Setup event listeners
            setupEventListeners();
            
            // Load appointments data from PHP
            const appointmentsData = <?php echo json_encode($appointments_data); ?>;
            const userId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
            
            // Global variables
            let appointments = appointmentsData;
            let currentAppointmentId = null;
            let currentMonth = new Date().getMonth();
            let currentYear = new Date().getFullYear();
            
            // Initialize appointments page
            function initAppointmentsPage() {
                console.log('Appointments Page Initialized');
                
                // Mobile Menu Toggle
                const menuToggle = document.getElementById('menuToggle');
                const sidebar = document.getElementById('sidebar');
                
                if (menuToggle && sidebar) {
                    menuToggle.addEventListener('click', function() {
                        sidebar.classList.toggle('active');
                    });
                    
                    // Close sidebar when clicking outside on mobile
                    document.addEventListener('click', function(event) {
                        if (window.innerWidth <= 992) {
                            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {
                                sidebar.classList.remove('active');
                            }
                        }
                    });
                }
                
                // Logout Button
                const logoutBtn = document.getElementById('logoutBtn');
                if (logoutBtn) {
                    logoutBtn.addEventListener('click', function() {
                        if (confirm('Are you sure you want to logout?')) {
                            window.location.href = '../logout.php';
                        }
                    });
                }
                
                // Set minimum date to tomorrow
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const formattedDate = tomorrow.toISOString().split('T')[0];
                document.getElementById('appointmentDate').min = formattedDate;
                
                // Render appointments
                renderAppointments();
                
                // Generate calendar
                generateCalendar();
            }
            
            // Setup event listeners
            function setupEventListeners() {
                // Tab switching
                document.querySelectorAll('.appointment-tab').forEach(tab => {
                    tab.addEventListener('click', function() {
                        const tabId = this.getAttribute('data-tab');
                        
                        // Update active tab
                        document.querySelectorAll('.appointment-tab').forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        
                        // Show corresponding tab content
                        document.querySelectorAll('.tab-content').forEach(content => {
                            content.classList.remove('active');
                        });
                        
                        document.getElementById(tabId + 'Tab').classList.add('active');
                        
                        // Generate calendar if calendar tab is selected
                        if (tabId === 'calendar') {
                            generateCalendar();
                        }
                    });
                });
                
                // Schedule appointment buttons
                document.querySelectorAll('#scheduleAppointmentBtn, #scheduleFromUpcomingBtn, #scheduleFromScheduledBtn').forEach(btn => {
                    btn.addEventListener('click', openScheduleModal);
                });
                
                // Modal close buttons
                document.querySelectorAll('.modal-close, .btn-secondary[data-modal]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const modalId = this.getAttribute('data-modal') || this.closest('.modal').id;
                        closeModal(modalId);
                    });
                });
                
                // Close modal on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        document.querySelectorAll('.modal').forEach(modal => {
                            modal.classList.remove('active');
                        });
                    }
                });
                
                // Submit appointment form
                const submitAppointmentBtn = document.getElementById('submitAppointmentBtn');
                const appointmentForm = document.getElementById('appointmentForm');
                
                if (submitAppointmentBtn && appointmentForm) {
                    submitAppointmentBtn.addEventListener('click', function() {
                        if (validateAppointmentForm()) {
                            scheduleAppointment();
                        }
                    });
                }
                
                // Calendar navigation
                document.getElementById('prevMonthBtn')?.addEventListener('click', previousMonth);
                document.getElementById('nextMonthBtn')?.addEventListener('click', nextMonth);
                document.getElementById('todayBtn')?.addEventListener('click', goToToday);
                
                // Cancel appointment
                const confirmCancelBtn = document.getElementById('confirmCancelBtn');
                if (confirmCancelBtn) {
                    confirmCancelBtn.addEventListener('click', confirmCancelAppointment);
                }
                
                // Reschedule appointment button
                const rescheduleBtn = document.getElementById('rescheduleBtn');
                if (rescheduleBtn) {
                    rescheduleBtn.addEventListener('click', function() {
                        if (currentAppointmentId) {
                            rescheduleAppointment(currentAppointmentId);
                        }
                    });
                }
                
                // Cancel appointment button
                const cancelAppointmentBtn = document.getElementById('cancelAppointmentBtn');
                if (cancelAppointmentBtn) {
                    cancelAppointmentBtn.addEventListener('click', function() {
                        if (currentAppointmentId) {
                            openCancelModal(currentAppointmentId);
                        }
                    });
                }
            }
            
            // Render appointments
            function renderAppointments() {
                renderUpcomingAppointments();
                renderScheduledAppointments();
                renderCompletedAppointments();
                renderCancelledAppointments();
            }
            
            // Render upcoming appointments
            function renderUpcomingAppointments() {
                const container = document.getElementById('upcomingAppointments');
                const noResults = document.getElementById('noUpcoming');
                
                if (!container || !noResults) return;
                
                container.innerHTML = '';
                const upcomingAppointments = appointments.filter(a => a.status === 'upcoming');
                
                if (upcomingAppointments.length === 0) {
                    noResults.style.display = 'block';
                    return;
                }
                
                noResults.style.display = 'none';
                
                upcomingAppointments.forEach(appointment => {
                    const card = createAppointmentCard(appointment);
                    container.appendChild(card);
                });
            }
            
            // Render scheduled appointments
            function renderScheduledAppointments() {
                const container = document.getElementById('scheduledAppointments');
                const noResults = document.getElementById('noScheduled');
                
                if (!container || !noResults) return;
                
                container.innerHTML = '';
                const scheduledAppointments = appointments.filter(a => a.status === 'scheduled');
                
                if (scheduledAppointments.length === 0) {
                    noResults.style.display = 'block';
                    return;
                }
                
                noResults.style.display = 'none';
                
                scheduledAppointments.forEach(appointment => {
                    const card = createAppointmentCard(appointment);
                    container.appendChild(card);
                });
            }
            
            // Render completed appointments
            function renderCompletedAppointments() {
                const container = document.getElementById('completedAppointments');
                const noResults = document.getElementById('noCompleted');
                
                if (!container || !noResults) return;
                
                container.innerHTML = '';
                const completedAppointments = appointments.filter(a => a.status === 'completed');
                
                if (completedAppointments.length === 0) {
                    noResults.style.display = 'block';
                    return;
                }
                
                noResults.style.display = 'none';
                
                completedAppointments.forEach(appointment => {
                    const card = createAppointmentCard(appointment);
                    container.appendChild(card);
                });
            }
            
            // Render cancelled appointments
            function renderCancelledAppointments() {
                const container = document.getElementById('cancelledAppointments');
                const noResults = document.getElementById('noCancelled');
                
                if (!container || !noResults) return;
                
                container.innerHTML = '';
                const cancelledAppointments = appointments.filter(a => a.status === 'cancelled');
                
                if (cancelledAppointments.length === 0) {
                    noResults.style.display = 'block';
                    return;
                }
                
                noResults.style.display = 'none';
                
                cancelledAppointments.forEach(appointment => {
                    const card = createAppointmentCard(appointment);
                    container.appendChild(card);
                });
            }
            
            // Create appointment card
            function createAppointmentCard(appointment) {
                const card = document.createElement('div');
                card.className = `appointment-card ${appointment.status}`;
                card.dataset.id = appointment.id;
                
                const icon = getAppointmentIcon(appointment.appointment_type || appointment.type);
                const statusText = getStatusText(appointment.status);
                const statusClass = `status-${appointment.status}`;
                
                const formattedDate = formatDate(appointment.appointment_date || appointment.date);
                const formattedTime = formatTime(appointment.appointment_time || appointment.time);
                const scheduledDate = formatDate(appointment.created_at || appointment.scheduledDate);
                
                card.innerHTML = `
                    <div class="appointment-header">
                        <div class="appointment-title">
                            <div class="appointment-icon">
                                <i class="fas ${icon}"></i>
                            </div>
                            <div class="appointment-details">
                                <h3>${appointment.title || getAppointmentTitle(appointment.appointment_type || appointment.type)}</h3>
                                <p>${formattedDate} at ${formattedTime} • ${appointment.duration || '1 hour'}</p>
                            </div>
                        </div>
                        <div class="appointment-status ${statusClass}">${statusText}</div>
                    </div>
                    <div class="appointment-body">
                        <div class="appointment-info-grid">
                            <div class="info-item">
                                <div class="info-label">Caseworker</div>
                                <div class="info-value">${appointment.caseworker || 'To be assigned'}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Location</div>
                                <div class="info-value">${appointment.location || getLocationName(appointment.meeting_location)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Scheduled On</div>
                                <div class="info-value">${scheduledDate}</div>
                            </div>
                            ${appointment.confirmed !== undefined ? `
                                <div class="info-item">
                                    <div class="info-label">Confirmed</div>
                                    <div class="info-value">${appointment.confirmed ? 'Yes' : 'No'}</div>
                                </div>
                            ` : ''}
                        </div>
                        ${appointment.notes || appointment.appointment_notes ? `
                            <div style="background-color: var(--light); padding: 15px; border-radius: var(--border-radius);">
                                <strong style="color: var(--dark);">Notes:</strong>
                                <p style="color: var(--gray); margin-top: 5px; font-size: 0.9rem;">${appointment.notes || appointment.appointment_notes}</p>
                            </div>
                        ` : ''}
                    </div>
                    <div class="appointment-footer">
                        <div class="appointment-notes">
                            ${appointment.address ? `<i class="fas fa-map-marker-alt"></i> ${appointment.address}` : ''}
                        </div>
                        <div class="appointment-actions">
                            <button class="btn btn-sm btn-primary view-appointment-btn" data-id="${appointment.id}">
                                <i class="fas fa-eye"></i> Details
                            </button>
                            ${(appointment.status === 'upcoming' || appointment.status === 'scheduled') && appointment.id ? `
                                <button class="btn btn-sm btn-warning reschedule-appointment-btn" data-id="${appointment.id}">
                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                </button>
                                <button class="btn btn-sm btn-danger cancel-appointment-btn" data-id="${appointment.id}">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </button>
                            ` : ''}
                            ${appointment.status === 'scheduled' && !appointment.confirmed ? `
                                <button class="btn btn-sm btn-success confirm-appointment-btn" data-id="${appointment.id}">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                // Add event listeners
                const viewBtn = card.querySelector('.view-appointment-btn');
                const rescheduleBtn = card.querySelector('.reschedule-appointment-btn');
                const cancelBtn = card.querySelector('.cancel-appointment-btn');
                const confirmBtn = card.querySelector('.confirm-appointment-btn');
                
                if (viewBtn) {
                    viewBtn.addEventListener('click', function() {
                        const appointmentId = parseInt(this.dataset.id);
                        viewAppointmentDetails(appointmentId);
                    });
                }
                
                if (rescheduleBtn) {
                    rescheduleBtn.addEventListener('click', function() {
                        const appointmentId = parseInt(this.dataset.id);
                        rescheduleAppointment(appointmentId);
                    });
                }
                
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        const appointmentId = parseInt(this.dataset.id);
                        openCancelModal(appointmentId);
                    });
                }
                
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', function() {
                        const appointmentId = parseInt(this.dataset.id);
                        confirmAppointment(appointmentId);
                    });
                }
                
                return card;
            }
            
            // View appointment details
            function viewAppointmentDetails(appointmentId) {
                const appointment = appointments.find(a => a.id === appointmentId);
                if (!appointment) return;
                
                currentAppointmentId = appointmentId;
                
                const modalTitle = document.getElementById('detailsModalTitle');
                const modalContent = document.getElementById('appointmentDetailsContent');
                
                if (!modalTitle || !modalContent) return;
                
                modalTitle.textContent = appointment.title || getAppointmentTitle(appointment.appointment_type || appointment.type);
                
                const icon = getAppointmentIcon(appointment.appointment_type || appointment.type);
                const statusText = getStatusText(appointment.status);
                const statusClass = `status-${appointment.status}`;
                const formattedDate = formatDate(appointment.appointment_date || appointment.date);
                const formattedTime = formatTime(appointment.appointment_time || appointment.time);
                const scheduledDate = formatDate(appointment.created_at || appointment.scheduledDate);
                
                modalContent.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 25px;">
                        <div class="appointment-icon" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div>
                            <h3 style="color: var(--dark); margin-bottom: 5px;">${appointment.title || getAppointmentTitle(appointment.appointment_type || appointment.type)}</h3>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span class="appointment-status ${statusClass}">${statusText}</span>
                                <span style="color: var(--gray);">ID: APPT-${appointment.id.toString().padStart(4, '0')}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background-color: var(--light); padding: 20px; border-radius: var(--border-radius); margin-bottom: 25px;">
                        <h4 style="color: var(--primary); margin-bottom: 15px;">Appointment Details</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>Date & Time:</strong>
                                <div>${formattedDate} at ${formattedTime}</div>
                            </div>
                            <div>
                                <strong>Duration:</strong>
                                <div>${appointment.duration || '1 hour'}</div>
                            </div>
                            <div>
                                <strong>Caseworker:</strong>
                                <div>${appointment.caseworker || 'To be assigned'}</div>
                            </div>
                            <div>
                                <strong>Location:</strong>
                                <div>${appointment.location || getLocationName(appointment.meeting_location)}</div>
                            </div>
                            <div>
                                <strong>Scheduled On:</strong>
                                <div>${scheduledDate}</div>
                            </div>
                            <div>
                                <strong>Confirmed:</strong>
                                <div>${appointment.confirmed ? '<span style="color: var(--success);">Yes</span>' : '<span style="color: var(--warning);">Pending</span>'}</div>
                            </div>
                        </div>
                    </div>
                    
                    ${appointment.address ? `
                        <div style="margin-bottom: 20px;">
                            <strong>Address/Location Details:</strong>
                            <div style="color: var(--gray); margin-top: 5px;">${appointment.address}</div>
                        </div>
                    ` : ''}
                    
                    ${appointment.notes || appointment.appointment_notes ? `
                        <div style="margin-bottom: 20px;">
                            <strong>Notes:</strong>
                            <div style="background-color: var(--light-gray); padding: 15px; border-radius: var(--border-radius); margin-top: 5px;">
                                ${appointment.notes || appointment.appointment_notes}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${appointment.completed_date || appointment.completedDate ? `
                        <div style="background-color: rgba(76, 175, 80, 0.1); padding: 15px; border-radius: var(--border-radius); border-left: 4px solid var(--success);">
                            <strong><i class="fas fa-check-circle"></i> Completed:</strong>
                            <div style="color: var(--gray); margin-top: 5px;">This appointment was completed on ${formatDate(appointment.completed_date || appointment.completedDate)}</div>
                        </div>
                    ` : ''}
                    
                    ${appointment.status === 'upcoming' && appointment.reminder_sent === false ? `
                        <div style="background-color: rgba(33, 150, 243, 0.1); padding: 15px; border-radius: var(--border-radius); border-left: 4px solid var(--info); margin-top: 20px;">
                            <strong><i class="fas fa-bell"></i> Reminder:</strong>
                            <div style="color: var(--gray); margin-top: 5px;">A reminder will be sent 24 hours before your appointment.</div>
                        </div>
                    ` : ''}
                `;
                
                // Update buttons visibility
                const rescheduleBtn = document.getElementById('rescheduleBtn');
                const cancelBtn = document.getElementById('cancelAppointmentBtn');
                
                if (rescheduleBtn && cancelBtn) {
                    if (appointment.status === 'upcoming' || appointment.status === 'scheduled') {
                        rescheduleBtn.style.display = 'flex';
                        cancelBtn.style.display = 'flex';
                    } else {
                        rescheduleBtn.style.display = 'none';
                        cancelBtn.style.display = 'none';
                    }
                }
                
                openModal('appointmentDetailsModal');
            }
            
            // Open schedule modal
            function openScheduleModal() {
                // Set default date to tomorrow
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const formattedDate = tomorrow.toISOString().split('T')[0];
                
                document.getElementById('appointmentDate').value = formattedDate;
                document.getElementById('appointmentDate').min = formattedDate;
                document.getElementById('appointmentForm').reset();
                
                openModal('scheduleModal');
            }
            
            // Validate appointment form
            function validateAppointmentForm() {
                const type = document.getElementById('appointmentType').value;
                const date = document.getElementById('appointmentDate').value;
                const time = document.getElementById('appointmentTime').value;
                const location = document.getElementById('meetingLocation').value;
                
                if (!type) {
                    showAlert('Please select an appointment type.', 'error');
                    return false;
                }
                
                if (!date) {
                    showAlert('Please select a date.', 'error');
                    return false;
                }
                
                // Check if date is in the past
                const selectedDate = new Date(date);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate < today) {
                    showAlert('Please select a future date.', 'error');
                    return false;
                }
                
                if (!time) {
                    showAlert('Please select a time.', 'error');
                    return false;
                }
                
                if (!location) {
                    showAlert('Please select a meeting location.', 'error');
                    return false;
                }
                
                return true;
            }
            
            // Schedule appointment
            function scheduleAppointment() {
                const formData = new FormData(document.getElementById('appointmentForm'));
                
                // Show loading
                const submitBtn = document.getElementById('submitAppointmentBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling...';
                submitBtn.disabled = true;
                
                // Send to server
                fetch('schedule_appointment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Add new appointment to array
                        appointments.push(data.appointment);
                        
                        // Show success message
                        showAlert('Appointment scheduled successfully! Our team will confirm within 24 hours.', 'success');
                        
                        // Close modal
                        closeModal('scheduleModal');
                        
                        // Reset form
                        document.getElementById('appointmentForm').reset();
                        
                        // Update UI
                        renderAppointments();
                        updateStatistics();
                        generateCalendar();
                        
                        // Reload page to get updated data from server
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showAlert(data.message || 'Failed to schedule appointment. Please try again.', 'error');
                    }
                    
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Network error. Please try again.', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            }
            
            // Reschedule appointment
            function rescheduleAppointment(appointmentId) {
                const appointment = appointments.find(a => a.id === appointmentId);
                if (!appointment) return;
                
                // Pre-fill form with current appointment details
                document.getElementById('appointmentType').value = appointment.appointment_type || appointment.type;
                document.getElementById('appointmentDate').value = appointment.appointment_date || appointment.date;
                document.getElementById('appointmentTime').value = appointment.appointment_time || appointment.time;
                document.getElementById('meetingLocation').value = appointment.meeting_location || getLocationType(appointment.location);
                document.getElementById('appointmentNotes').value = `Rescheduling of: ${appointment.title}\n\n${appointment.notes || appointment.appointment_notes || ''}`;
                
                // Update modal title
                document.querySelector('#scheduleModal h3').textContent = 'Reschedule Appointment';
                
                // Update submit button
                const submitBtn = document.getElementById('submitAppointmentBtn');
                submitBtn.innerHTML = '<i class="fas fa-calendar-alt"></i> Reschedule Appointment';
                
                // Store appointment ID for reschedule
                submitBtn.dataset.rescheduleId = appointmentId;
                
                openModal('scheduleModal');
            }
            
            // Open cancel modal
            function openCancelModal(appointmentId) {
                const appointment = appointments.find(a => a.id === appointmentId);
                if (!appointment) return;
                
                currentAppointmentId = appointmentId;
                
                document.getElementById('cancelAppointmentName').textContent = appointment.title || getAppointmentTitle(appointment.appointment_type || appointment.type);
                document.getElementById('cancelAppointmentDate').textContent = formatDate(appointment.appointment_date || appointment.date);
                document.getElementById('cancelAppointmentTime').textContent = formatTime(appointment.appointment_time || appointment.time);
                
                openModal('cancelModal');
            }
            
            // Confirm cancel appointment
            function confirmCancelAppointment() {
                const reason = document.getElementById('cancellationReason').value;
                const notes = document.getElementById('cancellationNotes').value;
                
                if (!reason) {
                    showAlert('Please select a cancellation reason.', 'error');
                    return;
                }
                
                const appointment = appointments.find(a => a.id === currentAppointmentId);
                if (!appointment) return;
                
                // Show loading
                const confirmBtn = document.getElementById('confirmCancelBtn');
                const originalText = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
                confirmBtn.disabled = true;
                
                // Send cancellation to server
                fetch('cancel_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=${currentAppointmentId}&reason=${encodeURIComponent(reason)}&notes=${encodeURIComponent(notes)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update appointment status locally
                        appointment.status = 'cancelled';
                        appointment.cancellation_reason = reason;
                        appointment.cancellation_notes = notes;
                        appointment.cancelled_date = new Date().toISOString().split('T')[0];
                        
                        // Show success message
                        showAlert('Appointment cancelled successfully.', 'success');
                        
                        // Close modals
                        closeModal('cancelModal');
                        closeModal('appointmentDetailsModal');
                        
                        // Reset form
                        document.getElementById('cancellationReason').value = '';
                        document.getElementById('cancellationNotes').value = '';
                        
                        // Update UI
                        renderAppointments();
                        updateStatistics();
                        generateCalendar();
                        
                        // Reload page to get updated data from server
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showAlert(data.message || 'Failed to cancel appointment. Please try again.', 'error');
                    }
                    
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Network error. Please try again.', 'error');
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                });
            }
            
            // Confirm appointment
            function confirmAppointment(appointmentId) {
                const appointment = appointments.find(a => a.id === appointmentId);
                if (!appointment) return;
                
                // Show loading
                const confirmBtn = document.querySelector(`[data-id="${appointmentId}"]`);
                if (confirmBtn) {
                    const originalText = confirmBtn.innerHTML;
                    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    confirmBtn.disabled = true;
                }
                
                // Send confirmation to server
                fetch('confirm_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=${appointmentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update appointment locally
                        appointment.confirmed = true;
                        
                        // Show success message
                        showAlert('Appointment confirmed successfully!', 'success');
                        
                        // Update UI
                        renderAppointments();
                        updateStatistics();
                        
                        // If viewing details, update the modal
                        if (currentAppointmentId === appointmentId) {
                            viewAppointmentDetails(appointmentId);
                        }
                    } else {
                        showAlert(data.message || 'Failed to confirm appointment. Please try again.', 'error');
                    }
                    
                    if (confirmBtn) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Network error. Please try again.', 'error');
                    if (confirmBtn) {
                        confirmBtn.innerHTML = originalText;
                        confirmBtn.disabled = false;
                    }
                });
            }
            
            // Generate calendar
            function generateCalendar() {
                const calendarDays = document.getElementById('calendarDays');
                const monthYear = document.getElementById('currentMonthYear');
                
                if (!calendarDays || !monthYear) return;
                
                calendarDays.innerHTML = '';
                
                // Update month header
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                monthYear.textContent = `${monthNames[currentMonth]} ${currentYear}`;
                
                // Get first day of month
                const firstDay = new Date(currentYear, currentMonth, 1);
                const startingDay = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
                
                // Get number of days in month
                const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
                
                // Get today's date
                const today = new Date();
                const isCurrentMonth = today.getMonth() === currentMonth && today.getFullYear() === currentYear;
                
                // Add empty cells for days before first day of month
                for (let i = 0; i < startingDay; i++) {
                    const emptyCell = document.createElement('div');
                    emptyCell.className = 'calendar-day';
                    calendarDays.appendChild(emptyCell);
                }
                
                // Add cells for each day of month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayCell = document.createElement('div');
                    dayCell.className = 'calendar-day';
                    
                    // Check if today
                    if (isCurrentMonth && day === today.getDate()) {
                        dayCell.classList.add('today');
                    }
                    
                    // Check if has appointments
                    const dateStr = `${currentYear}-${(currentMonth + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                    const hasAppointments = appointments.some(a => 
                        (a.appointment_date || a.date) === dateStr && 
                        (a.status === 'upcoming' || a.status === 'scheduled')
                    );
                    
                    if (hasAppointments) {
                        dayCell.classList.add('has-appointment');
                    }
                    
                    dayCell.innerHTML = `
                        <div class="calendar-day-number">${day}</div>
                        ${hasAppointments ? '<div class="calendar-appointment-indicator"></div>' : ''}
                    `;
                    
                    // Add click event
                    if (hasAppointments) {
                        dayCell.addEventListener('click', function() {
                            showDayAppointments(dateStr);
                        });
                    }
                    
                    calendarDays.appendChild(dayCell);
                }
            }
            
            // Show day appointments
            function showDayAppointments(dateStr) {
                const dayAppointments = appointments.filter(a => 
                    (a.appointment_date || a.date) === dateStr && 
                    (a.status === 'upcoming' || a.status === 'scheduled')
                );
                
                if (dayAppointments.length === 0) return;
                
                let message = `Appointments on ${formatDate(dateStr)}:\n\n`;
                dayAppointments.forEach((appt, index) => {
                    message += `${index + 1}. ${appt.title || getAppointmentTitle(appt.appointment_type || appt.type)} at ${formatTime(appt.appointment_time || appt.time)} with ${appt.caseworker || 'To be assigned'}\n`;
                });
                
                message += '\nClick "OK" to view details.';
                
                if (confirm(message) && dayAppointments.length > 0) {
                    // Open first appointment details
                    viewAppointmentDetails(dayAppointments[0].id);
                }
            }
            
            // Calendar navigation
            function previousMonth() {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                generateCalendar();
            }
            
            function nextMonth() {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                generateCalendar();
            }
            
            function goToToday() {
                const today = new Date();
                currentMonth = today.getMonth();
                currentYear = today.getFullYear();
                generateCalendar();
            }
            
            // Update statistics
            function updateStatistics() {
                const upcoming = appointments.filter(a => a.status === 'upcoming').length;
                const scheduled = appointments.filter(a => a.status === 'scheduled').length;
                const completed = appointments.filter(a => a.status === 'completed').length;
                const cancelled = appointments.filter(a => a.status === 'cancelled').length;
                
                // Update counts
                document.getElementById('upcomingCount').textContent = upcoming;
                document.getElementById('scheduledCount').textContent = scheduled;
                document.getElementById('completedCount').textContent = completed;
                document.getElementById('cancelledCount').textContent = cancelled;
                
                // Update sidebar badge
                document.getElementById('upcomingAppointmentsBadge').textContent = upcoming;
            }
            
            // Utility functions
            function getAppointmentIcon(type) {
                const iconMap = {
                    'home-study': 'fa-home',
                    'caseworker': 'fa-user-tie',
                    'counseling': 'fa-comments',
                    'document-review': 'fa-file-alt',
                    'child-meeting': 'fa-child',
                    'follow-up': 'fa-redo',
                    'other': 'fa-calendar'
                };
                return iconMap[type] || 'fa-calendar';
            }
            
            function getAppointmentTitle(type) {
                const titleMap = {
                    'home-study': 'Home Study Visit',
                    'caseworker': 'Caseworker Meeting',
                    'counseling': 'Adoption Counseling',
                    'document-review': 'Document Review Session',
                    'child-meeting': 'Child Introduction Meeting',
                    'follow-up': 'Follow-up Meeting',
                    'other': 'Appointment'
                };
                return titleMap[type] || 'Appointment';
            }
            
            function getLocationName(locationType) {
                const locationMap = {
                    'office': 'Family Bridge Office',
                    'home': 'Your Home',
                    'video': 'Video Conference',
                    'phone': 'Phone Call'
                };
                return locationMap[locationType] || locationType || 'Not specified';
            }
            
            function getLocationType(locationName) {
                const locationMap = {
                    'Family Bridge Office': 'office',
                    'Your Home': 'home',
                    'Video Conference': 'video',
                    'Phone Call': 'phone'
                };
                return locationMap[locationName] || '';
            }
            
            function getStatusText(status) {
                const statusMap = {
                    'upcoming': 'Upcoming',
                    'scheduled': 'Scheduled',
                    'completed': 'Completed',
                    'cancelled': 'Cancelled'
                };
                return statusMap[status] || status;
            }
            
            function formatDate(dateString) {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    weekday: 'short',
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
            }
            
            function formatTime(timeString) {
                if (!timeString) return '';
                const [hours, minutes] = timeString.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour % 12 || 12;
                return `${displayHour}:${minutes} ${ampm}`;
            }
            
            function openModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                }
            }
            
            function closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                }
            }
            
            function showAlert(message, type = 'info') {
                // Remove existing alerts
                document.querySelectorAll('.custom-alert').forEach(alert => alert.remove());
                
                // Create alert element
                const alertDiv = document.createElement('div');
                alertDiv.className = `custom-alert alert alert-${type}`;
                alertDiv.style.position = 'fixed';
                alertDiv.style.top = '20px';
                alertDiv.style.right = '20px';
                alertDiv.style.zIndex = '1000';
                alertDiv.style.maxWidth = '400px';
                alertDiv.style.animation = 'slideIn 0.3s ease';
                
                let icon = 'fa-info-circle';
                if (type === 'success') icon = 'fa-check-circle';
                if (type === 'error') icon = 'fa-exclamation-circle';
                if (type === 'warning') icon = 'fa-exclamation-triangle';
                
                alertDiv.innerHTML = `
                    <i class="fas ${icon}"></i>
                    <div>${message}</div>
                    <button style="margin-left: auto; background: none; border: none; color: inherit; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                document.body.appendChild(alertDiv);
                
                // Add close button functionality
                alertDiv.querySelector('button').addEventListener('click', function() {
                    alertDiv.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 300);
                });
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.style.animation = 'slideOut 0.3s ease';
                        setTimeout(() => {
                            if (alertDiv.parentNode) {
                                alertDiv.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>