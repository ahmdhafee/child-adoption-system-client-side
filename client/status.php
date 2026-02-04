<?php
session_start();


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
$eligibility_score = 0;
$application_status = 'Not Started';
$current_stage = 'Application';
$days_in_process = 0;
$overall_progress = 0;
$estimated_days = 45;
$estimated_completion = date('M Y', strtotime('+45 days'));
$current_stage_days = 0;


$timeline = [];
$requirements = [];
$next_steps = [];


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
   
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.registration_id, u.created_at,
                                  a.id as app_id, a.status as app_status, a.current_stage, 
                                  a.eligibility_score, a.submitted_at,
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
        $eligibility_score = intval($user['eligibility_score'] ?? 0);
        $application_status = htmlspecialchars($user['app_status'] ?? 'Not Started');
        $current_stage = htmlspecialchars($user['current_stage'] ?? 'Application');
        
        // Calculate days in process
        if ($user['submitted_at']) {
            $submitted_date = new DateTime($user['submitted_at']);
            $current_date = new DateTime();
            $days_in_process = $current_date->diff($submitted_date)->days;
        } elseif ($user['created_at']) {
            $created_date = new DateTime($user['created_at']);
            $current_date = new DateTime();
            $days_in_process = $current_date->diff($created_date)->days;
        }
        
        $timeline_stmt = $pdo->prepare("SELECT * FROM application_timeline WHERE application_id = ? OR application_id IS NULL ORDER BY stage_order");
        $timeline_stmt->execute([$user['app_id'] ?? null]);
        $timeline_data = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);
        
      
        if (empty($timeline_data)) {
            $timeline = getDefaultTimeline($user, $pdo);
        } else {
            $timeline = $timeline_data;
        }
        
  
        $req_stmt = $pdo->prepare("SELECT * FROM application_requirements WHERE user_id = ? ORDER BY due_date");
        $req_stmt->execute([$user_id]);
        $requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        if (empty($requirements)) {
            $requirements = getDefaultRequirements($user_id, $pdo);
        }
        
       
        $completed_stages = array_filter($timeline, function($stage) {
            return $stage['status'] === 'completed';
        });
        
        $total_stages = count($timeline);
        $completed_count = count($completed_stages);
        $overall_progress = $total_stages > 0 ? round(($completed_count / $total_stages) * 100) : 0;
        
       
        $current_stage_days = min($days_in_process, 30);
        
       
        $next_steps = getNextSteps($current_stage, $requirements);
        
    } else {
       
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Status page database error: " . $e->getMessage());
    
    $timeline = getDefaultTimeline(['id' => 0], null);
    $requirements = getDefaultRequirements(0, null);
    $next_steps = getNextSteps('Application', []);
}


