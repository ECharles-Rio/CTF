<?php
session_start(); // Start the session at the very beginning

// Include the database connection file
require_once 'db.php';

$errors = []; // Array to hold login errors
$username = '';

// Check for any success messages from registration
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying it once
}

// If user is already logged in, redirect them to the quiz page (or a dashboard)
if (isset($_SESSION['user_id'])) {
    header("Location: quiz.php"); // We'll create quiz.php later
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        try {
            // Prepare SQL statement to fetch user by username
            $stmt = $pdo->prepare("SELECT user_id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(); // Fetch the user record

            if ($user) {
                // User found, verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Password is correct, login successful
                    session_regenerate_id(true); // Regenerate session ID for security

                    // Store user data in session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];

                    // Redirect to the main quiz page (we'll create quiz.php next)
                    header("Location: quiz.php");
                    exit();
                } else {
                    // Password incorrect
                    $errors[] = "Invalid username or password.";
                }
            } else {
                // User not found
                $errors[] = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors[] = "An error occurred during login. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; max-width: 500px; margin: auto; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] {
            width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;
        }
        button {
            background-color: #337ab7; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;
        }
        button:hover { background-color: #286090; }
        .errors { background-color: #f2dede; color: #a94442; padding: 10px; border: 1px solid #ebccd1; border-radius: 4px; margin-bottom: 15px; }
        .errors ul { margin: 0; padding-left: 20px; }
        .success { background-color: #dff0d8; color: #3c763d; padding: 10px; border: 1px solid #d6e9c6; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .register-link { text-align: center; margin-top: 15px; }
        .register-link a { color: #5cb85c; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>

        <?php if (!empty($success_message)): ?>
            <div class="success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <p><strong>Login failed:</strong></p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>