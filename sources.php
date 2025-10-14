<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add Source
        if (isset($_POST['add_source'])) {
            $type = $_POST['type'];
            $name = $_POST['name'];
            $category = $_POST['category'];
            $is_baseline = isset($_POST['is_baseline']) ? $_POST['is_baseline'] : 0;
            $description = isset($_POST['description']) ? $_POST['description'] : '';
            // Build config array based on type
            $config = [];
            switch ($type) {
                case 'CSV':
                    $config = [
                        'file_path' => $_POST['file_path'] ?? '',
                        'has_headers' => isset($_POST['has_headers']),
                        'field_mapping' => [
                            'email' => $_POST['email_field'] ?? 'email',
                            'firstname' => $_POST['firstname_field'] ?? 'firstname',
                            'username' => $_POST['username_field'] ?? 'username',
                            //'employee_id' => $_POST['employee_id_field'] ?? 'employee_id',
                            'lastname' => $_POST['lastname_field'] ?? 'lastname',
                            'supervisor_email' => $_POST['supervisor_email_field'] ?? 'supervisor_email'

                        ]
                    ];
                    break;
                case 'LDAP':
                    $config = [
                        'host' => $_POST['ldap_host'] ?? '',
                        'port' => $_POST['ldap_port'] ?? '',
                        'base_dn' => $_POST['base_dn'] ?? '',
                        'bind_dn' => $_POST['bind_dn'] ?? '',
                        'bind_password' => $_POST['bind_password'] ?? ''
                    ];
                    break;
            }
            $configJson = json_encode($config);
            // Insert new source into account_sources with config JSON
            $insertStmt = $db->prepare("INSERT INTO account_sources (name, type, category, is_baseline, description, config) VALUES (?, ?, ?, ?, ?, ?)");
            $inserted = $insertStmt->execute([$name, $type, $category, $is_baseline, $description, $configJson]);
            if ($inserted) {
                $_SESSION['message'] = "Source added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Failed to add source.";
                $_SESSION['message_type'] = "danger";
            }
            header("Location: sources.php");
            exit;
        }
        // Update Source
        elseif (isset($_POST['update_source'])) {
            $sourceId = $_POST['source_id'];
            $type = $_POST['type'];
            $name = $_POST['name'];
            $category = $_POST['category'];
            $is_baseline = isset($_POST['is_baseline']) ? $_POST['is_baseline'] : 0;
            $description = isset($_POST['description']) ? $_POST['description'] : '';
            // Build config array based on type
            $config = [];
            switch ($type) {
                case 'CSV':
                    $config = [
                        'file_path' => $_POST['file_path'] ?? '',
                        'has_headers' => isset($_POST['has_headers']),
                        'field_mapping' => [
                            'email' => $_POST['email_field'] ?? 'email',
                            'firstname' => $_POST['firstname_field'] ?? 'firstname',
                            //'username' => $_POST['username_field'] ?? 'username',
                            //'employee_id' => $_POST['employee_id_field'] ?? 'employee_id',
                            'lastname' => $_POST['lastname_field'] ?? 'lastname'
                        ]
                    ];
                    break;
                case 'LDAP':
                    $config = [
                        'host' => $_POST['ldap_host'] ?? '',
                        'port' => $_POST['ldap_port'] ?? '',
                        'base_dn' => $_POST['base_dn'] ?? '',
                        'bind_dn' => $_POST['bind_dn'] ?? '',
                        'bind_password' => $_POST['bind_password'] ?? ''
                    ];
                    break;
            }
            $configJson = json_encode($config);
            $updateStmt = $db->prepare("UPDATE account_sources SET name=?, type=?, category=?, is_baseline=?, description=?, config=? WHERE id=?");
            $updated = $updateStmt->execute([$name, $type, $category, $is_baseline, $description, $configJson, $sourceId]);
            if ($updated) {
                $_SESSION['message'] = "Source updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Failed to update source.";
                $_SESSION['message_type'] = "danger";
            }
            header("Location: sources.php?source_id=$sourceId");
            exit;
        }
        // Delete Source
        elseif (isset($_POST['delete_source'])) {
            $sourceId = $_POST['source_id'];
            $deleteStmt = $db->prepare("DELETE FROM account_sources WHERE id=?");
            $deleted = $deleteStmt->execute([$sourceId]);
            if ($deleted) {
                $_SESSION['message'] = "Source deleted successfully!";
                $_SESSION['message_type'] = "info";
                header("Location: sources.php");
            } else {
                $_SESSION['message'] = "Failed to delete source.";
                $_SESSION['message_type'] = "danger";
                header("Location: sources.php?source_id=$sourceId");
            }
            exit;
        }
        elseif (isset($_POST['add_rule'])) {
            $sourceId = $_POST['source_id'];
            $matchField = $_POST['match_field'];
            $matchType = $_POST['match_type'];
            $priority = $_POST['priority'];

            if ($correlationEngine->addRule($sourceId, $matchField, $matchType, $priority)) {
                $_SESSION['message'] = "Correlation rule added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception("Failed to add correlation rule");
            }

            header("Location: sources.php?source_id=$sourceId");
            exit;
        }
        elseif (isset($_POST['delete_rule'])) {
            $ruleId = $_POST['rule_id'];
            $sourceId = $_POST['source_id'];

            if ($correlationEngine->deleteRule($ruleId)) {
                $_SESSION['message'] = "Rule deleted successfully!";
                $_SESSION['message_type'] = "info";
            } else {
                throw new Exception("Failed to delete rule");
            }

            header("Location: sources.php?source_id=$sourceId");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
        header("Location: sources.php" . (isset($sourceId) ? "?source_id=$sourceId" : ""));
        exit;
    }
}

