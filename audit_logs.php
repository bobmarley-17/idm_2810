<?php
// audit_logs.php
require_once 'bootstrap.php';
require_once 'auth_check.php';

$pageTitle = 'Audit Logs';

// --- Filters from GET ---
$channel   = $_GET['channel']   ?? '';
$level     = $_GET['level']     ?? '';
$search    = trim($_GET['q']    ?? '');
$dateFrom  = $_GET['date_from'] ?? '';
$dateTo    = $_GET['date_to']   ?? '';

// --- Build WHERE clause ---
$where  = [];
$params = [];

if ($channel !== '') {
    $where[] = 'channel = :channel';
    $params[':channel'] = $channel;
}

if ($level !== '') {
    $where[] = 'level = :level';
    $params[':level'] = $level;
}

if ($search !== '') {
    $where[] = '(message LIKE :search OR username LIKE :search OR context_json LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($dateFrom !== '') {
    $where[] = 'created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[] = 'created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$sql = "
    SELECT
        id,
        created_at,
        channel,
        level,
        message,
        context_json,
        username,
        ip_address,
        request_uri
    FROM audit_logs
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY created_at DESC LIMIT 500';

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Failed to load audit logs: ' . $e->getMessage());
}

// Distinct channels & levels for dropdowns
$channels = $db->query("SELECT DISTINCT channel FROM audit_logs ORDER BY channel")->fetchAll(PDO::FETCH_COLUMN);
$levels   = $db->query("SELECT DISTINCT level   FROM audit_logs ORDER BY level")->fetchAll(PDO::FETCH_COLUMN);

include 'templates/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-file-alt me-2"></i>Audit Logs</h2>
        <span class="badge bg-secondary"><?= count($logs) ?> entries (max 500)</span>
    </div>

    <!-- Filter Form -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small mb-1">Channel</label>
                    <select name="channel" class="form-select form-select-sm">
                        <option value="">All Channels</option>
                        <?php foreach ($channels as $ch): ?>
                            <option value="<?= htmlspecialchars($ch) ?>" <?= $ch === $channel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ch) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="form-label small mb-1">Level</label>
                    <select name="level" class="form-select form-select-sm">
                        <option value="">All Levels</option>
                        <?php foreach ($levels as $lv): ?>
                            <option value="<?= htmlspecialchars($lv) ?>" <?= $lv === $level ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($lv)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-4">
                    <label class="form-label small mb-1">Search</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                           class="form-control form-control-sm" placeholder="Message, user, or context...">
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="form-label small mb-1">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" 
                           class="form-control form-control-sm">
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="form-label small mb-1">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" 
                           class="form-control form-control-sm">
                </div>

                <div class="col-lg-1 col-md-4">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="audit_logs.php" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No audit log entries found for the selected filters.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive" style="max-height: calc(100vh - 280px); overflow-y: auto;">
                <table class="table table-sm table-striped table-hover table-bordered mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th style="width: 140px; white-space: nowrap;">Time</th>
                            <th style="width: 100px;">Channel</th>
                            <th style="width: 80px;">Level</th>
                            <th style="min-width: 200px;">Message</th>
                            <th style="min-width: 250px;">Context</th>
                            <th style="width: 100px;">User</th>
                            <th style="width: 120px;">IP</th>
                            <th style="min-width: 150px;">Request</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $contextArr = [];
                        if (!empty($log['context_json'])) {
                            $decoded = json_decode($log['context_json'], true);
                            if (is_array($decoded)) {
                                $contextArr = $decoded;
                            }
                        }
                        
                        // Level badge colors
                        $levelColors = [
                            'info' => 'bg-info',
                            'warning' => 'bg-warning text-dark',
                            'error' => 'bg-danger',
                            'critical' => 'bg-danger',
                            'debug' => 'bg-secondary'
                        ];
                        $levelColor = $levelColors[$log['level']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td class="text-nowrap">
                                <small><?= date('M j, H:i:s', strtotime($log['created_at'])) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-outline-secondary border text-dark">
                                    <?= htmlspecialchars($log['channel']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $levelColor ?>">
                                    <?= htmlspecialchars(strtoupper($log['level'])) ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($log['message'])) ?></td>
                            <td>
                                <?php if (!empty($contextArr)): ?>
                                    <details>
                                        <summary class="text-primary" style="cursor: pointer;">
                                            <small>View Details (<?= count($contextArr) ?> fields)</small>
                                        </summary>
                                        <pre class="mb-0 mt-1 p-2 bg-light rounded" 
                                             style="white-space: pre-wrap; word-break: break-word; font-size: 0.75rem; max-height: 200px; overflow-y: auto;"><?= htmlspecialchars(json_encode($contextArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                                    </details>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($log['username'] ?? '—') ?></small>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></small>
                            </td>
                            <td>
                                <small class="text-truncate d-inline-block" style="max-width: 200px;" 
                                       title="<?= htmlspecialchars($log['request_uri'] ?? '') ?>">
                                    <?= htmlspecialchars($log['request_uri'] ?? '—') ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-3 d-flex justify-content-between align-items-center">
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
        <small class="text-muted">
            Showing <?= count($logs) ?> most recent entries
            <?php if ($channel || $level || $search || $dateFrom || $dateTo): ?>
                (filtered)
            <?php endif; ?>
        </small>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
