<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);

// Handle sync request
$syncResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_source'])) {
    $sourceId = $_POST['source_id'];
    
    // Execute Python sync script
    $command = escapeshellcmd("python3 run_sync.py --source $sourceId");
    $output = shell_exec($command . " 2>&1");
    
    $syncResult = [
        'source_id' => $sourceId,
        'output' => $output
    ];
    
    // Update last sync time in UI
    $stmt = $db->prepare("
        UPDATE account_sources 
        SET last_sync = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$sourceId]);
}

// Get all sources
$sources = $db->query("SELECT * FROM account_sources ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="container mt-4">
    <h2>Manual Synchronization</h2>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Sync Data Source
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Source</label>
                            <select name="source_id" class="form-control" required>
                                <option value="">-- Select Source --</option>
                                <?php foreach ($sources as $source): ?>
                                <option value="<?= $source['id'] ?>">
                                    <?= htmlspecialchars($source['name']) ?> (<?= $source['type'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="sync_source" class="btn btn-primary">
                            <i class="fas fa-sync mr-2"></i> Sync Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Sync All Sources
                </div>
                <div class="card-body">
                    <p>Run synchronization for all sources that are due for update.</p>
                    <form method="POST" action="sync_all.php">
                        <button type="submit" name="sync_all" class="btn btn-info">
                            <i class="fas fa-sync-alt mr-2"></i> Sync All
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($syncResult): ?>
    <div class="card mt-4">
        <div class="card-header">
            Sync Results for Source #<?= $syncResult['source_id'] ?>
        </div>
        <div class="card-body">
            <pre style="max-height: 300px; overflow: auto;"><?= htmlspecialchars($syncResult['output']) ?></pre>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card mt-4">
        <div class="card-header">
            Sync Status
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Type</th>
                        <th>Last Sync</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): 
                        $syncStatus = 'Never run';
                        $statusClass = 'text-muted';
                        
                        if ($source['last_sync']) {
                            $syncStatus = date('M j, Y H:i', strtotime($source['last_sync']));
                            $statusClass = 'text-success';
                            
                            // If sync is older than 1 day
                            if (time() - strtotime($source['last_sync']) > 86400) {
                                $statusClass = 'text-warning';
                            }
                            
                            // If sync is older than 1 week
                            if (time() - strtotime($source['last_sync']) > 604800) {
                                $statusClass = 'text-danger';
                            }
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($source['name']) ?></td>
                        <td><?= htmlspecialchars($source['type']) ?></td>
                        <td><?= $syncStatus ?></td>
                        <td class="<?= $statusClass ?>">
                            <?php if ($source['last_sync']): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>