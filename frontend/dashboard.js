// dashboard.js — личный кабинет (admin / employer / applicant)
const user = requireSession();
if (user) initNavbar();

async function init() {
  if (!user) return;
  const el = document.getElementById('dashboard-content');
  if (user.role === 'admin')     await renderAdmin(el);
  if (user.role === 'employer')  await renderEmployer(el);
  if (user.role === 'applicant') await renderApplicant(el);
  initModals();
}

// ── ADMIN ─────────────────────────────────────────────────────────────────────

async function renderAdmin(el) {
  el.innerHTML = `
    <div class="section__header">
      <h1 class="section__title">👋 Добро пожаловать, ${escHtml(user.first_name)}</h1>
      <span class="badge badge--blue">Сотрудник бюро</span>
    </div>
    <div id="admin-stats" class="stats-grid"></div>
    <div class="section">
      <div class="section__header">
        <h2 class="section__title">Последние отклики</h2>
        <a href="analytics.html" class="btn btn--outline btn--sm">Аналитика</a>
      </div>
      <div id="admin-apps"></div>
    </div>
    <div class="section">
      <div class="section__header">
        <h2 class="section__title">Все вакансии</h2>
        <a href="vacancies.html" class="btn btn--outline btn--sm">Перейти</a>
      </div>
      <div id="admin-vacs"></div>
    </div>
  `;

  try {
    const sum = await apiGet('/analytics.php?action=summary');
    document.getElementById('admin-stats').innerHTML = `
      <div class="stat-card"><div class="stat-card__val">${sum.users.total}</div><div class="stat-card__label">Пользователей</div></div>
      <div class="stat-card"><div class="stat-card__val">${sum.vacancies.open}</div><div class="stat-card__label">Открытых вакансий</div></div>
      <div class="stat-card"><div class="stat-card__val">${sum.resumes.active}</div><div class="stat-card__label">Активных резюме</div></div>
      <div class="stat-card"><div class="stat-card__val">${sum.applications.total}</div><div class="stat-card__label">Откликов</div></div>
      <div class="stat-card"><div class="stat-card__val">${sum.successful_placements}</div><div class="stat-card__label">Трудоустроено</div></div>
      <div class="stat-card"><div class="stat-card__val">${sum.conversion_rate}%</div><div class="stat-card__label">Конверсия</div></div>
    `;
  } catch(e) {}

  try {
    const apps = await apiGet('/analytics.php?action=recent_applications');
    const t = document.getElementById('admin-apps');
    if (!apps.length) { empty(t, 'Откликов пока нет'); return; }
    t.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Соискатель</th><th>Вакансия</th><th>Статус</th><th>Дата</th></tr></thead>
      <tbody>${apps.map(a => `<tr>
        <td>${escHtml(a.applicant_first + ' ' + a.applicant_last)}</td>
        <td>${escHtml(a.vacancy_title)}</td>
        <td>${statusBadge(a.status)}</td>
        <td class="text-sm text-2">${formatDate(a.created_at)}</td>
      </tr>`).join('')}</tbody></table></div>`;
  } catch(e) {}

  try {
    const vacs = await apiGet('/vacancies.php?action=list');
    const t = document.getElementById('admin-vacs');
    if (!vacs.length) { empty(t, 'Вакансий нет'); return; }
    t.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Название</th><th>Работодатель</th><th>Статус</th><th>Откликов</th><th>Дата</th></tr></thead>
      <tbody>${vacs.slice(0, 10).map(v => `<tr>
        <td><a href="vacancy.html?id=${v.id}">${escHtml(v.title)}</a></td>
        <td>${escHtml(v.company || v.first_name + ' ' + v.last_name)}</td>
        <td>${statusBadge(v.status)}</td>
        <td>${v.applications_count}</td>
        <td class="text-sm text-2">${formatDate(v.created_at)}</td>
      </tr>`).join('')}</tbody></table></div>`;
  } catch(e) {}
}

// ── EMPLOYER ──────────────────────────────────────────────────────────────────

async function renderEmployer(el) {
  el.innerHTML = `
    <div class="section__header">
      <div>
        <h1 class="section__title">👋 ${escHtml(user.first_name + ' ' + user.last_name)}</h1>
        <div class="text-sm text-2 mt-8">${escHtml(user.company || '')}</div>
      </div>
      <span class="badge badge--blue">Работодатель</span>
    </div>
    <div id="emp-stats" class="stats-grid"></div>
    <div class="section">
      <div class="section__header">
        <h2 class="section__title">Мои вакансии</h2>
        <button class="btn btn--primary btn--sm" onclick="openVacancyModal()">+ Новая вакансия</button>
      </div>
      <div id="emp-vacs"></div>
    </div>
    <div class="section">
      <div class="section__header">
        <h2 class="section__title">Отклики на мои вакансии</h2>
      </div>
      <div id="emp-apps"></div>
    </div>
  `;
  await loadEmployerData();
}

