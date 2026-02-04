
<header>
    <div class="container">
        <nav>
            <a href="#home" class="logo-img-container">
                <img src="logo.png" alt="Family Bridge - Child Adoption System" class="logo-image">
            </a>
            
            <ul class="nav-links">
                <li><a href="index.php#home">Home</a></li>
                <li><a href="index.php#process">Process</a></li>
                <li><a href="index.php#features">Features</a></li>
                
                <li><a href="register.php" class="btn btn-secondary">Start Registration</a></li>
                <li><a href="login.php" class="btn btn-secondary" id="btnlogin">login</a></li>
            </ul>
            
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </nav>
    </div>
</header>

<script>

document.addEventListener('DOMContentLoaded', function() {
    
    document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
        document.querySelector('.nav-links').classList.toggle('active');
    });
    
   
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', function() {
            document.querySelector('.nav-links').classList.remove('active');
        });
    });
    
    
    window.addEventListener('scroll', function() {
        const header = document.querySelector('header');
        if (window.scrollY > 100) {
            header.style.boxShadow = '0 4px 12px rgba(139, 69, 19, 0.2)';
            header.style.backgroundColor = 'rgba(250, 243, 224, 0.95)';
        } else {
            header.style.boxShadow = 'none';
            header.style.backgroundColor = 'transparent';
        }
    });
});
</script>