<?php
// pages/login.php — Авторизация

require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) redirect('/pages/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($login && $password) {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $stmt = db()->prepare("SELECT * FROM users WHERE $field=? AND is_active=1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user);
            if ($remember) {
                session_set_cookie_params(['lifetime' => 86400 * 30]);
            }
            flash('success', 'Добро пожаловать, ' . $user['full_name'] . '!');
            $redirect = $_GET['redirect'] ?? '/pages/dashboard.php';
            redirect($redirect);
        } else {
            $error = 'Неверный логин или пароль';
        }
    } else {
        $error = 'Заполните все поля';
    }
}

$pageTitle = 'Вход';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:440px;margin:0 auto;padding:20px 0;">
    <div class="card" style="padding:36px;">
        <div style="text-align:center;margin-bottom:28px;">
            <div style="width:56px;height:56px;border-radius:14px;background:var(--primary-light);color:var(--primary);font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="ti ti-login"></i>
            </div>
            <h1 style="font-size:1.5rem;font-family:var(--font-display);">Вход в аккаунт</h1>
            <p style="color:var(--text-muted);font-size:0.88rem;margin-top:6px;">Нет аккаунта? <a href="<?= APP_URL ?>/pages/register.php">Зарегистрироваться</a></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="ti ti-alert-circle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Email или логин</label>
                <input type="text" name="login" class="form-control" value="<?= e($_POST['login'] ?? '') ?>"
                       placeholder="ivan@example.com или ivan_ivanov" autofocus required>
            </div>

            <div class="form-group">
                <label class="form-label" style="display:flex;justify-content:space-between;">
                    <span>Пароль</span>
                    <a href="<?= APP_URL ?>/pages/forgot-password.php" style="font-weight:400;font-size:0.8rem;">Забыли пароль?</a>
                </label>
                <input type="password" name="password" class="form-control" placeholder="Ваш пароль" required>
            </div>

            <div class="form-group">
                <label class="check-item" style="font-size:0.88rem;">
                    <input type="checkbox" name="remember">
                    Запомнить меня на 30 дней
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="ti ti-login"></i> Войти
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>