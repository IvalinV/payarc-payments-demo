const TOKEN_KEY = 'payarc.portal.token';
let memoryToken = null;

export const tokenStore = {
    get: () => memoryToken || sessionStorage.getItem(TOKEN_KEY),
    set: (token) => { memoryToken = token; sessionStorage.setItem(TOKEN_KEY, token); },
    clear: () => { memoryToken = null; sessionStorage.removeItem(TOKEN_KEY); },
};

export async function request(path, options = {}) {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
    const token = tokenStore.get();
    if (token) headers.set('Authorization', `Bearer ${token}`);

    const response = await fetch(path, { ...options, headers });
    const body = await response.json().catch(() => ({}));
    if (response.status === 401) {
        tokenStore.clear();
        window.dispatchEvent(new CustomEvent('portal:unauthorized'));
    }
    if (!response.ok) throw new Error(body?.error?.message || 'Request failed');
    return body;
}

export const api = {
    login: (email, password) => request('/api/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
    me: () => request('/api/me'),
    accounts: () => request('/api/accounts'),
    charges: (query, options = {}) => request(`/api/charges?${query}`, options),
    charge: (id) => request(`/api/charges/${id}`),
    createCharge: (payload, idempotencyKey) => request('/api/charges', { method: 'POST', headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : undefined, body: JSON.stringify(payload) }),
    refund: (id, amount) => request(`/api/charges/${id}/refund`, { method: 'POST', body: JSON.stringify({ amount }) }),
    void: (id, reason) => request(`/api/charges/${id}/void`, { method: 'POST', body: JSON.stringify({ reason }) }),
};
