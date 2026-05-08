// resumes.js — база резюме (admin / employer)
const user = requireSession(['admin', 'employer']);
if (user) initNavbar();
initModals();

async function loadResumes() {
  const el = document.getElementById('resumes-list');
  loader(el);

  const params   = new URLSearchParams();
  const q        = document.getElementById('f-q').value.trim();
  const location = document.getElementById('f-location').value.trim();
  const skill    = document.getElementById('f-skill').value.trim();
  const salary   = document.getElementById('f-salary').value;

  if (q)        params.set('q',         q);
  if (location) params.set('location',  location);
  if (skill)    params.set('skill',     skill);
  if (salary)   params.set('salary_to', salary);

  try {
    const data = await apiGet('/resumes.php?action=list&' + params.toString());
    document.getElementById('res-count').textContent = `Найдено: ${data.length}`;

    if (!data.length) { empty(el, 'Резюме не найдены', '📄'); return; }

    el.innerHTML = `<div class="table-wrap"><table>
      <thead>
        <tr>
          <th>Соискатель</th>
          <th>Желаемая должность</th>
          <th>Навыки</th>
          <th>Зарплата</th>
          <th>Город</th>
          <th>Дата</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        ${data.map(r => `<tr>
          <td>
            <strong>${escHtml(r.first_name + ' ' + r.last_name)}</strong><br>
            <span class="text-sm text-2">${escHtml(r.email)}</span>
            ${r.phone ? `<br><span class="text-sm text-2">${escHtml(r.phone)}</span>` : ''}
          </td>
          <td>${escHtml(r.title)}</td>
          <td style="max-width:200px;">
            ${renderSkills(r.skills.slice(0, 4))}
            ${r.skills.length > 4 ? `<span class="text-sm text-2"> +${r.skills.length - 4}</span>` : ''}
          </td>
          <td class="salary">${r.salary_from ? 'от ' + (+r.salary_from).toLocaleString('ru') + ' ₽' : '—'}</td>
          <td>${escHtml(r.location || '—')}</td>
          <td class="text-sm text-2">${formatDate(r.created_at)}</td>
          <td><button class="btn btn--outline btn--sm" onclick="viewResume(${r.id})">Просмотр</button></td>
        </tr>`).join('')}
      </tbody>
    </table></div>`;
  } catch(e) {
    empty(el, 'Ошибка загрузки', '⚠️');
  }
}

async function viewResume(id) {
  const body = document.getElementById('modal-resume-body');
  body.innerHTML = '<div class="loader"><div class="spinner"></div>Загрузка…</div>';
  openModal('modal-resume');
  try {
    const r = await apiGet('/resumes.php?action=one&id=' + id);
    body.innerHTML = `
      <div style="margin-bottom:16px;">
        <div style="font-size:19px; font-weight:700; margin-bottom:4px;">${escHtml(r.title)}</div>
        <div style="font-size:14px; color:var(--clr-text-2);">
          ${escHtml(r.first_name + ' ' + r.last_name)}
          ${r.email ? ` · <a href="mailto:${escHtml(r.email)}">${escHtml(r.email)}</a>` : ''}
          ${r.phone ? ` · ${escHtml(r.phone)}` : ''}
        </div>
      </div>

      <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
        ${r.salary_from ? `<span class="tag salary">от ${(+r.salary_from).toLocaleString('ru')} ₽</span>` : ''}
        ${r.location    ? `<span class="tag">📍 ${escHtml(r.location)}</span>` : ''}
        ${statusBadge(r.status)}
      </div>

      ${r.skills && r.skills.length ? `
        <div style="margin-bottom:16px;">
          <div style="font-size:13px; font-weight:700; color:var(--clr-text-2); margin-bottom:6px;">НАВЫКИ</div>
          <div style="display:flex; flex-wrap:wrap; gap:6px;">${renderSkills(r.skills)}</div>
        </div>` : ''}

      <div style="margin-bottom:14px;">
        <div style="font-size:13px; font-weight:700; color:var(--clr-text-2); margin-bottom:4px;">О СЕБЕ</div>
        <div style="line-height:1.7; white-space:pre-wrap;">${escHtml(r.description)}</div>
      </div>

      ${r.experience ? `
        <div style="margin-bottom:14px;">
          <div style="font-size:13px; font-weight:700; color:var(--clr-text-2); margin-bottom:4px;">ОПЫТ РАБОТЫ</div>
          <div style="line-height:1.7; white-space:pre-wrap;">${escHtml(r.experience)}</div>
        </div>` : ''}

      ${r.education ? `
        <div style="margin-bottom:14px;">
          <div style="font-size:13px; font-weight:700; color:var(--clr-text-2); margin-bottom:4px;">ОБРАЗОВАНИЕ</div>
          <div>${escHtml(r.education)}</div>
        </div>` : ''}

      <div style="font-size:12px; color:var(--clr-text-3); margin-top:8px;">
        Добавлено: ${formatDate(r.created_at)}
      </div>
    `;
  } catch(e) {
    body.innerHTML = '<div class="empty"><div class="empty__icon">⚠️</div><div class="empty__text">Ошибка загрузки</div></div>';
  }
}

function resetFilters() {
  ['f-q', 'f-location', 'f-skill', 'f-salary'].forEach(id => document.getElementById(id).value = '');
  loadResumes();
}

document.getElementById('f-q').addEventListener('keydown', e => {
  if (e.key === 'Enter') loadResumes();
});

loadResumes();
