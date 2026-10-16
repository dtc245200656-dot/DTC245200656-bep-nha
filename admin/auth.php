<?php
require_once __DIR__ . '/../includes/auth.php';
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    requireAdmin();
    header('Location: index.php');
    exit;
}