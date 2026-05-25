<?php
// includes/header.php — Шапка сайта

$user = currentUser();
$unreadMsgs = $user ? unreadMessagesCount($user['id']) : 0;
$unreadNotifs = $user ? unreadNotificationsCount($user['id']) : 0;
$pageTitle = ($pageTitle ?? APP_NAME);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
    <meta name="description" content="<?= e($pageDescription ?? 'Платформа для обмена навыками и услугами') ?>">

    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/tabler-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= APP_URL ?>assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container">
        <div class="header-inner">
            <!-- Логотип -->
            <a href="<?= APP_URL ?>/" class="logo">
                <span class="logo-icon"><i class="ti ti-arrows-exchange"></i></span>
                <span class="logo-text">Skill<strong>Bridge</strong></span>
            </a>

            <!-- Поиск -->
            <form class="header-search" action="<?= APP_URL ?>/pages/search.php" method="GET">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="text" name="q" placeholder="Найти навык или услугу..."
                       value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
            </form>

            <!-- Навигация -->
            <nav class="header-nav">
                <a href="<?= APP_URL ?>/pages/listings.php" class="nav-link">
                    <i class="ti ti-layout-grid"></i> Объявления
                </a>
                <a href="<?= APP_URL ?>/pages/categories.php" class="nav-link">
                    <i class="ti ti-category"></i> Категории
                </a>

                <?php if ($user): ?>
                    <!-- Сообщения -->
                    <a href="<?= APP_URL ?>/pages/messages.php" class="nav-link nav-icon-link" title="Сообщения">
                        <i class="ti ti-message-circle"></i>
                        <?php if ($unreadMsgs > 0): ?>
                            <span class="badge"><?= $unreadMsgs ?></span>
                        <?php endif; ?>
                    </a>
                    <!-- Уведомления -->
                    <a href="<?= APP_URL ?>/pages/notifications.php" class="nav-link nav-icon-link" title="Уведомления">
                        <i class="ti ti-bell"></i>
                        <?php if ($unreadNotifs > 0): ?>
                            <span class="badge"><?= $unreadNotifs ?></span>
                        <?php endif; ?>
                    </a>
                    <!-- Профиль -->
                    <div class="user-menu-wrap">
                        <button class="user-menu-btn" id="userMenuBtn" aria-expanded="false">
                            <img src="<?= avatarUrl($user['avatar']) ?>" alt="Аватар" class="user-avatar-sm">
                            <span><?= e($user['full_name'] ?: $user['username']) ?></span>
                            <i class="ti ti-chevron-down"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown" role="menu">
                            <div class="dropdown-header">
                                <div class="dropdown-user-name"><?= e($user['full_name']) ?></div>
                                <div class="dropdown-user-balance"><?= formatPrice($user['balance']) ?></div>
                            </div>
                            <a href="<?= APP_URL ?>/pages/profile.php?id=<?= $user['id'] ?>" class="dropdown-item"><i class="ti ti-user"></i> Мой профиль</a>
                            <a href="<?= APP_URL ?>/pages/dashboard.php" class="dropdown-item"><i class="ti ti-layout-dashboard"></i> Личный кабинет</a>
                            <a href="<?= APP_URL ?>/pages/my-listings.php" class="dropdown-item"><i class="ti ti-list"></i> Мои объявления</a>
                            <a href="<?= APP_URL ?>/pages/favorites.php" class="dropdown-item"><i class="ti ti-heart"></i> Избранное</a>
                            <a href="<?= APP_URL ?>/pages/settings.php" class="dropdown-item"><i class="ti ti-settings"></i> Настройки</a>
                            <?php if ($user['role'] !== 'user'): ?>
                                <div class="dropdown-divider"></div>
                                <a href="<?= APP_URL ?>/admin/" class="dropdown-item"><i class="ti ti-shield"></i> Админ-панель</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?= APP_URL ?>/pages/logout.php" class="dropdown-item dropdown-item-danger"><i class="ti ti-logout"></i> Выйти</a>
                        </div>
                    </div>
                    <a href="<?= APP_URL ?>/pages/create-listing.php" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus"></i> Разместить
                    </a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-outline-primary btn-sm">Войти</a>
                    <a href="<?= APP_URL ?>/pages/register.php" class="btn btn-primary btn-sm">Регистрация</a>
                <?php endif; ?>
            </nav>

            <!-- Мобильное меню -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Меню">
                <i class="ti ti-menu-2"></i>
            </button>
        </div>

        <!-- Мобильная навигация -->
        <nav class="mobile-nav" id="mobileNav">
            <a href="<?= APP_URL ?>/pages/listings.php" class="mobile-nav-link"><i class="ti ti-layout-grid"></i> Объявления</a>
            <a href="<?= APP_URL ?>/pages/categories.php" class="mobile-nav-link"><i class="ti ti-category"></i> Категории</a>
            <?php if ($user): ?>
                <a href="<?= APP_URL ?>/pages/dashboard.php" class="mobile-nav-link"><i class="ti ti-layout-dashboard"></i> Кабинет</a>
                <a href="<?= APP_URL ?>/pages/messages.php" class="mobile-nav-link"><i class="ti ti-message-circle"></i> Сообщения <?php if ($unreadMsgs): ?><span class="badge"><?= $unreadMsgs ?></span><?php endif; ?></a>
                <a href="<?= APP_URL ?>/pages/create-listing.php" class="mobile-nav-link mobile-nav-cta"><i class="ti ti-plus"></i> Разместить объявление</a>
                <a href="<?= APP_URL ?>/pages/logout.php" class="mobile-nav-link"><i class="ti ti-logout"></i> Выйти</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/pages/login.php" class="mobile-nav-link"><i class="ti ti-login"></i> Войти</a>
                <a href="<?= APP_URL ?>/pages/register.php" class="mobile-nav-link mobile-nav-cta"><i class="ti ti-user-plus"></i> Регистрация</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="site-main">
    <div class="container">
        <?= renderFlashes() ?>