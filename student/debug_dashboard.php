<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

echo "<h1>Debug Info</h1>";

// Check session
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if student is logged in
if (isStudentLoggedIn()) {
    echo "<p style='color: green;'>✅ Student is logged in</p>";
    echo "<p>Student ID: " . $_SESSION['student_id'] . "</p>";
    echo "<p>Student Name: " . $_SESSION['student_name'] . "</p>";
    echo "<p>Student Class: " . $_SESSION['student_class'] . "</p>";
    echo "<p>Admission No: " . $_SESSION['student_admission_no'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Student is NOT logged in</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

// Test database connection
try {
    $test_query = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ Database connection working</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    exit();
}

// Test available exams query
$student_class = $_SESSION['student_class'];
$student_id = $_SESSION['student_id'];

echo "<h3>Testing Available Exams Query:</h3>";
echo "<p>Student Class: $student_class</p>";
echo "<p>Student ID: $student_id</p>";

$stmt = $pdo->prepare("
    SELECT e.*, s.subject_name 
    FROM exams e 
    JOIN subjects s ON e.subject_id = s.id 
    WHERE e.class = ? AND e.is_active = 1 
    AND e.id NOT IN (
        SELECT exam_id FROM exam_sessions 
        WHERE student_id = ? AND status = 'completed'
    )
    ORDER BY e.created_at DESC
");
$stmt->execute([$student_class, $student_id]);
$available_exams = $stmt->fetchAll();

echo "<p>Available exams found: " . count($available_exams) . "</p>";
echo "<pre>";
print_r($available_exams);
echo "</pre>";

echo "<p><a href='dashboard.php'>Go to Real Dashboard</a></p>";
?>