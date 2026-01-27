<?php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="icon">🏠</span>
                <span>Dashboard</span>
            </a>
        </li>
        
        <?php if (hasRole([ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_STAFF])): ?>
            <hr class="sidebar-divider">
            
            <li>
                <a href="books.php" class="<?php echo $currentPage === 'books.php' ? 'active' : ''; ?>">
                    <span class="icon">📚</span>
                    <span>Books</span>
                </a>
            </li>
            
            <li>
                <a href="categories.php" class="<?php echo $currentPage === 'categories.php' ? 'active' : ''; ?>">
                    <span class="icon">📑</span>
                    <span>Categories</span>
                </a>
            </li>
            
            <li>
                <a href="authors.php" class="<?php echo $currentPage === 'authors.php' ? 'active' : ''; ?>">
                    <span class="icon">✍️</span>
                    <span>Authors</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <li>
                <a href="borrowings.php" class="<?php echo $currentPage === 'borrowings.php' ? 'active' : ''; ?>">
                    <span class="icon">📖</span>
                    <span>Borrowings</span>
                </a>
            </li>
            
            <li>
                <a href="issue_book.php" class="<?php echo $currentPage === 'issue_book.php' ? 'active' : ''; ?>">
                    <span class="icon">➕</span>
                    <span>Issue Book</span>
                </a>
            </li>
            
            <li>
                <a href="return_book.php" class="<?php echo $currentPage === 'return_book.php' ? 'active' : ''; ?>">
                    <span class="icon">↩️</span>
                    <span>Return Book</span>
                </a>
            </li>
        <?php else: ?>
            <hr class="sidebar-divider">
            
            <li>
                <a href="browse_books.php" class="<?php echo $currentPage === 'browse_books.php' ? 'active' : ''; ?>">
                    <span class="icon">🔍</span>
                    <span>Browse Books</span>
                </a>
            </li>
            
            <li>
                <a href="my_borrowings.php" class="<?php echo $currentPage === 'my_borrowings.php' ? 'active' : ''; ?>">
                    <span class="icon">📖</span>
                    <span>My Borrowings</span>
                </a>
            </li>
            
            <li>
                <a href="my_reservations.php" class="<?php echo $currentPage === 'my_reservations.php' ? 'active' : ''; ?>">
                    <span class="icon">🔖</span>
                    <span>My Reservations</span>
                </a>
            </li>
        <?php endif; ?>
        
        <?php if (hasRole([ROLE_SUPER_ADMIN, ROLE_ADMIN])): ?>
            <hr class="sidebar-divider">
            
            <li>
                <a href="users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    <span>Users</span>
                </a>
            </li>
            
            <li>
                <a href="reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span>Reports</span>
                </a>
            </li>
        <?php endif; ?>
        
        <?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
            <li>
                <a href="activity_logs.php" class="<?php echo $currentPage === 'activity_logs.php' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <span>Activity Logs</span>
                </a>
            </li>
            
            <li>
                <a href="settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span>
                    <span>Settings</span>
                </a>
            </li>
        <?php endif; ?>
        
        <hr class="sidebar-divider">
        
        <li>
            <a href="profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <span class="icon">👤</span>
                <span>My Profile</span>
            </a>
        </li>
    </ul>
</aside>