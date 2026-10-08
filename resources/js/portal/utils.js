const CURRENCY_DIGITS = { JPY: 0, KRW: 0 };

export function formatDateTime(value) {
    const date = new Date(value);
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

export function formatMerchant(account = {}) {
    return account.business_name || account.dba_name || account.mid || account.id || 'Unknown merchant';
}

export function formatMoney(minorUnits, currency = 'USD') {
    const code = currency.toUpperCase();
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        minimumFractionDigits: CURRENCY_DIGITS[code] ?? 2,
        maximumFractionDigits: CURRENCY_DIGITS[code] ?? 2,
    }).format(Number(minorUnits) / 10 ** (CURRENCY_DIGITS[code] ?? 2));
}

export function chargeAmountValue(charge = {}) {
    return Number(charge.amount);
}

export function chargeField(charge = {}, field) {
    return charge?.[field];
}

export function toChargeQuery({ search = '', status = '', page = 1, pageSize = 25, sortModel = [], filterModel = {} } = {}) {
    const params = new URLSearchParams({ page: String(page), page_size: String(pageSize) });
    if (search.trim()) params.set('q', search.trim());
    if (status) params.set('status', status);
    const sort = sortModel[0];
    if (sort?.colId && (sort.sort === 'asc' || sort.sort === 'desc')) params.set('sort', `${sort.colId}:${sort.sort}`);
    for (const [field, filter] of Object.entries(filterModel)) {
        if (filter?.filter != null && ['status', 'currency', 'account_id'].includes(field)) params.set(field, String(filter.filter));
    }
    return params.toString();
}

export function canActOnCharge(charge, permissions = [], action) {
    if (action === 'refund') {
        return permissions.includes('charges.refund') && !charge.disputed && charge.status === 'captured' && Number(charge.amount_refunded) < Number(charge.amount);
    }
    if (action === 'void') {
        return permissions.includes('charges.void') && !charge.disputed && charge.status === 'authorized';
    }
    return false;
}

export function isGridActionClick(target) {
    return Boolean(target?.closest?.('.grid-actions'));
}

export function isCardActivationKey(key) {
    return key === 'Enter' || key === ' ';
}

export function shouldFinishDrawerClose(event) {
    return event.target === event.currentTarget;
}
