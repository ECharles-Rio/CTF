<?php
// db.php - Database Connection File

// --- IMPORTANT: VERIFY AND SET YOUR DB USER AND PASSWORD ---
$db_host = 'localhost';
$db_name = 'simple_auth_db'; // Updated based on your phpMyAdmin URL
$db_user = 'root';           // TRY THIS: Common default MySQL username for XAMPP/WAMP/MAMP
$db_pass = '';               // TRY THIS: Common default (empty) password for XAMPP/WAMP 'root'.
                             // For MAMP, try 'root'.
                             // **USE YOUR ACTUAL MYSQL PASSWORD if you have set one.**
// ----------------------------------------------------------------

// Data Source Name (DSN)
$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";

// Options for PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Turn on errors in the form of exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Make the default fetch be an associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Turn off emulation mode for real prepared statements
];

try {
    // Create a PDO instance (connect to the database)
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // If connection fails, stop the script and show an error message
    // In a production environment, you might want to log this error instead of showing it to the user
    error_log("Database Connection Error: " . $e->getMessage()); // Log the error
    die("Sorry, there was a problem connecting to the database. Please try again later.");
}

// If the script reaches here, the connection was successful.
// The $pdo object can now be used in other files to interact with the database.
?>