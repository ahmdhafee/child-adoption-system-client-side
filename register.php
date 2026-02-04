<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration | Family Bridge - Child Adoption System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Style/registration-styles.css">
    <link rel="shortcut icon" href="favlogo.png" type="logo">
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
    
    
    <?php
    
    session_start();
    
    
    $host = 'localhost';
    $dbname = 'family_bridge';
    $username = 'root'; 
    $password = ''; 
    
    
    $errors = [];
    $success = false;
    $registration_id = '';
    
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $confirmEmail = filter_var($_POST['confirmEmail'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';
            $paymentConfirmation = htmlspecialchars($_POST['paymentConfirmation'] ?? '');
            
         
            $partner1FullName = htmlspecialchars($_POST['partner1FullName'] ?? '');
            $partner1Age = filter_var($_POST['partner1Age'] ?? 0, FILTER_VALIDATE_INT);
            $partner1Occupation = htmlspecialchars($_POST['partner1Occupation'] ?? '');
            $partner1ID = htmlspecialchars($_POST['partner1ID'] ?? '');
            $partner1BloodGroup = htmlspecialchars($_POST['partner1BloodGroup'] ?? '');
            $partner1MedicalConditions = htmlspecialchars($_POST['partner1MedicalConditions'] ?? '');
            
            
            $partner2FullName = htmlspecialchars($_POST['partner2FullName'] ?? '');
            $partner2Age = filter_var($_POST['partner2Age'] ?? 0, FILTER_VALIDATE_INT);
            $partner2Occupation = htmlspecialchars($_POST['partner2Occupation'] ?? '');
            $partner2ID = htmlspecialchars($_POST['partner2ID'] ?? '');
            $partner2BloodGroup = htmlspecialchars($_POST['partner2BloodGroup'] ?? '');
            $partner2MedicalConditions = htmlspecialchars($_POST['partner2MedicalConditions'] ?? '');
            
          
            $district = htmlspecialchars($_POST['district'] ?? '');
            $address = htmlspecialchars($_POST['address'] ?? '');
            
            
            $privacyConsent = isset($_POST['privacyConsent']) ? 1 : 0;
            $termsAgreement = isset($_POST['termsAgreement']) ? 1 : 0;
            $finalConfirmation = isset($_POST['finalConfirmation']) ? 1 : 0;
            
          
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Valid email address is required.";
            }
            
            if ($email !== $confirmEmail) {
                $errors[] = "Email addresses do not match.";
            }
            
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters.";
            }
            
            if ($password !== $confirmPassword) {
                $errors[] = "Passwords do not match.";
            }
            
            if (empty($partner1FullName) || empty($partner2FullName)) {
                $errors[] = "Both partners' full names are required.";
            }
            
            if ($partner1Age < 21 || $partner1Age > 65 || $partner2Age < 21 || $partner2Age > 65) {
                $errors[] = "Both partners must be between 21 and 65 years old.";
            }
            
            if ($privacyConsent !== 1 || $termsAgreement !== 1) {
                $errors[] = "All consents and agreements must be accepted.";
            }
            
         
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "This email is already registered.";
            }
            
            
            if (empty($errors)) {
               
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                
                $registration_id = 'FB-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                
                
                $pdo->beginTransaction();
                

                $stmt = $pdo->prepare("INSERT INTO users (email, password, registration_id, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$email, $hashedPassword, $registration_id]);
                $user_id = $pdo->lastInsertId();
                
                
                $stmt = $pdo->prepare("INSERT INTO applications (user_id, registration_id, payment_confirmation, partner1_name, partner1_age, partner1_occupation, partner1_id, partner1_blood_group, partner1_medical, partner2_name, partner2_age, partner2_occupation, partner2_id, partner2_blood_group, partner2_medical, district, address, privacy_consent, terms_agreement, eligibility_score, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
             
                $eligibility_score = isset($_SESSION['eligibility_score']) ? $_SESSION['eligibility_score'] : 0;
                
                $stmt->execute([
                    $user_id,
                    $registration_id,
                    $paymentConfirmation,
                    $partner1FullName,
                    $partner1Age,
                    $partner1Occupation,
                    $partner1ID,
                    $partner1BloodGroup,
                    $partner1MedicalConditions,
                    $partner2FullName,
                    $partner2Age,
                    $partner2Occupation,
                    $partner2ID,
                    $partner2BloodGroup,
                    $partner2MedicalConditions,
                    $district,
                    $address,
                    $privacyConsent,
                    $termsAgreement,
                    $eligibility_score,
                    'pending'
                ]);
                
                
                $upload_dir = "uploads/nic_photos/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                
                function uploadNICPhoto($file, $user_id, $partner_num, $side) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                        $max_size = 5 * 1024 * 1024; // 5MB
                        
                        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $filename = "user_{$user_id}_partner{$partner_num}_{$side}_" . time() . "." . $ext;
                            $destination = "uploads/nic_photos/" . $filename;
                            
                            if (move_uploaded_file($file['tmp_name'], $destination)) {
                                return $filename;
                            }
                        }
                    }
                    return null;
                }
                
                
                $nic_files = [];
                for ($i = 1; $i <= 2; $i++) {
                    foreach (['front', 'back'] as $side) {
                        $field_name = "nic{$side}{$i}";
                        if (isset($_FILES[$field_name])) {
                            $filename = uploadNICPhoto($_FILES[$field_name], $user_id, $i, $side);
                            if ($filename) {
                                $nic_files[] = [
                                    'user_id' => $user_id,
                                    'partner_num' => $i,
                                    'side' => $side,
                                    'filename' => $filename,
                                    'uploaded_at' => date('Y-m-d H:i:s')
                                ];
                            }
                        }
                    }
                }
                
               
                if (!empty($nic_files)) {
                    $stmt = $pdo->prepare("INSERT INTO nic_photos (user_id, partner_num, side, filename, uploaded_at) VALUES (?, ?, ?, ?, ?)");
                    foreach ($nic_files as $file) {
                        $stmt->execute([
                            $file['user_id'],
                            $file['partner_num'],
                            $file['side'],
                            $file['filename'],
                            $file['uploaded_at']
                        ]);
                    }
                }
                
                $pdo->commit();
                $success = true;
                
              
                unset($_SESSION['eligibility_score']);
                
            } else {
              
                $_SESSION['form_data'] = $_POST;
            }
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
    ?>

   <?php include 'includes/header.php'?>

    <main class="main-content">
    
        <?php if (!empty($errors)): ?>
            <div class="container">
                <div class="server-error">
                    <h3>Please correct the following errors:</h3>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <script>
          
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('eligibility').style.display = 'none';
                    document.getElementById('payment').style.display = 'none';
                    document.getElementById('registrationForm').style.display = 'none';
                    document.getElementById('reviewSection').style.display = 'none';
                    document.getElementById('confirmationSection').style.display = 'block';
                    
                    // Update confirmation details
                    document.getElementById('registrationID').textContent = '<?php echo $registration_id; ?>';
                    document.getElementById('submissionDate').textContent = new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                    document.getElementById('portalEmail').textContent = '<?php echo htmlspecialchars($email); ?>';
                });
            </script>
        <?php endif; ?>

       
        <section class="registration-steps">
            <div class="container">
                <div class="steps-container">
                    <div class="step-indicator active" id="step1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Eligibility Check</div>
                    </div>
                    <div class="step-indicator" id="step2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Payment</div>
                    </div>
                    <div class="step-indicator" id="step3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Registration</div>
                    </div>
                    <div class="step-indicator" id="step4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Confirmation</div>
                    </div>
                </div>
            </div>
        </section>

       
        <section class="eligibility-section" id="eligibility">
            <div class="container">
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 25%;"></div>
                    </div>
                    <div class="progress-text">
                        <span>Step 1 of 4: Eligibility Check</span>
                        <span>25% Complete</span>
                    </div>
                </div>
                
                <div class="eligibility-container">
                    <h2>Automated Eligibility Assessment</h2>
                    <p class="form-note">You must pass the eligibility check (75% or higher) before proceeding to payment and registration. The system will automatically calculate your score based on the criteria below.</p>
                    
                    <div class="eligibility-criteria">
                        <h3>Eligibility Criteria (Weighted Scoring)</h3>
                        <ul class="criteria-list">
                            <li><strong>Age (20%):</strong> Both partners must be between 25-45 years (optimal range)</li>
                            <li><strong>Stable Income (25%):</strong> Combined household income threshold</li>
                            <li><strong>Marriage Duration (15%):</strong> Minimum 3 years of marriage</li>
                            <li><strong>Health Status (20%):</strong> No serious chronic medical conditions</li>
                            <li><strong>Residential Stability (10%):</strong> Living at current address for 2+ years</li>
                            <li><strong>Criminal Record (10%):</strong> No criminal history for either partner</li>
                        </ul>
                        <p><strong>Threshold for approval:</strong> 75% or higher</p>
                    </div>
                    
                   
                    <div class="eligibility-alert" id="eligibilityAlert">
                        <div style="display: flex; align-items: center;">
                            <i id="alertIcon"></i>
                            <div>
                                <h3 id="alertTitle"></h3>
                                <p id="alertMessage"></p>
                            </div>
                        </div>
                    </div>
                    
                
                    <div class="eligibility-score-container" id="scoreContainer" style="display: none;">
                        <div class="score-circle" id="scoreCircle">
                            <div class="score-value" id="scoreValue">0<span class="score-percentage">%</span></div>
                        </div>
                        <h3 class="score-text" id="scoreText">Eligibility Score</h3>
                        <p id="scoreMessage" class="threshold-info"></p>
                        <div id="eligibilityActionButtons" style="margin-top: 20px;"></div>
                    </div>
                    
                    <div class="eligibility-form">
                        <h3>Provide Information for Eligibility Assessment</h3>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner1AgeEligibility" class="required">Partner 1 Age</label>
                                <input type="number" id="partner1AgeEligibility" class="form-control" min="21" max="65" placeholder="Enter age" value="<?php echo isset($_SESSION['form_data']['partner1Age']) ? htmlspecialchars($_SESSION['form_data']['partner1Age']) : ''; ?>">
                                <div class="error-message" id="partner1AgeEligibilityError">Age must be between 21 and 65</div>
                            </div>
                            <div class="form-col">
                                <label for="partner2AgeEligibility" class="required">Partner 2 Age</label>
                                <input type="number" id="partner2AgeEligibility" class="form-control" min="21" max="65" placeholder="Enter age" value="<?php echo isset($_SESSION['form_data']['partner2Age']) ? htmlspecialchars($_SESSION['form_data']['partner2Age']) : ''; ?>">
                                <div class="error-message" id="partner2AgeEligibilityError">Age must be between 21 and 65</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="combinedIncome" class="required">Combined Monthly Income ($)</label>
                                <select id="combinedIncome" class="form-control">
                                    <option value="">Select Income Range</option>
                                    <option value="low" <?php echo (isset($_SESSION['form_data']['combinedIncome']) && $_SESSION['form_data']['combinedIncome'] == 'low') ? 'selected' : ''; ?>>Below $2,000</option>
                                    <option value="medium" <?php echo (isset($_SESSION['form_data']['combinedIncome']) && $_SESSION['form_data']['combinedIncome'] == 'medium') ? 'selected' : ''; ?>>$2,000 - $4,000</option>
                                    <option value="high" <?php echo (isset($_SESSION['form_data']['combinedIncome']) && $_SESSION['form_data']['combinedIncome'] == 'high') ? 'selected' : ''; ?>>$4,000 - $6,000</option>
                                    <option value="very-high" <?php echo (isset($_SESSION['form_data']['combinedIncome']) && $_SESSION['form_data']['combinedIncome'] == 'very-high') ? 'selected' : ''; ?>>Above $6,000</option>
                                </select>
                                <div class="error-message" id="combinedIncomeError">Please select income range</div>
                            </div>
                            <div class="form-col">
                                <label for="marriageYears" class="required">Years of Marriage</label>
                                <input type="number" id="marriageYears" class="form-control" min="0" max="50" placeholder="Number of years" value="<?php echo isset($_SESSION['form_data']['marriageYears']) ? htmlspecialchars($_SESSION['form_data']['marriageYears']) : ''; ?>">
                                <div class="error-message" id="marriageYearsError">Please enter years of marriage</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="healthStatus" class="required">Health Status</label>
                                <select id="healthStatus" class="form-control">
                                    <option value="">Select Health Status</option>
                                    <option value="excellent" <?php echo (isset($_SESSION['form_data']['healthStatus']) && $_SESSION['form_data']['healthStatus'] == 'excellent') ? 'selected' : ''; ?>>Excellent (no chronic conditions)</option>
                                    <option value="good" <?php echo (isset($_SESSION['form_data']['healthStatus']) && $_SESSION['form_data']['healthStatus'] == 'good') ? 'selected' : ''; ?>>Good (minor controlled conditions)</option>
                                    <option value="fair" <?php echo (isset($_SESSION['form_data']['healthStatus']) && $_SESSION['form_data']['healthStatus'] == 'fair') ? 'selected' : ''; ?>>Fair (one serious condition)</option>
                                    <option value="poor" <?php echo (isset($_SESSION['form_data']['healthStatus']) && $_SESSION['form_data']['healthStatus'] == 'poor') ? 'selected' : ''; ?>>Poor (multiple serious conditions)</option>
                                </select>
                                <div class="error-message" id="healthStatusError">Please select health status</div>
                            </div>
                            <div class="form-col">
                                <label for="residenceYears" class="required">Years at Current Address</label>
                                <input type="number" id="residenceYears" class="form-control" min="0" max="50" step="0.5" placeholder="Number of years" value="<?php echo isset($_SESSION['form_data']['residenceYears']) ? htmlspecialchars($_SESSION['form_data']['residenceYears']) : ''; ?>">
                                <div class="error-message" id="residenceYearsError">Please enter years at current address</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="criminalRecord" class="required">Criminal Record</label>
                                <select id="criminalRecord" class="form-control">
                                    <option value="">Select Option</option>
                                    <option value="none" <?php echo (isset($_SESSION['form_data']['criminalRecord']) && $_SESSION['form_data']['criminalRecord'] == 'none') ? 'selected' : ''; ?>>No criminal record for either partner</option>
                                    <option value="minor" <?php echo (isset($_SESSION['form_data']['criminalRecord']) && $_SESSION['form_data']['criminalRecord'] == 'minor') ? 'selected' : ''; ?>>Minor offense (traffic violation, etc.)</option>
                                    <option value="serious" <?php echo (isset($_SESSION['form_data']['criminalRecord']) && $_SESSION['form_data']['criminalRecord'] == 'serious') ? 'selected' : ''; ?>>Serious criminal record</option>
                                </select>
                                <div class="error-message" id="criminalRecordError">Please select criminal record status</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button class="btn btn-primary btn-large btn-block" id="checkEligibility">Check Eligibility Score</button>
                            <p class="form-note">Your eligibility will be calculated automatically. If you score 75% or higher, you can proceed to payment and registration.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <section class="payment-section" id="payment" style="display: none;">
            <div class="container">
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="paymentProgressFill"></div>
                    </div>
                    <div class="progress-text">
                        <span>Step 2 of 4: Payment</span>
                        <span>50% Complete</span>
                    </div>
                </div>
                
                <div class="payment-container">
                    <h2>Mandatory Registration Payment</h2>
                    <p class="form-note">Congratulations on passing the eligibility check! Payment is now required to proceed with registration.</p>
                    
                    <div class="payment-info">
                        <h3>Payment Details</h3>
                        <p>The registration fee is non-refundable except in cases where applicants do not meet the 75% eligibility threshold after automated scoring.</p>
                        <div class="payment-amount">LKR 2000</div>
                        <p><strong>Note:</strong> You will need your payment confirmation number to proceed with registration.</p>
                    </div>
                    
                    <h3>Select Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-method" data-method="credit-card">
                            <i class="fas fa-credit-card"></i>
                            <div>Card Payment</div>
                        </div>
                       
                        
                    </div>
                    <div class="form-group">
                        <label for="cardNumber" class="required">Card Number</label>
                        <input type="text" id="cardNumber" class="form-control" placeholder="1234 5678 9012 3456">
                        <div class="error-message" id="cardNumberError">Please enter a valid card number</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label for="expiryDate" class="required">Expiry Date</label>
                            <input type="text" id="expiryDate" class="form-control" placeholder="MM/YY">
                            <div class="error-message" id="expiryDateError">Please enter a valid expiry date</div>
                        </div>
                        <div class="form-col">
                            <label for="cvv" class="required">CVV</label>
                            <input type="text" id="cvv" class="form-control" placeholder="123">
                            <div class="error-message" id="cvvError">Please enter a valid CVV</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cardholderName" class="required">Cardholder Name</label>
                        <input type="text" id="cardholderName" class="form-control" placeholder="As printed on card">
                        <div class="error-message" id="cardholderNameError">Please enter cardholder name</div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="saveCard" name="saveCard">
                        <label for="saveCard">Save card details for future payments (securely encrypted)</label>
                    </div>
                    <div class="payment-simulation">
     
                        <div class="form-group">
                            <button class="btn btn-primary" id="simulatePayment">
                                 Pay LKR 2000
                            </button>
                           
                        </div>
                    </div>
                    
                    <div class="section-navigation">
                        <button class="btn btn-secondary" id="backToEligibility">Back to Eligibility Check</button>
                    </div>
                </div>
            </div>
        </section>

      
        <section class="form-section" id="registrationForm" style="display: none;">
            <div class="container">
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span>Step 3 of 4: Registration</span>
                        <span>75% Complete</span>
                    </div>
                </div>
                
                <div class="form-container">
                    <form id="registrationFormData" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
                        <h2>Complete Registration & Portal Creation</h2>
                        <p class="form-note">Please provide accurate information for both partners and create your portal login credentials.</p>
                        
                        <h3>Portal Login Credentials</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
                                <div class="error" id="emailError"></div>
                            </div>
                            <div class="form-col">
                                <label for="confirmEmail" class="required">Confirm Email Address</label>
                                <input type="email" id="confirmEmail" name="confirmEmail" class="form-control" placeholder="you@example.com" required value="<?php echo isset($_SESSION['form_data']['confirmEmail']) ? htmlspecialchars($_SESSION['form_data']['confirmEmail']) : ''; ?>">
                                <div class="error" id="confirmEmailError"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="password" class="required">Password</label>
                                <input type="password" id="password" name="password" class="form-control" placeholder="Create a strong password" required>
                                <div class="error" id="passwordError"></div>
                            </div>
                            <div class="form-col">
                                <label for="confirmPassword" class="required">Confirm Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Confirm your password" required>
                                <div class="error" id="confirmPasswordError"></div>
                            </div>
                        </div>
                        
                        <h3>Payment Confirmation</h3>
                        <div class="form-group">
                            <label for="paymentConfirmation" class="required">Payment Confirmation Number</label>
                            <input type="text" id="paymentConfirmation" name="paymentConfirmation" class="form-control" placeholder="Enter the confirmation number from your payment" required value="<?php echo isset($_SESSION['form_data']['paymentConfirmation']) ? htmlspecialchars($_SESSION['form_data']['paymentConfirmation']) : ''; ?>">
                            <div class="error" id="paymentConfirmationError"></div>
                        </div>
                        
                        <h3>Partner 1 Information</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner1FullName" class="required">Full Name</label>
                                <input type="text" id="partner1FullName" name="partner1FullName" class="form-control" placeholder="First and Last Name" required value="<?php echo isset($_SESSION['form_data']['partner1FullName']) ? htmlspecialchars($_SESSION['form_data']['partner1FullName']) : ''; ?>">
                                <div class="error" id="partner1FullNameError"></div>
                            </div>
                            <div class="form-col">
                                <label for="partner1Age" class="required">Age</label>
                                <input type="number" id="partner1Age" name="partner1Age" class="form-control" min="21" max="65" readonly required>
                                <div class="error" id="partner1AgeError"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner1Occupation" class="required">Occupation</label>
                                <input type="text" id="partner1Occupation" name="partner1Occupation" class="form-control" placeholder="Current occupation" required value="<?php echo isset($_SESSION['form_data']['partner1Occupation']) ? htmlspecialchars($_SESSION['form_data']['partner1Occupation']) : ''; ?>">
                                <div class="error" id="partner1OccupationError"></div>
                            </div>
                            <div class="form-col">
                                <label for="partner1ID" class="required">ID Number</label>
                                <input type="text" id="partner1ID" name="partner1ID" class="form-control" placeholder="National ID/Passport Number" required value="<?php echo isset($_SESSION['form_data']['partner1ID']) ? htmlspecialchars($_SESSION['form_data']['partner1ID']) : ''; ?>">
                                <div class="error" id="partner1IDError"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner1BloodGroup" class="required">Blood Group</label>
                                <select id="partner1BloodGroup" name="partner1BloodGroup" class="form-control" required>
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                    <option value="O+" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB+" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo (isset($_SESSION['form_data']['partner1BloodGroup']) && $_SESSION['form_data']['partner1BloodGroup'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                </select>
                                <div class="error" id="partner1BloodGroupError"></div>
                            </div>
                            <div class="form-col">
                                <label for="partner1MedicalConditions">Long-term Medical Conditions</label>
                                <textarea id="partner1MedicalConditions" name="partner1MedicalConditions" class="form-control" rows="3" placeholder="List any chronic medical conditions or allergies (if none, type 'None')"><?php echo isset($_SESSION['form_data']['partner1MedicalConditions']) ? htmlspecialchars($_SESSION['form_data']['partner1MedicalConditions']) : ''; ?></textarea>
                                <div class="error" id="partner1MedicalConditionsError"></div>
                            </div>
                        </div>
                        
                        
                        <div class="file-upload-container">
                            <h4>Partner 1 NIC Photos (Required)</h4>
                            <p class="form-note">Upload clear photos of the front and back of the National Identity Card (Max 5MB each, JPG/PNG)</p>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="file-upload-box" id="nicFront1Box">
                                        <i class="fas fa-id-card"></i>
                                        <p><strong>NIC Front Side</strong></p>
                                        <p>Click to upload front side photo</p>
                                        <input type="file" id="nicFront1" name="nicFront1" class="file-input" accept="image/*" data-partner="1" data-side="front">
                                    </div>
                                    <div class="file-preview" id="nicFront1Preview"></div>
                                </div>
                                
                                <div class="form-col">
                                    <div class="file-upload-box" id="nicBack1Box">
                                        <i class="fas fa-id-card"></i>
                                        <p><strong>NIC Back Side</strong></p>
                                        <p>Click to upload back side photo</p>
                                        <input type="file" id="nicBack1" name="nicBack1" class="file-input" accept="image/*" data-partner="1" data-side="back">
                                    </div>
                                    <div class="file-preview" id="nicBack1Preview"></div>
                                </div>
                            </div>
                        </div>
                        
                        <h3>Partner 2 Information</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner2FullName" class="required">Full Name</label>
                                <input type="text" id="partner2FullName" name="partner2FullName" class="form-control" placeholder="First and Last Name" required value="<?php echo isset($_SESSION['form_data']['partner2FullName']) ? htmlspecialchars($_SESSION['form_data']['partner2FullName']) : ''; ?>">
                                <div class="error" id="partner2FullNameError"></div>
                            </div>
                            <div class="form-col">
                                <label for="partner2Age" class="required">Age</label>
                                <input type="number" id="partner2Age" name="partner2Age" class="form-control" min="21" max="65" readonly required>
                                <div class="error" id="partner2AgeError"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner2Occupation" class="required">Occupation</label>
                                <input type="text" id="partner2Occupation" name="partner2Occupation" class="form-control" placeholder="Current occupation" required value="<?php echo isset($_SESSION['form_data']['partner2Occupation']) ? htmlspecialchars($_SESSION['form_data']['partner2Occupation']) : ''; ?>">
                                <div class="error" id="partner2OccupationError"></div>
                            </div>
                            <div class="form-col">
                                <label for="partner2ID" class="required">ID Number</label>
                                <input type="text" id="partner2ID" name="partner2ID" class="form-control" placeholder="National ID/Passport Number" required value="<?php echo isset($_SESSION['form_data']['partner2ID']) ? htmlspecialchars($_SESSION['form_data']['partner2ID']) : ''; ?>">
                                <div class="error" id="partner2IDError"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <label for="partner2BloodGroup" class="required">Blood Group</label>
                                <select id="partner2BloodGroup" name="partner2BloodGroup" class="form-control" required>
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                    <option value="O+" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                    <option value="AB+" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo (isset($_SESSION['form_data']['partner2BloodGroup']) && $_SESSION['form_data']['partner2BloodGroup'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                </select>
                                <div class="error" id="partner2BloodGroupError"></div>
                            </div>
                            <div class="form-col">
                                <label for="partner2MedicalConditions">Long-term Medical Conditions</label>
                                <textarea id="partner2MedicalConditions" name="partner2MedicalConditions" class="form-control" rows="3" placeholder="List any chronic medical conditions or allergies (if none, type 'None')"><?php echo isset($_SESSION['form_data']['partner2MedicalConditions']) ? htmlspecialchars($_SESSION['form_data']['partner2MedicalConditions']) : ''; ?></textarea>
                                <div class="error" id="partner2MedicalConditionsError"></div>
                            </div>
                        </div>
                        
                        
                        <div class="file-upload-container">
                            <h4>Partner 2 NIC Photos (Required)</h4>
                            <p class="form-note">Upload clear photos of the front and back of the National Identity Card (Max 5MB each, JPG/PNG)</p>
                            
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="file-upload-box" id="nicFront2Box">
                                        <i class="fas fa-id-card"></i>
                                        <p><strong>NIC Front Side</strong></p>
                                        <p>Click to upload front side photo</p>
                                        <input type="file" id="nicFront2" name="nicFront2" class="file-input" accept="image/*" data-partner="2" data-side="front">
                                    </div>
                                    <div class="file-preview" id="nicFront2Preview"></div>
                                </div>
                                
                                <div class="form-col">
                                    <div class="file-upload-box" id="nicBack2Box">
                                        <i class="fas fa-id-card"></i>
                                        <p><strong>NIC Back Side</strong></p>
                                        <p>Click to upload back side photo</p>
                                        <input type="file" id="nicBack2" name="nicBack2" class="file-input" accept="image/*" data-partner="2" data-side="back">
                                    </div>
                                    <div class="file-preview" id="nicBack2Preview"></div>
                                </div>
                            </div>
                        </div>
                        
                        <h3>Address Information</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="district" class="required">District</label>
                                <input type="text" id="district" name="district" class="form-control" placeholder="Your district" required value="<?php echo isset($_SESSION['form_data']['district']) ? htmlspecialchars($_SESSION['form_data']['district']) : ''; ?>">
                                <div class="error" id="districtError"></div>
                            </div>
                            <div class="form-col">
                                <label for="address" class="required">Full Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3" placeholder="House number, street, city" required><?php echo isset($_SESSION['form_data']['address']) ? htmlspecialchars($_SESSION['form_data']['address']) : ''; ?></textarea>
                                <div class="error" id="addressError"></div>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="privacyConsent" name="privacyConsent" required <?php echo (isset($_SESSION['form_data']['privacyConsent'])) ? 'checked' : ''; ?>>
                            <label for="privacyConsent" class="required">I consent to the collection and processing of my personal data for the purpose of adoption eligibility assessment. I understand this information will be stored securely and accessed only by authorized personnel.</label>
                            <div class="error" id="privacyConsentError"></div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="termsAgreement" name="termsAgreement" required <?php echo (isset($_SESSION['form_data']['termsAgreement'])) ? 'checked' : ''; ?>>
                            <label for="termsAgreement" class="required">I agree to the terms and conditions of the Family Bridge adoption platform and understand that my information will be assessed by the automated eligibility scoring system.</label>
                            <div class="error" id="termsAgreementError"></div>
                        </div>
                        
                        <div class="section-navigation">
                            <button type="button" class="btn btn-secondary" id="backToPaymentFromReg">Back to Payment</button>
                            <button type="button" class="btn btn-primary" id="proceedToReview">Proceed to Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

      
        <section class="review-section" id="reviewSection" style="display: none;">
            <div class="container">
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span>Step 4 of 4: Review</span>
                        <span>85% Complete</span>
                    </div>
                </div>
                
                <div class="review-container">
                    <h2>Review Your Registration Information</h2>
                    <p class="form-note">Please review all information carefully before submission. Once submitted, you cannot make changes until eligibility assessment is complete.</p>
                    
                    <div class="review-info">
                        <h3>Portal Credentials</h3>
                        <div class="review-row">
                            <div class="review-label">Email Address</div>
                            <div class="review-value" id="reviewEmail">user@example.com</div>
                        </div>
                        
                        <h3>Payment Information</h3>
                        <div class="review-row">
                            <div class="review-label">Payment Confirmation Number</div>
                            <div class="review-value" id="reviewConfirmationNumber">ABC123XYZ</div>
                        </div>
                        
                        <h3>Eligibility Score</h3>
                        <div class="review-row">
                            <div class="review-label">Automated Eligibility Score</div>
                            <div class="review-value" id="reviewEligibilityScore">85%</div>
                        </div>
                        
                        <h3>Partner 1 Details</h3>
                        <div class="review-row">
                            <div class="review-label">Full Name</div>
                            <div class="review-value" id="reviewPartner1Name">John Doe</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Age</div>
                            <div class="review-value" id="reviewPartner1Age">32</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Occupation</div>
                            <div class="review-value" id="reviewPartner1Occupation">Software Engineer</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">ID Number</div>
                            <div class="review-value" id="reviewPartner1ID">ID123456789</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Blood Group</div>
                            <div class="review-value" id="reviewPartner1Blood">O+</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Medical Conditions</div>
                            <div class="review-value" id="reviewPartner1Medical">None</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">NIC Photos</div>
                            <div class="review-value">Front & Back Uploaded</div>
                        </div>
                        
                        <h3>Partner 2 Details</h3>
                        <div class="review-row">
                            <div class="review-label">Full Name</div>
                            <div class="review-value" id="reviewPartner2Name">Jane Doe</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Age</div>
                            <div class="review-value" id="reviewPartner2Age">30</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Occupation</div>
                            <div class="review-value" id="reviewPartner2Occupation">Teacher</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">ID Number</div>
                            <div class="review-value" id="reviewPartner2ID">ID987654321</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Blood Group</div>
                            <div class="review-value" id="reviewPartner2Blood">A+</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Medical Conditions</div>
                            <div class="review-value" id="reviewPartner2Medical">None</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">NIC Photos</div>
                            <div class="review-value">Front & Back Uploaded</div>
                        </div>
                        
                        <h3>Address Information</h3>
                        <div class="review-row">
                            <div class="review-label">District</div>
                            <div class="review-value" id="reviewDistrict">Central District</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Address</div>
                            <div class="review-value" id="reviewAddress">123 Main Street, Springfield</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="finalConfirmation" name="finalConfirmation" required>
                            <label for="finalConfirmation" class="required">I confirm that all information provided is accurate to the best of my knowledge. I understand that providing false information may result in disqualification from the adoption process.</label>
                            <div class="error" id="finalConfirmationError"></div>
                        </div>
                    </div>
                    
                    <div class="section-navigation">
                        <button class="btn btn-secondary" id="backToRegistrationFromReview">Back to Registration</button>
                        <button class="btn btn-primary" id="submitRegistration">Submit Registration</button>
                    </div>
                </div>
            </div>
        </section>

    
        <section class="confirmation-section" id="confirmationSection" style="display: none;">
            <div class="container">
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text">
                        <span>Step 5 of 5: Confirmation</span>
                        <span>100% Complete</span>
                    </div>
                </div>
                
                <div class="confirmation-container">
                    <div class="confirmation-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    
                    <h2>Registration Submitted Successfully!</h2>
                    
                    <div class="review-info">
                        <div class="review-row">
                            <div class="review-label">Registration ID</div>
                            <div class="review-value" id="registrationID">FB-2023-789456</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Submission Date</div>
                            <div class="review-value" id="submissionDate">October 26, 2023</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Portal Email</div>
                            <div class="review-value" id="portalEmail">user@example.com</div>
                        </div>
                        <div class="review-row">
                            <div class="review-label">Eligibility Assessment</div>
                            <div class="review-value">Pending Final Verification</div>
                        </div>
                    </div>
                    
                    <p>Your registration has been received and your portal account has been created. You can now log in to track your application status.</p>
                    
                    <div class="payment-info">
                        <h3>Next Steps</h3>
                        <ol style="text-align: left; margin: 20px 0; padding-left: 20px;">
                            <li><strong>Portal Access:</strong> Use your email and password to log in to your account</li>
                            <li><strong>Final Verification:</strong> Your application will undergo final verification by the Chief Officer</li>
                            <li><strong>Child Profiles:</strong> Once approved, you will gain access to child profiles in the portal</li>
                            <li><strong>Notifications:</strong> You will receive email notifications when suitable children become available</li>
                            <li><strong>Single Vote:</strong> You may cast a single vote for a child you wish to adopt</li>
                        </ol>
                        
                        <p><strong>Note:</strong> All sensitive data including NIC photos is securely stored and can only be accessed by the Chief Officer of the adoption institution.</p>
                    </div>
                    
                    <div class="form-group" style="margin-top: 40px;">
                        <a href="login.php" class="btn btn-primary btn-large">
                            <i class="fas fa-sign-in-alt"></i> Go to Your Portal
                        </a>
                        <a href="index.php" class="btn btn-secondary btn-large" style="margin-left: 15px;">
                            Return to Homepage
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php 

    if (isset($_SESSION['form_data'])) {
        unset($_SESSION['form_data']);
    }
    include 'includes/footer.php'; 
    ?>

    <script>

document.addEventListener('DOMContentLoaded', function() {
   
    const checkEligibilityBtn = document.getElementById('checkEligibility');
    if (checkEligibilityBtn) {
        checkEligibilityBtn.addEventListener('click', function() {
          
            const partner1Age = parseInt(document.getElementById('partner1AgeEligibility').value) || 0;
            const partner2Age = parseInt(document.getElementById('partner2AgeEligibility').value) || 0;
            const combinedIncome = document.getElementById('combinedIncome').value;
            const marriageYears = parseInt(document.getElementById('marriageYears').value) || 0;
            const healthStatus = document.getElementById('healthStatus').value;
            const residenceYears = parseFloat(document.getElementById('residenceYears').value) || 0;
            const criminalRecord = document.getElementById('criminalRecord').value;
            
        
            let totalScore = 0;
            
           
            const ageScore = calculateAgeScore(partner1Age, partner2Age);
            totalScore += ageScore * 0.20;
            
        
            const incomeScore = calculateIncomeScore(combinedIncome);
            totalScore += incomeScore * 0.25;
            
           
            const marriageScore = calculateMarriageScore(marriageYears);
            totalScore += marriageScore * 0.15;
            
           
            const healthScore = calculateHealthScore(healthStatus);
            totalScore += healthScore * 0.20;
            
          
            const residenceScore = calculateResidenceScore(residenceYears);
            totalScore += residenceScore * 0.10;
            
            
            const criminalScore = calculateCriminalScore(criminalRecord);
            totalScore += criminalScore * 0.10;
            
           
            const finalScore = Math.round(totalScore * 100);
            
            
            fetch('store_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `score=${finalScore}`
            });
            
         
            displayEligibilityResult(finalScore);
            
          
            document.getElementById('partner1Age').value = partner1Age;
            document.getElementById('partner2Age').value = partner2Age;
        });
    }
    
 
    const simulatePaymentBtn = document.getElementById('simulatePayment');
    if (simulatePaymentBtn) {
        simulatePaymentBtn.addEventListener('click', function() {
            
            const confirmationNumber = 'PAY-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9).toUpperCase();
            
            
            alert('Payment simulated successfully!\nConfirmation Number: ' + confirmationNumber);
            
           
            document.getElementById('paymentConfirmation').value = confirmationNumber;
            
            
            setTimeout(() => {
                document.getElementById('payment').style.display = 'none';
                document.getElementById('registrationForm').style.display = 'block';
                updateStepIndicator(3);
            }, 1000);
        });
    }
    
    
    const backToEligibilityBtn = document.getElementById('backToEligibility');
    if (backToEligibilityBtn) {
        backToEligibilityBtn.addEventListener('click', function() {
            document.getElementById('payment').style.display = 'none';
            document.getElementById('eligibility').style.display = 'block';
            updateStepIndicator(1);
        });
    }
    
    const backToPaymentFromRegBtn = document.getElementById('backToPaymentFromReg');
    if (backToPaymentFromRegBtn) {
        backToPaymentFromRegBtn.addEventListener('click', function() {
            document.getElementById('registrationForm').style.display = 'none';
            document.getElementById('payment').style.display = 'block';
            updateStepIndicator(2);
        });
    }
    
    const proceedToReviewBtn = document.getElementById('proceedToReview');
    if (proceedToReviewBtn) {
        proceedToReviewBtn.addEventListener('click', function() {
            if (validateRegistrationForm()) {
                populateReviewSection();
                document.getElementById('registrationForm').style.display = 'none';
                document.getElementById('reviewSection').style.display = 'block';
                updateStepIndicator(4);
            }
        });
    }
    
    const backToRegistrationFromReviewBtn = document.getElementById('backToRegistrationFromReview');
    if (backToRegistrationFromReviewBtn) {
        backToRegistrationFromReviewBtn.addEventListener('click', function() {
            document.getElementById('reviewSection').style.display = 'none';
            document.getElementById('registrationForm').style.display = 'block';
            updateStepIndicator(3);
        });
    }
    
    const submitRegistrationBtn = document.getElementById('submitRegistration');
    if (submitRegistrationBtn) {
        submitRegistrationBtn.addEventListener('click', function() {
            const finalConfirmation = document.getElementById('finalConfirmation');
            if (!finalConfirmation.checked) {
                showError(finalConfirmationError, 'Please confirm that all information is accurate');
                return;
            }
            
        
            document.getElementById('registrationFormData').submit();
        });
    }
    
   
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewId = e.target.id + 'Preview';
            const preview = document.getElementById(previewId);
            
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                    return;
                }
                
                if (!file.type.match('image.*')) {
                    alert('Please select an image file');
                    e.target.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <div class="preview-container">
                            <img src="${e.target.result}" alt="Preview" style="max-width: 100px; max-height: 100px;">
                            <p>${file.name}</p>
                            <button type="button" class="remove-file" data-input="${e.target.id}">Remove</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Remove file
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-file')) {
            const inputId = e.target.getAttribute('data-input');
            document.getElementById(inputId).value = '';
            e.target.closest('.preview-container').remove();
        }
    });
    
    // Helper functions
    function calculateAgeScore(age1, age2) {
        const avgAge = (age1 + age2) / 2;
        if (avgAge >= 25 && avgAge <= 45) return 1.0;
        if (avgAge >= 22 && avgAge <= 50) return 0.7;
        if (avgAge >= 21 && avgAge <= 55) return 0.5;
        return 0.2;
    }
    
    function calculateIncomeScore(income) {
        switch(income) {
            case 'very-high': return 1.0;
            case 'high': return 0.8;
            case 'medium': return 0.6;
            case 'low': return 0.3;
            default: return 0;
        }
    }
    
    function calculateMarriageScore(years) {
        if (years >= 5) return 1.0;
        if (years >= 3) return 0.8;
        if (years >= 1) return 0.5;
        return 0.2;
    }
    
    function calculateHealthScore(health) {
        switch(health) {
            case 'excellent': return 1.0;
            case 'good': return 0.8;
            case 'fair': return 0.5;
            case 'poor': return 0.2;
            default: return 0;
        }
    }
    
    function calculateResidenceScore(years) {
        if (years >= 5) return 1.0;
        if (years >= 3) return 0.8;
        if (years >= 2) return 0.6;
        if (years >= 1) return 0.4;
        return 0.2;
    }
    
    function calculateCriminalScore(record) {
        switch(record) {
            case 'none': return 1.0;
            case 'minor': return 0.5;
            case 'serious': return 0;
            default: return 0;
        }
    }
    
    function displayEligibilityResult(score) {
        const scoreContainer = document.getElementById('scoreContainer');
        const scoreValue = document.getElementById('scoreValue');
        const scoreText = document.getElementById('scoreText');
        const scoreMessage = document.getElementById('scoreMessage');
        const alertTitle = document.getElementById('alertTitle');
        const alertMessage = document.getElementById('alertMessage');
        const alertIcon = document.getElementById('alertIcon');
        const eligibilityAlert = document.getElementById('eligibilityAlert');
        const actionButtons = document.getElementById('eligibilityActionButtons');
        
        scoreValue.textContent = score;
        scoreContainer.style.display = 'block';
        
        
        const scoreCircle = document.getElementById('scoreCircle');
        if (score >= 75) {
            scoreCircle.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
            scoreText.textContent = 'Eligibility Passed!';
            scoreMessage.textContent = 'Congratulations! You meet the eligibility criteria.';
            scoreMessage.style.color = '#28a745';
            
           
            eligibilityAlert.style.backgroundColor = '#d4edda';
            eligibilityAlert.style.borderColor = '#c3e6cb';
            eligibilityAlert.style.color = '#155724';
            alertIcon.className = 'fas fa-check-circle';
            alertIcon.style.color = '#28a745';
            alertIcon.style.marginRight = '15px';
            alertIcon.style.fontSize = '2rem';
            alertTitle.textContent = 'Eligibility Check Passed';
            alertMessage.textContent = `You scored ${score}% which meets the 75% threshold. You may proceed to payment.`;
            eligibilityAlert.style.display = 'block';
            
           
            actionButtons.innerHTML = `
                <button class="btn btn-primary" id="proceedToPayment">
                    Proceed to Payment
                </button>
            `;
            
            // Add event listener to proceed button
            setTimeout(() => {
                const proceedToPaymentBtn = document.getElementById('proceedToPayment');
                if (proceedToPaymentBtn) {
                    proceedToPaymentBtn.addEventListener('click', function() {
                        document.getElementById('eligibility').style.display = 'none';
                        document.getElementById('payment').style.display = 'block';
                        updateStepIndicator(2);
                    });
                }
            }, 100);
            
        } else {
            scoreCircle.style.background = 'linear-gradient(135deg, #dc3545, #e4606d)';
            scoreText.textContent = 'Eligibility Failed';
            scoreMessage.textContent = 'Sorry, you do not meet the minimum 75% threshold.';
            scoreMessage.style.color = '#dc3545';
            
          
            eligibilityAlert.style.backgroundColor = '#f8d7da';
            eligibilityAlert.style.borderColor = '#f5c6cb';
            eligibilityAlert.style.color = '#721c24';
            alertIcon.className = 'fas fa-exclamation-circle';
            alertIcon.style.color = '#dc3545';
            alertIcon.style.marginRight = '15px';
            alertIcon.style.fontSize = '2rem';
            alertTitle.textContent = 'Eligibility Check Failed';
            alertMessage.textContent = `You scored ${score}% which is below the 75% threshold. Please review your information or contact support.`;
            eligibilityAlert.style.display = 'block';
            
         
            actionButtons.innerHTML = `
                <button class="btn btn-secondary" onclick="location.reload()">
                    Retry Eligibility Check
                </button>
            `;
        }
    }
    
    function updateStepIndicator(step) {
       
        document.querySelectorAll('.step-indicator').forEach(indicator => {
            indicator.classList.remove('active');
        });
        
     
        document.getElementById(`step${step}`).classList.add('active');
        
       
        const progressFill = document.getElementById('progressFill');
        if (progressFill) {
            const width = (step - 1) * 25 + 25;
            progressFill.style.width = `${width}%`;
        }
    }
    
    function validateRegistrationForm() {
        let isValid = true;
        
    
        const email = document.getElementById('email');
        const emailError = document.getElementById('emailError');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!email.value.trim()) {
            showError(emailError, 'Email is required');
            email.classList.add('error-border');
            isValid = false;
        } else if (!emailRegex.test(email.value)) {
            showError(emailError, 'Please enter a valid email address');
            email.classList.add('error-border');
            isValid = false;
        } else {
            clearError(emailError);
            email.classList.remove('error-border');
        }
        
        
        const confirmEmail = document.getElementById('confirmEmail');
        const confirmEmailError = document.getElementById('confirmEmailError');
        
        if (!confirmEmail.value.trim()) {
            showError(confirmEmailError, 'Please confirm your email');
            confirmEmail.classList.add('error-border');
            isValid = false;
        } else if (email.value !== confirmEmail.value) {
            showError(confirmEmailError, 'Emails do not match');
            confirmEmail.classList.add('error-border');
            isValid = false;
        } else {
            clearError(confirmEmailError);
            confirmEmail.classList.remove('error-border');
        }
        
        
        const password = document.getElementById('password');
        const passwordError = document.getElementById('passwordError');
        
        if (!password.value.trim()) {
            showError(passwordError, 'Password is required');
            password.classList.add('error-border');
            isValid = false;
        } else if (password.value.length < 8) {
            showError(passwordError, 'Password must be at least 8 characters');
            password.classList.add('error-border');
            isValid = false;
        } else {
            clearError(passwordError);
            password.classList.remove('error-border');
        }
        
        
        const confirmPassword = document.getElementById('confirmPassword');
        const confirmPasswordError = document.getElementById('confirmPasswordError');
        
        if (!confirmPassword.value.trim()) {
            showError(confirmPasswordError, 'Please confirm your password');
            confirmPassword.classList.add('error-border');
            isValid = false;
        } else if (password.value !== confirmPassword.value) {
            showError(confirmPasswordError, 'Passwords do not match');
            confirmPassword.classList.add('error-border');
            isValid = false;
        } else {
            clearError(confirmPasswordError);
            confirmPassword.classList.remove('error-border');
        }
        
       
        const requiredFields = [
            'partner1FullName', 'partner1Occupation', 'partner1ID', 'partner1BloodGroup',
            'partner2FullName', 'partner2Occupation', 'partner2ID', 'partner2BloodGroup',
            'district', 'address', 'paymentConfirmation'
        ];
        
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            const error = document.getElementById(fieldId + 'Error');
            
            if (!field.value.trim()) {
                showError(error, 'This field is required');
                field.classList.add('error-border');
                isValid = false;
            } else {
                clearError(error);
                field.classList.remove('error-border');
            }
        });
        
      
        const privacyConsent = document.getElementById('privacyConsent');
        const privacyConsentError = document.getElementById('privacyConsentError');
        
        if (!privacyConsent.checked) {
            showError(privacyConsentError, 'Privacy consent is required');
            isValid = false;
        } else {
            clearError(privacyConsentError);
        }
        
        const termsAgreement = document.getElementById('termsAgreement');
        const termsAgreementError = document.getElementById('termsAgreementError');
        
        if (!termsAgreement.checked) {
            showError(termsAgreementError, 'Terms agreement is required');
            isValid = false;
        } else {
            clearError(termsAgreementError);
        }
        
        return isValid;
    }
    
    function showError(errorElement, message) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }
    
    function clearError(errorElement) {
        errorElement.textContent = '';
        errorElement.classList.remove('show');
    }
    
    function populateReviewSection() {
       
        document.getElementById('reviewEmail').textContent = document.getElementById('email').value;
        document.getElementById('reviewConfirmationNumber').textContent = document.getElementById('paymentConfirmation').value;
        document.getElementById('reviewPartner1Name').textContent = document.getElementById('partner1FullName').value;
        document.getElementById('reviewPartner1Age').textContent = document.getElementById('partner1Age').value;
        document.getElementById('reviewPartner1Occupation').textContent = document.getElementById('partner1Occupation').value;
        document.getElementById('reviewPartner1ID').textContent = document.getElementById('partner1ID').value;
        document.getElementById('reviewPartner1Blood').textContent = document.getElementById('partner1BloodGroup').value;
        document.getElementById('reviewPartner1Medical').textContent = document.getElementById('partner1MedicalConditions').value || 'None';
        document.getElementById('reviewPartner2Name').textContent = document.getElementById('partner2FullName').value;
        document.getElementById('reviewPartner2Age').textContent = document.getElementById('partner2Age').value;
        document.getElementById('reviewPartner2Occupation').textContent = document.getElementById('partner2Occupation').value;
        document.getElementById('reviewPartner2ID').textContent = document.getElementById('partner2ID').value;
        document.getElementById('reviewPartner2Blood').textContent = document.getElementById('partner2BloodGroup').value;
        document.getElementById('reviewPartner2Medical').textContent = document.getElementById('partner2MedicalConditions').value || 'None';
        document.getElementById('reviewDistrict').textContent = document.getElementById('district').value;
        document.getElementById('reviewAddress').textContent = document.getElementById('address').value;
        
      
        fetch('get_score.php')
            .then(response => response.text())
            .then(score => {
                document.getElementById('reviewEligibilityScore').textContent = score + '%';
            });
    }
});
</script>
</body>
</html>