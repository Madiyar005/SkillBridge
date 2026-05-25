// assets/js/main.js — SkillSwap клиентский скрипт

'use strict';

// ── User Menu ──────────────────────────────────────────────
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = userDropdown.classList.toggle('open');
        userMenuBtn.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', () => {
        userDropdown.classList.remove('open');
        if (userMenuBtn) userMenuBtn.setAttribute('aria-expanded', 'false');
    });
    userDropdown.addEventListener('click', e => e.stopPropagation());
}

// ── Mobile Menu ─────────────────────────────────────────────
const mobileToggle = document.getElementById('mobileMenuToggle');
const mobileNav = document.getElementById('mobileNav');

if (mobileToggle && mobileNav) {
    mobileToggle.addEventListener('click', () => {
        const open = mobileNav.classList.toggle('open');
        mobileToggle.innerHTML = open ? '<i class="ti ti-x"></i>' : '<i class="ti ti-menu-2"></i>';
    });
}

// ── Авто-dismiss alerts ─────────────────────────────────────
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.4s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 400);
    }, 4000);
});

// ── Автоматическое изменение высоты textarea ─────────────────
document.querySelectorAll('textarea[data-autoresize]').forEach(el => {
    const resize = () => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };
    el.addEventListener('input', resize);
    resize();
});

// ── Предпросмотр изображения ────────────────────────────────
document.querySelectorAll('.img-preview-input').forEach(input => {
    const preview = document.getElementById(input.dataset.preview);
    if (!preview) return;
    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(file);
    });
});

// ── Счётчик символов ────────────────────────────────────────
document.querySelectorAll('[data-maxlength]').forEach(el => {
    const max = parseInt(el.dataset.maxlength);
    const counter = document.createElement('small');
    counter.className = 'form-text';
    el.parentNode.insertBefore(counter, el.nextSibling);
    const update = () => {
        const left = max - el.value.length;
        counter.textContent = `${el.value.length}/${max}`;
        counter.style.color = left < 20 ? '#ef4444' : '';
    };
    el.addEventListener('input', update);
    update();
});

// ── Переключение избранного ─────────────────────────────────
document.querySelectorAll('.favorite-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const listingId = btn.dataset.id;
        try {
            const resp = await fetch('/skillswap/api/favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ listing_id: listingId, csrf: window._csrf })
            });
            const data = await resp.json();
            if (data.status === 'added') {
                btn.querySelector('i').className = 'ti ti-heart-filled';
                btn.title = 'Убрать из избранного';
            } else {
                btn.querySelector('i').className = 'ti ti-heart';
                btn.title = 'Добавить в избранное';
            }
        } catch {}
    });
});

// ── Подтверждение удаления ──────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});

// ── Тег-инпут ───────────────────────────────────────────────
const tagInput = document.getElementById('tagInput');
const tagList = document.getElementById('tagList');
const tagHidden = document.getElementById('tagsHidden');

if (tagInput && tagList && tagHidden) {
    let tags = [];
    const render = () => {
        tagList.innerHTML = tags.map((t, i) =>
            `<span class="tag">${t} <button type="button" data-i="${i}">&times;</button></span>`
        ).join('');
        tagHidden.value = tags.join(',');
        tagList.querySelectorAll('button').forEach(b => {
            b.addEventListener('click', () => { tags.splice(+b.dataset.i, 1); render(); });
        });
    };
    tagInput.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ',') && tagInput.value.trim()) {
            e.preventDefault();
            const val = tagInput.value.trim().replace(/,/g, '');
            if (val && !tags.includes(val) && tags.length < 10) { tags.push(val); render(); }
            tagInput.value = '';
        }
    });
}

// ── Автообновление чата ─────────────────────────────────────
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) {
    const exchangeId = chatMessages.dataset.exchangeId;
    const lastId = () => {
        const msgs = chatMessages.querySelectorAll('[data-id]');
        return msgs.length ? msgs[msgs.length - 1].dataset.id : 0;
    };
    const poll = async () => {
        try {
            const resp = await fetch(`/skillswap/api/messages.php?exchange_id=${exchangeId}&after=${lastId()}`);
            const data = await resp.json();
            if (data.messages && data.messages.length) {
                data.messages.forEach(m => {
                    const div = document.createElement('div');
                    div.className = 'message-bubble ' + (m.mine ? 'mine' : 'theirs');
                    div.dataset.id = m.id;
                    div.innerHTML = `<div class="message-text">${escHtml(m.body)}</div><span class="message-time">${m.time}</span>`;
                    chatMessages.appendChild(div);
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } catch {}
        setTimeout(poll, 3000);
    };
    chatMessages.scrollTop = chatMessages.scrollHeight;
    setTimeout(poll, 3000);
}

// ── Форма отправки сообщения (AJAX) ────────────────────────
const msgForm = document.getElementById('msgForm');
if (msgForm) {
    msgForm.addEventListener('submit', async e => {
        e.preventDefault();
        const textarea = msgForm.querySelector('textarea');
        const body = textarea.value.trim();
        if (!body) return;
        const formData = new FormData(msgForm);
        try {
            const resp = await fetch('/skillswap/api/send-message.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.ok) { textarea.value = ''; }
        } catch {}
    });
}

// ── Утилита ─────────────────────────────────────────────────
function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}