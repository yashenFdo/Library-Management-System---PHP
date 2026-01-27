<?php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}
?>
<header class="main-header">
    <div class="logo">
        <span>📚</span>
        <span><?php echo SITE_NAME; ?></span>
    </div>
    
    <div class="header-right">
        <div class="search-box">
            <form action="search.php" method="GET">
                <input type="text" name="q" placeholder="Search books, authors..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                <button type="submit">🔍</button>
            </form>
        </div>
        
        <div class="user-menu" onclick="toggleUserDropdown()">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                <div class="user-role" style="font-size: 0.8rem; color: var(--gray);">
                    <?php echo ucwords(str_replace('_', ' ', $_SESSION['role'])); ?>
                </div>
            </div>
        </div>
        
        <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
</header>

<style>
.user-menu {
    position: relative;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-radius: 10px;
    margin-top: 10px;
    min-width: 200px;
    display: none;
}

.user-dropdown.active {
    display: block;
}

.user-dropdown a {
    display: block;
    padding: 12px 20px;
    color: var(--dark);
    text-decoration: none;
    transition: background 0.2s;
}

.user-dropdown a:hover {
    background: var(--light);
}
</style>

<script>
function toggleUserDropdown() {
    const dropdown = document.querySelector('.user-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu')) {
        const dropdown = document.querySelector('.user-dropdown');
        if (dropdown) {
            dropdown.classList.remove('active');
        }
    }
});
</script>