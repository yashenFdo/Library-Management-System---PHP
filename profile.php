<?php
require_once 'config.php';
requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param("ssssi", $fullName, $email, $phone, $address, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $fullName;
            logActivity($userId, 'UPDATE_PROFILE', 'user', $userId, 'Updated profile information');
            flashMessage('success', 'Profile updated successfully!');
        } else {
            flashMessage('error', 'Failed to update profile!');
        }
        redirect('profile.php');
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Get current password from database
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password'])) {
            flashMessage('error', 'Current password is incorrect!');
        } elseif ($newPassword !== $confirmPassword) {
            flashMessage('error', 'New passwords do not match!');
        } elseif (strlen($newPassword) < 6) {
            flashMessage('error', 'Password must be at least 6 characters!');
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                logActivity($userId, 'CHANGE_PASSWORD', 'user', $userId, 'Changed password');
                flashMessage('success', 'Password changed successfully!');
            } else {
                flashMessage('error', 'Failed to change password!');
            }
        }
        redirect('profile.php');
    }
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$stats = [];
$stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['total_borrowed'] = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['current_borrowed'] = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $db->prepare("SELECT SUM(fine_amount) as total FROM borrowings WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats['total_fines'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>My Profile</h1>
                <p>Manage your account information</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card blue">
                    <div class="stat-icon">📚</div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total_borrowed']; ?></h3>
                        <p>Books Borrowed</p>
                    </div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-icon">📖</div>
                    <div class="stat-details">
                        <h3><?php echo $stats['current_borrowed']; ?></h3>
                        <p>Currently Borrowed</p>
                    </div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <h3>Rs. <?php echo number_format($stats['total_fines'], 2); ?></h3>
                        <p>Total Fines</p>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Profile Information -->
                <div class="section-card">
                    <h3>Profile Information</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small style="color: var(--gray);">Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 Update Profile</button>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="section-card">
                    <h3>Change Password</h3>
                    <form method="POST" onsubmit="return validatePassword()">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            <small style="color: var(--gray);">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">🔒 Change Password</button>
                    </form>
                    
                    <hr style="margin: 30px 0;">
                    
                    <h3>Account Information</h3>
                    <table style="width: 100%; margin-top: 15px;">
                        <tr>
                            <td style="padding: 8px 0;"><strong>Role:</strong></td>
                            <td style="padding: 8px 0;">
                                <span class="badge badge-primary">
                                    <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Status:</strong></td>
                            <td style="padding: 8px 0;">
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Member Since:</strong></td>
                            <td style="padding: 8px 0;"><?php echo formatDate($user['created_at'], 'F d, Y'); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0;"><strong>Last Updated:</strong></td>
                            <td style="padding: 8px 0;"><?php echo formatDate($user['updated_at'], 'F d, Y H:i'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function validatePassword() {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPass.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>