<?php
require_once 'bootstrap.php';
require_once 'auth_check.php';  // ✅ Require authentication
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/AuditLogger.php';

// Initialize database connection
$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if user has permission to sync (optional - add role check if needed)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Log the sync all action
AuditLogger::getLogger('sync')->info('Sync all sources initiated', [
    'user' => $_SESSION['username'] ?? 'unknown',
    'user_id' => $_SESSION['user_id'] ?? null
]);

include 'templates/header.php';
?>

<div class="container mt-4">
    <h2>Sync All Sources</h2>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        Synchronizing all configured sources. This may take several minutes...
    </div>

    <div class="card">
        <div class="card-body">
            <div class="progress mb-3" style="height: 25px;">
                <div id="sync-progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%">0%</div>
            </div>
            
            <ul id="sync-results" class="list-group">
                <?php
                // Get all sources
                $stmt = $db->query("SELECT id, name, type FROM account_sources ORDER BY id");
                $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($sources)) {
                    echo '<li class="list-group-item">No sources configured</li>';
                } else {
                    $totalSources = count($sources);
                    $successCount = 0;
                    $failCount = 0;
                    
                    foreach ($sources as $index => $src) {
                        $sourceId = intval($src['id']);
                        $sourceName = htmlspecialchars($src['name']);
                        $sourceType = htmlspecialchars($src['type']);
                        
                        echo "<li class='list-group-item' id='source-{$sourceId}'>";
                        echo "<div class='d-flex justify-content-between align-items-center'>";
                        echo "<span><strong>{$sourceName}</strong> <small class='text-muted'>({$sourceType})</small></span>";
                        echo "<span class='badge badge-secondary'>Processing...</span>";
                        echo "</div>";
                        
                        // Flush output to browser
                        ob_flush();
                        flush();
                        
                        // Execute sync with proper escaping
                        $command = "python3 run_sync.py --source " . escapeshellarg($sourceId);
                        $output = shell_exec($command . " 2>&1");
                        
                        // Determine success/failure
                        $success = false;
                        $exitCode = 0;
                        
                        // Better success detection
                        if ($output !== null) {
                            // Check for success indicators
                            if (preg_match('/completed|success|✓/i', $output) && 
                                !preg_match('/failed|error|exception/i', $output)) {
                                $success = true;
                                $successCount++;
                            } else {
                                $failCount++;
                            }
                        } else {
                            $failCount++;
                        }
                        
                        // Update the list item with result
                        if ($success) {
                            echo "<script>
                                document.getElementById('source-{$sourceId}').querySelector('.badge').className = 'badge badge-success';
                                document.getElementById('source-{$sourceId}').querySelector('.badge').textContent = 'Success';
                            </script>";
                            
                            // Log success
                            AuditLogger::getLogger('sync')->info('Source sync completed', [
                                'source_id' => $sourceId,
                                'source_name' => $src['name'],
                                'status' => 'success'
                            ]);
                        } else {
                            echo "<script>
                                document.getElementById('source-{$sourceId}').querySelector('.badge').className = 'badge badge-danger';
                                document.getElementById('source-{$sourceId}').querySelector('.badge').textContent = 'Failed';
                            </script>";
                            
                            // Show error output
                            $errorOutput = htmlspecialchars(substr($output ?? 'No output received', 0, 500));
                            echo "<div class='mt-2'><small class='text-danger'><pre style='font-size: 11px; max-height: 150px; overflow: auto;'>{$errorOutput}</pre></small></div>";
                            
                            // Log failure
                            AuditLogger::getLogger('sync')->error('Source sync failed', [
                                'source_id' => $sourceId,
                                'source_name' => $src['name'],
                                'status' => 'failed',
                                'output' => substr($output ?? '', 0, 1000)
                            ]);
                        }
                        
                        echo "</li>";
                        
                        // Update progress bar
                        $progress = round((($index + 1) / $totalSources) * 100);
                        echo "<script>
                            var bar = document.getElementById('sync-progress');
                            bar.style.width = '{$progress}%';
                            bar.textContent = '{$progress}%';
                        </script>";
                        
                        // Flush output
                        ob_flush();
                        flush();
                        
                        // Small delay to prevent overwhelming the system
                        usleep(500000); // 0.5 seconds
                    }
                    
                    // Final summary
                    echo "</ul>";
                    echo "<div class='alert alert-" . ($failCount > 0 ? 'warning' : 'success') . " mt-3'>";
                    echo "<h5>Sync Summary</h5>";
                    echo "<p><strong>Total Sources:</strong> {$totalSources}<br>";
                    echo "<strong>Successful:</strong> <span class='text-success'>{$successCount}</span><br>";
                    echo "<strong>Failed:</strong> <span class='text-danger'>{$failCount}</span></p>";
                    echo "</div>";
                    
                    // Complete progress bar
                    echo "<script>
                        var bar = document.getElementById('sync-progress');
                        bar.classList.remove('progress-bar-animated');
                        bar.classList.add('bg-success');
                        bar.style.width = '100%';
                        bar.textContent = 'Complete';
                    </script>";
                    
                    // Log final summary
                    AuditLogger::getLogger('sync')->info('Sync all completed', [
                        'total' => $totalSources,
                        'success' => $successCount,
                        'failed' => $failCount,
                        'user' => $_SESSION['username'] ?? 'unknown'
                    ]);
                }
                ?>
            </ul>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="sync.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Sync
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
