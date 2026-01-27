<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

// Filters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$sql = "SELECT br.*, u.full_name, u.username, bk.title, bk.isbn, a.author_name,
        iu.full_name as issued_by_name, ru.full_name as returned_by_name,
        DATEDIFF(CURDATE(), br.due_date) as days_overdue
        FROM borrowings br
        JOIN users u ON br.user_id = u.user_id
        JOIN books bk ON br.book_id = bk.book_id
        LEFT JOIN authors a ON bk.author_id = a.author_id
        LEFT JOIN users iu ON br.issued_by = iu.user_id
        LEFT JOIN users ru ON br.returned_to = ru.user_id
        WHERE 1=1";

if ($status) {
    $sql .= " AND br.status = '$status'";
}

if ($search) {
    $sql .= " AND (u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR bk.title LIKE '%$search%' OR bk.isbn LIKE '%$search%')";
}

if ($dateFrom) {
    $sql .= " AND br.borrowed_date >= '$dateFrom'";
}

if ($dateTo) {
    $sql .= " AND br.borrowed_date <= '$dateTo'";
}

$sql .= " ORDER BY br.borrowed_date DESC, br.borrowing_id DESC";

$borrowings = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats = [];
$stats['total'] = $db->query("SELECT COUNT(*) as count FROM borrowings")->fetch_assoc()['count'];
$stats['borrowed'] = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'borrowed'")->fetch_assoc()['count'];
$stats['returned'] = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'returned'")->fetch_assoc()['count'];
$stats['overdue'] = $db->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'borrowed' AND due_date < CURDATE()")->fetch_assoc()['count'];
$stats['total_fines'] = $db->query("SELECT SUM(fine_amount) as total FROM borrowings WHERE fine_amount > 0")->fetch_assoc()['total'] ?? 0;

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Borrowings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>All Borrowing Records</h1>
                <p>Complete borrowing history and management</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">📚</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total']); ?></h3>
                        <p>Total Transactions</p>
                    </div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-icon">📖</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['borrowed']); ?></h3>
                        <p>Currently Borrowed</p>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">✅</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['returned']); ?></h3>
                        <p>Returned</p>
                    </div>
                </div>
                
                <div class="stat-card red">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['overdue']); ?></h3>
                        <p>Overdue</p>
                    </div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <h3>Rs. <?php echo number_format($stats['total_fines'], 2); ?></h3>
                        <p>Total Fines</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="section-card">
                <form method="GET" class="form-grid">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="User, book title, ISBN..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="borrowed" <?php echo $status === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                            <option value="returned" <?php echo $status === 'returned' ? 'selected' : ''; ?>>Returned</option>
                            <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="borrowings.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Borrowings Table -->
            <div class="section-card">
                <h3>Borrowing Records (<?php echo count($borrowings); ?>)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Book</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                                <th>Issued By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($borrowings)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No borrowing records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($borrowings as $b): ?>
                                    <?php 
                                    $isOverdue = $b['status'] === 'borrowed' && $b['days_overdue'] > 0;
                                    $statusClass = $b['status'] === 'returned' ? 'success' : ($isOverdue ? 'danger' : 'info');
                                    ?>
                                    <tr>
                                        <td><?php echo $b['borrowing_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($b['username']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['title']); ?></strong><br>
                                            <small>by <?php echo htmlspecialchars($b['author_name'] ?? 'Unknown'); ?></small>
                                        </td>
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
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($b['issued_by_name'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>