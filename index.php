<?php
session_start();
// Redirect if already logged in
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Secure Online Tutoring (SOTMS)</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Specific Styles for the Modern Home Page */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            color: #333;
        }
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), 
                        url('uploads/image001.jpg'); /* Using your uploaded image */
            background-size: cover;
            background-position: center;
            height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 0 20px;
        }
        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .btn-group .btn {
            padding: 15px 35px;
            font-size: 1.1rem;
            border-radius: 50px;
            transition: 0.3s;
            margin: 10px;
            text-transform: bold;
        }
        .section-title {
            text-align: center;
            margin: 50px 0;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 40px 10%;
            background: #fff;
        }
        .feature-card {
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 5px solid #3498db;
        }
        footer {
            background: #2c3e50;
            color: white;
            padding: 40px 0;
            text-align: center;
        }
    </style>
</head>
<body>

    <header style="position: absolute; width: 100%; z-index: 10;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 5%;">
            <h2 style="color: white; margin: 0;">SOTMS <span style="color: #3498db;">PRO</span></h2>
            <nav>
                <a href="system_details.php" style="color: white; text-decoration: none; margin-right: 20px;">System Info</a>
                <a href="auth/login.php" style="color: white; text-decoration: none; margin-right: 20px;">Login</a>
                <a href="auth/register.php" class="btn" style="background: #3498db; color: white; padding: 10px 25px; border-radius: 5px; text-decoration: none;">Join Now</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Excellence in Online Tutoring</h1>
            <p>A Secure Online Tutoring Management System designed to facilitate interactions between students, tutors, and administrators while ensuring data security and privacy.</p>
            <div class="btn-group">
                <a href="auth/register.php" class="btn" style="background: #2ecc71; color: white; text-decoration: none;">Get Started</a>
                <a href="#about" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white; text-decoration: none;">Learn More</a>
            </div>
        </div>
    </section>

    <section id="about" class="container" style="padding: 60px 10%;">
        <div class="section-title">
            <h2>Why Choose SOTMS PRO?</h2>
            <p>Our platform is built with a focus on three core objectives: Security, Management, and Communication.</p>
        </div>

        <div class="features">
            <div class="feature-card">
                <h3>Secure Authentication</h3>
                <p>Advanced hashing for passwords and session protection to keep your data safe.</p>
            </div>
            <div class="feature-card">
                <h3>Session Booking</h3>
                <p>Easy-to-use scheduling for students to connect with expert tutors instantly.</p>
            </div>
            <div class="feature-card">
                <h3>Role Control</h3>
                <p>Specific dashboards for Admins, Tutors, and Students to manage their unique tasks.</p>
            </div>
            <div class="feature-card">
                <h3>Learning Resources</h3>
                <p>Centralized management for learning materials and resources for effective studies.</p>
            </div>
        </div>
    </section>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SOTMS PRO. All Rights Reserved.</p>
        <p style="font-size: 0.8rem; opacity: 0.7;">Secure Online Tutoring Management System - Built for Integrity.</p>
    </footer>

</body>
</html>