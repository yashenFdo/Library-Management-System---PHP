<?php
require_once 'config.php';
requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get user's borrowings
$borrowings = $db->query("SELECT br.*, bk.title, bk.cover_image, bk.isbn, a.author_name, c.category_name,
                          DATEDIFF(CURDATE(), br.due_date) as days_overdue
                          FROM borrowings br
                          JOIN books bk ON br.book_id = bk.book_id
                          LEFT JOIN authors a ON bk.author_id = a.author_id
                          LEFT JOIN categories c ON bk.category_id = c.category_id
                          WHERE br.user_id = $userId
                          ORDER BY br.borrowed_date DESC")->fetch_all(MYSQLI_ASSOC);

// Statistics
$currentBorrowed = 0;
$overdueCount = 0;
$totalFines = 0;
$totalBorrowed = count($borrowings);

foreach ($borrowings as $b) {
    if ($b['status'] === 'borrowed') {
        $currentBorrowed++;
        if ($b['days_overdue'] > 0) {
            $overdueCount++;
        }
    }
    $totalFines += $b['fine_amount'];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>My Borrowings</h1>
                <p>View your borrowing history and current books</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card blue">
                    <div class="stat-icon">📖</div>
                    <div class="stat-details">
                        <h3><?php echo $currentBorrowed; ?> / <?php echo DEFAULT_MAX_BOOKS; ?></h3>
                        <p>Currently Borrowed</p>
                    </div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-details">
                        <h3><?php echo $overdueCount; ?></h3>
                        <p>Overdue Books</p>
                    </div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <h3>Rs. <?php echo number_format($totalFines, 2); ?></h3>
                        <p>Total Fines</p>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">📚</div>
                    <div class="stat-details">
                        <h3><?php echo $totalBorrowed; ?></h3>
                        <p>Total Borrowed</p>
                    </div>
                </div>
            </div>
            
            <!-- Currently Borrowed Books -->
            <?php 
            $currentBooks = array_filter($borrowings, function($b) {
                return $b['status'] === 'borrowed';
            });
            ?>
            
            <?php if (!empty($currentBooks)): ?>
                <div class="section-card">
                    <h3>Currently Borrowed Books</h3>
                    <div class="books-grid">
                        <?php foreach ($currentBooks as $b): ?>
                            <?php 
                            $isOverdue = $b['days_overdue'] > 0;
                            $daysLeft = -$b['days_overdue'];
                            ?>
                            <div class="book-card" style="<?php echo $isOverdue ? 'border: 2px solid var(--danger);' : ''; ?>">
                                <div class="book-cover">
                                    <?php if ($b['cover_image']): ?>
                                        <img src="<?php echo UPLOAD_URL . 'books/' . $b['cover_image']; ?>" alt="Book cover">
                                    <?php else: ?>
                                        <div class="no-cover">📚</div>
                                    <?php endif; ?>
                                    
                                    <?php if ($isOverdue): ?>
                                        <div style="position: absolute; top: 10px; right: 10px;">
                                            <span class="badge badge-danger">Overdue</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-details">
                                    <h4><?php echo htmlspecialchars($b['title']); ?></h4>
                                    <p class="author">by <?php echo htmlspecialchars($b['author_name'] ?? 'Unknown'); ?></p>
                                    
                                    <div style="margin: 10px 0; font-size: 0.85rem;">
                                        <p><strong>Borrowed:</strong> <?php echo formatDate($b['borrowed_date'], 'M d, Y'); ?></p>
                                        <p><strong>Due:</strong> <?php echo formatDate($b['due_date'], 'M d, Y'); ?></p>
                                        
                                        <?php if ($isOverdue): ?>
                                            <p style="color: var(--danger); font-weight: bold;">
                                                Overdue by <?php echo $b['days_overdue']; ?> day(s)
                                            </p>
                                            <p style="color: var(--danger);">
                                                <strong>Fine:</strong> Rs. <?php echo number_format($b['days_overdue'] * DEFAULT_FINE_PER_DAY, 2); ?>
                                            </p>
                                        <?php else: ?>
                                            <p style="color: var(--success);">
                                                <?php echo $daysLeft; ?> day(s) remaining
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Borrowing History -->
            <div class="section-card">
                <h3>Borrowing History</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Author</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($borrowings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div style="padding: 40px;">
                                            <div style="font-size: 3rem; margin-bottom: 10px;">📚</div>
                                            <p>You haven't borrowed any books yet.</p>
                                            <a href="browse_books.php" class="btn btn-primary" style="margin-top: 15px;">Browse Books</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($borrowings as $b): ?>
                                    <?php 
                                    $isOverdue = $b['status'] === 'borrowed' && $b['days_overdue'] > 0;
                                    $statusClass = $b['status'] === 'returned' ? 'success' : ($isOverdue ? 'danger' : 'info');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['title']); ?></strong><br>
                                            <small>ISBN: <?php echo htmlspecialchars($b['isbn'] ?? '-'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($b['author_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo formatDate($b['borrowed_date'], 'M d, Y'); ?></td>
                                        <td><?php echo formatDate($b['due_date'], 'M d, Y'); ?></td>
                                        <td><?php echo formatDate($b['return_date'], 'M d, Y'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php 
                                                if ($isOverdue) {
                                                    echo "Overdue ({$b['days_overdue']} days)";
                                                } else {
                                                    echo ucfirst($b['status']);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($b['fine_amount'] > 0): ?>
                                                <span class="badge badge-warning">Rs. <?php echo number_format($b['fine_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-success">None</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($overdueCount > 0 || $totalFines > 0): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Important Notice:</strong><br>
                    <?php if ($overdueCount > 0): ?>
                        You have <?php echo $overdueCount; ?> overdue book(s). Please return them as soon as possible to avoid additional fines.<br>
                    <?php endif; ?>
                    <?php if ($totalFines > 0): ?>
                        You have outstanding fines totaling Rs. <?php echo number_format($totalFines, 2); ?>. Please pay at the library counter.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>