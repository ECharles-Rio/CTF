<?php
session_start(); // Start the session at the very beginning

// Include the database connection file
require_once 'db.php';

$errors = []; // Array to hold validation errors
$username = '';
$email = '';    // Optional: if you decide to collect email

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize input (basic sanitization)
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    // $email = trim($_POST['email']); // Uncomment if you add an email field

    // --- Validation ---
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    }

    // You can add more username validation (e.g., allowed characters)

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) { // Basic password length check
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Uncomment and adapt if using email
    /*
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } else {
            // Check if email is already taken
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "This email address is already registered.";
            }
        }
    }
    */

    // Check if username is already taken (only if other errors are not present yet for this field)
    if (empty($errors['username_exists']) && !empty($username)) { // A way to avoid duplicate error messages if needed
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username already taken. Please choose another.";
        }
    }

    // If there are no validation errors, proceed to insert into database
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Prepare SQL statement to insert user
            // quiz_attempt_status defaults to 'not_started' as per table schema
            // final_score defaults to NULL
            $sql = "INSERT INTO users (username, password_hash";
            // if (!empty($email)) $sql .= ", email"; // Add email to SQL if collecting
            $sql .= ") VALUES (?, ?";
            // if (!empty($email)) $sql .= ", ?";
            $sql .= ")";

            $stmt = $pdo->prepare($sql);

            $params = [$username, $hashed_password];
            // if (!empty($email)) $params[] = $email;

            if ($stmt->execute($params)) {
                // Registration successful
                // Set a success message in session and redirect to login page
                $_SESSION['success_message'] = "Registration successful! You can now log in.";
                header("Location: login.php");
                exit(); // Important to prevent further script execution after redirect
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        } catch (PDOException $e) {
            // Log error for debugging
            error_log("Registration Error: " . $e->getMessage());
            $errors[] = "An error occurred during registration. Please try again later.";
            // In a production environment, don't display $e->getMessage() to the user
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; max-width: 500px; margin: auto; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="email"] {
            width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;
        }
        button {
            background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;
        }
        button:hover { background-color: #4cae4c; }
        .errors { background-color: #f2dede; color: #a94442; padding: 10px; border: 1px solid #ebccd1; border-radius: 4px; margin-bottom: 15px; }
        .errors ul { margin: 0; padding-left: 20px; }
        .success { background-color: #dff0d8; color: #3c763d; padding: 10px; border: 1px solid #d6e9c6; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .login-link { text-align: center; margin-top: 15px; }
        .login-link a { color: #337ab7; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register New Account</h2>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <p><strong>Please correct the following errors:</strong></p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="password_confirm">Confirm Password:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <div>
                <button type="submit">Register</button>
            </div>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>