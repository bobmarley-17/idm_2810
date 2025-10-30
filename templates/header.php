<?php
// templates/header.php
$pageTitle = $pageTitle ?? 'Identity Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IDM Tool</title>

    <!-- CSS (Correctly placed in the head) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; }
        .navbar-brand { font-weight: bold; }
        .card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .nav-link.active { font-weight: bold; }
        .dropdown-menu { min-width: auto; }
    </style>
</head>
<body>
    <div class="container-fluid px-4">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php"><i class="fas fa-id-card-alt mr-2"></i>IDM Tool</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <!-- Navigation Links -->
                        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sources.php' ? 'active' : '' ?>" href="sources.php"><i class="fas fa-server"></i> Data Sources</a></li>
                        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sync.php' ? 'active' : '' ?>" href="sync.php"><i class="fas fa-sync"></i> Sync</a></li>
                    </ul>
                    <div class="d-flex">
                        <?php if (isset($_SESSION['username'])): ?>
                            <div class="dropdown">
                                <a href="#" class="nav-link dropdown-toggle text-white" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="user_detail.php?id=<?= $_SESSION['user_id'] ?>"><i class="fas fa-id-badge fa-fw me-2"></i>My Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a></li>
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
