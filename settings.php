<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN]);

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $borrowingDays = intval($_POST['borrowing_period_days']);
    $maxBooks = intval($_POST['max_books_per_user']);
    $finePerDay = floatval($_POST['fine_per_day']);
    $reservationDays = intval($_POST['reservation_expiry_days']);
    
    // Update settings
    $settings = [
        'borrowing_period_days' => $borrowingDays,
        'max_books_per_user' => $maxBooks,
        'fine_per_day' => $finePerDay,
        'reservation_expiry_days' => $reservationDays
    ];
    
    $success = true;
    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        logActivity($_SESSION['user_id'], 'UPDATE_SETTINGS', 'settings', null, 'Updated system settings');
        flashMessage('success', 'Settings updated successfully!');
    } else {
        flashMessage('error', 'Failed to update settings!');
    }
    
    redirect('settings.php');
}

// Get current settings
$settingsData = $db->query("SELECT * FROM settings")->fetch_all(MYSQLI_ASSOC);
$settings = [];
foreach ($settingsData as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// System statistics
$stats = [];
$stats['total_books'] = $db->query("SELECT COUNT(*) as count FROM books")->fetch_assoc()['count'];
$stats['total_users'] = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['total_borrowings'] = $db->query("SELECT COUNT(*) as count FROM borrowings")->fetch_assoc()['count'];
$stats['total_categories'] = $db->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$stats['total_authors'] = $db->query("SELECT COUNT(*) as count FROM authors")->fetch_assoc()['count'];

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>System Settings</h1>
                <p>Configure library management system</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- System Overview -->
            <div class="section-card">
                <h3>📊 System Overview</h3>
                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon">📚</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_books']); ?></h3>
                            <p>Total Books</p>
                        </div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-icon">📖</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_borrowings']); ?></h3>
                            <p>Total Borrowings</p>
                        </div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="stat-icon">📑</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_categories']); ?></h3>
                            <p>Categories</p>
                        </div>
                    </div>
                    
                    <div class="stat-card red">
                        <div class="stat-icon">✍️</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_authors']); ?></h3>
                            <p>Authors</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Library Settings -->
            <div class="section-card">
                <h3>⚙️ Library Settings</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="borrowing_period_days">Default Borrowing Period (Days) *</label>
                            <input type="number" class="form-control" id="borrowing_period_days" name="borrowing_period_days" 
                                   value="<?php echo $settings['borrowing_period_days']; ?>" min="1" max="365" required>
                            <small style="color: var(--gray);">How many days users can borrow books</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_books_per_user">Maximum Books Per User *</label>
                            <input type="number" class="form-control" id="max_books_per_user" name="max_books_per_user" 
                                   value="<?php echo $settings['max_books_per_user']; ?>" min="1" max="50" required>
                            <small style="color: var(--gray);">Maximum books a user can borrow at once</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="fine_per_day">Fine Per Day (Rs.) *</label>
                            <input type="number" class="form-control" id="fine_per_day" name="fine_per_day" 
                                   value="<?php echo $settings['fine_per_day']; ?>" min="0" step="0.01" required>
                            <small style="color: var(--gray);">Fine amount charged per day for overdue books</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reservation_expiry_days">Reservation Expiry (Days) *</label>
                            <input type="number" class="form-control" id="reservation_expiry_days" name="reservation_expiry_days" 
                                   value="<?php echo $settings['reservation_expiry_days']; ?>" min="1" max="30" required>
                            <small style="color: var(--gray);">Days before a reservation expires</small>
                        </div>
                    </div>
                    
                    <div class="mt-20">
                        <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                    </div>
                </form>
            </div>
            
            <!-- System Information -->
            <div class="section-card">
                <h3>ℹ️ System Information</h3>
                <table style="width: 100%;">
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px; font-weight: bold;">PHP Version</td>
                        <td style="padding: 12px;"><?php echo phpversion(); ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px; font-weight: bold;">MySQL Version</td>
                        <td style="padding: 12px;">
                            <?php 
                            $result = $db->query("SELECT VERSION() as version");
                            echo $result->fetch_assoc()['version'];
                            ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px; font-weight: bold;">System Name</td>
                        <td style="padding: 12px;"><?php echo SITE_NAME; ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 12px; font-weight: bold;">Upload Directory</td>
                        <td style="padding: 12px;"><?php echo UPLOAD_PATH; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold;">Current Date/Time</td>
                        <td style="padding: 12px;"><?php echo date('F d, Y H:i:s'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Database Backup -->
            <div class="section-card">
                <h3>💾 Database Management</h3>
                <p style="margin-bottom: 20px; color: var(--gray);">
                    Backup your database regularly to prevent data loss. Contact your system administrator for backup procedures.
                </p>
                
                <div class="alert alert-info">
                    <strong>ℹ️ Backup Recommendation:</strong><br>
                    It is recommended to backup your database at least once a week. Keep backups in a secure location.
                </div>
            </div>
        </main>
    </div>
</body>
</html>