<?php
// includes/listing-card.php — Карточка объявления (переменная $l)
// Используется: index.php, listings.php, search.php, profile.php и т.д.

$user = currentUser();
$isFav = false;
if ($user) {
    $favCheck = db()->prepare("SELECT 1 FROM favorites WHERE user_id=? AND listing_id=?");
    $favCheck->execute([$user['id'], $l['id']]);
    $isFav = (bool)$favCheck->fetchColumn();
}
?>
<div class="card listing-card">
    <?php if (!empty($l['image'])): ?>
    <img src="<?= UPLOAD_URL . e($l['image']) ?>" alt="<?= e($l['title']) ?>" class="card-img" loading="lazy">
    <?php else: ?>
    <div class="card-img" style="background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:2.5rem;">
        <i class="ti ti-photo"></i>
    </div>
    <?php endif; ?>

    <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span class="listing-type-badge <?= $l['type'] === 'offer' ? 'badge-offer' : 'badge-request' ?>">
                <i class="ti <?= $l['type'] === 'offer' ? 'ti-hand-rock' : 'ti-hand' ?>"></i>
                <?= $l['type'] === 'offer' ? 'Предложение' : 'Запрос' ?>
            </span>
            <?php if ($user): ?>
            <button class="favorite-btn" data-id="<?= $l['id'] ?>" title="<?= $isFav ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                style="background:none;border:none;color:<?= $isFav ? '#ef4444' : 'var(--text-muted)' ?>;font-size:1.1rem;cursor:pointer;padding:4px;">
                <i class="ti <?= $isFav ? 'ti-heart-filled' : 'ti-heart' ?>"></i>
            </button>
            <?php endif; ?>
        </div>

        <h3 class="card-title">
            <a href="<?= APP_URL ?>/pages/listing.php?id=<?= $l['id'] ?>" style="color:inherit;text-decoration:none;">
                <?= e(truncate($l['title'], 60)) ?>
            </a>
        </h3>

        <p class="card-text"><?= e(truncate($l['description'], 100)) ?></p>

        <div class="card-meta">
            <?php if ($l['price']): ?>
            <span class="listing-price"><?= formatPrice($l['price'], $l['currency'] ?? 'RUB') ?></span>
            <?php endif; ?>
            <?php if ($l['exchange_type'] !== 'paid'): ?>
            <span class="listing-swap"><i class="ti ti-arrows-exchange"></i> Обмен возможен</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-footer">
        <div class="listing-author">
            <img src="<?= avatarUrl($l['avatar']) ?>" alt="<?= e($l['full_name']) ?>">
            <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $l['user_id'] ?>"><?= e($l['full_name'] ?: $l['username']) ?></a>
            <?= stars($l['rating']) ?>
        </div>
        <span style="font-size:0.78rem;color:var(--text-muted);">
            <i class="ti ti-eye"></i> <?= $l['views'] ?>
            · <?= formatDate($l['created_at']) ?>
        </span>
    </div>
</div>