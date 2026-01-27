<?php
require_once 'config.php';
requireRole([ROLE_SUPER_ADMIN]);

$db = Database::getInstance();

// Filters
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

// Build query
$sql = "SELECT al.*, u.full_name, u.username, u.role
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE 1=1";

if ($action) {
    $sql .= " AND al.action = '$action'";
}

if ($userId > 0) {
    $sql .= " AND al.user_id = $userId";
}

if ($dateFrom) {
    $sql .= " AND DATE(al.created_at) >= '$dateFrom'";
}

if ($dateTo) {
    $sql .= " AND DATE(al.created_at) <= '$dateTo'";
}

$sql .= " ORDER BY al.created_at DESC LIMIT $limit";

$logs = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetch_all(MYSQLI_ASSOC);

// Get all users for filter
$users = $db->query("SELECT user_id, full_name, username FROM users ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats = [];
$stats['total_logs'] = $db->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
$stats['today_logs'] = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$stats['unique_users'] = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM activity_logs")->fetch_assoc()['count'];

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>Activity Logs</h1>
                <p>Monitor all system activities</p>
            </div>
            
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card blue">
                    <div class="stat-icon">📝</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_logs']); ?></h3>
                        <p>Total Logs</p>
                    </div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['today_logs']); ?></h3>
                        <p>Today's Activities</p>
                    </div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['unique_users']); ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="section-card">
                <form method="GET" class="form-grid">
                    <div class="form-group">
                        <label for="action">Action</label>
                        <select class="form-control" id="action" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act['action']); ?>" 
                                        <?php echo $action === $act['action'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($act['action']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">User</label>
                        <select class="form-control" id="user_id" name="user_id">
                            <option value="0">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" 
                                        <?php echo $userId == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="limit">Show Records</label>
                        <select class="form-control" id="limit" name="limit">
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                            <option value="1000" <?php echo $limit == 1000 ? 'selected' : ''; ?>>1000</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="activity_logs.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Activity Logs Table -->
            <div class="section-card">
                <h3>Activity Logs (<?php echo count($logs); ?> records)</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Entity Type</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No activity logs found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['log_id']; ?></td>
                                        <td><?php echo formatDate($log['created_at'], 'M d, Y H:i:s'); ?></td>
                                        <td>
                                            <?php if ($log['full_name']): ?>
                                                <strong><?php echo htmlspecialchars($log['full_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($log['username']); ?></small><br>
                                                <span class="badge badge-primary" style="font-size: 0.7rem;">
                                                    <?php echo ucwords(str_replace('_', ' ', $log['role'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <em>System</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['entity_type'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                        <td><code><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>