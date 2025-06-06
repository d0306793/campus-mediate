<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campus Mediate | Hostel Interface</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&display=swap" rel="stylesheet">
  <!-- <link rel="stylesheet" href="assets1/css/mediate2.css"> -->
  <link rel="stylesheet" href="index.css">
</head>

<body>
    <!-- Header Section Nav-Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="section-wrapper container-fluid">
        <div class="logo">
        <span class="brand-text">Campus Mediate</span>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">
        <ul class="navbar-nav mx-auto">
            <li class="nav-item">
                <a class="nav-link" href="#about">About Us</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#hostels">Hostels</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#contact">Contact</a>
            </li>
            
        </ul>
        </div>
        <div class="navbar-nav">
            <a href="views/auth/register.php" class="register-btn">Register</a>
        </div>
      
    </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">    
        <div class="section-wrapper hero-content">
            <h1>FIND YOUR IDEAL HOSTEL</h1>
            <p class="dynamic-text">
                <span class="text-rotate"></span>
            </p>

                <a href="views/auth/register.php" class="hero-register-btn">Register Now</a>

        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
    <div class="section-wrapper about-container">
        <!-- Text Content Side -->
        <div class="about-content">
            <h2>About Us</h2>
            <p class="main-text"><b>Campus Mediate</b> is your dedicated online platform that connects students 
                with hostel managers for hassle-free accommodation solutions. We provide an efficient, 
                streamlined interface for students to find and book hostels near campus while allowing hostel managers
                to showcase their properties and manage bookings.</p>

            <p class="main-text">Our commitment is to ensure a reliable, transparent experience for both parties. Should you encounter 
                any issues, use our contact form to report them, and we'll resolve them promptly.</p>
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-wifi"></i>
                    <h3>High-Speed WiFi</h3>
                    <p>Stay connected with premium internet access</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <h3>24/7 Security</h3>
                    <p>Round-the-clock security for your peace of mind</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-coffee"></i>
                    <h3>Study Areas</h3>
                    <p>Dedicated spaces for focused learning</p>
                </div>
            </div>
        </div>

        <!-- Image Slider Side -->
        <div class="about-slider">
            <div class="slider-container">
                <!-- Images will be inserted via JavaScript -->
            </div>
            <div class="slider-controls">
                <div class="slider-dots"></div>
            </div>
        </div>
    </div>
    </section>

    <!-- Hostels Section -->
    <section id="hostels" class="hostels-section">
    <div class="section-wrapper container">
        <h2 class="section-title">Our Featured Hostels</h2>
        <div class="hostels-grid">
            <!-- Central Haven Hostel Card -->
            <div class="hostel-card" data-hostel="central-haven">
                <div class="hostel-image">
                    <img src="assets/images/listed-hostels/central-haven-1.jpg" alt="Central Haven Hostel">
                </div>
                <div class="hostel-info">
                    <h3>Central Haven</h3>
                    <p class="location"><i class="fas fa-map-marker-alt"></i> City Center</p>
                    <ul class="amenities">
                        <li><i class="fas fa-wifi"></i> Free WiFi</li>
                        <li><i class="fas fa-shield-alt"></i> 24/7 Security</li>
                        <li><i class="fas fa-book"></i> Study Areas</li>
                    </ul>
                    <a href="views/hostel/hostel_details.php?id=central-haven" class="view-details-btn">View Details</a>
                </div>
            </div>

            <!-- Urban Stay Hostel Card -->
            <div class="hostel-card" data-hostel="urban-stay">
                <div class="hostel-image">
                    <img src="assets/images/listed-hostels/urban-stay-1.jpg" alt="Urban Stay Hostel">
                </div>
                <div class="hostel-info">
                    <h3>Urban Stay</h3>
                    <p class="location"><i class="fas fa-map-marker-alt"></i> Metropolitan Area</p>
                    <ul class="amenities">
                        <li><i class="fas fa-wifi"></i> High-Speed Internet</li>
                        <li><i class="fas fa-utensils"></i> Kitchen Facilities</li>
                        <li><i class="fas fa-couch"></i> Common Room</li>
                    </ul>
                    <a href="views/hostel/hostel_details.php?id=urban-stay" class="view-details-btn">View Details</a>
                </div>
            </div>

            <!-- Student Retreat Hostel Card -->
            <div class="hostel-card" data-hostel="student-retreat">
                <div class="hostel-image">
                    <img src="assets/images/listed-hostels/student-retreat-1.jpg" alt="Student Retreat Hostel">
                </div>
                <div class="hostel-info">
                    <h3>Student Retreat</h3>
                    <p class="location"><i class="fas fa-map-marker-alt"></i> Campus Zone</p>
                    <ul class="amenities">
                        <li><i class="fas fa-desktop"></i> Computer Lab</li>
                        <li><i class="fas fa-dumbbell"></i> Fitness Center</li>
                        <li><i class="fas fa-parking"></i> Parking Available</li>
                    </ul>
                    <a href="views/hostel/hostel_details.php?id=student-retreat" class="view-details-btn">View Details</a>
                </div>
            </div>
        </div>
    </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
    <div class="section-wrapper container">
        <div class="contact-wrapper">
            <div class="contact-info">
                <h2 class="section-title">Contact Us</h2>
                <div class="contact-details">
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <p>support@campusmediate.com</p>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <p>+256 24-456-7890</p>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>Gulu, Uganda</p>
                    </div>
                </div>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="contact-form">
                <form id="contactForm">
                    <div class="form-group">
                        <input type="text" id="name" name="name" required>
                        <label for="name">Your Name</label>
                    </div>
                    <div class="form-group">
                        <input type="email" id="email" name="email" required>
                        <label for="email">Your Email</label>
                    </div>
                    <div class="form-group">
                        <textarea id="message" name="message" required></textarea>
                        <label for="message">Your Message</label>
                    </div>
                    <button type="submit" class="submit-btn">
                        <span>Send Message</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    </section>

    <!-- Footer Section -->
    <footer class="site-footer">
    <div class="section-wrapper container">
        <div class="footer-content">
            <div class="footer-brand">
                <h3>Campus Mediate</h3>
                <p>Your trusted platform for student accommodation</p>
            </div>
            <div class="footer-links">
                <div class="link-group">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#hostels">Hostels</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="link-group">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Campus Mediate. All rights reserved.</p>
        </div>
    </div>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Text rotation for hero section
    const textRotate = document.querySelector('.text-rotate');
    const phrases = [
      'Affordable Student Housing',
      'Safe & Secure Accommodation',
      'Close to Campus Locations'
    ];
    let currentIndex = 0;
    
    function updateText() {
      textRotate.textContent = phrases[currentIndex];
      currentIndex = (currentIndex + 1) % phrases.length;
    }
    
    updateText(); // Initial text
    setInterval(updateText, 3000); // Change text every 3 seconds
    
    // Redirect users who click on hostel section to register
    const hostelLinks = document.querySelectorAll('.view-details-btn, .hostel-card, #hostels a, a[href="#hostels"]');
    hostelLinks.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = 'views/auth/register.php';
      });
    });
  });
</script>
</body>
</html>