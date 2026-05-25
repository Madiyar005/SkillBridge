<?php
// includes/functions.php — Вспомогательные функции

// ── Сессии ──────────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function currentUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return currentUser() !== null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('/pages/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = currentUser();
    $roles = ['user' => 0, 'moderator' => 1, 'admin' => 2];
    if (($roles[$user['role']] ?? 0) < ($roles[$role] ?? 99)) {
        http_response_code(403);
        die('Доступ запрещён.');
    }
}

function loginUser(array $user): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'full_name'=> $user['full_name'],
        'avatar'   => $user['avatar'],
        'role'     => $user['role'],
        'balance'  => $user['balance'],
    ];
}

function logoutUser(): void {
    startSession();
    session_destroy();
}

function refreshSession(): void {
    if (isLoggedIn()) {
        $user = db()->prepare("SELECT id,username,email,full_name,avatar,role,balance FROM users WHERE id=?");
        $user->execute([$_SESSION['user']['id']]);
        $data = $user->fetch();
        if ($data) {
            loginUser($data);
        }
    }
}

// ── Безопасность ─────────────────────────────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf() . '">';
}

// ── Flash-сообщения ────────────────────────────────────────────
function flash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array {
    startSession();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function renderFlashes(): string {
    $html = '';
    foreach (getFlashes() as $f) {
        $type = match($f['type']) {
            'success' => 'success',
            'error'   => 'danger',
            'warning' => 'warning',
            default   => 'info',
        };
        $html .= '<div class="alert alert-' . $type . '" role="alert">' . e($f['message']) . '</div>';
    }
    return $html;
}

// ── Редиректы ─────────────────────────────────────────────────
function redirect(string $path): never {
    header('Location: ' . APP_URL . $path);
    exit;
}

function redirectBack(): never {
    $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL;
    header('Location: ' . $ref);
    exit;
}

// ── Пагинация ─────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

function renderPagination(array $p, string $baseUrl): string {
    if ($p['total_pages'] <= 1) return '';
    $html = '<nav class="pagination-nav" aria-label="Навигация по страницам"><ul class="pagination">';
    if ($p['has_prev']) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($p['current'] - 1) . '">&laquo;</a></li>';
    }
    for ($i = max(1, $p['current'] - 2); $i <= min($p['total_pages'], $p['current'] + 2); $i++) {
        $active = $i === $p['current'] ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }
    if ($p['has_next']) {
        $html .= '<li><a href="' . $baseUrl . '&page=' . ($p['current'] + 1) . '">&raquo;</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// ── Загрузка файлов ───────────────────────────────────────────
function uploadImage(array $file, string $subdir = 'listings'): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) return false;

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };
    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = $subdir . '/' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        return $filename;
    }
    return false;
}

function avatarUrl(?string $avatar): string {
    if ($avatar && file_exists(UPLOAD_DIR . $avatar)) {
        return UPLOAD_URL . $avatar;
    }
    return APP_URL . '/assets/img/default-avatar.png';
}

// ── Форматирование ───────────────────────────────────────────
function formatPrice(float $amount, string $currency = 'RUB'): string {
    $symbols = ['RUB' => '₽', 'USD' => '$', 'EUR' => '€'];
    $sym = $symbols[$currency] ?? $currency;
    return number_format($amount, 0, '.', ' ') . ' ' . $sym;
}

function formatDate(string $date): string {
    $ts = strtotime($date);
    $diff = time() - $ts;
    if ($diff < 60) return 'только что';
    if ($diff < 3600) return (int)($diff / 60) . ' мин. назад';
    if ($diff < 86400) return (int)($diff / 3600) . ' ч. назад';
    if ($diff < 604800) return (int)($diff / 86400) . ' дн. назад';
    return date('d.m.Y', $ts);
}

function formatDateFull(string $date): string {
    $months = ['', 'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function slug(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    $cyr = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
    $lat = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','kh','ts','ch','sh','shch','','y','','e','yu','ya'];
    $str = str_replace($cyr, $lat, $str);
    $str = preg_replace('/[^a-z0-9\-]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

function stars(float $rating, int $max = 5): string {
    $html = '<span class="stars" title="' . number_format($rating, 1) . '">';
    for ($i = 1; $i <= $max; $i++) {
        if ($rating >= $i) $html .= '<i class="ti ti-star-filled star-full"></i>';
        elseif ($rating >= $i - 0.5) $html .= '<i class="ti ti-star-half-filled star-half"></i>';
        else $html .= '<i class="ti ti-star star-empty"></i>';
    }
    $html .= '</span>';
    return $html;
}

function truncate(string $text, int $limit = 120): string {
    if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
    return mb_substr($text, 0, $limit, 'UTF-8') . '…';
}

// ── Уведомления ───────────────────────────────────────────────
function sendNotification(int $userId, string $type, string $title, string $body = '', string $link = ''): void {
    $stmt = db()->prepare("
        INSERT INTO notifications (user_id, type, title, body, link) VALUES (?,?,?,?,?)
    ");
    $stmt->execute([$userId, $type, $title, $body, $link]);
}

function unreadNotificationsCount(int $userId): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function unreadMessagesCount(int $userId): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM messages WHERE to_user_id=? AND is_read=0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}