<?php
// pages/register.php — Регистрация

require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) redirect('/pages/dashboard.php');

$errors = [];
$data = ['username' => '', 'email' => '', 'full_name' => '', 'city' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data['username']  = trim($_POST['username'] ?? '');
    $data['email']     = trim($_POST['email'] ?? '');
    $data['full_name'] = trim($_POST['full_name'] ?? '');
    $data['city']      = trim($_POST['city'] ?? '');
    $password          = $_POST['password'] ?? '';
    $password2         = $_POST['password2'] ?? '';

    // Валидация
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $data['username'])) {
        $errors['username'] = 'Логин: 3-30 символов, латиница, цифры и _';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email';
    }
    if (mb_strlen($data['full_name']) < 2 || mb_strlen($data['full_name']) > 80) {
        $errors['full_name'] = 'Введите имя (2-80 символов)';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Пароль должен быть не менее 8 символов';
    }
    if ($password !== $password2) {
        $errors['password2'] = 'Пароли не совпадают';
    }

    if (empty($errors)) {
        // Проверка уникальности
        $exists = db()->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $exists->execute([$data['username'], $data['email']]);
        if ($row = $exists->fetch()) {
            $errors['general'] = 'Пользователь с таким логином или email уже существует';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = db()->prepare("
                INSERT INTO users (username, email, password_hash, full_name, city)
                VALUES (?,?,?,?,?)
            ");
            $stmt->execute([$data['username'], $data['email'], $hash, $data['full_name'], $data['city']]);
            $uid = db()->lastInsertId();

            // Автовход
            $newUser = db()->prepare("SELECT * FROM users WHERE id=?");
            $newUser->execute([$uid]);
            loginUser($newUser->fetch());

            flash('success', 'Добро пожаловать на SkillSwap, ' . $data['full_name'] . '!');
            redirect('/pages/dashboard.php');
        }
    }
}

$pageTitle = 'Регистрация';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:480px;margin:0 auto;padding:20px 0;">
    <div class="card" style="padding:36px;">
        <div style="text-align:center;margin-bottom:28px;">
            <div style="width:56px;height:56px;border-radius:14px;background:var(--primary-light);color:var(--primary);font-size:1.8rem;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="ti ti-user-plus"></i>
            </div>
            <h1 style="font-size:1.5rem;font-family:var(--font-display);">Создать аккаунт</h1>
            <p style="color:var(--text-muted);font-size:0.88rem;margin-top:6px;">Уже есть аккаунт? <a href="<?= APP_URL ?>/pages/login.php">Войти</a></p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Имя и фамилия <span class="required">*</span></label>
                <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                       value="<?= e($data['full_name']) ?>" placeholder="Иван Иванов" required>
                <?php if (isset($errors['full_name'])): ?><div class="form-error"><?= e($errors['full_name']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Логин <span class="required">*</span></label>
                <input type="text" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                       value="<?= e($data['username']) ?>" placeholder="ivan_ivanov" required>
                <?php if (isset($errors['username'])): ?><div class="form-error"><?= e($errors['username']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= e($data['email']) ?>" placeholder="ivan@example.com" required>
                <?php if (isset($errors['email'])): ?><div class="form-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Город</label>
                <input type="text" name="city" class="form-control" value="<?= e($data['city']) ?>" placeholder="Москва">
            </div>

            <div class="form-group">
                <label class="form-label">Пароль <span class="required">*</span></label>
                <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       placeholder="Минимум 8 символов" required>
                <?php if (isset($errors['password'])): ?><div class="form-error"><?= e($errors['password']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Повторите пароль <span class="required">*</span></label>
                <input type="password" name="password2" class="form-control <?= isset($errors['password2']) ? 'is-invalid' : '' ?>"
                       placeholder="Повторите пароль" required>
                <?php if (isset($errors['password2'])): ?><div class="form-error"><?= e($errors['password2']) ?></div><?php endif; ?>
            </div>

            <div class="form-group">
                <label class="check-item" style="font-size:0.85rem;">
                    <input type="checkbox" required>
                    Я принимаю <a href="<?= APP_URL ?>/pages/terms.php" target="_blank">условия использования</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">
                <i class="ti ti-user-check"></i> Зарегистрироваться
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>