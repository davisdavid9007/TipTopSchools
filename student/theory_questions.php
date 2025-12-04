<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isStudentLoggedIn()) {
    header("Location: login.php");
    exit();
}

$exam_id = $_GET['exam_id'] ?? 0;
$student_id = $_SESSION['student_id'];

// Get exam details
$stmt = $pdo->prepare("
    SELECT e.*, s.subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject_id = s.id 
    WHERE e.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header("Location: dashboard.php");
    exit();
}

// Get theory questions for this exam
$topics_json = $exam['topics'];
$topics_array = json_decode($topics_json, true);
$question_limit = (int)$exam['theory_count'];

if (!empty($topics_array) && is_array($topics_array)) {
    $topic_ids = implode(',', array_map('intval', $topics_array));
    $query = "
        SELECT tq.* 
        FROM theory_questions tq 
        WHERE tq.subject_id = {$exam['subject_id']}
        AND tq.topic_id IN ($topic_ids)
        ORDER BY RAND() 
        LIMIT $question_limit
    ";
    $stmt = $pdo->query($query);
} else {
    $query = "
        SELECT tq.* 
        FROM theory_questions tq 
        WHERE tq.subject_id = {$exam['subject_id']}
        ORDER BY RAND() 
        LIMIT $question_limit
    ";
    $stmt = $pdo->query($query);
}

$theory_questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theory Questions - <?php echo htmlspecialchars($exam['exam_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #4a90e2;
        }
        .header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .instructions h3 {
            color: #856404;
            margin-bottom: 1rem;
        }
        .instructions ul {
            margin-left: 1.5rem;
            color: #856404;
        }
        .instructions li {
            margin-bottom: 0.5rem;
        }
        .question-section {
            margin-bottom: 3rem;
        }
        .question-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #4a90e2;
            padding-bottom: 0.5rem;
        }
        .theory-question {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }
        .question-number {
            background: #4a90e2;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 1rem;
            display: inline-block;
        }
        .question-text {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        .question-file {
            margin-top: 1rem;
            padding: 1rem;
            background: #e7f3ff;
            border-radius: 5px;
            border-left: 4px solid #4a90e2;
        }
        .marks {
            font-weight: bold;
            color: #28a745;
            margin-top: 0.5rem;
        }
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }
        .btn-primary {
            background: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background: #218838;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .no-questions {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-style: italic;
        }
        .print-only {
            display: none;
        }
        @media print {
            .no-print {
                display: none;
            }
            .print-only {
                display: block;
            }
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                padding: 0;
            }
            .theory-question {
                page-break-inside: avoid;
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Theory Questions</h1>
            <p><strong>Exam:</strong> <?php echo htmlspecialchars($exam['exam_name']); ?> | 
               <strong>Subject:</strong> <?php echo htmlspecialchars($exam['subject_name']); ?></p>
        </div>

        <div class="instructions">
            <h3>📋 Important Instructions</h3>
            <ul>
                <li>Write your answers clearly on the provided answer sheet</li>
                <li>Show all working where necessary</li>
                <li>Write the question number for each answer</li>
                <li>Use blue or black ink only</li>
                <li>Allocate your time wisely among the questions</li>
            </ul>
        </div>

        <div class="question-section">
            <h2>Theory Questions (<?php echo count($theory_questions); ?> Questions)</h2>
            
            <?php if (empty($theory_questions)): ?>
                <div class="no-questions">
                    <p>No theory questions available for this exam.</p>
                    <p>You have completed the objective section successfully.</p>
                </div>
            <?php else: ?>
                <?php foreach ($theory_questions as $index => $question): ?>
                <div class="theory-question">
                    <div class="question-number">Question <?php echo $index + 1; ?></div>
                    
                    <?php if (!empty($question['question_text'])): ?>
                    <div class="question-text"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($question['question_file'])): ?>
                    <div class="question-file">
                        <strong>Refer to attached file:</strong> <?php echo htmlspecialchars($question['question_file']); ?>
                        <br><small>(The file contains diagrams, images, or additional question details)</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="marks">Marks: <?php echo $question['marks']; ?></div>
                    
                    <div style="margin-top: 2rem; height: 150px; border: 1px dashed #ccc; padding: 1rem;">
                        <em>Space for your answer...</em>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Questions</button>
            <a href="dashboard.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
        </div>

        <div class="print-only">
            <p><strong>Student:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            <p><strong>Admission No:</strong> <?php echo htmlspecialchars($_SESSION['admission_number']); ?></p>
            <p><strong>Class:</strong> <?php echo htmlspecialchars($_SESSION['class']); ?></p>
            <p><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
        
        // Prevent going back to exam
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>