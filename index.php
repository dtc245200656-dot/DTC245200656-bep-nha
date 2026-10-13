<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/admin/auth.php';

// Kiểm tra tài khoản đang đăng nhập có quyền quản trị.
$stmt = $pdo->prepare(
    "SELECT id, username FROM users
     WHERE id = ? AND role = 'admin'"
);
$stmt->execute([$_SESSION['user_id'] ?? 0]);
$currentAdmin = $stmt->fetch();

$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = max(0, (int) ($_GET['category'] ?? 0));

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY id'
)->fetchAll();

$sql = 'SELECT r.*, c.name AS category_name
        FROM recipes r
        JOIN categories c ON c.id = r.category_id
        WHERE 1 = 1';

$params = [];

if ($q !== '') {
    $sql .= ' AND r.title LIKE ?';
    $params[] = '%' . $q . '%';
}

if ($categoryId > 0) {
    $sql .= ' AND r.category_id = ?';
    $params[] = $categoryId;
}

$sql .= ' ORDER BY r.created_at DESC, r.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

$featured = $pdo->query(
    "SELECT r.*, c.name AS category_name
     FROM recipes r
     JOIN categories c ON c.id = r.category_id
     ORDER BY CASE
         WHEN r.title = 'Gà chiên mắm tỏi' THEN 0
         ELSE 1
     END, r.id DESC
     LIMIT 1"
)->fetch();

function categoryUrl(int $id, string $q): string
{
    return 'index.php?' . http_build_query([
        'category' => $id,
        'q' => $q,
    ]);
}

