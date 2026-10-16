<?php
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/guest-options.php';
ensureGuestRecipeOptions($pdo);
require_once __DIR__ . '/includes/community-lib.php';
ensureCommunity($pdo);
$stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id'] ?? 0]);
$currentUser = $stmt->fetch();
$currentAdmin = $currentUser && $currentUser['role'] === 'admin' ? $currentUser : false;
$memberFavorites = [];
if ($currentUser) {
    $stmt = $pdo->prepare('SELECT recipe_id FROM favorites WHERE user_id=?');
    $stmt->execute([$currentUser['id']]);
    $memberFavorites = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
if (($_GET['page'] ?? '') === 'account') {
    $accountTab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'profile';
    header('Location: /recipe/member/index.php?tab=' . rawurlencode($accountTab));
    exit;
}
if (($_GET['page'] ?? '') === 'about') {
    require __DIR__ . '/includes/about-view.php';
    exit;
}
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 200) : '';
$categoryId = max(0, (int) ($_GET['category'] ?? 0));
$maxTime = (int) ($_GET['max_time'] ?? 0);
if (!in_array($maxTime, [0, 15, 30, 60, 90, 180], true)) $maxTime = 0;
$difficulty = is_string($_GET['difficulty'] ?? null) ? $_GET['difficulty'] : '';
if (!array_key_exists($difficulty, difficultyLabels())) $difficulty = '';
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();
$sql = 'SELECT r.*, c.name AS category_name FROM recipes r JOIN categories c ON c.id = r.category_id WHERE r.status=\'approved\'';
$params = [];
if ($q !== '') { $sql .= ' AND r.title LIKE ?'; $params[] = '%' . $q . '%'; }
if ($categoryId > 0) { $sql .= ' AND r.category_id = ?'; $params[] = $categoryId; }
if ($maxTime > 0) { $sql .= ' AND r.cooking_minutes <= ?'; $params[] = $maxTime; }
if ($difficulty !== '') { $sql .= ' AND r.difficulty = ?'; $params[] = $difficulty; }
$sql .= ' ORDER BY r.created_at DESC, r.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();
$recentRecipes = $pdo->query('SELECT id, title, image_path, cooking_minutes FROM recipes WHERE status=\'approved\' ORDER BY created_at DESC, id DESC LIMIT 3')->fetchAll();
$featured = $pdo->query("SELECT r.*, c.name AS category_name FROM recipes r JOIN categories c ON c.id=r.category_id WHERE r.status='approved' ORDER BY CASE WHEN r.title='Gà chiên mắm tỏi' THEN 0 ELSE 1 END,r.id DESC LIMIT 1")->fetch();
function categoryUrl(int $id, string $q): string
{
    global $maxTime, $difficulty;
    return 'index.php?' . http_build_query(['category' => $id, 'q' => $q, 'max_time' => $maxTime, 'difficulty' => $difficulty]);
}
function recipeImage(array $recipe): string
{
    return $recipe['image_path'] ?: 'images/bua-com-nha.png';
}
?>

