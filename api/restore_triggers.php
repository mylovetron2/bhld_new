<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$conn = new mysqli("localhost", "diavatly_ltd", "cntt2019", "diavatly_ltd");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_restore'])) {
    try {
        // Read the fixed triggers file
        $sqlFile = '../fix_mysql56_triggers.sql';
        
        if (!file_exists($sqlFile)) {
            throw new Exception("Trigger file not found: $sqlFile");
        }
        
        $sqlContent = file_get_contents($sqlFile);
        
        if ($sqlContent === false) {
            throw new Exception("Failed to read trigger file");
        }
        
        // Execute SQL statements
        $conn->multi_query($sqlContent);
        
        // Process all results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        // Check for errors
        if ($conn->error) {
            throw new Exception("SQL Error: " . $conn->error);
        }
        
        $message = "Triggers restored successfully from: $sqlFile";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Restore Triggers</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007acc; padding-bottom: 10px; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .btn { display: inline-block; padding: 12px 24px; margin: 5px; background: #007acc; color: white; text-decoration: none; border-radius: 4px; cursor: pointer; border: none; font-size: 16px; }
        .btn:hover { background: #005a9e; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; }
        .info-box { background: #e7f3fe; border-left: 4px solid #2196F3; padding: 20px; margin: 20px 0; }
        ul { line-height: 1.8; }
        .actions { margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Restore Triggers</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <strong>WARNING:</strong> This action will:
            <ul>
                <li>Drop all existing triggers on <code>bhld_ctu</code> and <code>bhld_ctctu</code></li>
                <li>Restore triggers from: <code>fix_mysql56_triggers.sql</code></li>
                <li>This action cannot be undone automatically</li>
            </ul>
        </div>
        
        <div class="info-box">
            <strong>Triggers to be restored:</strong>
            <ul>
                <li><strong>bhld_ctu</strong>: after_insert, after_update, before_delete</li>
                <li><strong>bhld_ctctu</strong>: after_insert, after_update, before_delete</li>
            </ul>
            <p>Source: <code>fix_mysql56_triggers.sql</code> (MySQL 5.6 compatible)</p>
        </div>
        
        <div class="actions">
            <?php if ($messageType !== 'success'): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore triggers? This will overwrite existing triggers.');">
                    <input type="hidden" name="confirm_restore" value="1">
                    <button type="submit" class="btn btn-danger">Restore Triggers Now</button>
                </form>
            <?php endif; ?>
            
            <a href="show_current_triggers.php" class="btn btn-secondary">Back to Trigger List</a>
            <a href="backup_triggers.php" class="btn" target="_blank">Download Current Backup First</a>
        </div>
        
        <p style="margin-top: 30px; color: #999; font-size: 14px;">
            MySQL Version: <?php echo $conn->server_info; ?><br>
            Generated: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
<?php $conn->close(); ?>
