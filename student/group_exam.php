<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isStudentLoggedIn()) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$exam_id = $_GET['exam_id'] ?? 0;

// Get group exam details
$stmt = $pdo->prepare("
    SELECT e.*, sg.group_name, sg.total_duration_minutes
    FROM exams e 
    JOIN subject_groups sg ON e.group_id = sg.id 
    WHERE e.id = ? AND e.is_active = 1 AND e.exam_type = 'group'
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header("Location: dashboard.php");
    exit();
}

// Get subjects in this group
$stmt = $pdo->prepare("
    SELECT sgm.*, s.subject_name 
    FROM subject_group_members sgm
    JOIN subjects s ON sgm.subject_id = s.id
    WHERE sgm.group_id = ? 
    ORDER BY sgm.display_order
");
$stmt->execute([$exam['group_id']]);
$group_subjects = $stmt->fetchAll();

// Check if student has already taken this exam
$stmt = $pdo->prepare("SELECT * FROM exam_sessions WHERE student_id = ? AND exam_id = ? AND status = 'completed'");
$stmt->execute([$student_id, $exam_id]);
if ($stmt->fetch()) {
    header("Location: dashboard.php");
    exit();
}

// Check for existing exam session or create new one
$stmt = $pdo->prepare("SELECT * FROM exam_sessions WHERE student_id = ? AND exam_id = ? AND status = 'in_progress'");
$stmt->execute([$student_id, $exam_id]);
$exam_session = $stmt->fetch();

if (!$exam_session) {
    // Create new exam session
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+{$exam['duration_minutes']} minutes"));

    $stmt = $pdo->prepare("INSERT INTO exam_sessions (student_id, exam_id, start_time, end_time, status) VALUES (?, ?, ?, ?, 'in_progress')");
    $stmt->execute([$student_id, $exam_id, $start_time, $end_time]);
    $session_id = $pdo->lastInsertId();
} else {
    $session_id = $exam_session['id'];
    $start_time = $exam_session['start_time'];
    $end_time = $exam_session['end_time'];
}

// Get objective questions for all subjects in this group exam - SIMPLIFIED VERSION
$all_questions = [];
$subject_questions_count = [];

foreach ($group_subjects as $subject) {
    $subject_id = $subject['subject_id'];
    $question_limit = (int)$subject['question_count'];

    // Simple approach: Get all questions for this subject and then limit in PHP
    $query = "
        SELECT oq.* 
        FROM objective_questions oq 
        WHERE oq.subject_id = ?
        ORDER BY RAND()
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$subject_id]);
    $all_subject_questions = $stmt->fetchAll();

    // Take only the required number of questions
    $questions = array_slice($all_subject_questions, 0, $question_limit);

    // Shuffle the questions array to randomize question order
    shuffle($questions);

    // Shuffle options for each question
    foreach ($questions as $index => $question) {
        $options = [
            'A' => $question['option_a'],
            'B' => $question['option_b'],
            'C' => $question['option_c'],
            'D' => $question['option_d']
        ];

        // Shuffle the options while keeping the keys
        $keys = array_keys($options);
        shuffle($keys);

        $shuffled_options = [];
        foreach ($keys as $key) {
            $shuffled_options[$key] = $options[$key];
        }

        $questions[$index]['shuffled_options'] = $shuffled_options;
        $questions[$index]['shuffled_keys'] = array_keys($shuffled_options);
    }

    $all_questions[$subject_id] = $questions;
    $subject_questions_count[$subject_id] = count($questions);

    // Log for debugging
    error_log("Subject {$subject_id}: Loaded " . count($questions) . " questions");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Exam - <?php echo htmlspecialchars($exam['exam_name']); ?></title>

    <!-- MathJax Configuration -->
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [
                    ['$', '$'],
                    ['\\(', '\\)']
                ],
                displayMath: [
                    ['$$', '$$'],
                    ['\\[', '\\]']
                ],
                processEscapes: true,
                processEnvironments: true
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre'],
                ignoreHtmlClass: 'tex-ignore',
                processHtmlClass: 'tex-process'
            },
            startup: {
                ready: function() {
                    MathJax.startup.defaultReady();
                    renderAllMathWithRetry();
                }
            }
        };

        function renderAllMathWithRetry(retryCount = 0) {
            if (!window.MathJax) {
                if (retryCount < 5) {
                    setTimeout(() => renderAllMathWithRetry(retryCount + 1), 1000);
                }
                return;
            }

            MathJax.typesetPromise()
                .then(() => {
                    console.log('Math rendering completed');
                })
                .catch(err => {
                    console.error('Math rendering failed:', err);
                    if (retryCount < 3) {
                        setTimeout(() => renderAllMathWithRetry(retryCount + 1), 500);
                    }
                });
        }

        function renderMathForQuestion(questionElement, questionNumber) {
            if (!window.MathJax) return;

            setTimeout(() => {
                MathJax.typesetClear([questionElement]);
                MathJax.typesetPromise([questionElement])
                    .catch((err) => {
                        console.error(`Math rendering failed for question ${questionNumber}:`, err);
                    });
            }, 300);
        }

        function loadMathJax() {
            const script = document.createElement('script');
            script.id = 'MathJax-script';
            script.async = true;

            script.src = '../assets/mathjax/es5/tex-mml-chtml.js';
            window.mathJaxSource = 'local';

            script.onerror = function() {
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js';
                window.mathJaxSource = 'cdn';
            };

            document.head.appendChild(script);
        }

        loadMathJax();
    </script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f8f9fa;
            padding-top: 120px;
        }

        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .exam-info h2 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .exam-info p {
            color: #666;
            margin-bottom: 0.25rem;
        }

        .timer-container {
            background: #ff6b6b;
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            text-align: center;
            min-width: 150px;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .timer-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .exam-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .question {
            margin-bottom: 2rem;
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
            margin-bottom: 1.5rem;
            line-height: 1.6;
            min-height: 80px;
        }

        .math-container {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            overflow-x: auto;
        }

        .options {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option:hover {
            border-color: #4a90e2;
            background: #f8f9fa;
        }

        .option.selected {
            border-color: #4a90e2;
            background: #e7f3ff;
        }

        .option input {
            margin-right: 1rem;
        }

        .option-text {
            flex: 1;
        }

        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }

        .nav-btn {
            background: #4a90e2;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .nav-btn:hover {
            background: #357abd;
        }

        .nav-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .question-counter {
            color: #666;
            font-weight: bold;
        }

        .submit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-btn:hover {
            background: #218838;
        }

        .subject-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .subject-tab {
            padding: 0.75rem 1.5rem;
            background: #e9ecef;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .subject-tab.active {
            background: #4a90e2;
            color: white;
            border-color: #357abd;
        }

        .subject-tab.answered {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .subject-content {
            display: none;
        }

        .subject-content.active {
            display: block;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .progress-bar {
            width: 100%;
            height: 5px;
            background: #e0e0e0;
            border-radius: 5px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: #4a90e2;
            transition: width 0.3s ease;
        }

        .answered-count {
            text-align: center;
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="fixed-header">
        <div class="exam-info">
            <h2><?php echo htmlspecialchars($exam['exam_name']); ?></h2>
            <p><strong>Group:</strong> <?php echo htmlspecialchars($exam['group_name']); ?></p>
            <p><strong>Current Subject:</strong> <span id="currentSubject"><?php echo $group_subjects[0]['subject_name']; ?></span></p>
        </div>
        <div class="timer-container">
            <div class="timer" id="timer">00:00:00</div>
            <div class="timer-label">TIME REMAINING</div>
        </div>
    </div>

    <div class="container">
        <form id="examForm">
            <div class="exam-card">
                <?php if (empty($group_subjects)): ?>
                    <div class="warning">
                        <h3>⚠️ No Subjects Available</h3>
                        <p>There are no subjects configured for this group exam. Please contact your administrator.</p>
                    </div>
                <?php else: ?>
                    <!-- Subject Tabs -->
                    <div class="subject-tabs" id="subjectTabs">
                        <?php foreach ($group_subjects as $index => $subject): ?>
                            <div class="subject-tab <?= $index === 0 ? 'active' : '' ?>"
                                onclick="switchSubject(<?= $subject['subject_id'] ?>, <?= $index ?>)"
                                id="tab_<?= $subject['subject_id'] ?>">
                                <?= htmlspecialchars($subject['subject_name']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Progress Info -->
                    <div class="progress-info">
                        <span id="subjectProgress">Subject 1 of <?= count($group_subjects) ?></span>
                        <span id="globalProgress">Total: 0/<?= array_sum($subject_questions_count) ?> answered</span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-bar">
                        <div class="progress" id="progressBar" style="width: <?= (1 / count($group_subjects)) * 100; ?>%"></div>
                    </div>

                    <!-- Questions Container -->
                    <div id="questionsContainer">
                        <?php foreach ($group_subjects as $subject_index => $subject): ?>
                            <div class="subject-content <?= $subject_index === 0 ? 'active' : '' ?>"
                                id="subject_<?= $subject['subject_id'] ?>">

                                <?php if (empty($all_questions[$subject['subject_id']])): ?>
                                    <div class="warning">
                                        <h3>⚠️ No Questions Available</h3>
                                        <p>No questions found for <?= htmlspecialchars($subject['subject_name']) ?>. Please contact your administrator.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($all_questions[$subject['subject_id']] as $q_index => $question): ?>
                                        <div class="question-page" id="question_<?= $subject['subject_id'] ?>_<?= $q_index + 1 ?>" style="<?= $q_index === 0 ? '' : 'display: none;' ?>">
                                            <div class="question">
                                                <div class="question-number">
                                                    <?= htmlspecialchars($subject['subject_name']) ?> - Question <?= $q_index + 1 ?>
                                                </div>
                                                <div class="question-text">
                                                    <?php
                                                    $question_text = $question['question_text'];
                                                    $question_text = htmlspecialchars($question_text);
                                                    $question_text = preg_replace('/(\$\$.*?\$\$|\$.*?\$)/', ' $1 ', $question_text);
                                                    echo $question_text;
                                                    ?>
                                                </div>
                                                <div class="options">
                                                    <?php
                                                    $shuffled_keys = $question['shuffled_keys'];
                                                    $shuffled_options = $question['shuffled_options'];
                                                    ?>
                                                    <?php foreach ($shuffled_keys as $key_index => $key): ?>
                                                        <label class="option" for="option_<?= $question['id'] ?>_<?= $key ?>">
                                                            <input type="radio"
                                                                id="option_<?= $question['id'] ?>_<?= $key ?>"
                                                                name="question_<?= $question['id'] ?>"
                                                                value="<?= $key ?>"
                                                                data-subject="<?= $subject['subject_id'] ?>"
                                                                data-question-index="<?= $q_index ?>">
                                                            <div class="option-text">
                                                                <strong><?= $key ?>.</strong>
                                                                <?php
                                                                $option_text = $shuffled_options[$key];
                                                                $option_text = htmlspecialchars($option_text);
                                                                $option_text = preg_replace('/(\$\$.*?\$\$|\$.*?\$)/', ' $1 ', $option_text);
                                                                echo $option_text;
                                                                ?>
                                                            </div>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="navigation">
                                                <button type="button" class="nav-btn" onclick="previousQuestion(<?= $subject['subject_id'] ?>)" id="prevBtn_<?= $subject['subject_id'] ?>" style="<?= $q_index === 0 ? 'display: none;' : ''; ?>">
                                                    ← Previous
                                                </button>

                                                <div class="question-counter">
                                                    <?= htmlspecialchars($subject['subject_name']) ?>:
                                                    Question <?= $q_index + 1 ?> of <?= $subject_questions_count[$subject['subject_id']] ?>
                                                </div>

                                                <?php if ($q_index < $subject_questions_count[$subject['subject_id']] - 1): ?>
                                                    <button type="button" class="nav-btn" onclick="nextQuestion(<?= $subject['subject_id'] ?>)" id="nextBtn_<?= $subject['subject_id'] ?>">
                                                        Next →
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="nav-btn" onclick="nextQuestion(<?= $subject['subject_id'] ?>)" id="nextBtn_<?= $subject['subject_id'] ?>" style="display: none;">
                                                        Next →
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Submit Section -->
                    <div class="navigation" style="margin-top: 2rem;">
                        <button type="button" class="submit-btn" onclick="submitGroupExam()">
                            Submit Complete Exam
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        // =============================================
        // EXAM CONFIGURATION
        // =============================================
        const subjects = <?= json_encode($group_subjects) ?>;
        const subjectQuestions = <?= json_encode($subject_questions_count) ?>;
        const totalDuration = <?= $exam['duration_minutes'] * 60 ?>;
        let currentSubjectId = <?= $group_subjects[0]['subject_id'] ?>;
        let currentQuestionIndex = 1;
        let timeRemaining = totalDuration;
        let answers = {};
        let timerInterval;

        // =============================================
        // TIMER FUNCTIONS
        // =============================================
        function updateTimer() {
            const hours = Math.floor(timeRemaining / 3600);
            const minutes = Math.floor((timeRemaining % 3600) / 60);
            const seconds = timeRemaining % 60;

            document.getElementById('timer').textContent =
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            if (timeRemaining <= 0) {
                autoSubmitExam();
            } else {
                timeRemaining--;
            }
        }

        // =============================================
        // SUBJECT NAVIGATION FUNCTIONS
        // =============================================
        function switchSubject(subjectId, subjectIndex) {
            // Hide all subjects
            document.querySelectorAll('.subject-content').forEach(content => {
                content.classList.remove('active');
            });

            // Show selected subject
            document.getElementById('subject_' + subjectId).classList.add('active');

            // Update tabs
            document.querySelectorAll('.subject-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById('tab_' + subjectId).classList.add('active');

            // Update current subject display
            document.getElementById('currentSubject').textContent =
                subjects.find(s => s.subject_id === subjectId).subject_name;

            // Show first question of this subject
            showQuestion(subjectId, 1);
            currentSubjectId = subjectId;

            // Update subject progress
            document.getElementById('subjectProgress').textContent =
                `Subject ${subjectIndex + 1} of ${subjects.length}`;
        }

        // =============================================
        // QUESTION NAVIGATION FUNCTIONS
        // =============================================
        function showQuestion(subjectId, questionNumber) {
            // Hide all questions in this subject
            const subjectElement = document.getElementById('subject_' + subjectId);
            subjectElement.querySelectorAll('.question-page').forEach(page => {
                page.style.display = 'none';
            });

            // Show selected question
            const questionElement = document.getElementById(`question_${subjectId}_${questionNumber}`);
            if (questionElement) {
                questionElement.style.display = 'block';
                currentQuestionIndex = questionNumber;

                // Update navigation buttons
                const prevBtn = document.getElementById('prevBtn_' + subjectId);
                const nextBtn = document.getElementById('nextBtn_' + subjectId);

                if (prevBtn) {
                    prevBtn.style.display = questionNumber === 1 ? 'none' : 'block';
                }

                if (nextBtn) {
                    const totalQuestions = subjectQuestions[subjectId];
                    nextBtn.style.display = questionNumber < totalQuestions ? 'block' : 'none';
                }
            }
        }

        function nextQuestion(subjectId) {
            const totalQuestions = subjectQuestions[subjectId];
            if (currentQuestionIndex < totalQuestions) {
                saveCurrentAnswer();
                showQuestion(subjectId, currentQuestionIndex + 1);
            }
        }

        function previousQuestion(subjectId) {
            if (currentQuestionIndex > 1) {
                saveCurrentAnswer();
                showQuestion(subjectId, currentQuestionIndex - 1);
            }
        }

        // =============================================
        // ANSWER MANAGEMENT FUNCTIONS
        // =============================================
        function saveCurrentAnswer() {
            const currentSubjectElement = document.getElementById('subject_' + currentSubjectId);
            if (currentSubjectElement) {
                const currentQuestionElement = currentSubjectElement.querySelector('.question-page[style*="display: block"]');
                if (currentQuestionElement) {
                    const radioButtons = currentQuestionElement.querySelectorAll('input[type="radio"]:checked');

                    if (radioButtons.length > 0) {
                        const questionId = radioButtons[0].name.replace('question_', '');
                        answers[questionId] = radioButtons[0].value;

                        // Update tab style to show answered status
                        updateTabStatus();

                        // Update global progress
                        updateGlobalProgress();

                        // Save to localStorage as backup
                        localStorage.setItem('group_exam_answers', JSON.stringify(answers));
                    }
                }
            }
        }

        function updateTabStatus() {
            subjects.forEach(subject => {
                const subjectId = subject.subject_id;
                const tab = document.getElementById('tab_' + subjectId);
                const subjectAnswers = getSubjectAnswers(subjectId);

                if (subjectAnswers > 0) {
                    tab.classList.add('answered');
                } else {
                    tab.classList.remove('answered');
                }
            });
        }

        function getSubjectAnswers(subjectId) {
            let count = 0;
            Object.keys(answers).forEach(questionId => {
                const input = document.querySelector(`input[name="question_${questionId}"][data-subject="${subjectId}"]`);
                if (input && answers[questionId]) {
                    count++;
                }
            });
            return count;
        }

        function updateGlobalProgress() {
            const totalAnswered = Object.keys(answers).length;
            const totalQuestions = Object.values(subjectQuestions).reduce((a, b) => a + b, 0);
            document.getElementById('globalProgress').textContent =
                `Total: ${totalAnswered}/${totalQuestions} answered`;

            // Update progress bar
            const progress = (totalAnswered / totalQuestions) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
        }

        function loadSavedAnswers() {
            const saved = localStorage.getItem('group_exam_answers');
            if (saved) {
                answers = JSON.parse(saved);

                // Restore selected answers in the form
                Object.keys(answers).forEach(questionId => {
                    const radio = document.querySelector(`input[name="question_${questionId}"][value="${answers[questionId]}"]`);
                    if (radio) {
                        radio.checked = true;
                        const option = radio.closest('.option');
                        if (option) {
                            option.classList.add('selected');
                        }
                    }
                });

                // Update answered counter and tabs
                updateTabStatus();
                updateGlobalProgress();
            }
        }

        // =============================================
        // EXAM SUBMISSION FUNCTIONS
        // =============================================
        function autoSubmitExam() {
            clearInterval(timerInterval);
            alert('Time is up! Your exam will be submitted automatically.');
            submitGroupExam();
        }

        function submitGroupExam() {
            // Save any unsaved answer
            saveCurrentAnswer();

            const answeredQuestions = Object.keys(answers).length;
            const totalQuestions = Object.values(subjectQuestions).reduce((a, b) => a + b, 0);

            // Show confirmation with detailed information
            let confirmMessage = `Are you sure you want to submit your group exam?\n\n`;
            confirmMessage += `Questions Answered: ${answeredQuestions} of ${totalQuestions}\n`;

            if (answeredQuestions < totalQuestions) {
                confirmMessage += `\n⚠️ You have ${totalQuestions - answeredQuestions} unanswered questions.\n`;
                confirmMessage += `Unanswered questions will be marked as incorrect.\n`;
                confirmMessage += `Your score will be based on all ${totalQuestions} assigned questions.`;
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            const formData = new FormData();
            formData.append('exam_id', <?= $exam_id ?>);
            formData.append('session_id', <?= $session_id ?>);
            formData.append('answers', JSON.stringify(answers));

            // Show loading state
            const submitBtn = document.querySelector('.submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }

            // Stop timer
            clearInterval(timerInterval);

            // Clear localStorage
            localStorage.removeItem('group_exam_answers');

            fetch('submit_group_exam.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let successMessage = `Group exam submitted successfully!\n\n`;
                        successMessage += `Overall Score: ${data.overall_score}%\n`;
                        successMessage += `Grade: ${data.overall_grade}\n`;
                        successMessage += `Correct Answers: ${data.total_correct}/${data.total_questions}\n`;

                        if (data.overall_score < 50) {
                            successMessage += `\n⚠️ You need to improve. Try to answer all questions next time!`;
                        }

                        alert(successMessage);
                        window.location.href = 'dashboard.php';
                    } else {
                        alert('Error submitting exam: ' + data.message);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Submit Complete Exam';
                        }
                        // Restart timer if submission failed
                        timerInterval = setInterval(updateTimer, 1000);
                    }
                })
                .catch(error => {
                    alert('Network error submitting exam. Please check your connection and try again.');
                    console.error('Error:', error);
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Submit Complete Exam';
                    }
                    // Restart timer if submission failed
                    timerInterval = setInterval(updateTimer, 1000);
                });
        }

        // =============================================
        // INITIALIZATION
        // =============================================
        window.addEventListener('load', function() {
            // Start timer
            timerInterval = setInterval(updateTimer, 1000);

            // Show first question of first subject
            showQuestion(currentSubjectId, 1);

            // Load saved answers
            loadSavedAnswers();

            console.log('Group exam initialized successfully');
            console.log(`Total subjects: ${subjects.length}`);
            console.log(`Total questions: ${Object.values(subjectQuestions).reduce((a, b) => a + b, 0)}`);
            console.log(`Exam duration: ${totalDuration} seconds`);
        });

        // Prevent accidental navigation
        window.addEventListener('beforeunload', function(e) {
            if (timeRemaining > 0) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</body>

</html>