async function loadEmployerData() {
  try {
    const vacs      = await apiGet('/vacancies.php?action=list');
    const open      = vacs.filter(v => v.status === 'open').length;
    const totalApps = vacs.reduce((s, v) => s + (+v.applications_count || 0), 0);

    document.getElementById('emp-stats').innerHTML = `
      <div class="stat-card"><div class="stat-card__val">${vacs.length}</div><div class="stat-card__label">Всего вакансий</div></div>
      <div class="stat-card"><div class="stat-card__val">${open}</div><div class="stat-card__label">Открытых</div></div>
      <div class="stat-card"><div class="stat-card__val">${totalApps}</div><div class="stat-card__label">Откликов</div></div>
    `;

    const t = document.getElementById('emp-vacs');
    if (!vacs.length) { empty(t, 'Вакансий пока нет. Создайте первую!', '💼'); }
    else t.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Название</th><th>Статус</th><th>Откликов</th><th>Дата</th><th>Действия</th></tr></thead>
      <tbody>${vacs.map(v => `<tr>
        <td><a href="vacancy.html?id=${v.id}">${escHtml(v.title)}</a></td>
        <td>${statusBadge(v.status)}</td>
        <td>${v.applications_count}</td>
        <td class="text-sm text-2">${formatDate(v.created_at)}</td>
        <td style="display:flex;gap:6px;">
          <button class="btn btn--outline btn--sm" onclick="openVacancyModal(${v.id})">✏️</button>
          <button class="btn btn--danger btn--sm"  onclick="deleteVacancy(${v.id})">🗑</button>
        </td>
      </tr>`).join('')}</tbody></table></div>`;
  } catch(e) { showToast('Ошибка загрузки вакансий', 'error'); }

  try {
    const apps = await apiGet('/applications.php?action=list');
    const t = document.getElementById('emp-apps');
    if (!apps.length) { empty(t, 'Откликов пока нет', '📩'); return; }
    t.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Соискатель</th><th>Вакансия</th><th>Письмо</th><th>Статус</th><th>Дата</th><th>Действие</th></tr></thead>
      <tbody>${apps.map(a => `<tr>
        <td>${escHtml(a.applicant_first + ' ' + a.applicant_last)}<br>
            <span class="text-sm text-2">${escHtml(a.applicant_email)}</span></td>
        <td>${escHtml(a.vacancy_title)}</td>
        <td class="text-sm">${a.cover_letter ? escHtml(a.cover_letter.slice(0, 60)) + (a.cover_letter.length > 60 ? '…' : '') : '—'}</td>
        <td>${statusBadge(a.status)}</td>
        <td class="text-sm text-2">${formatDate(a.created_at)}</td>
        <td>
          ${a.status === 'pending' ? `
            <button class="btn btn--sm" style="background:var(--clr-success-l);color:var(--clr-success);" onclick="changeAppStatus(${a.id},'accepted')">✓ Принять</button>
            <button class="btn btn--sm" style="background:var(--clr-danger-l);color:var(--clr-danger);"   onclick="changeAppStatus(${a.id},'rejected')">✗ Отклонить</button>
            <a href="interviews.html" class="btn btn--sm btn--outline" onclick="sessionStorage.setItem('schedule_app_id','${a.id}')">📅 Собеседование</a>
          ` : '—'}
        </td>
      </tr>`).join('')}</tbody></table></div>`;
  } catch(e) {}
}

// ── APPLICANT ─────────────────────────────────────────────────────────────────

async function renderApplicant(el) {
  el.innerHTML = `
    <div class="section__header">
      <h1 class="section__title">👋 ${escHtml(user.first_name + ' ' + user.last_name)}</h1>
      <span class="badge badge--blue">Соискатель</span>
    </div>
    <div id="app-stats" class="stats-grid"></div>
    <div class="section">
      <div class="section__header">
        <h2 class="section__title">Мои резюме</h2>
        <button class="btn btn--primary btn--sm" onclick="openResumeModal()">+ Новое резюме</button>
      </div>
      <div id="app-resumes"></div>
    </div>
    <div class="section">
      <div class="section__header">
        <h2 class="section__title">Мои отклики</h2>
        <a href="vacancies.html" class="btn btn--outline btn--sm">Найти вакансии</a>
      </div>
      <div id="app-apps"></div>
    </div>
  `;
  await loadApplicantData();
}

