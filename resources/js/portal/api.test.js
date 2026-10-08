import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, tokenStore } from './api';

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

        await api.createCharge({ account_id: 1, amount: 1000, currency: 'USD' }, 'idem-123');

        expect(fetchMock.mock.calls[0][1].headers.get('Idempotency-Key')).toBe('idem-123');
    });
});
