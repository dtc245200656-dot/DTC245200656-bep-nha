<?php
require __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/community-lib.php';
ensureModeration($pdo);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    checkCsrf();
    $id=filter_var($_POST['recipe_id']??0,FILTER_VALIDATE_INT);
    $status=is_string($_POST['status']??null)?$_POST['status']:'';
    $note=is_string($_POST['review_note']??null)?trim($_POST['review_note']):'';
    if (!$id || !in_array($status,['approved','rejected'],true) || mb_strlen($note)>500) {
        $error='Thông tin duyệt món không hợp lệ.';
    } else {
        $stmt=$pdo->prepare("UPDATE recipes SET status=?,review_note=? WHERE id=? AND status='pending'");
        $stmt->execute([$status,$status==='approved'?'':$note,$id]);
        $_SESSION['review_flash']=$stmt->rowCount() ? 'Đã cập nhật trạng thái công thức.' : 'Công thức không còn ở trạng thái chờ duyệt.';
        header('Location: review.php',true,303);exit;
    }
}
$flash=$_SESSION['review_flash']??'';unset($_SESSION['review_flash']);
$pending=$pdo->query("SELECT r.*,c.name AS category_name,u.username FROM recipes r JOIN categories c ON c.id=r.category_id LEFT JOIN users u ON u.id=r.owner_id WHERE r.status='pending' ORDER BY r.created_at,r.id")->fetchAll();
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Duyệt công thức — Bếp Nhà</title><style>body{margin:0;background:#faf8f3;font:15px/1.7 Arial,sans-serif;color:#30362e}main{width:min(1000px,92%);margin:35px auto}a{color:#40532e}h1{font:700 34px 'Times New Roman',serif}.card{background:white;border:1px solid #e1e6d7;border-radius:14px;padding:24px;margin:18px 0}.card h2{margin:0 0 8px}.small{font-size:12px;color:#747e69}.notice{background:#e6eedb;padding:14px;border-radius:8px}.error{color:#a53e2c}textarea{box-sizing:border-box;width:100%;font:inherit;padding:10px;border:1px solid #d2dcc6;border-radius:8px;margin-top:8px}button{padding:11px 20px;border:0;border-radius:25px;background:#40532e;color:white;font:inherit;cursor:pointer}.reject{background:#a45430}.row{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}</style></head><body><main><a href="index.php">← Quản trị</a><h1>Duyệt công thức thành viên</h1><p><?= count($pending) ?> công thức chờ duyệt. Xem nội dung trước khi quyết định.</p><?php if($flash):?><p class="notice" role="status"><?= e($flash) ?></p><?php endif;?><?php if($error):?><p class="error" role="alert"><?= e($error) ?></p><?php endif;?><?php if(!$pending):?><div class="card">Không có công thức chờ duyệt.</div><?php endif;?><?php foreach($pending as $item):?><article class="card"><h2><?= e($item['title']) ?></h2><p class="small">Người đăng: <?= e($item['username']??'Chưa xác định') ?> · <?= e($item['category_name']) ?></p><p><?= e($item['description']) ?></p><a href="../recipe.php?id=<?= (int)$item['id'] ?>">Xem đầy đủ công thức →</a><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="recipe_id" value="<?= (int)$item['id'] ?>"><label for="note-<?= (int)$item['id'] ?>">Góp ý khi cần chỉnh sửa (không bắt buộc)</label><textarea id="note-<?= (int)$item['id'] ?>" name="review_note" maxlength="500" rows="2"></textarea><div class="row"><button name="status" value="approved" type="submit">Duyệt và công khai</button><button class="reject" name="status" value="rejected" type="submit">Yêu cầu chỉnh sửa</button></div></form></article><?php endforeach;?></main></body></html>
