<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/community-lib.php';
ensureCommunity($pdo);
$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
function communityReply(string $message, int $status, string $location, array $extra = []): never
{
    global $json;
    if ($json) {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $status < 400, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE); exit;
    }
    $_SESSION['community_flash'] = $message;
    header('Location: ' . $location, true, 303); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Chỉ chấp nhận POST.'); }
$user = currentMember();
if (!$user) communityReply('Hãy đăng nhập để sử dụng chức năng này.',401,'/recipe/login.php');
checkCsrf();
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$id = filter_var($_POST['recipe_id'] ?? 0, FILTER_VALIDATE_INT);
$back = '/recipe/member/index.php?tab=profile';
try {
    if (in_array($action,['favorite','rate','comment','delete_comment'],true)) {
        $stmt = $pdo->prepare('SELECT id,status,owner_id FROM recipes WHERE id=?'); $stmt->execute([$id ?: 0]);
        $actionRecipe=$stmt->fetch();
        if (!$actionRecipe || ($actionRecipe['status']!=='approved' && $user['role']!=='admin' && (int)$actionRecipe['owner_id']!==(int)$user['id'])) throw new InvalidArgumentException('Công thức không còn tồn tại.');
        if ($action!=='delete_comment' && $actionRecipe['status']!=='approved') throw new InvalidArgumentException('Công thức cần được duyệt trước khi yêu thích, đánh giá hoặc bình luận.');
        $back = '/recipe/recipe.php?id=' . $id . '#community';
    }
    switch ($action) {
        case 'favorite':
            $save = ($_POST['saved'] ?? '') === '1';
            $stmt = $pdo->prepare($save ? 'INSERT IGNORE INTO favorites(user_id,recipe_id) VALUES(?,?)' : 'DELETE FROM favorites WHERE user_id=? AND recipe_id=?');
            $stmt->execute([$user['id'],$id]);
            communityReply($save ? 'Đã lưu vào yêu thích.' : 'Đã bỏ khỏi yêu thích.',200,$back,['saved'=>$save]);
        case 'rate':
            $score = filter_var($_POST['score'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>5]]);
            if ($score === false) throw new InvalidArgumentException('Hãy chọn từ 1 đến 5 sao.');
            $stmt=$pdo->prepare('INSERT INTO recipe_ratings(user_id,recipe_id,score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE score=?,updated_at=CURRENT_TIMESTAMP');
            $stmt->execute([$user['id'],$id,$score,$score]);
            communityReply('Đã lưu đánh giá của bạn.',200,$back);
        case 'comment':
            $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
            if ($body === '' || mb_strlen($body)>3000) throw new InvalidArgumentException('Bình luận cần từ 1 đến 3000 ký tự.');
            $stmt=$pdo->prepare('INSERT INTO recipe_comments(user_id,recipe_id,body) VALUES(?,?,?)');
            $stmt->execute([$user['id'],$id,$body]);
            communityReply('Đã đăng bình luận.',200,$back);
        case 'delete_comment':
            $commentId=filter_var($_POST['comment_id'] ?? 0,FILTER_VALIDATE_INT);
            $stmt=$pdo->prepare('SELECT user_id FROM recipe_comments WHERE id=? AND recipe_id=?'); $stmt->execute([$commentId ?: 0,$id]); $comment=$stmt->fetch();
            if (!$comment || ($user['role']!=='admin' && (int)$comment['user_id']!==(int)$user['id'])) {
                communityReply('Bạn không có quyền xóa bình luận này.',403,$back);
            }
            $stmt=$pdo->prepare('DELETE FROM recipe_comments WHERE id=? AND recipe_id=?'); $stmt->execute([$commentId,$id]);
            communityReply('Đã xóa bình luận.',200,$back);
        case 'profile':
            $name=is_string($_POST['display_name'] ?? null) ? trim($_POST['display_name']) : '';
            $email=is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $bio=is_string($_POST['bio'] ?? null) ? trim($_POST['bio']) : '';
            if ($name==='' || mb_strlen($name)>80 || mb_strlen($bio)>500 || strlen($email)>254 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('Kiểm tra tên hiển thị, email và giới thiệu cá nhân.');
            $password=is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $confirm=is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
            if ($password!=='') {
                $old=is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
                $stmt=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$stmt->execute([$user['id']]);
                if (!password_verify($old,$stmt->fetchColumn())) throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
                if (strlen($password)<6 || strlen($password)>72 || $password!==$confirm) throw new InvalidArgumentException('Mật khẩu mới cần từ 6 đến 72 byte và nhập lại trùng khớp.');
            }
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('INSERT INTO user_profiles(user_id,display_name,email,bio) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE display_name=?,email=?,bio=?');
            $stmt->execute([$user['id'],$name,$email,$bio,$name,$email,$bio]);
            if ($password!=='') { $stmt=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');$stmt->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]); }
            $pdo->commit();
            if ($password!=='') { session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); }
            communityReply('Đã cập nhật hồ sơ.',200,$back);
        case 'clear_history':
            $stmt=$pdo->prepare('DELETE FROM recipe_views WHERE user_id=?');$stmt->execute([$user['id']]);
            communityReply('Đã xóa lịch sử xem của bạn.',200,$back.'&tab=history');
        default: throw new InvalidArgumentException('Thao tác không hợp lệ.');
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($exception instanceof InvalidArgumentException) communityReply($exception->getMessage(),422,$back);
    error_log((string)$exception); communityReply('Chưa thể lưu thay đổi. Vui lòng thử lại.',500,$back);
}
