<?php
session_start(); // Start or resume the session
require_once 'db.php'; // Include the database connection

// Check if the user is logged in. If not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- HANDLE ANSWER SUBMISSION (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['question_id'], $_POST['user_answer'])) {
        $question_id_answered = (int)$_POST['question_id'];
        $user_submitted_answer = trim($_POST['user_answer']);

        try {
            $stmt_check_answered = $pdo->prepare("SELECT answer_id FROM user_answers WHERE user_id = ? AND question_id = ?");
            $stmt_check_answered->execute([$user_id, $question_id_answered]);
            if ($stmt_check_answered->fetch()) {
                $_SESSION['answer_feedback'] = ['type' => 'info', 'message' => 'You have already answered this question.'];
                header("Location: quiz.php");
                exit();
            }

            $stmt_question_details = $pdo->prepare("SELECT correct_answer, points FROM questions WHERE question_id = ?");
            $stmt_question_details->execute([$question_id_answered]);
            $question_info = $stmt_question_details->fetch();

            if ($question_info) {
                $correct_answer_db = $question_info['correct_answer'];
                $points_for_question = (int)$question_info['points'];
                $is_correct = false;

                // --- Answer Verification Logic ---
                if ($user_submitted_answer === $correct_answer_db) {
                    $is_correct = true;
                }
                // TODO: Consider specific case-insensitive checks for certain question types if needed
                // e.g., if (strtolower($user_submitted_answer) == strtolower($correct_answer_db)) { $is_correct = true; }

                $score_awarded = $is_correct ? $points_for_question : 0;

                $stmt_insert_answer = $pdo->prepare(
                    "INSERT INTO user_answers (user_id, question_id, submitted_answer, is_correct, score_awarded, timestamp)
                     VALUES (?, ?, ?, ?, ?, NOW())"
                );
                $stmt_insert_answer->execute([
                    $user_id,
                    $question_id_answered,
                    $user_submitted_answer,
                    $is_correct ? 1 : 0,
                    $score_awarded
                ]);

                // Set feedback message for the user
                if ($is_correct) {
                    $_SESSION['answer_feedback'] = [
                        'type' => 'correct',
                        'message' => 'Correct Answer! Well done, Agent! Points awarded: ' . $points_for_question
                    ];
                } else {
                    $_SESSION['answer_feedback'] = [
                        'type' => 'incorrect',
                        'message' => 'That wasn\'t the intel we were looking for. No points for this one.'
                        // Optionally, to show the correct answer (can be a setting later):
                        // , 'correct_answer_was' => $correct_answer_db
                    ];
                }

                header("Location: quiz.php");
                exit();
            } else {
                $_SESSION['answer_feedback'] = ['type' => 'error', 'message' => 'Invalid question processed.'];
                error_log("Error: Attempt to answer non-existent question_id: " . $question_id_answered . " by user_id: " . $user_id);
                header("Location: quiz.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['answer_feedback'] = ['type' => 'error', 'message' => 'Error submitting your answer. Please try again.'];
            error_log("Answer Submission Error: " . $e->getMessage());
            header("Location: quiz.php");
            exit();
        }
    } else {
        $_SESSION['answer_feedback'] = ['type' => 'error', 'message' => 'Incomplete submission. Please try again.'];
        header("Location: quiz.php");
        exit();
    }
}
// --- END OF ANSWER SUBMISSION HANDLING ---


// --- DISPLAY LOGIC ---
$current_question_data = null;
$quiz_overall_message = ''; // For general quiz status like completion or major errors
$final_score_display = null;
$current_running_score = 0;

// Retrieve and clear answer feedback from session
$answer_feedback_html = '';
if (isset($_SESSION['answer_feedback'])) {
    $feedback = $_SESSION['answer_feedback'];
    $feedback_class = 'feedback-info'; // Default class
    if ($feedback['type'] == 'correct') {
        $feedback_class = 'feedback-correct';
    } elseif ($feedback['type'] == 'incorrect') {
        $feedback_class = 'feedback-incorrect';
    } elseif ($feedback['type'] == 'error') {
        $feedback_class = 'feedback-error';
    }
    $answer_feedback_html = "<div class='answer-feedback {$feedback_class}'><p>" . htmlspecialchars($feedback['message']);
    // if (isset($feedback['correct_answer_was'])) {
    //     $answer_feedback_html .= "<br>The correct answer was: " . htmlspecialchars($feedback['correct_answer_was']);
    // }
    $answer_feedback_html .= "</p></div>";
    unset($_SESSION['answer_feedback']);
}


