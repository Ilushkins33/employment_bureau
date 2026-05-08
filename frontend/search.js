// search.js — поиск вакансий, резюме и сопоставление
const user = requireSession(['admin', 'employer']);
if (user) initNavbar();

function switchTab(name, btn) {
  ['vacancies', 'resumes', 'match'].forEach(t => {
    document.getElementById('tab-' + t).classList.toggle('hidden', t !== name);
  });
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

// ── Поиск вакансий ────────────────────────────────────────────────────────────

async function searchVacancies() {
  const el = document.getElementById('sv-results');
  loader(el);

  const params     = new URLSearchParams();
  const q          = document.getElementById('sv-q').value.trim();
  const location   = document.getElementById('sv-location').value.trim();
  const employment = document.getElementById('sv-employment').value;
  const experience = document.getElementById('sv-experience').value;
  const salary     = document.getElementById('sv-salary').value;

  if (q)          params.set('q',           q);
  if (location)   params.set('location',    location);
  if (employment) params.set('employment',  employment);
  if (experience) params.set('experience',  experience);
  if (salary)     params.set('salary_from', salary);

  try {
    const data = await apiGet('/search.php?action=vacancies&' + params.toString());
    document.getElementById('sv-count').textContent = `Найдено: ${data.length}`;

    if (!data.length) { empty(el, 'Вакансий не найдено', '💼'); return; }

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
        ${v.salary_from || v.salary_to ? `<div class="salary">${formatSalary(v.salary_from, v.salary_to)}</div>` : ''}
        <div class="card__footer">
          <span class="card__date">${formatDate(v.created_at)}</span>
          <a href="vacancy.html?id=${v.id}" class="btn btn--outline btn--sm">Открыть</a>
        </div>
      </div>
    `).join('');
  } catch(e) { empty(el, 'Ошибка запроса', '⚠️'); }
}

// ── Поиск резюме ──────────────────────────────────────────────────────────────

async function searchResumes() {
  const el = document.getElementById('sr-results');
  loader(el);

  const params   = new URLSearchParams();
  const q        = document.getElementById('sr-q').value.trim();
  const skill    = document.getElementById('sr-skill').value.trim();
  const location = document.getElementById('sr-location').value.trim();
  const salary   = document.getElementById('sr-salary').value;

  if (q)        params.set('q',         q);
  if (skill)    params.set('skill',     skill);
  if (location) params.set('location',  location);
  if (salary)   params.set('salary_to', salary);

  try {
    const data = await apiGet('/search.php?action=resumes&' + params.toString());
    document.getElementById('sr-count').textContent = `Найдено: ${data.length}`;

    if (!data.length) { empty(el, 'Резюме не найдены', '📄'); return; }

    el.innerHTML = `<div class="table-wrap"><table>
      <thead><tr><th>Соискатель</th><th>Должность</th><th>Навыки</th><th>Зарплата</th><th>Город</th><th>Дата</th></tr></thead>
      <tbody>${data.map(r => `<tr>
        <td>
          <strong>${escHtml(r.first_name + ' ' + r.last_name)}</strong><br>
          <span class="text-sm text-2">${escHtml(r.email)}</span>
        </td>
        <td>${escHtml(r.title)}</td>
        <td style="max-width:180px;">
          ${renderSkills(r.skills.slice(0, 4))}
          ${r.skills.length > 4 ? `<span class="text-sm text-2"> +${r.skills.length - 4}</span>` : ''}
        </td>
        <td class="salary">${r.salary_from ? 'от ' + (+r.salary_from).toLocaleString('ru') + ' ₽' : '—'}</td>
        <td>${escHtml(r.location || '—')}</td>
        <td class="text-sm text-2">${formatDate(r.created_at)}</td>
      </tr>`).join('')}</tbody>
    </table></div>`;
  } catch(e) { empty(el, 'Ошибка запроса', '⚠️'); }
}

// ── Сопоставление ─────────────────────────────────────────────────────────────

async function loadVacanciesForMatch() {
  const sel = document.getElementById('match-vacancy');
  try {
    const vacs = await apiGet('/vacancies.php?action=list');
    vacs.filter(v => v.status === 'open').forEach(v => {
      const opt = document.createElement('option');
      opt.value = v.id;
      opt.textContent = v.title + (v.company ? ' — ' + v.company : '');
      sel.appendChild(opt);
    });
  } catch(e) {}
}

async function runMatch() {
  const vacId = document.getElementById('match-vacancy').value;
  if (!vacId) { showToast('Выберите вакансию', 'error'); return; }

  const el = document.getElementById('match-results');
  loader(el, 'Анализирую кандидатов…');

  try {
    const data = await apiGet('/search.php?action=match&vacancy_id=' + vacId);
    const { vacancy, results } = data;

    if (!results.length) { empty(el, 'Подходящих кандидатов не найдено', '🔍'); return; }

    el.innerHTML = `
      <div style="margin-bottom:16px;">
        <strong>Вакансия:</strong> ${escHtml(vacancy.title)}
        ${vacancy.location ? ` · 📍 ${escHtml(vacancy.location)}` : ''}
        ${vacancy.salary_from || vacancy.salary_to ? ` · ${formatSalary(vacancy.salary_from, vacancy.salary_to)}` : ''}
      </div>
      <div style="font-size:13px; color:var(--clr-text-2); margin-bottom:16px;">
        Найдено кандидатов: <strong>${results.length}</strong>. Отсортировано по степени соответствия.
      </div>
      <div class="grid">
        ${results.map(r => `
          <div class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
              <div>
                <div class="card__title">${escHtml(r.first_name + ' ' + r.last_name)}</div>
                <div class="card__sub">${escHtml(r.title)}</div>
              </div>
              <div style="text-align:right;">
                <div class="match-score">${r.match_score}%</div>
                <div style="font-size:11px; color:var(--clr-text-3);">совпадение</div>
              </div>
            </div>
            <div class="match-bar"><div class="match-bar__fill" style="width:${r.match_score}%"></div></div>
            <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">
              ${r.location   ? `<span class="tag">📍 ${escHtml(r.location)}</span>`                              : ''}
              ${r.salary_from? `<span class="tag salary">от ${(+r.salary_from).toLocaleString('ru')} ₽</span>` : ''}
            </div>
            ${r.matched_skills && r.matched_skills.length ? `
              <div style="margin-top:10px;">
                <div style="font-size:11.5px; color:var(--clr-text-2); margin-bottom:4px;">Совпавшие навыки:</div>
                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                  ${r.matched_skills.map(s => `<span class="badge badge--green">${escHtml(s)}</span>`).join('')}
                </div>
              </div>` : ''}
            <div class="card__footer" style="margin-top:12px;">
              <span class="text-sm text-2">${escHtml(r.email)}</span>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  } catch(e) { empty(el, 'Ошибка сопоставления', '⚠️'); }
}

document.getElementById('sv-q').addEventListener('keydown', e => { if (e.key === 'Enter') searchVacancies(); });
document.getElementById('sr-q').addEventListener('keydown', e => { if (e.key === 'Enter') searchResumes(); });

searchVacancies();
loadVacanciesForMatch();
