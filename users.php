<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN, ROLE_ADMIN]);

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $fullName = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $role = sanitize($_POST['role']);
        $status = sanitize($_POST['status']);
        
        // Role validation - admins can't create super_admins
        if ($role === ROLE_SUPER_ADMIN && $_SESSION['role'] !== ROLE_SUPER_ADMIN) {
            flashMessage('error', 'You cannot create super admin users!');
            redirect('users.php');
        }
        
        if ($action === 'add') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $username, $email, $password, $fullName, $phone, $address, $role, $status);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'ADD_USER', 'user', $db->lastInsertId(), "Added user: $username");
                flashMessage('success', 'User created successfully!');
            } else {
                flashMessage('error', 'Failed to create user: ' . $stmt->error);
            }
        } else {
            $userId = intval($_POST['user_id']);
            
            // Prevent editing super admin by non-super admin
            $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userRole = $stmt->get_result()->fetch_assoc()['role'];
            
            if ($userRole === ROLE_SUPER_ADMIN && $_SESSION['role'] !== ROLE_SUPER_ADMIN) {
                flashMessage('error', 'You cannot edit super admin users!');
                redirect('users.php');
            }
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username=?, email=?, password=?, full_name=?, phone=?, address=?, role=?, status=? WHERE user_id=?");
                $stmt->bind_param("ssssssssi", $username, $email, $password, $fullName, $phone, $address, $role, $status, $userId);
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, email=?, full_name=?, phone=?, address=?, role=?, status=? WHERE user_id=?");
                $stmt->bind_param("sssssssi", $username, $email, $fullName, $phone, $address, $role, $status, $userId);
            }
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'UPDATE_USER', 'user', $userId, "Updated user: $username");
                flashMessage('success', 'User updated successfully!');
            } else {
                flashMessage('error', 'Failed to update user: ' . $stmt->error);
            }
        }
        redirect('users.php');
    }
    
    // AJAX Delete
    if (isset($_POST['ajax']) && $_POST['ajax_action'] === 'delete') {
        $userId = intval($_POST['user_id']);
        
        // Prevent self-deletion
        if ($userId === $_SESSION['user_id']) {
            jsonResponse(false, 'You cannot delete your own account!');
        }
        
        // Check user role
        $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userRole = $stmt->get_result()->fetch_assoc()['role'];
        
        if ($userRole === ROLE_SUPER_ADMIN && $_SESSION['role'] !== ROLE_SUPER_ADMIN) {
            jsonResponse(false, 'You cannot delete super admin users!');
        }
        
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'DELETE_USER', 'user', $userId, 'Deleted user');
            jsonResponse(true, 'User deleted successfully!');
        } else {
            jsonResponse(false, 'Failed to delete user');
        }
    }
}

// Get all users
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$roleFilter = isset($_GET['role_filter']) ? sanitize($_GET['role_filter']) : '';

$sql = "SELECT * FROM users WHERE 1=1";
if ($search) {
    $sql .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
}
if ($roleFilter) {
    $sql .= " AND role = '$roleFilter'";
}
$sql .= " ORDER BY created_at DESC";

$users = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header d-flex justify-between align-center">
                <div>
                    <h1>Users Management</h1>
                    <p>Manage system users and permissions</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ Add New User
                </button>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="section-card">
                <form method="GET" class="d-flex gap-10 align-center">
                    <input type="text" name="search" class="form-control" placeholder="Search users..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="max-width: 400px;">
                    
                    <select name="role_filter" class="form-control" style="max-width: 200px;">
                        <option value="">All Roles</option>
                        <option value="super_admin" <?php echo $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="staff" <?php echo $roleFilter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="users.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="section-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-warning btn-sm" onclick='editUser(<?php echo json_encode($user); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                                    🗑️ Delete
                                                </button>
                                            <?php endif; ?>
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
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New User</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="userForm" method="POST">
                <input type="hidden" name="user_id" id="user_id">
                <input type="hidden" name="action" id="formAction" value="add">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span id="passwordHint">*</span></label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="text-muted">Leave blank to keep current password (when editing)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select class="form-control" id="role" name="role" required>
                            <?php if ($_SESSION['role'] === ROLE_SUPER_ADMIN): ?>
                                <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                </div>
                
                <div class="d-flex gap-10 mt-20">
                    <button type="submit" class="btn btn-primary">Save User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New User';
            document.getElementById('userForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('formAction').value = 'add';
            document.getElementById('password').required = true;
            document.getElementById('passwordHint').textContent = '*';
            document.getElementById('userModal').classList.add('active');
        }
        
        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('user_id').value = user.user_id;
            document.getElementById('formAction').value = 'edit';
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('address').value = user.address || '';
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;
            document.getElementById('password').required = false;
            document.getElementById('password').value = '';
            document.getElementById('passwordHint').textContent = '(optional)';
            document.getElementById('userModal').classList.add('active');
        }
        
        function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
            
            fetch('users.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ajax=1&ajax_action=delete&user_id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            });
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.remove('active');
        }
    </script>
</body>
</html>