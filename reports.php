<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN]);

$db = Database::getInstance();

// Most borrowed books
$mostBorrowed = $db->query("SELECT b.title, b.isbn, a.author_name, COUNT(br.borrowing_id) as borrow_count
                             FROM books b
                             LEFT JOIN borrowings br ON b.book_id = br.book_id
                             LEFT JOIN authors a ON b.author_id = a.author_id
                             GROUP BY b.book_id
                             ORDER BY borrow_count DESC
                             LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Most active users
$activeUsers = $db->query("SELECT u.full_name, u.username, u.email, COUNT(br.borrowing_id) as borrow_count
                           FROM users u
                           LEFT JOIN borrowings br ON u.user_id = br.user_id
                           WHERE u.role = 'user'
                           GROUP BY u.user_id
                           ORDER BY borrow_count DESC
                           LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Books by category
$booksByCategory = $db->query("SELECT c.category_name, COUNT(b.book_id) as book_count
                               FROM categories c
                               LEFT JOIN books b ON c.category_id = b.category_id
                               GROUP BY c.category_id
                               ORDER BY book_count DESC")->fetch_all(MYSQLI_ASSOC);

// Monthly borrowing statistics (last 6 months)
$monthlyStats = $db->query("SELECT DATE_FORMAT(borrowed_date, '%Y-%m') as month, 
                            COUNT(*) as total_borrowed,
                            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) as returned,
                            SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as still_borrowed
                            FROM borrowings
                            WHERE borrowed_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                            GROUP BY month
                            ORDER BY month DESC")->fetch_all(MYSQLI_ASSOC);

// Overdue books
$overdueBooks = $db->query("SELECT br.*, u.full_name, u.username, u.phone, bk.title, 
                            DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                            (DATEDIFF(CURDATE(), br.due_date) * " . DEFAULT_FINE_PER_DAY . ") as calculated_fine
                            FROM borrowings br
                            JOIN users u ON br.user_id = u.user_id
                            JOIN books bk ON br.book_id = bk.book_id
                            WHERE br.status = 'borrowed' AND br.due_date < CURDATE()
                            ORDER BY days_overdue DESC")->fetch_all(MYSQLI_ASSOC);

// Fine statistics
$fineStats = $db->query("SELECT 
                         SUM(fine_amount) as total_fines,
                         COUNT(DISTINCT user_id) as users_with_fines,
                         AVG(fine_amount) as avg_fine
                         FROM borrowings
                         WHERE fine_amount > 0")->fetch_assoc();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Reports & Analytics</h1>
                <p>System statistics and insights</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Fine Statistics -->
            <div class="section-card">
                <h3>📊 Fine Statistics</h3>
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-card red">
                        <div class="stat-icon">💰</div>
                        <div class="stat-details">
                            <h3>Rs. <?php echo number_format($fineStats['total_fines'] ?? 0, 2); ?></h3>
                            <p>Total Fines</p>
                        </div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <h3><?php echo $fineStats['users_with_fines'] ?? 0; ?></h3>
                            <p>Users with Fines</p>
                        </div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-icon">📈</div>
                        <div class="stat-details">
                            <h3>Rs. <?php echo number_format($fineStats['avg_fine'] ?? 0, 2); ?></h3>
                            <p>Average Fine</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Borrowing Trend -->
            <div class="section-card">
                <h3>📈 Monthly Borrowing Trend (Last 6 Months)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Borrowed</th>
                                <th>Returned</th>
                                <th>Still Borrowed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyStats as $stat): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($stat['month'] . '-01')); ?></td>
                                    <td><span class="badge badge-info"><?php echo $stat['total_borrowed']; ?></span></td>
                                    <td><span class="badge badge-success"><?php echo $stat['returned']; ?></span></td>
                                    <td><span class="badge badge-warning"><?php echo $stat['still_borrowed']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Most Borrowed Books -->
                <div class="section-card">
                    <h3>📚 Top 10 Most Borrowed Books</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Times Borrowed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($mostBorrowed as $book): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?></td>
                                        <td><span class="badge badge-primary"><?php echo $book['borrow_count']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Most Active Users -->
                <div class="section-card">
                    <h3>👥 Top 10 Most Active Users</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Books Borrowed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($activeUsers as $user): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><span class="badge badge-success"><?php echo $user['borrow_count']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Books by Category -->
            <div class="section-card">
                <h3>📑 Books Distribution by Category</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Number of Books</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalBooks = array_sum(array_column($booksByCategory, 'book_count'));
                            foreach ($booksByCategory as $cat): 
                                $percentage = $totalBooks > 0 ? ($cat['book_count'] / $totalBooks * 100) : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $cat['book_count']; ?></span></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="flex: 1; background: var(--light); border-radius: 10px; height: 20px; overflow: hidden;">
                                                <div style="background: var(--primary); height: 100%; width: <?php echo $percentage; ?>%;"></div>
                                            </div>
                                            <span><?php echo number_format($percentage, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Overdue Books Alert -->
            <?php if (!empty($overdueBooks)): ?>
                <div class="section-card">
                    <h3 style="color: var(--danger);">⚠️ Overdue Books Requiring Attention</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Book</th>
                                    <th>Due Date</th>
                                    <th>Days Overdue</th>
                                    <th>Calculated Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueBooks as $ob): ?>
                                    <tr style="background: #fee2e2;">
                                        <td>
                                            <strong><?php echo htmlspecialchars($ob['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($ob['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($ob['phone'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($ob['title']); ?></td>
                                        <td><?php echo formatDate($ob['due_date'], 'M d, Y'); ?></td>
                                        <td><span class="badge badge-danger"><?php echo $ob['days_overdue']; ?> days</span></td>
                                        <td><span class="badge badge-warning">Rs. <?php echo number_format($ob['calculated_fine'], 2); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>