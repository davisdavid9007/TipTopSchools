<?php
session_start();
require_once '../includes/config.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $resource_id = $_GET['id'];
    
    // Get resource details
    $stmt = $pdo->prepare("SELECT * FROM library_resources WHERE id = ?");
    $stmt->execute([$resource_id]);
    $resource = $stmt->fetch();
    
    if ($resource && file_exists($resource['file_path'])) {
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($resource['file_path']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($resource['file_path']));
        
        // Clear output buffer
        flush();
        
        // Read the file and output it
        readfile($resource['file_path']);
        exit;
    } else {
        // Resource not found
        header("Location: dashboard.php?error=Resource not found");
        exit;
    }
} else {
    header("Location: dashboard.php");
    exit;
}
?>