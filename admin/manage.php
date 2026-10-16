<?php
require_once __DIR__ . '/../includes/auth.php';requireAdmin();
require_once __DIR__ . '/../includes/community-lib.php';
$admin=currentMember();
$tab=is_string($_GET['tab']??null)?$_GET['tab']:'categories';
$tabs=['categories'=>'Danh mục','users'=>'Tài khoản','comments'=>'Bình luận'];if(!isset($tabs[$tab]))$tab='categories';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    checkCsrf();$action=$_POST['action']??'';
    try{
        if($action==='category_save'){
            $name=is_string($_POST['name']??null)?trim($_POST['name']):'';$id=(int)($_POST['id']??0);
            if($name===''||mb_strlen($name)>100)throw new InvalidArgumentException('Tên danh mục cần từ 1 đến 100 ký tự.');
            $stmt=$pdo->prepare($id>0?'UPDATE categories SET name=? WHERE id=?':'INSERT INTO categories(name) VALUES(?)');$stmt->execute($id>0?[$name,$id]:[$name]);
        }elseif($action==='category_delete'){
            $stmt=$pdo->prepare('DELETE FROM categories WHERE id=? AND NOT EXISTS (SELECT 1 FROM recipes WHERE category_id=categories.id)');$stmt->execute([(int)($_POST['id']??0)]);
        }elseif($action==='role'){
            $id=(int)($_POST['id']??0);$role=$_POST['role']??'';
            if(!in_array($role,['admin','user'],true))throw new InvalidArgumentException('Vai trò không hợp lệ.');
            if($id===(int)$admin['id'])throw new InvalidArgumentException('Không đổi vai trò tài khoản đang sử dụng.');
            $pdo->beginTransaction();
            $locked=$pdo->query('SELECT id,role FROM users ORDER BY id FOR UPDATE')->fetchAll();
            $target=null;$adminCount=0;foreach($locked as $row){if((int)$row['id']===$id)$target=$row;if($row['role']==='admin')$adminCount++;}
            if(!$target)throw new InvalidArgumentException('Tài khoản không tồn tại.');
            if($target['role']==='admin'&&$role==='user'&&$adminCount<=1)throw new InvalidArgumentException('Phải giữ ít nhất một quản trị viên.');
            $stmt=$pdo->prepare('UPDATE users SET role=? WHERE id=?');$stmt->execute([$role,$id]);$pdo->commit();
        }elseif($action==='comment_delete'){
            $stmt=$pdo->prepare('DELETE FROM recipe_comments WHERE id=?');$stmt->execute([(int)($_POST['id']??0)]);
        }else throw new InvalidArgumentException('Thao tác không hợp lệ.');
        $_SESSION['manage_flash']='Đã lưu thay đổi.';header('Location: manage.php?tab='.$tab,true,303);exit;
    }catch(Throwable $exception){
        if($pdo->inTransaction())$pdo->rollBack();
        if($exception instanceof InvalidArgumentException)$error=$exception->getMessage();
        elseif($exception instanceof PDOException&&$exception->getCode()==='23000')$error='Tên danh mục bị trùng hoặc danh mục đang có công thức nên chưa thể xóa.';
        else{error_log((string)$exception);$error='Chưa thể lưu thay đổi.';}
    }
}
$flash=$_SESSION['manage_flash']??'';unset($_SESSION['manage_flash']);
if($tab==='categories')$rows=$pdo->query('SELECT c.id,c.name,COUNT(r.id) AS recipe_count FROM categories c LEFT JOIN recipes r ON r.category_id=c.id GROUP BY c.id,c.name ORDER BY c.id')->fetchAll();
elseif($tab==='users')$rows=$pdo->query('SELECT id,username,role,created_at FROM users ORDER BY id DESC')->fetchAll();
else $rows=$pdo->query('SELECT cm.id,cm.body,cm.created_at,u.username,r.title,r.id AS recipe_id FROM recipe_comments cm JOIN users u ON u.id=cm.user_id JOIN recipes r ON r.id=cm.recipe_id ORDER BY cm.id DESC LIMIT 100')->fetchAll();
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản lý <?= e($tabs[$tab]) ?> — Bếp Nhà</title><style>*{box-sizing:border-box}body{margin:0;background:#faf8f3;color:#30362e;font:14px/1.7 Arial,sans-serif}main{width:min(1050px,92%);margin:30px auto}a{color:#40532e}h1{font:700 33px 'Times New Roman',serif}nav{display:flex;gap:20px;flex-wrap:wrap;margin:22px 0}.card{padding:20px;background:white;border:1px solid #e0e5d8;border-radius:12px;margin:14px 0}.row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}input,select{padding:10px;border:1px solid #d2dbc6;border-radius:8px;font:inherit}button{border:0;border-radius:20px;padding:9px 17px;background:#40532e;color:white;font:inherit;cursor:pointer}.danger{background:#a64c32}.hint{font-size:12px;color:#747d6a}.notice{background:#e6eedb;padding:12px;border-radius:8px}.error{color:#b33d28}.body{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><main><a href="index.php">← Trang quản trị</a><h1>Quản lý <?= e($tabs[$tab]) ?></h1><nav><?php foreach($tabs as $key=>$label):?><a href="manage.php?tab=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach;?><a href="review.php">Duyệt công thức</a></nav><?php if($flash):?><p class="notice"><?= e($flash) ?></p><?php endif;?><?php if($error):?><p class="error" role="alert"><?= e($error) ?></p><?php endif;?>
<?php if($tab==='categories'):?><form class="card row" method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="category_save"><label>Danh mục mới <input name="name" maxlength="100" required></label><button type="submit">Thêm danh mục</button></form><?php endif;?>
<?php foreach($rows as $row):?><article class="card">
<?php if($tab==='categories'):?><form method="post" class="row"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="category_save"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><label>Tên danh mục <input name="name" value="<?= e($row['name']) ?>" maxlength="100" required></label><span><?= (int)$row['recipe_count'] ?> công thức</span><button type="submit">Lưu tên</button></form><?php if(!(int)$row['recipe_count']):?><form method="post" onsubmit="return confirm('Xóa danh mục trống này?')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="category_delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Xóa danh mục trống</button></form><?php endif;?>
<?php elseif($tab==='users'):?><strong><?= e($row['username']) ?></strong><p class="hint">ID <?= (int)$row['id'] ?> · Ngày tạo <?= e($row['created_at']) ?></p><?php if((int)$row['id']!==(int)$admin['id']):?><form method="post" class="row"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><label>Vai trò <select name="role"><option value="user" <?= $row['role']==='user'?'selected':'' ?>>Thành viên</option><option value="admin" <?= $row['role']==='admin'?'selected':'' ?>>Quản trị viên</option></select></label><button type="submit">Cập nhật vai trò</button></form><?php else:?><p>Tài khoản quản trị đang sử dụng.</p><?php endif;?>
<?php else:?><strong><?= e($row['username']) ?></strong> — <a href="../recipe.php?id=<?= (int)$row['recipe_id'] ?>"><?= e($row['title']) ?></a><p class="body"><?= e($row['body']) ?></p><form method="post" onsubmit="return confirm('Xóa bình luận này?')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="comment_delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="danger" type="submit">Xóa bình luận</button></form><?php endif;?>
</article><?php endforeach;?><?php if(!$rows):?><p>Chưa có dữ liệu.</p><?php endif;?></main></body></html>
