// vacancies.js — список вакансий с фильтрами
initNavbar();

async function loadVacancies() {
  const el = document.getElementById('vacancies-list');
  loader(el);

  const params     = new URLSearchParams();
  const q          = document.getElementById('f-q').value.trim();
  const category   = document.getElementById('f-category').value;
  const employment = document.getElementById('f-employment').value;
  const experience = document.getElementById('f-experience').value;
  const salary     = document.getElementById('f-salary').value;
  const location   = document.getElementById('f-location').value.trim();

  if (q)          params.set('q',           q);
  if (category)   params.set('category',    category);
  if (employment) params.set('employment',  employment);
  if (experience) params.set('experience',  experience);
  if (salary)     params.set('salary_from', salary);
  if (location)   params.set('location',    location);

  try {
    const data = await apiGet('/vacancies.php?action=list&' + params.toString());
    document.getElementById('vac-count').textContent = `Найдено: ${data.length}`;

    if (!data.length) { empty(el, 'Вакансий по вашему запросу не найдено', '💼'); return; }

    el.innerHTML = data.map(v => `
      <div class="card">
        <a href="vacancy.html?id=${v.id}" class="card__title-link">
          <div class="card__title">${escHtml(v.title)}</div>
        </a>
        <div class="card__sub">${escHtml(v.company || v.first_name + ' ' + v.last_name)}</div>
        <div class="card__meta">
          ${v.location   ? `<span class="tag">📍 ${escHtml(v.location)}</span>`       : ''}
          ${v.employment ? `<span class="tag">${employmentLabel(v.employment)}</span>` : ''}
          ${v.category   ? `<span class="tag">${escHtml(v.category)}</span>`           : ''}
          ${v.experience ? `<span class="tag">Опыт: ${escHtml(v.experience)}</span>`  : ''}
        </div>
        ${v.salary_from || v.salary_to
          ? `<div class="salary mb-8">${formatSalary(v.salary_from, v.salary_to)}</div>` : ''}
        <div class="text-sm text-2 mb-8" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
          ${escHtml(v.description)}
        </div>
        <div class="card__footer">
          <span class="card__date">${formatDate(v.created_at)}</span>
          <a href="vacancy.html?id=${v.id}" class="btn btn--primary btn--sm">Подробнее</a>
        </div>
      </div>
    `).join('');
  } catch(e) {
    empty(el, 'Ошибка загрузки вакансий', '⚠️');
  }
}

function resetFilters() {
  ['f-q', 'f-salary', 'f-location'].forEach(id => document.getElementById(id).value = '');
  ['f-category', 'f-employment', 'f-experience'].forEach(id => document.getElementById(id).value = '');
  loadVacancies();
}

// Поиск по Enter
document.getElementById('f-q').addEventListener('keydown', e => {
  if (e.key === 'Enter') loadVacancies();
});

// Пре-заполнение из URL (?q=...)
const urlQ = new URLSearchParams(location.search).get('q');
if (urlQ) document.getElementById('f-q').value = urlQ;

loadVacancies();
