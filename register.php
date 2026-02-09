<?php
// registration.php  (FULL PAGE - NO includes/header/footer files)
// ✅ Keeps ALL your existing ids/classes unchanged (so your CSS works)
// ✅ Stores eligibility values from Step 1 (hidden inputs)
// ✅ Also validates eligibility on SERVER (cannot bypass)
// ✅ Fixes login "account not active" by inserting user as active (status / is_active fallback)

session_start();

$host = 'localhost';
$dbname = 'family_bridge';
$dbuser = 'root';
$dbpass = '';

$errors = [];
$success = false;
$registration_id = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Portal
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $confirmEmail = filter_var($_POST['confirmEmail'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Eligibility values (from hidden inputs filled by eligibility JS)
    $marriageYears = filter_var($_POST['marriage_years'] ?? 0, FILTER_VALIDATE_INT);
    $monthlyIncome = filter_var($_POST['monthly_income'] ?? 0, FILTER_VALIDATE_INT);

    $eligibilityResult = strtolower(trim((string)($_POST['eligibility_result'] ?? 'pending')));
    $eligibilityStatus = strtolower(trim((string)($_POST['eligibility_status'] ?? 'pending')));

    // Husband
    $husbandName = htmlspecialchars($_POST['husbandFullName'] ?? '', ENT_QUOTES, 'UTF-8');
    $husbandAge  = filter_var($_POST['husbandAge'] ?? 0, FILTER_VALIDATE_INT);
    $husbandOccupation = htmlspecialchars($_POST['husbandOccupation'] ?? '', ENT_QUOTES, 'UTF-8');
    $husbandNIC = htmlspecialchars($_POST['husbandNIC'] ?? '', ENT_QUOTES, 'UTF-8');

    // Wife
    $wifeName = htmlspecialchars($_POST['wifeFullName'] ?? '', ENT_QUOTES, 'UTF-8');
    $wifeAge  = filter_var($_POST['wifeAge'] ?? 0, FILTER_VALIDATE_INT);
    $wifeOccupation = htmlspecialchars($_POST['wifeOccupation'] ?? '', ENT_QUOTES, 'UTF-8');
    $wifeNIC = htmlspecialchars($_POST['wifeNIC'] ?? '', ENT_QUOTES, 'UTF-8');

    // Address
    $district = htmlspecialchars($_POST['district'] ?? '', ENT_QUOTES, 'UTF-8');
    $address  = htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8');

    // Agreements
    $privacyConsent = isset($_POST['privacyConsent']) ? 1 : 0;
    $termsAgreement = isset($_POST['termsAgreement']) ? 1 : 0;
    $finalConfirmation = isset($_POST['finalConfirmation']) ? 1 : 0;

    // -------------------------
    // ✅ SERVER-SIDE ELIGIBILITY (cannot bypass)
    // Rules: age 21-45, marriage >=3, income >=60000
    // -------------------------
    $eligible_server = (
      $husbandAge >= 21 && $husbandAge <= 45 &&
      $wifeAge >= 21 && $wifeAge <= 45 &&
      $marriageYears >= 3 &&
      $monthlyIncome >= 60000
    );

    // Validate hidden eligibility (must have clicked "Check Eligibility")
    if ($eligibilityStatus !== 'checked' || $eligibilityResult !== 'eligible') {
      $errors[] = "Please complete the Eligibility Check and ensure you are eligible before registering.";
    }

    // Server result must be eligible too
    if (!$eligible_server) {
      $errors[] = "You are not eligible to register. Eligibility rules not satisfied (Age 21–45, Marriage ≥ 3, Income ≥ 60000).";
    }

    // Validations
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
    if ($email !== $confirmEmail) $errors[] = "Email addresses do not match.";

    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match.";

    if (empty($husbandName) || empty($wifeName)) $errors[] = "Both Husband and Wife full names are required.";

    if (empty($husbandOccupation) || empty($wifeOccupation)) $errors[] = "Both occupations are required.";
    if (empty($husbandNIC) || empty($wifeNIC)) $errors[] = "Both NIC numbers are required.";

    if (empty($district) || empty($address)) $errors[] = "District and Address are required.";
    if ($privacyConsent !== 1 || $termsAgreement !== 1 || $finalConfirmation !== 1) $errors[] = "All consents and final confirmation must be accepted.";

    // Check email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) $errors[] = "This email is already registered.";

    if (empty($errors)) {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $registration_id = 'FB-' . date('Y') . '-' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);

      $pdo->beginTransaction();

      // -------------------------
      // ✅ INSERT USERS AS ACTIVE (fix login issue)
      // Supports: status OR is_active OR fallback schema
      // -------------------------
      $user_id = 0;

      try {
        // Option A: users.status column
        $stmt = $pdo->prepare("
          INSERT INTO users (email, password, registration_id, status, created_at)
          VALUES (?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$email, $hashedPassword, $registration_id]);
        $user_id = (int)$pdo->lastInsertId();
      } catch (PDOException $ex1) {
        try {
          // Option B: users.is_active column
          $stmt = $pdo->prepare("
            INSERT INTO users (email, password, registration_id, is_active, created_at)
            VALUES (?, ?, ?, 1, NOW())
          ");
          $stmt->execute([$email, $hashedPassword, $registration_id]);
          $user_id = (int)$pdo->lastInsertId();
        } catch (PDOException $ex2) {
          // Option C: fallback
          $stmt = $pdo->prepare("
            INSERT INTO users (email, password, registration_id, created_at)
            VALUES (?, ?, ?, NOW())
          ");
          $stmt->execute([$email, $hashedPassword, $registration_id]);
          $user_id = (int)$pdo->lastInsertId();
        }
      }

   
      $eligibility_status_db = 'checked';
      $eligibility_result_db = 'eligible';
      $eligibility_checked_at = date('Y-m-d H:i:s');

      $stmt = $pdo->prepare("
  INSERT INTO applications (
    user_id, registration_id,
    husband_name, husband_age, husband_occupation, husband_id,
    wife_name, wife_age, wife_occupation, wife_id,
    district, address,
    privacy_consent, terms_agreement,
    marriage_years, monthly_income,
    eligibility_status, eligibility_result, eligibility_checked_at,
    status, created_at
  ) VALUES (
    ?, ?,
    ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?,
    ?, ?,
    ?, ?,
    ?, ?, ?,
    'approved', NOW()
  )
");


      $stmt->execute([
        $user_id, $registration_id,
        $husbandName, $husbandAge, $husbandOccupation, $husbandNIC,
        $wifeName, $wifeAge, $wifeOccupation, $wifeNIC,
        $district, $address,
        $privacyConsent, $termsAgreement,
        $marriageYears, $monthlyIncome,
        $eligibility_status_db, $eligibility_result_db, $eligibility_checked_at
      ]);

      $pdo->commit();
      $success = true;

    } else {
      $_SESSION['form_data'] = $_POST;
    }

  } catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $errors[] = "Database error: " . $e->getMessage();
  } catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $errors[] = "Error: " . $e->getMessage();
  }
}
?>
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
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
  </style>
