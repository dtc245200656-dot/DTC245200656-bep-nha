<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/community-lib.php';
ensureCommunity($pdo);
$actingMember = requireContributor();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    exit('Không tìm thấy món. <a href="<?= e(memberDashboard($actingMember)) ?>">Về quản trị</a>');
}

$stmt = $pdo->prepare(
    'SELECT id, title FROM recipes WHERE id = ?'
);
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    exit('Món không tồn tại. <a href="<?= e(memberDashboard($actingMember)) ?>">Về quản trị</a>');
}

requireRecipeOwner((int) $id, $actingMember);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    try {
        $stmt = $pdo->prepare('DELETE FROM recipes WHERE id = ?');
        $stmt->execute([$id]);

        header('Location: ' . memberDashboard($actingMember));
        exit;
    } catch (PDOException $exception) {
        error_log((string) $exception);
        $error = 'Không thể xóa công thức. Hãy kiểm tra log PHP.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xóa công thức — Bếp Nhà</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 60px 16px;
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            color: #172033;
            line-height: 1.6;
        }

        main {
            max-width: 550px;
            margin: auto;
            padding: 30px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        button, .cancel {
            display: inline-block;
            padding: 12px 20px;
            border: 0;
            border-radius: 9px;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        button { background: #dc2626; color: white; }
        button:hover { background: #b91c1c; }
        .cancel { background: #e2e8f0; color: #172033; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
    <main>
        <h1>Xóa công thức</h1>

        <p>
            Bạn có chắc muốn xóa
            <strong><?= e($recipe['title']) ?></strong>?
        </p>

        <p>
            Công thức, danh sách nguyên liệu của món và các bước nấu
            sẽ bị xóa. File ảnh vẫn được giữ lại.
        </p>

        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >

            <div class="actions">
                <a class="cancel" href="<?= e(memberDashboard($actingMember)) ?>">Hủy, giữ lại món</a>
                <button type="submit">Xác nhận xóa</button>
            </div>
        </form>
    </main>
</body>
</html>