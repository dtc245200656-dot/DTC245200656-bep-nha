<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
// Bổ sung cấu trúc một lần cho bản phát triển hiện tại; không sửa dữ liệu món cũ.
function ensureGuestRecipeOptions(PDO $pdo): void
{
    $check = $pdo->query("SHOW COLUMNS FROM recipes LIKE 'difficulty'");
    if ($check->fetch()) return;
    $lock = (int) $pdo->query("SELECT GET_LOCK('bepnha_guest_options_v1', 10)")->fetchColumn();
    if ($lock !== 1) throw new RuntimeException('Không thể cập nhật cấu trúc công thức. Hãy thử lại.');
    try {
        if (!$pdo->query("SHOW COLUMNS FROM recipes LIKE 'difficulty'")->fetch()) {
            $pdo->exec("ALTER TABLE recipes ADD COLUMN difficulty ENUM('easy','medium','hard') NULL DEFAULT NULL");
            $stmt = $pdo->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
            $stmt->execute(['Món canh']);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('bepnha_guest_options_v1')");
    }
}

function difficultyLabels(): array
{
    return ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
}
