<?php // includes/footer.php — Подвал сайта ?>
    </div><!-- .container -->
</main><!-- .site-main -->

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?= APP_URL ?>/" class="logo logo-light">
                    <span class="logo-icon"><i class="ti ti-arrows-exchange"></i></span>
                    <span class="logo-text">Skill<strong>Bridge</strong></span>
                </a>
                <p>Платформа для взаимовыгодного обмена навыками и услугами между людьми.</p>
                <div class="footer-socials">
                    <a href="#" aria-label="Telegram"><i class="ti ti-brand-telegram"></i></a>
                    <a href="#" aria-label="ВКонтакте"><i class="ti ti-brand-vk"></i></a>
                    <a href="#" aria-label="Instagram"><i class="ti ti-brand-instagram"></i></a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Платформа</h4>
                <ul>
                    <li><a href="<?= APP_URL ?>/pages/listings.php">Все объявления</a></li>
                    <li><a href="<?= APP_URL ?>/pages/categories.php">Категории</a></li>
                    <li><a href="<?= APP_URL ?>/pages/listings.php?type=offer">Предложения</a></li>
                    <li><a href="<?= APP_URL ?>/pages/listings.php?type=request">Запросы</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Аккаунт</h4>
                <ul>
                    <li><a href="<?= APP_URL ?>/pages/register.php">Регистрация</a></li>
                    <li><a href="<?= APP_URL ?>/pages/login.php">Вход</a></li>
                    <li><a href="<?= APP_URL ?>/pages/create-listing.php">Разместить объявление</a></li>
                    <li><a href="<?= APP_URL ?>/pages/dashboard.php">Личный кабинет</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Помощь</h4>
                <ul>
                    <li><a href="<?= APP_URL ?>/pages/how-it-works.php">Как это работает</a></li>
                    <li><a href="<?= APP_URL ?>/pages/faq.php">FAQ</a></li>
                    <li><a href="<?= APP_URL ?>/pages/terms.php">Условия использования</a></li>
                    <li><a href="<?= APP_URL ?>/pages/privacy.php">Конфиденциальность</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> SkillSwap. Все права защищены.</p>
            <p>Сделано с <i class="ti ti-heart" style="color:#e53e3e;"></i> в России</p>
        </div>
    </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>