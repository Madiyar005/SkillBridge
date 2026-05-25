<?php
// pages/create-listing.php — Создание объявления

require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$categories = db()->query("SELECT * FROM categories ORDER BY parent_id, sort_order")->fetchAll();
$errors = [];
$data = [
    'title' => '', 'description' => '', 'category_id' => '',
    'type' => 'offer', 'exchange_type' => 'both',
    'price' => '', 'skill_wanted' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'title'         => trim($_POST['title'] ?? ''),
        'description'   => trim($_POST['description'] ?? ''),
        'category_id'   => (int)($_POST['category_id'] ?? 0),
        'type'          => $_POST['type'] ?? 'offer',
        'exchange_type' => $_POST['exchange_type'] ?? 'both',
        'price'         => trim($_POST['price'] ?? ''),
        'skill_wanted'  => trim($_POST['skill_wanted'] ?? ''),
    ];

    // Валидация
    if (mb_strlen($data['title']) < 10 || mb_strlen($data['title']) > 200)
        $errors['title'] = 'Заголовок: 10-200 символов';
    if (mb_strlen($data['description']) < 30)
        $errors['description'] = 'Описание: минимум 30 символов';
    if (!$data['category_id'])
        $errors['category_id'] = 'Выберите категорию';
    if (!in_array($data['type'], ['offer', 'request']))
        $errors['type'] = 'Выберите тип объявления';
    if (!in_array($data['exchange_type'], ['skill_swap', 'paid', 'both']))
        $errors['exchange_type'] = 'Выберите формат';
    if (($data['exchange_type'] === 'paid' || $data['exchange_type'] === 'both') && !is_numeric($data['price']))
        $errors['price'] = 'Укажите корректную цену';

    if (empty($errors)) {
        $user = currentUser();
        $stmt = db()->prepare("
            INSERT INTO listings (user_id, category_id, title, description, type, exchange_type, price, skill_wanted)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $user['id'],
            $data['category_id'],
            $data['title'],
            $data['description'],
            $data['type'],
            $data['exchange_type'],
            $data['price'] ?: null,
            $data['skill_wanted'] ?: null,
        ]);
        $listingId = db()->lastInsertId();

        // Загрузка изображений
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            for ($i = 0; $i < min(5, count($files['name'])); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $filename = uploadImage($file, 'listings');
                    if ($filename) {
                        db()->prepare("INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?,?,?)")
                           ->execute([$listingId, $filename, $i]);
                    }
                }
            }
        }

        // Теги
        $tagsRaw = trim($_POST['tags'] ?? '');
        if ($tagsRaw) {
            foreach (array_unique(array_slice(array_map('trim', explode(',', $tagsRaw)), 0, 10)) as $tagName) {
                if (!$tagName) continue;
                $tagSlug = slug($tagName);
                db()->prepare("INSERT IGNORE INTO tags (name, slug) VALUES (?,?)")->execute([$tagName, $tagSlug]);
                $tagId = db()->query("SELECT id FROM tags WHERE slug='" . addslashes($tagSlug) . "' LIMIT 1")->fetchColumn();
                if ($tagId) {
                    db()->prepare("INSERT IGNORE INTO listing_tags (listing_id, tag_id) VALUES (?,?)")->execute([$listingId, $tagId]);
                }
            }
        }

        flash('success', 'Объявление успешно опубликовано!');
        redirect('/pages/listing.php?id=' . $listingId);
    }
}

