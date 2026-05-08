// index.js — главная страница
initNavbar();

async function loadStats() {
  const el = document.getElementById('stats-row');
  try {
    const vacancies = await apiGet('/vacancies.php?action=list');
    const open = vacancies.filter(v => v.status === 'open').length;
    el.innerHTML = `
      <div class="stat-card"><div class="stat-card__val">${open}</div><div class="stat-card__label">Открытых вакансий</div></div>
      <div class="stat-card"><div class="stat-card__val">→</div><div class="stat-card__label"><a href="register.html">Зарегистрироваться</a></div></div>
    `;
  } catch { el.innerHTML = ''; }
}

async function loadVacancies() {
  const el = document.getElementById('vacancies-list');
  loader(el);
  try {
    const data = await apiGet('/vacancies.php?action=list');
    const top  = data.slice(0, 6);
    if (!top.length) { empty(el, 'Вакансий пока нет', '💼'); return; }
    el.innerHTML = top.map(v => `
      <div class="card">
        <a href="vacancy.html?id=${v.id}" class="card__title-link">
          <div class="card__title">${escHtml(v.title)}</div>
        </a>
        <div class="card__sub">${escHtml(v.company || v.first_name + ' ' + v.last_name)}</div>
        <div class="card__meta">
          ${v.location   ? `<span class="tag">📍 ${escHtml(v.location)}</span>`        : ''}
          ${v.employment ? `<span class="tag">${employmentLabel(v.employment)}</span>`  : ''}
          ${v.category   ? `<span class="tag">${escHtml(v.category)}</span>`            : ''}
        </div>
        ${v.salary_from || v.salary_to ? `<div class="salary">${formatSalary(v.salary_from, v.salary_to)}</div>` : ''}
        <div class="card__footer">
          <span class="card__date">${formatDate(v.created_at)}</span>
          <a href="vacancy.html?id=${v.id}" class="btn btn--primary btn--sm">Подробнее</a>
        </div>
      </div>
    `).join('');
  } catch(e) { empty(el, 'Ошибка загрузки', '⚠️'); }
}

function heroSearch() {
  const q = document.getElementById('hero-q').value.trim();
  window.location.href = 'vacancies.html' + (q ? '?q=' + encodeURIComponent(q) : '');
}

document.getElementById('hero-q').addEventListener('keydown', e => {
  if (e.key === 'Enter') heroSearch();
});

loadStats();
loadVacancies();
