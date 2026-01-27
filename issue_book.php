<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id']);
    $bookId = intval($_POST['book_id']);
    $borrowDays = intval($_POST['borrow_days']) ?: DEFAULT_BORROWING_DAYS;
    
    // Check if book is available
    $stmt = $db->prepare("SELECT title, available_quantity FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    
    if (!$book || $book['available_quantity'] <= 0) {
        flashMessage('error', 'Book is not available for borrowing!');
        redirect('issue_book.php');
    }
    
    // Check user's current borrowings
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $currentBorrowings = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($currentBorrowings >= DEFAULT_MAX_BOOKS) {
        flashMessage('error', 'User has reached maximum borrowing limit!');
        redirect('issue_book.php');
    }
    
    // Calculate dates
    $borrowedDate = date('Y-m-d');
    $dueDate = date('Y-m-d', strtotime("+$borrowDays days"));
    $issuedBy = $_SESSION['user_id'];
    
    // Insert borrowing record
    $stmt = $db->prepare("INSERT INTO borrowings (book_id, user_id, borrowed_date, due_date, status, issued_by) VALUES (?, ?, ?, ?, 'borrowed', ?)");
    $stmt->bind_param("iissi", $bookId, $userId, $borrowedDate, $dueDate, $issuedBy);
    
    if ($stmt->execute()) {
        // Update book availability
        $db->query("UPDATE books SET available_quantity = available_quantity - 1 WHERE book_id = $bookId");
        
        logActivity($_SESSION['user_id'], 'ISSUE_BOOK', 'borrowing', $db->lastInsertId(), "Issued book: {$book['title']} to user ID: $userId");
        flashMessage('success', 'Book issued successfully!');
    } else {
        flashMessage('error', 'Failed to issue book!');
    }
    
    redirect('issue_book.php');
}

// Get available books
$availableBooks = $db->query("SELECT b.*, a.author_name, c.category_name 
                               FROM books b 
                               LEFT JOIN authors a ON b.author_id = a.author_id 
                               LEFT JOIN categories c ON b.category_id = c.category_id 
                               WHERE b.available_quantity > 0 
                               ORDER BY b.title")->fetch_all(MYSQLI_ASSOC);

// Get active users
$users = $db->query("SELECT user_id, username, full_name, email 
                     FROM users 
                     WHERE role = 'user' AND status = 'active' 
                     ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Book - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Issue Book</h1>
                <p>Lend books to library members</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="section-card" style="max-width: 800px;">
                <form method="POST" id="issueForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="user_id">Select User *</label>
                            <select class="form-control" id="user_id" name="user_id" required onchange="loadUserInfo()">
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="book_id">Select Book *</label>
                            <select class="form-control" id="book_id" name="book_id" required onchange="loadBookInfo()">
                                <option value="">-- Select Book --</option>
                                <?php foreach ($availableBooks as $book): ?>
                                    <option value="<?php echo $book['book_id']; ?>"
                                            data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                            data-author="<?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?>"
                                            data-isbn="<?php echo htmlspecialchars($book['isbn'] ?? '-'); ?>"
                                            data-available="<?php echo $book['available_quantity']; ?>">
                                        <?php echo htmlspecialchars($book['title']); ?> - <?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?> 
                                        (Available: <?php echo $book['available_quantity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="borrow_days">Borrowing Period (Days) *</label>
                            <input type="number" class="form-control" id="borrow_days" name="borrow_days" 
                                   value="<?php echo DEFAULT_BORROWING_DAYS; ?>" min="1" max="90" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="text" class="form-control" id="due_date_display" readonly 
                                   style="background: #f8f9fa;">
                        </div>
                    </div>
                    
                    <!-- User Info Card -->
                    <div id="userInfo" class="mt-20" style="display: none; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4>User Information</h4>
                        <p><strong>Name:</strong> <span id="user_name"></span></p>
                        <p><strong>Email:</strong> <span id="user_email"></span></p>
                        <p><strong>Current Borrowings:</strong> <span id="user_borrowings">0</span> / <?php echo DEFAULT_MAX_BOOKS; ?></p>
                    </div>
                    
                    <!-- Book Info Card -->
                    <div id="bookInfo" class="mt-20" style="display: none; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h4>Book Information</h4>
                        <p><strong>Title:</strong> <span id="book_title"></span></p>
                        <p><strong>Author:</strong> <span id="book_author"></span></p>
                        <p><strong>ISBN:</strong> <span id="book_isbn"></span></p>
                        <p><strong>Available Copies:</strong> <span id="book_available"></span></p>
                    </div>
                    
                    <div class="d-flex gap-10 mt-20">
                        <button type="submit" class="btn btn-primary">📖 Issue Book</button>
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">🔄 Reset</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function loadUserInfo() {
            const select = document.getElementById('user_id');
            const option = select.options[select.selectedIndex];
            
            if (select.value) {
                document.getElementById('user_name').textContent = option.dataset.name;
                document.getElementById('user_email').textContent = option.dataset.email;
                document.getElementById('userInfo').style.display = 'block';
                
                // Load user's current borrowings via AJAX
                fetch(`get_user_borrowings.php?user_id=${select.value}`)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('user_borrowings').textContent = data.count;
                    });
            } else {
                document.getElementById('userInfo').style.display = 'none';
            }
        }
        
        function loadBookInfo() {
            const select = document.getElementById('book_id');
            const option = select.options[select.selectedIndex];
            
            if (select.value) {
                document.getElementById('book_title').textContent = option.dataset.title;
                document.getElementById('book_author').textContent = option.dataset.author;
                document.getElementById('book_isbn').textContent = option.dataset.isbn;
                document.getElementById('book_available').textContent = option.dataset.available;
                document.getElementById('bookInfo').style.display = 'block';
            } else {
                document.getElementById('bookInfo').style.display = 'none';
            }
        }
        
        function updateDueDate() {
            const days = parseInt(document.getElementById('borrow_days').value) || <?php echo DEFAULT_BORROWING_DAYS; ?>;
            const today = new Date();
            today.setDate(today.getDate() + days);
            
            const dueDate = today.toISOString().split('T')[0];
            document.getElementById('due_date_display').value = dueDate;
        }
        
        function resetForm() {
            document.getElementById('userInfo').style.display = 'none';
            document.getElementById('bookInfo').style.display = 'none';
            updateDueDate();
        }
        
        // Update due date on page load and when days change
        document.addEventListener('DOMContentLoaded', updateDueDate);
        document.getElementById('borrow_days').addEventListener('input', updateDueDate);
    </script>
</body>
</html>