<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Database configuration
$host = 'localhost';
$dbname = 'family_bridge';
$username = 'root'; // Change as needed
$password = ''; // Change as needed

// Initialize variables
$user_name = 'User';
$user_reg_id = 'Not Set';
$eligibility_score = 0;
$has_voted = false;
$shortlisted_children = [];
$children_data = [];
$total_children = 0;
$age_0_5 = 0;
$age_6_12 = 0;
$age_13_18 = 0;

// Fetch user data from database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Fetch user details
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.registration_id, 
                                  a.partner1_name, a.partner2_name, a.eligibility_score
                           FROM users u 
                           LEFT JOIN applications a ON u.id = a.user_id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Set user data
        if (!empty($user['partner1_name']) && !empty($user['partner2_name'])) {
            $user_name = htmlspecialchars($user['partner1_name'] . ' & ' . $user['partner2_name']);
        } elseif (!empty($user['partner1_name'])) {
            $user_name = htmlspecialchars($user['partner1_name']);
        } else {
            $user_name = htmlspecialchars($user['email']);
        }
        
        $user_reg_id = htmlspecialchars($user['registration_id'] ?? 'Not Set');
        $eligibility_score = intval($user['eligibility_score'] ?? 0);
        
        // Check if user has voted
        $vote_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_votes WHERE user_id = ? AND status = 'active'");
        $vote_stmt->execute([$user_id]);
        $vote_count = $vote_stmt->fetch(PDO::FETCH_ASSOC);
        $has_voted = ($vote_count['count'] ?? 0) > 0;
        
        // Fetch shortlisted children (if you have a shortlist table)
        // For now, using session storage
        $shortlisted_children = isset($_SESSION['shortlisted_children']) ? $_SESSION['shortlisted_children'] : [];
        
        // Fetch available children from database
        $children_stmt = $pdo->prepare("SELECT * FROM children WHERE status = 'available' ORDER BY added_at DESC");
        $children_stmt->execute();
        $children_data = $children_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate statistics
        $total_children = count($children_data);
        foreach ($children_data as $child) {
            $age = intval($child['age'] ?? 0);
            if ($age <= 5) $age_0_5++;
            elseif ($age <= 12) $age_6_12++;
            else $age_13_18++;
        }
        
    } else {
        // User not found in database
        session_destroy();
        header("Location: ../login.php?error=session_expired");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Children page database error: " . $e->getMessage());
    $children_data = []; // Use empty array if database fails
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Children | Family Bridge Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/children.css">
    <link rel="shortcut icon" href="../favlogo.png" type="logo">
    <style>
        /* CSS Reset and Base Styles */
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

<?php include 'includes/sidebar.php' ?>
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Available Children</h1>
                <p>Browse children available for adoption and shortlist your preferences</p>
            </div>

            <!-- Voting Alert -->
            <?php if (!$has_voted && $eligibility_score >= 75): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-vote-yea"></i>
                    <div>
                        <strong>Voting Reminder:</strong> You are eligible to vote. 
                        Each couple can vote for only one child. Choose carefully!
                    </div>
                </div>
            <?php elseif ($has_voted): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Vote Submitted:</strong> You have already cast your vote. 
                        The Chief Officer will review all votes and propose a match.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Children Statistics -->
            <div class="children-stats">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-child"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="totalChildren"><?php echo $total_children; ?></h3>
                        <p>Total Children</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon age-0-5">
                        <i class="fas fa-baby"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="age0to5"><?php echo $age_0_5; ?></h3>
                        <p>Ages 0-5 Years</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon age-6-12">
                        <i class="fas fa-child"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="age6to12"><?php echo $age_6_12; ?></h3>
                        <p>Ages 6-12 Years</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon age-13-18">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="age13to18"><?php echo $age_13_18; ?></h3>
                        <p>Ages 13-18 Years</p>
                    </div>
                </div>
            </div>

            <!-- Shortlisted Children -->
            <div class="shortlisted-section" id="shortlistedSection" style="<?php echo empty($shortlisted_children) ? 'display: none;' : ''; ?>">
                <h2 style="color: var(--success); margin-bottom: 10px;">
                    <i class="fas fa-heart"></i> Your Shortlisted Children
                </h2>
                <p style="color: var(--gray); margin-bottom: 15px;">Children you've expressed interest in</p>
                
                <div class="shortlisted-children" id="shortlistedChildren">
                    <!-- Shortlisted children will be loaded here -->
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchChildren" placeholder="Search children by name, age, or special needs..." aria-label="Search children">
                </div>
                
                <div class="filter-select">
                    <select id="filterAge">
                        <option value="all">All Ages</option>
                        <option value="0-5">0-5 Years</option>
                        <option value="6-12">6-12 Years</option>
                        <option value="13-18">13-18 Years</option>
                    </select>
                </div>
                
                <div class="filter-select">
                    <select id="filterGender">
                        <option value="all">All Genders</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                
                <div class="filter-select">
                    <select id="filterStatus">
                        <option value="all">All Children</option>
                        <option value="new">New Arrivals</option>
                        <option value="featured">Featured</option>
                        <option value="sibling">Sibling Groups</option>
                        <option value="special-needs">Special Needs</option>
                    </select>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="view-toggle-btn active" data-view="grid">
                    <i class="fas fa-th-large"></i> Grid View
                </button>
                <button class="view-toggle-btn" data-view="table">
                    <i class="fas fa-table"></i> Table View
                </button>
            </div>

            <!-- Children Grid View -->
            <div id="gridView" class="children-grid">
                <!-- Children cards will be loaded here -->
            </div>

            <!-- Children Table View -->
            <div id="tableView" class="children-table-container" style="display: none;">
                <table class="children-table" id="childrenTable">
                    <thead>
                        <tr>
                            <th>Child</th>
                            <th>Age & Gender</th>
                            <th>Background</th>
                            <th>Special Needs</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="childrenTableBody">
                        <!-- Table rows will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="no-results" style="display: none;">
                <i class="fas fa-search"></i>
                <h3>No Children Found</h3>
                <p>Try adjusting your search or filter to find what you're looking for.</p>
                <button class="btn btn-primary" id="resetFiltersBtn">
                    <i class="fas fa-redo"></i> Reset All Filters
                </button>
            </div>

            <!-- Important Notes -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Note:</strong> All information about children is confidential. 
                    Please respect their privacy and only share information with authorized personnel.
                </div>
            </div>
        </main>
    </div>

    <!-- Child Profile Modal -->
    <div class="modal" id="childModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Child Profile</h3>
                <button class="modal-close" data-modal="childModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="childProfileContent">
                    <!-- Child profile will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="childModal">Close</button>
                <button class="btn btn-outline" id="shortlistBtn">
                    <i class="fas fa-heart"></i> Add to Shortlist
                </button>
                <button class="btn btn-primary" id="expressInterestBtn">
                    <i class="fas fa-star"></i> Express Interest
                </button>
                <?php if (!$has_voted && $eligibility_score >= 75): ?>
                    <button class="btn btn-success" id="voteForChildBtn">
                        <i class="fas fa-vote-yea"></i> Vote for This Child
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vote Confirmation Modal -->
    <?php if (!$has_voted && $eligibility_score >= 75): ?>
    <div class="modal" id="voteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Your Vote</h3>
                <button class="modal-close" data-modal="voteModal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--warning); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--dark); margin-bottom: 15px;">Important: One Vote Per Couple</h3>
                    <p style="color: var(--gray); margin-bottom: 20px;">
                        You are about to cast your vote for <strong id="voteChildName">[Child Name]</strong>. 
                        Each couple can vote for only <strong>ONE</strong> child, and once cast, your vote cannot be changed.
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-ban"></i>
                        <div><strong>Warning:</strong> This action is permanent and cannot be undone.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-modal="voteModal">Cancel</button>
                <button class="btn btn-success" id="confirmVoteBtn">
                    <i class="fas fa-check-circle"></i> Yes, Cast My Vote
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    

    <script>
         const logoutBtn = document.getElementById('logoutBtn');
        logoutBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../logout.php';
            }
        });
        
    
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
    </script>
       
</body>
</html>