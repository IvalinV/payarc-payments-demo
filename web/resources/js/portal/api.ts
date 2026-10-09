import type { Account, ApiErrorPayload, Charge, CreateChargePayload, ListResponse, LoginResponse, User } from './types';

const TOKEN_KEY = 'payarc.portal.token';
let memoryToken: string | null = null;

export class ApiError extends Error {
    code?: string;
    details?: unknown;
    status: number;

    constructor(message: string, status: number, payload: ApiErrorPayload = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = payload.error?.code;
        this.details = payload.error?.details;
    }
}

function createIdempotencyKey(): string {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    if (globalThis.crypto?.getRandomValues) {
        const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }
    throw new Error('Secure idempotency key generation is unavailable');
}

export const tokenStore = {
    get: (): string | null => memoryToken || sessionStorage.getItem(TOKEN_KEY),
    set: (token: string): void => { memoryToken = token; sessionStorage.setItem(TOKEN_KEY, token); },
    clear: () => { memoryToken = null; sessionStorage.removeItem(TOKEN_KEY); },
};

export async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
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
    if (!response.ok) throw new ApiError(body?.error?.message || 'Request failed', response.status, body);
    return body as T;
}

export const api = {
    login: (email: string, password: string): Promise<LoginResponse> => request('/api/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
    me: (): Promise<{ data: User }> => request('/api/me'),
    accounts: (): Promise<ListResponse<Account>> => request('/api/accounts'),
    charges: (query: string, options: RequestInit = {}): Promise<ListResponse<Charge>> => request(`/api/charges?${query}`, options),
    charge: (id: number | string): Promise<{ data: Charge }> => request(`/api/charges/${id}`),
    createCharge: (payload: CreateChargePayload, idempotencyKey?: string): Promise<{ data: Charge }> => request('/api/charges', { method: 'POST', headers: { 'Idempotency-Key': idempotencyKey || createIdempotencyKey() }, body: JSON.stringify(payload) }),
    refund: (id: number | string, amount: number): Promise<{ data: Charge }> => request(`/api/charges/${id}/refund`, { method: 'POST', body: JSON.stringify({ amount }) }),
    void: (id: number | string, reason: string): Promise<{ data: Charge }> => request(`/api/charges/${id}/void`, { method: 'POST', body: JSON.stringify({ reason }) }),
};
