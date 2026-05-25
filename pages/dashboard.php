<?php
// pages/dashboard.php — Личный кабинет

require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$user = currentUser();
$uid  = $user['id'];

// Статистика
$stats = db()->prepare("
    SELECT
        (SELECT COUNT(*) FROM listings WHERE user_id=? AND status='active') as active_listings,
        (SELECT COUNT(*) FROM exchange_requests WHERE (from_user_id=? OR to_user_id=?) AND status='pending') as pending_requests,
        (SELECT COUNT(*) FROM exchange_requests WHERE (from_user_id=? OR to_user_id=?) AND status='completed') as completed_exchanges,
        (SELECT COUNT(*) FROM reviews WHERE to_user_id=?) as total_reviews
");
$stats->execute([$uid, $uid, $uid, $uid, $uid, $uid]);
$stats = $stats->fetch();

// Мои объявления (последние)
$myListings = db()->prepare("
    SELECT l.*, c.name as cat_name,
           (SELECT COUNT(*) FROM exchange_requests WHERE listing_id=l.id AND status='pending') as pending_req
    FROM listings l JOIN categories c ON c.id=l.category_id
    WHERE l.user_id=? AND l.status!='deleted'
    ORDER BY l.created_at DESC LIMIT 5
");
$myListings->execute([$uid]);
$myListings = $myListings->fetchAll();

// Входящие заявки
$incomingRequests = db()->prepare("
    SELECT er.*, l.title as listing_title, u.full_name, u.avatar, u.username
    FROM exchange_requests er
    JOIN listings l ON l.id=er.listing_id
    JOIN users u ON u.id=er.from_user_id
    WHERE er.to_user_id=? AND er.status='pending'
    ORDER BY er.created_at DESC LIMIT 5
");
$incomingRequests->execute([$uid]);
$incomingRequests = $incomingRequests->fetchAll();

// Мои заявки (исходящие)
$myRequests = db()->prepare("
    SELECT er.*, l.title as listing_title, u.full_name, u.avatar, u.username
    FROM exchange_requests er
    JOIN listings l ON l.id=er.listing_id
    JOIN users u ON u.id=er.to_user_id
    WHERE er.from_user_id=? AND er.status NOT IN ('cancelled')
    ORDER BY er.created_at DESC LIMIT 5
");
$myRequests->execute([$uid]);
$myRequests = $myRequests->fetchAll();

// Последние отзывы
$lastReviews = db()->prepare("
    SELECT r.*, u.full_name, u.avatar, u.username
    FROM reviews r JOIN users u ON u.id=r.from_user_id
    WHERE r.to_user_id=?
    ORDER BY r.created_at DESC LIMIT 3
");
$lastReviews->execute([$uid]);
$lastReviews = $lastReviews->fetchAll();

// Уведомления
$notifications = db()->prepare("
    SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6
");
$notifications->execute([$uid]);
$notifications = $notifications->fetchAll();

// Отметить уведомления прочитанными
db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);

$pageTitle = 'Личный кабинет';
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-family:var(--font-display);font-size:1.6rem;">
            Привет, <?= e($user['full_name'] ?: $user['username']) ?>! 👋
        </h1>
        <p style="color:var(--text-muted);font-size:0.88rem;margin-top:4px;">
            Вот что происходит на вашем аккаунте
        </p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $uid ?>" class="btn btn-secondary btn-sm">
            <i class="ti ti-user"></i> Мой профиль
        </a>
        <a href="<?= APP_URL ?>/pages/create-listing.php" class="btn btn-primary btn-sm">
            <i class="ti ti-plus"></i> Новое объявление
        </a>
    </div>
</div>

<!-- Статы -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-card-icon purple"><i class="ti ti-list"></i></div>
        <div>
            <div class="stat-card-value"><?= $stats['active_listings'] ?></div>
            <div class="stat-card-label">Активных объявлений</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon amber"><i class="ti ti-clock"></i></div>
        <div>
            <div class="stat-card-value"><?= $stats['pending_requests'] ?></div>
            <div class="stat-card-label">Ожидающих заявок</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon green"><i class="ti ti-check"></i></div>
        <div>
            <div class="stat-card-value"><?= $stats['completed_exchanges'] ?></div>
            <div class="stat-card-label">Завершённых обменов</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon blue"><i class="ti ti-star"></i></div>
        <div>
            <div class="stat-card-value"><?= $stats['total_reviews'] ?></div>
            <div class="stat-card-label">Отзывов получено</div>
        </div>
    </div>
</div>

<div class="grid-2" style="align-items:start;">
    <!-- Левая колонка -->
    <div>
        <!-- Входящие заявки -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h2 style="font-size:1rem;font-weight:700;">
                        <i class="ti ti-inbox" style="color:var(--primary);"></i> Входящие заявки
                        <?php if ($stats['pending_requests'] > 0): ?>
                        <span class="pill pill-pending" style="margin-left:6px;"><?= $stats['pending_requests'] ?></span>
                        <?php endif; ?>
                    </h2>
                    <a href="<?= APP_URL ?>/pages/requests.php" style="font-size:0.82rem;color:var(--primary);">Все заявки</a>
                </div>
                <?php if ($incomingRequests): ?>
                    <?php foreach ($incomingRequests as $req): ?>
                    <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--border);">
                        <img src="<?= avatarUrl($req['avatar']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:0.88rem;"><?= e($req['full_name']) ?></div>
                            <div style="font-size:0.8rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                По: <?= e($req['listing_title']) ?>
                            </div>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= formatDate($req['created_at']) ?></div>
                        </div>
                        <div style="display:flex;gap:6px;flex-shrink:0;">
                            <a href="<?= APP_URL ?>/pages/request.php?id=<?= $req['id'] ?>&action=accept"
                               class="btn btn-success btn-sm" onclick="return confirm('Принять заявку?')">
                                <i class="ti ti-check"></i>
                            </a>
                            <a href="<?= APP_URL ?>/pages/request.php?id=<?= $req['id'] ?>&action=reject"
                               class="btn btn-danger btn-sm" onclick="return confirm('Отклонить заявку?')">
                                <i class="ti ti-x"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:24px;">
                        <i class="ti ti-inbox" style="font-size:2rem;"></i>
                        <p>Новых заявок нет</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Мои объявления -->
        <div class="card">
            <div class="card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h2 style="font-size:1rem;font-weight:700;"><i class="ti ti-list" style="color:var(--primary);"></i> Мои объявления</h2>
                    <a href="<?= APP_URL ?>/pages/my-listings.php" style="font-size:0.82rem;color:var(--primary);">Все</a>
                </div>
                <?php if ($myListings): ?>
                    <?php foreach ($myListings as $lst): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
                        <div style="flex:1;min-width:0;">
                            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $lst['id'] ?>" style="font-weight:600;font-size:0.88rem;color:inherit;">
                                <?= e(truncate($lst['title'], 50)) ?>
                            </a>
                            <div style="font-size:0.78rem;color:var(--text-muted);">
                                <?= e($lst['cat_name']) ?> · <?= $lst['views'] ?> просм.
                                <?php if ($lst['pending_req'] > 0): ?>
                                · <span style="color:var(--warning);font-weight:600;"><?= $lst['pending_req'] ?> заявок</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="pill pill-<?= e($lst['status']) ?>"><?= e($lst['status']) ?></span>
                        <a href="<?= APP_URL ?>/pages/edit-listing.php?id=<?= $lst['id'] ?>" style="color:var(--text-muted);font-size:1rem;">
                            <i class="ti ti-edit"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:24px;">
                        <i class="ti ti-plus-circle" style="font-size:2rem;"></i>
                        <p>Объявлений пока нет</p>
                        <a href="<?= APP_URL ?>/pages/create-listing.php" class="btn btn-primary btn-sm" style="margin-top:10px;">Создать объявление</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Правая колонка -->
    <div>
        <!-- Баланс -->
        <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;border:none;">
            <div class="card-body">
                <div style="font-size:0.82rem;opacity:0.8;margin-bottom:8px;">Баланс аккаунта</div>
                <div style="font-size:2rem;font-weight:800;margin-bottom:16px;"><?= formatPrice($user['balance']) ?></div>
                <div style="display:flex;gap:10px;">
                    <a href="<?= APP_URL ?>/pages/topup.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.3);">
                        <i class="ti ti-plus"></i> Пополнить
                    </a>
                    <a href="<?= APP_URL ?>/pages/withdraw.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.3);">
                        <i class="ti ti-arrow-up"></i> Вывести
                    </a>
                </div>
            </div>
        </div>

        <!-- Уведомления -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;"><i class="ti ti-bell" style="color:var(--primary);"></i> Уведомления</h2>
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $n): ?>
                    <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);<?= !$n['is_read'] ? 'background:var(--primary-light);margin:0 -18px;padding:10px 18px;' : '' ?>">
                        <div style="width:8px;height:8px;border-radius:50%;background:<?= !$n['is_read'] ? 'var(--primary)' : 'var(--border)' ?>;margin-top:6px;flex-shrink:0;"></div>
                        <div style="flex:1;min-width:0;">
                            <?php if ($n['link']): ?>
                            <a href="<?= APP_URL . e($n['link']) ?>" style="font-weight:600;font-size:0.85rem;color:inherit;"><?= e($n['title']) ?></a>
                            <?php else: ?>
                            <div style="font-weight:600;font-size:0.85rem;"><?= e($n['title']) ?></div>
                            <?php endif; ?>
                            <?php if ($n['body']): ?>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e(truncate($n['body'], 70)) ?></div>
                            <?php endif; ?>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= formatDate($n['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:16px;">
                        <i class="ti ti-bell-off" style="font-size:1.8rem;"></i>
                        <p style="font-size:0.85rem;">Нет новых уведомлений</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Последние отзывы -->
        <?php if ($lastReviews): ?>
        <div class="card">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;"><i class="ti ti-star" style="color:var(--accent);"></i> Последние отзывы</h2>
                <?php foreach ($lastReviews as $rev): ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <img src="<?= avatarUrl($rev['avatar']) ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                        <span style="font-weight:600;font-size:0.85rem;"><?= e($rev['full_name']) ?></span>
                        <?= stars($rev['rating']) ?>
                    </div>
                    <?php if ($rev['comment']): ?>
                    <p style="font-size:0.82rem;color:var(--text-secondary);margin:0;"><?= e(truncate($rev['comment'], 90)) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>