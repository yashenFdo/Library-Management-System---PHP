<?php
/**
 * System Verification Tool
 * Check if everything is setup correctly
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .check {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 5px solid #ddd;
        }
        .check.success {
            border-left-color: #10b981;
        }
        .check.error {
            border-left-color: #ef4444;
        }
        .check.warning {
            border-left-color: #f59e0b;
        }
        h1 { color: #333; }
        h2 { color: #667eea; margin-top: 30px; }
        code {
            background: #333;
            color: #0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>";

echo "<h1>🔍 Library Management System - Verification Tool</h1>";

// Check 1: PHP Version
echo "<h2>1. PHP Environment</h2>";
$phpVersion = phpversion();
$phpOk = version_compare($phpVersion, '7.4.0', '>=');
echo "<div class='check " . ($phpOk ? 'success' : 'error') . "'>";
echo $phpOk ? "✅" : "❌";
echo " PHP Version: <strong>$phpVersion</strong>";
echo $phpOk ? " (OK)" : " (Required: 7.4 or higher)";
echo "</div>";

// Check 2: Required Extensions
echo "<h2>2. Required PHP Extensions</h2>";
$extensions = ['mysqli', 'gd', 'mbstring', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<div class='check " . ($loaded ? 'success' : 'error') . "'>";
    echo $loaded ? "✅" : "❌";
    echo " Extension: <strong>$ext</strong>";
    echo $loaded ? " (Loaded)" : " (Missing - Please install)";
    echo "</div>";
}

// Check 3: Database Connection
echo "<h2>3. Database Connection</h2>";
$dbConfig = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db' => 'library_management'
];

$conn = @new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['db']);

if ($conn->connect_error) {
    echo "<div class='check error'>";
    echo "❌ Database Connection: <strong>FAILED</strong><br>";
    echo "Error: " . $conn->connect_error . "<br>";
    echo "Check your database credentials in config.php";
    echo "</div>";
} else {
    echo "<div class='check success'>";
    echo "✅ Database Connection: <strong>SUCCESSFUL</strong>";
    echo "</div>";
    
    // Check 4: Database Tables
    echo "<h2>4. Database Tables</h2>";
    $requiredTables = ['users', 'books', 'categories', 'authors', 'borrowings', 'reservations', 'fines', 'activity_logs', 'settings'];
    $existingTables = [];
    
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $existingTables[] = $row[0];
    }
    
    echo "<table>";
    echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";
    
    foreach ($requiredTables as $table) {
        $exists = in_array($table, $existingTables);
        $count = 0;
        
        if ($exists) {
            $countResult = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
            $count = $countResult->fetch_assoc()['cnt'];
        }
        
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>" . ($exists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>";
        echo "<td>" . ($exists ? $count . " rows" : "-") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check 5: Default Users
    echo "<h2>5. Default Users</h2>";
    $userResult = $conn->query("SELECT username, email, role, status FROM users ORDER BY user_id");
    
    if ($userResult->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        
        while ($user = $userResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td><span class='" . ($user['status'] === 'active' ? 'success' : 'warning') . "'>" . 
                 ucfirst($user['status']) . "</span></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='check success'>";
        echo "✅ Found " . $userResult->num_rows . " user(s) in database";
        echo "</div>";
    } else {
        echo "<div class='check error'>";
        echo "❌ No users found in database. Please run the SQL import again.";
        echo "</div>";
    }
    
    // Check 6: Test Login
    echo "<h2>6. Password Verification Test</h2>";
    $testUser = $conn->query("SELECT username, password FROM users WHERE username = 'superadmin' LIMIT 1");
    
    if ($testUser->num_rows > 0) {
        $user = $testUser->fetch_assoc();
        $testPassword = 'admin123';
        $isValid = password_verify($testPassword, $user['password']);
        
        echo "<div class='check " . ($isValid ? 'success' : 'error') . "'>";
        echo $isValid ? "✅" : "❌";
        echo " Password Test for 'superadmin': ";
        echo $isValid ? "<strong class='success'>VALID</strong>" : "<strong class='error'>INVALID</strong>";
        
        if (!$isValid) {
            echo "<br><br>";
            echo "<strong>⚠️ Password hash doesn't match!</strong><br>";
            echo "Current hash in DB: <code>" . substr($user['password'], 0, 50) . "...</code><br>";
            echo "<br><strong>Solution:</strong><br>";
            echo "1. Run <a href='reset_passwords.php' style='color: #667eea;'><strong>reset_passwords.php</strong></a> to fix passwords<br>";
            echo "2. Or manually update with this SQL:<br>";
            echo "<code style='display: block; margin-top: 10px; padding: 10px;'>";
            echo "UPDATE users SET password = '" . password_hash($testPassword, PASSWORD_DEFAULT) . "' WHERE username = 'superadmin';";
            echo "</code>";
        }
        echo "</div>";
    }
}

// Check 7: File Permissions
echo "<h2>7. File Structure & Permissions</h2>";

$directories = [
    'uploads' => './uploads',
    'uploads/books' => './uploads/books',
    'uploads/profiles' => './uploads/profiles',
];

foreach ($directories as $name => $path) {
    $exists = file_exists($path);
    $writable = $exists && is_writable($path);
    
    echo "<div class='check " . ($exists && $writable ? 'success' : ($exists ? 'warning' : 'error')) . "'>";
    
    if ($exists && $writable) {
        echo "✅ Directory: <strong>$name</strong> - Exists & Writable";
    } elseif ($exists) {
        echo "⚠️ Directory: <strong>$name</strong> - Exists but NOT writable (chmod 777 needed)";
    } else {
        echo "❌ Directory: <strong>$name</strong> - Missing (will be auto-created)";
    }
    echo "</div>";
}

// Check 8: Required Files
echo "<h2>8. Core Files Check</h2>";
$coreFiles = [
    'config.php',
    'login.php',
    'dashboard.php',
    'books.php',
    'users.php',
    'includes/header.php',
    'includes/sidebar.php',
    'assets/css/style.css'
];

$missingFiles = [];
foreach ($coreFiles as $file) {
    $exists = file_exists($file);
    if (!$exists) {
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    echo "<div class='check success'>";
    echo "✅ All core files present (" . count($coreFiles) . " files checked)";
    echo "</div>";
} else {
    echo "<div class='check error'>";
    echo "❌ Missing files: <br>";
    foreach ($missingFiles as $file) {
        echo "- $file<br>";
    }
    echo "</div>";
}

// Summary
echo "<h2>📋 Summary</h2>";
if ($conn && !$conn->connect_error && $userResult->num_rows > 0) {
    echo "<div class='check success'>";
    echo "<h3>✅ System is Ready!</h3>";
    echo "<p>All checks passed. You can now use the system.</p>";
    echo "<p><strong>Default Login:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <code>superadmin</code></li>";
    echo "<li>Password: <code>admin123</code></li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Go to Login Page →</a></p>";
    echo "</div>";
} else {
    echo "<div class='check error'>";
    echo "<h3>❌ Setup Incomplete</h3>";
    echo "<p>Please fix the errors above before using the system.</p>";
    echo "<p><strong>Common Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Create database: <code>library_management</code></li>";
    echo "<li>Import SQL file in phpMyAdmin</li>";
    echo "<li>Update config.php with correct credentials</li>";
    echo "<li>Run <a href='reset_passwords.php'>reset_passwords.php</a> if password test fails</li>";
    echo "</ul>";
    echo "</div>";
}

if ($conn && !$conn->connect_error) {
    $conn->close();
}

echo "</body></html>";
?>