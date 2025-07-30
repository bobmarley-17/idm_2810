<?php
require_once 'error_config.php';

error_log("Test error logging");
throw new Exception("Test exception");
?>
