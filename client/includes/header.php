
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
                        <div class="user-avatar">
                            <?php 
                            $initials = 'FB';
                            if (isset($user['partner1_name'])) {
                                $names = explode(' ', $user['partner1_name']);
                                $initials = substr($names[0], 0, 1);
                                if (isset($user['partner2_name'])) {
                                    $names2 = explode(' ', $user['partner2_name']);
                                    $initials .= substr($names2[0], 0, 1);
                                }
                            }
                            echo strtoupper($initials);
                            ?>
                        </div>
                        <div class="user-details">
                            <h3><?php echo htmlspecialchars($user_name); ?></h3>
                            <p>Registration ID: <?php echo htmlspecialchars($user_reg_id); ?></p>
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
