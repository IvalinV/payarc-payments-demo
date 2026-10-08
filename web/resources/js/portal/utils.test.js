import { describe, expect, it } from 'vitest';
import { chargeAmountValue, chargeField, formatDateTime, formatMerchant, formatMoney, toChargeQuery, canActOnCharge, isCardActivationKey, isGridActionClick, shouldFinishDrawerClose } from './utils';

describe('formatDateTime', () => {
    it('formats payment timestamps with an explicit UTC timezone', () => {
        expect(formatDateTime('2026-10-06T13:23:45Z')).toBe('Oct 6, 2026, 1:23 PM UTC');
    });

    it('returns a placeholder for missing or invalid timestamps', () => {
        expect(formatDateTime(undefined)).toBe('—');
        expect(formatDateTime('not-a-date')).toBe('—');
    });
});

describe('formatMerchant', () => {
    it('prefers the business name', () => {
        expect(formatMerchant({ business_name: 'Acme Pay', dba_name: 'Acme', mid: 'MID-42', id: 'acct-42' })).toBe('Acme Pay');
    });

    it('falls back through merchant identifiers', () => {
        expect(formatMerchant({ dba_name: 'Acme', mid: 'MID-42', id: 'acct-42' })).toBe('Acme');
        expect(formatMerchant({ mid: 'MID-42', id: 'acct-42' })).toBe('MID-42');
        expect(formatMerchant({ id: 'acct-42' })).toBe('acct-42');
    });
});

describe('formatMoney', () => {
    it('formats minor units using the charge currency', () => {
        expect(formatMoney(12345, 'USD')).toBe('$123.45');
        expect(formatMoney(900, 'EUR')).toBe('€9.00');
    });
});

describe('chargeAmountValue', () => {
    it('returns the raw numeric amount for sorting', () => {
        expect(chargeAmountValue({ amount: 28900 })).toBe(28900);
        expect(chargeAmountValue({ amount: '31000' })).toBe(31000);
    });
});

describe('chargeField', () => {
    it('returns an empty value for an infinite-grid placeholder row', () => {
        expect(chargeField(undefined, 'currency')).toBeUndefined();
        expect(chargeField({ currency: 'USD' }, 'currency')).toBe('USD');
    });
});

describe('toChargeQuery', () => {
    it('maps portal filters to the API query contract', () => {
        expect(toChargeQuery({ search: 'alice', status: 'captured', page: 2, pageSize: 25 })).toBe(
            'page=2&page_size=25&q=alice&status=captured',
        );
    });

    it('maps grid sort and filter models to the API query contract', () => {
        expect(toChargeQuery({
            page: 1,
            pageSize: 100,
            sortModel: [{ colId: 'created_at', sort: 'desc' }],
            filterModel: {
                status: { filterType: 'text', type: 'equals', filter: 'captured' },
                currency: { filterType: 'text', type: 'equals', filter: 'USD' },
            },
        })).toBe('page=1&page_size=100&sort=created_at%3Adesc&status=captured&currency=USD');
    });

    it('maps all active grid sorts to the API query contract', () => {
        expect(toChargeQuery({
            sortModel: [
                { colId: 'amount', sort: 'desc' },
                { colId: 'created_at', sort: 'asc' },
            ],
        })).toBe('page=1&page_size=25&sort=amount%3Adesc%2Ccreated_at%3Aasc');
    });
});

describe('canActOnCharge', () => {
    it('only exposes state and permission compatible actions', () => {
        const charge = { status: 'captured', amount_refunded: 0, amount: 1000 };
        expect(canActOnCharge(charge, ['charges.refund'], 'refund')).toBe(true);
        expect(canActOnCharge(charge, [], 'refund')).toBe(false);
        expect(canActOnCharge(charge, ['charges.void'], 'void')).toBe(false);
        expect(canActOnCharge({ ...charge, disputed: true }, ['charges.refund'], 'refund')).toBe(false);
        expect(canActOnCharge({ status: 'authorized', disputed: true }, ['charges.void'], 'void')).toBe(false);
    });
});

describe('isGridActionClick', () => {
    it('identifies clicks inside the actions cell', () => {
        const grid = document.createElement('div');
        grid.innerHTML = '<div class="grid-actions"><button>Refund</button></div>';
        expect(isGridActionClick(grid.querySelector('button'))).toBe(true);
        expect(isGridActionClick(grid)).toBe(false);
    });
});

describe('responsive interaction helpers', () => {
    it('recognizes keyboard activation for a charge card', () => {
        expect(isCardActivationKey('Enter')).toBe(true);
        expect(isCardActivationKey(' ')).toBe(true);
        expect(isCardActivationKey('Escape')).toBe(false);
    });

    it('only finishes drawer close on the backdrop transition', () => {
        const backdrop = document.createElement('div');
        const drawer = document.createElement('aside');
        expect(shouldFinishDrawerClose({ target: drawer, currentTarget: backdrop })).toBe(false);
        expect(shouldFinishDrawerClose({ target: backdrop, currentTarget: backdrop })).toBe(true);
    });
});
