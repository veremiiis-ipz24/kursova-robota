// frontend/js/app.js — Менеджер контактів v2 (Ukrainian)

// ─── СТАН ────────────────────────────────────────────────
const state = {
    user: null,
    contacts: [],
    groups: [],
    users: [],
    currentPage: 'contacts',
    editingContactId: null,
    editingGroupId: null,
    passwordTargetId: null,
    confirmCallback: null,
    activeGroupId: null,
    sortBy: 'last_name',
    sortOrder: 'ASC',
    favoritesOnly: false,
    page: 1,
    perPage: 50,
    totalPages: 1,
    total: 0,
    selectedIds: new Set(),
};

// ─── УТИЛІТИ ─────────────────────────────────────────────
function $(id) { return document.getElementById(id); }

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('uk-UA', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('uk-UA', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function showAlert(containerId, msg, type = 'error') {
    const el = $(containerId);
    if (!el) return;
    el.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    setTimeout(() => { if ($(containerId)) $(containerId).innerHTML = ''; }, 5000);
}
function clearAlert(id) { if ($(id)) $(id).innerHTML = ''; }

// ─── МОДАЛЬНІ ВІКНА ──────────────────────────────────────
function openModal(id) { $(id).classList.add('open'); }
function closeModal(id) { $(id).classList.remove('open'); }

document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.close));
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(overlay.id); });
});

// Tabs inside modals
document.querySelectorAll('.modal-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const modal = tab.closest('.modal');
        modal.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
        modal.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        const pane = modal.querySelector(`#${tab.dataset.tab}`);
        if (pane) { pane.classList.add('active'); pane.style.display = 'block'; }
        // If switching to history tab, load it
        if (tab.dataset.tab === 'tab-history' && state.editingContactId) {
            loadHistory(state.editingContactId);
        }
    });
});

// ─── НАВІГАЦІЯ ───────────────────────────────────────────
function navigate(page) {
    state.currentPage = page;
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    $(`page-${page}`).classList.add('active');
    document.querySelector(`[data-page="${page}"]`)?.classList.add('active');

    const titles = {
        contacts:       'Контакти',
        groups:         'Групи',
        'import-export':'Імпорт / Експорт',
        users:          'Управління користувачами',
    };
    $('topbar-title').textContent = titles[page] || page;

    const actions = $('topbar-actions');
    actions.innerHTML = '';
    if (page === 'contacts') {
        const btn = document.createElement('button');
        btn.className = 'btn btn-primary';
        btn.textContent = '+ Додати контакт';
        btn.onclick = () => openContactModal();
        actions.appendChild(btn);
    }

    if (page === 'contacts')       loadContacts();
    if (page === 'groups')         loadGroups();
    if (page === 'users')          loadUsers();
}

document.querySelectorAll('.nav-item[data-page]').forEach(item => {
    item.addEventListener('click', () => navigate(item.dataset.page));
});

// ─── АВТЕНТИФІКАЦІЯ ──────────────────────────────────────
async function init() {
    try {
        const { user } = await Auth.me();
        if (user) { setUser(user); showApp(); }
        else showLogin();
    } catch { showLogin(); }
}

function setUser(user) {
    state.user = user;
    $('sidebar-username').textContent = user.username;
    const badge = $('sidebar-role-badge');
    badge.textContent = user.role === 'admin' ? 'адмін' : 'користувач';
    if (user.role === 'admin') {
        badge.classList.add('admin');
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = '');
    }
}

function showLogin() {
    $('login-screen').style.display = 'flex';
    $('app').style.display = 'none';
}

function showApp() {
    $('login-screen').style.display = 'none';
    $('app').style.display = 'flex';
    loadGroups();
    navigate('contacts');
}

$('btn-login').addEventListener('click', async () => {
    const username = $('login-username').value.trim();
    const password = $('login-password').value;
    if (!username || !password) {
        showAlert('login-alert', "Введіть логін та пароль.");
        return;
    }
    try {
        $('btn-login').textContent = 'Вхід…';
        const { user } = await Auth.login(username, password);
        setUser(user);
        showApp();
    } catch (e) {
        showAlert('login-alert', e.message || 'Невірні дані для входу.');
    } finally {
        $('btn-login').textContent = 'Увійти';
    }
});