try {
    $stmt_user_status = $pdo->prepare("SELECT quiz_attempt_status, final_score FROM users WHERE user_id = ?");
    $stmt_user_status->execute([$user_id]);
    $user_quiz_info = $stmt_user_status->fetch();

    if (!$user_quiz_info) {
        die("Error: Could not retrieve user quiz status.");
    }

    $quiz_status = $user_quiz_info['quiz_attempt_status'];
    $final_score_display = $user_quiz_info['final_score'];

    if ($quiz_status == 'in_progress' || $quiz_status == 'not_started') {
        $stmt_current_score = $pdo->prepare("SELECT SUM(score_awarded) as running_total FROM user_answers WHERE user_id = ?");
        $stmt_current_score->execute([$user_id]);
        $score_row = $stmt_current_score->fetch();
        if ($score_row && $score_row['running_total'] !== null) {
            $current_running_score = (int)$score_row['running_total'];
        }
    } elseif ($quiz_status == 'completed') {
        $current_running_score = (int)$final_score_display;
    }

    if ($quiz_status == 'completed') {
        $quiz_overall_message = "<div class='quiz-message'>🎉 *Congratulations, Agent! You've successfully navigated all challenges and secured the intel! Your final mission score is: " . htmlspecialchars($final_score_display) . "* 🎉</div>";
    } else {
        if ($quiz_status == 'not_started') {
            $stmt_update_status = $pdo->prepare("UPDATE users SET quiz_attempt_status = 'in_progress' WHERE user_id = ?");
            $stmt_update_status->execute([$user_id]);
            $quiz_status = 'in_progress';
        }

        $sql_next_question = "
            SELECT q.question_id, q.question_text, q.hint, w.week_name
            FROM questions q
            JOIN weeks w ON q.week_id = w.week_id
            LEFT JOIN user_answers ua ON q.question_id = ua.question_id AND ua.user_id = ?
            WHERE ua.answer_id IS NULL
            ORDER BY w.week_order ASC, q.question_order_in_week ASC
            LIMIT 1
        ";
        $stmt_next_question = $pdo->prepare($sql_next_question);
        $stmt_next_question->execute([$user_id]);
        $current_question_data = $stmt_next_question->fetch();

        if (!$current_question_data && $quiz_status == 'in_progress') {
            $final_score_value = $current_running_score;
            $stmt_complete_quiz = $pdo->prepare("UPDATE users SET quiz_attempt_status = 'completed', final_score = ? WHERE user_id = ?");
            $stmt_complete_quiz->execute([$final_score_value, $user_id]);
            
            $quiz_overall_message = "<div class='quiz-message'>🎉 **Congratulations, Agent!** You've successfully navigated all challenges and secured the intel! Your final mission score is: **" . htmlspecialchars($final_score_value) . "** 🎉</div>";
            $final_score_display = $final_score_value;
            $quiz_status = 'completed';
        }
    }

} catch (PDOException $e) {
    error_log("Quiz Page Error (GET): " . $e->getMessage());
    $quiz_overall_message = "<div class='feedback-error'>An error occurred while loading the quiz. Please try again later.</div>";
    $current_question_data = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Time! - <?php echo date("Y-m-d H:i:s"); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: auto; background-color: #f0f2f5; color: #333; }
        .container { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .user-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #dee2e6; }
        .user-bar p { margin: 0; font-size: 0.95em; }
        .user-bar .score { font-weight: bold; color: #28a745; margin-left: 15px; font-size: 1em; }
        .logout-link { display: inline-block; padding: 8px 15px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em; }
        .logout-link:hover { background-color: #c82333; }
        
        .answer-feedback {
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 5px;
    font-size: 1.05em;
    text-align: center;
    border-left-width: 5px;
    border-left-style: solid;
    /* Add for fade-out effect */
    opacity: 1;
    transition: opacity 0.5s ease-out; 
}
.feedback-correct {
    background-color: #d1e7dd; color: #0f5132; border-left-color: #0f5132;
}
.feedback-incorrect {
    background-color: #f8d7da; color: #842029; border-left-color: #842029;
}
.feedback-info {
    background-color: #cff4fc; color: #055160; border-left-color: #055160;
}
.feedback-error { 
    background-color: #f8d7da; color: #842029; border-left-color: #842029;
}

        .question-container { margin-top: 25px; }
        .question-week { font-size: 1.3em; color: #495057; margin-bottom: 10px; font-weight: 600; }
        .question-content { margin-bottom: 20px; padding: 20px; border: 1px solid #ced4da; border-radius: 5px; background-color: #f8f9fa; }
        .question-content input[type="text"], .question-content input[type="password"],
        .question-content label { 
            display: block; margin-bottom: 8px;
        }
        .question-content input[type="text"], .question-content input[type="password"] {
            width: calc(100% - 24px); padding: 10px; margin-bottom: 12px; border: 1px solid #ced4da; border-radius: 4px;
            box-sizing: border-box; font-size: 1em;
        }
        .question-content .input-group .input-field { width: calc(100% - 24px); }
        .hint-container { margin-top: 15px; margin-bottom: 20px; }
        .hint-button { background-color: #ffc107; color: #212529; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-size: 0.95em; }
        .hint-button:hover { background-color: #e0a800; }
        .hint-text { display: none; margin-top: 12px; padding: 12px; background-color: #fff3cd; border: 1px solid #ffeeba; border-radius: 4px; color: #664d03;}
        .submit-button { background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 1.05em; }
        .submit-button:hover { background-color: #218838; }
        
        /* Styles for specific input IDs from your snippets */
        #word-input { width: 60%; }
        #stage2Number { width: 120px; text-align: center; font-size: 1.1em; }
        #stage4Input, #stage5Input, #s3-flag, #s1-flag { width: calc(90% - 24px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="user-bar">
            <div>
                Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong>
                <?php if ($quiz_status == 'in_progress' || $quiz_status == 'completed'): ?>
                    <span class="score">| Score: <?php echo $current_running_score; ?></span>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>

        <h1>Quiz Time!</h1>

        <?php echo $answer_feedback_html; // Display feedback from last answer submission ?>

        <?php if (!empty($quiz_overall_message) && (!$current_question_data || $quiz_status == 'completed')): ?>
            <?php echo $quiz_overall_message; // Display completion message or major errors ?>
        <?php endif; ?>


        <?php if ($current_question_data): ?>
            <div class="question-container">
                <h2 class="question-week"><?php echo htmlspecialchars($current_question_data['week_name']); ?></h2>
                <form action="quiz.php" method="POST">
                    <div class="question-content">
                        <?php echo $current_question_data['question_text']; ?>
                    </div>

                    <?php if (!empty($current_question_data['hint'])): ?>
                        <div class="hint-container">
                            <button type="button" class="hint-button" onclick="toggleHint()">Show/Hide Hint</button>
                            <div id="hintText" class="hint-text">
                                <?php echo htmlspecialchars($current_question_data['hint']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($current_question_data['question_id']); ?>">
                    <button type="submit" class="submit-button">Submit Answer</button>
                </form>
            </div>
        <?php elseif (empty($quiz_overall_message) && empty($answer_feedback_html) && $quiz_status != 'completed'): ?>
            <p>Loading your quiz...</p> 
        <?php endif; ?>
    </div>

    <script>
        function toggleHint() {
            var hintText = document.getElementById('hintText');
            if (hintText.style.display === 'none' || hintText.style.display === '') {
                hintText.style.display = 'block';
            } else {
                hintText.style.display = 'none';
            }
        }

        // New JavaScript for auto-hiding feedback messages
        window.addEventListener('DOMContentLoaded', (event) => {
            const feedbackBox = document.querySelector('.answer-feedback'); // Selects the feedback box by its class
            
            if (feedbackBox) {
                // Wait for 3 seconds (3000 milliseconds) then hide the feedback box
                setTimeout(() => {
                    // Option 1: Smooth fade out (add CSS for transition)
                    feedbackBox.style.opacity = '0';
                    setTimeout(() => {
                        feedbackBox.style.display = 'none';
                    }, 500); // Wait for opacity transition to finish (0.5s)

                    // Option 2: Just hide it abruptly (simpler if you don't want a fade)
                    // feedbackBox.style.display = 'none';

                    // Option 3: Or remove it completely from the DOM after fading (or directly)
                    // feedbackBox.remove(); // Use after fade or directly if no fade
                }, 3000); // Time in milliseconds (e.g., 3000 for 3 seconds)
            }
        });
    </script>

</body>
</html>