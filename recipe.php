<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/community-lib.php';
ensureCommunity($pdo);
$detailMember = currentMember();
require_once __DIR__ . '/includes/guest-options.php';
ensureGuestRecipeOptions($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    exit('Không tìm thấy công thức. <a href="index.php">Về trang chủ</a>');
}

$stmt = $pdo->prepare(
    'SELECT r.*, c.name AS category_name
     FROM recipes r
     JOIN categories c ON c.id = r.category_id
     WHERE r.id = ?'
);
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe || ($recipe['status']!=='approved' && (!$detailMember || ($detailMember['role']!=='admin' && (int)$recipe['owner_id']!==(int)$detailMember['id'])))) {
    http_response_code(404);
    exit('Không tìm thấy công thức. <a href="index.php">Về trang chủ</a>');
}

$stmt = $pdo->prepare(
    'SELECT i.name, ri.quantity, ri.unit
     FROM recipe_ingredients ri
     JOIN ingredients i ON i.id = ri.ingredient_id
     WHERE ri.recipe_id = ?
     ORDER BY i.id'
);
$stmt->execute([$id]);
$ingredients = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT step_number, instruction
     FROM recipe_steps
     WHERE recipe_id = ?
     ORDER BY step_number'
);
$stmt->execute([$id]);
$steps = $stmt->fetchAll();

$image = $recipe['image_path'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($recipe['title']) ?> — Bếp Nhà</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            color: #172033;
            line-height: 1.7;
        }

        header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .container {
            width: min(1000px, 92%);
            margin: auto;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 20px 0;
        }

        a { color: #ea580c; text-decoration: none; }

        .brand { font-size: 26px; font-weight: bold; }

        main { padding: 30px 0 50px; }

        .intro {
            overflow: hidden;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
        }

        .recipe-image {
            display: block;
            width: 100%;
            max-height: 430px;
            object-fit: cover;
        }

        .intro-content { padding: 28px; }

        .category {
            display: inline-block;
            padding: 5px 14px;
            background: #fff0e5;
            color: #c2410c;
            border-radius: 20px;
            font-size: 14px;
        }

        h1 {
            margin: 14px 0;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.3;
        }

        .description { color: #64748b; }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            color: #475569;
        }

        .columns {
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 24px;
            margin-top: 24px;
            align-items: start;
        }

        .panel {
            padding: 26px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
        }

        h2 { margin: 0 0 20px; font-size: 23px; }

        .ingredients {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .ingredients li {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .ingredients strong { white-space: nowrap; }

        .steps { margin: 0; padding-left: 25px; }

        .steps li { padding: 0 0 20px 8px; }

        .steps li::marker {
            color: #ea580c;
            font-weight: bold;
        }

        footer {
            padding: 24px;
            color: #64748b;
            text-align: center;
        }

        a:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 4px;
        }

        @media (max-width: 700px) {
            .columns { grid-template-columns: 1fr; }
            .intro-content, .panel { padding: 20px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <a class="brand" href="index.php">🍴 Bếp Nhà</a>
            <div><a href="index.php">← Về trang chủ</a> · <a href="member/index.php">Tài khoản của tôi</a></div>
        </div>
    </header>

    <main class="container">
        <article>
            <div class="intro">
                <?php if ($image): ?>
                    <img
                        class="recipe-image"
                        src="<?= e($image) ?>"
                        alt="<?= e($recipe['title']) ?>"
                    >
                <?php endif; ?>

                <div class="intro-content">
                    <span class="category">
                        <?= e($recipe['category_name']) ?>
                    </span>

                    <h1><?= e($recipe['title']) ?></h1><?php if($recipe['status']!=='approved'): ?><p style="padding:12px;background:#fff0d9;border-radius:8px">Trạng thái: <?= e(recipeStatusLabels()[$recipe['status']]) ?>. Chỉ tác giả và quản trị viên được xem. <?= e($recipe['review_note']) ?></p><?php endif; ?>

                    <p class="description">
                        <?= e($recipe['description']) ?>
                    </p>

                    <div class="meta">
                        <span>
                            ⏱ <?= (int) $recipe['cooking_minutes'] ?> phút
                        </span>
                        <span>
                            🍽 <?= (int) $recipe['servings'] ?> người ăn</span><span>Độ khó: <?= e(difficultyLabels()[$recipe['difficulty'] ?? ''] ?? 'Chưa phân loại') ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="columns">
                <section class="panel">
                    <h2>🥣 Nguyên liệu</h2>

                    <?php if (!$ingredients): ?>
                        <p>Nguyên liệu đang được cập nhật.</p>
                    <?php else: ?>
                        <ul class="ingredients">
                            <?php foreach ($ingredients as $ingredient): ?>
                                <li>
                                    <span><?= e($ingredient['name']) ?></span>
                                    <strong>
                                        <?= e(rtrim(rtrim(
                                            $ingredient['quantity'], '0'
                                        ), '.')) ?>
                                        <?= e($ingredient['unit']) ?>
                                    </strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h2>👨‍🍳 Cách làm</h2>

                    <?php if (!$steps): ?>
                        <p>Cách làm đang được cập nhật.</p>
                    <?php else: ?>
                        <ol class="steps">
                            <?php foreach ($steps as $step): ?>
                                <li value="<?= (int) $step['step_number'] ?>">
                                    <?= e($step['instruction']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            </div>
        </article>
        <?php require __DIR__ . '/includes/community-detail.php'; ?>
    </main>

    <footer>
        Bếp Nhà · Công thức ngon cho mỗi ngày
    </footer>
</body>
</html>