<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    $categoryName = sanitize($_POST['category_name']);
    $description = sanitize($_POST['description'] ?? '');
    
    if ($action === 'add') {
        $createdBy = $_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO categories (category_name, description, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $categoryName, $description, $createdBy);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'ADD_CATEGORY', 'category', $db->lastInsertId(), "Added category: $categoryName");
            flashMessage('success', 'Category added successfully!');
        } else {
            flashMessage('error', 'Failed to add category!');
        }
    } elseif ($action === 'edit') {
        $categoryId = intval($_POST['category_id']);
        $stmt = $db->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
        $stmt->bind_param("ssi", $categoryName, $description, $categoryId);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'UPDATE_CATEGORY', 'category', $categoryId, "Updated category: $categoryName");
            flashMessage('success', 'Category updated successfully!');
        } else {
            flashMessage('error', 'Failed to update category!');
        }
    }
    redirect('categories.php');
}

// Handle AJAX delete
if (isset($_POST['ajax']) && $_POST['ajax_action'] === 'delete') {
    $categoryId = intval($_POST['category_id']);
    
    // Check if category has books
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM books WHERE category_id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $bookCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($bookCount > 0) {
        jsonResponse(false, "Cannot delete category with $bookCount book(s). Please reassign or delete the books first.");
    }
    
    $stmt = $db->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $categoryId);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'DELETE_CATEGORY', 'category', $categoryId, 'Deleted category');
        jsonResponse(true, 'Category deleted successfully!');
    } else {
        jsonResponse(false, 'Failed to delete category!');
    }
}

// Get all categories with book count
$categories = $db->query("SELECT c.*, COUNT(b.book_id) as book_count, u.full_name as created_by_name
                          FROM categories c
                          LEFT JOIN books b ON c.category_id = b.category_id
                          LEFT JOIN users u ON c.created_by = u.user_id
                          GROUP BY c.category_id
                          ORDER BY c.category_name")->fetch_all(MYSQLI_ASSOC);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header d-flex justify-between align-center">
                <div>
                    <h1>Categories Management</h1>
                    <p>Organize books by categories</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ Add Category
                </button>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="section-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Books Count</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['category_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($category['description'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $category['book_count']; ?> books</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['created_by_name'] ?? '-'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-warning btn-sm" onclick='editCategory(<?php echo json_encode($category); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteCategory(<?php echo $category['category_id']; ?>)">
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
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Category</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="category_id" id="category_id">
                <input type="hidden" name="action" id="action" value="add">
                
                <div class="form-group">
                    <label for="category_name">Category Name *</label>
                    <input type="text" class="form-control" id="category_name" name="category_name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="d-flex gap-10 mt-20">
                    <button type="submit" class="btn btn-primary">Save Category</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('category_id').value = '';
            document.getElementById('action').value = 'add';
            document.getElementById('category_name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('categoryModal').classList.add('active');
        }
        
        function editCategory(category) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('category_id').value = category.category_id;
            document.getElementById('action').value = 'edit';
            document.getElementById('category_name').value = category.category_name;
            document.getElementById('description').value = category.description || '';
            document.getElementById('categoryModal').classList.add('active');
        }
        
        function deleteCategory(id) {
            if (!confirm('Are you sure you want to delete this category?')) return;
            
            fetch('categories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&ajax_action=delete&category_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function closeModal() {
            document.getElementById('categoryModal').classList.remove('active');
        }
    </script>
</body>
</html>