</head>
<body>

<?php include '<includes/header.php'?>

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
      document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('eligibilitySection').style.display = 'none';
        document.getElementById('registrationSection').style.display = 'none';
        document.getElementById('confirmSection').style.display = 'none';
        document.getElementById('paymentSection').style.display = 'none';
        document.getElementById('confirmationSection').style.display = 'block';

        document.getElementById('registrationID').textContent = '<?php echo $registration_id; ?>';
        document.getElementById('portalEmail').textContent = '<?php echo htmlspecialchars($email); ?>';
        document.getElementById('submissionDate').textContent = new Date().toLocaleDateString('en-US', {
          year: 'numeric', month: 'long', day: 'numeric'
        });
      });
    </script>
  <?php endif; ?>

  <!-- STEP INDICATOR -->
  <section class="registration-steps">
    <div class="container">
      <div class="steps-container">
        <div class="step-indicator active" id="step1">
          <div class="step-circle">1</div>
          <div class="step-label">Eligibility</div>
        </div>
        <div class="step-indicator" id="step2">
          <div class="step-circle">2</div>
          <div class="step-label">Registration</div>
        </div>
        <div class="step-indicator" id="step3">
          <div class="step-circle">3</div>
          <div class="step-label">Confirm</div>
        </div>
        <div class="step-indicator" id="step4">
          <div class="step-circle">4</div>
          <div class="step-label">Payment</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ELIGIBILITY CHECK (STEP 1) -->
  <section class="eligibility-section" id="eligibilitySection">
    <div class="container">
      <div class="progress-bar-container">
        <div class="progress-bar">
          <div class="progress-fill" id="progressFill" style="width:25%;"></div>
        </div>
        <div class="progress-text">
          <span>Step 1 of 4: Eligibility Check</span>
          <span>25% Complete</span>
        </div>
      </div>

      <div class="eligibility-container">
        <h2>Eligibility Check</h2>
        <p class="form-note">You must pass this eligibility check to continue registration.</p>

        <div class="form-row">
          <div class="form-col">
            <label class="required">Husband Age (21–45)</label>
            <input type="number" id="eligHusbandAge" class="form-control" min="21" max="45" required>
            <div class="error" id="eligHusbandAgeError"></div>
          </div>

          <div class="form-col">
            <label class="required">Wife Age (21–45)</label>
            <input type="number" id="eligWifeAge" class="form-control" min="21" max="45" required>
            <div class="error" id="eligWifeAgeError"></div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col">
            <label class="required">Marriage Duration (Years) (≥ 3)</label>
            <input type="number" id="marriageYears" class="form-control" min="0" required>
            <div class="error" id="marriageYearsError"></div>
          </div>

          <div class="form-col">
            <label class="required">Combined Monthly Income (LKR) (≥ 60000)</label>
            <input type="number" id="monthlyIncome" class="form-control" min="0" required>
            <div class="error" id="monthlyIncomeError"></div>
          </div>
        </div>

        <div id="eligibilityResultBox" style="display:none;margin-top:15px;"></div>

        <div class="form-group" style="margin-top:18px;">
          <button class="btn btn-primary btn-large btn-block" id="checkEligibilityBtn">Check Eligibility</button>
        </div>

        <div class="form-group" style="margin-top:10px;">
          <button class="btn btn-secondary btn-large btn-block" id="continueToRegistrationBtn" style="display:none;">
            Continue to Registration
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- REGISTRATION (STEP 2) -->
  <section class="form-section" id="registrationSection" style="display:none;">
    <div class="container">
      <div class="progress-bar-container">
        <div class="progress-bar">
          <div class="progress-fill" style="width:50%;"></div>
        </div>
        <div class="progress-text">
          <span>Step 2 of 4: Registration</span>
          <span>50% Complete</span>
        </div>
      </div>

      <div class="form-container">
        <form id="registrationFormData" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>

          <!-- ✅ hidden eligibility fields MUST be inside the form -->
          <input type="hidden" name="marriage_years" id="hiddenMarriageYears">
          <input type="hidden" name="monthly_income" id="hiddenMonthlyIncome">
          <input type="hidden" name="eligibility_result" id="hiddenEligibilityResult">
          <input type="hidden" name="eligibility_status" id="hiddenEligibilityStatus">

          <h2>Complete Registration & Portal Creation</h2>
          <p class="form-note">Provide accurate information and create your portal credentials.</p>

          <h3>Portal Login Credentials</h3>
          <div class="form-row">
            <div class="form-col">
              <label class="required" for="email">Email Address</label>
              <input type="email" id="email" name="email" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['email']) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>">
              <div class="error" id="emailError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="confirmEmail">Confirm Email</label>
              <input type="email" id="confirmEmail" name="confirmEmail" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['confirmEmail']) ? htmlspecialchars($_SESSION['form_data']['confirmEmail']) : ''; ?>">
              <div class="error" id="confirmEmailError"></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="required" for="password">Password</label>
              <input type="password" id="password" name="password" class="form-control" required>
              <div class="error" id="passwordError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="confirmPassword">Confirm Password</label>
              <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
              <div class="error" id="confirmPasswordError"></div>
            </div>
          </div>

          <h3>Husband’s Information</h3>
          <div class="form-row">
            <div class="form-col">
              <label class="required" for="husbandFullName">Full Name</label>
              <input type="text" id="husbandFullName" name="husbandFullName" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['husbandFullName']) ? htmlspecialchars($_SESSION['form_data']['husbandFullName']) : ''; ?>">
              <div class="error" id="husbandFullNameError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="husbandAge">Age</label>
              <input type="number" id="husbandAge" name="husbandAge" class="form-control" min="21" max="45" required
                     value="<?php echo isset($_SESSION['form_data']['husbandAge']) ? htmlspecialchars($_SESSION['form_data']['husbandAge']) : ''; ?>">
              <div class="error" id="husbandAgeError"></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="required" for="husbandOccupation">Occupation</label>
              <input type="text" id="husbandOccupation" name="husbandOccupation" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['husbandOccupation']) ? htmlspecialchars($_SESSION['form_data']['husbandOccupation']) : ''; ?>">
              <div class="error" id="husbandOccupationError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="husbandNIC">NIC Number</label>
              <input type="text" id="husbandNIC" name="husbandNIC" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['husbandNIC']) ? htmlspecialchars($_SESSION['form_data']['husbandNIC']) : ''; ?>">
              <div class="error" id="husbandNICError"></div>
            </div>
          </div>

          <h3>Wife’s Information</h3>
          <div class="form-row">
            <div class="form-col">
              <label class="required" for="wifeFullName">Full Name</label>
              <input type="text" id="wifeFullName" name="wifeFullName" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['wifeFullName']) ? htmlspecialchars($_SESSION['form_data']['wifeFullName']) : ''; ?>">
              <div class="error" id="wifeFullNameError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="wifeAge">Age</label>
              <input type="number" id="wifeAge" name="wifeAge" class="form-control" min="21" max="45" required
                     value="<?php echo isset($_SESSION['form_data']['wifeAge']) ? htmlspecialchars($_SESSION['form_data']['wifeAge']) : ''; ?>">
              <div class="error" id="wifeAgeError"></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="required" for="wifeOccupation">Occupation</label>
              <input type="text" id="wifeOccupation" name="wifeOccupation" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['wifeOccupation']) ? htmlspecialchars($_SESSION['form_data']['wifeOccupation']) : ''; ?>">
              <div class="error" id="wifeOccupationError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="wifeNIC">NIC Number</label>
              <input type="text" id="wifeNIC" name="wifeNIC" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['wifeNIC']) ? htmlspecialchars($_SESSION['form_data']['wifeNIC']) : ''; ?>">
              <div class="error" id="wifeNICError"></div>
            </div>
          </div>

          <h3>Address Information</h3>
          <div class="form-row">
            <div class="form-col">
              <label class="required" for="district">District</label>
              <input type="text" id="district" name="district" class="form-control" required
                     value="<?php echo isset($_SESSION['form_data']['district']) ? htmlspecialchars($_SESSION['form_data']['district']) : ''; ?>">
              <div class="error" id="districtError"></div>
            </div>
            <div class="form-col">
              <label class="required" for="address">Full Address</label>
              <textarea id="address" name="address" class="form-control" rows="3" required><?php echo isset($_SESSION['form_data']['address']) ? htmlspecialchars($_SESSION['form_data']['address']) : ''; ?></textarea>
              <div class="error" id="addressError"></div>
            </div>
          </div>

          <div class="checkbox-group">
            <input type="checkbox" id="privacyConsent" name="privacyConsent" required <?php echo (isset($_SESSION['form_data']['privacyConsent'])) ? 'checked' : ''; ?>>
            <label class="required" for="privacyConsent">I consent to the collection and processing of my personal data.</label>
            <div class="error" id="privacyConsentError"></div>
          </div>

          <div class="checkbox-group">
            <input type="checkbox" id="termsAgreement" name="termsAgreement" required <?php echo (isset($_SESSION['form_data']['termsAgreement'])) ? 'checked' : ''; ?>>
            <label class="required" for="termsAgreement">I agree to the terms and conditions of the Family Bridge platform.</label>
            <div class="error" id="termsAgreementError"></div>
          </div>

          <div class="section-navigation">
            <button type="button" class="btn btn-secondary" id="backToEligibility">Back to Eligibility</button>
            <button type="button" class="btn btn-primary" id="proceedToConfirm">Proceed to Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- CONFIRM -->
  <section class="review-section" id="confirmSection" style="display:none;">
    <div class="container">
      <div class="progress-bar-container">
        <div class="progress-bar">
          <div class="progress-fill" style="width:75%;"></div>
        </div>
        <div class="progress-text">
          <span>Step 3 of 4: Confirm</span>
          <span>75% Complete</span>
        </div>
      </div>

      <div class="review-container">
        <h2>Confirm Your Information</h2>
        <p class="form-note">Please review before submitting.</p>

        <div class="review-info">
          <h3>Portal</h3>
          <div class="review-row"><div class="review-label">Email</div><div class="review-value" id="reviewEmail"></div></div>

          <h3>Husband</h3>
          <div class="review-row"><div class="review-label">Name</div><div class="review-value" id="reviewHusbandName"></div></div>
          <div class="review-row"><div class="review-label">Age</div><div class="review-value" id="reviewHusbandAge"></div></div>
          <div class="review-row"><div class="review-label">Occupation</div><div class="review-value" id="reviewHusbandOcc"></div></div>
          <div class="review-row"><div class="review-label">NIC</div><div class="review-value" id="reviewHusbandNIC"></div></div>

          <h3>Wife</h3>
          <div class="review-row"><div class="review-label">Name</div><div class="review-value" id="reviewWifeName"></div></div>
          <div class="review-row"><div class="review-label">Age</div><div class="review-value" id="reviewWifeAge"></div></div>
          <div class="review-row"><div class="review-label">Occupation</div><div class="review-value" id="reviewWifeOcc"></div></div>
          <div class="review-row"><div class="review-label">NIC</div><div class="review-value" id="reviewWifeNIC"></div></div>

          <h3>Address</h3>
          <div class="review-row"><div class="review-label">District</div><div class="review-value" id="reviewDistrict"></div></div>
          <div class="review-row"><div class="review-label">Address</div><div class="review-value" id="reviewAddress"></div></div>
        </div>

        <div class="checkbox-group" style="margin-top:18px;">
          <input type="checkbox" id="finalConfirmation" name="finalConfirmation">
          <label class="required" for="finalConfirmation">I confirm that all information provided is accurate.</label>
          <div class="error" id="finalConfirmationError"></div>
        </div>

        <div class="section-navigation">
          <button class="btn btn-secondary" id="backToRegistration">Back to Registration</button>
          <button class="btn btn-primary" id="goToPayment">Continue to Payment</button>
        </div>
      </div>
    </div>
  </section>

  <!-- PAYMENT -->
  <section class="payment-section" id="paymentSection" style="display:none;">
    <div class="container">
      <div class="progress-bar-container">
        <div class="progress-bar">
          <div class="progress-fill" style="width:100%;"></div>
        </div>
        <div class="progress-text">
          <span>Step 4 of 4: Payment</span>
          <span>100% Complete</span>
        </div>
      </div>

      <div class="payment-container">
        <h2>Mandatory Registration Payment</h2>
        <p class="form-note">This is a simulation. Click Pay to submit the registration.</p>

        <div class="payment-info">
          <h3>Payment Details</h3>
          <div class="payment-amount">LKR 2000</div>
        </div>

        <div class="section-navigation">
          <button class="btn btn-secondary" id="backToConfirm">Back to Confirm</button>
          <button class="btn btn-primary" id="simulatePayment">Pay & Submit</button>
        </div>
      </div>
    </div>
  </section>

  <!-- CONFIRMATION -->
  <section class="confirmation-section" id="confirmationSection" style="display:none;">
    <div class="container">
      <div class="confirmation-container">
        <div class="confirmation-icon"><i class="fas fa-check"></i></div>
        <h2>Registration Submitted Successfully!</h2>

        <div class="review-info">
          <div class="review-row"><div class="review-label">Registration ID</div><div class="review-value" id="registrationID"></div></div>
          <div class="review-row"><div class="review-label">Submission Date</div><div class="review-value" id="submissionDate"></div></div>
          <div class="review-row"><div class="review-label">Portal Email</div><div class="review-value" id="portalEmail"></div></div>
        </div>

        <div class="form-group" style="margin-top:30px;">
          <a href="login.php" class="btn btn-primary btn-large"><i class="fas fa-sign-in-alt"></i> Go to Your Portal</a>
          <a href="index.php" class="btn btn-secondary btn-large" style="margin-left:15px;">Return to Homepage</a>
        </div>
      </div>
    </div>
  </section>

