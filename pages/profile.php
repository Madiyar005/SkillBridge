<?php
// pages/profile.php — Профиль пользователя

require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/');

$stmt = db()->prepare("SELECT * FROM users WHERE id=? AND is_active=1");
$stmt->execute([$id]);
$profile = $stmt->fetch();
if (!$profile) { http_response_code(404); die('Пользователь не найден.'); }

// Его объявления
$listings = db()->prepare("
    SELECT l.*, c.name as cat_name, c.slug as cat_slug, c.icon as cat_icon,
           (SELECT filename FROM listing_images WHERE listing_id=l.id ORDER BY sort_order LIMIT 1) as image
    FROM listings l JOIN categories c ON c.id=l.category_id
    WHERE l.user_id=? AND l.status='active'
    ORDER BY l.created_at DESC LIMIT 9
");
$listings->execute([$id]);
$listings = $listings->fetchAll();

// Отзывы
$reviews = db()->prepare("
    SELECT r.*, u.full_name, u.avatar, u.username
    FROM reviews r JOIN users u ON u.id=r.from_user_id
    WHERE r.to_user_id=?
    ORDER BY r.created_at DESC LIMIT 10
");
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

// Распределение рейтинга
$ratingDist = db()->prepare("
    SELECT rating, COUNT(*) as cnt FROM reviews WHERE to_user_id=? GROUP BY rating ORDER BY rating DESC
");
$ratingDist->execute([$id]);
$ratingDist = $ratingDist->fetchAll(PDO::FETCH_KEY_PAIR);

// Кол-во успешных обменов
$completedCount = db()->prepare("
    SELECT COUNT(*) FROM exchange_requests
    WHERE (from_user_id=? OR to_user_id=?) AND status='completed'
");
$completedCount->execute([$id, $id]);
$completedCount = (int)$completedCount->fetchColumn();

$currentUser = currentUser();
$isOwn = $currentUser && $currentUser['id'] === $id;

$pageTitle = $profile['full_name'] . ' — профиль';
include __DIR__ . '/../includes/header.php';
?>

<!-- Профиль -->
<div class="profile-header">
    <img src="<?= avatarUrl($profile['avatar']) ?>" alt="<?= e($profile['full_name']) ?>" class="profile-avatar">
    <div class="profile-info">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <h1 class="profile-name">
                <?= e($profile['full_name']) ?>
                <?php if ($profile['is_verified']): ?>
                <span class="verified-badge"><i class="ti ti-check"></i></span>
                <?php endif; ?>
            </h1>
        </div>
        <div class="profile-username">@<?= e($profile['username']) ?></div>
        <?php if ($profile['city']): ?>
        <div style="font-size:0.88rem;color:var(--text-muted);margin-top:4px;">
            <i class="ti ti-map-pin"></i> <?= e($profile['city']) ?>
        </div>
        <?php endif; ?>
        <?php if ($profile['bio']): ?>
        <p style="margin-top:12px;font-size:0.9rem;color:var(--text-secondary);max-width:600px;line-height:1.7;">
            <?= nl2br(e($profile['bio'])) ?>
        </p>
        <?php endif; ?>
        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-value"><?= count($listings) ?>+</span>
                <span class="stat-label">Объявлений</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?= $completedCount ?></span>
                <span class="stat-label">Обменов</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?= number_format($profile['rating'], 1) ?></span>
                <span class="stat-label">Рейтинг</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?= $profile['rating_count'] ?></span>
                <span class="stat-label">Отзывов</span>
            </div>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;flex-shrink:0;">
        <?php if ($isOwn): ?>
            <a href="<?= APP_URL ?>/pages/settings.php" class="btn btn-secondary btn-sm"><i class="ti ti-edit"></i> Редактировать</a>
            <a href="<?= APP_URL ?>/pages/dashboard.php" class="btn btn-primary btn-sm"><i class="ti ti-layout-dashboard"></i> Кабинет</a>
        <?php elseif ($currentUser): ?>
            <a href="<?= APP_URL ?>/pages/messages.php?to=<?= $id ?>" class="btn btn-primary btn-sm">
                <i class="ti ti-message-circle"></i> Написать
            </a>
        <?php else: ?>
            <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary btn-sm"><i class="ti ti-login"></i> Войти</a>
        <?php endif; ?>
        <div style="font-size:0.78rem;color:var(--text-muted);text-align:center;">
            На платформе с <?= formatDateFull($profile['created_at']) ?>
        </div>
    </div>
</div>

<!-- Объявления -->
<div class="section">
    <div class="section-header">
        <h2 class="section-title">Объявления</h2>
    </div>
    <?php if ($listings): ?>
    <div class="grid-3">
        <?php foreach ($listings as $l): ?>
            <?php include __DIR__ . '/../includes/listing-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="ti ti-list"></i><h3>Объявлений пока нет</h3></div>
    <?php endif; ?>
</div>

<!-- Отзывы -->
<div class="section">
    <div class="section-header">
        <h2 class="section-title">Отзывы (<?= count($reviews) ?>)</h2>
        <?= stars($profile['rating']) ?>
        <span class="rating-text"><?= number_format($profile['rating'], 1) ?> из 5</span>
    </div>

    <?php if ($ratingDist): ?>
    <div class="card" style="margin-bottom:24px;max-width:400px;">
        <div class="card-body">
            <?php for ($s = 5; $s >= 1; $s--): ?>
            <?php $cnt = $ratingDist[$s] ?? 0; $pct = $profile['rating_count'] > 0 ? round($cnt / $profile['rating_count'] * 100) : 0; ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <span style="font-size:0.8rem;white-space:nowrap;width:20px;text-align:right;"><?= $s ?> <i class="ti ti-star-filled" style="color:var(--accent);font-size:0.75rem;"></i></span>
                <div style="flex:1;height:8px;background:var(--bg-muted);border-radius:99px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:var(--accent);border-radius:99px;"></div>
                </div>
                <span style="font-size:0.8rem;width:30px;color:var(--text-muted);"><?= $cnt ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($reviews): ?>
    <div class="grid-2">
        <?php foreach ($reviews as $rev): ?>
        <div class="card">
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <img src="<?= avatarUrl($rev['avatar']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                    <div style="flex:1;">
                        <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $rev['from_user_id'] ?>" style="font-weight:600;font-size:0.9rem;color:inherit;"><?= e($rev['full_name']) ?></a>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <?= stars($rev['rating']) ?>
                            <span style="font-size:0.75rem;color:var(--text-muted);"><?= formatDateFull($rev['created_at']) ?></span>
                        </div>
                    </div>
                </div>
                <?php if ($rev['comment']): ?>
                <p style="font-size:0.88rem;color:var(--text-secondary);line-height:1.6;margin:0;"><?= e($rev['comment']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><i class="ti ti-star"></i><h3>Отзывов пока нет</h3></div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>