<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
function ensureModeration(PDO $pdo): void
{
    ensureCommunity($pdo);
    if ($pdo->query('SELECT version FROM community_schema WHERE version=2')->fetch()) return;
    if ((int)$pdo->query("SELECT GET_LOCK('bepnha_moderation_v2',10)")->fetchColumn() !== 1) throw new RuntimeException('Chưa thể cập nhật quy trình duyệt món.');
    try {
        if (!$pdo->query("SHOW COLUMNS FROM recipes LIKE 'status'")->fetch()) {
            $pdo->exec("ALTER TABLE recipes ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved', ADD COLUMN review_note VARCHAR(500) NOT NULL DEFAULT ''");
        }
        $pdo->exec('INSERT IGNORE INTO community_schema(version) VALUES(2)');
    } finally { $pdo->query("SELECT RELEASE_LOCK('bepnha_moderation_v2')"); }
}
function recipeStatusLabels(): array { return ['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Cần chỉnh sửa']; }
