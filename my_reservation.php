<?php
require_once 'config.php';
requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get user's reservations
$reservations = $db->query("SELECT r.*, b.title, b.cover_image, b.isbn, a.author_name, b.available_quantity
                            FROM reservations r
                            JOIN books b ON r.book_id = b.book_id
                            LEFT JOIN authors a ON b.author_id = a.author_id
                            WHERE r.user_id = $userId
                            ORDER BY r.reservation_date DESC")->fetch_all(MYSQLI_ASSOC);

// Statistics
$pending = 0;
$fulfilled = 0;
$cancelled = 0;
$expired = 0;

foreach ($reservations as $r) {
    switch ($r['status']) {
        case 'pending': $pending++; break;
        case 'fulfilled': $fulfilled++; break;
        case 'cancelled': $cancelled++; break;
        case 'expired': $expired++; break;
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>My Reservations</h1>
                <p>View and manage your book reservations</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card orange">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-details">
                        <h3><?php echo $pending; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <h3><?php echo $fulfilled; ?></h3>
                        <p>Fulfilled</p>
                    </div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">❌</div>
                    <div class="stat-details">
                        <h3><?php echo $cancelled; ?></h3>
                        <p>Cancelled</p>
                    </div>
                </div>
                
                <div class="stat-card blue">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-details">
                        <h3><?php echo $expired; ?></h3>
                        <p>Expired</p>
                    </div>
                </div>
            </div>
            
            <!-- Reservations -->
            <div class="section-card">
                <h3>My Reservations</h3>
                
                <?php if (empty($reservations)): ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">🔖</div>
                        <h3>No Reservations Yet</h3>
                        <p style="color: var(--gray); margin: 10px 0 20px;">You haven't reserved any books.</p>
                        <a href="browse_books.php" class="btn btn-primary">Browse Books</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Author</th>
                                    <th>Reservation Date</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                    <th>Availability</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $r): ?>
                                    <?php
                                    $statusBadge = '';
                                    switch ($r['status']) {
                                        case 'pending':
                                            $statusBadge = '<span class="badge badge-warning">Pending</span>';
                                            break;
                                        case 'fulfilled':
                                            $statusBadge = '<span class="badge badge-success">Fulfilled</span>';
                                            break;
                                        case 'cancelled':
                                            $statusBadge = '<span class="badge badge-danger">Cancelled</span>';
                                            break;
                                        case 'expired':
                                            $statusBadge = '<span class="badge badge-secondary" style="background: var(--gray);">Expired</span>';
                                            break;
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['title']); ?></strong><br>
                                            <small>ISBN: <?php echo htmlspecialchars($r['isbn'] ?? '-'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['author_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo formatDate($r['reservation_date'], 'M d, Y'); ?></td>
                                        <td><?php echo formatDate($r['expiry_date'], 'M d, Y'); ?></td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td>
                                            <?php if ($r['available_quantity'] > 0): ?>
                                                <span class="badge badge-success">Available (<?php echo $r['available_quantity']; ?>)</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="alert alert-info">
                <strong>ℹ️ About Reservations:</strong><br>
                Reservations allow you to hold a book that's currently unavailable. When the book becomes available, you'll be notified and have 
                <?php echo $db->query("SELECT setting_value FROM settings WHERE setting_key = 'reservation_expiry_days'")->fetch_assoc()['setting_value']; ?> 
                days to collect it before the reservation expires.
            </div>
        </main>
    </div>
</body>
</html>