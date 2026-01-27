<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return'])) {
    $borrowingId = intval($_POST['borrowing_id']);
    $returnDate = $_POST['return_date'] ?? date('Y-m-d');
    $returnedTo = $_SESSION['user_id'];
    
    // Get borrowing details
    $stmt = $db->prepare("SELECT b.*, bk.title FROM borrowings b 
                          JOIN books bk ON b.book_id = bk.book_id 
                          WHERE b.borrowing_id = ?");
    $stmt->bind_param("i", $borrowingId);
    $stmt->execute();
    $borrowing = $stmt->get_result()->fetch_assoc();
    
    if (!$borrowing) {
        flashMessage('error', 'Borrowing record not found!');
        redirect('return_book.php');
    }
    
    if ($borrowing['status'] !== 'borrowed') {
        flashMessage('error', 'This book has already been returned!');
        redirect('return_book.php');
    }
    
    // Calculate fine
    $fine = calculateFine($borrowing['due_date'], $returnDate);
    $status = $fine > 0 ? 'overdue' : 'returned';
    
    // Update borrowing record
    $stmt = $db->prepare("UPDATE borrowings SET return_date = ?, status = ?, fine_amount = ?, returned_to = ? WHERE borrowing_id = ?");
    $stmt->bind_param("ssdii", $returnDate, $status, $fine, $returnedTo, $borrowingId);
    
    if ($stmt->execute()) {
        // Update book availability
        $db->query("UPDATE books SET available_quantity = available_quantity + 1 WHERE book_id = {$borrowing['book_id']}");
        
        // Log activity
        logActivity($_SESSION['user_id'], 'RETURN_BOOK', 'borrowing', $borrowingId, "Returned book: {$borrowing['title']}" . ($fine > 0 ? " (Fine: Rs. $fine)" : ""));
        
        $message = 'Book returned successfully!';
        if ($fine > 0) {
            $message .= " Fine amount: Rs. " . number_format($fine, 2);
        }
        flashMessage('success', $message);
    } else {
        flashMessage('error', 'Failed to process return!');
    }
    
    redirect('return_book.php');
}

// Get all currently borrowed books
$borrowings = $db->query("SELECT br.*, u.full_name, u.username, bk.title, bk.isbn, a.author_name,
                          DATEDIFF(CURDATE(), br.due_date) as days_overdue
                          FROM borrowings br
                          JOIN users u ON br.user_id = u.user_id
                          JOIN books bk ON br.book_id = bk.book_id
                          LEFT JOIN authors a ON bk.author_id = a.author_id
                          WHERE br.status = 'borrowed'
                          ORDER BY br.due_date ASC")->fetch_all(MYSQLI_ASSOC);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Return Book</h1>
                <p>Process book returns and calculate fines</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="section-card">
                <h3>Currently Borrowed Books</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($borrowings)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No borrowed books found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($borrowings as $b): ?>
                                    <?php 
                                    $isOverdue = $b['days_overdue'] > 0;
                                    $calculatedFine = $isOverdue ? $b['days_overdue'] * DEFAULT_FINE_PER_DAY : 0;
                                    ?>
                                    <tr class="<?php echo $isOverdue ? 'bg-danger-light' : ''; ?>">
                                        <td><?php echo $b['borrowing_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($b['username']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['title']); ?></strong><br>
                                            <small>ISBN: <?php echo htmlspecialchars($b['isbn'] ?? '-'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($b['author_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo formatDate($b['borrowed_date'], 'M d, Y'); ?></td>
                                        <td><?php echo formatDate($b['due_date'], 'M d, Y'); ?></td>
                                        <td>
                                            <?php if ($isOverdue): ?>
                                                <span class="badge badge-danger">
                                                    Overdue (<?php echo $b['days_overdue']; ?> days)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">On Time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($calculatedFine > 0): ?>
                                                <span class="badge badge-warning">
                                                    Rs. <?php echo number_format($calculatedFine, 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="openReturnModal(<?php echo htmlspecialchars(json_encode($b)); ?>, <?php echo $calculatedFine; ?>)">
                                                ↩️ Return
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Return Modal -->
    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Return Book</h3>
                <button class="close-modal" onclick="closeReturnModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="borrowing_id" id="borrowing_id">
                
                <div id="returnInfo" style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <!-- Info will be loaded by JavaScript -->
                </div>
                
                <div class="form-group">
                    <label for="return_date">Return Date *</label>
                    <input type="date" class="form-control" id="return_date" name="return_date" 
                           value="<?php echo date('Y-m-d'); ?>" required onchange="recalculateFine()">
                </div>
                
                <div id="fineDisplay" class="alert alert-warning" style="display: none;">
                    <strong>Fine Amount:</strong> <span id="fine_amount">Rs. 0.00</span>
                </div>
                
                <div class="d-flex gap-10 mt-20">
                    <button type="submit" name="return" class="btn btn-primary">Process Return</button>
                    <button type="button" class="btn btn-secondary" onclick="closeReturnModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentBorrowing = null;
        
        function openReturnModal(borrowing, fine) {
            currentBorrowing = borrowing;
            
            document.getElementById('borrowing_id').value = borrowing.borrowing_id;
            document.getElementById('return_date').value = '<?php echo date('Y-m-d'); ?>';
            
            const info = `
                <h4>Book Details</h4>
                <p><strong>User:</strong> ${borrowing.full_name}</p>
                <p><strong>Book:</strong> ${borrowing.title}</p>
                <p><strong>Author:</strong> ${borrowing.author_name || 'Unknown'}</p>
                <p><strong>Borrowed:</strong> ${formatDate(borrowing.borrowed_date)}</p>
                <p><strong>Due:</strong> ${formatDate(borrowing.due_date)}</p>
                ${borrowing.days_overdue > 0 ? `<p class="text-danger"><strong>Days Overdue:</strong> ${borrowing.days_overdue}</p>` : ''}
            `;
            
            document.getElementById('returnInfo').innerHTML = info;
            
            if (fine > 0) {
                document.getElementById('fine_amount').textContent = 'Rs. ' + fine.toFixed(2);
                document.getElementById('fineDisplay').style.display = 'block';
            } else {
                document.getElementById('fineDisplay').style.display = 'none';
            }
            
            document.getElementById('returnModal').classList.add('active');
        }
        
        function closeReturnModal() {
            document.getElementById('returnModal').classList.remove('active');
            currentBorrowing = null;
        }
        
        function recalculateFine() {
            if (!currentBorrowing) return;
            
            const returnDate = document.getElementById('return_date').value;
            const dueDate = new Date(currentBorrowing.due_date);
            const retDate = new Date(returnDate);
            
            const diffTime = retDate - dueDate;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays > 0) {
                const fine = diffDays * <?php echo DEFAULT_FINE_PER_DAY; ?>;
                document.getElementById('fine_amount').textContent = 'Rs. ' + fine.toFixed(2);
                document.getElementById('fineDisplay').style.display = 'block';
            } else {
                document.getElementById('fineDisplay').style.display = 'none';
            }
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
    </script>
    
    <style>
        .bg-danger-light {
            background-color: #fee2e2 !important;
        }
        
        .text-danger {
            color: #dc2626 !important;
        }
    </style>
</body>
</html>