$pageTitle = 'Разместить объявление';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:720px;margin:0 auto;">
    <h1 style="font-family:var(--font-display);font-size:1.6rem;margin-bottom:24px;">
        <i class="ti ti-plus" style="color:var(--primary);"></i> Новое объявление
    </h1>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <i class="ti ti-alert-circle"></i>
            Пожалуйста, исправьте ошибки в форме
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>

        <!-- Основная информация -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border);">
                    <i class="ti ti-info-circle" style="color:var(--primary);"></i> Основная информация
                </h2>

                <div class="form-group">
                    <label class="form-label">Тип объявления <span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="type" value="offer" <?= $data['type']==='offer' ? 'checked' : '' ?>>
                            <span><i class="ti ti-hand-rock"></i> Предлагаю навык/услугу</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="type" value="request" <?= $data['type']==='request' ? 'checked' : '' ?>>
                            <span><i class="ti ti-hand"></i> Ищу навык/услугу</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Заголовок <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                           value="<?= e($data['title']) ?>" placeholder="Например: Уроки английского для начинающих"
                           data-maxlength="200" required>
                    <?php if (isset($errors['title'])): ?><div class="form-error"><?= e($errors['title']) ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Категория <span class="required">*</span></label>
                    <select name="category_id" class="form-control <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="">— Выберите категорию —</option>
                        <?php
                        $parents = array_filter($categories, fn($c) => !$c['parent_id']);
                        $children = [];
                        foreach ($categories as $c) if ($c['parent_id']) $children[$c['parent_id']][] = $c;
                        foreach ($parents as $p):
                        ?>
                            <optgroup label="<?= e($p['name']) ?>">
                                <option value="<?= $p['id'] ?>" <?= $data['category_id']==$p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (общее)
                                </option>
                                <?php foreach ($children[$p['id']] ?? [] as $ch): ?>
                                <option value="<?= $ch['id'] ?>" <?= $data['category_id']==$ch['id'] ? 'selected' : '' ?>>
                                    &nbsp;&nbsp;<?= e($ch['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?><div class="form-error"><?= e($errors['category_id']) ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Описание <span class="required">*</span></label>
                    <textarea name="description" class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                              rows="6" placeholder="Подробно опишите: что вы предлагаете, ваш опыт, что входит в услугу..."
                              data-maxlength="3000" data-autoresize required><?= e($data['description']) ?></textarea>
                    <?php if (isset($errors['description'])): ?><div class="form-error"><?= e($errors['description']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Условия обмена -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border);">
                    <i class="ti ti-arrows-exchange" style="color:var(--primary);"></i> Условия
                </h2>

                <div class="form-group">
                    <label class="form-label">Формат <span class="required">*</span></label>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="exchange_type" value="both" <?= $data['exchange_type']==='both' ? 'checked' : '' ?>>
                            <span>Обмен или оплата</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="exchange_type" value="skill_swap" <?= $data['exchange_type']==='skill_swap' ? 'checked' : '' ?>>
                            <span>Только обмен навыками</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="exchange_type" value="paid" <?= $data['exchange_type']==='paid' ? 'checked' : '' ?>>
                            <span>Только за оплату</span>
                        </label>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Цена (₽)</label>
                        <input type="number" name="price" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
                               value="<?= e($data['price']) ?>" min="0" step="50" placeholder="0 — бесплатно">
                        <div class="form-text">Укажите 0 если бесплатно</div>
                        <?php if (isset($errors['price'])): ?><div class="form-error"><?= e($errors['price']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Что хотите получить в обмен</label>
                        <input type="text" name="skill_wanted" class="form-control"
                               value="<?= e($data['skill_wanted']) ?>" placeholder="Уроки Python, дизайн логотипа...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Медиа и теги -->
        <div class="card" style="margin-bottom:20px;">
            <div class="card-body">
                <h2 style="font-size:1rem;font-weight:700;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border);">
                    <i class="ti ti-photo" style="color:var(--primary);"></i> Фото и теги
                </h2>

                <div class="form-group">
                    <label class="form-label">Изображения (до 5 штук)</label>
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                    <div class="form-text">JPG, PNG, WebP. Максимум 5 МБ каждое.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Теги</label>
                    <input type="text" id="tagInput" class="form-control" placeholder="Введите тег и нажмите Enter">
                    <input type="hidden" name="tags" id="tagsHidden">
                    <div id="tagList" class="tags-list" style="margin-top:8px;"></div>
                    <div class="form-text">До 10 тегов. Помогают находить ваше объявление в поиске.</div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="<?= APP_URL ?>/pages/my-listings.php" class="btn btn-secondary">Отмена</a>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-check"></i> Опубликовать объявление
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>