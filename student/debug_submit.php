<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isStudentLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

echo "<h1>Debug Submit Exam</h1>";
echo "<pre>";

// Check POST data
echo "POST Data:\n";
print_r($_POST);

// Check session
echo "\nSession Data:\n";
print_r($_SESSION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_SESSION['student_id'];
    $exam_id = $_POST['exam_id'] ?? 0;
    $session_id = $_POST['session_id'] ?? 0;
    $answers = json_decode($_POST['answers'] ?? '{}', true);
    
    echo "\nProcessing Data:\n";
    echo "Student ID: $student_id\n";
    echo "Exam ID: $exam_id\n";
    echo "Session ID: $session_id\n";
    echo "Answers:\n";
    print_r($answers);
    
    try {
        // Calculate score
        $score = 0;
        $total_questions = 0;
        $correct_answers = 0;

        foreach ($answers as $question_id => $student_answer) {
            $stmt = $pdo->prepare("SELECT correct_answer, marks FROM objective_questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch();

            if ($question) {
                $total_questions++;
                if (strtoupper($student_answer) === strtoupper($question['correct_answer'])) {
                    $score += $question['marks'];
                    $correct_answers++;
                }
            }
        }

        $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;

        // Determine grade
        if ($percentage >= 80) $grade = 'A';
        elseif ($percentage >= 70) $grade = 'B';
        elseif ($percentage >= 60) $grade = 'C';
        elseif ($percentage >= 50) $grade = 'D';
        else $grade = 'F';

        echo "\nScoring Results:\n";
        echo "Total Questions: $total_questions\n";
        echo "Correct Answers: $correct_answers\n";
        echo "Score: $score\n";
        echo "Percentage: $percentage%\n";
        echo "Grade: $grade\n";

        // Update exam session
        $stmt = $pdo->prepare("UPDATE exam_sessions SET status = 'completed', objective_answers = ?, score = ? WHERE id = ? AND student_id = ?");
        $result = $stmt->execute([json_encode($answers), $percentage, $session_id, $student_id]);
        
        echo "Exam session update: " . ($result ? "SUCCESS" : "FAILED") . "\n";

        // Save to results table
        $stmt = $pdo->prepare("INSERT INTO results (student_id, exam_id, objective_score, total_score, percentage, grade) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$student_id, $exam_id, $score, $score, $percentage, $grade]);
        
        echo "Results insert: " . ($result ? "SUCCESS" : "FAILED") . "\n";

        echo "\n✅ Exam submitted successfully!";
        
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        echo "Error in file: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    }
}

echo "</pre>";
?>