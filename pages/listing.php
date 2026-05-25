<?php
// pages/listing.php — Страница объявления

require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/pages/listings.php');

// Получить объявление
$stmt = db()->prepare("
    SELECT l.*,
           u.id as uid, u.full_name, u.username, u.avatar, u.rating, u.rating_count, u.bio, u.city, u.is_verified, u.created_at as user_since,
           c.name as cat_name, c.slug as cat_slug, c.icon as cat_icon
    FROM listings l
    JOIN users u ON u.id = l.user_id
    JOIN categories c ON c.id = l.category_id
    WHERE l.id=? AND l.status != 'deleted'
");
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    die('Объявление не найдено.');
}

// Увеличить счётчик просмотров
db()->prepare("UPDATE listings SET views=views+1 WHERE id=?")->execute([$id]);

// Изображения
$images = db()->prepare("SELECT * FROM listing_images WHERE listing_id=? ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

// Теги
$tags = db()->prepare("
    SELECT t.name, t.slug FROM tags t
    JOIN listing_tags lt ON lt.tag_id=t.id
    WHERE lt.listing_id=?
");
$tags->execute([$id]);
$tags = $tags->fetchAll();

// Отзывы об авторе
$reviews = db()->prepare("
    SELECT r.*, u.full_name, u.username, u.avatar
    FROM reviews r
    JOIN users u ON u.id = r.from_user_id
    WHERE r.to_user_id=?
    ORDER BY r.created_at DESC LIMIT 5
");
$reviews->execute([$listing['uid']]);
$reviews = $reviews->fetchAll();

// Похожие объявления
$similar = db()->prepare("
    SELECT l.*, u.full_name, u.avatar, u.username, u.rating,
           c.name as cat_name,
           (SELECT filename FROM listing_images WHERE listing_id=l.id ORDER BY sort_order LIMIT 1) as image
    FROM listings l
    JOIN users u ON u.id=l.user_id
    JOIN categories c ON c.id=l.category_id
    WHERE l.category_id=? AND l.id!=? AND l.status='active'
    ORDER BY RAND() LIMIT 4
");
$similar->execute([$listing['category_id'], $id]);
$similar = $similar->fetchAll();

// Проверить избранное
$currentUser = currentUser();
$isFav = false;
$alreadyRequested = false;
if ($currentUser) {
    $favCheck = db()->prepare("SELECT 1 FROM favorites WHERE user_id=? AND listing_id=?");
    $favCheck->execute([$currentUser['id'], $id]);
    $isFav = (bool)$favCheck->fetchColumn();

    $reqCheck = db()->prepare("SELECT 1 FROM exchange_requests WHERE listing_id=? AND from_user_id=? AND status NOT IN ('rejected','cancelled')");
    $reqCheck->execute([$id, $currentUser['id']]);
    $alreadyRequested = (bool)$reqCheck->fetchColumn();
}

$pageTitle = $listing['title'];
include __DIR__ . '/../includes/header.php';
?>

<div class="layout-sidebar" style="--sidebar-w:300px;">
    <!-- Основной контент -->
    <main>
        <!-- Хлебные крошки -->
        <nav style="font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;">
            <a href="<?= APP_URL ?>/">Главная</a> /
            <a href="<?= APP_URL ?>/pages/listings.php">Объявления</a> /
            <a href="<?= APP_URL ?>/pages/listings.php?category=<?= e($listing['cat_slug']) ?>">
                <?= e($listing['cat_name']) ?>
            </a> /
            <span><?= e(truncate($listing['title'], 40)) ?></span>
        </nav>

        <!-- Изображения -->
        <?php if ($images): ?>
        <div style="margin-bottom:24px;">
            <img src="<?= UPLOAD_URL . e($images[0]['filename']) ?>" alt="<?= e($listing['title']) ?>"
                 style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius-lg);border:1px solid var(--border);">
            <?php if (count($images) > 1): ?>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
                <?php foreach (array_slice($images, 1) as $img): ?>
                <img src="<?= UPLOAD_URL . e($img['filename']) ?>" alt=""
                     style="width:80px;height:60px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Заголовок -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                    <span class="listing-type-badge <?= $listing['type']==='offer' ? 'badge-offer' : 'badge-request' ?>">
                        <?= $listing['type'] === 'offer' ? 'Предложение' : 'Запрос' ?>
                    </span>
                    <a href="<?= APP_URL ?>/pages/listings.php?category=<?= e($listing['cat_slug']) ?>" class="tag">
                        <i class="ti <?= e($listing['cat_icon']) ?>"></i> <?= e($listing['cat_name']) ?>
                    </a>
                    <?php if ($listing['is_featured']): ?>
                    <span class="listing-type-badge badge-featured"><i class="ti ti-star"></i> Топ</span>
                    <?php endif; ?>
                </div>
                <h1 style="font-size:1.5rem;font-family:var(--font-display);line-height:1.3;"><?= e($listing['title']) ?></h1>
            </div>
            <?php if ($currentUser): ?>
            <button class="favorite-btn" data-id="<?= $listing['id'] ?>"
                    style="background:none;border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;color:<?= $isFav ? '#ef4444' : 'var(--text-muted)' ?>;font-size:1rem;cursor:pointer;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="ti <?= $isFav ? 'ti-heart-filled' : 'ti-heart' ?>"></i>
                <?= $isFav ? 'В избранном' : 'В избранное' ?>
            </button>
            <?php endif; ?>
        </div>

        <!-- Цена и условия -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                    <?php if ($listing['price']): ?>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Цена</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--primary);">
                            <?= formatPrice($listing['price'], $listing['currency'] ?? 'RUB') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($listing['exchange_type'] !== 'paid'): ?>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Обмен</div>
                        <div style="font-size:0.95rem;color:#065f46;display:flex;align-items:center;gap:6px;">
                            <i class="ti ti-arrows-exchange"></i>
                            <?= $listing['skill_wanted'] ? e($listing['skill_wanted']) : 'Возможен' ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Формат</div>
                        <div style="font-size:0.88rem;">
                            <?= match($listing['exchange_type']) {
                                'skill_swap' => '🤝 Только обмен навыками',
                                'paid'       => '💳 Только за оплату',
                                default      => '🤝 Обмен или оплата',
                            } ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Просмотров</div>
                        <div style="font-size:0.88rem;"><i class="ti ti-eye"></i> <?= $listing['views'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Описание -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;">Описание</h2>
                <div style="font-size:0.9rem;line-height:1.8;color:var(--text-secondary);">
                    <?= nl2br(e($listing['description'])) ?>
                </div>

                <?php if ($tags): ?>
                <div class="tags-list" style="margin-top:18px;">
                    <?php foreach ($tags as $tag): ?>
                    <a href="<?= APP_URL ?>/pages/search.php?q=<?= urlencode($tag['name']) ?>" class="tag"><?= e($tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Отзывы -->
        <?php if ($reviews): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
                    Отзывы об исполнителе (<?= count($reviews) ?>)
                </h2>
                <?php foreach ($reviews as $rev): ?>
                <div style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);">
                    <img src="<?= avatarUrl($rev['avatar']) ?>" alt=""
                         style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                            <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $rev['from_user_id'] ?>" style="font-weight:600;font-size:0.88rem;">
                                <?= e($rev['full_name']) ?>
                            </a>
                            <?= stars($rev['rating']) ?>
                            <span style="font-size:0.78rem;color:var(--text-muted);"><?= formatDate($rev['created_at']) ?></span>
                        </div>
                        <?php if ($rev['comment']): ?>
                        <p style="font-size:0.88rem;color:var(--text-secondary);margin:0;"><?= e($rev['comment']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Похожие -->
        <?php if ($similar): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Похожие объявления</h2>
            </div>
            <div class="grid-4" style="grid-template-columns:repeat(2,1fr);">
                <?php foreach ($similar as $l): ?>
                    <?php include __DIR__ . '/../includes/listing-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Боковая панель -->
    <aside class="sidebar">
        <!-- Автор -->
        <div class="sidebar-widget">
            <div style="text-align:center;margin-bottom:16px;">
                <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $listing['uid'] ?>">
                    <img src="<?= avatarUrl($listing['avatar']) ?>" alt="<?= e($listing['full_name']) ?>"
                         style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;border:2px solid var(--primary-light);">
                </a>
                <div style="font-weight:700;margin-bottom:2px;">
                    <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $listing['uid'] ?>" style="color:inherit;">
                        <?= e($listing['full_name']) ?>
                    </a>
                    <?php if ($listing['is_verified']): ?>
                    <span class="verified-badge"><i class="ti ti-check"></i> Верифицирован</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.8rem;color:var(--text-muted);">@<?= e($listing['username']) ?></div>
                <?php if ($listing['city']): ?>
                <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;">
                    <i class="ti ti-map-pin"></i> <?= e($listing['city']) ?>
                </div>
                <?php endif; ?>
                <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;">
                    <?= stars($listing['rating']) ?>
                    <span class="rating-text"><?= number_format($listing['rating'], 1) ?> (<?= $listing['rating_count'] ?> отзывов)</span>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                    На платформе с <?= formatDateFull($listing['user_since']) ?>
                </div>
            </div>

            <?php if ($currentUser && $currentUser['id'] !== $listing['uid']): ?>
                <?php if ($alreadyRequested): ?>
                    <div class="alert alert-info" style="text-align:center;margin-bottom:0;">
                        <i class="ti ti-check"></i> Вы уже отправили заявку
                    </div>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/pages/send-request.php?listing_id=<?= $listing['id'] ?>"
                       class="btn btn-primary btn-block" style="margin-bottom:10px;">
                        <i class="ti ti-send"></i> Отправить заявку
                    </a>
                    <a href="<?= APP_URL ?>/pages/messages.php?to=<?= $listing['uid'] ?>&listing=<?= $listing['id'] ?>"
                       class="btn btn-secondary btn-block">
                        <i class="ti ti-message-circle"></i> Написать сообщение
                    </a>
                <?php endif; ?>
            <?php elseif (!$currentUser): ?>
                <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary btn-block">
                    <i class="ti ti-login"></i> Войти для связи
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/pages/edit-listing.php?id=<?= $listing['id'] ?>" class="btn btn-secondary btn-block">
                    <i class="ti ti-edit"></i> Редактировать
                </a>
            <?php endif; ?>
        </div>

        <!-- Мета -->
        <div class="sidebar-widget">
            <div style="display:flex;flex-direction:column;gap:10px;font-size:0.85rem;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Опубликовано</span>
                    <span><?= formatDateFull($listing['created_at']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Обновлено</span>
                    <span><?= formatDate($listing['updated_at']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">ID</span>
                    <span>#<?= $listing['id'] ?></span>
                </div>
            </div>
        </div>

        <!-- Пожаловаться -->
        <?php if ($currentUser && $currentUser['id'] !== $listing['uid']): ?>
        <div style="text-align:center;">
            <a href="<?= APP_URL ?>/pages/report.php?listing_id=<?= $listing['id'] ?>" style="font-size:0.78rem;color:var(--text-muted);">
                <i class="ti ti-flag"></i> Пожаловаться на объявление
            </a>
        </div>
        <?php endif; ?>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?><?php
// pages/listing.php — Страница объявления

require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/pages/listings.php');

// Получить объявление
$stmt = db()->prepare("
    SELECT l.*,
           u.id as uid, u.full_name, u.username, u.avatar, u.rating, u.rating_count, u.bio, u.city, u.is_verified, u.created_at as user_since,
           c.name as cat_name, c.slug as cat_slug, c.icon as cat_icon
    FROM listings l
    JOIN users u ON u.id = l.user_id
    JOIN categories c ON c.id = l.category_id
    WHERE l.id=? AND l.status != 'deleted'
");
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    die('Объявление не найдено.');
}

// Увеличить счётчик просмотров
db()->prepare("UPDATE listings SET views=views+1 WHERE id=?")->execute([$id]);

// Изображения
$images = db()->prepare("SELECT * FROM listing_images WHERE listing_id=? ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

// Теги
$tags = db()->prepare("
    SELECT t.name, t.slug FROM tags t
    JOIN listing_tags lt ON lt.tag_id=t.id
    WHERE lt.listing_id=?
");
$tags->execute([$id]);
$tags = $tags->fetchAll();

// Отзывы об авторе
$reviews = db()->prepare("
    SELECT r.*, u.full_name, u.username, u.avatar
    FROM reviews r
    JOIN users u ON u.id = r.from_user_id
    WHERE r.to_user_id=?
    ORDER BY r.created_at DESC LIMIT 5
");
$reviews->execute([$listing['uid']]);
$reviews = $reviews->fetchAll();

// Похожие объявления
$similar = db()->prepare("
    SELECT l.*, u.full_name, u.avatar, u.username, u.rating,
           c.name as cat_name,
           (SELECT filename FROM listing_images WHERE listing_id=l.id ORDER BY sort_order LIMIT 1) as image
    FROM listings l
    JOIN users u ON u.id=l.user_id
    JOIN categories c ON c.id=l.category_id
    WHERE l.category_id=? AND l.id!=? AND l.status='active'
    ORDER BY RAND() LIMIT 4
");
$similar->execute([$listing['category_id'], $id]);
$similar = $similar->fetchAll();

// Проверить избранное
$currentUser = currentUser();
$isFav = false;
$alreadyRequested = false;
if ($currentUser) {
    $favCheck = db()->prepare("SELECT 1 FROM favorites WHERE user_id=? AND listing_id=?");
    $favCheck->execute([$currentUser['id'], $id]);
    $isFav = (bool)$favCheck->fetchColumn();

    $reqCheck = db()->prepare("SELECT 1 FROM exchange_requests WHERE listing_id=? AND from_user_id=? AND status NOT IN ('rejected','cancelled')");
    $reqCheck->execute([$id, $currentUser['id']]);
    $alreadyRequested = (bool)$reqCheck->fetchColumn();
}

$pageTitle = $listing['title'];
include __DIR__ . '/../includes/header.php';
?>

<div class="layout-sidebar" style="--sidebar-w:300px;">
    <!-- Основной контент -->
    <main>
        <!-- Хлебные крошки -->
        <nav style="font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;">
            <a href="<?= APP_URL ?>/">Главная</a> /
            <a href="<?= APP_URL ?>/pages/listings.php">Объявления</a> /
            <a href="<?= APP_URL ?>/pages/listings.php?category=<?= e($listing['cat_slug']) ?>">
                <?= e($listing['cat_name']) ?>
            </a> /
            <span><?= e(truncate($listing['title'], 40)) ?></span>
        </nav>

        <!-- Изображения -->
        <?php if ($images): ?>
        <div style="margin-bottom:24px;">
            <img src="<?= UPLOAD_URL . e($images[0]['filename']) ?>" alt="<?= e($listing['title']) ?>"
                 style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius-lg);border:1px solid var(--border);">
            <?php if (count($images) > 1): ?>
            <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
                <?php foreach (array_slice($images, 1) as $img): ?>
                <img src="<?= UPLOAD_URL . e($img['filename']) ?>" alt=""
                     style="width:80px;height:60px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Заголовок -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                    <span class="listing-type-badge <?= $listing['type']==='offer' ? 'badge-offer' : 'badge-request' ?>">
                        <?= $listing['type'] === 'offer' ? 'Предложение' : 'Запрос' ?>
                    </span>
                    <a href="<?= APP_URL ?>/pages/listings.php?category=<?= e($listing['cat_slug']) ?>" class="tag">
                        <i class="ti <?= e($listing['cat_icon']) ?>"></i> <?= e($listing['cat_name']) ?>
                    </a>
                    <?php if ($listing['is_featured']): ?>
                    <span class="listing-type-badge badge-featured"><i class="ti ti-star"></i> Топ</span>
                    <?php endif; ?>
                </div>
                <h1 style="font-size:1.5rem;font-family:var(--font-display);line-height:1.3;"><?= e($listing['title']) ?></h1>
            </div>
            <?php if ($currentUser): ?>
            <button class="favorite-btn" data-id="<?= $listing['id'] ?>"
                    style="background:none;border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;color:<?= $isFav ? '#ef4444' : 'var(--text-muted)' ?>;font-size:1rem;cursor:pointer;flex-shrink:0;display:flex;align-items:center;gap:6px;">
                <i class="ti <?= $isFav ? 'ti-heart-filled' : 'ti-heart' ?>"></i>
                <?= $isFav ? 'В избранном' : 'В избранное' ?>
            </button>
            <?php endif; ?>
        </div>

        <!-- Цена и условия -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
                    <?php if ($listing['price']): ?>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Цена</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--primary);">
                            <?= formatPrice($listing['price'], $listing['currency'] ?? 'RUB') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($listing['exchange_type'] !== 'paid'): ?>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Обмен</div>
                        <div style="font-size:0.95rem;color:#065f46;display:flex;align-items:center;gap:6px;">
                            <i class="ti ti-arrows-exchange"></i>
                            <?= $listing['skill_wanted'] ? e($listing['skill_wanted']) : 'Возможен' ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Формат</div>
                        <div style="font-size:0.88rem;">
                            <?= match($listing['exchange_type']) {
                                'skill_swap' => '🤝 Только обмен навыками',
                                'paid'       => '💳 Только за оплату',
                                default      => '🤝 Обмен или оплата',
                            } ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;">Просмотров</div>
                        <div style="font-size:0.88rem;"><i class="ti ti-eye"></i> <?= $listing['views'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Описание -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;">Описание</h2>
                <div style="font-size:0.9rem;line-height:1.8;color:var(--text-secondary);">
                    <?= nl2br(e($listing['description'])) ?>
                </div>

                <?php if ($tags): ?>
                <div class="tags-list" style="margin-top:18px;">
                    <?php foreach ($tags as $tag): ?>
                    <a href="<?= APP_URL ?>/pages/search.php?q=<?= urlencode($tag['name']) ?>" class="tag"><?= e($tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Отзывы -->
        <?php if ($reviews): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:16px;">
                    Отзывы об исполнителе (<?= count($reviews) ?>)
                </h2>
                <?php foreach ($reviews as $rev): ?>
                <div style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);">
                    <img src="<?= avatarUrl($rev['avatar']) ?>" alt=""
                         style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                            <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $rev['from_user_id'] ?>" style="font-weight:600;font-size:0.88rem;">
                                <?= e($rev['full_name']) ?>
                            </a>
                            <?= stars($rev['rating']) ?>
                            <span style="font-size:0.78rem;color:var(--text-muted);"><?= formatDate($rev['created_at']) ?></span>
                        </div>
                        <?php if ($rev['comment']): ?>
                        <p style="font-size:0.88rem;color:var(--text-secondary);margin:0;"><?= e($rev['comment']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Похожие -->
        <?php if ($similar): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Похожие объявления</h2>
            </div>
            <div class="grid-4" style="grid-template-columns:repeat(2,1fr);">
                <?php foreach ($similar as $l): ?>
                    <?php include __DIR__ . '/../includes/listing-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Боковая панель -->
    <aside class="sidebar">
        <!-- Автор -->
        <div class="sidebar-widget">
            <div style="text-align:center;margin-bottom:16px;">
                <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $listing['uid'] ?>">
                    <img src="<?= avatarUrl($listing['avatar']) ?>" alt="<?= e($listing['full_name']) ?>"
                         style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;border:2px solid var(--primary-light);">
                </a>
                <div style="font-weight:700;margin-bottom:2px;">
                    <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $listing['uid'] ?>" style="color:inherit;">
                        <?= e($listing['full_name']) ?>
                    </a>
                    <?php if ($listing['is_verified']): ?>
                    <span class="verified-badge"><i class="ti ti-check"></i> Верифицирован</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.8rem;color:var(--text-muted);">@<?= e($listing['username']) ?></div>
                <?php if ($listing['city']): ?>
                <div style="font-size:0.82rem;color:var(--text-muted);margin-top:4px;">
                    <i class="ti ti-map-pin"></i> <?= e($listing['city']) ?>
                </div>
                <?php endif; ?>
                <div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;">
                    <?= stars($listing['rating']) ?>
                    <span class="rating-text"><?= number_format($listing['rating'], 1) ?> (<?= $listing['rating_count'] ?> отзывов)</span>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                    На платформе с <?= formatDateFull($listing['user_since']) ?>
                </div>
            </div>

            <?php if ($currentUser && $currentUser['id'] !== $listing['uid']): ?>
                <?php if ($alreadyRequested): ?>
                    <div class="alert alert-info" style="text-align:center;margin-bottom:0;">
                        <i class="ti ti-check"></i> Вы уже отправили заявку
                    </div>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/pages/send-request.php?listing_id=<?= $listing['id'] ?>"
                       class="btn btn-primary btn-block" style="margin-bottom:10px;">
                        <i class="ti ti-send"></i> Отправить заявку
                    </a>
                    <a href="<?= APP_URL ?>/pages/messages.php?to=<?= $listing['uid'] ?>&listing=<?= $listing['id'] ?>"
                       class="btn btn-secondary btn-block">
                        <i class="ti ti-message-circle"></i> Написать сообщение
                    </a>
                <?php endif; ?>
            <?php elseif (!$currentUser): ?>
                <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-primary btn-block">
                    <i class="ti ti-login"></i> Войти для связи
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/pages/edit-listing.php?id=<?= $listing['id'] ?>" class="btn btn-secondary btn-block">
                    <i class="ti ti-edit"></i> Редактировать
                </a>
            <?php endif; ?>
        </div>

        <!-- Мета -->
        <div class="sidebar-widget">
            <div style="display:flex;flex-direction:column;gap:10px;font-size:0.85rem;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Опубликовано</span>
                    <span><?= formatDateFull($listing['created_at']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">Обновлено</span>
                    <span><?= formatDate($listing['updated_at']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-muted);">ID</span>
                    <span>#<?= $listing['id'] ?></span>
                </div>
            </div>
        </div>

        <!-- Пожаловаться -->
        <?php if ($currentUser && $currentUser['id'] !== $listing['uid']): ?>
        <div style="text-align:center;">
            <a href="<?= APP_URL ?>/pages/report.php?listing_id=<?= $listing['id'] ?>" style="font-size:0.78rem;color:var(--text-muted);">
                <i class="ti ti-flag"></i> Пожаловаться на объявление
            </a>
        </div>
        <?php endif; ?>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>