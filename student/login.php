<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

if ($_POST) {
    $admission_number = trim($_POST['admission_number']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM students WHERE admission_number = ?");
    $stmt->execute([$admission_number]);
    $student = $stmt->fetch();
    
    if ($student) {
        // Check if password is hashed (starts with $2y$)
        if (password_verify($password, $student['password'])) {
            // Password is hashed and matches
            login_success($student);
        } elseif ($student['password'] === $password) {
            // Password is plain text and matches - hash it for future use
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $student['id']]);
            login_success($student);
        } else {
            $error = "Invalid admission number or password!";
        }
    } else {
        $error = "Invalid admission number or password!";
    }
}

function login_success($student) {
    $_SESSION['student_id'] = $student['id'];
    $_SESSION['admission_number'] = $student['admission_number'];
    $_SESSION['full_name'] = $student['full_name'];
    $_SESSION['class'] = $student['class'];
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - Mighty School for Valours</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid #e0e0ff;
        }
        
        .school-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .school-name {
            color: #1e3c72;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .tagline {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #1e3c72;
            font-weight: 600;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0ff;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            padding-right: 50px;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #8a2be2;
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1);
            outline: none;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #8a2be2;
            background: rgba(138, 43, 226, 0.1);
        }
        
        .password-toggle i {
            font-size: 18px;
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4);
        }
        
        .error-message {
            background: #ff6b6b;
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .footer {
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
    <!-- Font Awesome for eye icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="school-logo">TCMS</div>
        <h1 class="school-name">Mighty School for Valours</h1>
        <p class="tagline">Computer Based Test Platform</p>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="admission_number">Admission Number</label>
                <input type="text" id="admission_number" name="admission_number" required 
                       placeholder="Enter your admission number" value="<?php echo isset($_POST['admission_number']) ? htmlspecialchars($_POST['admission_number']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                    <button type="button" class="password-toggle" id="passwordToggle">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="login-btn">Login to Dashboard</button>
        </form>
        
        <div class="footer">
            &copy; 2025 Mighty School for Valours. All rights reserved.
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            const passwordIcon = passwordToggle.querySelector('i');
            
            passwordToggle.addEventListener('click', function() {
                // Toggle password visibility
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.className = 'fas fa-eye-slash';
                    passwordToggle.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.className = 'fas fa-eye';
                    passwordToggle.setAttribute('aria-label', 'Show password');
                }
                
                // Keep focus on password field for better UX
                passwordInput.focus();
            });
            
            // Add keyboard support for accessibility
            passwordToggle.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    passwordToggle.click();
                }
            });
        });
    </script>
</body>
</html>