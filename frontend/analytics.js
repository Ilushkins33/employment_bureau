// analytics.js — аналитика (только admin)
const user = requireSession(['admin']);
if (user) initNavbar();

async function loadAll() {
  await Promise.all([
    loadSummary(),
    loadByCategory(),
    loadByLocation(),
    loadTopEmployers(),
    loadRecentApps(),
  ]);
}

async function loadSummary() {
  const el = document.getElementById('summary-stats');
  try {
    const s = await apiGet('/analytics.php?action=summary');
    el.innerHTML = `
      <div class="stat-card"><div class="stat-card__val">${s.users.total}</div><div class="stat-card__label">Всего пользователей</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.users.employers}</div><div class="stat-card__label">Работодателей</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.users.applicants}</div><div class="stat-card__label">Соискателей</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.vacancies.total}</div><div class="stat-card__label">Всего вакансий</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.vacancies.open}</div><div class="stat-card__label">Открытых вакансий</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.vacancies.closed}</div><div class="stat-card__label">Закрытых вакансий</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.resumes.active}</div><div class="stat-card__label">Активных резюме</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.applications.total}</div><div class="stat-card__label">Всего откликов</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.successful_placements}</div><div class="stat-card__label">Трудоустроено</div></div>
      <div class="stat-card"><div class="stat-card__val">${s.conversion_rate}%</div><div class="stat-card__label">Конверсия откликов</div></div>
    `;

    const appsEl = document.getElementById('apps-status');
    appsEl.innerHTML = `
      <div class="table-wrap">
        <table>
          <thead><tr><th>Статус</th><th>Количество</th><th style="width:40%">Доля</th></tr></thead>
          <tbody>
            ${[
              ['На рассмотрении', s.applications.pending,  'badge--yellow'],
              ['Принято',         s.applications.accepted, 'badge--green'],
              ['Отклонено',       s.applications.rejected, 'badge--red'],
            ].map(([label, val, cls]) => `<tr>
              <td><span class="badge ${cls}">${label}</span></td>
              <td><strong>${val}</strong></td>
              <td>
                <div style="background:var(--clr-border);border-radius:4px;height:8px;overflow:hidden;">
                  <div style="height:100%;border-radius:4px;background:var(--clr-accent);width:${s.applications.total ? Math.round(val / s.applications.total * 100) : 0}%"></div>
                </div>
                <span class="text-sm text-2">${s.applications.total ? Math.round(val / s.applications.total * 100) : 0}%</span>
              </td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    `;
  } catch(e) { el.innerHTML = '<div class="empty">Ошибка загрузки</div>'; }
}

async function loadByCategory() {
  const el = document.getElementById('by-category');
  try {
    const data = await apiGet('/analytics.php?action=by_category');
    if (!data.length) { empty(el, 'Нет данных', '📊'); return; }
    const max = Math.max(...data.map(d => d.count), 1);
    el.innerHTML = `<table style="width:100%">
      <thead><tr>
        <th style="padding:10px 14px;">Категория</th>
        <th style="padding:10px 14px;">Вакансий</th>
        <th style="padding:10px 14px; width:45%;">График</th>
      </tr></thead>
      <tbody>${data.map(d => `<tr>
        <td style="padding:10px 14px;">${escHtml(d.category)}</td>
        <td style="padding:10px 14px;"><strong>${d.count}</strong></td>
        <td style="padding:10px 14px;">
          <div style="background:var(--clr-border);border-radius:4px;height:10px;overflow:hidden;">
            <div style="height:100%;border-radius:4px;background:var(--clr-primary);width:${Math.round(d.count / max * 100)}%"></div>
          </div>
        </td>
      </tr>`).join('')}</tbody>
    </table>`;
  } catch(e) { empty(el, 'Ошибка загрузки', '⚠️'); }
}

async function loadByLocation() {
  const el = document.getElementById('by-location');
  try {
    const data = await apiGet('/analytics.php?action=by_location');
    if (!data.length) { empty(el, 'Нет данных', '📍'); return; }
    const max = Math.max(...data.map(d => d.count), 1);
    el.innerHTML = `<table style="width:100%">
      <thead><tr>
        <th style="padding:10px 14px;">Город</th>
        <th style="padding:10px 14px;">Вакансий</th>
        <th style="padding:10px 14px; width:45%;">График</th>
      </tr></thead>
      <tbody>${data.map(d => `<tr>
        <td style="padding:10px 14px;">📍 ${escHtml(d.location)}</td>
        <td style="padding:10px 14px;"><strong>${d.count}</strong></td>
        <td style="padding:10px 14px;">
          <div style="background:var(--clr-border);border-radius:4px;height:10px;overflow:hidden;">
            <div style="height:100%;border-radius:4px;background:var(--clr-accent);width:${Math.round(d.count / max * 100)}%"></div>
          </div>
        </td>
      </tr>`).join('')}</tbody>
    </table>`;
  } catch(e) { empty(el, 'Ошибка загрузки', '⚠️'); }
}

async function loadTopEmployers() {
  const el = document.getElementById('top-employers');
  try {
    const data = await apiGet('/analytics.php?action=top_employers');
    if (!data.length) { empty(el, 'Нет данных', '🏢'); return; }
    el.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Работодатель</th><th>Вакансий</th><th>Открыто</th><th>Закрыто</th></tr></thead>
      <tbody>${data.map(d => `<tr>
        <td>
          <strong>${escHtml(d.company || d.first_name + ' ' + d.last_name)}</strong>
          ${d.company ? `<br><span class="text-sm text-2">${escHtml(d.first_name + ' ' + d.last_name)}</span>` : ''}
        </td>
        <td><strong>${d.vacancies_count}</strong></td>
        <td><span class="badge badge--green">${d.open_count}</span></td>
        <td><span class="badge badge--grey">${d.closed_count}</span></td>
      </tr>`).join('')}</tbody>
    </table></div>`;
  } catch(e) { empty(el, 'Ошибка загрузки', '⚠️'); }
}

async function loadRecentApps() {
  const el = document.getElementById('recent-apps');
  try {
    const data = await apiGet('/analytics.php?action=recent_applications');
    if (!data.length) { empty(el, 'Откликов пока нет', '📩'); return; }
    el.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Соискатель</th><th>Вакансия</th><th>Статус</th><th>Дата</th></tr></thead>
      <tbody>${data.map(a => `<tr>
        <td>${escHtml(a.applicant_first + ' ' + a.applicant_last)}</td>
        <td>${escHtml(a.vacancy_title)}</td>
        <td>${statusBadge(a.status)}</td>
        <td class="text-sm text-2">${formatDate(a.created_at)}</td>
      </tr>`).join('')}</tbody>
    </table></div>`;
  } catch(e) { empty(el, 'Ошибка загрузки', '⚠️'); }
}

loadAll();
