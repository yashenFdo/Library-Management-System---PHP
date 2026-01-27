<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    $authorName = sanitize($_POST['author_name']);
    $biography = sanitize($_POST['biography'] ?? '');
    $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $nationality = sanitize($_POST['nationality'] ?? '');
    
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO authors (author_name, biography, birth_date, nationality) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $authorName, $biography, $birthDate, $nationality);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'ADD_AUTHOR', 'author', $db->lastInsertId(), "Added author: $authorName");
            flashMessage('success', 'Author added successfully!');
        } else {
            flashMessage('error', 'Failed to add author!');
        }
    } elseif ($action === 'edit') {
        $authorId = intval($_POST['author_id']);
        $stmt = $db->prepare("UPDATE authors SET author_name = ?, biography = ?, birth_date = ?, nationality = ? WHERE author_id = ?");
        $stmt->bind_param("ssssi", $authorName, $biography, $birthDate, $nationality, $authorId);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'UPDATE_AUTHOR', 'author', $authorId, "Updated author: $authorName");
            flashMessage('success', 'Author updated successfully!');
        } else {
            flashMessage('error', 'Failed to update author!');
        }
    }
    redirect('authors.php');
}

// Handle AJAX delete
if (isset($_POST['ajax']) && $_POST['ajax_action'] === 'delete') {
    $authorId = intval($_POST['author_id']);
    
    // Check if author has books
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM books WHERE author_id = ?");
    $stmt->bind_param("i", $authorId);
    $stmt->execute();
    $bookCount = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($bookCount > 0) {
        jsonResponse(false, "Cannot delete author with $bookCount book(s). Please reassign or delete the books first.");
    }
    
    $stmt = $db->prepare("DELETE FROM authors WHERE author_id = ?");
    $stmt->bind_param("i", $authorId);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'DELETE_AUTHOR', 'author', $authorId, 'Deleted author');
        jsonResponse(true, 'Author deleted successfully!');
    } else {
        jsonResponse(false, 'Failed to delete author!');
    }
}

// Get all authors with book count
$authors = $db->query("SELECT a.*, COUNT(b.book_id) as book_count
                       FROM authors a
                       LEFT JOIN books b ON a.author_id = b.author_id
                       GROUP BY a.author_id
                       ORDER BY a.author_name")->fetch_all(MYSQLI_ASSOC);

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authors - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header d-flex justify-between align-center">
                <div>
                    <h1>Authors Management</h1>
                    <p>Manage book authors</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ Add Author
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
                                <th>Author Name</th>
                                <th>Nationality</th>
                                <th>Birth Date</th>
                                <th>Books Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($authors as $author): ?>
                                <tr>
                                    <td><?php echo $author['author_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($author['author_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($author['nationality'] ?: '-'); ?></td>
                                    <td><?php echo formatDate($author['birth_date'], 'M d, Y'); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $author['book_count']; ?> books</span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick='viewAuthor(<?php echo json_encode($author); ?>)'>
                                                👁️ View
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick='editAuthor(<?php echo json_encode($author); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteAuthor(<?php echo $author['author_id']; ?>)">
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
    <div id="authorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Author</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="author_id" id="author_id">
                <input type="hidden" name="action" id="action" value="add">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="author_name">Author Name *</label>
                        <input type="text" class="form-control" id="author_name" name="author_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nationality">Nationality</label>
                        <input type="text" class="form-control" id="nationality" name="nationality">
                    </div>
                    
                    <div class="form-group">
                        <label for="birth_date">Birth Date</label>
                        <input type="date" class="form-control" id="birth_date" name="birth_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="biography">Biography</label>
                    <textarea class="form-control" id="biography" name="biography" rows="4"></textarea>
                </div>
                
                <div class="d-flex gap-10 mt-20">
                    <button type="submit" class="btn btn-primary">Save Author</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Author Details</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewContent"></div>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Author';
            document.getElementById('author_id').value = '';
            document.getElementById('action').value = 'add';
            document.getElementById('author_name').value = '';
            document.getElementById('nationality').value = '';
            document.getElementById('birth_date').value = '';
            document.getElementById('biography').value = '';
            document.getElementById('authorModal').classList.add('active');
        }
        
        function editAuthor(author) {
            document.getElementById('modalTitle').textContent = 'Edit Author';
            document.getElementById('author_id').value = author.author_id;
            document.getElementById('action').value = 'edit';
            document.getElementById('author_name').value = author.author_name;
            document.getElementById('nationality').value = author.nationality || '';
            document.getElementById('birth_date').value = author.birth_date || '';
            document.getElementById('biography').value = author.biography || '';
            document.getElementById('authorModal').classList.add('active');
        }
        
        function viewAuthor(author) {
            const content = `
                <div style="padding: 20px;">
                    <h4>${author.author_name}</h4>
                    <hr style="margin: 15px 0;">
                    <p><strong>Nationality:</strong> ${author.nationality || '-'}</p>
                    <p><strong>Birth Date:</strong> ${author.birth_date ? new Date(author.birth_date).toLocaleDateString() : '-'}</p>
                    <p><strong>Books Count:</strong> ${author.book_count}</p>
                    <hr style="margin: 15px 0;">
                    <p><strong>Biography:</strong></p>
                    <p style="text-align: justify;">${author.biography || 'No biography available.'}</p>
                </div>
            `;
            document.getElementById('viewContent').innerHTML = content;
            document.getElementById('viewModal').classList.add('active');
        }
        
        function deleteAuthor(id) {
            if (!confirm('Are you sure you want to delete this author?')) return;
            
            fetch('authors.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&ajax_action=delete&author_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function closeModal() {
            document.getElementById('authorModal').classList.remove('active');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
    </script>
</body>
</html>