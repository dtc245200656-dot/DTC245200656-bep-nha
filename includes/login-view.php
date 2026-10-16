<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/auth.php';
$mode = $accountMode ?? 'login';
$isRegister = $mode === 'register';
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
    $now = time();
    if ($now - (int) ($_SESSION['member_attempt_start'] ?? 0) > 600) {
        $_SESSION['member_attempt_start'] = $now;
        $_SESSION['member_attempts'] = 0;
    }
    if ((int) ($_SESSION['member_attempts'] ?? 0) >= 10) {
        $error = 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau 10 phút.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.@-]{3,50}$/D', $username)) {
        $error = 'Tên tài khoản cần 3–50 ký tự: chữ không dấu, số hoặc các dấu _ . @ -';
    } elseif (strlen($password) > 72 || ($isRegister && strlen($password) < 6)) {
        $error = 'Mật khẩu cần từ 6 đến 72 byte; có thể dùng chữ không dấu, số và ký hiệu.';
    } elseif ($isRegister && $password !== $confirm) {
        $error = 'Hai mật khẩu chưa giống nhau.';
    } else {
        $_SESSION['member_attempts'] = (int) ($_SESSION['member_attempts'] ?? 0) + 1;
        try {
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($isRegister) {
                if ($user) {
                    $error = 'Tên tài khoản này đã được sử dụng. Hãy chọn tên khác.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                    $user = ['id' => (int) $pdo->lastInsertId(), 'username' => $username];
                }
            } elseif (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Tên tài khoản hoặc mật khẩu không đúng.';
            }
            if ($error === '' && $user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                unset($_SESSION['member_attempts'], $_SESSION['member_attempt_start']);
                header('Location: /recipe/index.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && $isRegister) {
                $error = 'Tên tài khoản này đã được sử dụng.';
            } else {
                error_log((string) $exception);
                $error = 'Chưa thể xử lý tài khoản. Vui lòng thử lại sau.';
            }
        }
    }
}
$pageTitle = $isRegister ? 'Đăng ký tài khoản' : 'Đăng nhập';
?>
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($pageTitle) ?> — Bếp Nhà</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f7f5ee;color:#30362e;font:15px/1.6 Arial,sans-serif;min-height:100vh;padding:40px 18px}main{max-width:440px;margin:auto;background:#fffefa;border:1px solid #e5e5d9;border-radius:20px;padding:34px;box-shadow:0 15px 50px #34412a08}a{color:#4a5c34;text-decoration:none}.brand{font:bold 30px 'Times New Roman',serif}.brand span{color:#b95830}h1{font-size:25px;margin:25px 0 6px}.intro{font-size:13px;color:#757b6d;margin-top:0}label{display:block;font-weight:bold;font-size:13px;margin-top:20px}input{display:block;width:100%;padding:12px;margin-top:7px;border:1px solid #d8ddce;border-radius:9px;font:inherit;background:white}input:focus{outline:2px solid #75875c;outline-offset:2px}button{width:100%;padding:13px;margin-top:25px;background:#40532e;color:white;border:0;border-radius:30px;font:inherit;cursor:pointer}.error{background:#ffede6;color:#9d3a22;padding:12px;border-radius:8px;font-size:13px}.switch{text-align:center;font-size:13px}.back{display:block;margin-top:24px;font-size:12px;color:#777e6c}.hint{font-size:11px;color:#777e6c;margin-top:5px}
</style></head><body><main><a class="brand" href="/recipe/index.php">⌂ Bếp <span>Nhà</span></a><h1><?= e($pageTitle) ?></h1><p class="intro"><?= $isRegister ? 'Tạo tài khoản để bắt đầu hành trình vào bếp cùng Bếp Nhà.' : 'Chào bạn trở lại với những món ngon của Bếp Nhà.' ?></p>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><label for="username">Tên tài khoản</label><input name="username" id="username" autocomplete="username" minlength="3" maxlength="50" required value="<?= e($username) ?>"><p class="hint">Có thể dùng email hoặc tên viết liền không dấu.</p><label for="password">Mật khẩu</label><input type="password" name="password" id="password" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" <?= $isRegister ? 'minlength="6"' : '' ?> required>
<?php if ($isRegister): ?><p class="hint">Từ 6 ký tự trở lên nếu dùng chữ không dấu.</p><label for="confirm">Nhập lại mật khẩu</label><input type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="6" required><?php endif; ?>
<button type="submit"><?= $isRegister ? 'Tạo tài khoản' : 'Đăng nhập' ?></button></form>
<p class="switch"><?= $isRegister ? 'Đã có tài khoản?' : 'Chưa có tài khoản?' ?> <a href="<?= $isRegister ? '/recipe/login.php' : '/recipe/register.php' ?>"><strong><?= $isRegister ? 'Đăng nhập' : 'Đăng ký ngay' ?></strong></a></p><a class="back" href="/recipe/index.php">← Về trang chủ Bếp Nhà</a></main></body></html>
