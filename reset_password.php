<?php
/**
 * Password Reset Utility
 * Use this file ONCE to reset all default passwords to 'admin123'
 * Delete this file after use for security!
 */

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'library_management';

// Connect to database
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "<h2>🔧 Password Reset Utility</h2>";
echo "<p>This will reset all default user passwords to: <strong>admin123</strong></p>";

// Generate password hash for 'admin123'
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "<p>Generated password hash: <code>" . $hashedPassword . "</code></p>";

// Update all default users
$users = ['superadmin', 'admin', 'staff', 'user'];
$success = 0;
$failed = 0;

foreach ($users as $username) {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashedPassword, $username);
    
    if ($stmt->execute()) {
        echo "✅ Password reset for user: <strong>$username</strong><br>";
        $success++;
    } else {
        echo "❌ Failed to reset password for user: <strong>$username</strong><br>";
        $failed++;
    }
    $stmt->close();
}

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p>✅ Successfully reset: $success users<br>";
echo "❌ Failed: $failed users</p>";

if ($success > 0) {
    echo "<h3 style='color: green;'>✅ Password Reset Complete!</h3>";
    echo "<p><strong>You can now login with:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <strong>superadmin</strong> | Password: <strong>admin123</strong></li>";
    echo "<li>Username: <strong>admin</strong> | Password: <strong>admin123</strong></li>";
    echo "<li>Username: <strong>staff</strong> | Password: <strong>admin123</strong></li>";
    echo "<li>Username: <strong>user</strong> | Password: <strong>admin123</strong></li>";
    echo "</ul>";
    echo "<p style='color: red;'><strong>⚠️ IMPORTANT: Delete this file (reset_passwords.php) after use for security!</strong></p>";
    echo "<p><a href='login.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Utility</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        code {
            background: #333;
            color: #0f0;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            word-break: break-all;
        }
        ul {
            background: white;
            padding: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
</body>
</html>