</main>

<?php include 'includes/footer.php' ?>

<?php
if (isset($_SESSION['form_data'])) unset($_SESSION['form_data']);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const sections = {
    1: document.getElementById('eligibilitySection'),
    2: document.getElementById('registrationSection'),
    3: document.getElementById('confirmSection'),
    4: document.getElementById('paymentSection')
  };

  function showStep(step) {
    Object.values(sections).forEach(s => s && (s.style.display = 'none'));
    if (sections[step]) sections[step].style.display = 'block';

    document.querySelectorAll('.step-indicator').forEach(ind => ind.classList.remove('active'));
    const active = document.getElementById('step' + step);
    if (active) active.classList.add('active');

    const fill = document.getElementById('progressFill');
    if (fill) {
      const map = {1:'25%',2:'50%',3:'75%',4:'100%'};
      fill.style.width = map[step] || '25%';
    }
  }

  showStep(1);

  const checkBtn = document.getElementById('checkEligibilityBtn');
  const continueBtn = document.getElementById('continueToRegistrationBtn');
  const resultBox = document.getElementById('eligibilityResultBox');

  function setErr(id, msg){
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg || '';
    if (msg) el.classList.add('show'); else el.classList.remove('show');
  }

  checkBtn?.addEventListener('click', function(e){
    e.preventDefault();

    const hAge = parseInt(document.getElementById('eligHusbandAge').value || '0', 10);
    const wAge = parseInt(document.getElementById('eligWifeAge').value || '0', 10);
    const marriageYears = parseInt(document.getElementById('marriageYears').value || '0', 10);
    const income = parseInt(document.getElementById('monthlyIncome').value || '0', 10);

    setErr('eligHusbandAgeError','');
    setErr('eligWifeAgeError','');
    setErr('marriageYearsError','');
    setErr('monthlyIncomeError','');

    let ok = true;
    if (hAge < 21 || hAge > 45){ setErr('eligHusbandAgeError','Husband age must be 21–45'); ok=false; }
    if (wAge < 21 || wAge > 45){ setErr('eligWifeAgeError','Wife age must be 21–45'); ok=false; }
    if (marriageYears < 3){ setErr('marriageYearsError','Marriage duration must be 3 years or more'); ok=false; }
    if (income < 60000){ setErr('monthlyIncomeError','Income must be 60,000 or more'); ok=false; }

    if (!ok) {
      resultBox.style.display = 'block';
      resultBox.className = 'alert alert-warning';
      resultBox.innerHTML = `<i class="fas fa-exclamation-triangle"></i>
        <div><strong>Not Eligible.</strong> You cannot continue to registration.</div>`;
      continueBtn.style.display = 'none';

      // IMPORTANT: reset hidden eligibility so server blocks registration
      document.getElementById('hiddenMarriageYears').value = marriageYears;
      document.getElementById('hiddenMonthlyIncome').value = income;
      document.getElementById('hiddenEligibilityResult').value = 'not_eligible';
      document.getElementById('hiddenEligibilityStatus').value = 'checked';
      return;
    }

    // Eligible
    resultBox.style.display = 'block';
    resultBox.className = 'alert alert-success';
    resultBox.innerHTML = `<i class="fas fa-check-circle"></i>
      <div><strong>Eligible!</strong> You may continue.</div>`;
    continueBtn.style.display = 'block';

    // ✅ Fill hidden inputs inside form (SERVER will read these)
    document.getElementById('hiddenMarriageYears').value = marriageYears;
    document.getElementById('hiddenMonthlyIncome').value = income;
    document.getElementById('hiddenEligibilityResult').value = 'eligible';
    document.getElementById('hiddenEligibilityStatus').value = 'checked';

    // auto-fill registration ages
    document.getElementById('husbandAge').value = hAge;
    document.getElementById('wifeAge').value = wAge;
  });

  continueBtn?.addEventListener('click', function(e){
    e.preventDefault();
    showStep(2);
  });

  // Back
  document.getElementById('backToEligibility')?.addEventListener('click', () => showStep(1));
  document.getElementById('backToRegistration')?.addEventListener('click', () => showStep(2));
  document.getElementById('backToConfirm')?.addEventListener('click', () => showStep(3));

  // Proceed to confirm
  document.getElementById('proceedToConfirm')?.addEventListener('click', function(){
    if (!validateRegistrationForm()) return;
    populateConfirm();
    showStep(3);
  });

  // Confirm -> Payment
  document.getElementById('goToPayment')?.addEventListener('click', function(){
    const fc = document.getElementById('finalConfirmation');
    const err = document.getElementById('finalConfirmationError');
    if (!fc.checked) {
      err.textContent = "Please confirm that all information is accurate.";
      err.classList.add('show');
      return;
    }
    err.textContent = "";
    err.classList.remove('show');
    showStep(4);
  });

  // Payment -> Submit
  document.getElementById('simulatePayment')?.addEventListener('click', function(){
    const form = document.getElementById('registrationFormData');
    if (!form) return;

    if (!form.querySelector('input[name="finalConfirmation"]')) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'finalConfirmation';
      hidden.value = '1';
      form.appendChild(hidden);
    }
    form.submit();
  });

  function validateRegistrationForm() {
    let ok = true;

    const required = [
      'email','confirmEmail','password','confirmPassword',
      'husbandFullName','husbandAge','husbandOccupation','husbandNIC',
      'wifeFullName','wifeAge','wifeOccupation','wifeNIC',
      'district','address'
    ];

    required.forEach(id => {
      const el = document.getElementById(id);
      const err = document.getElementById(id + 'Error');
      if (!el) return;

      if (!el.value || !el.value.toString().trim()) {
        if (err) { err.textContent = "This field is required"; err.classList.add('show'); }
        el.classList.add('error-border');
        ok = false;
      } else {
        if (err) { err.textContent = ""; err.classList.remove('show'); }
        el.classList.remove('error-border');
      }
    });

    const email = document.getElementById('email');
    const confirmEmail = document.getElementById('confirmEmail');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (email && !emailRegex.test(email.value)) {
      document.getElementById('emailError').textContent = "Enter a valid email";
      ok = false;
    }
    if (email && confirmEmail && email.value !== confirmEmail.value) {
      document.getElementById('confirmEmailError').textContent = "Emails do not match";
      ok = false;
    }

    const pass = document.getElementById('password');
    const cpass = document.getElementById('confirmPassword');
    if (pass && pass.value.length < 8) {
      document.getElementById('passwordError').textContent = "Minimum 8 characters";
      ok = false;
    }
    if (pass && cpass && pass.value !== cpass.value) {
      document.getElementById('confirmPasswordError').textContent = "Passwords do not match";
      ok = false;
    }

    const pc = document.getElementById('privacyConsent');
    const ta = document.getElementById('termsAgreement');
    if (pc && !pc.checked) { document.getElementById('privacyConsentError').textContent = "Required"; ok = false; }
    else if (document.getElementById('privacyConsentError')) document.getElementById('privacyConsentError').textContent = "";

    if (ta && !ta.checked) { document.getElementById('termsAgreementError').textContent = "Required"; ok = false; }
    else if (document.getElementById('termsAgreementError')) document.getElementById('termsAgreementError').textContent = "";

    // also make sure hidden eligibility exists before proceeding
    const hs = document.getElementById('hiddenEligibilityStatus');
    const hr = document.getElementById('hiddenEligibilityResult');
    if (!hs || !hr || hs.value !== 'checked' || hr.value !== 'eligible') {
      ok = false;
      alert('Please complete Eligibility Check (Step 1) before continuing.');
      showStep(1);
    }

    return ok;
  }

  function populateConfirm() {
    document.getElementById('reviewEmail').textContent = document.getElementById('email').value;

    document.getElementById('reviewHusbandName').textContent = document.getElementById('husbandFullName').value;
    document.getElementById('reviewHusbandAge').textContent = document.getElementById('husbandAge').value;
    document.getElementById('reviewHusbandOcc').textContent = document.getElementById('husbandOccupation').value;
    document.getElementById('reviewHusbandNIC').textContent = document.getElementById('husbandNIC').value;

    document.getElementById('reviewWifeName').textContent = document.getElementById('wifeFullName').value;
    document.getElementById('reviewWifeAge').textContent = document.getElementById('wifeAge').value;
    document.getElementById('reviewWifeOcc').textContent = document.getElementById('wifeOccupation').value;
    document.getElementById('reviewWifeNIC').textContent = document.getElementById('wifeNIC').value;

    document.getElementById('reviewDistrict').textContent = document.getElementById('district').value;
    document.getElementById('reviewAddress').textContent = document.getElementById('address').value;
  }

});
</script>

</body>
</html>
