import type { Charge, QueryState } from './types';

const CURRENCY_DIGITS: Record<string, number> = { JPY: 0, KRW: 0 };

export function formatDateTime(value: string | undefined): string {
    const date = new Date(value || '');
    if (!Number.isFinite(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-US', {
        timeZone: 'UTC',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(date);
}

export function formatMerchant(account: { business_name?: string; dba_name?: string; mid?: string; id?: number | string } = {}): string {
    return String(account.business_name || account.dba_name || account.mid || account.id || 'Unknown merchant');
}

export function formatMoney(minorUnits: number | string | undefined, currency = 'USD'): string {
    const value = Number(minorUnits);
    if (!Number.isFinite(value)) return '—';
    const code = currency.toUpperCase();
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        minimumFractionDigits: CURRENCY_DIGITS[code] ?? 2,
        maximumFractionDigits: CURRENCY_DIGITS[code] ?? 2,
    }).format(value / 10 ** (CURRENCY_DIGITS[code] ?? 2));
}

export function defaultRefundAmount(charge: Partial<Charge>): string {
    const captured = Number(charge.amount_captured ?? charge.amount);
    const refunded = Number(charge.amount_refunded);
    return (Math.max(0, captured - refunded) / 100).toFixed(2);
}

export function chargeAmountValue(charge: Partial<Charge> = {}): number {
    return Number(charge.amount);
}

export function chargeField<K extends keyof Charge>(charge: Partial<Charge> | undefined, field: K): Charge[K] | undefined {
    return charge?.[field];
}

export function toChargeQuery({ search = '', status = '', page = 1, pageSize = 25, sortModel = [], filterModel = {} }: QueryState = {}): string {
    const params = new URLSearchParams({ page: String(page), page_size: String(pageSize) });
    if (search.trim()) params.set('q', search.trim());
    if (status) params.set('status', status);
    const sorts = sortModel
        .filter((sort) => sort?.colId && (sort.sort === 'asc' || sort.sort === 'desc'))
        .map((sort) => `${sort.colId}:${sort.sort}`);
    if (sorts.length) params.set('sort', sorts.join(','));
    for (const [field, filter] of Object.entries(filterModel)) {
        if (filter?.filter == null) continue;
        if (['status', 'currency', 'account_id'].includes(field)) {
            params.set(field, String(filter.filter));
        } else if (field === 'amount') {
            if (filter.type === 'lessThan' || filter.type === 'lessThanOrEqual') params.set('amount_max', String(filter.filter));
            else params.set('amount_min', String(filter.filter));
            if (filter.filterTo != null) params.set('amount_max', String(filter.filterTo));
        }
    }
    return params.toString();
}

export function canActOnCharge(charge: Partial<Charge>, permissions: string[] = [], action: 'refund' | 'void'): boolean {
    if (action === 'refund') {
        return permissions.includes('charges.refund') && !charge.disputed && charge.status === 'captured' && Number(charge.amount_refunded) < Number(charge.amount);
    }
    if (action === 'void') {
        return permissions.includes('charges.void') && !charge.disputed && charge.status === 'authorized';
    }
    return false;
}

export function isGridActionClick(target: EventTarget | null): boolean {
    return target instanceof Element && Boolean(target.closest('.grid-actions, .row-actions'));
}

export function isCardActivationKey(key: string): boolean {
    return key === 'Enter' || key === ' ';
}

export function shouldFinishDrawerClose(event: { target: EventTarget | null; currentTarget: EventTarget | null }): boolean {
    return event.target === event.currentTarget;
}
