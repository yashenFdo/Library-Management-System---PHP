<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF]);

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Common fields
    $isbn = sanitize($_POST['isbn'] ?? '');
    $title = sanitize($_POST['title']);
    $authorId = intval($_POST['author_id']);
    $categoryId = intval($_POST['category_id']);
    $publisher = sanitize($_POST['publisher'] ?? '');
    $publishYear = !empty($_POST['publish_year']) ? intval($_POST['publish_year']) : null;
    $pages = !empty($_POST['pages']) ? intval($_POST['pages']) : null;
    $quantity = intval($_POST['quantity']);
    $location = sanitize($_POST['location'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    // Handle file upload
    $coverImage = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['cover_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newFilename = uniqid() . '.' . $ext;
            $uploadPath = UPLOAD_PATH . 'books/' . $newFilename;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadPath)) {
                $coverImage = $newFilename;
            }
        }
    }
    
    if ($action === 'add') {
        // Add new book
        $availableQty = $quantity;
        $addedBy = $_SESSION['user_id'];
        
        $sql = "INSERT INTO books (isbn, title, author_id, category_id, publisher, publish_year, pages, quantity, available_quantity, description, location, cover_image, added_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssiisiiissssi", $isbn, $title, $authorId, $categoryId, $publisher, $publishYear, $pages, $quantity, $availableQty, $description, $location, $coverImage, $addedBy);
        
        if ($stmt->execute()) {
            $bookId = $db->lastInsertId();
            logActivity($_SESSION['user_id'], 'ADD_BOOK', 'book', $bookId, "Added book: $title");
            flashMessage('success', 'Book added successfully!');
        } else {
            flashMessage('error', 'Failed to add book. ' . $stmt->error);
        }
        
    } elseif ($action === 'edit') {
        // Edit existing book
        $bookId = intval($_POST['book_id']);
        
        // Get current book data
        $stmt = $db->prepare("SELECT quantity, available_quantity, cover_image FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $bookId);
        $stmt->execute();
        $currentBook = $stmt->get_result()->fetch_assoc();
        
        // Calculate new available quantity
        $borrowed = $currentBook['quantity'] - $currentBook['available_quantity'];
        $newAvailable = $quantity - $borrowed;
        
        if ($newAvailable < 0) {
            flashMessage('error', 'Cannot reduce quantity below borrowed books count!');
            redirect('books.php');
        }
        
        // Use old cover if no new one uploaded
        if (empty($coverImage) && !empty($currentBook['cover_image'])) {
            $coverImage = $currentBook['cover_image'];
        }
        
        $sql = "UPDATE books SET 
                isbn = ?, title = ?, author_id = ?, category_id = ?, 
                publisher = ?, publish_year = ?, pages = ?, quantity = ?, 
                available_quantity = ?, description = ?, location = ?, cover_image = ?
                WHERE book_id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ssiisiissssi", $isbn, $title, $authorId, $categoryId, $publisher, $publishYear, $pages, $quantity, $newAvailable, $description, $location, $coverImage, $bookId);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'UPDATE_BOOK', 'book', $bookId, "Updated book: $title");
            flashMessage('success', 'Book updated successfully!');
        } else {
            flashMessage('error', 'Failed to update book. ' . $stmt->error);
        }
    }
}

redirect('books.php');
?>