<?php
$allRecipeCount = (int) $pdo->query('SELECT COUNT(*) FROM recipes WHERE status=\'approved\'')->fetchColumn();
$categoryIcons = ['Món chính' => '🍲', 'Món chay' => '🥗', 'Tráng miệng' => '🍮', 'Món canh' => '🍜'];
$categoryDescriptions = ['Món chính' => 'Đậm đà hương vị quê nhà', 'Món chay' => 'Thanh mát, tốt cho sức khỏe', 'Tráng miệng' => 'Ngọt ngào mỗi ngày', 'Món canh' => 'Ấm lành cho bữa cơm nhà'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Bếp Nhà — chia sẻ công thức nấu ăn giản dị, dễ làm cho mỗi bữa cơm gia đình Việt.">
<title>Bếp Nhà — Gắn kết yêu thương</title>
<style>
:root{--green:#3f512c;--green-dark:#293b20;--orange:#b95830;--ink:#30362e;--muted:#777c70;--cream:#faf8f3;--line:#e9e6dd}
*{box-sizing:border-box}html{scroll-behavior:smooth;scroll-padding-top:24px}body{margin:0;background:var(--cream);color:var(--ink);font:15px/1.65 Arial,sans-serif}a{color:inherit;text-decoration:none}button,input{font:inherit}button,a,input{ -webkit-tap-highlight-color:transparent}button{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible{outline:3px solid #b95830;outline-offset:4px}.wrap{width:min(1240px,92%);margin:auto}.header{background:#fffefa;border-bottom:1px solid var(--line)}.header-row{display:flex;align-items:center;gap:28px;min-height:92px;padding:14px 0}.brand{display:flex;align-items:center;gap:9px;flex-shrink:0}.brand svg{width:49px;height:49px}.brand-name{font:700 29px/1.3 'Times New Roman',Times,serif;letter-spacing:normal}.brand-name em{color:var(--orange);font-style:normal}.brand small{display:block;margin-top:5px;font-size:9px;letter-spacing:.2px;color:#666c60}.nav{display:flex;align-items:center;gap:23px;margin-left:auto;white-space:nowrap;font-size:13px}.nav a{padding:11px 0}.nav .active{border-bottom:2px solid var(--green);font-weight:700;color:var(--green)}.search{display:flex;align-items:center;gap:7px;background:#f2f3ef;border:1px solid #eaede5;border-radius:30px;padding:8px 13px;flex:0 1 245px}.search input[type=search]{width:100%;min-width:0;border:0;background:transparent;outline:none;font-size:12px}.search button{border:0;padding:0;background:none;color:#737a6d;display:flex;align-items:center}.icon{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.account{display:flex;align-items:center;gap:13px;font-size:12px;white-space:nowrap}.account a,.account button{display:inline-flex;align-items:center;gap:6px}.account form{margin:0}.account button{border:0;background:none;padding:0;color:var(--orange)}.account .add{color:var(--green);font-weight:bold}.fav-filter{display:flex;border:0;background:none;color:var(--orange);padding:4px;position:relative}.fav-filter[aria-pressed=true]{background:#fce9dd;border-radius:8px}.fav-count{position:absolute;right:-5px;top:-5px;background:var(--green);color:white;font-size:9px;line-height:15px;min-width:15px;border-radius:20px}.hero{position:relative;min-height:390px;overflow:hidden;background:#eee4d5}.hero-photo{position:absolute;inset:0 0 0 28%;width:72%;height:100%;object-fit:cover;object-position:center 52%}.hero::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,#f6f0e5 0%,#f6f0e5 24%,#f6f0e5ed 32%,#f6f0e590 42%,transparent 60%)}.hero-inner{position:relative;z-index:1;padding-top:47px;padding-bottom:46px}.hero-copy{max-width:460px}.eyebrow{font-size:10px;letter-spacing:3px;color:#745e49;font-weight:700}.hero h1{font:700 clamp(34px,3.6vw,52px)/1.25 'Times New Roman',Times,serif;letter-spacing:normal;margin:15px 0 18px}.hero h1 em{display:block;color:var(--orange);font-weight:400;line-height:1.3}.hero-description{font-size:14px;color:#51584c;max-width:320px;margin:0 0 23px}.pill{display:inline-flex;align-items:center;justify-content:center;gap:15px;padding:11px 22px;border-radius:30px;background:var(--green);color:white;font-size:12px;border:0}.pill:hover{background:var(--green-dark)}.hero-note{position:absolute;right:6%;top:32px;z-index:2;color:white;text-shadow:0 2px 8px #000;font:italic 23px/1.5 'Times New Roman',Times,serif;transform:rotate(-5deg)}.hero-credit{position:absolute;right:24px;bottom:17px;z-index:2;padding:5px 12px;border-radius:20px;background:#1b261db3;color:white;font-size:11px}.sprig{position:absolute;z-index:2;bottom:22px;left:1%;width:85px;opacity:.55;pointer-events:none}.category-section{padding-top:23px;padding-bottom:28px}.category-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}.category-tile{display:flex;align-items:center;gap:14px;padding:17px 16px;background:#f0f0e9;border:1px solid transparent;border-radius:12px;transition:background .15s}.category-tile:hover,.category-tile.selected{background:#e8ecdf;border-color:#ccd4be}.category-art{font-size:34px;line-height:1}.category-text strong{display:block;font-size:14px}.category-text small{display:block;font-size:11px;color:#777b70}.arrow{margin-left:auto;flex-shrink:0;background:#e5e8dd;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;color:var(--green)}.section-heading{display:flex;align-items:center;gap:12px;margin:0 0 21px}.section-heading h2{font:700 27px/1.3 'Times New Roman',Times,serif;margin:0}.section-heading h2 span{color:var(--green)}.section-heading .rule{height:1px;width:65px;background:#c49266}.section-heading a{margin-left:auto;font-size:12px;color:var(--green)}.count{font:12px Arial,sans-serif;padding:3px 8px;border-radius:20px;background:#e8ecdf;color:var(--green)}.recipe-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:23px}.card{background:white;overflow:hidden;border:1px solid var(--line);border-radius:13px;box-shadow:0 4px 14px #47412b04;transition:transform .2s,box-shadow .2s}.card:hover{transform:translateY(-4px);box-shadow:0 10px 22px #47412b10}.card-picture{position:relative;aspect-ratio:1.9;overflow:hidden;background:#e5e9dc}.card-picture>a{display:block;height:100%}.card-picture img{width:100%;height:100%;object-fit:cover;transition:transform .35s}.card:hover img{transform:scale(1.04)}.heart{position:absolute;right:11px;top:11px;display:grid;place-items:center;border:0;border-radius:50%;width:32px;height:32px;background:#fffdf7ed;color:var(--orange);box-shadow:0 2px 7px #0001}.heart[aria-pressed=true] .icon{fill:var(--orange)}.tag{position:absolute;left:12px;bottom:11px;background:#fffdf4ed;color:var(--green-dark);border-radius:20px;padding:3px 10px;font-size:10px}.card-content{padding:16px 18px}.card h3{font-size:16px;line-height:1.4;margin:0 0 10px}.card h3 a:hover{color:var(--orange)}.meta{display:flex;flex-wrap:wrap;gap:15px;color:#6e7466;font-size:11px}.meta span{display:flex;align-items:center;gap:5px}.meta .icon{width:14px;height:14px;color:#9b795c}.card p{font-size:12px;color:var(--muted);margin:10px 0 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.recipes-section{padding-bottom:45px}.empty{padding:38px 20px;background:#f0f0e9;text-align:center;border-radius:12px}.empty p{color:var(--muted)}.about{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;border-top:1px solid var(--line);padding:42px 0}.about h2{font:700 30px/1.25 'Times New Roman',Times,serif;margin:10px 0}.about p{font-size:14px;color:#707669}.about-note{background:#ecefdf;border-radius:15px;padding:27px 32px;border-left:3px solid #73875d}.about-note strong{font:italic 25px/1.5 'Times New Roman',Times,serif;color:var(--green)}.about-note p{margin-bottom:0;font-size:12px}.footer{background:#edf0e7;border-top:1px solid #e0e5d5;padding:22px 0;color:#72796a;font-size:11px}.footer .wrap{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}.footer strong{color:var(--green)}[hidden]{display:none!important}.favorites-note{color:var(--muted);font-size:12px;margin-top:-10px}.status{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);border-radius:30px;background:var(--green-dark);color:white;padding:10px 20px;z-index:10;font-size:13px;box-shadow:0 4px 15px #0002}.mobile-link{display:none}
@media(min-width:1400px){.hero{min-height:440px}.hero-inner{padding-top:65px;padding-bottom:65px}}
@media(max-width:1150px){.header-row{gap:18px;flex-wrap:wrap}.nav{gap:18px}.search{margin-left:auto}.account{margin-left:auto}.header-row:has(.add) .search{flex-basis:230px}.category-text small{font-size:10px}.category-tile{padding:15px 12px;gap:9px}.category-art{font-size:28px}}
@media(max-width:800px){.header-row{gap:14px}.brand-name{font-size:25px}.brand svg{width:42px;height:42px}.nav{order:3;width:100%;justify-content:center;margin:0;border-top:1px solid var(--line);padding-top:4px}.search{order:4;flex:1 1 100%!important;margin:0}.account{margin-left:auto}.hero-photo{inset:0;width:100%;opacity:.55}.hero::after{background:linear-gradient(90deg,#f6f0e5fa,#f6f0e5a3 65%,#f6f0e520)}.hero-note{display:none}.hero-inner{padding-top:38px;padding-bottom:50px}.hero{min-height:350px}.hero-credit{font-size:10px;bottom:10px}.category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.recipe-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.about{gap:25px}.sprig{display:none}.section-heading h2{font-size:25px}}
@media(max-width:480px){.wrap{width:91%}.account{gap:9px;font-size:11px}.account .add{font-size:10px}.brand small{font-size:8px}.nav{gap:24px;font-size:12px}.hero h1{font-size:38px}.hero-copy{max-width:350px}.eyebrow{font-size:8px;letter-spacing:2px}.hero-description{max-width:275px}.category-tile{padding:12px 10px;gap:8px}.category-art{font-size:25px}.category-text strong{font-size:12px}.category-text small{font-size:9px}.arrow{width:20px;height:20px}.recipe-grid{grid-template-columns:1fr}.card-picture{aspect-ratio:1.8}.section-heading{gap:8px}.section-heading h2{font-size:23px}.section-heading .rule{display:none}.section-heading a{font-size:10px}.about{grid-template-columns:1fr;gap:15px}.about h2{font-size:26px}.hero-credit{max-width:70%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
.filter-bar{display:flex;align-items:end;gap:14px;flex-wrap:wrap;padding:20px;background:#eff1e8;border-radius:13px;margin:0 0 16px}.filter-bar label{display:block;font-size:12px;font-weight:bold;flex:1 1 150px}.filter-bar select{display:block;width:100%;font:14px Arial,sans-serif;color:#30362e;border:1px solid #d9decd;background:#fffefa;border-radius:8px;padding:11px;margin-top:7px}.filter-bar>a{font-size:12px;padding-bottom:10px;color:#b95830}.difficulty-tag{color:#877050}.account-name{max-width:125px;overflow:hidden;text-overflow:ellipsis;color:#4a5c34;font-size:12px}.discovery{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px}.featured-pick{position:relative;overflow:hidden;border-radius:14px;min-height:230px;background:#3f512c}.featured-pick>img{width:100%;height:100%;position:absolute;inset:0;object-fit:cover}.featured-pick>span{position:relative;display:flex;flex-direction:column;justify-content:end;gap:5px;min-height:230px;padding:24px;color:white;background:linear-gradient(0deg,#1d2a1ee8,transparent)}.featured-pick small{font-size:10px;letter-spacing:2px}.featured-pick strong{font:700 28px 'Times New Roman',serif}.featured-pick span span{font-size:12px}.recent-panel{padding:19px 22px;border:1px solid #e9e6dd;border-radius:14px;background:#fffefa}.recent-panel h2{font:700 23px 'Times New Roman',serif;margin:0 0 14px}.recent-list{display:grid;gap:10px}.recent-list>a{display:flex;gap:12px;align-items:center}.recent-list img{width:63px;height:46px;object-fit:cover;border-radius:7px}.recent-list strong{display:block;font-size:13px}.recent-list small{display:block;font-size:10px;color:#777c70}@media(max-width:700px){.discovery{grid-template-columns:1fr}.account{flex-wrap:wrap}.filter-bar label{flex-basis:100%}.filter-bar .pill{flex:1}.account-name{max-width:90px}}

</style>
</head>
<body>
<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">
<symbol id="heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></symbol>
<symbol id="search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
<symbol id="user" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="8" r="3"/><path d="M5 19c0-7 14-7 14 0"/></symbol>
<symbol id="clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="people" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-15a3 3 0 0 1 0 6m3 9v-2a6 6 0 0 0-2-4"/></symbol>
</svg>
<header class="header"><div class="wrap header-row">
<a href="index.php" class="brand" aria-label="Bếp Nhà — Trang chủ">
<svg viewBox="0 0 60 60" aria-hidden="true"><path d="M6 31 30 8l24 23-5 5-19-18L11 36Z" fill="#b76a37"/><path d="M16 31 30 18l14 13v24H16Z" fill="#e7dcc3"/><path d="M25 39h12v16H25Z" fill="#4a6038"/><path d="M6 49c2-15 11-19 15-19-1 11-8 17-15 19Zm38-29c-1-9 4-15 10-16 1 8-3 14-10 16Z" fill="#758856"/><path d="m10 46 10-13m26-16 5-9" stroke="#3d512e" stroke-width="1.5"/><circle cx="32" cy="34" r="6" fill="#c9753d"/><path d="M29 32q-2-3 1-5m3 5q-2-3 1-5" fill="none" stroke="#fff8e4" stroke-width="1.5"/></svg>
<div><div class="brand-name">Bếp <em>Nhà</em></div><small>Công thức nhỏ, thương nhớ đong đầy</small></div></a>
<nav class="nav" aria-label="Điều hướng chính"><a class="active" href="index.php">Trang chủ</a><a href="#cong-thuc">Công thức</a><a href="#danh-muc">Danh mục</a><a href="index.php?page=about">Giới thiệu</a></nav>
<form class="search" action="index.php" method="get" role="search"><button type="submit" aria-label="Tìm kiếm"><svg class="icon"><use href="#search"/></svg></button><input name="q" type="search" value="<?= e($q) ?>" placeholder="Tìm món ăn yêu thích..." aria-label="Tên món ăn"><input name="category" type="hidden" value="<?= $categoryId ?>"><input name="max_time" type="hidden" value="<?= $maxTime ?>"><input name="difficulty" type="hidden" value="<?= e($difficulty) ?>"></form>
<div class="account"><button class="fav-filter" id="favorite-filter" type="button" aria-pressed="false" aria-label="Xem món yêu thích" title="Món yêu thích"><svg class="icon"><use href="#heart"/></svg><span id="favorite-count" class="fav-count" hidden>0</span></button>
<?php if ($currentUser): ?><a class="account-name" href="member/index.php" title="Tài khoản cá nhân">Tài khoản</a><a class="add" href="member/create.php">+ Đăng món</a><?php if ($currentAdmin): ?><a href="admin/index.php">Quản trị</a><?php endif; ?><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button type="submit">Đăng xuất</button></form>
<?php else: ?><a href="login.php"><svg class="icon" style="color:var(--orange)"><use href="#user"/></svg>Đăng nhập</a><a href="register.php">Đăng ký</a><?php endif; ?></div>
</div></header>
<main>
<section class="hero" aria-label="Cảm hứng nấu ăn">
<img class="hero-photo" src="images/bua-com-nha.png" alt="Bữa cơm nhà ấm áp với cơm trắng, cá, canh và rau trên bàn gỗ" fetchpriority="high">
<div class="wrap hero-inner"><div class="hero-copy"><div class="eyebrow">NẤU NGON · SỐNG VUI · GIA ĐÌNH ẤM ÁP</div><h1>Những món ăn<br><em>gắn kết yêu thương</em></h1><p class="hero-description">Chia sẻ công thức nấu ăn đơn giản, dễ làm<br>cho mỗi bữa cơm gia đình Việt.</p><a class="pill" href="#cong-thuc">Khám phá công thức <span aria-hidden="true">→</span></a></div></div>
<div class="hero-note">Bữa cơm nhà<br>luôn là ngon nhất ♡</div>
<a class="hero-credit" href="#cong-thuc">Gợi ý cho bữa cơm nhà hôm nay ↗</a>
<svg class="sprig" viewBox="0 0 90 150" aria-hidden="true"><path d="M10 150 60 8" fill="none" stroke="#4f643a" stroke-width="2"/><g fill="#819567" stroke="#4f643a" stroke-width=".5"><path d="M24 112Q-2 98 3 66q28 10 21 46Zm8-25Q12 58 24 35q21 22 8 52Zm12-32Q35 28 52 8q10 27-8 47Zm-14 65q20-34 51-29-12 25-51 29Zm12-38q17-31 43-31-10 26-43 31Z"/></g></svg>
</section>
<section id="danh-muc" class="wrap category-section" aria-label="Danh mục công thức"><div class="category-grid">
<a class="category-tile <?= $categoryId === 0 ? 'selected' : '' ?>" href="<?= e(categoryUrl(0, $q)) ?>#cong-thuc"><span class="category-art" aria-hidden="true">🍚</span><span class="category-text"><strong>Tất cả món ngon</strong><small><?= $allRecipeCount ?> công thức từ Bếp Nhà</small></span><span class="arrow" aria-hidden="true">→</span></a>
<?php foreach ($categories as $category): ?><a class="category-tile <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>" href="<?= e(categoryUrl((int) $category['id'], $q)) ?>#cong-thuc"><span class="category-art" aria-hidden="true"><?= $categoryIcons[$category['name']] ?? '🍽️' ?></span><span class="category-text"><strong><?= e($category['name']) ?></strong><small><?= e($categoryDescriptions[$category['name']] ?? 'Cùng vào bếp mỗi ngày') ?></small></span><span class="arrow" aria-hidden="true">→</span></a><?php endforeach; ?>
</div></section>
<?php if ($q === '' && !$categoryId && !$maxTime && $difficulty === ''): ?>
<section class="wrap discovery" aria-label="Món nổi bật và món mới">
<?php if ($featured): ?><a class="featured-pick" href="recipe.php?id=<?= (int) $featured['id'] ?>"><img src="<?= e(recipeImage($featured)) ?>" alt="" loading="lazy"><span><small>GỢI Ý NỔI BẬT</small><strong><?= e($featured['title']) ?></strong><span><?= (int) $featured['cooking_minutes'] ?> phút · Xem công thức →</span></span></a><?php endif; ?>
<div class="recent-panel"><h2>Mới từ căn bếp</h2><div class="recent-list"><?php foreach ($recentRecipes as $recent): ?><a href="recipe.php?id=<?= (int) $recent['id'] ?>"><img src="<?= e(recipeImage($recent)) ?>" alt="" loading="lazy"><span><strong><?= e($recent['title']) ?></strong><small><?= (int) $recent['cooking_minutes'] ?> phút · Vừa thêm vào Bếp Nhà</small></span></a><?php endforeach; ?></div></div>
</section>
<?php endif; ?>

<section id="cong-thuc" class="wrap recipes-section"><div class="section-heading"><h2><span aria-hidden="true">❧</span> <?= $q !== '' || $categoryId > 0 || $maxTime > 0 || $difficulty !== '' ? 'Công thức dành cho bạn' : 'Khám phá công thức' ?></h2><span class="count"><?= count($recipes) ?></span><span class="rule"></span><a href="index.php#cong-thuc">Xem tất cả công thức →</a></div>
<?php if ($q !== ''): ?><p class="favorites-note">Kết quả cho “<?= e($q) ?>”</p><?php endif; ?>
<form class="filter-bar" method="get" action="index.php#cong-thuc" aria-label="Lọc công thức">
<input type="hidden" name="q" value="<?= e($q) ?>">
<label>Loại món<select name="category"><option value="0">Tất cả loại món</option><?php foreach ($categories as $filterCategory): ?><option value="<?= (int) $filterCategory['id'] ?>" <?= $categoryId === (int) $filterCategory['id'] ? 'selected' : '' ?>><?= e($filterCategory['name']) ?></option><?php endforeach; ?></select></label>
<label>Thời gian nấu<select name="max_time"><option value="0">Không giới hạn</option><?php foreach ([15,30,60,90,180] as $limit): ?><option value="<?= $limit ?>" <?= $maxTime === $limit ? 'selected' : '' ?>>Tối đa <?= $limit ?> phút</option><?php endforeach; ?></select></label>
<label>Độ khó<select name="difficulty"><option value="">Tất cả</option><?php foreach (difficultyLabels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $difficulty === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<button class="pill" type="submit">Áp dụng bộ lọc</button><a href="index.php#cong-thuc">Xóa bộ lọc</a>
</form>
<p class="favorites-note">Độ khó do quản trị viên đánh giá. Món chưa có đánh giá được ghi “Chưa phân loại”.</p>

<p id="favorites-note" class="favorites-note" hidden>Đang xem món yêu thích trong danh sách này. Món yêu thích được lưu theo tài khoản khi bạn đăng nhập.</p>
<?php if (!$recipes): ?><div class="empty"><strong>Chưa tìm thấy món phù hợp</strong><p>Thử tên món khác hoặc chọn lại danh mục nhé.</p><a class="pill" href="index.php#cong-thuc">Xem tất cả món</a></div><?php else: ?><div class="recipe-grid">
<?php foreach ($recipes as $recipe): ?><article class="card" data-recipe-id="<?= (int) $recipe['id'] ?>"><div class="card-picture"><a href="recipe.php?id=<?= (int) $recipe['id'] ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(recipeImage($recipe)) ?>" alt="<?= e($recipe['title']) ?>" loading="lazy"></a><button type="button" class="heart" data-favorite="<?= (int) $recipe['id'] ?>" data-name="<?= e($recipe['title']) ?>" aria-pressed="false" aria-label="Yêu thích: <?= e($recipe['title']) ?>"><svg class="icon" aria-hidden="true"><use href="#heart"/></svg></button><span class="tag"><?= e($recipe['category_name']) ?></span></div><div class="card-content"><h3><a href="recipe.php?id=<?= (int) $recipe['id'] ?>"><?= e($recipe['title']) ?></a></h3><div class="meta"><span><svg class="icon" aria-hidden="true"><use href="#clock"/></svg><?= (int) $recipe['cooking_minutes'] ?> phút</span><span><svg class="icon" aria-hidden="true"><use href="#people"/></svg><?= (int) $recipe['servings'] ?> người</span><span class="difficulty-tag"><?= e(difficultyLabels()[$recipe['difficulty'] ?? ''] ?? 'Chưa phân loại') ?></span></div><p><?= e($recipe['description']) ?></p></div></article><?php endforeach; ?>
</div><div id="favorites-empty" class="empty" hidden><strong>Chưa có món yêu thích trong danh sách này</strong><p>Nhấn trái tim trên thẻ món để lưu lại, hoặc bỏ bộ lọc yêu thích để khám phá thêm.</p><button type="button" class="pill" id="show-all">Bỏ lọc yêu thích</button></div><?php endif; ?>
</section>
<section id="gioi-thieu" class="wrap about"><div><div class="eyebrow">CHUYỆN CỦA BẾP NHÀ</div><a href="index.php?page=about" style="color:var(--orange);font-size:12px">Tìm hiểu về Bếp Nhà →</a><h2>Một chút thời gian,<br>một bữa cơm đầy thương nhớ.</h2><p>Bếp Nhà gom những công thức quen thuộc, nguyên liệu gần gũi và cách làm dễ hiểu, để việc vào bếp mỗi ngày trở thành một niềm vui.</p></div><div class="about-note"><strong>“Có những yêu thương<br>được nói bằng một bữa cơm.”</strong><p>Chọn món bạn thích, chuẩn bị nguyên liệu và cùng Bếp Nhà nấu điều ngon lành hôm nay.</p></div></section>
</main>
<footer class="footer"><div class="wrap"><span><strong>Bếp Nhà</strong> · Công thức nhỏ, thương nhớ đong đầy</span><span>Dự án môn Triển khai và quản trị hệ thống phần mềm</span></div></footer>
<div id="status" class="status" role="status" aria-live="polite" hidden></div>
<script>
window.bepNhaMember = <?= json_encode(['loggedIn'=>(bool)$currentUser,'csrf'=>$_SESSION['csrf'],'favorites'=>$memberFavorites], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
(() => {
    const boot = window.bepNhaMember;
    const key = 'bepnha-favorites';
    let favorites = new Set(boot.loggedIn ? boot.favorites.map(String) : []);
    if (!boot.loggedIn) {
        try { const stored = JSON.parse(localStorage.getItem(key) || '[]'); if (Array.isArray(stored)) favorites = new Set(stored.filter(v => typeof v === 'string')); } catch (_) {}
    }
    let filtering = false, noticeTimer;
    const buttons = document.querySelectorAll('[data-favorite]');
    const filter = document.getElementById('favorite-filter');
    const count = document.getElementById('favorite-count');
    const empty = document.getElementById('favorites-empty');
    const note = document.getElementById('favorites-note');
    const status = document.getElementById('status');
    function announce(message) {
        status.textContent=message; status.hidden=false; clearTimeout(noticeTimer);
        noticeTimer=setTimeout(() => { status.hidden=true; },3500);
    }
    function render() {
        buttons.forEach(button => {
            const saved=favorites.has(button.dataset.favorite);
            button.setAttribute('aria-pressed',String(saved));
            button.setAttribute('aria-label',(saved?'Bỏ yêu thích: ':'Yêu thích: ')+button.dataset.name);
        });
        let visible=0;
        document.querySelectorAll('[data-recipe-id]').forEach(card => { card.hidden=filtering&&!favorites.has(card.dataset.recipeId);if(!card.hidden)visible++; });
        count.textContent=favorites.size;count.hidden=favorites.size===0;
        filter.setAttribute('aria-pressed',String(filtering));
        note.hidden=!filtering;
        note.textContent=boot.loggedIn?'Đang lọc các món yêu thích trong kết quả hiện tại. Xem toàn bộ trong Tài khoản → Món yêu thích.':'Món được lưu trên trình duyệt này. Đăng nhập để lưu yêu thích theo tài khoản.';
        if(empty)empty.hidden=!filtering||visible>0;
    }
    buttons.forEach(button => button.addEventListener('click',async () => {
        const id=button.dataset.favorite, saved=!favorites.has(id);
        button.disabled=true;
        try {
            if(boot.loggedIn){
                const response=await fetch('member/action.php',{method:'POST',headers:{'Accept':'application/json'},body:new URLSearchParams({csrf:boot.csrf,action:'favorite',recipe_id:id,saved:saved?'1':'0'})});
                const data=await response.json();
                if(!response.ok||!data.ok)throw new Error(data.message||'Chưa thể lưu món. Hãy tải lại trang.');
            } else {
                const next=new Set(favorites);if(saved)next.add(id);else next.delete(id);
                localStorage.setItem(key,JSON.stringify([...next]));
            }
            if(saved)favorites.add(id);else favorites.delete(id);
            render();announce(saved?'Đã lưu món yêu thích':'Đã bỏ món khỏi yêu thích');
        } catch(error){announce(error.message||'Không thể lưu món lúc này.');}
        finally{button.disabled=false;}
    }));
    filter.addEventListener('click',()=>{filtering=!filtering;render();document.getElementById('cong-thuc').scrollIntoView();});
    document.getElementById('show-all')?.addEventListener('click',()=>{filtering=false;render();});
    render();
})();

</script>
</body>
</html>
