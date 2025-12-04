<?php
session_start();
require_once '../includes/config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

// Get available single subject exams for student's class
$stmt = $pdo->prepare("
    SELECT e.*, s.subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject_id = s.id 
    WHERE e.class = ? AND e.is_active = TRUE AND e.exam_type = 'single'
    AND e.id NOT IN (
        SELECT exam_id FROM exam_sessions 
        WHERE student_id = ? AND status = 'completed'
    )
");
$stmt->execute([$_SESSION['class'], $_SESSION['student_id']]);
$available_single_exams = $stmt->fetchAll();

// Get available group exams for student's class
$stmt = $pdo->prepare("
    SELECT e.*, sg.group_name 
    FROM exams e 
    JOIN subject_groups sg ON e.group_id = sg.id 
    WHERE e.class = ? AND e.is_active = TRUE AND e.exam_type = 'group'
    AND e.id NOT IN (
        SELECT exam_id FROM exam_sessions 
        WHERE student_id = ? AND status = 'completed'
    )
");
$stmt->execute([$_SESSION['class'], $_SESSION['student_id']]);
$available_group_exams = $stmt->fetchAll();

// Get completed exams
$stmt = $pdo->prepare("
    SELECT es.*, e.exam_name, s.subject_name, e.exam_type
    FROM exam_sessions es 
    JOIN exams e ON es.exam_id = e.id 
    LEFT JOIN subjects s ON e.subject_id = s.id 
    WHERE es.student_id = ? AND es.status = 'completed'
    ORDER BY es.end_time DESC
");
$stmt->execute([$_SESSION['student_id']]);
$completed_exams = $stmt->fetchAll();

// Get pending assignments
$stmt = $pdo->prepare("
    SELECT a.*, s.subject_name 
    FROM assignments a 
    JOIN subjects s ON a.subject_id = s.id 
    WHERE a.class = ? AND a.deadline >= CURDATE()
    AND a.id NOT IN (
        SELECT assignment_id FROM assignment_submissions 
        WHERE student_id = ?
    )
");
$stmt->execute([$_SESSION['class'], $_SESSION['student_id']]);
$pending_assignments = $stmt->fetchAll();

// Get submitted assignments
$stmt = $pdo->prepare("
    SELECT asub.*, a.title, a.subject_id, s.subject_name 
    FROM assignment_submissions asub 
    JOIN assignments a ON asub.assignment_id = a.id 
    JOIN subjects s ON a.subject_id = s.id 
    WHERE asub.student_id = ?
    ORDER BY asub.submitted_at DESC
");
$stmt->execute([$_SESSION['student_id']]);
$submitted_assignments = $stmt->fetchAll();

// Get recent e-library resources
$stmt = $pdo->prepare("
    SELECT * FROM library_resources 
    WHERE class = ? OR class = 'All'
    ORDER BY uploaded_at DESC 
    LIMIT 6
");
$stmt->execute([$_SESSION['class']]);
$library_resources = $stmt->fetchAll();

// Get student progress stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT es.exam_id) as completed_exams,
        COUNT(DISTINCT asub.assignment_id) as submitted_assignments,
        AVG(es.score) as avg_score
    FROM exam_sessions es 
    LEFT JOIN assignment_submissions asub ON es.student_id = asub.student_id
    WHERE es.student_id = ? AND es.status = 'completed'
");
$stmt->execute([$_SESSION['student_id']]);
$progress_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Mighty School for Valours</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 4px solid #8a2be2;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .school-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .school-logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .school-name {
            color: #1e3c72;
            font-size: 24px;
            font-weight: bold;
        }
        
        .student-info {
            text-align: right;
            color: #1e3c72;
        }
        
        .student-name {
            font-weight: bold;
            font-size: 18px;
        }
        
        .student-details {
            font-size: 14px;
            color: #666;
        }
        
        .logout-btn {
            background: linear-gradient(45deg, #8a2be2, #6a0dad);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin-left: 20px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: linear-gradient(45deg, #6a0dad, #4b0082);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .nav-tabs {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid #e0e0ff;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 12px 24px;
            background: #f0f4ff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            color: #1e3c72;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #1e3c72, #8a2be2);
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: #d9e2ff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid #e0e0ff;
        }
        
        .section-title {
            color: #1e3c72;
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #8a2be2;
            display: inline-block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e3c72, #8a2be2);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .exam-grid, .assignment-grid, .library-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .exam-card, .assignment-card, .resource-card {
            background: linear-gradient(135deg, #1e3c72, #8a2be2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .exam-card:hover, .assignment-card:hover, .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .exam-card.completed, .assignment-card.submitted {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        
        .assignment-card.urgent {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .exam-name, .assignment-title, .resource-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .exam-details, .assignment-details, .resource-details {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .start-btn, .view-btn, .submit-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        
        .start-btn:hover, .view-btn:hover, .submit-btn:hover {
            background: white;
            color: #1e3c72;
        }
        
        .score {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin-top: 10px;
        }
        
        .no-items {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        
        .welcome-message {
            text-align: center;
            color: #1e3c72;
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .profile-form {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #1e3c72;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0ff;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #8a2be2;
        }
        
        .update-btn {
            background: linear-gradient(135deg, #1e3c72, #8a2be2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .update-btn:hover {
            background: linear-gradient(135deg, #8a2be2, #1e3c72);
            transform: translateY(-2px);
        }
        
        .deadline {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .file-size {
            font-size: 12px;
            opacity: 0.8;
        }
        
        /* New styles for group exams */
        .exam-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.3);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            backdrop-filter: blur(10px);
        }
        
        .group-exam {
            background: linear-gradient(135deg, #6a0dad, #8a2be2);
            border-left: 4px solid #ffd700;
        }
        
        .single-exam {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
        }
        
        .exam-type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .exam-type-tab {
            padding: 10px 20px;
            background: #f0f4ff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            color: #1e3c72;
            transition: all 0.3s ease;
        }
        
        .exam-type-tab.active {
            background: linear-gradient(135deg, #1e3c72, #8a2be2);
            color: white;
        }
        
        .exam-type-content {
            display: none;
        }
        
        .exam-type-content.active {
            display: block;
        }
        
        .subject-list {
            font-size: 14px;
            margin-top: 10px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="school-info">
                <div class="school-logo">MSV</div>
                <div class="school-name">Mighty School for Valours</div>
            </div>
            <div style="display: flex; align-items: center;">
                <div class="student-info">
                    <div class="student-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="student-details">
                        <?php echo htmlspecialchars($_SESSION['admission_number']); ?> | 
                        <?php echo htmlspecialchars($_SESSION['class']); ?>
                    </div>
                </div>
                <a href="login.php?logout=true" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="nav-tabs">
            <div class="tabs">
                <button class="tab active" onclick="showTab('dashboard')">Dashboard</button>
                <button class="tab" onclick="showTab('e-library')">E-Library</button>
                <button class="tab" onclick="showTab('assignments')">Assignments</button>
                <button class="tab" onclick="showTab('exams')">Exams</button>
                <button class="tab" onclick="showTab('settings')">Settings</button>
            </div>
        </div>
        
        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <div class="section">
                <h2 class="section-title">Welcome to Your Dashboard</h2>
                <div class="welcome-message">
                    Ready to excel in your academic journey? Track your progress and access learning materials below!
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Your Progress Overview</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $progress_stats['completed_exams'] ?? 0; ?></div>
                        <div class="stat-label">Exams Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $progress_stats['submitted_assignments'] ?? 0; ?></div>
                        <div class="stat-label">Assignments Submitted</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($pending_assignments); ?></div>
                        <div class="stat-label">Pending Assignments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo round($progress_stats['avg_score'] ?? 0, 1); ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Recent Activities</h2>
                <?php if (count($available_single_exams) > 0 || count($available_group_exams) > 0 || count($pending_assignments) > 0): ?>
                    <div class="exam-grid">
                        <?php if (count($available_single_exams) > 0 || count($available_group_exams) > 0): ?>
                            <div class="exam-card single-exam">
                                <div class="exam-name">Available Exams</div>
                                <div class="exam-details">
                                    You have <?php echo count($available_single_exams) + count($available_group_exams); ?> exam(s) waiting for you.<br>
                                    - <?php echo count($available_single_exams); ?> Single Subject Exam(s)<br>
                                    - <?php echo count($available_group_exams); ?> Group Exam(s)
                                </div>
                                <button class="view-btn" onclick="showTab('exams')">View All Exams</button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (count($pending_assignments) > 0): ?>
                            <div class="assignment-card urgent">
                                <div class="assignment-title">Pending Assignments</div>
                                <div class="assignment-details">
                                    You have <?php echo count($pending_assignments); ?> assignment(s) to submit. 
                                    Check the assignments section for details.
                                </div>
                                <button class="view-btn" onclick="showTab('assignments')">View Assignments</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        No recent activities. Check back later for new exams and assignments.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- E-Library Tab -->
        <div id="e-library" class="tab-content">
            <div class="section">
                <h2 class="section-title">E-Library Resources</h2>
                <?php if (count($library_resources) > 0): ?>
                    <div class="library-grid">
                        <?php foreach ($library_resources as $resource): ?>
                            <div class="resource-card">
                                <div class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></div>
                                <div class="resource-details">
                                    Type: <?php echo htmlspecialchars($resource['file_type']); ?><br>
                                    Subject: <?php echo htmlspecialchars($resource['subject']); ?><br>
                                    Uploaded: <?php echo date('M j, Y', strtotime($resource['uploaded_at'])); ?>
                                    <?php if ($resource['file_size']): ?>
                                        <br><span class="file-size">Size: <?php echo htmlspecialchars($resource['file_size']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <a href="download_resource.php?id=<?php echo $resource['id']; ?>" class="view-btn">Download</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        No library resources available for your class at the moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Assignments Tab -->
        <div id="assignments" class="tab-content">
            <div class="section">
                <h2 class="section-title">Pending Assignments</h2>
                <?php if (count($pending_assignments) > 0): ?>
                    <div class="assignment-grid">
                        <?php foreach ($pending_assignments as $assignment): 
                            $is_urgent = strtotime($assignment['deadline']) - time() < 2 * 24 * 60 * 60; // Less than 2 days
                        ?>
                            <div class="assignment-card <?php echo $is_urgent ? 'urgent' : ''; ?>">
                                <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                <div class="assignment-details">
                                    Subject: <?php echo htmlspecialchars($assignment['subject_name']); ?><br>
                                    Deadline: <span class="deadline"><?php echo date('M j, Y g:i A', strtotime($assignment['deadline'])); ?></span><br>
                                    Instructions: <?php echo htmlspecialchars(substr($assignment['instructions'], 0, 100)); ?>...
                                </div>
                                <a href="assignment.php?id=<?php echo $assignment['id']; ?>" class="submit-btn">Submit Work</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        No pending assignments. Great job!
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2 class="section-title">Submitted Assignments</h2>
                <?php if (count($submitted_assignments) > 0): ?>
                    <div class="assignment-grid">
                        <?php foreach ($submitted_assignments as $submission): ?>
                            <div class="assignment-card submitted">
                                <div class="assignment-title"><?php echo htmlspecialchars($submission['title']); ?></div>
                                <div class="assignment-details">
                                    Subject: <?php echo htmlspecialchars($submission['subject_name']); ?><br>
                                    Submitted: <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?><br>
                                    Status: <strong><?php echo htmlspecialchars($submission['status'] ?? 'Submitted'); ?></strong>
                                    <?php if ($submission['grade']): ?>
                                        <br>Grade: <strong><?php echo htmlspecialchars($submission['grade']); ?></strong>
                                    <?php endif; ?>
                                </div>
                                <?php if ($submission['teacher_feedback']): ?>
                                    <div class="assignment-details">
                                        Feedback: <?php echo htmlspecialchars($submission['teacher_feedback']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        No assignments submitted yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Exams Tab (Updated with Group Exams) -->
        <div id="exams" class="tab-content">
            <!-- Single Subject Exams Section -->
            <div class="section">
                <h2 class="section-title">Single Subject Exams</h2>
                <?php if (count($available_single_exams) > 0): ?>
                    <div class="exam-grid">
                        <?php foreach ($available_single_exams as $exam): ?>
                            <div class="exam-card single-exam">
                                <div class="exam-badge">Single Subject</div>
                                <div class="exam-name"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                <div class="exam-details">
                                    Subject: <?php echo htmlspecialchars($exam['subject_name']); ?><br>
Duration: <?php echo htmlspecialchars($exam['duration_minutes'] ?? 'N/A'); ?> minutes                                    Questions: <?php echo htmlspecialchars($exam['objective_count'] + $exam['theory_count']); ?>
                                </div>
                                <a href="exam.php?exam_id=<?php echo $exam['id']; ?>" class="start-btn">
                                    Start Exam
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        No single subject exams available at the moment. Please check back later.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Group Exams Section -->
            <div class="section">
                <h2 class="section-title">Group Exams (UTME Style)</h2>
                <?php if (count($available_group_exams) > 0): ?>
                    <div class="exam-grid">
                        <?php foreach ($available_group_exams as $exam): ?>
                            <div class="exam-card group-exam">
                                <div class="exam-badge">Group Exam</div>
                                <div class="exam-name"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                <div class="exam-details">
                                    Group: <?php echo htmlspecialchars($exam['group_name']); ?><br>
                                    Duration: <?php echo htmlspecialchars($exam['duration_minutes']); ?> minutes<br>
                                    Type: Multiple Subjects Combined
                                </div>
                                <div class="subject-list">
                                    <strong>Subjects included:</strong><br>
                                    <?php
                                    // Get subjects in this group
                                    $stmt = $pdo->prepare("
                                        SELECT s.subject_name 
                                        FROM subject_group_members sgm
                                        JOIN subjects s ON sgm.subject_id = s.id
                                        WHERE sgm.group_id = ?
                                        ORDER BY sgm.display_order
                                    ");
                                    $stmt->execute([$exam['group_id']]);
                                    $subjects = $stmt->fetchAll();
                                    $subject_names = array_map(function($subject) {
                                        return $subject['subject_name'];
                                    }, $subjects);
                                    echo implode(', ', $subject_names);
                                    ?>
                                </div>
                                <a href="group_exam.php?exam_id=<?php echo $exam['id']; ?>" class="start-btn">
                                    Start Group Exam
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        No group exams available at the moment. Please check back later.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Completed Exams Section -->
            <div class="section">
                <h2 class="section-title">Completed Exams</h2>
                <?php if (count($completed_exams) > 0): ?>
                    <div class="exam-grid">
                        <?php foreach ($completed_exams as $exam): ?>
                            <div class="exam-card completed <?php echo $exam['exam_type'] === 'group' ? 'group-exam' : 'single-exam'; ?>">
                                <?php if ($exam['exam_type'] === 'group'): ?>
                                    <div class="exam-badge">Group Exam</div>
                                <?php else: ?>
                                    <div class="exam-badge">Single Subject</div>
                                <?php endif; ?>
                                <div class="exam-name"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                <div class="exam-details">
                                    <?php if ($exam['exam_type'] === 'single'): ?>
                                        Subject: <?php echo htmlspecialchars($exam['subject_name']); ?><br>
                                    <?php endif; ?>
                                    Completed: <?php echo date('M j, Y g:i A', strtotime($exam['end_time'])); ?>
                                </div>
                                <div class="score">
                                    Score: <?php echo htmlspecialchars($exam['score'] ?? 'Pending'); ?>%
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-items">
                        You haven't completed any exams yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <div class="section">
                <h2 class="section-title">Update Your Profile</h2>
                <form class="profile-form" action="update_profile.php" method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password (to confirm changes)</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    
                    <button type="submit" class="update-btn">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            const tabs = document.getElementsByClassName('tab');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content and activate tab
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>