// Get current source ID
$currentSourceId = $_GET['source_id'] ?? null;

// Get all sources with stats
$sources = $db->query("
    SELECT s.*,
        (
            SELECT COUNT(*) FROM user_accounts ua WHERE ua.source_id = s.id
        ) + (
            SELECT COUNT(*) FROM uncorrelated_accounts uca WHERE uca.source_id = s.id AND uca.role_account_id IS NOT NULL
        ) AS account_count,
        MAX(a.updated_at) as last_account_update,
        COUNT(r.id) as rule_count
    FROM account_sources s
    LEFT JOIN user_accounts a ON a.source_id = s.id
    LEFT JOIN correlation_rules r ON r.source_id = s.id
    GROUP BY s.id
    ORDER BY s.name
")->fetchAll(PDO::FETCH_ASSOC);



// Get rules for current source
$rules = [];
if ($currentSourceId) {
    $rules = $correlationEngine->getRulesForSource($currentSourceId);
}

// Set current source for template and initialize related variables
$currentSource = null;
$accounts = [];
$inactiveCount = 0;

if ($currentSourceId) {
    foreach ($sources as $src) {
        if ($src['id'] == $currentSourceId) {
            $currentSource = $src;

            // Get active accounts
            $accountsStmt = $db->prepare("
                SELECT ua.account_id, ua.email, ua.username, ua.created_at, ua.updated_at, ua.status
                FROM user_accounts ua
                JOIN users u ON ua.user_id = u.id
                WHERE ua.source_id = ? AND u.status = 'active'
            ");
            $accountsStmt->execute([$currentSourceId]);
            $accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get count of inactive/deleted accounts
            $inactiveStmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM user_accounts ua
                JOIN users u ON ua.user_id = u.id
                WHERE ua.source_id = ? AND u.status != 'active'
            ");
            $inactiveStmt->execute([$currentSourceId]);
            $inactiveCount = (int)$inactiveStmt->fetch(PDO::FETCH_ASSOC)['count'];
            break;
        }
    }
}

include 'templates/header.php';
?>


<div class="container mt-4">
    <h2>Data Source Management</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar: Source List -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Sources</span>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSourceModal">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($sources)): ?>
                        <?php foreach ($sources as $src): ?>
                            <a href="sources.php?source_id=<?= urlencode($src['id']) ?>" class="list-group-item list-group-item-action<?= ($currentSourceId == $src['id']) ? ' active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($src['name']) ?></span>
                                    <span class="badge bg-secondary ms-2">Accounts: <?= $src['account_count'] ?></span>
                                </div>
                                <div class="small text-muted">Type: <?= htmlspecialchars($src['type']) ?> | Rules: <?= $src['rule_count'] ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-muted">No sources found.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content: Source Details or Prompt -->
        <div class="col-md-9">
            <?php if ($currentSource): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($currentSource['name']) ?> (<?= htmlspecialchars($currentSource['type']) ?>)</span>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#editSourceModal">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSourceModal">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
<!-- Edit Source Modal -->
<?php if ($currentSource): ?>
<div class="modal fade" id="editSourceModal" tabindex="-1" aria-labelledby="editSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSourceModalLabel">Edit Data Source</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="source_id" value="<?= htmlspecialchars($currentSource['id']) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Source Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentSource['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source Type</label>
                        <select name="type" class="form-control" id="editSourceType" required>
                            <option value="CSV" <?= $currentSource['type'] === 'CSV' ? 'selected' : '' ?>>CSV File</option>
                            <option value="LDAP" <?= $currentSource['type'] === 'LDAP' ? 'selected' : '' ?>>LDAP Directory</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editCategory">Category</label>
                        <select name="category" id="editCategory" class="form-select" required>
                            <option value="Application" <?= ($currentSource['category'] ?? '') === 'Application' ? 'selected' : '' ?>>Application</option>
                            <option value="OS" <?= ($currentSource['category'] ?? '') === 'OS' ? 'selected' : '' ?>>OS</option>
                            <option value="Database" <?= ($currentSource['category'] ?? '') === 'Database' ? 'selected' : '' ?>>Database</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-check-label" for="edit_is_baseline">
                            This is the baseline HR source (do not apply correlation rules)
                        </label>
                        <input type="checkbox" name="is_baseline" id="edit_is_baseline" value="1" <?= !empty($currentSource['is_baseline']) ? 'checked' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($currentSource['description'] ?? '') ?></textarea>
                    </div>
                    <?php
                    $editConfig = json_decode($currentSource['config'] ?? '{}', true);
                    ?>
                    <!-- CSV Configuration -->
                    <div id="editCsvConfig" class="source-config" style="display:<?= $currentSource['type'] === 'CSV' ? 'block' : 'none' ?>;">
                        <h6 class="mt-3">CSV Configuration</h6>
                        <div class="mb-3">
                            <label class="form-label">File Path</label>
                            <div class="input-group">
                                <input type="text" name="file_path" id="editCsvFilePath" class="form-control" value="<?= htmlspecialchars($editConfig['file_path'] ?? '') ?>">
                                <button type="button" id="editDetectHeadersBtn" class="btn btn-outline-secondary">
                                    <i class="fas fa-search"></i> Detect Headers
                                </button>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="has_headers" class="form-check-input" id="editCsvHeaders" <?= !empty($editConfig['has_headers']) ? 'checked' : '' ?> >
                            <label class="form-check-label" for="editCsvHeaders">First row contains headers</label>
                        </div>
                        <h6 class="mt-3">Field Mappings</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Email Field</label>
                                <select name="email_field" class="form-select edit-field-mapping-select">
                                    <option value="<?= htmlspecialchars($editConfig['field_mapping']['email'] ?? 'email') ?>" selected><?= htmlspecialchars($editConfig['field_mapping']['email'] ?? 'email') ?></option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">FirstName Field</label>
                                <select name="firstname_field" class="form-select edit-field-mapping-select">
                                    <option value="<?= htmlspecialchars($editConfig['field_mapping']['firstname'] ?? 'firstname') ?>" selected><?= htmlspecialchars($editConfig['field_mapping']['firstname'] ?? 'firstname') ?></option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">LastName Field</label>
                                <select name="lastname_field" class="form-select edit-field-mapping-select">
                                    <option value="<?= htmlspecialchars($editConfig['field_mapping']['lastname'] ?? 'lastname') ?>" selected><?= htmlspecialchars($editConfig['field_mapping']['lastname'] ?? 'lastname') ?></option>
                                </select>
                            </div>
                        </div>
                        <div id="editHeaderDetectionStatus" class="mt-2 text-muted small"></div>
                    </div>
                    <!-- LDAP Configuration -->
                    <div id="editLdapConfig" class="source-config" style="display:<?= $currentSource['type'] === 'LDAP' ? 'block' : 'none' ?>;">
                        <h6 class="mt-3">LDAP Configuration</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Host</label>
                                <input type="text" name="ldap_host" class="form-control" value="<?= htmlspecialchars($editConfig['host'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Port</label>
                                <input type="number" name="ldap_port" class="form-control" value="<?= htmlspecialchars($editConfig['port'] ?? '389') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Base DN</label>
                                <input type="text" name="base_dn" class="form-control" value="<?= htmlspecialchars($editConfig['base_dn'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bind DN</label>
                                <input type="text" name="bind_dn" class="form-control" value="<?= htmlspecialchars($editConfig['bind_dn'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bind Password</label>
                                <input type="password" name="bind_password" class="form-control" value="<?= htmlspecialchars($editConfig['bind_password'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_source" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Source Modal -->
<?php if ($currentSource): ?>
<div class="modal fade" id="deleteSourceModal" tabindex="-1" aria-labelledby="deleteSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteSourceModalLabel">Delete Data Source</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="source_id" value="<?= htmlspecialchars($currentSource['id']) ?>">
                <div class="modal-body">
                    <p>Are you sure you want to delete the source <strong><?= htmlspecialchars($currentSource['name']) ?></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_source" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
</script>
<script>
// Show/hide configuration in Edit Source Modal based on type and handle Detect Headers
document.addEventListener('DOMContentLoaded', function() {
    var editTypeSelect = document.getElementById('editSourceType');
    if (editTypeSelect) {
        editTypeSelect.addEventListener('change', function() {
            document.getElementById('editCsvConfig').style.display = this.value === 'CSV' ? 'block' : 'none';
            document.getElementById('editLdapConfig').style.display = this.value === 'LDAP' ? 'block' : 'none';
        });
    }
    // CSV header detection for Edit Source Modal
    var editDetectHeadersBtn = document.getElementById('editDetectHeadersBtn');
    if (editDetectHeadersBtn) {
        editDetectHeadersBtn.addEventListener('click', async function() {
            const filePath = document.getElementById('editCsvFilePath').value;
            const statusElement = document.getElementById('editHeaderDetectionStatus');
            const hasHeaders = document.getElementById('editCsvHeaders').checked;
            if (!filePath) {
                statusElement.textContent = 'Please enter a file path first';
                statusElement.className = 'mt-2 text-danger small';
                return;
            }
            statusElement.textContent = 'Detecting headers...';
            statusElement.className = 'mt-2 text-info small';
            try {
                const formData = new FormData();
                formData.append('file_path', filePath);
                formData.append('has_headers', hasHeaders ? '1' : '0');
                const response = await fetch('get_csv_headers.php', {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Unknown error occurred');
                }
                // Update all field mapping dropdowns in edit modal
                document.querySelectorAll('.edit-field-mapping-select').forEach(select => {
                    const currentValue = select.value;
                    select.innerHTML = '';
                    data.headers.forEach(header => {
                        const option = document.createElement('option');
                        option.value = header;
                        option.textContent = header;
                        option.selected = (header === currentValue);
                        select.appendChild(option);
                    });
                    // Keep current value if not in detected headers
                    if (currentValue && !data.headers.includes(currentValue)) {
                        const option = document.createElement('option');
                        option.value = currentValue;
                        option.textContent = currentValue;
                        option.selected = true;
                        select.insertBefore(option, select.firstChild);
                    }
                });
                statusElement.textContent = `Detected ${data.headers.length} headers`;
                statusElement.className = 'mt-2 text-success small';
            } catch (error) {
                statusElement.textContent = `Error: ${error.message}`;
                statusElement.className = 'mt-2 text-danger small';
                console.error('Header detection error:', error);
            }
        });
    }
});
</script>
                    <div class="card-body">
                        <!-- Correlation Rules Section -->
                        <h5 class="mt-4">Correlation Rules</h5>
                        <div class="mb-3">
                            <form method="POST" class="row g-2 align-items-end">
                                <input type="hidden" name="source_id" value="<?= htmlspecialchars($currentSource['id']) ?>">
                                <div class="col-md-3">
                                    <label class="form-label">Match Field</label>
                                    <input type="text" name="match_field" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Match Type</label>
                                    <select name="match_type" class="form-select" required>
                                        <option value="exact">Exact</option>
                                        <option value="contains">Contains</option>
                                        <option value="startswith">Starts With</option>
                                        <option value="endswith">Ends With</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Priority</label>
                                    <input type="number" name="priority" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="add_rule" class="btn btn-primary">Add Rule</button>
                                </div>
                            </form>
                        </div>
                        <?php if (!empty($rules)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover table-sm mt-2" style="font-size: 0.95rem;">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Type</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rule['match_field']) ?></td>
                                        <td><?= htmlspecialchars($rule['match_type']) ?></td>
                                        <td><?= htmlspecialchars($rule['priority']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="rule_id" value="<?= htmlspecialchars($rule['id']) ?>">
                                                <input type="hidden" name="source_id" value="<?= htmlspecialchars($currentSource['id']) ?>">
                                                <button type="submit" name="delete_rule" class="btn btn-sm btn-danger" onclick="return confirm('Delete this rule?')"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-info">No correlation rules defined for this source.</div>
                        <?php endif; ?>
                        <p><strong>Category:</strong> <?= htmlspecialchars($currentSource['category']) ?></p>
                        <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($currentSource['description'] ?? '')) ?></p>
                        <p>
                            <strong>Active Accounts:</strong> <?= count($accounts) ?>
                            <?php if ($inactiveCount > 0): ?>
                            <a href="inactive_users.php?source_id=<?= $currentSource['id'] ?>" class="btn btn-sm btn-secondary ms-2">
                                View <?= $inactiveCount ?> Inactive/Deleted Accounts
                            </a>
                            <?php endif; ?>
                        </p>
                        <p><strong>Last Account Update:</strong> <?= $currentSource['last_account_update'] ?></p>
                        <p><strong>Correlation Rules:</strong> <?= $currentSource['rule_count'] ?></p>

                        <?php
                        // Define columns to display and their user-friendly headers

                        $displayColumns = [
                            'account_id' => 'Account ID',
                            'email' => 'Email Address',
                            'username' => 'Username',
                            'created_at' => 'Created',
                            'updated_at' => 'Last Updated',
                        ];

                        // Display accounts in table format
                        if (!empty($allAccounts)) {
                            echo '<h5 class="mt-4">Accounts</h5>';
                            echo '<div class="table-responsive"><table class="table table-bordered table-hover table-sm" style="font-size: 0.92rem;">';
                            echo '<thead><tr>';
                            foreach ($displayColumns as $col => $header) {
                                echo '<th>' . htmlspecialchars($header) . '</th>';
                            }
                            echo '</tr></thead><tbody>';
                        }  // end if (!empty($allAccounts))

                        // Fetch correlated accounts (role accounts)

                        // Fetch correlated accounts by joining uncorrelated_accounts with user_accounts (role accounts)

                        // Note: uncorrelated_accounts may not have updated_at column
                        $correlatedStmt = $db->prepare("
                            SELECT account_id, email, username, created_at, role_account_id
                            FROM uncorrelated_accounts
                            WHERE source_id = ? AND role_account_id IS NOT NULL
                        ");
                        $correlatedStmt->execute([$currentSource['id']]);
                        $correlatedAccounts = $correlatedStmt->fetchAll(PDO::FETCH_ASSOC);


                        // For correlated accounts, use role_account's username as username
                        foreach ($correlatedAccounts as &$ca) {
                            if (!isset($ca['updated_at'])) {
                                $ca['updated_at'] = '';
                            }
                            // If role_account_id is set, fetch name from role_accounts and use as username
                            if (!empty($ca['role_account_id'])) {
                                $roleStmt = $db->prepare("SELECT name FROM role_accounts WHERE id = ? LIMIT 1");
                                $roleStmt->execute([$ca['role_account_id']]);
                                $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
                                if ($role && !empty($role['name'])) {
                                    $ca['username'] = $role['name'];
                                }
                            }
                        }
                        unset($ca);


                        // If SSHRData source exists, build a lookup of email => username from it
                        $sshrUsernames = [];
                        foreach ($sources as $src) {
                            if (strtolower($src['name']) === 'sshrdata') {
                                $sshrStmt = $db->prepare("SELECT email, username FROM user_accounts WHERE source_id = ?");
                                $sshrStmt->execute([$src['id']]);
                                foreach ($sshrStmt->fetchAll(PDO::FETCH_ASSOC) as $sshrRow) {
                                    if (!empty($sshrRow['email']) && !empty($sshrRow['username'])) {
                                        $sshrUsernames[strtolower($sshrRow['email'])] = $sshrRow['username'];
                                    }
                                }
                                break;
                            }
                        }

                        // For all accounts, if username is missing, fill from SSHRData by email
                        $allAccounts = array_merge($accounts, $correlatedAccounts);
                        if (!empty($sshrUsernames)) {
                            foreach ($allAccounts as &$acc) {
                                if (empty($acc['username']) && !empty($acc['email'])) {
                                    $emailKey = strtolower($acc['email']);
                                    if (isset($sshrUsernames[$emailKey])) {
                                        $acc['username'] = $sshrUsernames[$emailKey];
                                    }
                                }
                            }
                            unset($acc);
                        }

                        if (!empty($allAccounts)) {
                            echo '<h5 class="mt-4">Accounts</h5>';
                            echo '<div class="table-responsive"><table class="table table-bordered table-hover table-sm" style="font-size: 0.92rem;">';
                            echo '<thead><tr>';
                            foreach ($displayColumns as $col => $header) {
                                echo '<th>' . htmlspecialchars($header) . '</th>';
                            }
                            echo '</tr></thead><tbody>';
                            foreach ($allAccounts as $row) {
                                echo '<tr>';
                                foreach ($displayColumns as $col => $header) {
                                    echo '<td>' . htmlspecialchars($row[$col] ?? '') . '</td>';
                                }
                                echo '</tr>';
                            }
                            echo '</tbody></table></div>';
                        } else {
                            echo '<div class="alert alert-info mt-4">No accounts found for this source.</div>';
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-server fa-4x text-muted mb-3"></i>
                        <h5>Select a data source</h5>
                        <p class="text-muted">Choose a source from the list to view or manage correlation rules</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Source Modal -->
<div class="modal fade" id="addSourceModal" tabindex="-1" aria-labelledby="addSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSourceModalLabel">Add New Data Source</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Source Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Source Type</label>
                        <select name="type" class="form-control" id="sourceType" required>
                            <option value="">-- Select Type --</option>
                            <option value="CSV">CSV File</option>
                            <option value="LDAP">LDAP Directory</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="category">Category</label>
                        <select name="category" id="category" class="form-select" required>
                            <option value="Application" <?= ($category ?? '') === 'Application' ? 'selected' : '' ?>>Application</option>
                            <option value="OS" <?= ($category ?? '') === 'OS' ? 'selected' : '' ?>>OS</option>
                            <option value="Database" <?= ($category ?? '') === 'Database' ? 'selected' : '' ?>>Database</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-check-label" for="is_baseline">
                            This is the baseline HR source (do not apply correlation rules)
                        </label>
                        <input type="checkbox" name="is_baseline" id="is_baseline" value="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- CSV Configuration -->
                    <div id="csvConfig" class="source-config" style="display:none;">
                        <h6 class="mt-3">CSV Configuration</h6>
                        <div class="mb-3">
                            <label class="form-label">File Path</label>
                            <div class="input-group">
                                <input type="text" name="file_path" id="csvFilePath" class="form-control" placeholder="C:\\path\\to\\file.csv" required>
                                <button type="button" id="detectHeadersBtn" class="btn btn-outline-secondary">
                                    <i class="fas fa-search"></i> Detect Headers
                                </button>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="has_headers" class="form-check-input" checked id="csvHeaders">
                            <label class="form-check-label" for="csvHeaders">First row contains headers</label>
                        </div>

                        <h6 class="mt-3">Field Mappings</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Email Field</label>
                                <select name="email_field" class="form-select field-mapping-select">
                                    <option value="email">email</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">FirstName Field</label>
                                <select name="firstname_field" class="form-select field-mapping-select">
                                    <option value="firstname">firstname</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">LastName Field</label>
                                <select name="lastname_field" class="form-select field-mapping-select">
                                    <option value="lastname">lastname</option>
                                </select>
                            </div>
                        </div>
                        <div id="headerDetectionStatus" class="mt-2 text-muted small"></div>
                    </div>
                    <!-- LDAP Configuration -->
                    <div id="ldapConfig" class="source-config" style="display:none;">
                        <h6 class="mt-3">LDAP Configuration</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Host</label>
                                <input type="text" name="ldap_host" class="form-control" placeholder="ldap.example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Port</label>
                                <input type="number" name="ldap_port" class="form-control" value="389">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Base DN</label>
                                <input type="text" name="base_dn" class="form-control" placeholder="ou=users,dc=example,dc=com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bind DN</label>
                                <input type="text" name="bind_dn" class="form-control" placeholder="cn=admin,dc=example,dc=com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bind Password</label>
                                <input type="password" name="bind_password" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_source" class="btn btn-primary">Add Source</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide configuration based on source type
document.getElementById('sourceType').addEventListener('change', function() {
    document.querySelectorAll('.source-config').forEach(el => {
        el.style.display = 'none';
    });

    const configEl = document.getElementById(this.value.toLowerCase() + 'Config');
    if (configEl) {
        configEl.style.display = 'block';
    }
});

// Initialize tabs
var tabElms = document.querySelectorAll('#sourceTabs button[data-bs-toggle="tab"]');
tabElms.forEach(function(tabEl) {
    tabEl.addEventListener('click', function (event) {
        event.preventDefault();
        new bootstrap.Tab(this).show();
    });
});

// CSV header detection from file path
document.getElementById('detectHeadersBtn').addEventListener('click', async function() {
    const filePath = document.getElementById('csvFilePath').value;
    const statusElement = document.getElementById('headerDetectionStatus');

    if (!filePath) {
        statusElement.textContent = 'Please enter a file path first';
        statusElement.className = 'mt-2 text-danger small';
        return;
    }

    statusElement.textContent = 'Detecting headers...';
    statusElement.className = 'mt-2 text-info small';

    try {
        const formData = new FormData();
        formData.append('file_path', filePath);
        formData.append('has_headers', document.getElementById('csvHeaders').checked ? '1' : '0');

        const response = await fetch('get_csv_headers.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Unknown error occurred');
        }

        // Update all field mapping dropdowns
        document.querySelectorAll('.field-mapping-select').forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '';

            // Add detected headers
            data.headers.forEach(header => {
                const option = document.createElement('option');
                option.value = header;
                option.textContent = header;
                option.selected = (header === currentValue);
                select.appendChild(option);
            });

            // Keep current value if not in detected headers
            if (currentValue && !data.headers.includes(currentValue)) {
                const option = document.createElement('option');
                option.value = currentValue;
                option.textContent = currentValue;
                option.selected = true;
                select.insertBefore(option, select.firstChild);
            }
        });

        statusElement.textContent = `Detected ${data.headers.length} headers`;
        statusElement.className = 'mt-2 text-success small';

    } catch (error) {
        statusElement.textContent = `Error: ${error.message}`;
        statusElement.className = 'mt-2 text-danger small';
        console.error('Header detection error:', error);
    }
});
</script>

<?php include 'templates/footer.php'; ?>

