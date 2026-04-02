<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Online Tutoring Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f3f7fb; color: #1f2937; }
        .site-header { background: #1f2937; color: #ffffff; padding: 18px 5%; position: sticky; top: 0; z-index: 100; }
        .site-header .brand { font-size: 1.4rem; letter-spacing: 1px; }
        .site-header nav a { color: #d1d5db; text-decoration: none; margin-left: 24px; font-weight: 500; }
        .site-header nav a:hover { color: #ffffff; }
        .hero-section {
            background: linear-gradient(180deg, rgba(15,23,42,0.72), rgba(15,23,42,0.72)),
                        url('uploads/image001.jpg') center/cover no-repeat;
            color: white;
            padding: 100px 5% 80px;
            border-bottom-left-radius: 28px;
            border-bottom-right-radius: 28px;
            min-height: 520px;
        }
        .hero-section h1 { font-size: clamp(2.5rem, 4vw, 4rem); margin: 0 0 18px; line-height: 1.05; }
        .hero-section p { max-width: 760px; font-size: 1.05rem; line-height: 1.8; margin-bottom: 30px; color: #dbeafe; }
        .hero-buttons a { display: inline-block; margin-right: 16px; margin-top: 10px; padding: 14px 28px; border-radius: 999px; font-weight: 600; text-decoration: none; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .hero-buttons a.primary { background: #f8fafc; color: #1f2937; }
        .hero-buttons a.secondary { background: rgba(255,255,255,0.18); color: white; border: 1px solid rgba(255,255,255,0.35); }
        .hero-buttons a:hover { transform: translateY(-2px); box-shadow: 0 18px 45px rgba(15,23,42,0.18); }
        .content-section { padding: 60px 5%; max-width: 1200px; margin: auto; }
        .section-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 40px; }
        .section-head h2 { font-size: 2rem; color: #111827; margin: 0; }
        .section-head p { color: #4b5563; max-width: 720px; margin: 0; }
        .info-grid { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .info-card { background: white; border-radius: 24px; padding: 30px; box-shadow: 0 22px 50px rgba(15,23,42,0.08); border: 1px solid rgba(148,163,184,0.16); }
        .info-card h3 { margin-top: 0; font-size: 1.3rem; color: #111827; }
        .info-card ul { margin: 16px 0 0; padding-left: 20px; color: #4b5563; }
        .info-card ul li { margin-bottom: 10px; line-height: 1.7; }
        .feature-list { display: grid; gap: 20px; margin-top: 20px; }
        .feature-item { display: flex; gap: 14px; align-items: flex-start; }
        .feature-item strong { color: #1f2937; min-width: 90px; }
        .page-footer { padding: 30px 5%; text-align: center; color: #6b7280; font-size: 0.95rem; }
        .badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(59,130,246,0.12); color: #1d4ed8; font-weight: 600; padding: 8px 14px; border-radius: 999px; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
            <div class="brand">SOTMS PRO</div>
            <nav>
                <a href="index.php">Home</a>
                <a href="system_details.php">System Info</a>
                <a href="auth/login.php">Login</a>
                <a href="auth/register.php">Register</a>
            </nav>
        </div>
    </header>

    <section class="hero-section">
        <div class="container">
            <div class="section-head">
                <div>
                    <h1>System Details for SOTMS PRO</h1>
                    <p>Everything a student, tutor, or administrator needs to know about this secure online tutoring platform. Learn how the system works, the roles available, core features, and how to get started quickly.</p>
                </div>
                <div class="badge">Trusted Tutoring Management</div>
            </div>
            <div class="hero-buttons">
                <a class="primary" href="auth/register.php">Create Account</a>
                <a class="secondary" href="auth/login.php">Login</a>
                <a class="secondary" href="#features">View Features</a>
            </div>
        </div>
    </section>

    <section id="overview" class="content-section">
        <div class="section-head">
            <h2>Platform Overview</h2>
            <p>SOTMS PRO is built to connect students and tutors through a secure, role-based dashboard system. It prioritizes ease of use, privacy, and effective online learning management.</p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>Purpose</h3>
                <p>This system supports online tutoring by managing student enrollments, tutor sessions, content access, and administrator workflows in one trusted interface.</p>
            </div>
            <div class="info-card">
                <h3>Who Can Use It</h3>
                <ul>
                    <li><strong>Students:</strong> Access lessons, book sessions, and view progress.</li>
                    <li><strong>Tutors:</strong> Manage class schedules and student support.</li>
                    <li><strong>Admins:</strong> Oversee users, content, and system settings.</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Technology</h3>
                <ul>
                    <li>PHP for server-side pages and authentication.</li>
                    <li>MySQL for secure data storage.</li>
                    <li>Responsive HTML/CSS interface designed for modern devices.</li>
                </ul>
            </div>
        </div>
    </section>

    <section id="features" class="content-section" style="background: #edf2f7; border-radius: 32px;">
        <div class="section-head">
            <h2>Key System Features</h2>
            <p>Important capabilities that make SOTMS PRO a complete online tutoring management solution.</p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>Secure Login</h3>
                <p>Each user signs in with a unique account. Sessions and login data are protected by secure server-side logic.</p>
            </div>
            <div class="info-card">
                <h3>Role-Based Dashboards</h3>
                <ul>
                    <li>Students see their learning resources and bookings.</li>
                    <li>Tutors manage lessons and student communications.</li>
                    <li>Admins control users and system operations.</li>
                </ul>
            </div>
            <div class="info-card">
                <h3>Responsive Design</h3>
                <p>The layout adapts smoothly across desktop, tablet, and mobile screens so learners can access the system anywhere.</p>
            </div>
            <div class="info-card">
                <h3>Content Management</h3>
                <p>Admins can add or update system content and resources, ensuring learners always have the latest materials.</p>
            </div>
        </div>
    </section>

    <section class="content-section">
        <div class="section-head">
            <h2>How to Use the System</h2>
            <p>Simple steps to get started for each type of user.</p>
        </div>

        <div class="feature-list">
            <div class="feature-item"><strong>1. Register</strong> Create your account using the registration page. Choose the correct role (Student, Tutor, or Admin) if available.</div>
            <div class="feature-item"><strong>2. Login</strong> Sign in through the login page using your credentials.</div>
            <div class="feature-item"><strong>3. Dashboard</strong> Access a personalized dashboard based on your role immediately after login.</div>
            <div class="feature-item"><strong>4. Explore Tools</strong> Students can join sessions, tutors can organize lessons, and admins can manage users.</div>
            <div class="feature-item"><strong>5. Get Support</strong> Contact your administrator for account help or technical assistance.</div>
        </div>
    </section>

    <section class="content-section" style="padding-bottom: 40px;">
        <div class="section-head">
            <h2>Security & Privacy</h2>
            <p>The system follows a secure workflow to protect user data and keep tutoring information confidential.</p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>Data Protection</h3>
                <p>User credentials and session data are managed on the server to reduce exposure on public devices.</p>
            </div>
            <div class="info-card">
                <h3>Role Separation</h3>
                <p>Each user sees only the information relevant to their account, lowering the risk of unauthorized access.</p>
            </div>
            <div class="info-card">
                <h3>Reliable Access</h3>
                <p>Always close your browser after use on shared computers and logout when you are finished.</p>
            </div>
        </div>
    </section>

    <footer class="page-footer">
        <p>&copy; <?php echo date('Y'); ?> SOTMS PRO. Built for secure, effective online tutoring.</p>
        <p>If you need additional information, please contact your system administrator.</p>
    </footer>
</body>
</html>
