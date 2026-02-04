<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Bridge | Child Adoption System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/mainIndex.css">
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
    
 <?php include 'includes/header.php'?>

    <section class="hero" id="home">
        <div class="container">
            <h1>Building Bridges to Loving Families</h1>
            <p>Family Bridge is a government-approved secure platform connecting eligible couples with children awaiting adoption. Our transparent system ensures every child finds a safe, loving home through a fair and confidential process.</p>
            <div class="cta-buttons">
                <a href="#register" class="btn btn-primary btn-large">Begin Your Journey</a>
                <a href="#process" class="btn btn-accent btn-large">Learn More</a>
            </div>
        </div>
    </section>

 
   
    <section class="process" id="process">
        <div class="container">
            <h2>Our 4-Step Adoption Process</h2>
            <div class="process-steps">
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="step-number">1</div>
                    <h3>Registration & Payment</h3>
                    <p>Complete your profile with required details and make the mandatory payment through our secure gateway to initiate the process.</p>
                </div>
                
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="step-number">2</div>
                    <h3>Automated Eligibility Scoring</h3>
                    <p>Our system evaluates your application against key criteria. Only candidates achieving 75%+ threshold proceed to the next stage.</p>
                </div>
                
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-child"></i>
                    </div>
                    <div class="step-number">3</div>
                    <h3>Child Profile & Voting</h3>
                    <p>Approved couples can access verified child profiles and cast a single vote for the child they wish to adopt.</p>
                </div>
                
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="step-number">4</div>
                    <h3>Notification & Finalization</h3>
                    <p>Receive notifications when suitable children become available. Final matches are overseen by our Chief Officer.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="container">
            <h2>Platform Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure Data Storage</h3>
                    <p>All sensitive information including IDs, medical records, and personal data is encrypted and stored with maximum security protocols.</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-robot"></i>
                    <h3>Automated Eligibility Scoring</h3>
                    <p>Objective, transparent scoring system eliminates bias and ensures fair evaluation based on predefined government criteria.</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h3>Smart Match Notifications</h3>
                    <p>Immediate alerts when a child matching your profile becomes available at any approved institution nationwide.</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-vote-yea"></i>
                    <h3>Single Vote System</h3>
                    <p>Each couple can vote for only one child, ensuring fair distribution of adoption opportunities across all applicants.</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-user-lock"></i>
                    <h3>Role-Based Access Control</h3>
                    <p>Sensitive data is accessible only to the Chief Officer, maintaining strict confidentiality and security standards.</p>
                </div>
                
                <div class="feature-card">
                    <i class="fas fa-file-contract"></i>
                    <h3>Government Approved</h3>
                    <p>All participating institutions are verified and approved by government authorities, ensuring compliance and legitimacy.</p>
                </div>
            </div>
        </div>
    </section>


   

  
    <section class="cta-section" id="register">
        <div class="container">
            <h2>Ready to Build Your Family Bridge?</h2>
            <p>Join thousands of couples who have found their perfect match through our secure, transparent adoption platform. Begin your registration today and take the first step toward building your family.</p>
            <a href="#" class="btn btn-large" style="background-color: white; color: var(--primary);">Start Registration Now</a>
            <p style="margin-top: 20px; font-size: 0.9rem; opacity: 0.9;">Mandatory payment required before registration. All payments are secure and refundable if eligibility criteria are not met.</p>
        </div>
    </section>

    <?php include 'includes/footer.php'?>

</body>
</html>