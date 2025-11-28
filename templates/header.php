<?php
// templates/header.php
$pageTitle = $pageTitle ?? 'Identity Management';

// Reuse $pendingCount if already passed from index.php, otherwise try calculating it.
// Safe fallback to 0 if DB is unavailable.
$navPending = $pendingCount ?? null;

if ($navPending === null) {
    $navPending = 0;
    if (isset($db)) {
        try {
            $stmt = $db->query("
                SELECT COUNT(DISTINCT user_id)
                FROM defunct_users
                WHERE status = 'pending'
            ");
            if ($stmt !== false) {
                $navPending = (int)$stmt->fetchColumn();
            }
        } catch (Exception $e) {
            $navPending = 0;
        }
    }
}

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IDM Tool</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body { padding-top: 20px; background-color: #f8f9fa; }
        .navbar-brand { font-weight: bold; }
        .card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .nav-link.active { font-weight: bold; }
        .dropdown-menu { min-width: auto; }

        /* --- Tile Grid UI ---- */
        .tile-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
          gap: 1rem;
          align-items: stretch;
        }
        .tile {
          border-radius: 10px;
          background: #ffffff;
          color: #333;
          padding: 1.25rem;
          min-height: 110px;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          box-shadow: 0 2px 8px rgba(0,0,0,0.08);
          border: 1px solid #e9ecef;
          transition: transform .14s ease, box-shadow .14s ease;
          overflow: hidden;
        }
        .tile:hover {
          transform: translateY(-4px);
          box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .tile .icon {
          width: 48px;
          height: 48px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 22px;
        }
        .tile .metric {
          font-size: 1.25rem;
          font-weight: 700;
          color: #1a1a1a;
        }
        .tile .text-uppercase { color: #6c757d; font-weight: 600; }
        .tile .meta { font-size: 0.875rem; }
        .tile .meta a { color: #0d6efd; text-decoration: none; }
        .tile .meta a:hover { text-decoration: underline; }

        /* Icon colors */
        .tile.bg-blue .icon { background: rgba(30,144,255,0.1); color: #1E90FF; }
        .tile.bg-green .icon { background: rgba(44,182,125,0.1); color: #2CB67D; }
        .tile.bg-cyan .icon { background: rgba(37,208,208,0.1); color: #00A6C6; }
        .tile.bg-purple .icon { background: rgba(75,85,99,0.1); color: #374151; }
        .tile.bg-gray .icon { background: rgba(107,114,128,0.1); color: #6B7280; }
        .tile.bg-red .icon { background: rgba(220,38,38,0.1); color: #DC2626; }
        .tile.bg-yellow .icon { background: rgba(246,200,95,0.15); color: #D97706; }

        /* Colored left border */
        .tile.bg-blue { border-left: 4px solid #1E90FF; }
        .tile.bg-green { border-left: 4px solid #2CB67D; }
        .tile.bg-cyan { border-left: 4px solid #00A6C6; }
        .tile.bg-purple { border-left: 4px solid #374151; }
        .tile.bg-gray { border-left: 4px solid #6B7280; }
        .tile.bg-red { border-left: 4px solid #DC2626; }
        .tile.bg-yellow { border-left: 4px solid #D97706; }
    </style>
</head>

<body>
<div class="container-fluid px-4">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-id-card-alt me-2"></i>IDM Tool</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">

                <!-- TOP NAVIGATION -->
                <ul class="navbar-nav me-auto">

                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'users.php' ? 'active' : '' ?>" href="users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'sources.php' ? 'active' : '' ?>" href="sources.php">
                            <i class="fas fa-server"></i> Data Sources
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'sync.php' ? 'active' : '' ?>" href="sync.php">
                            <i class="fas fa-sync"></i> Sync
                        </a>
                    </li>

                    <!-- NEW: Reports -->
                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                            <i class="fas fa-file-alt"></i> Reports
                        </a>
                    </li>

                    <!-- NEW: Audit Logs -->
                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'audit_logs.php' ? 'active' : '' ?>" href="audit_logs.php">
                            <i class="fas fa-clipboard-list"></i> Audit Logs
                        </a>
                    </li>

                    <!-- NEW: Action Required (with badge) -->
                    <li class="nav-item">
                        <a class="nav-link <?= $current === 'pending_deletions.php' ? 'active' : '' ?>" href="pending_deletions.php">
                            <i class="fas fa-exclamation-triangle"></i> Action Required
                            <?php if ($navPending > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $navPending ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <!-- RIGHT SIDE USER DROPDOWN -->
                <div class="d-flex">
                    <?php if (isset($_SESSION['username'])): ?>
                        <div class="dropdown">
                            <a href="#" class="nav-link dropdown-toggle text-white" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </nav>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

