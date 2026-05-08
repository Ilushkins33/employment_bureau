// interviews.js — модуль собеседований
const user = requireSession();
if (user) initNavbar();

const FORMAT_LABELS = { office: 'Офис', online: 'Онлайн', phone: 'Телефон' };
const STATUS_LABELS = { scheduled: 'Запланировано', completed: 'Состоялось', cancelled: 'Отменено' };
const STATUS_CLASS  = { scheduled: 'badge--blue', completed: 'badge--green', cancelled: 'badge--gray' };

async function load() {
  const list = document.getElementById('interviews-list');
  const status = document.getElementById('filter-status').value;

  list.innerHTML = '<p class="text-secondary">Загрузка…</p>';

  try {
    const params = new URLSearchParams({ action: 'list' });
    if (status) params.set('status', status);
    const items = await apiGet('/interviews.php?' + params);

    if (!items.length) {
      list.innerHTML = '<p class="text-secondary">Собеседований пока нет.</p>';
      return;
    }

    list.innerHTML = items.map(iv => renderCard(iv)).join('');
  } catch (e) {
    list.innerHTML = `<p class="text-secondary">${escHtml(e.message)}</p>`;
  }
}

function renderCard(iv) {
  const dt = new Date(iv.scheduled_at).toLocaleString('ru-RU', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });

  const canManage = user.role === 'employer' || user.role === 'admin';

  return `
  <div class="card" style="margin-bottom:12px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div style="font-weight:500; font-size:15px; margin-bottom:4px;">${escHtml(iv.vacancy_title)}</div>
        <div style="font-size:13px; color:var(--color-text-secondary); margin-bottom:6px;">
          Кандидат: ${escHtml(iv.applicant_first + ' ' + iv.applicant_last)}
          ${iv.applicant_phone ? '· ' + escHtml(iv.applicant_phone) : ''}
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:6px;">
          <span class="badge ${STATUS_CLASS[iv.status]}">${STATUS_LABELS[iv.status]}</span>
          <span class="badge badge--outline">${FORMAT_LABELS[iv.format]}</span>
        </div>
        <div style="font-size:13px;">
          <strong>Дата:</strong> ${escHtml(dt)}
          ${iv.location ? ' · <strong>Место:</strong> ' + escHtml(iv.location) : ''}
        </div>
        ${iv.notes ? `<div style="font-size:13px; color:var(--color-text-secondary); margin-top:4px;">${escHtml(iv.notes)}</div>` : ''}
      </div>
      ${canManage && iv.status === 'scheduled' ? `
      <div style="display:flex; gap:8px; flex-shrink:0;">
        <button class="btn btn--sm btn--outline" onclick="openUpdate(${JSON.stringify(iv).replace(/"/g,'&quot;')})">Изменить</button>
        <button class="btn btn--sm btn--danger" onclick="cancelInterview(${iv.id})">Отменить</button>
      </div>` : ''}
    </div>
  </div>`;
}

// ── Назначить собеседование (вызывается из dashboard.js) ─────────────────────

function openSchedule(applicationId) {
  document.getElementById('f-application-id').value = applicationId;
  document.getElementById('f-scheduled-at').value = '';
  document.getElementById('f-format').value = 'office';
  document.getElementById('f-location').value = '';
  document.getElementById('f-notes').value = '';
  document.getElementById('modal-schedule').style.display = 'flex';
}

function closeModal() {
  document.getElementById('modal-schedule').style.display = 'none';
}

async function submitSchedule(e) {
  e.preventDefault();
  try {
    await apiPost('/interviews.php?action=schedule', {
      application_id: parseInt(document.getElementById('f-application-id').value),
      scheduled_at:   document.getElementById('f-scheduled-at').value,
      format:         document.getElementById('f-format').value,
      location:       document.getElementById('f-location').value,
      notes:          document.getElementById('f-notes').value,
    });
    showToast('Собеседование назначено', 'success');
    closeModal();
    load();
  } catch (e) {
    showToast(e.message, 'error');
  }
}

// ── Редактировать ─────────────────────────────────────────────────────────────

function openUpdate(iv) {
  document.getElementById('u-id').value = iv.id;
  document.getElementById('u-status').value = iv.status;
  document.getElementById('u-scheduled-at').value = iv.scheduled_at.replace(' ', 'T').slice(0, 16);
  document.getElementById('u-location').value = iv.location || '';
  document.getElementById('u-notes').value = iv.notes || '';
  document.getElementById('modal-update').style.display = 'flex';
}

function closeUpdateModal() {
  document.getElementById('modal-update').style.display = 'none';
}

async function submitUpdate(e) {
  e.preventDefault();
  const id = document.getElementById('u-id').value;
  try {
    await apiPut(`/interviews.php?action=update&id=${id}`, {
      status:       document.getElementById('u-status').value,
      scheduled_at: document.getElementById('u-scheduled-at').value,
      location:     document.getElementById('u-location').value,
      notes:        document.getElementById('u-notes').value,
    });
    showToast('Изменения сохранены', 'success');
    closeUpdateModal();
    load();
  } catch (e) {
    showToast(e.message, 'error');
  }
}

// ── Отменить ──────────────────────────────────────────────────────────────────

async function cancelInterview(id) {
  if (!confirm('Отменить это собеседование?')) return;
  try {
    await apiDelete(`/interviews.php?action=cancel&id=${id}`);
    showToast('Собеседование отменено', 'info');
    load();
  } catch (e) {
    showToast(e.message, 'error');
  }
}

// ── Инициализация ─────────────────────────────────────────────────────────────

document.getElementById('filter-status').addEventListener('change', load);

// Если пришли с дашборда через кнопку «Собеседование» — сразу открываем модалку
const pendingAppId = sessionStorage.getItem('schedule_app_id');
if (pendingAppId && (user.role === 'employer' || user.role === 'admin')) {
  sessionStorage.removeItem('schedule_app_id');
  openSchedule(parseInt(pendingAppId));
}

load();