[$('login-username'), $('login-password')].forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter') $('btn-login').click(); });
});

$('btn-logout').addEventListener('click', async () => {
    await Auth.logout();
    state.user = null;
    showLogin();
});

// ─── КОНТАКТИ ────────────────────────────────────────────
async function loadContacts() {
    try {
        const q = $('contacts-search').value.trim();
        const gf = $('contacts-group-filter').value;
        let result;

        if (q) {
            const contacts = await Contacts.search(q, state.favoritesOnly);
            result = { contacts, total: contacts.length, total_pages: 1, page: 1 };
        } else if (gf) {
            const contacts = await Contacts.filterByGroup(gf);
            result = { contacts, total: contacts.length, total_pages: 1, page: 1 };
        } else {
            result = await Contacts.list({
                sort: state.sortBy,
                order: state.sortOrder,
                favorites: state.favoritesOnly,
                page: state.page,
                perPage: state.perPage,
            });
        }

        state.contacts     = result.contacts;
        state.total        = result.total;
        state.totalPages   = result.total_pages || 1;
        clearSelection();
        renderContacts(result.contacts);
        renderPagination();
        updateStats();
    } catch (e) {
        console.error(e);
    }
}

function renderContacts(contacts) {
    const tbody = $('contacts-tbody');
    const empty = $('contacts-empty');
    if (!contacts.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    tbody.innerHTML = contacts.map(c => {
        const name   = `${c.first_name} ${c.last_name}`;
        const groups = (c.groups || []).map(g => `<span class="tag group">${g.name}</span>`).join('');
        const isFav  = c.favorite;
        const phone  = c.phone || '';
        const email  = c.email || '';
        return `<tr data-id="${c.id}" class="${isFav ? 'is-favorite' : ''}">
            <td><input type="checkbox" class="row-check" data-id="${c.id}" ${state.selectedIds.has(c.id) ? 'checked' : ''}/></td>
            <td>
                <button class="star-btn ${isFav ? 'active' : ''}" data-id="${c.id}" title="${isFav ? 'Видалити з улюблених' : 'Додати до улюблених'}">
                    ${isFav ? '★' : '☆'}
                </button>
            </td>
            <td><div class="name-primary">${name}</div></td>
            <td>${phone ? `<a href="tel:${phone}" style="color:var(--text-dim);text-decoration:none;">${phone}</a>` : '<span style="color:var(--text-muted)">—</span>'}</td>
            <td>${email ? `<a href="mailto:${email}" style="color:var(--text-dim);text-decoration:none;">${email}</a>` : '<span style="color:var(--text-muted)">—</span>'}</td>
            <td>${groups || '<span style="color:var(--text-muted)">—</span>'}</td>
            <td style="color:var(--text-dim);font-size:11px;">${formatDate(c.created_at)}</td>
            <td>
                <div class="actions-cell">
                    <button class="btn btn-ghost btn-sm" onclick="openContactModal(${c.id});event.stopPropagation();">Редагувати</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteContact(${c.id},'${name.replace(/'/g,"\\'")}');event.stopPropagation();">Видалити</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    // Star toggle
    tbody.querySelectorAll('.star-btn').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            const id = parseInt(btn.dataset.id);
            try {
                const { favorite } = await Contacts.toggleFavorite(id);
                const row = tbody.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    btn.classList.toggle('active', favorite);
                    btn.textContent = favorite ? '★' : '☆';
                    btn.title = favorite ? 'Видалити з улюблених' : 'Додати до улюблених';
                    row.classList.toggle('is-favorite', favorite);
                }
            } catch (err) { console.error(err); }
        });
    });

    // Row checkboxes
    tbody.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', () => {
            const id = parseInt(cb.dataset.id);
            if (cb.checked) state.selectedIds.add(id);
            else state.selectedIds.delete(id);
            updateBulkBar();
        });
    });

    // Row click → open contact
    tbody.querySelectorAll('tr').forEach(row => {
        row.addEventListener('click', e => {
            if (e.target.closest('button, input, a')) return;
            openContactModal(parseInt(row.dataset.id));
        });
    });
}

// ─── ПАГІНАЦІЯ ───────────────────────────────────────────
function renderPagination() {
    const pg = $('pagination');
    if (state.totalPages <= 1) { pg.style.display = 'none'; return; }
    pg.style.display = 'flex';

    const p = state.page, tp = state.totalPages;
    let html = `<button class="page-btn" ${p === 1 ? 'disabled' : ''} data-p="${p-1}">← Попередня</button>`;

    const pages = new Set([1, tp, p-1, p, p+1].filter(x => x >= 1 && x <= tp));
    let prev = 0;
    [...pages].sort((a,b)=>a-b).forEach(n => {
        if (prev && n - prev > 1) html += `<span class="page-ellipsis">…</span>`;
        html += `<button class="page-btn ${n === p ? 'active' : ''}" data-p="${n}">${n}</button>`;
        prev = n;
    });

    html += `<button class="page-btn" ${p === tp ? 'disabled' : ''} data-p="${p+1}">Наступна →</button>`;
    pg.innerHTML = html;
    pg.querySelectorAll('.page-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => {
            state.page = parseInt(btn.dataset.p);
            loadContacts();
        });
    });
}

// ─── ВИБІР / МАСОВІ ОПЕРАЦІЇ ─────────────────────────────
function updateBulkBar() {
    const count = state.selectedIds.size;
    $('bulk-bar').style.display = count ? 'flex' : 'none';
    $('bulk-count').textContent = `${count} вибрано`;
    // Sync select-all checkbox
    const all = state.contacts.every(c => state.selectedIds.has(c.id));
    $('select-all').checked = all && state.contacts.length > 0;
    $('select-all').indeterminate = !all && count > 0;
}

function clearSelection() {
    state.selectedIds.clear();
    updateBulkBar();
}

$('select-all').addEventListener('change', e => {
    if (e.target.checked) {
        state.contacts.forEach(c => state.selectedIds.add(c.id));
    } else {
        state.contacts.forEach(c => state.selectedIds.delete(c.id));
    }
    // Re-check all visible checkboxes
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = state.selectedIds.has(parseInt(cb.dataset.id));
    });
    updateBulkBar();
});

$('btn-bulk-cancel').addEventListener('click', () => {
    clearSelection();
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    $('select-all').checked = false;
});

$('btn-bulk-delete').addEventListener('click', () => {
    const ids = [...state.selectedIds];
    confirm_(`Видалити ${ids.length} контакт(ів)? Цю дію не можна скасувати.`, async () => {
        await Contacts.bulkDelete(ids);
        clearSelection();
        loadContacts();
    });
});

$('btn-bulk-group').addEventListener('click', () => {
    const sel = $('bulk-group-select');
    sel.innerHTML = state.groups.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
    openModal('modal-bulk-group');
});

$('btn-do-bulk-group').addEventListener('click', async () => {
    const groupId = $('bulk-group-select').value;
    if (!groupId) return;
    await Contacts.bulkAssignGroup([...state.selectedIds], groupId);
    closeModal('modal-bulk-group');
    clearSelection();
    loadContacts();
});

$('btn-bulk-export').addEventListener('click', async () => {
    const ids = [...state.selectedIds];
    const res = await ImportExport.exportSelected(ids);
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'contacts_selected.csv';
    a.click();
    URL.revokeObjectURL(url);
});

// ─── СТАТИСТИКА ──────────────────────────────────────────
function updateStats() {
    const total   = state.total;
    const grouped = state.contacts.filter(c => c.groups && c.groups.length > 0).length;
    const fav     = state.contacts.filter(c => c.favorite).length;
    $('contacts-stats').innerHTML = `
        <div class="stat-card"><div class="stat-label">Всього контактів</div><div class="stat-value">${total}</div></div>
        <div class="stat-card"><div class="stat-label">У групах</div><div class="stat-value">${grouped}</div></div>
        <div class="stat-card"><div class="stat-label">Груп</div><div class="stat-value">${state.groups.length}</div></div>
        <div class="stat-card"><div class="stat-label">Улюблених</div><div class="stat-value" style="color:#f5c518;">${fav}</div></div>
    `;
}

// ─── ПОШУК І ФІЛЬТРИ ─────────────────────────────────────
let searchTimer;
$('contacts-search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    state.page = 1;
    searchTimer = setTimeout(loadContacts, 300);
});

$('contacts-group-filter').addEventListener('change', () => { state.page = 1; loadContacts(); });

$('contacts-sort').addEventListener('change', e => {
    state.sortBy    = e.target.value;
    state.sortOrder = 'ASC';
    state.page      = 1;
    loadContacts();
});

$('filter-favorites').addEventListener('change', e => {
    state.favoritesOnly = e.target.checked;
    state.page = 1;
    loadContacts();
});

function updateGroupFilter() {
    const sel = $('contacts-group-filter');
    const cur = sel.value;
    sel.innerHTML = '<option value="">Всі групи</option>' +
        state.groups.map(g => `<option value="${g.id}" ${cur == g.id ? 'selected' : ''}>${g.name}</option>`).join('');
}

// ─── МОДАЛЬНЕ ВІКНО КОНТАКТУ ─────────────────────────────
async function openContactModal(id = null) {
    state.editingContactId = id;
    clearAlert('modal-contact-alert');
    $('modal-contact-title').textContent = id ? 'Редагувати контакт' : 'Новий контакт';

    // Reset tabs to "Info"
    document.querySelectorAll('#modal-contact .modal-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('#modal-contact .tab-pane').forEach(p => { p.classList.remove('active'); p.style.display = 'none'; });
    document.querySelector('[data-tab="tab-info"]').classList.add('active');
    $('tab-info').classList.add('active');
    $('tab-info').style.display = 'block';

    // Group checkboxes
    const cbWrap = $('cf-groups-checkboxes');
    cbWrap.innerHTML = state.groups.length
        ? state.groups.map(g =>
            `<label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;font-size:12px;color:var(--text-dim);text-transform:none;letter-spacing:0;">
                <input type="checkbox" value="${g.id}" style="width:auto;accent-color:var(--accent);"> ${g.name}
            </label>`
        ).join('')
        : '<span style="color:var(--text-muted);font-size:12px;">Груп немає. Спочатку створіть групу.</span>';

    // Quick actions
    $('quick-actions').style.display = id ? 'block' : 'none';

    if (id) {
        try {
            const c = await Contacts.get(id);
            $('cf-first_name').value  = c.first_name;
            $('cf-last_name').value   = c.last_name;
            $('cf-phone').value       = c.phone || '';
            $('cf-email').value       = c.email || '';
            $('cf-note').value        = c.note  || '';
            $('cf-favorite').checked  = !!c.favorite;

            // Quick actions links
            const phone = (c.phone || '').replace(/\s/g, '');
            const qaCall = $('qa-call');
            if (phone) { qaCall.href = `tel:${phone}`; qaCall.style.display = ''; }
            else qaCall.style.display = 'none';

            const qaEmail = $('qa-email');
            if (c.email) { qaEmail.href = `mailto:${c.email}`; qaEmail.style.display = ''; }
            else qaEmail.style.display = 'none';

            const qaTg = $('qa-telegram');
            if (phone && phone.startsWith('+')) { qaTg.href = `https://t.me/${phone}`; qaTg.style.display = ''; }
            else qaTg.style.display = 'none';

            // Group checkboxes
            const assignedIds = (c.groups || []).map(g => String(g.id));
            cbWrap.querySelectorAll('input[type=checkbox]').forEach(cb => {
                if (assignedIds.includes(cb.value)) cb.checked = true;
            });
        } catch (e) {
            showAlert('modal-contact-alert', e.message);
        }
    } else {
        ['cf-first_name','cf-last_name','cf-phone','cf-email','cf-note'].forEach(fid => $(fid).value = '');
        $('cf-favorite').checked = false;
    }
    openModal('modal-contact');
}

async function loadHistory(contactId) {
    $('history-list').innerHTML = '<div class="empty"><div class="empty-icon">◷</div>Завантаження…</div>';
    try {
        const history = await Contacts.history(contactId);
        if (!history.length) {
            $('history-list').innerHTML = '<div class="empty"><div class="empty-icon">◷</div>Історія змін відсутня.</div>';
            return;
        }
        $('history-list').innerHTML = history.map(h => `
            <div class="history-item">
                <div class="history-dot"></div>
                <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <span class="history-action">${h.action}</span>
                        <span class="history-user">${h.username}</span>
                    </div>
                    <div class="history-time">${formatDateTime(h.created_at)}</div>
                </div>
            </div>`).join('');
    } catch (e) {
        $('history-list').innerHTML = `<div class="alert alert-error">${e.message}</div>`;
    }
}

$('btn-save-contact').addEventListener('click', async () => {
    const data = {
        first_name: $('cf-first_name').value.trim(),
        last_name:  $('cf-last_name').value.trim(),
        phone:      $('cf-phone').value.trim(),
        email:      $('cf-email').value.trim(),
        note:       $('cf-note').value.trim(),
        favorite:   $('cf-favorite').checked,
    };
    const selectedGroups = [...($('cf-groups-checkboxes').querySelectorAll('input:checked'))].map(cb => cb.value);
    clearAlert('modal-contact-alert');

    try {
        let contact;
        if (state.editingContactId) {
            contact = await Contacts.update(state.editingContactId, data);
            // Sync group memberships
            const prev = await Contacts.get(contact.id);
            const prevIds = (prev.groups || []).map(g => String(g.id));
            for (const gid of selectedGroups) {
                if (!prevIds.includes(gid)) await Contacts.assignGroup(contact.id, gid);
            }
            for (const gid of prevIds) {
                if (!selectedGroups.includes(gid)) await Contacts.removeGroup(contact.id, gid);
            }
        } else {
            contact = await Contacts.create(data);
            for (const gid of selectedGroups) {
                await Contacts.assignGroup(contact.id, gid);
            }
        }
        closeModal('modal-contact');
        loadContacts();
    } catch (e) {
        showAlert('modal-contact-alert', e.message);
    }
});

function deleteContact(id, name) {
    confirm_(`Видалити контакт «${name}»? Цю дію не можна скасувати.`, async () => {
        await Contacts.delete(id);
        loadContacts();
    });
}

// ─── ГРУПИ ───────────────────────────────────────────────
async function loadGroups() {
    try {
        state.groups = await Groups.list();
        renderGroupsList();
        updateGroupFilter();
    } catch (e) { console.error(e); }
}

function renderGroupsList() {
    const body = $('groups-list-body');
    if (!state.groups.length) {
        body.innerHTML = '<div class="empty" style="padding:20px;">Груп ще немає.</div>';
        return;
    }
    body.innerHTML = state.groups.map(g =>
        `<div class="group-item ${state.activeGroupId === g.id ? 'active' : ''}" data-gid="${g.id}">
            <span>${g.name}</span>
            <div style="display:flex;gap:4px;align-items:center;">
                <button class="btn btn-ghost btn-sm" onclick="openGroupModal(${g.id});event.stopPropagation();" title="Редагувати">✎</button>
                <button class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="deleteGroup(${g.id},'${g.name.replace(/'/g,"\\'")}');event.stopPropagation();" title="Видалити">✕</button>
            </div>
        </div>`
    ).join('');
    body.querySelectorAll('.group-item').forEach(el => {
        el.addEventListener('click', () => loadGroupContacts(parseInt(el.dataset.gid)));
    });
}

async function loadGroupContacts(groupId) {
    state.activeGroupId = groupId;
    renderGroupsList();
    const group = state.groups.find(g => g.id === groupId);
    $('group-contacts-title').textContent = group ? group.name : 'Група';

    const actionsDiv = $('group-contacts-actions');
    actionsDiv.innerHTML = `
        <a href="${Groups.exportUrl(groupId)}" class="btn btn-secondary btn-sm">↓ Експортувати групу</a>
        <button class="btn btn-secondary btn-sm" onclick="openAssignGroupModal(${groupId})">+ Додати контакт</button>`;

    try {
        const contacts = await Contacts.filterByGroup(groupId);
        if (!contacts.length) {
            $('group-contacts-body').innerHTML = '<div class="empty"><div class="empty-icon">◈</div>У цій групі ще немає контактів.</div>';
            return;
        }
        $('group-contacts-body').innerHTML = `<div class="table-wrap"><table>
            <thead><tr><th>Ім'я</th><th>Телефон</th><th>Email</th><th></th></tr></thead>
            <tbody>${contacts.map(c => `
                <tr>
                    <td>${c.first_name} ${c.last_name} ${c.favorite ? '<span style="color:#f5c518;">★</span>' : ''}</td>
                    <td>${c.phone ? `<a href="tel:${c.phone}" style="color:var(--text-dim);text-decoration:none;">${c.phone}</a>` : '—'}</td>
                    <td>${c.email ? `<a href="mailto:${c.email}" style="color:var(--text-dim);text-decoration:none;">${c.email}</a>` : '—'}</td>
                    <td><button class="btn btn-ghost btn-sm" style="color:var(--danger);"
                        onclick="removeFromGroup(${c.id},${groupId})">Видалити</button></td>
                </tr>`).join('')}
            </tbody>
        </table></div>`;
    } catch (e) { console.error(e); }
}

async function removeFromGroup(contactId, groupId) {
    await Contacts.removeGroup(contactId, groupId);
    loadGroupContacts(groupId);
}

function openGroupModal(id = null) {
    state.editingGroupId = id;
    clearAlert('modal-group-alert');
    $('modal-group-title').textContent = id ? 'Редагувати групу' : 'Нова група';
    const group = id ? state.groups.find(g => g.id === id) : null;
    $('gf-name').value = group ? group.name : '';
    openModal('modal-group');
}

$('btn-new-group').addEventListener('click', () => openGroupModal());

$('btn-save-group').addEventListener('click', async () => {
    const name = $('gf-name').value.trim();
    clearAlert('modal-group-alert');
    try {
        if (state.editingGroupId) await Groups.update(state.editingGroupId, name);
        else await Groups.create(name);
        closeModal('modal-group');
        await loadGroups();
        if (state.editingGroupId && state.activeGroupId === state.editingGroupId) {
            loadGroupContacts(state.editingGroupId);
        }
    } catch (e) { showAlert('modal-group-alert', e.message); }
});

function deleteGroup(id, name) {
    confirm_(`Видалити групу «${name}»? Контакти не будуть видалені.`, async () => {
        await Groups.delete(id);
        if (state.activeGroupId === id) {
            state.activeGroupId = null;
            $('group-contacts-title').textContent = 'Виберіть групу';
            $('group-contacts-body').innerHTML = '<div class="empty"><div class="empty-icon">⬡</div>Виберіть групу зліва.</div>';
            $('group-contacts-actions').innerHTML = '';
        }
        loadGroups();
    });
}

function openAssignGroupModal(groupId) {
    const sel = $('assign-group-select');
    Promise.all([Contacts.list({ perPage: 1000 }), Contacts.filterByGroup(groupId)]).then(([allResult, inGroup]) => {
        const all = allResult.contacts || [];
        const inGroupIds = inGroup.map(c => c.id);
        const available = all.filter(c => !inGroupIds.includes(c.id));
        if (!available.length) {
            sel.innerHTML = '<option disabled>Всі контакти вже в цій групі</option>';
        } else {
            sel.innerHTML = available.map(c => `<option value="${c.id}">${c.first_name} ${c.last_name}</option>`).join('');
        }
        $('btn-do-assign-group').onclick = async () => {
            const contactId = sel.value;
            if (contactId) {
                await Contacts.assignGroup(contactId, groupId);
                closeModal('modal-assign-group');
                loadGroupContacts(groupId);
            }
        };
        openModal('modal-assign-group');
    });
}

// ─── КОРИСТУВАЧІ ─────────────────────────────────────────
async function loadUsers() {
    try {
        state.users = await Users.list();
        renderUsers();
    } catch (e) { console.error(e); }
}

function renderUsers() {
    const tbody = $('users-tbody');
    if (!state.users.length) {
        tbody.innerHTML = '';
        $('users-empty').style.display = 'block';
        return;
    }
    $('users-empty').style.display = 'none';
    tbody.innerHTML = state.users.map(u => `
        <tr>
            <td><span style="font-family:var(--font-mono);font-size:12px;">${u.username}</span></td>
            <td><span class="tag">${u.role === 'admin' ? 'адмін' : 'користувач'}</span></td>
            <td>
                <div class="actions-cell" style="opacity:1;">
                    <button class="btn btn-secondary btn-sm" onclick="openChangePassword(${u.id})">Змінити пароль</button>
                    ${u.role !== 'admin' ? `<button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id},'${u.username}')">Видалити</button>` : ''}
                </div>
            </td>
        </tr>`).join('');
}

$('btn-new-user').addEventListener('click', () => {
    clearAlert('modal-user-alert');
    $('uf-username').value = '';
    $('uf-password').value = '';
    $('uf-role').value = 'user';
    openModal('modal-user');
});

$('btn-save-user').addEventListener('click', async () => {
    clearAlert('modal-user-alert');
    try {
        await Users.create($('uf-username').value.trim(), $('uf-password').value, $('uf-role').value);
        closeModal('modal-user');
        loadUsers();
    } catch (e) { showAlert('modal-user-alert', e.message); }
});

function openChangePassword(id) {
    state.passwordTargetId = id;
    $('pf-password').value = '';
    clearAlert('modal-password-alert');
    openModal('modal-password');
}

$('btn-save-password').addEventListener('click', async () => {
    clearAlert('modal-password-alert');
    try {
        await Users.changePassword(state.passwordTargetId, $('pf-password').value);
        closeModal('modal-password');
        showAlert('topbar-actions', 'Пароль оновлено.', 'success');
    } catch (e) { showAlert('modal-password-alert', e.message); }
});

function deleteUser(id, username) {
    confirm_(`Видалити користувача «${username}»? Всі його контакти також будуть видалені.`, async () => {
        await Users.delete(id);
        loadUsers();
    });
}

// ─── ІМПОРТ / ЕКСПОРТ ────────────────────────────────────
$('btn-export').addEventListener('click', () => {
    window.location.href = ImportExport.exportUrl();
});

$('btn-download-template').addEventListener('click', () => {
    const csv = '\uFEFFfirst_name,last_name,phone,email,note\nІван,Петренко,+380501234567,ivan@example.com,Клієнт\n';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contacts_template.csv';
    a.click();
});

const uploadArea = $('upload-area');
const fileInput  = $('csv-file-input');
uploadArea.addEventListener('click', () => fileInput.click());
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        uploadArea.innerHTML = `<div style="font-size:20px;margin-bottom:6px;">📄</div>${e.dataTransfer.files[0].name}`;
    }
});
fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) {
        uploadArea.innerHTML = `<div style="font-size:20px;margin-bottom:6px;">📄</div>${fileInput.files[0].name}`;
    }
});

$('btn-import').addEventListener('click', async () => {
    const file = fileInput.files[0];
    if (!file) { $('import-results').innerHTML = '<div class="alert alert-error">Оберіть CSV-файл.</div>'; return; }
    $('btn-import').textContent = 'Імпортуємо…';
    try {
        const result = await ImportExport.import(file);
        let html = `<div class="alert alert-success">Імпортовано ${result.imported} контакт(ів) успішно.</div>`;
        if (result.errors && result.errors.length) {
            html += `<div class="import-results">${result.errors.map(e => `<div class="err-item">⚠ ${e}</div>`).join('')}</div>`;
        }
        $('import-results').innerHTML = html;
        if (result.imported > 0) loadContacts();
    } catch (e) {
        $('import-results').innerHTML = `<div class="alert alert-error">${e.message}</div>`;
    } finally {
        $('btn-import').textContent = 'Імпортувати';
    }
});

// ─── ПІДТВЕРДЖЕННЯ ───────────────────────────────────────
function confirm_(msg, cb) {
    $('confirm-message').textContent = msg;
    state.confirmCallback = cb;
    openModal('modal-confirm');
}

$('btn-confirm-yes').addEventListener('click', async () => {
    closeModal('modal-confirm');
    if (state.confirmCallback) { await state.confirmCallback(); state.confirmCallback = null; }
});

// ─── ГАРЯЧІ КЛАВІШІ ──────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        $('contacts-search').focus();
    }
});

// ─── ЗАПУСК ──────────────────────────────────────────────
init();
