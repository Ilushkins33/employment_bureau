// shared.js — общие утилиты: API, сессия, toast, хелперы

const API_BASE = '../api';

// ── Сессия ────────────────────────────────────────────────────────────────────

function getSession() {
  try { return JSON.parse(localStorage.getItem('bureau_session') || 'null'); }
  catch { return null; }
}

function saveSession(data) {
  localStorage.setItem('bureau_session', JSON.stringify(data));
}

function clearSession() {
  localStorage.removeItem('bureau_session');
}

function getToken() {
  const s = getSession();
  return s ? s.token : null;
}

function getUser() {
  const s = getSession();
  return s ? s.user : null;
}

function requireSession(allowedRoles = []) {
  const user = getUser();
  if (!user) { window.location.href = 'login.html'; return null; }
  if (allowedRoles.length && !allowedRoles.includes(user.role)) {
    window.location.href = 'dashboard.html';
    return null;
  }
  return user;
}

// ── API ───────────────────────────────────────────────────────────────────────

async function api(endpoint, options = {}) {
  const token = getToken();
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers['Authorization'] = 'Bearer ' + token;

  const res = await fetch(API_BASE + endpoint, { ...options, headers });
  const json = await res.json().catch(() => ({ ok: false, error: 'Ошибка сервера' }));

  if (!json.ok) {
    if (res.status === 401) { clearSession(); window.location.href = 'login.html'; }
    throw new Error(json.error || 'Неизвестная ошибка');
  }
  return json.data;
}

function apiGet(endpoint)         { return api(endpoint, { method: 'GET' }); }
function apiPost(endpoint, body)  { return api(endpoint, { method: 'POST',   body: JSON.stringify(body) }); }
function apiPut(endpoint, body)   { return api(endpoint, { method: 'PUT',    body: JSON.stringify(body) }); }
function apiDelete(endpoint)      { return api(endpoint, { method: 'DELETE' }); }

// ── Toast ─────────────────────────────────────────────────────────────────────

function showToast(msg, type = 'info', duration = 3000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  toast.textContent = msg;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), duration);
}

// ── Navbar ────────────────────────────────────────────────────────────────────

function initNavbar() {
  const user    = getUser();
  const navGuest = document.getElementById('nav-guest');
  const navAuth  = document.getElementById('nav-auth');
  const navName  = document.getElementById('nav-name');
  const navAvatar= document.getElementById('nav-avatar');
  const navLinks = document.querySelectorAll('[data-role]');

  if (user) {
    if (navGuest)  navGuest.style.display  = 'none';
    if (navAuth)   navAuth.style.display   = 'flex';
    if (navName)   navName.textContent     = user.first_name + ' ' + user.last_name;
    if (navAvatar) navAvatar.textContent   = (user.first_name[0] || '') + (user.last_name[0] || '');

    navLinks.forEach(el => {
      const roles = el.dataset.role.split(',').map(r => r.trim());
      if (!roles.includes(user.role)) el.style.display = 'none';
    });
  } else {
    if (navGuest) navGuest.style.display = 'flex';
    if (navAuth)  navAuth.style.display  = 'none';
    navLinks.forEach(el => el.style.display = 'none');
  }

  // Активная ссылка
  const current = location.pathname.split('/').pop();
  document.querySelectorAll('.navbar__link').forEach(a => {
    if (a.getAttribute('href') === current) a.classList.add('active');
  });

  // Logout
  const logoutBtn = document.getElementById('btn-logout');
  if (logoutBtn) logoutBtn.addEventListener('click', () => {
    clearSession(); window.location.href = 'index.html';
  });
}

// ── Модальные окна ────────────────────────────────────────────────────────────

function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('hidden'); document.body.style.overflow = ''; }
}

function initModals() {
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modalClose));
  });
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal(overlay.id);
    });
  });
}

// ── Хелперы ───────────────────────────────────────────────────────────────────

function formatDate(str) {
  if (!str) return '—';
  return new Date(str).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatSalary(from, to) {
  if (!from && !to) return 'Не указана';
  if (from && to)   return `${from.toLocaleString('ru')} – ${to.toLocaleString('ru')} ₽`;
  if (from)         return `от ${from.toLocaleString('ru')} ₽`;
  return `до ${to.toLocaleString('ru')} ₽`;
}

function employmentLabel(val) {
  return { full: 'Полная занятость', part: 'Частичная', remote: 'Удалённо', contract: 'Контракт' }[val] || val || '—';
}

function statusBadge(status) {
  const map = {
    open:     ['badge--green',  'Открыта'],
    closed:   ['badge--red',    'Закрыта'],
    draft:    ['badge--grey',   'Черновик'],
    pending:  ['badge--yellow', 'На рассмотрении'],
    accepted: ['badge--green',  'Принят'],
    rejected: ['badge--red',    'Отклонён'],
    active:   ['badge--green',  'Активно'],
    hidden:   ['badge--grey',   'Скрыто'],
  };
  const [cls, label] = map[status] || ['badge--grey', status];
  return `<span class="badge ${cls}">${label}</span>`;
}

function roleName(role) {
  return { admin: 'Сотрудник бюро', employer: 'Работодатель', applicant: 'Соискатель' }[role] || role;
}

function renderSkills(skills) {
  if (!skills || !skills.length) return '<span class="text-2 text-sm">—</span>';
  return skills.map(s => `<span class="tag">${escHtml(s)}</span>`).join(' ');
}

function escHtml(str) {
  return String(str || '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function loader(container, msg = 'Загрузка…') {
  container.innerHTML = `<div class="loader"><div class="spinner"></div>${escHtml(msg)}</div>`;
}

function empty(container, msg = 'Ничего не найдено', icon = '📋') {
  container.innerHTML = `<div class="empty"><div class="empty__icon">${icon}</div><div class="empty__text">${escHtml(msg)}</div></div>`;
}

// ── Skills input widget ───────────────────────────────────────────────────────

function initSkillsInput(wrapperId, hiddenId) {
  const wrapper = document.getElementById(wrapperId);
  const hidden  = document.getElementById(hiddenId);
  if (!wrapper || !hidden) return;

  let skills = JSON.parse(hidden.value || '[]');

  function render() {
    wrapper.innerHTML = '';
    skills.forEach((s, i) => {
      const tag = document.createElement('span');
      tag.className = 'tag';
      tag.innerHTML = `${escHtml(s)} <span class="tag__rm" data-i="${i}">×</span>`;
      wrapper.appendChild(tag);
    });
    const inp = document.createElement('input');
    inp.placeholder = 'Добавить навык…';
    inp.addEventListener('keydown', e => {
      if ((e.key === 'Enter' || e.key === ',') && inp.value.trim()) {
        e.preventDefault();
        const val = inp.value.trim().replace(/,$/, '');
        if (val && !skills.includes(val)) { skills.push(val); hidden.value = JSON.stringify(skills); render(); }
        else inp.value = '';
      }
      if (e.key === 'Backspace' && !inp.value && skills.length) {
        skills.pop(); hidden.value = JSON.stringify(skills); render();
      }
    });
    wrapper.appendChild(inp);
    inp.focus();

    wrapper.querySelectorAll('.tag__rm').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        skills.splice(+btn.dataset.i, 1);
        hidden.value = JSON.stringify(skills);
        render();
      });
    });
  }

  wrapper.addEventListener('click', () => wrapper.querySelector('input')?.focus());
  hidden.value = JSON.stringify(skills);
  render();

  return { getSkills: () => skills, setSkills: (arr) => { skills = arr; hidden.value = JSON.stringify(arr); render(); } };
}
