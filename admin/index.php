<?php
require __DIR__ . '/auth.php';
requireAdmin();

$recipes = $pdo->query(
    'SELECT r.id, r.title, c.name AS category_name
     FROM recipes r
     JOIN categories c ON c.id = r.category_id
     ORDER BY r.id DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản trị — Bếp Nhà</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            color: #172033;
        }

        header {
            padding: 20px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .bar {
            max-width: 1000px;
            margin: auto;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            color: #ea580c;
            font-size: 24px;
            font-weight: bold;
        }

        main {
            width: min(1000px, 92%);
            margin: 32px auto;
        }

        .panel {
            padding: 24px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th { background: #fff7ed; }

        a {
            color: #ea580c;
            text-decoration: none;
        }

        button {
            padding: 10px 18px;
            background: #ea580c;
            color: white;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
        }
        .add-button {
    display: inline-block;
    padding: 12px 20px;
    background: #ea580c;
    color: white;
    border-radius: 10px;
    font-weight: bold;
}

.add-button:hover {
    background: #c2410c;
}
    </style>
</head>
<body>
    <header>
        <div class="bar">
            <a class="brand" href="../index.php">🍴 Bếp Nhà</a>

            <span>
                Xin chào, <?= e($_SESSION['username']) ?>
            </span>

            <form method="post" action="logout.php">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($_SESSION['csrf']) ?>"
                >
                <button type="submit">Đăng xuất</button>
            </form>
        </div>
    </header>

    <main>
        <h1>Quản lý công thức</h1>
        <p>Hiện có <?= count($recipes) ?> công thức.</p>
        <p>
            <a href="create.php" class="add-button">+ Thêm công thức</a>
        </p>
        <div class="panel">
            <?php if (!$recipes): ?>
                <p>Chưa có công thức nào.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Tên món</th>
                                <th scope="col">Danh mục</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recipes as $recipe): ?>
                                <tr>
                                    <td><?= (int) $recipe['id'] ?></td>
                                    <td><?= e($recipe['title']) ?></td>
                                    <td><?= e($recipe['category_name']) ?></td>
                                    <td>
                        <a href="../recipe.php?id=<?= (int) $recipe['id'] ?>">
                            Xem công thức
    </a>

    <span> · </span>

    <a href="edit.php?id=<?= (int) $recipe['id'] ?>">
        Sửa
    </a>

    <span> · </span>

    <a
        href="delete.php?id=<?= (int) $recipe['id'] ?>"
        style="color: #dc2626"
    >
        Xóa
    </a>
</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>