function getDefaultTimeline($user, $pdo) {
    $default_timeline = [
        [
            'id' => 1,
            'title' => 'Initial Application Submitted',
            'description' => 'Your initial application form has been received and is under review.',
            'date' => date('M j, Y', strtotime('-'.(rand(10,20)).' days')),
            'status' => 'completed',
            'stage_order' => 1,
            'percentage' => 10,
            'details' => 'This is the first step where you submitted your basic information and expressed interest in adoption.'
        ],
        [
            'id' => 2,
            'title' => 'Background Check Completed',
            'description' => 'Criminal background and reference checks have been cleared.',
            'date' => date('M j, Y', strtotime('-'.(rand(5,10)).' days')),
            'status' => 'completed',
            'stage_order' => 2,
            'percentage' => 25,
            'details' => 'All background checks including criminal history, employment verification, and personal references have been successfully completed and cleared.'
        ],
        [
            'id' => 3,
            'title' => 'Home Study Scheduled',
            'description' => 'Home study visit has been scheduled with a caseworker.',
            'date' => date('M j, Y', strtotime('+'.(rand(3,7)).' days')),
            'status' => 'completed',
            'stage_order' => 3,
            'percentage' => 40,
            'details' => 'Your home study visit is scheduled. A caseworker will visit your home to assess your living environment and conduct interviews.'
        ],
        [
            'id' => 4,
            'title' => 'Financial Assessment',
            'description' => 'Financial stability review in progress.',
            'date' => date('M j, Y'),
            'status' => 'current',
            'stage_order' => 4,
            'percentage' => 55,
            'details' => 'Your financial documents have been submitted and are currently being reviewed to ensure you can provide for a child\'s needs.'
        ],
        [
            'id' => 5,
            'title' => 'Child Selection Phase',
            'description' => 'Reviewing available children profiles and expressing preferences.',
            'date' => date('M j, Y', strtotime('+'.(rand(7,14)).' days')),
            'status' => 'pending',
            'stage_order' => 5,
            'percentage' => 65,
            'details' => 'You will browse available children profiles and indicate your preferences.'
        ],
        [
            'id' => 6,
            'title' => 'Home Study Visit',
            'description' => 'In-person home study with caseworker.',
            'date' => date('M j, Y', strtotime('+'.(rand(15,20)).' days')),
            'status' => 'pending',
            'stage_order' => 6,
            'percentage' => 75,
            'details' => 'This visit will involve interviews with all family members and assessment of your home environment.'
        ],
        [
            'id' => 7,
            'title' => 'Matching Committee Review',
            'description' => 'Committee review for child matching.',
            'date' => date('M j, Y', strtotime('+'.(rand(25,30)).' days')),
            'status' => 'pending',
            'stage_order' => 7,
            'percentage' => 85,
            'details' => 'The matching committee will review your application and preferences to find suitable matches.'
        ],
        [
            'id' => 8,
            'title' => 'Placement Decision',
            'description' => 'Final decision on child placement.',
            'date' => date('M j, Y', strtotime('+'.(rand(35,40)).' days')),
            'status' => 'pending',
            'stage_order' => 8,
            'percentage' => 95,
            'details' => 'Final decision will be made regarding child placement. If approved, you\'ll move to the placement phase.'
        ],
        [
            'id' => 9,
            'title' => 'Placement & Finalization',
            'description' => 'Child placement and legal finalization.',
            'date' => date('M j, Y', strtotime('+'.(rand(45,60)).' days')),
            'status' => 'pending',
            'stage_order' => 9,
            'percentage' => 100,
            'details' => 'Child will be placed in your home, followed by legal finalization of the adoption through court proceedings.'
        ]
    ];
    
    // If we have a database connection, save the default timeline
    if ($pdo && $user['app_id']) {
        try {
            foreach ($default_timeline as $stage) {
                $insert_stmt = $pdo->prepare("INSERT INTO application_timeline 
                    (application_id, title, description, date, status, stage_order, percentage, details)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->execute([
                    $user['app_id'],
                    $stage['title'],
                    $stage['description'],
                    $stage['date'],
                    $stage['status'],
                    $stage['stage_order'],
                    $stage['percentage'],
                    $stage['details']
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error saving default timeline: " . $e->getMessage());
        }
    }
    
    return $default_timeline;
}


function getDefaultRequirements($user_id, $pdo) {
    $default_requirements = [
        [
            'title' => 'Financial Documents',
            'status' => 'completed',
            'category' => 'Financial',
            'due_date' => date('M j, Y', strtotime('-'.rand(5,10).' days')),
            'progress' => 100,
            'description' => 'Tax returns, pay stubs, and financial statements submitted and verified.'
        ],
        [
            'title' => 'Background Checks',
            'status' => 'completed',
            'category' => 'Legal',
            'due_date' => date('M j, Y', strtotime('-'.rand(3,7).' days')),
            'progress' => 100,
            'description' => 'Criminal background and fingerprint clearance completed.'
        ],
        [
            'title' => 'Medical Examinations',
            'status' => 'in-progress',
            'category' => 'Health',
            'due_date' => date('M j, Y', strtotime('+'.rand(5,10).' days')),
            'progress' => 80,
            'description' => 'Physical exams completed, waiting for final doctor\'s reports.'
        ],
        [
            'title' => 'Home Study Preparation',
            'status' => 'in-progress',
            'category' => 'Home',
            'due_date' => date('M j, Y', strtotime('+'.rand(7,14).' days')),
            'progress' => 60,
            'description' => 'Home safety checklist partially completed, CPR certification needed.'
        ],
        [
            'title' => 'References Submitted',
            'status' => 'completed',
            'category' => 'Personal',
            'due_date' => date('M j, Y', strtotime('-'.rand(2,5).' days')),
            'progress' => 100,
            'description' => 'All personal references have submitted their recommendations.'
        ],
        [
            'title' => 'Adoption Training',
            'status' => 'in-progress',
            'category' => 'Education',
            'due_date' => date('M j, Y', strtotime('+'.rand(10,15).' days')),
            'progress' => 75,
            'description' => 'Completed most required training hours.'
        ],
        [
            'title' => 'Child Preference Profile',
            'status' => 'pending',
            'category' => 'Preferences',
            'due_date' => date('M j, Y', strtotime('+'.rand(15,20).' days')),
            'progress' => 30,
            'description' => 'Need to specify age range, gender, and special needs preferences.'
        ],
        [
            'title' => 'Legal Documentation',
            'status' => 'pending',
            'category' => 'Legal',
            'due_date' => date('M j, Y', strtotime('+'.rand(20,25).' days')),
            'progress' => 20,
            'description' => 'Marriage certificate and other legal documents need verification.'
        ]
    ];
    
    // If we have a database connection, save the default requirements
    if ($pdo && $user_id) {
        try {
            foreach ($default_requirements as $req) {
                $insert_stmt = $pdo->prepare("INSERT INTO application_requirements 
                    (user_id, title, status, category, due_date, progress, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->execute([
                    $user_id,
                    $req['title'],
                    $req['status'],
                    $req['category'],
                    $req['due_date'],
                    $req['progress'],
                    $req['description']
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error saving default requirements: " . $e->getMessage());
        }
    }
    
    return $default_requirements;
}


function getNextSteps($current_stage, $requirements) {
    $next_steps = [];
    
    switch($current_stage) {
        case 'Application':
            $next_steps = [
                [
                    'title' => 'Complete Application Form',
                    'description' => 'Fill out all sections of the adoption application form.',
                    'action' => 'Complete Form',
                    'icon' => 'fas fa-file-alt',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Upload Required Documents',
                    'description' => 'Submit identification and financial documents.',
                    'action' => 'Upload Documents',
                    'icon' => 'fas fa-upload',
                    'priority' => 'high'
                ]
            ];
            break;
            
        case 'Review':
            $next_steps = [
                [
                    'title' => 'Complete Required Training',
                    'description' => 'Finish the remaining adoption parenting education hours.',
                    'action' => 'Access Training',
                    'icon' => 'fas fa-graduation-cap',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Schedule Home Study',
                    'description' => 'Contact caseworker to schedule home study visit.',
                    'action' => 'Schedule Visit',
                    'icon' => 'fas fa-calendar',
                    'priority' => 'medium'
                ]
            ];
            break;
            
        case 'Home Study':
            $next_steps = [
                [
                    'title' => 'Prepare for Home Study',
                    'description' => 'Complete home safety checklist and gather required documents.',
                    'action' => 'View Checklist',
                    'icon' => 'fas fa-home',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Complete Medical Exams',
                    'description' => 'Schedule and complete required medical examinations.',
                    'action' => 'Schedule Exams',
                    'icon' => 'fas fa-file-medical',
                    'priority' => 'medium'
                ]
            ];
            break;
            
        case 'Child Selection':
            $next_steps = [
                [
                    'title' => 'Review Child Profiles',
                    'description' => 'Browse available children and indicate your preferences.',
                    'action' => 'Browse Children',
                    'icon' => 'fas fa-child',
                    'priority' => 'high'
                ],
                [
                    'title' => 'Submit Child Preferences',
                    'description' => 'Formally submit your preferences for age, gender, and special needs.',
                    'action' => 'Submit Preferences',
                    'icon' => 'fas fa-heart',
                    'priority' => 'medium'
                ]
            ];
            break;
            
        default:
            $next_steps = [
                [
                    'title' => 'Check Application Status',
                    'description' => 'Regularly check for updates on your application progress.',
                    'action' => 'Check Status',
                    'icon' => 'fas fa-sync-alt',
                    'priority' => 'medium'
                ],
                [
                    'title' => 'Contact Caseworker',
                    'description' => 'Reach out to your assigned caseworker for any questions.',
                    'action' => 'Contact Support',
                    'icon' => 'fas fa-headset',
                    'priority' => 'low'
                ]
            ];
    }
    
    return $next_steps;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/status.css">
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
    
    <header>
        <div class="container">
            <div class="header-content">
                <div style="display: flex; align-items: center; gap: 20px;">
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
                        <div class="user-avatar"><?php echo substr($user_name, 0, 2); ?></div>
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
                <a href="index.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    Profile Management
                </a>
                <a href="documents.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    Documents
                </a>
                <a href="children.php" class="nav-item">
                    <i class="fas fa-child"></i>
                    Available Children
                </a>
                <a href="status.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    Application Status
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    Appointments
                </a>
                
                <div class="sidebar-footer">
                    <p>Family Bridge Portal</p>
                </div>
            </nav>
        </aside>

       
        <main class="main-content">
            
            <div class="page-header">
                <h1>Application Status</h1>
                <p>Track your adoption journey progress and see what's next</p>
            </div>

            
            <div class="status-overview">
                <div class="overview-header">
                    <h2><i class="fas fa-chart-line"></i> Application Overview</h2>
                    <div class="btn btn-outline" id="refreshStatusBtn">
                        <i class="fas fa-sync-alt"></i> Refresh Status
                    </div>
                </div>
                <div class="overview-content">
                    <div class="overview-item">
                        <div class="overview-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Application Status</h3>
                        <p><?php echo ucfirst($application_status); ?></p>
                    </div>
                    <div class="overview-item">
                        <div class="overview-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <h3>Eligibility Score</h3>
                        <p><?php echo $eligibility_score; ?>%</p>
                    </div>
                    <div class="overview-item">
                        <div class="overview-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Current Stage</h3>
                        <p><?php echo $current_stage; ?></p>
                    </div>
                    <div class="overview-item">
                        <div class="overview-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>Days in Process</h3>
                        <p><?php echo $days_in_process; ?> Days</p>
                    </div>
                </div>
            </div>

         
            <div class="progress-timeline">
                <div class="timeline-header">
                    <h2><i class="fas fa-history"></i> Your Adoption Journey</h2>
                    <div class="timeline-progress">
                        <div class="progress-percentage" id="overallProgress"><?php echo $overall_progress; ?>%</div>
                        <div class="progress-container" style="flex: 1;">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $overall_progress; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="timeline" id="applicationTimeline">
                 
                </div>
            </div>

        
            <div class="timeline-estimate">
                <div class="estimate-header">
                    <i class="fas fa-hourglass-half" style="font-size: 2rem;"></i>
                    <h2>Estimated Timeline</h2>
                </div>
                <div class="estimate-content">
                    <div class="estimate-item">
                        <h3 id="daysToCompletion"><?php echo $estimated_days; ?></h3>
                        <p>Estimated Days to Completion</p>
                    </div>
                    <div class="estimate-item">
                        <h3 id="estimatedCompletion"><?php echo $estimated_completion; ?></h3>
                        <p>Estimated Completion Date</p>
                    </div>
                    <div class="estimate-item">
                        <h3 id="currentStageTime"><?php echo $current_stage_days; ?></h3>
                        <p>Days in Current Stage</p>
                    </div>
                </div>
            </div>

          
            <div class="requirements-status">
                <div class="requirements-header">
                    <h2><i class="fas fa-clipboard-check"></i> Requirements Status</h2>
                    <div class="btn btn-primary" id="viewAllRequirementsBtn">
                        <i class="fas fa-list"></i> View All Requirements
                    </div>
                </div>
                <div class="requirements-grid" id="requirementsGrid">
                   
                </div>
            </div>

            
            <div class="next-steps">
                <div class="next-steps-header">
                    <i class="fas fa-arrow-right" style="font-size: 2rem; color: var(--info);"></i>
                    <h2>Next Steps</h2>
                </div>
                <div class="next-steps-content" id="nextStepsContent">
                   
                </div>
            </div>

           
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Status Update:</strong> Your application is progressing well. 
                    <?php if ($current_stage == 'Home Study'): ?>
                        The next major milestone is the home study visit.
                    <?php elseif ($current_stage == 'Child Selection'): ?>
                        You can now browse available children profiles.
                    <?php else: ?>
                        Continue completing the required documents and training.
                    <?php endif; ?>
                </div>
            </div>

          
            <div class="alert alert-warning">
                <i class="fas fa-headset"></i>
                <div>
                    <strong>Need Help?</strong> Contact your caseworker: Officer Sarah Johnson - 
                    <a href="mailto:sarah.johnson@familybridge.gov" style="color: inherit; text-decoration: underline;">
                        sarah.johnson@familybridge.gov
                    </a> | (800) 555-1234 ext. 567
                </div>
            </div>
        </main>
    </div>

  
    <div class="modal" id="timelineDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Stage Details</h3>
                <button class="modal-close" data-modal="timelineDetailsModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="timelineDetailsContent">
                    
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="timelineDetailsModal">Close</button>
            </div>
        </div>
    </div>


    <div class="modal" id="requirementsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>All Requirements</h3>
                <button class="modal-close" data-modal="requirementsModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="requirementsModalContent">
                    
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="requirementsModal">Close</button>
                <button class="btn btn-primary" id="downloadRequirementsBtn">
                    <i class="fas fa-download"></i> Download Checklist
                </button>
            </div>
        </div>
    </div>

    <script>
      
        const applicationData = {
            timeline: <?php echo json_encode($timeline); ?>,
            requirements: <?php echo json_encode($requirements); ?>,
            nextSteps: <?php echo json_encode($next_steps); ?>,
            currentStage: "<?php echo $current_stage; ?>",
            eligibilityScore: <?php echo $eligibility_score; ?>,
            applicationStatus: "<?php echo $application_status; ?>"
        };


        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const logoutBtn = document.getElementById('logoutBtn');
        const refreshStatusBtn = document.getElementById('refreshStatusBtn');
        const viewAllRequirementsBtn = document.getElementById('viewAllRequirementsBtn');
        const timelineContainer = document.getElementById('applicationTimeline');
        const requirementsGrid = document.getElementById('requirementsGrid');
        const nextStepsContent = document.getElementById('nextStepsContent');
        const timelineModal = document.getElementById('timelineDetailsModal');
        const requirementsModal = document.getElementById('requirementsModal');
        const timelineDetailsContent = document.getElementById('timelineDetailsContent');
        const requirementsModalContent = document.getElementById('requirementsModalContent');
        const downloadRequirementsBtn = document.getElementById('downloadRequirementsBtn');
        const overallProgress = document.getElementById('overallProgress');
        const daysToCompletion = document.getElementById('daysToCompletion');
        const estimatedCompletion = document.getElementById('estimatedCompletion');
        const currentStageTime = document.getElementById('currentStageTime');

    
        document.addEventListener('DOMContentLoaded', function() {
            renderTimeline();
            renderRequirements();
            renderNextSteps();
            setupEventListeners();
        });

       
        function renderTimeline() {
            timelineContainer.innerHTML = '';
            
            applicationData.timeline.forEach((item, index) => {
                const timelineItem = document.createElement('div');
                timelineItem.className = `timeline-item ${item.status}`;
                timelineItem.dataset.id = item.id;
                
                let statusText = '';
                let statusClass = '';
                
                switch(item.status) {
                    case 'completed':
                        statusText = 'Completed';
                        statusClass = 'status-completed';
                        break;
                    case 'current':
                        statusText = 'In Progress';
                        statusClass = 'status-current';
                        break;
                    case 'pending':
                        statusText = 'Pending';
                        statusClass = 'status-pending';
                        break;
                }
                
                timelineItem.innerHTML = `
                    <div class="timeline-icon">
                        <i class="fas fa-${item.status === 'completed' ? 'check' : item.status === 'current' ? 'spinner' : 'clock'}"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>${item.title} <i class="fas fa-info-circle" style="color: var(--accent); cursor: pointer;" data-action="view-details" data-id="${item.id}"></i></h4>
                        <p>${item.description}</p>
                        <div class="timeline-date">${item.date}</div>
                        <span class="timeline-status ${statusClass}">${statusText}</span>
                    </div>
                `;
                
                timelineContainer.appendChild(timelineItem);
            });
        }

        
        function renderRequirements() {
            requirementsGrid.innerHTML = '';
            
            
            const requirementsToShow = applicationData.requirements.slice(0, 4);
            
            requirementsToShow.forEach(item => {
                const requirementCard = document.createElement('div');
                requirementCard.className = `requirement-card ${item.status}`;
                requirementCard.dataset.id = item.id || item.title;
                
                let statusIcon = '';
                switch(item.status) {
                    case 'completed':
                        statusIcon = 'fa-check-circle';
                        break;
                    case 'in-progress':
                        statusIcon = 'fa-spinner';
                        break;
                    case 'pending':
                        statusIcon = 'fa-clock';
                        break;
                }
                
                requirementCard.innerHTML = `
                    <div class="requirement-header">
                        <div class="requirement-icon ${item.status}">
                            <i class="fas ${statusIcon}"></i>
                        </div>
                        <div class="requirement-title">
                            <h4>${item.title}</h4>
                            <p>${item.category} • Due: ${item.due_date}</p>
                        </div>
                    </div>
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Progress</span>
                            <span>${item.progress}%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${item.progress}%;"></div>
                        </div>
                    </div>
                    <div class="requirement-details">
                        ${item.description}
                    </div>
                `;
                
                requirementsGrid.appendChild(requirementCard);
            });
        }


        function renderNextSteps() {
            nextStepsContent.innerHTML = '';
            
            applicationData.nextSteps.forEach(step => {
                const stepCard = document.createElement('div');
                stepCard.className = 'next-step-card';
                
                let priorityColor = step.priority === 'high' ? 'var(--error)' : step.priority === 'medium' ? 'var(--warning)' : 'var(--info)';
                
                stepCard.innerHTML = `
                    <h4><i class="${step.icon}" style="color: ${priorityColor};"></i> ${step.title}</h4>
                    <p>${step.description}</p>
                    <button class="btn btn-sm ${step.priority === 'high' ? 'btn-danger' : step.priority === 'medium' ? 'btn-warning' : 'btn-primary'}" style="margin-top: 10px;">
                        ${step.action}
                    </button>
                `;
                
                nextStepsContent.appendChild(stepCard);
            });
        }

      
        function setupEventListeners() {
       
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

      
            logoutBtn.addEventListener('click', () => {
                if (confirm('Are you sure you want to log out?')) {
                    window.location.href = '../logout.php';
                }
            });


            refreshStatusBtn.addEventListener('click', () => {
                refreshStatusBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
                
         
                fetch('refresh_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            refreshStatusBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Status';
                            
                     
                            if (data.timeline) {
                                applicationData.timeline = data.timeline;
                                renderTimeline();
                            }
                            
                         
                            const alert = document.createElement('div');
                            alert.className = 'alert alert-success';
                            alert.innerHTML = '<i class="fas fa-check-circle"></i> <div><strong>Status Updated:</strong> Your application status has been refreshed.</div>';
                            
                            document.querySelector('.main-content').prepend(alert);
                            
                         
                            setTimeout(() => {
                                alert.remove();
                            }, 5000);
                        }
                    })
                    .catch(error => {
                        console.error('Error refreshing status:', error);
                        refreshStatusBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Status';
                        
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-warning';
                        alert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <div><strong>Update Failed:</strong> Could not refresh status. Please try again.</div>';
                        
                        document.querySelector('.main-content').prepend(alert);
                        
                        setTimeout(() => {
                            alert.remove();
                        }, 5000);
                    });
            });

         
            viewAllRequirementsBtn.addEventListener('click', () => {
                showAllRequirements();
                requirementsModal.classList.add('active');
            });

            
            timelineContainer.addEventListener('click', (e) => {
                if (e.target.closest('[data-action="view-details"]')) {
                    const itemId = e.target.closest('[data-action="view-details"]').dataset.id;
                    showTimelineDetails(itemId);
                    timelineModal.classList.add('active');
                }
            });

       
            document.querySelectorAll('.modal-close').forEach(button => {
                button.addEventListener('click', (e) => {
                    const modalId = e.target.closest('.modal-close').dataset.modal;
                    document.getElementById(modalId).classList.remove('active');
                });
            });

           
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                    }
                });
            });

         
            downloadRequirementsBtn.addEventListener('click', () => {
                downloadRequirementsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
                
              
                const checklistContent = generateChecklistContent();
                const blob = new Blob([checklistContent], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'adoption_requirements_checklist.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                setTimeout(() => {
                    downloadRequirementsBtn.innerHTML = '<i class="fas fa-download"></i> Download Checklist';
                }, 1000);
            });

        
            nextStepsContent.addEventListener('click', (e) => {
                if (e.target.closest('.btn')) {
                    const stepTitle = e.target.closest('.next-step-card').querySelector('h4').textContent;
                    
                  
                    if (stepTitle.includes('Browse Children')) {
                        window.location.href = 'children.php';
                    } else if (stepTitle.includes('Upload') || stepTitle.includes('Documents')) {
                        window.location.href = 'documents.php';
                    } else if (stepTitle.includes('Training')) {
                        
                        alert(`Action initiated: ${stepTitle}`);
                    } else {
                        alert(`Action initiated: ${stepTitle}`);
                    }
                }
            });
        }

      
        function showTimelineDetails(itemId) {
            const item = applicationData.timeline.find(i => i.id == itemId);
            
            if (!item) return;
            
            timelineDetailsContent.innerHTML = `
                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--primary); margin-bottom: 10px;">${item.title}</h3>
                    <p style="color: var(--gray); margin-bottom: 15px;">${item.description}</p>
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-calendar" style="color: var(--accent);"></i>
                            <span><strong>Date:</strong> ${item.date}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-percentage" style="color: var(--accent);"></i>
                            <span><strong>Progress:</strong> ${item.percentage}%</span>
                        </div>
                    </div>
                </div>
                <div style="background-color: var(--light); padding: 20px; border-radius: var(--border-radius);">
                    <h4 style="color: var(--dark); margin-bottom: 10px;">Details</h4>
                    <p style="color: var(--gray); line-height: 1.6;">${item.details}</p>
                </div>
            `;
        }

  
        function showAllRequirements() {
            requirementsModalContent.innerHTML = '';
            
            applicationData.requirements.forEach(item => {
                let statusBadge = '';
                let statusColor = '';
                
                switch(item.status) {
                    case 'completed':
                        statusBadge = 'Completed';
                        statusColor = 'var(--success)';
                        break;
                    case 'in-progress':
                        statusBadge = 'In Progress';
                        statusColor = 'var(--warning)';
                        break;
                    case 'pending':
                        statusBadge = 'Pending';
                        statusColor = 'var(--gray)';
                        break;
                }
                
                const requirementItem = document.createElement('div');
                requirementItem.className = 'requirement-card';
                requirementItem.style.marginBottom = '15px';
                requirementItem.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <div>
                            <h4 style="color: var(--dark); margin-bottom: 5px;">${item.title}</h4>
                            <p style="color: var(--gray); font-size: 0.9rem;">${item.category} • Due: ${item.due_date}</p>
                        </div>
                        <span style="background-color: ${statusColor}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                            ${statusBadge}
                        </span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="color: var(--dark); font-size: 0.9rem;">Progress</span>
                            <span style="color: var(--dark); font-size: 0.9rem;">${item.progress}%</span>
                        </div>
                        <div style="height: 8px; background-color: var(--light-gray); border-radius: 4px; overflow: hidden;">
                            <div style="width: ${item.progress}%; height: 100%; background: linear-gradient(to right, var(--primary), var(--secondary));"></div>
                        </div>
                    </div>
                    <p style="color: var(--gray); font-size: 0.9rem;">${item.description}</p>
                `;
                
                requirementsModalContent.appendChild(requirementItem);
            });
        }

       
        function generateChecklistContent() {
            let content = 'Family Bridge Adoption - Requirements Checklist\n';
            content += '===============================================\n\n';
            content += `Generated: ${new Date().toLocaleDateString()}\n`;
            content += `Applicant: ${document.querySelector('.user-details h3').textContent}\n`;
            content += `Application Status: ${applicationData.applicationStatus}\n\n`;
            
            applicationData.requirements.forEach((req, index) => {
                content += `${index + 1}. ${req.title}\n`;
                content += `   Status: ${req.status}\n`;
                content += `   Progress: ${req.progress}%\n`;
                content += `   Due Date: ${req.due_date}\n`;
                content += `   Notes: ${req.description}\n\n`;
            });
            
            return content;
        }
    </script>
</body>
</html>