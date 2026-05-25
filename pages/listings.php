<?php
// pages/listings.php — Каталог объявлений

require_once __DIR__ . '/../includes/bootstrap.php';

// Параметры фильтрации
$categorySlug = $_GET['category'] ?? '';
$type         = $_GET['type'] ?? '';        // offer | request
$exchange     = $_GET['exchange'] ?? '';    // skill_swap | paid | both
$sort         = $_GET['sort'] ?? 'new';    // new | views | price_asc | price_desc
$page         = max(1, (int)($_GET['page'] ?? 1));
$q            = trim($_GET['q'] ?? '');

// Получить категорию
$currentCategory = null;
if ($categorySlug) {
    $stmt = db()->prepare("SELECT * FROM categories WHERE slug=?");
    $stmt->execute([$categorySlug]);
    $currentCategory = $stmt->fetch();
}

// Все категории для сайдбара
$allCategories = db()->query("
    SELECT c.*, COUNT(l.id) as cnt
    FROM categories c
    LEFT JOIN listings l ON l.category_id=c.id AND l.status='active'
    WHERE c.parent_id IS NULL
    GROUP BY c.id ORDER BY c.sort_order
")->fetchAll();

// Строим условия запроса
$where = ["l.status='active'"];
$params = [];

if ($currentCategory) {
    // Включаем дочерние категории
    $childIds = db()->prepare("SELECT id FROM categories WHERE parent_id=?");
    $childIds->execute([$currentCategory['id']]);
    $ids = array_column($childIds->fetchAll(), 'id');
    $ids[] = $currentCategory['id'];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $where[] = "l.category_id IN ($ph)";
    $params = array_merge($params, $ids);
}
if ($type && in_array($type, ['offer', 'request'])) {
    $where[] = "l.type=?"; $params[] = $type;
}
if ($exchange && in_array($exchange, ['skill_swap', 'paid', 'both'])) {
    $where[] = "l.exchange_type=?"; $params[] = $exchange;
}
if ($q) {
    $where[] = "MATCH(l.title, l.description) AGAINST(? IN BOOLEAN MODE)";
    $params[] = $q . '*';
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$orderMap = [
    'new'        => 'l.created_at DESC',
    'views'      => 'l.views DESC',
    'price_asc'  => 'l.price ASC',
    'price_desc' => 'l.price DESC',
];
$orderBy = $orderMap[$sort] ?? 'l.created_at DESC';

// Подсчёт
$countStmt = db()->prepare("SELECT COUNT(*) FROM listings l $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag = paginate($total, ITEMS_PER_PAGE, $page);

// Получить объявления
$listingsStmt = db()->prepare("
    SELECT l.*,
           u.full_name, u.avatar, u.username, u.rating,
           c.name as cat_name, c.slug as cat_slug,
           (SELECT filename FROM listing_images WHERE listing_id=l.id ORDER BY sort_order LIMIT 1) as image
    FROM listings l
    JOIN users u ON u.id = l.user_id
    JOIN categories c ON c.id = l.category_id
    $whereStr
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$listingsStmt->execute(array_merge($params, [ITEMS_PER_PAGE, $pag['offset']]));
$listings = $listingsStmt->fetchAll();

$pageTitle = $currentCategory ? $currentCategory['name'] : 'Все объявления';
$baseUrl = '?' . http_build_query(array_filter(['category' => $categorySlug, 'type' => $type, 'exchange' => $exchange, 'sort' => $sort, 'q' => $q]));

include __DIR__ . '/../includes/header.php';
?>

<div class="layout-sidebar">
    <!-- Сайдбар с фильтрами -->
    <aside class="sidebar">
        <div class="sidebar-widget">
            <div class="sidebar-title">Категории</div>
            <div class="filter-list">
                <a href="<?= APP_URL ?>/pages/listings.php" class="filter-link <?= !$categorySlug ? 'active' : '' ?>">
                    <span><i class="ti ti-layout-grid"></i> Все категории</span>
                    <span class="filter-count"><?= $total ?></span>
                </a>
                <?php foreach ($allCategories as $cat): ?>
                <a href="?category=<?= e($cat['slug']) ?>" class="filter-link <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>">
                    <span><i class="ti <?= e($cat['icon']) ?>"></i> <?= e($cat['name']) ?></span>
                    <span class="filter-count"><?= $cat['cnt'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sidebar-widget">
            <div class="sidebar-title">Тип</div>
            <div class="filter-list">
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => '', 'page' => 1])) ?>"
                   class="filter-link <?= !$type ? 'active' : '' ?>">Все</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'offer', 'page' => 1])) ?>"
                   class="filter-link <?= $type === 'offer' ? 'active' : '' ?>">
                    <i class="ti ti-hand-rock"></i> Предложения
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'request', 'page' => 1])) ?>"
                   class="filter-link <?= $type === 'request' ? 'active' : '' ?>">
                    <i class="ti ti-hand"></i> Запросы
                </a>
            </div>
        </div>

        <div class="sidebar-widget">
            <div class="sidebar-title">Формат обмена</div>
            <div class="filter-list">
                <a href="?<?= http_build_query(array_merge($_GET, ['exchange' => '', 'page' => 1])) ?>"
                   class="filter-link <?= !$exchange ? 'active' : '' ?>">Все форматы</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['exchange' => 'skill_swap', 'page' => 1])) ?>"
                   class="filter-link <?= $exchange === 'skill_swap' ? 'active' : '' ?>">
                    <i class="ti ti-arrows-exchange"></i> Только обмен
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['exchange' => 'paid', 'page' => 1])) ?>"
                   class="filter-link <?= $exchange === 'paid' ? 'active' : '' ?>">
                    <i class="ti ti-currency-ruble"></i> Только платно
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['exchange' => 'both', 'page' => 1])) ?>"
                   class="filter-link <?= $exchange === 'both' ? 'active' : '' ?>">
                    <i class="ti ti-copy"></i> Оба варианта
                </a>
            </div>
        </div>

        <?php if (isLoggedIn()): ?>
        <a href="<?= APP_URL ?>/pages/create-listing.php" class="btn btn-primary btn-block">
            <i class="ti ti-plus"></i> Разместить объявление
        </a>
        <?php endif; ?>
    </aside>

    <!-- Основной контент -->
    <main>
        <!-- Заголовок и сортировка -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="font-size:1.3rem;font-family:var(--font-display);margin-bottom:4px;">
                    <?= e($pageTitle) ?>
                </h1>
                <p style="font-size:0.85rem;color:var(--text-muted);">
                    Найдено <?= $total ?> объявлений
                    <?= $q ? ' по запросу «' . e($q) . '»' : '' ?>
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <label style="font-size:0.85rem;color:var(--text-secondary);">Сортировка:</label>
                <select class="form-control" style="width:auto;" onchange="
                    const url = new URL(window.location);
                    url.searchParams.set('sort', this.value);
                    url.searchParams.set('page', 1);
                    window.location = url;
                ">
                    <option value="new" <?= $sort==='new' ? 'selected' : '' ?>>Новые</option>
                    <option value="views" <?= $sort==='views' ? 'selected' : '' ?>>Популярные</option>
                    <option value="price_asc" <?= $sort==='price_asc' ? 'selected' : '' ?>>Цена ↑</option>
                    <option value="price_desc" <?= $sort==='price_desc' ? 'selected' : '' ?>>Цена ↓</option>
                </select>
            </div>
        </div>

        <?php if ($listings): ?>
            <div class="grid-3">
                <?php foreach ($listings as $l): ?>
                    <?php include __DIR__ . '/../includes/listing-card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?= renderPagination($pag, $baseUrl) ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="ti ti-mood-empty"></i>
                <h3>Объявлений не найдено</h3>
                <p>Попробуйте изменить фильтры или <a href="<?= APP_URL ?>/pages/create-listing.php">разместите своё объявление</a></p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>