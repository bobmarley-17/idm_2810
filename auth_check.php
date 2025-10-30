<?php
// auth_check.php - Assumes bootstrap.php has already run.

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
