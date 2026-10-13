<?php
require __DIR__ . '/auth.php';
requireAdmin();

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY id'
)->fetchAll();

$categoryIds = array_map('intval', array_column($categories, 'id'));

// Danh sách ảnh đã có trong thư mục images.
$images = [];
$imageDirectory = __DIR__ . '/../images';

foreach (scandir($imageDirectory) ?: [] as $filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (
        is_file($imageDirectory . '/' . $filename)
        && in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
    ) {
        $images[] = 'images/' . $filename;
    }
}

$error = '';
$title = '';
$description = '';
$categoryId = 0;
$minutes = '30';
$servings = '2';
$image = '';
$ingredientText = '';
$stepText = '';
$recipeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$recipeId || $recipeId < 1) {
    http_response_code(404);
    exit('Không tìm thấy món. <a href="index.php">Về quản trị</a>');
}

$stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ?');
$stmt->execute([$recipeId]);
$existingRecipe = $stmt->fetch();

if (!$existingRecipe) {
    http_response_code(404);
    exit('Không tìm thấy món. <a href="index.php">Về quản trị</a>');
}

// Điền thông tin đã lưu vào form khi mở trang.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $title = $existingRecipe['title'];
    $description = $existingRecipe['description'];
    $categoryId = (int) $existingRecipe['category_id'];
    $minutes = (string) $existingRecipe['cooking_minutes'];
    $servings = (string) $existingRecipe['servings'];
    $image = $existingRecipe['image_path'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT i.name, ri.quantity, ri.unit
         FROM recipe_ingredients ri
         JOIN ingredients i ON i.id = ri.ingredient_id
         WHERE ri.recipe_id = ?
         ORDER BY i.id'
    );
    $stmt->execute([$recipeId]);

    $lines = [];

    foreach ($stmt->fetchAll() as $ingredient) {
        $quantity = rtrim(rtrim($ingredient['quantity'], '0'), '.');

        $lines[] = $ingredient['name']
            . ' | ' . $quantity
            . ' | ' . $ingredient['unit'];
    }

    $ingredientText = implode("\n", $lines);

    $stmt = $pdo->prepare(
        'SELECT instruction FROM recipe_steps
         WHERE recipe_id = ?
         ORDER BY step_number'
    );
    $stmt->execute([$recipeId]);

    $stepText = implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $minutes = trim((string) ($_POST['minutes'] ?? ''));
    $servings = trim((string) ($_POST['servings'] ?? ''));
    $image = (string) ($_POST['image'] ?? '');
    $ingredientText = trim((string) ($_POST['ingredients'] ?? ''));
    $stepText = trim((string) ($_POST['steps'] ?? ''));

    try {
        if ($title === '' || mb_strlen($title) > 200) {
            throw new InvalidArgumentException(
                'Tên món cần từ 1 đến 200 ký tự.'
            );
        }

        if ($description === '' || mb_strlen($description) > 2000) {
            throw new InvalidArgumentException(
                'Mô tả cần từ 1 đến 2000 ký tự.'
            );
        }

        if (!in_array($categoryId, $categoryIds, true)) {
            throw new InvalidArgumentException('Hãy chọn danh mục.');
        }

        $validMinutes = filter_var(
            $minutes,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 10080]]
        );

        $validServings = filter_var(
            $servings,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1000]]
        );

        if ($validMinutes === false || $validServings === false) {
            throw new InvalidArgumentException(
                'Thời gian và số người ăn phải là số nguyên dương hợp lệ.'
            );
        }

        if (!in_array($image, $images, true)) {
            throw new InvalidArgumentException('Hãy chọn ảnh món ăn.');
        }

        // Mỗi dòng: Tên nguyên liệu | Số lượng | Đơn vị
        $ingredientRows = [];

        foreach (preg_split('/\R/u', $ingredientText) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if (count($parts) !== 3) {
                throw new InvalidArgumentException(
                    'Nguyên liệu phải theo mẫu: Tên | Số lượng | Đơn vị.'
                );
            }

            [$name, $quantity, $unit] = $parts;
            $quantity = str_replace(',', '.', $quantity);

            if (
                $name === '' || mb_strlen($name) > 100
                || $unit === '' || mb_strlen($unit) > 30
                || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $quantity)
                || (float) $quantity <= 0
            ) {
                throw new InvalidArgumentException(
                    'Kiểm tra tên, số lượng và đơn vị của nguyên liệu.'
                );
            }

            $ingredientRows[] = [$name, $quantity, $unit];
        }

        $steps = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $stepText)),
            static fn($line) => $line !== ''
        ));

        if (!$ingredientRows || count($ingredientRows) > 100) {
            throw new InvalidArgumentException(
                'Nhập từ 1 đến 100 nguyên liệu.'
            );
        }

        if (!$steps || count($steps) > 100) {
            throw new InvalidArgumentException(
                'Nhập từ 1 đến 100 bước nấu.'
            );
        }

        foreach ($steps as $step) {
            if (mb_strlen($step) > 5000) {
                throw new InvalidArgumentException(
                    'Mỗi bước nấu tối đa 5000 ký tự.'
                );
            }
        }

        // Lưu toàn bộ công thức trong một giao dịch.
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
    'UPDATE recipes
     SET category_id = ?, title = ?, description = ?,
         cooking_minutes = ?, servings = ?, image_path = ?
     WHERE id = ?'
         );

        $stmt->execute([
            $categoryId,
            $title,
            $description,
            $validMinutes,
            $validServings,
            $image,
            $recipeId,
        ]);

       // Thay nguyên liệu và cách làm bằng nội dung vừa nhập.
       // Các thao tác này nằm trong giao dịch đã có của form.
       $stmt = $pdo->prepare(
           'DELETE FROM recipe_ingredients WHERE recipe_id = ?'
        );
       $stmt->execute([$recipeId]);

       $stmt = $pdo->prepare(
           'DELETE FROM recipe_steps WHERE recipe_id = ?'
       );
       $stmt->execute([$recipeId]);

        $saveIngredient = $pdo->prepare(
            'INSERT INTO ingredients (name) VALUES (?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );

        $saveLink = $pdo->prepare(
            'INSERT INTO recipe_ingredients
             (recipe_id, ingredient_id, quantity, unit)
             VALUES (?, ?, ?, ?)'
        );

        $seenIds = [];

        foreach ($ingredientRows as [$name, $quantity, $unit]) {
            $saveIngredient->execute([$name]);
            $ingredientId = (int) $pdo->lastInsertId();

            if (isset($seenIds[$ingredientId])) {
                throw new InvalidArgumentException(
                    'Nguyên liệu "' . $name . '" bị nhập trùng.'
                );
            }

            $seenIds[$ingredientId] = true;

            $saveLink->execute([
                $recipeId, $ingredientId, $quantity, $unit,
            ]);
        }

        $saveStep = $pdo->prepare(
            'INSERT INTO recipe_steps
             (recipe_id, step_number, instruction)
             VALUES (?, ?, ?)'
        );

        foreach ($steps as $index => $step) {
            $saveStep->execute([$recipeId, $index + 1, $step]);
        }

        $pdo->commit();

        header('Location: index.php?updated=1');
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof InvalidArgumentException) {
            $error = $exception->getMessage();
        } else {
            error_log((string) $exception);
            $error = 'Không thể lưu công thức. Hãy kiểm tra log PHP.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sửa công thức — Bếp Nhà</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f9fc;
            color: #172033;
            line-height: 1.6;
        }

        main {
            width: min(800px, 92%);
            margin: 30px auto;
        }

        a { color: #ea580c; text-decoration: none; }

        form {
            padding: 28px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            font: inherit;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
        }

        textarea { resize: vertical; }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .hint {
            margin: 6px 0;
            color: #64748b;
            font-size: 14px;
        }

        .error {
            padding: 14px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 10px;
        }

        button {
            margin-top: 24px;
            padding: 14px 24px;
            border: 0;
            border-radius: 10px;
            background: #ea580c;
            color: white;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .row { grid-template-columns: 1fr; gap: 0; }
            form { padding: 20px; }
        }
    </style>
</head>
<body>
    <main>
        <a href="index.php">← Về trang quản trị</a>
        <h1>Sửa công thức</h1>

        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

                <form method="post">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >

            <label for="title">Tên món ăn</label>
            <input
                id="title"
                name="title"
                value="<?= e($title) ?>"
                maxlength="200"
                placeholder="Ví dụ: Salad rau củ"
                required
            >

            <label for="description">Mô tả</label>
            <textarea
                id="description"
                name="description"
                rows="3"
                maxlength="2000"
                required
            ><?= e($description) ?></textarea>

            <label for="category_id">Danh mục</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- Chọn danh mục --</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= $categoryId === (int) $category['id']
                            ? 'selected' : '' ?>
                    >
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="row">
                <div>
                    <label for="minutes">Thời gian nấu (phút)</label>
                    <input
                        id="minutes"
                        name="minutes"
                        type="number"
                        min="1"
                        max="10080"
                        step="1"
                        value="<?= e($minutes) ?>"
                        required
                    >
                </div>

                <div>
                    <label for="servings">Số người ăn</label>
                    <input
                        id="servings"
                        name="servings"
                        type="number"
                        min="1"
                        max="1000"
                        step="1"
                        value="<?= e($servings) ?>"
                        required
                    >
                </div>
            </div>

            <label for="image">Ảnh món ăn</label>
            <select id="image" name="image" required>
                <option value="">-- Chọn ảnh --</option>

                <?php foreach ($images as $path): ?>
                    <option
                        value="<?= e($path) ?>"
                        <?= $image === $path ? 'selected' : '' ?>
                    >
                        <?= e(basename($path)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="hint">
                Chép ảnh món mới vào thư mục recipe/images,
                rồi tải lại trang trước khi điền form.
            </p>

            <label for="ingredients">Nguyên liệu</label>
            <p class="hint" id="ingredients-hint">
                Mỗi dòng: Tên nguyên liệu | Số lượng | Đơn vị.
                Không nhập trùng nguyên liệu.
            </p>
            <textarea
                id="ingredients"
                name="ingredients"
                rows="6"
                aria-describedby="ingredients-hint"
                placeholder="Xà lách | 200 | g&#10;Cà chua | 2 | quả&#10;Dưa leo | 1 | quả"
                required
            ><?= e($ingredientText) ?></textarea>

            <label for="steps">Cách làm</label>
            <p class="hint" id="steps-hint">
                Mỗi dòng là một bước. Website tự đánh số.
            </p>
            <textarea
                id="steps"
                name="steps"
                rows="7"
                aria-describedby="steps-hint"
                placeholder="Rửa sạch rau củ.&#10;Cắt rau củ vừa ăn.&#10;Trộn với nước sốt và trình bày ra đĩa."
                required
            ><?= e($stepText) ?></textarea>

            <button type="submit">Lưu công thức</button>
        </form>
    </main>
</body>
</html>