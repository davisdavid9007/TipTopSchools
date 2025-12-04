<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Use your correct authentication function
if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle student addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $admission_number = $_POST['admission_number'];
    $full_name = $_POST['full_name'];
    $class = $_POST['class'];
    $password = $_POST['password'];
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO students (admission_number, password, class, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admission_number, $hashed_password, $class, $full_name]);
        $message = "Student added successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "Admission number already exists!";
            $message_type = "error";
        } else {
            $message = "Error adding student: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle student editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $student_id = $_POST['student_id'];
    $admission_number = $_POST['admission_number'];
    $full_name = $_POST['full_name'];
    $class = $_POST['class'];
    $password = $_POST['password'];
    
    try {
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE students SET admission_number = ?, full_name = ?, class = ?, password = ? WHERE id = ?");
            $stmt->execute([$admission_number, $full_name, $class, $hashed_password, $student_id]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE students SET admission_number = ?, full_name = ?, class = ? WHERE id = ?");
            $stmt->execute([$admission_number, $full_name, $class, $student_id]);
        }
        $message = "Student updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "Admission number already exists!";
            $message_type = "error";
        } else {
            $message = "Error updating student: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle student deletion
if (isset($_GET['delete'])) {
    $student_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $message = "Student deleted successfully!";
    $message_type = "success";
}

// Get student data for editing
$edit_student = null;
if (isset($_GET['edit'])) {
    $student_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $edit_student = $stmt->fetch();
}

// Get filter parameters
$filter_class = $_GET['class'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT * FROM students WHERE 1=1";
$params = [];

if (!empty($filter_class)) {
    $query .= " AND class = ?";
    $params[] = $filter_class;
}

if (!empty($filter_search)) {
    $query .= " AND (admission_number LIKE ? OR full_name LIKE ?)";
    $search_term = "%$filter_search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY class, admission_number";

// Get filtered students
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get unique classes for filter dropdown
$classes = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Tip Top Schools</title>
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
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-bottom: 3px solid #8a2be2;
        }
        .school-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .school-logo img {
            max-width: 50px;
            height: auto;
        }
        .school-logo span {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e3c72;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .management-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .management-card h2 {
            color: #1e3c72;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2.2rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-group input,
        .form-group select {
            flex: 1;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            min-width: 200px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.2);
        }
        .password-wrapper {
            position: relative;
            flex: 1;
        }
        .password-wrapper input {
            width: 100%;
            padding-right: 3rem;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1.2rem;
            padding: 0.25rem;
            border-radius: 4px;
            transition: color 0.3s ease, background-color 0.3s ease;
        }
        .toggle-password:hover {
            color: #8a2be2;
            background-color: rgba(138, 43, 226, 0.1);
        }
        .add-btn, .update-btn {
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            font-size: 1.1rem;
            border: 2px solid transparent;
        }
        .add-btn:hover, .update-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
        }
        .cancel-btn {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            border: 2px solid transparent;
        }
        .cancel-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
        }
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .students-table th,
        .students-table td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .students-table th {
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .students-table tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            transition: transform 0.2s ease;
        }
        .delete-btn {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            padding: 0.7rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid transparent;
        }
        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        .edit-btn {
            background: linear-gradient(45deg, #ffc107, #e0a800);
            color: white;
            border: none;
            padding: 0.7rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid transparent;
            text-decoration: none;
            display: inline-block;
        }
        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
        }
        .message {
            padding: 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            border: 2px solid transparent;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 0.8rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            transition: background 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            color: white;
        }
        .filter-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0e6ff 100%);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            border: 2px solid #e9ecef;
        }
        .filter-form {
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }
        .filter-group label {
            font-weight: 600;
            color: #1e3c72;
            font-size: 1rem;
        }
        .filter-input {
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            min-width: 200px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .filter-input:focus {
            outline: none;
            border-color: #8a2be2;
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.2);
        }
        .filter-btn {
            background: linear-gradient(45deg, #1e3c72, #8a2be2);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            font-size: 1.1rem;
            border: 2px solid transparent;
        }
        .filter-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
        }
        .reset-btn {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 1.1rem;
            border: 2px solid transparent;
        }
        .reset-btn:hover {
            transform: translateY(-3px);
            color: white;
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
        }
        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .results-info {
            background: linear-gradient(135deg, #e7f3ff 0%, #d6e8ff 100%);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 5px solid #1e3c72;
            font-size: 1.1rem;
        }
        .results-info span {
            font-weight: 700;
            color: #1e3c72;
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
            background: #f8f9fa;
            border-radius: 15px;
            margin: 1rem 0;
            border: 2px dashed #ddd;
        }
        .no-results a {
            color: #8a2be2;
            text-decoration: none;
            font-weight: 600;
        }
        .no-results a:hover {
            text-decoration: underline;
        }
        .class-badge {
            font-weight: 700;
            color: #8a2be2;
            background: #f0e6ff;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            border: 2px solid #8a2be2;
        }
        .password-note {
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
            margin-top: 0.5rem;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        @media (max-width: 768px) {
            .form-group {
                flex-direction: column;
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-actions {
                justify-content: center;
            }
            .students-table {
                font-size: 0.9rem;
            }
            .students-table th,
            .students-table td {
                padding: 0.8rem;
            }
            .form-actions {
                flex-direction: column;
            }
            .password-wrapper {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-logo">       
            <img src="../assets/logo.png" alt="School Logo">
            <span>Tip Top Schools - Manage Students</span>
        </div>
    </div>

    <div class="container">
        <?php if ($edit_student): ?>
            <!-- Edit Student Form -->
            <div class="management-card">
                <h2>✏️ Edit Student</h2>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $edit_student['id']; ?>">
                    <div class="form-group">
                        <input type="text" name="admission_number" placeholder="📝 Admission Number" 
                               value="<?php echo htmlspecialchars($edit_student['admission_number']); ?>" required>
                        <input type="text" name="full_name" placeholder="👤 Full Name" 
                               value="<?php echo htmlspecialchars($edit_student['full_name']); ?>" required>
                        <select name="class" required>
                            <option value="">🎓 Select Class</option>
                            <option value="JSS 1" <?php echo $edit_student['class'] == 'JSS 1' ? 'selected' : ''; ?>>JSS 1</option>
                            <option value="JSS 2" <?php echo $edit_student['class'] == 'JSS 2' ? 'selected' : ''; ?>>JSS 2</option>
                            <option value="JSS 3" <?php echo $edit_student['class'] == 'JSS 3' ? 'selected' : ''; ?>>JSS 3</option>
                            <option value="SS 1" <?php echo $edit_student['class'] == 'SS 1' ? 'selected' : ''; ?>>SS 1</option>
                            <option value="SS 2" <?php echo $edit_student['class'] == 'SS 2' ? 'selected' : ''; ?>>SS 2</option>
                            <option value="SS 3" <?php echo $edit_student['class'] == 'SS 3' ? 'selected' : ''; ?>>SS 3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="password-wrapper">
                            <input type="password" name="password" id="edit-password" placeholder="🔒 New Password (leave blank to keep current)">
                            <button type="button" class="toggle-password" data-target="edit-password">👁️</button>
                        </div>
                        <div class="password-note">Leave password field blank if you don't want to change it</div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="edit_student" class="update-btn">💾 Update Student</button>
                        <a href="manage_students.php" class="cancel-btn">❌ Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Add Student Form -->
            <div class="management-card">
                <h2>👥 Add New Student</h2>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="admission_number" placeholder="📝 Admission Number" required>
                        <input type="text" name="full_name" placeholder="👤 Full Name" required>
                        <select name="class" required>
                            <option value="">🎓 Select Class</option>
                            <option value="JSS 1">JSS 1</option>
                            <option value="JSS 2">JSS 2</option>
                            <option value="JSS 3">JSS 3</option>
                            <option value="SS 1">SS 1</option>
                            <option value="SS 2">SS 2</option>
                            <option value="SS 3">SS 3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="password-wrapper">
                            <input type="password" name="password" id="add-password" placeholder="🔒 Password" required>
                            <button type="button" class="toggle-password" data-target="add-password">👁️</button>
                        </div>
                        <div class="password-note">Set a secure password for the student</div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_student" class="add-btn">➕ Add Student</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="management-card">
            <h2>🔍 Filter Students</h2>
            
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="class">🎓 Filter by Class:</label>
                        <select name="class" id="class" class="filter-input">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class); ?>" 
                                    <?php echo $filter_class === $class ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">🔎 Search by Name/Admission No:</label>
                        <input type="text" name="search" id="search" class="filter-input" 
                               placeholder="Enter name or admission number..." 
                               value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn">🔍 Apply Filters</button>
                        <a href="manage_students.php" class="reset-btn">🔄 Reset</a>
                    </div>
                </form>
            </div>

            <?php if (!empty($filter_class) || !empty($filter_search)): ?>
                <div class="results-info">
                    📊 Showing 
                    <?php if (!empty($filter_class)): ?>
                        <span><?php echo htmlspecialchars($filter_class); ?></span> class
                    <?php endif; ?>
                    <?php if (!empty($filter_class) && !empty($filter_search)): ?>
                        and 
                    <?php endif; ?>
                    <?php if (!empty($filter_search)): ?>
                        matching "<span><?php echo htmlspecialchars($filter_search); ?></span>"
                    <?php endif; ?>
                    - Found <span><?php echo count($students); ?></span> student(s)
                </div>
            <?php endif; ?>
            
            <table class="students-table">
                <thead>
                    <tr>
                        <th>Admission Number</th>
                        <th>Full Name</th>
                        <th>Class</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="no-results">
                                    <?php if (!empty($filter_class) || !empty($filter_search)): ?>
                                        <p>❌ No students found matching your filters.</p>
                                        <p><small>Try adjusting your search criteria or <a href="manage_students.php">reset filters</a>.</small></p>
                                    <?php else: ?>
                                        <p>📝 No students found. Start by adding some students above.</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['admission_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td>
                                    <span class="class-badge">
                                        <?php echo htmlspecialchars($student['class']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="manage_students.php?edit=<?php echo $student['id']; ?>" class="edit-btn">✏️ Edit</a>
                                        <button class="delete-btn" onclick="deleteStudent(<?php echo $student['id']; ?>)">🗑️ Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="index.php" class="back-link">← Back to Dashboard</a>
    </div>

    <script>
        function deleteStudent(studentId) {
            if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                window.location.href = 'manage_students.php?delete=' + studentId;
            }
        }

        // Password visibility toggle functionality
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.textContent = '🙈';
                } else {
                    passwordInput.type = 'password';
                    this.textContent = '👁️';
                }
            });
        });

        // Auto-submit form when class selection changes (optional feature)
        document.getElementById('class').addEventListener('change', function() {
            if (this.value !== '') {
                this.form.submit();
            }
        });

        // Clear search field when reset button is clicked
        document.querySelector('.reset-btn').addEventListener('click', function() {
            document.getElementById('search').value = '';
            document.getElementById('class').selectedIndex = 0;
        });

        // Password strength indicator (optional enhancement)
        document.querySelectorAll('input[type="password"]').forEach(input => {
            input.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                this.style.borderColor = strength.color;
            });
        });

        function checkPasswordStrength(password) {
            if (password.length === 0) return { color: '#ddd', text: '' };
            if (password.length < 6) return { color: '#e74c3c', text: 'Weak' };
            if (password.length < 8) return { color: '#f39c12', text: 'Medium' };
            return { color: '#27ae60', text: 'Strong' };
        }
    </script>
</body>

</html>
