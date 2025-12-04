<?php
session_start();
require_once '../includes/config.php';

if ($_POST) {
    $sessionId = $_POST['session_id'];
    $examId = $_POST['exam_id'];
    
    // Collect theory answers
    $theoryAnswers = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'answer_') === 0) {
            $questionId = str_replace('answer_', '', $key);
            $theoryAnswers[$questionId] = $value;
        }
    }
    
    // Update exam session with theory answers
    $stmt = $pdo->prepare("
        UPDATE exam_sessions 
        SET theory_answers = ?, end_time = NOW(), status = 'completed'
        WHERE id = ?
    ");
    $stmt->execute([json_encode($theoryAnswers), $sessionId]);
    
    echo json_encode(['success' => true]);
}
?>