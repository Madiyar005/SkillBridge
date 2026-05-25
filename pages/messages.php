<?php
// pages/messages.php — Чат / Сообщения

require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$me = currentUser();
$toUserId = (int)($_GET['to'] ?? 0);
$listingId = (int)($_GET['listing'] ?? 0);

// Список диалогов (уникальные собеседники)
$threads = db()->prepare("
    SELECT
        CASE WHEN m.from_user_id = :uid THEN m.to_user_id ELSE m.from_user_id END AS partner_id,
        u.full_name, u.avatar, u.username,
        MAX(m.id) as last_msg_id,
        (SELECT body FROM messages WHERE id = MAX(m.id)) as last_body,
        (SELECT created_at FROM messages WHERE id = MAX(m.id)) as last_time,
        SUM(CASE WHEN m.to_user_id = :uid AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.from_user_id = :uid THEN m.to_user_id ELSE m.from_user_id END
    WHERE m.from_user_id = :uid OR m.to_user_id = :uid
    GROUP BY partner_id
    ORDER BY last_msg_id DESC
");
$threads->execute([':uid' => $me['id']]);
$threads = $threads->fetchAll();

// Активный собеседник
$partner = null;
$messages = [];

if ($toUserId) {
    $pStmt = db()->prepare("SELECT * FROM users WHERE id=? AND is_active=1");
    $pStmt->execute([$toUserId]);
    $partner = $pStmt->fetch();

    if ($partner) {
        // Пометить сообщения прочитанными
        db()->prepare("UPDATE messages SET is_read=1 WHERE from_user_id=? AND to_user_id=?")
           ->execute([$toUserId, $me['id']]);

        // Загрузить сообщения
        $msgStmt = db()->prepare("
            SELECT m.*, u.full_name, u.avatar
            FROM messages m JOIN users u ON u.id=m.from_user_id
            WHERE (m.from_user_id=? AND m.to_user_id=?) OR (m.from_user_id=? AND m.to_user_id=?)
            ORDER BY m.created_at ASC LIMIT " . MESSAGES_PER_PAGE
        );
        $msgStmt->execute([$me['id'], $toUserId, $toUserId, $me['id']]);
        $messages = $msgStmt->fetchAll();
    }
}

// Отправка сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partner) {
    verifyCsrf();
    $body = trim($_POST['body'] ?? '');
    if ($body && mb_strlen($body) <= 5000) {
        db()->prepare("INSERT INTO messages (from_user_id, to_user_id, body) VALUES (?,?,?)")
           ->execute([$me['id'], $toUserId, $body]);
        // Уведомление
        sendNotification(
            $toUserId, 'new_message',
            'Новое сообщение от ' . $me['full_name'],
            truncate($body, 80),
            '/pages/messages.php?to=' . $me['id']
        );
        redirect('/pages/messages.php?to=' . $toUserId);
    }
}

$pageTitle = $partner ? 'Чат с ' . $partner['full_name'] : 'Сообщения';
include __DIR__ . '/../includes/header.php';
?>

<div class="chat-layout">
    <!-- Список диалогов -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <i class="ti ti-messages" style="color:var(--primary);"></i> Сообщения
        </div>
        <?php if ($threads): ?>
            <?php foreach ($threads as $t): ?>
            <a href="?to=<?= $t['partner_id'] ?>" class="chat-thread <?= $toUserId === (int)$t['partner_id'] ? 'active' : '' ?>">
                <img src="<?= avatarUrl($t['avatar']) ?>" alt="<?= e($t['full_name']) ?>">
                <div class="chat-thread-info">
                    <div class="chat-thread-name"><?= e($t['full_name']) ?></div>
                    <div class="chat-thread-preview"><?= e(truncate($t['last_body'], 40)) ?></div>
                </div>
                <div class="chat-thread-meta">
                    <span class="chat-thread-time"><?= formatDate($t['last_time']) ?></span>
                    <?php if ($t['unread_count'] > 0): ?>
                    <span class="badge" style="position:static;"><?= $t['unread_count'] ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:32px 16px;">
                <i class="ti ti-messages" style="font-size:2rem;"></i>
                <p style="font-size:0.85rem;">Нет диалогов</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Основная область чата -->
    <div class="chat-main">
        <?php if ($partner): ?>
        <!-- Шапка чата -->
        <div class="chat-header">
            <img src="<?= avatarUrl($partner['avatar']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
            <div style="flex:1;">
                <div style="font-weight:700;">
                    <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $partner['id'] ?>" style="color:inherit;"><?= e($partner['full_name']) ?></a>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);">@<?= e($partner['username']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $partner['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="ti ti-user"></i> Профиль
            </a>
        </div>

        <!-- Сообщения -->
        <div class="chat-messages" id="chatMessages" data-exchange-id="">
            <?php if ($messages): ?>
                <?php foreach ($messages as $msg): ?>
                <div class="message-bubble <?= $msg['from_user_id'] == $me['id'] ? 'mine' : 'theirs' ?>" data-id="<?= $msg['id'] ?>">
                    <div class="message-text"><?= nl2br(e($msg['body'])) ?></div>
                    <span class="message-time"><?= formatDate($msg['created_at']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center;color:var(--text-muted);padding:40px;font-size:0.88rem;">
                    <i class="ti ti-message-circle" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                    Начните диалог с <?= e($partner['full_name']) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Форма отправки -->
        <div class="chat-input">
            <form method="POST" style="display:flex;gap:10px;width:100%;" id="msgForm">
                <?= csrfField() ?>
                <textarea name="body" placeholder="Написать сообщение..." rows="1"
                          data-autoresize required style="flex:1;resize:none;border:1px solid var(--border);border-radius:var(--radius);padding:10px 14px;font-size:0.9rem;font-family:var(--font);outline:none;"
                          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-send"></i>
                </button>
            </form>
        </div>

        <?php else: ?>
        <!-- Заглушка если диалог не выбран -->
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);text-align:center;padding:40px;">
            <i class="ti ti-message-circle-2" style="font-size:4rem;margin-bottom:16px;color:var(--primary-light);"></i>
            <h3 style="font-size:1.1rem;margin-bottom:8px;">Выберите диалог</h3>
            <p style="font-size:0.88rem;">или откройте профиль пользователя, чтобы начать переписку</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>