<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $bookId = intval($_POST['book_id']);
        
        // Check if book is borrowed
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE book_id = ? AND status = 'borrowed'");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $borrowed = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($borrowed > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete book that is currently borrowed']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $bookId);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'DELETE_BOOK', 'book', $bookId, 'Deleted book');
            echo json_encode(['success' => true, 'message' => 'Book deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete book']);
        }
        exit;
    }
}

// Get all books with joins
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;

$sql = "SELECT b.*, c.category_name, a.author_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        LEFT JOIN authors a ON b.author_id = a.author_id 
        WHERE 1=1";

if ($search) {
    $sql .= " AND (b.title LIKE '%$search%' OR b.isbn LIKE '%$search%' OR a.author_name LIKE '%$search%')";
}

if ($category > 0) {
    $sql .= " AND b.category_id = $category";
}

$sql .= " ORDER BY b.created_at DESC";

$books = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Get authors for add/edit
$authors = $db->query("SELECT * FROM authors ORDER BY author_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header d-flex justify-between align-center">
                <div>
                    <h1>Books Management</h1>
                    <p>Manage your library's book collection</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ Add New Book
                </button>
            </div>
            
            <!-- Filters -->
            <div class="section-card">
                <form method="GET" class="d-flex gap-10 align-center">
                    <input type="text" name="search" class="form-control" placeholder="Search by title, ISBN, or author..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="max-width: 400px;">
                    
                    <select name="category" class="form-control" style="max-width: 200px;">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="books.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
            
            <!-- Books Table -->
            <div class="section-card">
                <div class="table-responsive">
                    <table class="data-table" id="booksTable">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>ISBN</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Available</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr data-id="<?php echo $book['book_id']; ?>">
                                    <td>
                                        <?php if ($book['cover_image']): ?>
                                            <img src="<?php echo UPLOAD_URL . 'books/' . $book['cover_image']; ?>" 
                                                 alt="Cover" style="width: 50px; height: 70px; object-fit: cover; border-radius: 5px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 70px; background: var(--light); display: flex; align-items: center; justify-content: center; border-radius: 5px;">📚</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['isbn'] ?? '-'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($book['category_name'] ?? '-'); ?></span></td>
                                    <td><?php echo $book['quantity']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $book['available_quantity'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $book['available_quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick='viewBook(<?php echo json_encode($book); ?>)'>
                                                👁️ View
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick='editBook(<?php echo json_encode($book); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteBook(<?php echo $book['book_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Modal -->
    <div id="bookModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Book</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="bookForm" action="book_process.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="book_id" id="book_id">
                <input type="hidden" name="action" id="formAction" value="add">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" class="form-control" id="isbn" name="isbn">
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="author_id">Author *</label>
                        <select class="form-control" id="author_id" name="author_id" required>
                            <option value="">Select Author</option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author['author_id']; ?>">
                                    <?php echo htmlspecialchars($author['author_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category *</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" class="form-control" id="publisher" name="publisher">
                    </div>
                    
                    <div class="form-group">
                        <label for="publish_year">Publish Year</label>
                        <input type="number" class="form-control" id="publish_year" name="publish_year" min="1800" max="2100">
                    </div>
                    
                    <div class="form-group">
                        <label for="pages">Pages</label>
                        <input type="number" class="form-control" id="pages" name="pages" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" class="form-control" id="location" name="location" placeholder="e.g., Shelf A-12">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cover_image">Cover Image</label>
                    <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
                </div>
                
                <div class="d-flex gap-10 mt-20">
                    <button type="submit" class="btn btn-primary">Save Book</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Book Details</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewContent"></div>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Book';
            document.getElementById('bookForm').reset();
            document.getElementById('book_id').value = '';
            document.getElementById('formAction').value = 'add';
            document.getElementById('bookModal').classList.add('active');
        }
        
        function editBook(book) {
            document.getElementById('modalTitle').textContent = 'Edit Book';
            document.getElementById('book_id').value = book.book_id;
            document.getElementById('formAction').value = 'edit';
            document.getElementById('isbn').value = book.isbn || '';
            document.getElementById('title').value = book.title;
            document.getElementById('author_id').value = book.author_id || '';
            document.getElementById('category_id').value = book.category_id || '';
            document.getElementById('publisher').value = book.publisher || '';
            document.getElementById('publish_year').value = book.publish_year || '';
            document.getElementById('pages').value = book.pages || '';
            document.getElementById('quantity').value = book.quantity;
            document.getElementById('location').value = book.location || '';
            document.getElementById('description').value = book.description || '';
            document.getElementById('bookModal').classList.add('active');
        }
        
        function viewBook(book) {
            const content = `
                <div style="text-align: center; margin-bottom: 20px;">
                    ${book.cover_image ? 
                        `<img src="<?php echo UPLOAD_URL; ?>books/${book.cover_image}" style="max-width: 200px; border-radius: 10px;">` :
                        '<div style="font-size: 5rem;">📚</div>'}
                </div>
                <table style="width: 100%;">
                    <tr><td><strong>ISBN:</strong></td><td>${book.isbn || '-'}</td></tr>
                    <tr><td><strong>Title:</strong></td><td>${book.title}</td></tr>
                    <tr><td><strong>Author:</strong></td><td>${book.author_name || 'Unknown'}</td></tr>
                    <tr><td><strong>Category:</strong></td><td>${book.category_name || '-'}</td></tr>
                    <tr><td><strong>Publisher:</strong></td><td>${book.publisher || '-'}</td></tr>
                    <tr><td><strong>Publish Year:</strong></td><td>${book.publish_year || '-'}</td></tr>
                    <tr><td><strong>Pages:</strong></td><td>${book.pages || '-'}</td></tr>
                    <tr><td><strong>Quantity:</strong></td><td>${book.quantity}</td></tr>
                    <tr><td><strong>Available:</strong></td><td>${book.available_quantity}</td></tr>
                    <tr><td><strong>Location:</strong></td><td>${book.location || '-'}</td></tr>
                    <tr><td><strong>Description:</strong></td><td>${book.description || '-'}</td></tr>
                </table>
            `;
            document.getElementById('viewContent').innerHTML = content;
            document.getElementById('viewModal').classList.add('active');
        }
        
        function deleteBook(id) {
            if (!confirm('Are you sure you want to delete this book?')) return;
            
            fetch('books.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&action=delete&book_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function closeModal() {
            document.getElementById('bookModal').classList.remove('active');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
    </script>
</body>
</html>