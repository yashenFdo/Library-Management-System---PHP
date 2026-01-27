<?php
require_once 'config.php';
requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get statistics based on role
$stats = [];

if (hasRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF])) {
    // Total books
    $result = $db->query("SELECT COUNT(*) as count FROM books");
    $stats['total_books'] = $result->fetch_assoc()['count'];
    
    // Available books
    $result = $db->query("SELECT SUM(available_quantity) as count FROM books");
    $stats['available_books'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Total users
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Currently borrowed
    $result = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'borrowed'");
    $stats['borrowed_books'] = $result->fetch_assoc()['count'];
    
    // Overdue books
    $result = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'borrowed' AND due_date < CURDATE()");
    $stats['overdue_books'] = $result->fetch_assoc()['count'];
    
    // Recent activities
    $stmt = $db->prepare("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 10");
    $stmt->execute();
    $recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // User stats
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats['my_borrowed'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'borrowed' AND due_date < CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats['my_overdue'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $db->prepare("SELECT SUM(fine_amount) as total FROM borrowings WHERE user_id = ? AND fine_amount > 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats['my_fines'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // My recent borrowings
    $stmt = $db->prepare("SELECT b.*, bk.title, bk.cover_image, a.author_name FROM borrowings b JOIN books bk ON b.book_id = bk.book_id LEFT JOIN authors a ON bk.author_id = a.author_id WHERE b.user_id = ? ORDER BY b.borrowed_date DESC LIMIT 5");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $my_borrowings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <?php if (hasRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF])): ?>
                    <div class="stat-card blue">
                        <div class="stat-icon">📚</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_books']); ?></h3>
                            <p>Total Books</p>
                        </div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-icon">✅</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['available_books']); ?></h3>
                            <p>Available</p>
                        </div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card purple">
                        <div class="stat-icon">📖</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['borrowed_books']); ?></h3>
                            <p>Borrowed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card red">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['overdue_books']); ?></h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="stat-card blue">
                        <div class="stat-icon">📖</div>
                        <div class="stat-details">
                            <h3><?php echo $stats['my_borrowed']; ?></h3>
                            <p>Currently Borrowed</p>
                        </div>
                    </div>
                    
                    <div class="stat-card orange">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-details">
                            <h3><?php echo $stats['my_overdue']; ?></h3>
                            <p>Overdue Books</p>
                        </div>
                    </div>
                    
                    <div class="stat-card red">
                        <div class="stat-icon">💰</div>
                        <div class="stat-details">
                            <h3>Rs. <?php echo number_format($stats['my_fines'], 2); ?></h3>
                            <p>Outstanding Fines</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity Section -->
            <?php if (hasRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF])): ?>
                <div class="section-card">
                    <h2>Recent Activity</h2>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                        <td><?php echo htmlspecialchars($activity['description'] ?? '-'); ?></td>
                                        <td><?php echo formatDate($activity['created_at'], 'M d, Y H:i'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="section-card">
                    <h2>My Recent Borrowings</h2>
                    <div class="books-grid">
                        <?php foreach ($my_borrowings as $borrowing): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <?php if ($borrowing['cover_image']): ?>
                                        <img src="<?php echo UPLOAD_URL . 'books/' . $borrowing['cover_image']; ?>" alt="Book cover">
                                    <?php else: ?>
                                        <div class="no-cover">📚</div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-details">
                                    <h4><?php echo htmlspecialchars($borrowing['title']); ?></h4>
                                    <p class="author"><?php echo htmlspecialchars($borrowing['author_name'] ?? 'Unknown'); ?></p>
                                    <p class="dates">
                                        <small>Borrowed: <?php echo formatDate($borrowing['borrowed_date'], 'M d, Y'); ?></small><br>
                                        <small>Due: <?php echo formatDate($borrowing['due_date'], 'M d, Y'); ?></small>
                                    </p>
                                    <span class="badge badge-<?php echo $borrowing['status'] === 'overdue' ? 'danger' : 'success'; ?>">
                                        <?php echo ucfirst($borrowing['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>