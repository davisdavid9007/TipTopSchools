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

    try {
        // Get group exam details
        $stmt = $pdo->prepare("
            SELECT e.*, sg.id as group_id
            FROM exams e 
            JOIN subject_groups sg ON e.group_id = sg.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$exam_id]);
        $exam = $stmt->fetch();

        if (!$exam) {
            throw new Exception('Exam not found');
        }

        // Get subjects in group
        $stmt = $pdo->prepare("
            SELECT sgm.*, s.subject_name 
            FROM subject_group_members sgm
            JOIN subjects s ON sgm.subject_id = s.id
            WHERE sgm.group_id = ?
        ");
        $stmt->execute([$exam['group_id']]);
        $group_subjects = $stmt->fetchAll();

        // Calculate scores per subject and overall
        $subject_scores = [];
        $total_correct = 0;
        $total_questions = 0;

        foreach ($group_subjects as $subject) {
            $subject_correct = 0;
            $subject_total = $subject['question_count'];
            $total_questions += $subject_total;

            // Get questions for this subject that were answered
            $subject_question_ids = [];
            foreach ($answers as $question_id => $answer) {
                $stmt = $pdo->prepare("SELECT subject_id FROM objective_questions WHERE id = ?");
                $stmt->execute([$question_id]);
                $question = $stmt->fetch();

                if ($question && $question['subject_id'] == $subject['subject_id']) {
                    $subject_question_ids[] = $question_id;
                }
            }

            // Calculate score for this subject
            foreach ($subject_question_ids as $question_id) {
                $stmt = $pdo->prepare("SELECT correct_answer FROM objective_questions WHERE id = ?");
                $stmt->execute([$question_id]);
                $question = $stmt->fetch();

                if ($question && isset($answers[$question_id])) {
                    if (strtoupper(trim($answers[$question_id])) === strtoupper(trim($question['correct_answer']))) {
                        $subject_correct++;
                        $total_correct++;
                    }
                }
            }

            $subject_percentage = ($subject_correct / $subject_total) * 100;
            $subject_scores[$subject['subject_id']] = [
                'subject_name' => $subject['subject_name'],
                'correct' => $subject_correct,
                'total' => $subject_total,
                'percentage' => $subject_percentage,
                'grade' => calculateGrade($subject_percentage)
            ];

            // Insert individual subject result into subject_results table
            $stmt = $pdo->prepare("
                INSERT INTO subject_results 
                (student_id, exam_id, subject_id, objective_score, total_score, percentage, grade, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $student_id,
                $exam_id,
                $subject['subject_id'],
                $subject_correct,
                $subject_total,
                $subject_percentage,
                calculateGrade($subject_percentage)
            ]);
        }

        // Calculate overall score
        $overall_percentage = ($total_correct / $total_questions) * 100;

        // Insert overall exam result into results table
        $stmt = $pdo->prepare("
            INSERT INTO results 
            (student_id, exam_id, objective_score, total_score, percentage, grade, submitted_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $student_id,
            $exam_id,
            $total_correct,
            $total_questions,
            $overall_percentage,
            calculateGrade($overall_percentage)
        ]);

        // Update exam session - using the correct column names from your table structure
        $stmt = $pdo->prepare("
            UPDATE exam_sessions 
            SET status = 'completed', 
                score = ?,
                objective_answers = ?,
                submitted_at = NOW()
            WHERE id = ? AND student_id = ?
        ");

        $stmt->execute([
            $overall_percentage,
            $answers_json, // Store the answers JSON in objective_answers column
            $session_id,
            $student_id
        ]);

        $response = [
            'success' => true,
            'overall_score' => round($overall_percentage, 2),
            'overall_grade' => calculateGrade($overall_percentage),
            'total_correct' => $total_correct,
            'total_questions' => $total_questions,
            'subject_scores' => $subject_scores
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        error_log("Exam submission error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error submitting exam: ' . $e->getMessage()
        ]);
    }
}

function calculateGrade($percentage)
{
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    if ($percentage >= 40) return 'E';
    return 'F';
}
