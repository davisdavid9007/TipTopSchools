<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isStudentLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = $_POST['exam_id'] ?? 0;
    $session_id = $_POST['session_id'] ?? 0;
    $answers_json = $_POST['answers'] ?? '{}';
    $answers = json_decode($answers_json, true);
    $student_id = $_SESSION['student_id'];

    // Log the submission for debugging
    error_log("Exam submission started - Exam ID: $exam_id, Session ID: $session_id, Student ID: $student_id");

    try {
        // Get exam details including total assigned questions
        $stmt = $pdo->prepare("
            SELECT e.*, s.subject_name 
            FROM exams e 
            JOIN subjects s ON e.subject_id = s.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$exam_id]);
        $exam = $stmt->fetch();

        if (!$exam) {
            throw new Exception('Exam not found');
        }

        // Calculate score based on TOTAL assigned questions, not just answered ones
        $total_assigned_questions = (int)$exam['objective_count'];
        $correct_count = 0;
        $answered_questions = 0;

        error_log("Total assigned questions: $total_assigned_questions");
        error_log("Answers received: " . print_r($answers, true));

        // Check each submitted answer
        if (is_array($answers) && !empty($answers)) {
            foreach ($answers as $question_id => $student_answer) {
                $answered_questions++;
                
                // Get the correct answer for this question
                $stmt = $pdo->prepare("SELECT correct_answer FROM objective_questions WHERE id = ?");
                $stmt->execute([$question_id]);
                $question = $stmt->fetch();

                if ($question) {
                    $correct_answer = $question['correct_answer'];
                    error_log("Question ID: $question_id - Student: $student_answer vs Correct: $correct_answer");
                    
                    // Compare answers (case-insensitive)
                    if (strtoupper(trim($student_answer)) === strtoupper(trim($correct_answer))) {
                        $correct_count++;
                        error_log("✓ Correct answer for question $question_id");
                    } else {
                        error_log("✗ Wrong answer for question $question_id");
                    }
                } else {
                    error_log("Question not found: $question_id");
                }
            }
        } else {
            error_log("No answers submitted or answers array is empty");
        }

        // Calculate score based on TOTAL assigned questions (not just answered)
        $score_percentage = ($correct_count / $total_assigned_questions) * 100;
        
        // Determine grade based on percentage
        $grade = calculateGrade($score_percentage);

        error_log("Score calculation: $correct_count / $total_assigned_questions = $score_percentage% ($grade)");

        // Update exam session - USING ACTUAL TABLE COLUMNS
        $stmt = $pdo->prepare("
            UPDATE exam_sessions 
            SET status = 'completed', 
                objective_answers = ?,
                score = ?,
                submitted_at = NOW()
            WHERE id = ? AND student_id = ?
        ");
        
        $update_result = $stmt->execute([
            $answers_json, // Store the answers in objective_answers column
            $score_percentage,
            $session_id,
            $student_id
        ]);

        if (!$update_result) {
            $error_info = $stmt->errorInfo();
            throw new Exception("Failed to update exam session: " . $error_info[2]);
        }

        error_log("Exam session updated successfully");

        // INSERT INTO RESULTS TABLE
        $stmt = $pdo->prepare("
            INSERT INTO results 
            (student_id, exam_id, objective_score, theory_score, total_score, percentage, grade, submitted_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $theory_score = 0; // Assuming no theory score for now
        $total_score = $score_percentage; // Or calculate differently if needed
        
        $insert_result = $stmt->execute([
            $student_id,
            $exam_id,
            $score_percentage,
            $theory_score,
            $total_score,
            $score_percentage,
            $grade
        ]);

        if (!$insert_result) {
            $error_info = $stmt->errorInfo();
            throw new Exception("Failed to insert into results table: " . $error_info[2]);
        }

        error_log("Result inserted successfully with ID: " . $pdo->lastInsertId());

        // Check if there are theory questions
        $has_theory = ($exam['theory_count'] > 0);

        $response = [
            'success' => true,
            'score' => round($score_percentage, 2),
            'grade' => $grade,
            'correct' => $correct_count,
            'total' => $total_assigned_questions,
            'answered' => $answered_questions,
            'has_theory' => $has_theory,
            'exam_id' => $exam_id
        ];

        error_log("Final response: " . print_r($response, true));
        echo json_encode($response);

    } catch (Exception $e) {
        error_log("Exam submission error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error submitting exam: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    if ($percentage >= 40) return 'E';
    return 'F';
}
?>