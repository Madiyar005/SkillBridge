<?php
// index.php — Главная страница SkillSwap

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Обмен навыками и услугами';
$pageDescription = 'Найдите специалиста или предложите свои навыки на платформе SkillSwap';

// Статистика платформы
$stats = db()->query("SELECT
    (SELECT COUNT(*) FROM users WHERE is_active=1) as total_users,
    (SELECT COUNT(*) FROM listings WHERE status='active') as total_listings,
    (SELECT COUNT(*) FROM exchange_requests WHERE status='completed') as total_exchanges
")->fetch();

// Категории
$categories = db()->query("
    SELECT c.*, COUNT(l.id) as listing_count
    FROM categories c
    LEFT JOIN listings l ON l.category_id = c.id AND l.status='active'
    WHERE c.parent_id IS NULL
    GROUP BY c.id ORDER BY c.sort_order LIMIT 12
")->fetchAll();

// Последние объявления
$latest = db()->query("
    SELECT l.*, u.full_name, u.avatar, u.username, u.rating, c.name as cat_name, c.slug as cat_slug
    FROM listings l
    JOIN users u ON u.id = l.user_id
    JOIN categories c ON c.id = l.category_id
    WHERE l.status='active'
    ORDER BY l.created_at DESC LIMIT 8
")->fetchAll();

// Популярные (по просмотрам)
$popular = db()->query("
    SELECT l.*, u.full_name, u.avatar, u.username, u.rating, c.name as cat_name
    FROM listings l
    JOIN users u ON u.id = l.user_id
    JOIN categories c ON c.id = l.category_id
    WHERE l.status='active'
    ORDER BY l.views DESC LIMIT 4
")->fetchAll();

// Топ исполнители
$topUsers = db()->query("
    SELECT u.*, COUNT(l.id) as listing_count
    FROM users u
    LEFT JOIN listings l ON l.user_id = u.id AND l.status='active'
    WHERE u.is_active=1
    GROUP BY u.id ORDER BY u.rating DESC, u.rating_count DESC LIMIT 4
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Обменивайся навыками,<br>развивайся вместе</h1>
            <p>Найди эксперта в нужной области или предложи свои умения. Обмен навыками и платные услуги — всё на одной платформе.</p>
            <form class="hero-search" action="<?= APP_URL ?>/pages/search.php" method="GET">
                <input type="text" name="q" placeholder="Что хотите найти? Например: «урок English», «дизайн логотипа»...">
                <button type="submit"><i class="ti ti-search"></i> Найти</button>
            </form>
            <div class="hero-stats">
                <div class="hero-stat">
                    <strong><?= number_format($stats['total_users']) ?>+</strong>
                    <span>Пользователей</span>
                </div>
                <div class="hero-stat">
                    <strong><?= number_format($stats['total_listings']) ?>+</strong>
                    <span>Объявлений</span>
                </div>
                <div class="hero-stat">
                    <strong><?= number_format($stats['total_exchanges']) ?>+</strong>
                    <span>Обменов завершено</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Категории -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Категории навыков</h2>
        <a href="<?= APP_URL ?>/pages/categories.php" class="section-link">Все категории <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="category-grid">
        <?php foreach ($categories as $cat): ?>
        <a href="<?= APP_URL ?>/pages/listings.php?category=<?= $cat['slug'] ?>" class="category-card">
            <div class="category-icon">
                <i class="ti <?= e($cat['icon']) ?>"></i>
            </div>
            <span><?= e($cat['name']) ?></span>
            <small class="text-muted"><?= $cat['listing_count'] ?> объявл.</small>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Новые объявления -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Свежие объявления</h2>
        <a href="<?= APP_URL ?>/pages/listings.php" class="section-link">Все объявления <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="grid-4">
        <?php foreach ($latest as $l): ?>
        <?php include __DIR__ . '/includes/listing-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- Популярные -->
<?php if ($popular): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">🔥 Популярные</h2>
        <a href="<?= APP_URL ?>/pages/listings.php?sort=views" class="section-link">Посмотреть все <i class="ti ti-arrow-right"></i></a>
    </div>
    <div class="grid-4">
        <?php foreach ($popular as $l): ?>
        <?php include __DIR__ . '/includes/listing-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Как это работает -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">Как работает SkillBridge</h2>
    </div>
    <div class="grid-3">
        <div class="card" style="text-align:center;padding:32px 24px;">
            <div style="width:64px;height:64px;border-radius:var(--radius-lg);background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 20px;">
                <i class="ti ti-user-plus"></i>
            </div>
            <h3 style="margin-bottom:10px;">1. Создайте профиль</h3>
            <p class="card-text">Расскажите о своих навыках, опыте и том, что вам нужно. Добавьте примеры работ.</p>
        </div>
        <div class="card" style="text-align:center;padding:32px 24px;">
            <div style="width:64px;height:64px;border-radius:var(--radius-lg);background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 20px;">
                <i class="ti ti-search"></i>
            </div>
            <h3 style="margin-bottom:10px;">2. Найдите партнёра</h3>
            <p class="card-text">Ищите нужные навыки по категориям. Предлагайте обмен или заказывайте платно.</p>
        </div>
        <div class="card" style="text-align:center;padding:32px 24px;">
            <div style="width:64px;height:64px;border-radius:var(--radius-lg);background:#fef3c7;color:#92400e;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 20px;">
                <i class="ti ti-arrows-exchange"></i>
            </div>
            <h3 style="margin-bottom:10px;">3. Обменивайтесь</h3>
            <p class="card-text">Договоритесь в чате, завершите сделку и оставьте отзыв. Растите вместе!</p>
        </div>
    </div>
    <?php if (!isLoggedIn()): ?>
    <div style="text-align:center;margin-top:28px;">
        <a href="<?= APP_URL ?>/pages/register.php" class="btn btn-primary btn-lg">
            <i class="ti ti-rocket"></i> Начать бесплатно
        </a>
    </div>
    <?php endif; ?>
</section>

<!-- Топ пользователи -->
<?php if ($topUsers): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">⭐ Лучшие участники</h2>
    </div>
    <div class="grid-4">
        <?php foreach ($topUsers as $u): ?>
        <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $u['id'] ?>" class="card" style="text-align:center;padding:24px 20px;text-decoration:none;color:inherit;">
            <img src="<?= avatarUrl($u['avatar']) ?>" alt="<?= e($u['full_name']) ?>"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin:0 auto 12px;border:2px solid var(--primary-light);">
            <div style="font-weight:700;margin-bottom:4px;"><?= e($u['full_name']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:10px;">@<?= e($u['username']) ?></div>
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                <?= stars($u['rating']) ?>
                <span class="rating-text"><?= number_format($u['rating'], 1) ?> (<?= $u['rating_count'] ?>)</span>
            </div>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-top:8px;"><?= $u['listing_count'] ?> объявлений</div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>