async function loadApplicantData() {
  try {
    const [resumes, apps] = await Promise.all([
      apiGet('/resumes.php?action=my'),
      apiGet('/applications.php?action=list'),
    ]);

    document.getElementById('app-stats').innerHTML = `
      <div class="stat-card"><div class="stat-card__val">${resumes.length}</div><div class="stat-card__label">Резюме</div></div>
      <div class="stat-card"><div class="stat-card__val">${apps.length}</div><div class="stat-card__label">Откликов</div></div>
      <div class="stat-card"><div class="stat-card__val">${apps.filter(a => a.status === 'pending').length}</div><div class="stat-card__label">На рассмотрении</div></div>
      <div class="stat-card"><div class="stat-card__val">${apps.filter(a => a.status === 'accepted').length}</div><div class="stat-card__label">Принято</div></div>
    `;

    const rt = document.getElementById('app-resumes');
    if (!resumes.length) empty(rt, 'Резюме пока нет. Создайте первое!', '📄');
    else rt.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Должность</th><th>Навыки</th><th>Зарплата</th><th>Статус</th><th>Действия</th></tr></thead>
      <tbody>${resumes.map(r => `<tr>
        <td><strong>${escHtml(r.title)}</strong></td>
        <td>${renderSkills(r.skills).slice(0, 120)}</td>
        <td class="salary">${r.salary_from ? 'от ' + r.salary_from.toLocaleString('ru') + ' ₽' : '—'}</td>
        <td>${statusBadge(r.status)}</td>
        <td style="display:flex;gap:6px;">
          <button class="btn btn--outline btn--sm" onclick="openResumeModal(${r.id})">✏️</button>
          <button class="btn btn--danger btn--sm"  onclick="deleteResume(${r.id})">🗑</button>
        </td>
      </tr>`).join('')}</tbody></table></div>`;

    const at = document.getElementById('app-apps');
    if (!apps.length) empty(at, 'Вы ещё не откликались на вакансии', '📩');
    else at.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Вакансия</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
      <tbody>${apps.map(a => `<tr>
        <td><a href="vacancy.html?id=${a.vacancy_id}">${escHtml(a.vacancy_title)}</a></td>
        <td>${statusBadge(a.status)}</td>
        <td class="text-sm text-2">${formatDate(a.created_at)}</td>
        <td>${a.status === 'pending' ? `<button class="btn btn--ghost btn--sm" onclick="cancelApp(${a.id})">Отозвать</button>` : ''}</td>
      </tr>`).join('')}</tbody></table></div>`;
  } catch(e) { showToast('Ошибка загрузки', 'error'); }
}

// ── Вакансии CRUD ─────────────────────────────────────────────────────────────

async function openVacancyModal(id = null) {
  document.getElementById('modal-vacancy-title').textContent = id ? 'Редактировать вакансию' : 'Новая вакансия';
  document.getElementById('vac-id').value = '';
  ['vac-title', 'vac-desc', 'vac-location', 'vac-category'].forEach(f => document.getElementById(f).value = '');
  ['vac-sal-from', 'vac-sal-to'].forEach(f => document.getElementById(f).value = '');
  document.getElementById('vac-exp').value        = '';
  document.getElementById('vac-employment').value = '';
  document.getElementById('vac-status').value     = 'open';

  if (id) {
    try {
      const v = await apiGet('/vacancies.php?action=one&id=' + id);
      document.getElementById('vac-id').value          = v.id;
      document.getElementById('vac-title').value        = v.title       || '';
      document.getElementById('vac-desc').value         = v.description || '';
      document.getElementById('vac-sal-from').value     = v.salary_from || '';
      document.getElementById('vac-sal-to').value       = v.salary_to   || '';
      document.getElementById('vac-location').value     = v.location    || '';
      document.getElementById('vac-category').value     = v.category    || '';
      document.getElementById('vac-exp').value          = v.experience  || '';
      document.getElementById('vac-employment').value   = v.employment  || '';
      document.getElementById('vac-status').value       = v.status      || 'open';
    } catch(e) { showToast('Ошибка загрузки', 'error'); return; }
  }
  openModal('modal-vacancy');
}

