<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/guest-options.php';
function ensureCommunity(PDO $pdo): void
{
    ensureGuestRecipeOptions($pdo);
    if ($pdo->query("SHOW TABLES LIKE 'community_schema'")->fetch()
        && $pdo->query('SELECT version FROM community_schema WHERE version=1')->fetch()) return;
    if ((int) $pdo->query("SELECT GET_LOCK('bepnha_community_v1',10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Không thể cập nhật chức năng thành viên.');
    }
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS community_schema (version INT PRIMARY KEY) ENGINE=InnoDB');
        if ($pdo->query('SELECT version FROM community_schema WHERE version=1')->fetch()) return;
        if (!$pdo->query("SHOW COLUMNS FROM recipes LIKE 'owner_id'")->fetch()) {
            $pdo->exec('ALTER TABLE recipes ADD COLUMN owner_id INT UNSIGNED NULL, ADD CONSTRAINT fk_recipes_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_profiles (
            user_id INT UNSIGNED PRIMARY KEY, display_name VARCHAR(80) NOT NULL DEFAULT '',
            email VARCHAR(254) NOT NULL DEFAULT '', bio VARCHAR(500) NOT NULL DEFAULT '',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec('CREATE TABLE IF NOT EXISTS favorites (
            user_id INT UNSIGNED NOT NULL, recipe_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id,recipe_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS recipe_ratings (
            user_id INT UNSIGNED NOT NULL, recipe_id INT UNSIGNED NOT NULL,
            score TINYINT UNSIGNED NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(user_id,recipe_id), CHECK(score BETWEEN 1 AND 5),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS recipe_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
            recipe_id INT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_comments_recipe(recipe_id,created_at),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec('CREATE TABLE IF NOT EXISTS recipe_views (
            user_id INT UNSIGNED NOT NULL, recipe_id INT UNSIGNED NOT NULL,
            last_viewed_at TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(user_id,recipe_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        $pdo->exec('INSERT IGNORE INTO community_schema(version) VALUES(1)');
    } finally { $pdo->query("SELECT RELEASE_LOCK('bepnha_community_v1')"); }
}
function currentMember(): array|false
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id,username,role FROM users WHERE id=?');
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    return $stmt->fetch();
}
function requireContributor(): array
{
    $user = currentMember();
    if (!$user) { header('Location: /recipe/login.php'); exit; }
    return $user;
}
function requireRecipeOwner(int $id, array $user): void
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT owner_id FROM recipes WHERE id=?');
    $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('Không tìm thấy công thức.'); }
    if ($user['role'] !== 'admin' && (int) $row['owner_id'] !== (int) $user['id']) {
        http_response_code(403);
        exit('Bạn chỉ được sửa hoặc xóa công thức của mình. <a href="/recipe/member/index.php?tab=recipes">Về công thức của tôi</a>');
    }
}
function memberDashboard(array $user): string
{
    return $user['role'] === 'admin' ? '/recipe/admin/index.php' : '/recipe/member/index.php?tab=recipes';
}

require_once __DIR__ . '/moderation-lib.php';
ensureModeration($pdo);
