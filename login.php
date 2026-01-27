<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT user_id, username, password, full_name, role, status FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] !== 'active') {
                $error = 'Your account is ' . $user['status'] . '. Please contact administrator.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                logActivity($user['user_id'], 'LOGIN', 'user', $user['user_id'], 'User logged in');
                
                flashMessage('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect('dashboard.php');
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-left h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .feature-list {
            list-style: none;
            margin-top: 30px;
        }
        
        .feature-list li {
            padding: 10px 0;
            display: flex;
            align-items: center;
        }
        
        .feature-list li:before {
            content: "✓";
            margin-right: 15px;
            background: rgba(255,255,255,0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .login-right {
            padding: 60px 40px;
        }
        
        .login-form h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .login-form p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .demo-credentials {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .demo-credentials h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .credential-item {
            background: white;
            padding: 10px;
            margin: 8px 0;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }
        
        .credential-item strong {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
            }
            
            .login-left {
                padding: 40px 30px;
            }
            
            .login-right {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <h1>📚 Library Management</h1>
            <p>Your complete solution for managing library operations efficiently and effectively.</p>
            <ul class="feature-list">
                <li>Manage Books & Categories</li>
                <li>Track Borrowing & Returns</li>
                <li>User Management System</li>
                <li>Real-time Reports & Analytics</li>
            </ul>
        </div>
        
        <div class="login-right">
            <form class="login-form" method="POST">
                <h2>Welcome Back</h2>
                <p>Please login to your account</p>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
                
                <div class="demo-credentials">
                    <h4>Demo Credentials</h4>
                    <div class="credential-item">
                        <strong>Super Admin:</strong> <span>superadmin / admin123</span>
                    </div>
                    <div class="credential-item">
                        <strong>Admin:</strong> <span>admin / admin123</span>
                    </div>
                    <div class="credential-item">
                        <strong>Staff:</strong> <span>staff / admin123</span>
                    </div>
                    <div class="credential-item">
                        <strong>User:</strong> <span>user / admin123</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>