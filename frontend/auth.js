// auth.js — вход и регистрация

// ── Login ─────────────────────────────────────────────────────────────────────

function initLogin() {
  if (!document.getElementById('btn-login')) return;

  if (getUser()) { window.location.href = 'dashboard.html'; return; }

  document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
}

async function doLogin() {
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const btn      = document.getElementById('btn-login');

  hideErr();
  if (!email || !password) { showErr('Заполните все поля'); return; }

  btn.disabled = true; btn.textContent = 'Вход…';
  try {
    const data = await apiPost('/auth.php?action=login', { email, password });
    saveSession({ token: data.token, user: data.user });
    showToast('Добро пожаловать, ' + data.user.first_name + '!', 'success');
    setTimeout(() => window.location.href = 'dashboard.html', 500);
  } catch(e) {
    showErr(e.message);
    btn.disabled = false; btn.textContent = 'Войти';
  }
}

// ── Register ──────────────────────────────────────────────────────────────────

function initRegister() {
  if (!document.getElementById('role')) return;

  if (getUser()) { window.location.href = 'dashboard.html'; return; }

  setRole('applicant');
}

function setRole(role) {
  document.getElementById('role').value = role;
  document.querySelectorAll('.role-btn').forEach(b => {
    const active = b.dataset.role === role;
    b.classList.toggle('active', active);
    b.style.background = active ? 'var(--clr-primary)' : '';
    b.style.color      = active ? '#fff' : '';
  });
  document.getElementById('company-field').style.display = role === 'employer' ? 'block' : 'none';
}

async function doRegister() {
  hideErr();

  const role       = document.getElementById('role').value;
  const first_name = document.getElementById('first_name').value.trim();
  const last_name  = document.getElementById('last_name').value.trim();
  const email      = document.getElementById('email').value.trim();
  const phone      = document.getElementById('phone').value.trim();
  const company    = document.getElementById('company')?.value.trim() || '';
  const password   = document.getElementById('password').value;
  const password2  = document.getElementById('password2').value;

  if (!first_name || !last_name || !email || !password) { showErr('Заполните обязательные поля'); return; }
  if (password !== password2) { showErr('Пароли не совпадают'); return; }
  if (password.length < 8)    { showErr('Пароль минимум 8 символов'); return; }

  const btn = document.querySelector('[onclick="doRegister()"]');
  btn.disabled = true; btn.textContent = 'Регистрация…';

  try {
    const data = await apiPost('/auth.php?action=register', {
      role, first_name, last_name, email, phone, company, password,
    });
    saveSession({ token: data.token, user: data.user });
    showToast('Аккаунт создан!', 'success');
    setTimeout(() => window.location.href = 'dashboard.html', 500);
  } catch(e) {
    showErr(e.message);
    btn.disabled = false; btn.textContent = 'Зарегистрироваться';
  }
}

// ── Общие ─────────────────────────────────────────────────────────────────────

function showErr(msg) {
  const el = document.getElementById('err-msg');
  if (el) { el.textContent = msg; el.style.display = 'block'; }
}

function hideErr() {
  const el = document.getElementById('err-msg');
  if (el) el.style.display = 'none';
}

// Автоинициализация
initLogin();
initRegister();
