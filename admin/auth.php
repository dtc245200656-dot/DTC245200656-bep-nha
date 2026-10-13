<?php
session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function checkCsrf(): void
{
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token)
        || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ. Hãy tải lại trang.');
    }
}

function requireAdmin(): void
{
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT id, username FROM users
         WHERE id = ? AND role = 'admin'"
    );
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['username']);
        header('Location: login.php');
        exit;
    }

    $_SESSION['username'] = $user['username'];
}