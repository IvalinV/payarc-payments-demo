import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent, type ReactNode, type RefObject } from 'react';
import { AgGridReact } from '@ag-grid-community/react';
import { ModuleRegistry, type ColDef, type GridApi, type GridReadyEvent, type ICellRendererParams, type IDatasource, type IGetRowsParams } from '@ag-grid-community/core';
import { InfiniteRowModelModule } from '@ag-grid-community/infinite-row-model';
import { api, tokenStore } from './api';
import { canActOnCharge, chargeAmountValue, chargeField, defaultRefundAmount, formatDateTime, formatMerchant, formatMoney, isCardActivationKey, isGridActionClick, shouldFinishDrawerClose, toChargeQuery } from './utils';
import type { Account, ActionType, Charge, Currency, ListMeta, QueryState, User } from './types';
import '@ag-grid-community/styles/ag-grid.css';
import '@ag-grid-community/styles/ag-theme-quartz.css';

ModuleRegistry.registerModules([InfiniteRowModelModule]);

function errorMessage(error: unknown): string {
    return error instanceof Error ? error.message : 'Request failed';
}

function useDialogFocus<T extends HTMLElement>(open: boolean, onClose: () => void): RefObject<T> {
    const dialogRef = useRef<T>(null);
    const closeRef = useRef(onClose);
    closeRef.current = onClose;
    useEffect(() => {
        if (!open || !dialogRef.current) return undefined;
        const dialog = dialogRef.current;
        const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const focusable = dialog.querySelector<HTMLElement>('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        (focusable || dialog).focus();
        function handleKeyDown(event: globalThis.KeyboardEvent): void {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeRef.current();
                return;
            }
            if (event.key !== 'Tab') return;
            const elements = Array.from(dialog.querySelectorAll<HTMLElement>('button, input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter((element) => !element.hasAttribute('disabled'));
            if (elements.length === 0) {
                event.preventDefault();
                dialog.focus();
                return;
            }
            const first = elements[0];
            const last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
        dialog.addEventListener('keydown', handleKeyDown);
        return () => {
            dialog.removeEventListener('keydown', handleKeyDown);
            previous?.focus();
        };
    }, [open]);
    return dialogRef;
}

function ErrorMessage({ message }: { message: string }): ReactNode {
    return message ? <div className="alert alert-error" role="alert" aria-live="polite">{message}</div> : null;
}

function Toast({ message }: { message: string }): ReactNode {
    return message ? <div className="toast" role="status" aria-live="polite">{message}</div> : null;
}

function Login({ onLogin }: { onLogin: () => Promise<void> }): ReactNode {
    const [email, setEmail] = useState('admin@payarc.test');
    const [password, setPassword] = useState('Demo#12345');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setLoading(true);
        setError('');
        try {
            const result = await api.login(email, password);
            tokenStore.set(result.token);
            await onLogin();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setLoading(false);
        }
    }

    return <main className="auth-layout"><form className="auth-card" onSubmit={submit}>
        <div className="eyebrow">PAYARC / OPERATIONS</div><h1>Payments, in focus.</h1>
        <p className="muted">Sign in to manage transactions across your connected accounts.</p>
        <ErrorMessage message={error} />
        <label htmlFor="login-email">Email<input id="login-email" name="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} type="email" required /></label>
        <label htmlFor="login-password">Password<input id="login-password" name="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} type="password" required /></label>
        <button className="button button-primary" disabled={loading} aria-busy={loading}>{loading ? 'Signing in…' : 'Sign in'}</button>
    </form></main>;
}

function StatusBadge({ status }: { status: string }): ReactNode {
    return <span className={`status status-${status}`}>{status}</span>;
}

function ChargeActions({ charge, permissions, onRefund, onVoid }: { charge: Charge; permissions: string[]; onRefund: (charge: Charge) => void; onVoid: (charge: Charge) => void }): ReactNode {
    return <div className="row-actions">
        {canActOnCharge(charge, permissions, 'refund') && <button className="button button-small" onClick={(event) => { event.stopPropagation(); onRefund(charge); }}>Refund</button>}
        {canActOnCharge(charge, permissions, 'void') && <button className="button button-small button-danger" onClick={(event) => { event.stopPropagation(); onVoid(charge); }}>Void</button>}
    </div>;
}

function ChargeCard({ charge, merchant, permissions, onSelect, onRefund, onVoid }: { charge: Charge; merchant: string; permissions: string[]; onSelect: (charge: Charge) => void; onRefund: (charge: Charge) => void; onVoid: (charge: Charge) => void }): ReactNode {
    function activateCard(event: KeyboardEvent<HTMLElement>): void {
        if (!isGridActionClick(event.target) && isCardActivationKey(event.key)) {
            event.preventDefault();
            onSelect(charge);
        }
    }

    return <article className="charge-card" tabIndex={0} onKeyDown={activateCard}>
        <button className="charge-card-main" onClick={() => onSelect(charge)} aria-label={`View ${charge.external_transaction_id || `charge ${charge.id}`}`}>
            <span className="card-top"><strong>{charge.external_transaction_id || `#${charge.id}`}</strong><StatusBadge status={charge.status} /></span>
            <span className="card-main"><span>{charge.customer_email}</span><strong>{formatMoney(charge.amount, charge.currency)}</strong></span>
            <span className="card-bottom"><span>{merchant} · {charge.currency}</span></span>
        </button>
        <ChargeActions charge={charge} permissions={permissions} onRefund={onRefund} onVoid={onVoid} />
    </article>;
}

function ChargeDrawer({ charge, merchant, onClose, permissions, onRefund, onVoid }: { charge: Charge | null; merchant: string; onClose: () => void; permissions: string[]; onRefund: (charge: Charge) => void; onVoid: (charge: Charge) => void }): ReactNode {
    const [closing, setClosing] = useState(false);
    const dialogRef = useDialogFocus<HTMLElement>(Boolean(charge), onClose);
    useEffect(() => { setClosing(false); }, [charge]);
    useEffect(() => {
        if (!closing) return undefined;
        const timer = window.setTimeout(onClose, 300);
        return () => window.clearTimeout(timer);
    }, [closing, onClose]);
    useEffect(() => {
        if (!charge) return undefined;
        const handleKeyDown = (event: globalThis.KeyboardEvent) => { if (event.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [charge, onClose]);
    if (!charge) return null;
    function beginClose(): void { if (!closing) setClosing(true); }
    function finishClose(event: React.TransitionEvent<HTMLDivElement>): void { if (closing && shouldFinishDrawerClose(event)) onClose(); }
    return <div className={`drawer-backdrop${closing ? ' is-closing' : ''}`} onClick={beginClose} onTransitionEnd={finishClose}><aside ref={dialogRef} tabIndex={-1} className="drawer" role="dialog" aria-modal="true" aria-labelledby="charge-detail-title" onClick={(event) => event.stopPropagation()}>
        <button className="close-button" aria-label="Close transaction details" onClick={beginClose}>×</button><div className="eyebrow">TRANSACTION DETAIL</div>
        <h2 id="charge-detail-title">{charge.external_transaction_id || `Charge #${charge.id}`}</h2><StatusBadge status={charge.status} />
        <div className="detail-amount">{formatMoney(charge.amount, charge.currency)}</div>
        <dl className="detail-list"><dt>Customer</dt><dd>{charge.customer_email}</dd><dt>Merchant</dt><dd>{merchant}</dd><dt>Account ID</dt><dd>{charge.account_id}</dd><dt>Captured</dt><dd>{formatMoney(charge.amount_captured, charge.currency)}</dd><dt>Refunded</dt><dd>{formatMoney(charge.amount_refunded, charge.currency)}</dd></dl>
        <ChargeActions charge={charge} permissions={permissions} onRefund={onRefund} onVoid={onVoid} />
    </aside></div>;
}

function NewPayment({ accounts, onClose, onCreated }: { accounts: Account[]; onClose: () => void; onCreated: (message: string) => void }): ReactNode {
    const [form, setForm] = useState<{ account_id: number | string; amount: string; currency: Currency; customer_email: string; description: string }>({ account_id: accounts[0]?.id || '', amount: '', currency: 'USD', customer_email: '', description: '' });
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const dialogRef = useDialogFocus<HTMLFormElement>(true, onClose);
    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault(); setLoading(true); setError('');
        try {
            await api.createCharge({ ...form, amount: Math.round(Number(form.amount) * 100), capture: true });
            onCreated('Payment created');
        } catch (err) { setError(errorMessage(err)); } finally { setLoading(false); }
    }
    return <div className="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="new-payment-title"><form ref={dialogRef} tabIndex={-1} className="modal" onSubmit={submit}><button type="button" className="close-button" aria-label="Close new payment" onClick={onClose}>×</button><div className="eyebrow">NEW PAYMENT</div><h2 id="new-payment-title">Create a payment</h2><ErrorMessage message={error} />
        <label htmlFor="payment-account">Account<select id="payment-account" name="account_id" autoComplete="off" value={form.account_id} onChange={(event) => setForm({ ...form, account_id: event.target.value })}>{accounts.map((account) => <option key={account.id} value={account.id}>{account.business_name || account.id}</option>)}</select></label>
        <label htmlFor="payment-amount">Amount<input id="payment-amount" name="amount" autoComplete="off" inputMode="decimal" type="number" min="0.01" step="0.01" value={form.amount} onChange={(event) => setForm({ ...form, amount: event.target.value })} required /></label>
        <label htmlFor="payment-email">Customer email<input id="payment-email" name="customer_email" autoComplete="email" type="email" value={form.customer_email} onChange={(event) => setForm({ ...form, customer_email: event.target.value })} required /></label>
        <label htmlFor="payment-description">Description<input id="payment-description" name="description" autoComplete="off" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /></label>
        <button className="button button-primary" disabled={loading} aria-busy={loading}>{loading ? 'Creating…' : 'Create payment'}</button>
    </form></div>;
}

function ActionDialog({ type, charge, onClose, onComplete }: { type: ActionType; charge: Charge; onClose: () => void; onComplete: (message: string) => void }): ReactNode {
    const [value, setValue] = useState(type === 'refund' ? defaultRefundAmount(charge) : '');
    const [error, setError] = useState(''); const [loading, setLoading] = useState(false);
    const dialogRef = useDialogFocus<HTMLFormElement>(true, onClose);
    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault(); setLoading(true); setError('');
        try { if (type === 'refund') await api.refund(charge.id, Math.round(Number(value) * 100)); else await api.void(charge.id, value); onComplete(type === 'refund' ? 'Payment refunded' : 'Payment voided'); }
        catch (err) { setError(errorMessage(err)); } finally { setLoading(false); }
    }
    return <div className="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="action-title"><form ref={dialogRef} tabIndex={-1} className="modal" onSubmit={submit}><button type="button" className="close-button" aria-label={`Close ${type} dialog`} onClick={onClose}>×</button><div className="eyebrow">CONFIRM {type.toUpperCase()}</div><h2 id="action-title">{type === 'refund' ? 'Refund payment' : 'Void payment'}</h2><ErrorMessage message={error} />
        {type === 'refund' ? <label htmlFor="refund-amount">Refund amount<input id="refund-amount" name="amount" autoComplete="off" inputMode="decimal" type="number" min="0.01" step="0.01" value={value} onChange={(event) => setValue(event.target.value)} required /></label> : <label htmlFor="void-reason">Reason<input id="void-reason" name="reason" autoComplete="off" value={value} onChange={(event) => setValue(event.target.value)} minLength={3} required /></label>}
        <button className="button button-primary" disabled={loading} aria-busy={loading}>{loading ? 'Processing…' : `Confirm ${type}`}</button>
    </form></div>;
}

function Transactions({ user, onLogout }: { user: User; onLogout: () => void }): ReactNode {
    const [charges, setCharges] = useState<Charge[]>([]); const [accounts, setAccounts] = useState<Account[]>([]); const [meta, setMeta] = useState<ListMeta>({ total: 0 }); const [filters, setFilters] = useState<QueryState>({ search: '', status: '', page: 1, pageSize: 25 }); const [loading, setLoading] = useState(true); const [error, setError] = useState(''); const [selected, setSelected] = useState<Charge | null>(null); const [modal, setModal] = useState<{ type: 'new' } | { type: ActionType; charge: Charge } | null>(null); const [gridApi, setGridApi] = useState<GridApi<Charge> | null>(null); const [toast, setToast] = useState('');
    useEffect(() => { api.accounts().then((result) => setAccounts(result.data || [])).catch((err: unknown) => setError(errorMessage(err))); }, []);
    useEffect(() => {
        const controller = new AbortController(); setLoading(true); setError('');
        api.charges(toChargeQuery(filters), { signal: controller.signal }).then((result) => { setCharges(result.data || []); setMeta(result.meta || {}); }).catch((err: unknown) => { if (err instanceof DOMException && err.name === 'AbortError') return; setError(errorMessage(err)); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [filters]);
    useEffect(() => { const handler = () => onLogout(); window.addEventListener('portal:unauthorized', handler); return () => window.removeEventListener('portal:unauthorized', handler); }, [onLogout]);
    useEffect(() => { if (!toast) return undefined; const timer = window.setTimeout(() => setToast(''), 3000); return () => window.clearTimeout(timer); }, [toast]);
    function datasource(state: QueryState): IDatasource { return { getRows: async (params: IGetRowsParams): Promise<void> => { const pageSize = params.endRow - params.startRow; const page = Math.floor(params.startRow / pageSize) + 1; try { const result = await api.charges(toChargeQuery({ ...state, page, pageSize, sortModel: params.sortModel, filterModel: params.filterModel })); const total = Number(result.meta?.total ?? result.data?.length ?? 0); params.successCallback(result.data || [], page * pageSize >= total ? total : -1); } catch (err) { params.failCallback(); setError(errorMessage(err)); } } }; }
    useEffect(() => { if (gridApi) gridApi.setGridOption('datasource', datasource(filters)); }, [gridApi, filters]);
    const refresh = (message: string) => { setModal(null); setSelected(null); setToast(message); setFilters((current) => ({ ...current })); if (gridApi) gridApi.refreshInfiniteCache(); };
    const merchantFor = (id: number | string): string => formatMerchant(accounts.find((account) => account.id === id));
    const openRefund = (charge: Charge): void => setModal({ type: 'refund', charge });
    const openVoid = (charge: Charge): void => setModal({ type: 'void', charge });
    const columns: ColDef<Charge>[] = [
        { field: 'created_at', headerName: 'Date', filter: false, valueFormatter: ({ value }) => formatDateTime(value), flex: 1.35 },
        { field: 'external_transaction_id', colId: 'external_transaction_id', headerName: 'Txn ID', filter: false, flex: 1.2 },
        { colId: 'merchant', headerName: 'Merchant', filter: false, valueGetter: ({ data }) => merchantFor(chargeField(data, 'account_id') || ''), flex: 1 },
        { field: 'customer_email', headerName: 'Customer', filter: false, flex: 1.4 },
        { colId: 'amount', headerName: 'Amount', filter: 'agNumberColumnFilter', valueGetter: ({ data }) => chargeAmountValue(data), valueFormatter: ({ value, data }) => formatMoney(value, chargeField(data, 'currency')), flex: .8 },
        { colId: 'amount_captured', headerName: 'Captured', filter: false, valueGetter: ({ data }) => chargeField(data, 'amount_captured'), valueFormatter: ({ value, data }) => formatMoney(value, chargeField(data, 'currency')), flex: .8 },
        { colId: 'amount_refunded', headerName: 'Refunded', filter: false, valueGetter: ({ data }) => chargeField(data, 'amount_refunded'), valueFormatter: ({ value, data }) => formatMoney(value, chargeField(data, 'currency')), flex: .8 },
        { field: 'status', filter: 'agTextColumnFilter', cellRenderer: ({ value }: ICellRendererParams<Charge>) => <div className="grid-cell-centered"><StatusBadge status={String(value || '')} /></div>, flex: .8 },
        { headerName: 'Actions', sortable: false, filter: false, cellRenderer: ({ data }: ICellRendererParams<Charge>) => data ? <div className="grid-cell-centered grid-actions"><ChargeActions charge={data} permissions={user.permissions} onRefund={openRefund} onVoid={openVoid} /></div> : null, flex: 1 },
    ];
    return <main className="portal"><header className="topbar"><div><div className="eyebrow">PAYARC / OPERATIONS</div><h1>Transactions</h1></div><div className="topbar-actions"><span className="user-pill">{user.email}</span><button className="button" onClick={onLogout}>Sign out</button></div></header>
        <section className="toolbar"><div className="search-wrap"><label className="sr-only" htmlFor="charge-search">Search transactions</label><input id="charge-search" name="search" autoComplete="off" aria-label="Search customer or transaction" placeholder="Search customer or transaction…" value={filters.search || ''} onChange={(event) => setFilters({ ...filters, search: event.target.value, page: 1 })} /></div><label className="sr-only" htmlFor="status-filter">Filter by status</label><select id="status-filter" name="status" className="status-filter" autoComplete="off" aria-label="Filter by status" value={filters.status || ''} onChange={(event) => setFilters({ ...filters, status: event.target.value, page: 1 })}><option value="">All statuses</option><option value="authorized">Authorized</option><option value="captured">Captured</option><option value="refunded">Refunded</option><option value="voided">Voided</option></select>{user.permissions.includes('charges.create') && <button className="button button-primary" onClick={() => setModal({ type: 'new' })}>+ New payment</button>}</section>
        <Toast message={toast} /><ErrorMessage message={error} />{loading ? <div className="state-panel" aria-busy="true">Loading transactions…</div> : charges.length === 0 ? <div className="state-panel"><strong>No transactions found</strong><span>Try changing your filters or create a new payment.</span></div> : <><div className="grid-wrap ag-theme-quartz"><AgGridReact<Charge> columnDefs={columns} defaultColDef={{ sortable: true, filter: true, resizable: true }} rowModelType="infinite" cacheBlockSize={25} maxBlocksInCache={5} domLayout="autoHeight" suppressCellFocus onGridReady={({ api }: GridReadyEvent<Charge>) => setGridApi(api)} onRowClicked={({ event, data }) => { if (data && !isGridActionClick(event?.target || null)) setSelected(data); }} /></div><div className="mobile-list">{charges.map((charge) => <ChargeCard key={charge.id} charge={charge} merchant={merchantFor(charge.account_id)} permissions={user.permissions} onSelect={setSelected} onRefund={openRefund} onVoid={openVoid} />)}</div><div className="pagination"><span>{meta.total || charges.length} transactions</span></div></>}
        <ChargeDrawer charge={selected} merchant={selected ? merchantFor(selected.account_id) : ''} onClose={() => setSelected(null)} permissions={user.permissions} onRefund={openRefund} onVoid={openVoid} />{modal?.type === 'new' && <NewPayment accounts={accounts} onClose={() => setModal(null)} onCreated={refresh} />}{(modal?.type === 'refund' || modal?.type === 'void') && <ActionDialog type={modal.type} charge={modal.charge} onClose={() => setModal(null)} onComplete={refresh} />}
    </main>;
}

export default function App(): ReactNode {
    const [user, setUser] = useState<User | null>(null); const [checking, setChecking] = useState(true);
    async function loadUser(): Promise<void> { try { const result = await api.me(); setUser(result.data); } catch { setUser(null); } finally { setChecking(false); } }
    useEffect(() => { if (tokenStore.get()) void loadUser(); else setChecking(false); }, []);
    function logout(): void { tokenStore.clear(); setUser(null); }
    if (checking) return <div className="state-panel full-screen" aria-busy="true">Loading…</div>;
    if (!user) return <Login onLogin={loadUser} />;
    if (!user.permissions?.includes('charges.view')) return <div className="state-panel full-screen"><strong>No access</strong><span>Your account has no transaction-view permission.</span><button className="button" onClick={logout}>Sign out</button></div>;
    return <Transactions user={user} onLogout={logout} />;
}
