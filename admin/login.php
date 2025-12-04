<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// If already logged in as admin, redirect to dashboard
if (isAdminLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password!";
    } else {
        if (adminLogin($pdo, $username, $password)) {
            // Login successful - redirect to dashboard
            header("Location: login.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Tip Top Schools</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #8a2be2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        .school-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .school-logo img {
            max-width: 80px;
            height: auto;
            margin-bottom: 1rem;
        }
        .school-name {
            color: #1e3c72;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .system-name {
            color: #8a2be2;
            font-size: 1.4rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .tagline {
            color: #666;
            font-size: 1rem;
            font-style: italic;
        }
        .login-title {
            text-align: center;
            color: #1e3c72;
            margin-bottom: 2rem;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1e3c72;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.2);
        }
        .login-btn {
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            border: none;
            padding: 1.2rem 2rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1rem;
        }
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
        }
        .error-message {
            background: #ffe6e6;
            color: #cc0000;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
            border: 2px solid #ffcccc;
            font-weight: 600;
        }
        .back-link {
            text-align: center;
            display: block;
            color: #8a2be2;
            text-decoration: none;
            margin-top: 1rem;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #1e3c72;
            text-decoration: underline;
        }
        .demo-credentials {
            background: #f0e6ff;
            border: 2px solid #8a2be2;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        .demo-credentials h4 {
            color: #1e3c72;
            margin-bottom: 0.5rem;
        }
        .demo-credentials p {
            color: #1e3c72;
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }
        .input-icon {
            position: relative;
        }
        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8a2be2;
        }
        .input-icon .form-control {
            padding-left: 45px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="school-header">
            <div class="school-logo">
                <img src="../assets/logo.png" alt="School Logo">
            </div>
            <div class="school-name">Tip Top Schools</div>
            <div class="system-name">Admin Portal</div>
            <div class="tagline">Stand Out and Lead</div>
        </div>

        <h2 class="login-title">🔐 Admin Login</h2>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Username:</label>
                <div class="input-icon">
                    <i>👤</i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password:</label>
                <div class="input-icon">
                    <i>🔒</i>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="login-btn">🚀 Login to Admin Panel</button>
        </form>

        <a href="../index.php" class="back-link">← Back to Main Site</a>
    </div>
</body>

</html>
