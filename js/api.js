// frontend/js/api.js — API client (Ukrainian Contact Manager)

const API_BASE = 'backend/api';

async function apiFetch(endpoint, options = {}) {
    const res = await fetch(`${API_BASE}/${endpoint}`, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options,
    });
    if (options._raw) return res;
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch { data = { error: text }; }
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
}

// ─── AUTH ─────────────────────────────────────────────────
const Auth = {
    login:  (username, password) =>
        apiFetch('auth.php?action=login', { method: 'POST', body: JSON.stringify({ username, password }) }),
    logout: () => apiFetch('auth.php?action=logout', { method: 'POST' }),
    me:     () => apiFetch('auth.php?action=me'),
};

// ─── CONTACTS ─────────────────────────────────────────────
const Contacts = {
    list: (params = {}) => {
        const q = new URLSearchParams({
            sort:      params.sort     || 'last_name',
            order:     params.order    || 'ASC',
            page:      params.page     || 1,
            per_page:  params.perPage  || 50,
            ...(params.favorites ? { favorites: 1 } : {}),
        });
        return apiFetch(`contacts.php?${q}`);
    },
    search:        (q, favorites = false) =>
        apiFetch(`contacts.php?search=${encodeURIComponent(q)}${favorites ? '&favorites=1' : ''}`),
    filterByGroup: (groupId) => apiFetch(`contacts.php?group_id=${groupId}`),
    get:           (id) => apiFetch(`contacts.php?id=${id}`),
    history:       (id) => apiFetch(`contacts.php?id=${id}&action=history`),
    create:        (data) => apiFetch('contacts.php', { method: 'POST', body: JSON.stringify(data) }),
    update:        (id, data) => apiFetch(`contacts.php?id=${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    delete:        (id) => apiFetch(`contacts.php?id=${id}`, { method: 'DELETE' }),
    toggleFavorite:(id) =>
        apiFetch(`contacts.php?id=${id}`, { method: 'PATCH', body: JSON.stringify({ action: 'toggle_favorite' }) }),
    assignGroup:   (contactId, groupId) =>
        apiFetch(`contacts.php?id=${contactId}`, { method: 'PATCH', body: JSON.stringify({ action: 'assign_group', group_id: groupId }) }),
    removeGroup:   (contactId, groupId) =>
        apiFetch(`contacts.php?id=${contactId}&action=ungroup&group_id=${groupId}`, { method: 'DELETE' }),
    bulkDelete:    (ids) =>
        apiFetch('contacts.php', { method: 'POST', body: JSON.stringify({ bulk_action: 'delete', ids }) }),
    bulkAssignGroup: (ids, groupId) =>
        apiFetch('contacts.php', { method: 'POST', body: JSON.stringify({ bulk_action: 'assign_group', ids, group_id: groupId }) }),
};

// ─── GROUPS ───────────────────────────────────────────────
const Groups = {
    list:        () => apiFetch('groups.php'),
    create:      (name) => apiFetch('groups.php', { method: 'POST', body: JSON.stringify({ name }) }),
    update:      (id, name) => apiFetch(`groups.php?id=${id}`, { method: 'PUT', body: JSON.stringify({ name }) }),
    delete:      (id) => apiFetch(`groups.php?id=${id}`, { method: 'DELETE' }),
    exportUrl:   (id) => `${API_BASE}/groups.php?id=${id}&action=export`,
};

// ─── USERS ────────────────────────────────────────────────
const Users = {
    list:           () => apiFetch('users.php'),
    create:         (username, password, role) =>
        apiFetch('users.php', { method: 'POST', body: JSON.stringify({ username, password, role }) }),
    delete:         (id) => apiFetch(`users.php?id=${id}`, { method: 'DELETE' }),
    changePassword: (id, password) =>
        apiFetch(`users.php?id=${id}`, { method: 'PATCH', body: JSON.stringify({ password }) }),
};

// ─── IMPORT / EXPORT ──────────────────────────────────────
const ImportExport = {
    exportUrl:      () => `${API_BASE}/importexport.php?action=export`,
    exportSelected: (ids) =>
        fetch(`${API_BASE}/importexport.php?action=export_selected`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids }),
        }),
    import: (file) => {
        const fd = new FormData();
        fd.append('csv_file', file);
        return fetch(`${API_BASE}/importexport.php?action=import`, {
            method: 'POST', credentials: 'include', body: fd,
        }).then(r => r.json());
    },
};
