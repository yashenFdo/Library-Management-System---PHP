<?php
require_once 'config.php';
requireLogin();

$db = Database::getInstance();

// Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$availability = isset($_GET['availability']) ? sanitize($_GET['availability']) : '';

// Build query
$sql = "SELECT b.*, c.category_name, a.author_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.category_id
        LEFT JOIN authors a ON b.author_id = a.author_id
        WHERE 1=1";

if ($search) {
    $sql .= " AND (b.title LIKE '%$search%' OR a.author_name LIKE '%$search%' OR b.isbn LIKE '%$search%')";
}

if ($category > 0) {
    $sql .= " AND b.category_id = $category";
}

if ($availability === 'available') {
    $sql .= " AND b.available_quantity > 0";
}

$sql .= " ORDER BY b.title";

$books = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Browse Books</h1>
                <p>Explore our collection of <?php echo count($books); ?> books</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="section-card">
                <form method="GET" class="form-grid">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Book title, author, ISBN..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select class="form-control" id="category" name="category">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="availability">Availability</label>
                        <select class="form-control" id="availability" name="availability">
                            <option value="">All Books</option>
                            <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available Only</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                        <a href="browse_books.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Books Grid -->
            <div class="section-card">
                <?php if (empty($books)): ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <div style="font-size: 4rem; margin-bottom: 20px;">📚</div>
                        <h3>No books found</h3>
                        <p>Try adjusting your search criteria</p>
                    </div>
                <?php else: ?>
                    <div class="books-grid">
                        <?php foreach ($books as $book): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <?php if ($book['cover_image']): ?>
                                        <img src="<?php echo UPLOAD_URL . 'books/' . $book['cover_image']; ?>" alt="Book cover">
                                    <?php else: ?>
                                        <div class="no-cover">📚</div>
                                    <?php endif; ?>
                                    
                                    <?php if ($book['available_quantity'] > 0): ?>
                                        <div style="position: absolute; top: 10px; right: 10px;">
                                            <span class="badge badge-success">Available</span>
                                        </div>
                                    <?php else: ?>
                                        <div style="position: absolute; top: 10px; right: 10px;">
                                            <span class="badge badge-danger">Unavailable</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-details">
                                    <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                                    <p class="author">by <?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?></p>
                                    <p style="font-size: 0.85rem; color: var(--gray); margin: 5px 0;">
                                        <span class="badge badge-info"><?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?></span>
                                    </p>
                                    <p style="font-size: 0.85rem; margin: 10px 0;">
                                        <strong>Available:</strong> <?php echo $book['available_quantity']; ?> / <?php echo $book['quantity']; ?>
                                    </p>
                                    <button class="btn btn-primary btn-sm" onclick='viewBookDetails(<?php echo json_encode($book); ?>)' 
                                            style="width: 100%; margin-top: 10px;">
                                        👁️ View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Book Details Modal -->
    <div id="bookModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Book Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="bookDetails"></div>
        </div>
    </div>
    
    <script>
        function viewBookDetails(book) {
            const available = book.available_quantity > 0;
            const statusBadge = available 
                ? '<span class="badge badge-success">Available for borrowing</span>'
                : '<span class="badge badge-danger">Currently unavailable</span>';
            
            const content = `
                <div style="text-align: center; margin-bottom: 20px;">
                    ${book.cover_image 
                        ? `<img src="<?php echo UPLOAD_URL; ?>books/${book.cover_image}" style="max-width: 200px; max-height: 300px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">`
                        : '<div style="font-size: 5rem; color: var(--gray); opacity: 0.3;">📚</div>'}
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Title:</td>
                        <td style="padding: 10px;">${book.title}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Author:</td>
                        <td style="padding: 10px;">${book.author_name || 'Unknown'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Category:</td>
                        <td style="padding: 10px;">${book.category_name || '-'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">ISBN:</td>
                        <td style="padding: 10px;">${book.isbn || '-'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Publisher:</td>
                        <td style="padding: 10px;">${book.publisher || '-'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Year:</td>
                        <td style="padding: 10px;">${book.publish_year || '-'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Pages:</td>
                        <td style="padding: 10px;">${book.pages || '-'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Location:</td>
                        <td style="padding: 10px;">${book.location || '-'}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 10px; font-weight: bold;">Availability:</td>
                        <td style="padding: 10px;">${book.available_quantity} / ${book.quantity} copies</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold;">Status:</td>
                        <td style="padding: 10px;">${statusBadge}</td>
                    </tr>
                </table>
                ${book.description ? `
                    <div style="margin-top: 20px; padding: 15px; background: var(--light); border-radius: 8px;">
                        <strong>Description:</strong>
                        <p style="margin-top: 10px; text-align: justify;">${book.description}</p>
                    </div>
                ` : ''}
                <div style="margin-top: 20px; text-align: center;">
                    <p style="color: var(--gray); font-size: 0.9rem;">
                        To borrow this book, please visit the library or contact the librarian.
                    </p>
                </div>
            `;
            
            document.getElementById('bookDetails').innerHTML = content;
            document.getElementById('bookModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('bookModal').classList.remove('active');
        }
    </script>
    
    <style>
        .book-cover {
            position: relative;
        }
    </style>
</body>
</html>