async function saveVacancy() {
  const id   = document.getElementById('vac-id').value;
  const body = {
    title:       document.getElementById('vac-title').value.trim(),
    description: document.getElementById('vac-desc').value.trim(),
    salary_from: +document.getElementById('vac-sal-from').value || null,
    salary_to:   +document.getElementById('vac-sal-to').value   || null,
    location:    document.getElementById('vac-location').value.trim(),
    category:    document.getElementById('vac-category').value.trim(),
    experience:  document.getElementById('vac-exp').value,
    employment:  document.getElementById('vac-employment').value || null,
    status:      document.getElementById('vac-status').value,
  };
  if (!body.title || !body.description) { showToast('Заполните название и описание', 'error'); return; }
  try {
    if (id) await apiPut('/vacancies.php?action=update&id=' + id, body);
    else    await apiPost('/vacancies.php?action=create', body);
    closeModal('modal-vacancy');
    showToast('Вакансия сохранена', 'success');
    await loadEmployerData();
  } catch(e) { showToast(e.message, 'error'); }
}

async function deleteVacancy(id) {
  if (!confirm('Удалить вакансию?')) return;
  try {
    await apiDelete('/vacancies.php?action=delete&id=' + id);
    showToast('Вакансия удалена', 'success');
    await loadEmployerData();
  } catch(e) { showToast(e.message, 'error'); }
}

// ── Резюме CRUD ───────────────────────────────────────────────────────────────

let skillsWidget = null;

async function openResumeModal(id = null) {
  document.getElementById('modal-resume-title').textContent = id ? 'Редактировать резюме' : 'Новое резюме';
  document.getElementById('res-id').value = '';
  ['res-title', 'res-desc', 'res-exp', 'res-edu', 'res-location'].forEach(f => document.getElementById(f).value = '');
  document.getElementById('res-salary').value = '';
  document.getElementById('res-status').value = 'active';
  document.getElementById('res-skills').value = '[]';
  skillsWidget = initSkillsInput('skills-wrap', 'res-skills');

  if (id) {
    try {
      const r = await apiGet('/resumes.php?action=one&id=' + id);
      document.getElementById('res-id').value       = r.id;
      document.getElementById('res-title').value    = r.title       || '';
      document.getElementById('res-desc').value     = r.description || '';
      document.getElementById('res-exp').value      = r.experience  || '';
      document.getElementById('res-edu').value      = r.education   || '';
      document.getElementById('res-salary').value   = r.salary_from || '';
      document.getElementById('res-location').value = r.location    || '';
      document.getElementById('res-status').value   = r.status      || 'active';
      skillsWidget.setSkills(r.skills || []);
    } catch(e) { showToast('Ошибка загрузки', 'error'); return; }
  }
  openModal('modal-resume');
}

async function saveResume() {
  const id   = document.getElementById('res-id').value;
  const body = {
    title:       document.getElementById('res-title').value.trim(),
    description: document.getElementById('res-desc').value.trim(),
    skills:      skillsWidget ? skillsWidget.getSkills() : [],
    experience:  document.getElementById('res-exp').value.trim(),
    education:   document.getElementById('res-edu').value.trim(),
    salary_from: +document.getElementById('res-salary').value || null,
    location:    document.getElementById('res-location').value.trim(),
    status:      document.getElementById('res-status').value,
  };
  if (!body.title || !body.description) { showToast('Заполните должность и описание', 'error'); return; }
  try {
    if (id) await apiPut('/resumes.php?action=update&id=' + id, body);
    else    await apiPost('/resumes.php?action=create', body);
    closeModal('modal-resume');
    showToast('Резюме сохранено', 'success');
    await loadApplicantData();
  } catch(e) { showToast(e.message, 'error'); }
}

async function deleteResume(id) {
  if (!confirm('Удалить резюме?')) return;
  try {
    await apiDelete('/resumes.php?action=delete&id=' + id);
    showToast('Резюме удалено', 'success');
    await loadApplicantData();
  } catch(e) { showToast(e.message, 'error'); }
}

// ── Отклики ───────────────────────────────────────────────────────────────────

async function changeAppStatus(id, status) {
  try {
    await apiPut('/applications.php?action=status&id=' + id, { status });
    showToast(status === 'accepted' ? 'Кандидат принят' : 'Кандидат отклонён', 'success');
    await loadEmployerData();
  } catch(e) { showToast(e.message, 'error'); }
}

async function cancelApp(id) {
  if (!confirm('Отозвать отклик?')) return;
  try {
    await apiDelete('/applications.php?action=delete&id=' + id);
    showToast('Отклик отозван', 'success');
    await loadApplicantData();
  } catch(e) { showToast(e.message, 'error'); }
}

init();
