import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiError, api, request, tokenStore } from './api';

describe('portal API client', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        tokenStore.clear();
    });

    it('keeps the bearer token in memory and session storage', () => {
        tokenStore.set('demo-token');
        expect(tokenStore.get()).toBe('demo-token');
        sessionStorage.removeItem('payarc.portal.token');
        expect(tokenStore.get()).toBe('demo-token');
    });

    it('sends an idempotency key when creating a charge', async () => {
        const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }));

        await api.createCharge({ account_id: 1, amount: 1000, currency: 'USD', customer_email: 'a@example.com', capture: true }, 'idem-123');

        const requestHeaders = fetchMock.mock.calls[0]?.[1]?.headers;
        expect(new Headers(requestHeaders).get('Idempotency-Key')).toBe('idem-123');
    });

    it('preserves structured API errors', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ error: { code: 'invalid_amount', message: 'Amount is invalid', details: { minimum: 1 } } }), { status: 422, headers: { 'Content-Type': 'application/json' } }));

        const error = await request('/api/test').catch((value: unknown) => value);

        expect(error).toBeInstanceOf(ApiError);
        expect(error).toMatchObject({ status: 422, code: 'invalid_amount', message: 'Amount is invalid', details: { minimum: 1 } });
    });

    it('generates a strong fallback idempotency key when UUID is unavailable', async () => {
        const originalCrypto = globalThis.crypto;
        try {
            Object.defineProperty(globalThis, 'crypto', { configurable: true, value: { getRandomValues: (bytes: Uint8Array) => bytes.fill(7) } });
            const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response('{}', { status: 200 }));

            await api.createCharge({ account_id: 1, amount: 1000, currency: 'USD', customer_email: 'a@example.com', capture: true });

            const requestHeaders = fetchMock.mock.calls[0]?.[1]?.headers;
            expect(new Headers(requestHeaders).get('Idempotency-Key')).toMatch(/^[0-9a-f-]{36}$/);
        } finally {
            Object.defineProperty(globalThis, 'crypto', { configurable: true, value: originalCrypto });
        }
    });
});