function recipeImage(array $recipe): string
{
    return $recipe['image_path'] ?: 'images/ga-chien.png';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BếpNhà — Công thức nấu ăn</title>

    <style>
        :root {
            --orange: #f97316;
            --text: #172033;
            --muted: #64748b;
            --background: #f7f9fc;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text);
            background: var(--background);
            line-height: 1.6;
        }

        a { text-decoration: none; color: inherit; }

        .container {
            width: min(1100px, 92%);
            margin: auto;
        }

        .site-header {
            background: white;
            border-bottom: 1px solid #e8edf3;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 22px 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ea580c;
            font-size: 25px;
            font-weight: bold;
            white-space: nowrap;
        }

        .brand-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            background: var(--orange);
            border-radius: 12px;
            font-size: 24px;
        }

        .search {
            display: flex;
            flex: 1;
            gap: 8px;
        }

        .search input {
            width: 100%;
            min-width: 0;
            padding: 13px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            background: #f1f5f9;
            font: inherit;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border: 0;
            border-radius: 30px;
            background: var(--orange);
            color: white;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .button:hover { background: #ea580c; }

        .categories {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 0 0 18px;
        }

        .category {
            padding: 8px 18px;
            border-radius: 24px;
            background: #f1f5f9;
            color: #475569;
            font-size: 14px;
            white-space: nowrap;
        }

        .category.active {
            background: var(--orange);
            color: white;
        }

        .hero {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 360px;
            margin: 28px 0 36px;
            overflow: hidden;
            border-radius: 24px;
            background: #263345;
            color: white;
            box-shadow: 0 12px 24px #17203318;
        }

        .hero-image,
        .hero-shade {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }

        .hero-image { object-fit: cover; }

        .hero-shade {
            background: linear-gradient(
                90deg,
                rgba(10, 17, 28, .9),
                rgba(10, 17, 28, .35)
            );
        }

        .hero-content {
            position: relative;
            max-width: 700px;
            padding: 44px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border: 1px solid #fb923c;
            border-radius: 20px;
            background: #f9731626;
            font-size: 12px;
            font-weight: bold;
        }

        .hero h1 {
            margin: 14px 0;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.15;
        }

        .hero p { color: #e2e8f0; }

        .hero-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 24px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 24px;
        }

        .count {
            padding: 2px 10px;
            border-radius: 20px;
            background: #e2e8f0;
            color: var(--muted);
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .card {
            overflow: hidden;
            background: white;
            border: 1px solid #e8edf3;
            border-radius: 18px;
            box-shadow: 0 4px 16px #17203306;
        }

        .card-picture {
            position: relative;
            height: 210px;
            background: #ffedd5;
        }

        .card-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-category {
            position: absolute;
            top: 14px;
            left: 14px;
            padding: 4px 12px;
            background: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .card-content { padding: 20px; }

        .card h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .description {
            color: var(--muted);
            font-size: 14px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
            color: var(--muted);
            font-size: 13px;
        }

        .empty {
            padding: 32px;
            border-radius: 16px;
            background: white;
            text-align: center;
        }

        footer {
            margin-top: 60px;
            padding: 28px 16px;
            border-top: 1px solid #e2e8f0;
            color: var(--muted);
            text-align: center;
            font-size: 14px;
        }

        :focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }

        @media (max-width: 900px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 600px) {
            .topbar { flex-wrap: wrap; gap: 16px; }
            .search { flex-basis: 100%; }
            .hero-content { padding: 28px; }
            .hero { min-height: 330px; }
            .grid { grid-template-columns: 1fr; }
        }
        .topbar {
    flex-wrap: wrap;
    gap: 16px;
}

.search {
    flex: 1 1 280px;
}

.account-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.account-actions form {
    margin: 0;
}

.account-link {
    color: #ea580c;
    font-weight: bold;
    white-space: nowrap;
}

.button-green {
    background: #22c55e;
}

.button-green:hover {
    background: #16a34a;
}

@media (max-width: 600px) {
    .account-actions {
        width: 100%;
    }
}
    </style>
</head>

<body>
    <header class="site-header">
        <div class="container">
            <div class="topbar">
                <a class="brand" href="index.php">
                    <span class="brand-icon">🍴</span>
                    BếpNhà<span style="color:#22c55e">.</span>
                </a>

                <form class="search" method="get" action="index.php">
                    <input
                        type="search"
                        name="q"
                        value="<?= e($q) ?>"
                        placeholder="Tìm món ăn..."
                        aria-label="Tìm món ăn"
                    >

                    <input
                        type="hidden"
                        name="category"
                        value="<?= $categoryId ?>"
                    >

                    <button class="button" type="submit">
                        Tìm kiếm
                    </button>
                </form>
                <div class="account-actions">
    <?php if ($currentAdmin): ?>
        <a class="button button-green" href="admin/create.php">
            + Thêm công thức
        </a>

        <a class="account-link" href="admin/index.php">
            Quản trị
        </a>

        <form method="post" action="admin/logout.php">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >
            <button class="button" type="submit">
                Đăng xuất
            </button>
        </form>
    <?php else: ?>
        <a class="button" href="admin/login.php">
            Đăng nhập
        </a>
    <?php endif; ?>
</div>
            </div>

            <nav class="categories" aria-label="Danh mục món ăn">
                <a
                    class="category <?= $categoryId === 0 ? 'active' : '' ?>"
                    href="<?= e(categoryUrl(0, $q)) ?>"
                >
                    Tất cả
                </a>

                <?php foreach ($categories as $category): ?>
                    <a
                        class="category <?= $categoryId === (int) $category['id'] ? 'active' : '' ?>"
                        href="<?= e(categoryUrl((int) $category['id'], $q)) ?>"
                    >
                        <?= e($category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($featured): ?>
            <section class="hero" aria-label="Món ăn nổi bật">
                <img
                    class="hero-image"
                    src="<?= e(recipeImage($featured)) ?>"
                    alt="<?= e($featured['title']) ?>"
                >
                <div class="hero-shade"></div>

                <div class="hero-content">
                    <span class="badge">🔥 GỢI Ý HÔM NAY</span>

                    <h1><?= e($featured['title']) ?></h1>
                    <p><?= e($featured['description']) ?></p>

                    <div class="hero-actions">
                        <a class="button" href="recipe.php?id=<?= (int) $featured['id'] ?>">
                           Xem công thức →
                        </a>
                        <span>
                            ⏱ <?= (int) $featured['cooking_minutes'] ?> phút
                        </span>
                        <span>
                            🍽 <?= (int) $featured['servings'] ?> người ăn
                        </span>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section id="recipes">
            <div class="section-title">
                <h2>
                    <?= $q !== '' || $categoryId > 0
                        ? 'Kết quả tìm kiếm'
                        : 'Tất Cả Công Thức' ?>
                </h2>
                <span class="count"><?= count($recipes) ?></span>
            </div>

            <?php if (!$recipes): ?>
                <div class="empty">
                    <p>Không tìm thấy công thức phù hợp.</p>
                    <a href="index.php" class="button">
                        Xem tất cả
                    </a>
                </div>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($recipes as $recipe): ?>
                        <article class="card">
                            <div class="card-picture">
                                <img
                                    src="<?= e(recipeImage($recipe)) ?>"
                                    alt="<?= e($recipe['title']) ?>"
                                    loading="lazy"
                                >
                                <span class="card-category">
                                    <?= e($recipe['category_name']) ?>
                                </span>
                            </div>

                            <div class="card-content">
                                <h3>
                                    <a href="recipe.php?id=<?= (int) $recipe['id'] ?>">
                                       <?= e($recipe['title']) ?>
                                    </a>
                                </h3>
                                <p class="description">
                                    <?= e($recipe['description']) ?>
                                </p>

                                <div class="meta">
                                    <span>
                                        ⏱ <?= (int) $recipe['cooking_minutes'] ?> phút
                                    </span>
                                    <span>
                                        🍽 <?= (int) $recipe['servings'] ?> người ăn
                                    </span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        BếpNhà · Dự án Triển khai và quản trị hệ thống phần mềm
    </footer>
</body>
</html>