<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check if user is admin using your correct function
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get statistics for dashboard
$students_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$exams_count = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$questions_count = $pdo->query("SELECT COUNT(*) FROM objective_questions")->fetchColumn();
$theory_count = $pdo->query("SELECT COUNT(*) FROM theory_questions")->fetchColumn();

// Get results statistics
$results_count = $pdo->query("SELECT COUNT(*) as count FROM results")->fetch()['count'];
$recent_results = $pdo->query("SELECT COUNT(*) as count FROM results WHERE DATE(submitted_at) = CURDATE()")->fetch()['count'];
$avg_score = $pdo->query("SELECT AVG(percentage) as avg FROM results")->fetch()['avg'];
$avg_score = $avg_score ? round($avg_score, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tip Top Schools</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .school-logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .school-logo img {
            max-width: 40px;
            height: auto;
        }
        .school-logo span {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }
        
        /* Mobile Navigation */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
            padding: 0.5rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        .nav-links a:hover {
            background: #4a90e2;
            color: white;
        }
        .logout-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
            font-size: 0.9rem;
        }
        .logout-btn:hover {
            background: #ff5252;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .welcome-banner {
            background: linear-gradient(45deg, #ff6b6b, #4a90e2);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .welcome-banner h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .welcome-banner p {
            font-size: 1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem 1rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            color: #666;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        .action-btn {
            background: white;
            padding: 1.2rem 0.8rem;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 3px solid transparent;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60px;
        }
        .action-btn:hover {
            background: #4a90e2;
            color: white;
            border-color: #357abd;
            transform: scale(1.05);
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .header-content {
                flex-wrap: wrap;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .nav-links {
                display: none;
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
                gap: 0.5rem;
                background: rgba(255, 255, 255, 0.98);
                border-radius: 10px;
                padding: 1rem;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .nav-links a {
                padding: 0.8rem;
                border-radius: 8px;
                text-align: center;
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid #eee;
                width: 100%;
            }
            
            .logout-btn {
                width: 100%;
                margin-top: 0.5rem;
            }
            
            .container {
                margin: 1rem auto;
                padding: 0 0.8rem;
            }
            
            .welcome-banner {
                padding: 1.2rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.5rem;
            }
            
            .welcome-banner p {
                font-size: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.8rem;
            }
            
            .stat-card {
                padding: 1rem 0.5rem;
            }
            
            .stat-card h3 {
                font-size: 1.3rem;
            }
            
            .stat-card p {
                font-size: 0.8rem;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .school-logo span {
                font-size: 0.9rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.3rem;
            }
            
            .welcome-banner p {
                font-size: 0.85rem;
            }
            
            .action-btn {
                padding: 1rem 0.5rem;
                font-size: 0.85rem;
                min-height: 50px;
            }
        }
        
        /* Additional small screen optimization */
        @media (max-width: 360px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .welcome-banner {
                padding: 1rem;
            }
            
            .stat-card {
                padding: 0.8rem 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="school-logo">       
                <img src="../assets/logo.png" alt="School Logo">
                <span>Admin Panel</span>
            </div>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation menu">☰</button>
            
            <div class="nav-links" id="navLinks">
                <a href="index.php">Dashboard</a>
                <a href="manage_students.php">Students</a>
                <a href="questions.php">Questions</a>
                <a href="manage_exams.php">Exams</a>
                <a href="view_results.php">Results</a>
                <a href="manage_subjects_topics.php">Subjects/Topics</a>
                <a href="manage_staff.php">Staff</a>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-banner">
            <h2>Welcome to Admin Dashboard</h2>
            <p>Manage your CBT system efficiently and effectively</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-left: 5px solid #4a90e2;">
                <h3><?php echo $students_count; ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-card" style="border-left: 5px solid #2ecc71;">
                <h3><?php echo $questions_count; ?></h3>
                <p>Objective Questions</p>
            </div>
            <div class="stat-card" style="border-left: 5px solid #f39c12;">
                <h3><?php echo $theory_count; ?></h3>
                <p>Theory Questions</p>
            </div>
            <div class="stat-card" style="border-left: 5px solid #ff6b6b;">
                <h3><?php echo $exams_count; ?></h3>
                <p>Active Exams</p>
            </div>
            <div class="stat-card" style="border-left: 5px solid #9b59b6;">
                <h3><?php echo $results_count; ?></h3>
                <p>Total Results</p>
            </div>
            <div class="stat-card" style="border-left: 5px solid #1abc9c;">
                <h3><?php echo $avg_score; ?>%</h3>
                <p>Average Score</p>
            </div>
        </div>

        <div class="quick-actions">
            <a href="manage_students.php" class="action-btn">Manage Students</a>
            <a href="upload_objective.php" class="action-btn">Upload Objective Questions</a>
            <a href="upload_theory.php" class="action-btn">Upload Theory Questions</a>
            <a href="create_exam.php" class="action-btn">Create New Exam</a>
            <a href="view_results.php" class="action-btn">📊 View Results</a>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
            
            // Update button text for better UX
            this.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
        });
        
        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            
            if (window.innerWidth <= 768 && 
                !navLinks.contains(event.target) && 
                !mobileMenuBtn.contains(event.target) &&
                navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                mobileMenuBtn.textContent = '☰';
            }
        });
        
        // Close menu when window is resized to desktop size
        window.addEventListener('resize', function() {
            const navLinks = document.getElementById('navLinks');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            
            if (window.innerWidth > 768 && navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                mobileMenuBtn.textContent = '☰';
            }
        });
    </script>
</body>

</html>
