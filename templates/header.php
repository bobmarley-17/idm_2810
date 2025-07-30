<?php
// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = isset($pageTitle) ? $pageTitle : 'Identity Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IDM Tool</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom CSS -->
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .sidebar {
            position: sticky;
            top: 20px;
        }
        .nav-link.active {
            font-weight: bold;
        }
    </style>
	<!-- jQuery (required for Bootstrap 5) -->
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

	<!-- Bootstrap JS Bundle -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-id-card-alt mr-2"></i>IDM Tool
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sources.php' ? 'active' : '' ?>" href="sources.php">
                                <i class="fas fa-server"></i> Data Sources
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sync.php' ? 'active' : '' ?>" href="sync.php">
                                <i class="fas fa-sync"></i> Sync
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <span class="navbar-text me-3">
                            <i class="fas fa-user-circle"></i> Admin
                        </span>
                        <a href="logout.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
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