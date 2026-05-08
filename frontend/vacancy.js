// vacancy.js — карточка вакансии с откликом
initNavbar();
initModals();

const vacId = new URLSearchParams(location.search).get('id');
if (!vacId) window.location.href = 'vacancies.html';

const user = getUser();

async function loadVacancy() {
  const el = document.getElementById('vacancy-content');
  try {
    const v = await apiGet('/vacancies.php?action=one&id=' + vacId);

    el.innerHTML = `
      <div class="detail">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:12px;">
          <div>
            <h1 class="detail__title">${escHtml(v.title)}</h1>
            <div style="font-size:15px; color:var(--clr-text-2); margin-bottom:10px;">
              ${escHtml(v.company || v.first_name + ' ' + v.last_name)}
            </div>
          </div>
          <div>${statusBadge(v.status)}</div>
        </div>

        <div class="detail__meta">
          ${v.salary_from || v.salary_to ? `<span class="tag salary" style="font-size:15px;">${formatSalary(v.salary_from, v.salary_to)}</span>` : ''}
          ${v.location   ? `<span class="tag">📍 ${escHtml(v.location)}</span>`       : ''}
          ${v.employment ? `<span class="tag">${employmentLabel(v.employment)}</span>` : ''}
          ${v.category   ? `<span class="tag">${escHtml(v.category)}</span>`           : ''}
          ${v.experience ? `<span class="tag">Опыт: ${escHtml(v.experience)}</span>`  : ''}
        </div>

        <div class="detail__section">
          <h3>Описание</h3>
          <div class="detail__body">${escHtml(v.description)}</div>
        </div>

        <div class="detail__section" style="border-top:1px solid var(--clr-border); padding-top:16px; margin-top:16px;">
          <h3>Контакты работодателя</h3>
          <div style="font-size:13.5px; color:var(--clr-text-2); line-height:2;">
            ${v.employer_email ? `📧 <a href="mailto:${escHtml(v.employer_email)}">${escHtml(v.employer_email)}</a><br>` : ''}
            ${v.phone          ? `📞 ${escHtml(v.phone)}<br>` : ''}
            📅 Опубликовано: ${formatDate(v.created_at)}
            ${v.applications_count ? `&nbsp;·&nbsp; Откликов: ${v.applications_count}` : ''}
          </div>
        </div>

        <div id="apply-block" style="margin-top:24px;"></div>
      </div>
    `;

    renderApplyBlock(v);
  } catch(e) {
    el.innerHTML = `<div class="empty"><div class="empty__icon">⚠️</div><div class="empty__text">Вакансия не найдена</div></div>`;
  }
}

function renderApplyBlock(v) {
  const el = document.getElementById('apply-block');
  if (!user) {
    el.innerHTML = `<a href="login.html" class="btn btn--primary btn--lg">Войти, чтобы откликнуться</a>`;
    return;
  }
  if (user.role !== 'applicant') return;
  if (v.status !== 'open') {
    el.innerHTML = `<div class="badge badge--grey" style="font-size:14px;">Вакансия закрыта</div>`;
    return;
  }
  el.innerHTML = `<button class="btn btn--primary btn--lg" onclick="openApplyModal('${escHtml(v.title)}')">📩 Откликнуться</button>`;
}

async function openApplyModal(title) {
  document.getElementById('apply-vid').value          = vacId;
  document.getElementById('apply-vname').textContent  = title;
  document.getElementById('apply-letter').value       = '';

  const sel = document.getElementById('apply-resume');
  sel.innerHTML = '<option value="">— Без резюме —</option>';
  try {
    const resumes = await apiGet('/resumes.php?action=my');
    resumes.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.title;
      sel.appendChild(opt);
    });
  } catch(e) {}

  openModal('modal-apply');
}

async function submitApply() {
  const vacancyId   = document.getElementById('apply-vid').value;
  const resumeId    = document.getElementById('apply-resume').value || null;
  const coverLetter = document.getElementById('apply-letter').value.trim();

  try {
    await apiPost('/applications.php?action=apply', {
      vacancy_id:   +vacancyId,
      resume_id:    resumeId ? +resumeId : null,
      cover_letter: coverLetter,
    });
    closeModal('modal-apply');
    showToast('Отклик отправлен!', 'success');
    document.getElementById('apply-block').innerHTML =
      `<div class="badge badge--green" style="font-size:14px;">✓ Вы откликнулись на эту вакансию</div>`;
  } catch(e) { showToast(e.message, 'error'); }
}

loadVacancy();
