export type Currency = 'USD' | 'EUR' | 'GBP' | 'BGN';
export type ChargeStatus = 'authorized' | 'captured' | 'refunded' | 'voided' | 'failed';
export type Permission = string;

export interface User {
    id?: number;
    email: string;
    roles?: string[];
    permissions: Permission[];
}

export interface Account {
    id: number | string;
    business_name?: string;
    dba_name?: string;
    mid?: string;
}

export interface Charge {
    id: number | string;
    account_id: number | string;
    amount: number | string;
    amount_captured?: number | string;
    amount_refunded: number | string;
    currency: Currency;
    status: ChargeStatus;
    external_transaction_id?: string;
    customer_email: string;
    disputed?: boolean;
    created_at?: string;
    description?: string;
}

export interface ApiErrorPayload {
    error?: {
        code?: string;
        message?: string;
        details?: unknown;
    };
}

export interface ListMeta {
    page?: number;
    page_size?: number;
    total?: number;
}

export interface ListResponse<T> {
    data: T[];
    meta?: ListMeta;
}

export interface LoginResponse {
    token: string;
}

export interface CreateChargePayload {
    account_id: number | string;
    amount: number;
    currency: Currency;
    customer_email: string;
    capture: boolean;
    description?: string;
}

export interface QueryState {
    search?: string;
    status?: string;
    page?: number;
    pageSize?: number;
    sortModel?: Array<{ colId?: string; sort?: 'asc' | 'desc' }>;
    filterModel?: Record<string, { filter?: string | number | null; filterTo?: string | number | null; filterType?: string; type?: string; dateFrom?: string; dateTo?: string }>;
}

export type ActionType = 'refund' | 'void';
