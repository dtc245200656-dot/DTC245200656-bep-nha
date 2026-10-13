<?php
require __DIR__ . '/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash
         FROM users
         WHERE username = ? AND role = 'admin'"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

        header('Location: ../index.php');
        exit;
    }

    $error = 'Tên tài khoản hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập quản trị — Bếp Nhà</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 60px 16px;
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            color: #172033;
        }
        main {
            max-width: 420px;
            margin: auto;
            padding: 32px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
        }
        h1 { color: #ea580c; }
        label { display: block; margin-top: 18px; }
        input {
            width: 100%;
            padding: 13px;
            margin-top: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 10px;
            background: #ea580c;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        a { color: #ea580c; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
    <main>
        <h1>🍴 Bếp Nhà</h1>
        <h2>Đăng nhập quản trị</h2>

        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf"
                   value="<?= e($_SESSION['csrf']) ?>">

            <label for="username">Tên tài khoản</label>
            <input id="username" name="username"
                   autocomplete="username" required maxlength="50"
                   value="<?= e($username ?? '') ?>">

            <label for="password">Mật khẩu</label>
            <input id="password" name="password" type="password"
                   autocomplete="current-password" required>

            <button type="submit">Đăng nhập</button>
        </form>

        <p><a href="../index.php">← Về website</a></p>
    </main>
</body>
</html>