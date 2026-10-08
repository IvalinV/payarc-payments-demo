import { useEffect, useState } from 'react';
import { AgGridReact } from '@ag-grid-community/react';
import { ModuleRegistry } from '@ag-grid-community/core';
import { InfiniteRowModelModule } from '@ag-grid-community/infinite-row-model';
import { api, tokenStore } from './api';
import { canActOnCharge, chargeAmountValue, chargeField, formatDateTime, formatMerchant, formatMoney, isCardActivationKey, isGridActionClick, shouldFinishDrawerClose, toChargeQuery } from './utils';
import '@ag-grid-community/styles/ag-grid.css';
import '@ag-grid-community/styles/ag-theme-quartz.css';

ModuleRegistry.registerModules([InfiniteRowModelModule]);

function ErrorMessage({ message }) {
    return message ? <div className="alert alert-error" role="alert" aria-live="polite">{message}</div> : null;
}

function Toast({ message }) { return message ? <div className="toast" role="status" aria-live="polite">{message}</div> : null; }

function Login({ onLogin }) {
    const [email, setEmail] = useState('admin@payarc.test');
    const [password, setPassword] = useState('Demo#12345');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    async function submit(event) {
        event.preventDefault();
        setLoading(true); setError('');
        try { const result = await api.login(email, password); tokenStore.set(result.token); onLogin(); }
        catch (err) { setError(err.message); }
        finally { setLoading(false); }
    }
    return <main className="auth-layout"><form className="auth-card" onSubmit={submit}>
        <div className="eyebrow">PAYARC / OPERATIONS</div><h1>Payments, in focus.</h1>
        <p className="muted">Sign in to manage transactions across your connected accounts.</p>
        <ErrorMessage message={error} />
        <label htmlFor="login-email">Email<input id="login-email" name="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} type="email" required /></label>
        <label htmlFor="login-password">Password<input id="login-password" name="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} type="password" required /></label>
        <button className="button button-primary" disabled={loading} aria-busy={loading}>{loading ? 'Signing in…' : 'Sign in'}</button>
    </form></main>;
}

function StatusBadge({ status }) { return <span className={`status status-${status}`}>{status}</span>; }

function ChargeActions({ charge, permissions, onRefund, onVoid }) {
    return <div className="row-actions">
        {canActOnCharge(charge, permissions, 'refund') && <button className="button button-small" onClick={(e) => { e.stopPropagation(); onRefund(charge); }}>Refund</button>}
        {canActOnCharge(charge, permissions, 'void') && <button className="button button-small button-danger" onClick={(e) => { e.stopPropagation(); onVoid(charge); }}>Void</button>}
    </div>;
}

function ChargeCard({ charge, merchant, permissions, onSelect, onRefund, onVoid }) {
    function selectCard(event) {
        if (!isGridActionClick(event.target)) onSelect(charge);
    }
    function activateCard(event) {
        if (!isGridActionClick(event.target) && isCardActivationKey(event.key)) {
            event.preventDefault();
            onSelect(charge);
        }
    }
    return <div className="charge-card" role="button" tabIndex="0" onClick={selectCard} onKeyDown={activateCard}>
        <div className="card-top"><strong>{charge.external_transaction_id || `#${charge.id}`}</strong><StatusBadge status={charge.status} /></div>
        <div className="card-main"><span>{charge.customer_email}</span><strong>{formatMoney(charge.amount, charge.currency)}</strong></div>
        <div className="card-bottom"><span>{merchant} · {charge.currency}</span><ChargeActions charge={charge} permissions={permissions} onRefund={onRefund} onVoid={onVoid} /></div>
    </div>;
}

function ChargeDrawer({ charge, merchant, onClose, permissions, onRefund, onVoid }) {
    const [closing, setClosing] = useState(false);
    useEffect(() => { setClosing(false); }, [charge]);
    useEffect(() => {
        if (!closing) return undefined;
        const timer = window.setTimeout(onClose, 300);
        return () => window.clearTimeout(timer);
    }, [closing, onClose]);
    useEffect(() => {
        if (!charge) return undefined;
        const handleKeyDown = (event) => { if (event.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [charge, onClose]);
    if (!charge) return null;
    function beginClose() { if (!closing) setClosing(true); }
    function finishClose(event) { if (closing && shouldFinishDrawerClose(event)) onClose(); }
    return <div className={`drawer-backdrop${closing ? ' is-closing' : ''}`} onClick={beginClose} onTransitionEnd={finishClose}><aside className="drawer" role="dialog" aria-modal="true" aria-labelledby="charge-detail-title" onClick={(e) => e.stopPropagation()}>
        <button className="close-button" aria-label="Close transaction details" onClick={beginClose}>×</button><div className="eyebrow">TRANSACTION DETAIL</div>
        <h2 id="charge-detail-title">{charge.external_transaction_id || `Charge #${charge.id}`}</h2><StatusBadge status={charge.status} />
        <div className="detail-amount">{formatMoney(charge.amount, charge.currency)}</div>
        <dl className="detail-list"><dt>Customer</dt><dd>{charge.customer_email}</dd><dt>Merchant</dt><dd>{merchant}</dd><dt>Account ID</dt><dd>{charge.account_id}</dd><dt>Captured</dt><dd>{formatMoney(charge.amount_captured, charge.currency)}</dd><dt>Refunded</dt><dd>{formatMoney(charge.amount_refunded, charge.currency)}</dd></dl>
        <ChargeActions charge={charge} permissions={permissions} onRefund={onRefund} onVoid={onVoid} />
    </aside></div>;
}

function NewPayment({ accounts, onClose, onCreated }) {
    const [form, setForm] = useState({ account_id: accounts[0]?.id || '', amount: '', currency: 'USD', customer_email: '', description: '' });
    const [error, setError] = useState(''); const [loading, setLoading] = useState(false);
    async function submit(e) { e.preventDefault(); setLoading(true); setError(''); try { const key = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`; await api.createCharge({ ...form, amount: Math.round(Number(form.amount) * 100), capture: true }, key); onCreated('Payment created'); } catch (err) { setError(err.message); } finally { setLoading(false); } }
    return <div className="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="new-payment-title" onKeyDown={(e) => e.key === 'Escape' && onClose()}><form className="modal" onSubmit={submit}><button type="button" className="close-button" aria-label="Close new payment" onClick={onClose}>×</button><div className="eyebrow">NEW PAYMENT</div><h2 id="new-payment-title">Create a payment</h2><ErrorMessage message={error} />
        <label htmlFor="payment-account">Account<select id="payment-account" name="account_id" autoComplete="off" value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>{accounts.map((a) => <option key={a.id} value={a.id}>{a.business_name || a.id}</option>)}</select></label>
        <label htmlFor="payment-amount">Amount<input id="payment-amount" name="amount" autoComplete="off" inputMode="decimal" type="number" min="0.01" step="0.01" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} required /></label>
        <label htmlFor="payment-email">Customer email<input id="payment-email" name="customer_email" autoComplete="email" type="email" value={form.customer_email} onChange={(e) => setForm({ ...form, customer_email: e.target.value })} required /></label>
        <label htmlFor="payment-description">Description<input id="payment-description" name="description" autoComplete="off" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
        <button className="button button-primary" disabled={loading} aria-busy={loading}>{loading ? 'Creating…' : 'Create payment'}</button>
    </form></div>;
}

function ActionDialog({ type, charge, onClose, onComplete }) {
    const [value, setValue] = useState(type === 'refund' ? ((charge.amount - charge.amount_refunded) / 100).toFixed(2) : ''); const [error, setError] = useState(''); const [loading, setLoading] = useState(false);
    async function submit(e) { e.preventDefault(); setLoading(true); setError(''); try { if (type === 'refund') await api.refund(charge.id, Math.round(Number(value) * 100)); else await api.void(charge.id, value); onComplete(type === 'refund' ? 'Payment refunded' : 'Payment voided'); } catch (err) { setError(err.message); } finally { setLoading(false); } }
    return <div className="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="action-title" onKeyDown={(e) => e.key === 'Escape' && onClose()}><form className="modal" onSubmit={submit}><button type="button" className="close-button" aria-label={`Close ${type} dialog`} onClick={onClose}>×</button><div className="eyebrow">CONFIRM {type.toUpperCase()}</div><h2 id="action-title">{type === 'refund' ? 'Refund payment' : 'Void payment'}</h2><ErrorMessage message={error} />
        {type === 'refund' ? <label htmlFor="refund-amount">Refund amount<input id="refund-amount" name="amount" autoComplete="off" inputMode="decimal" type="number" min="0.01" step="0.01" value={value} onChange={(e) => setValue(e.target.value)} required /></label> : <label htmlFor="void-reason">Reason<input id="void-reason" name="reason" autoComplete="off" value={value} onChange={(e) => setValue(e.target.value)} minLength="3" required /></label>}
        <button className="button button-primary" disabled={loading} aria-busy={loading}>{loading ? 'Processing…' : `Confirm ${type}`}</button>
    </form></div>;
}

function Transactions({ user, onLogout }) {
    const [charges, setCharges] = useState([]); const [accounts, setAccounts] = useState([]); const [meta, setMeta] = useState({ total: 0 }); const [filters, setFilters] = useState({ search: '', status: '', page: 1, pageSize: 25 }); const [loading, setLoading] = useState(true); const [error, setError] = useState(''); const [selected, setSelected] = useState(null); const [modal, setModal] = useState(null); const [gridApi, setGridApi] = useState(null); const [toast, setToast] = useState('');
    useEffect(() => { api.accounts().then((result) => setAccounts(result.data || [])).catch((err) => setError(err.message)); }, []);
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setError('');
        api.charges(toChargeQuery(filters), { signal: controller.signal }).then((result) => { setCharges(result.data || []); setMeta(result.meta || {}); }).catch((err) => { if (err.name !== 'AbortError') setError(err.message); }).finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [filters]);
    useEffect(() => { const handler = () => onLogout(); window.addEventListener('portal:unauthorized', handler); return () => window.removeEventListener('portal:unauthorized', handler); }, []);
    useEffect(() => { if (!toast) return undefined; const timer = window.setTimeout(() => setToast(''), 3000); return () => window.clearTimeout(timer); }, [toast]);
    function datasource(state) { return { getRows: async (params) => { const pageSize = params.endRow - params.startRow; const page = Math.floor(params.startRow / pageSize) + 1; try { const result = await api.charges(toChargeQuery({ ...state, page, pageSize, sortModel: params.sortModel, filterModel: params.filterModel })); const total = Number(result.meta?.total ?? result.data?.length ?? 0); params.successCallback(result.data || [], page * pageSize >= total ? total : -1); } catch (err) { params.failCallback(); setError(err.message); } } }; }
    useEffect(() => { if (gridApi) gridApi.setGridOption('datasource', datasource(filters)); }, [gridApi, filters]);
    const refresh = (message) => { setModal(null); setSelected(null); setToast(message); setFilters({ ...filters }); if (gridApi) gridApi.refreshInfiniteCache(); };
    const merchantFor = (id) => formatMerchant(accounts.find((account) => account.id === id));
    const columns = [{ field: 'created_at', headerName: 'Date', valueFormatter: ({ value }) => formatDateTime(value), flex: 1.35 }, { field: 'external_transaction_id', colId: 'external_transaction_id', headerName: 'Txn ID', flex: 1.2 }, { colId: 'merchant', headerName: 'Merchant', valueGetter: ({ data }) => merchantFor(chargeField(data, 'account_id')), flex: 1 }, { field: 'customer_email', headerName: 'Customer', flex: 1.4 }, { colId: 'amount', headerName: 'Amount', valueGetter: ({ data }) => chargeAmountValue(data), valueFormatter: ({ value, data }) => formatMoney(value, chargeField(data, 'currency')), flex: .8 }, { colId: 'amount_captured', headerName: 'Captured', valueGetter: ({ data }) => chargeField(data, 'amount_captured'), valueFormatter: ({ value, data }) => formatMoney(value, chargeField(data, 'currency')), flex: .8 }, { colId: 'amount_refunded', headerName: 'Refunded', valueGetter: ({ data }) => chargeField(data, 'amount_refunded'), valueFormatter: ({ value, data }) => formatMoney(value, chargeField(data, 'currency')), flex: .8 }, { field: 'status', cellRenderer: ({ value }) => <div className="grid-cell-centered"><StatusBadge status={value} /></div>, flex: .8 }, { headerName: 'Actions', sortable: false, filter: false, cellRenderer: ({ data }) => data ? <div className="grid-cell-centered grid-actions"><ChargeActions charge={data} permissions={user.permissions} onRefund={(c) => setModal({ type: 'refund', charge: c })} onVoid={(c) => setModal({ type: 'void', charge: c })} /></div> : null, flex: 1 }];
    return <main className="portal"><header className="topbar"><div><div className="eyebrow">PAYARC / OPERATIONS</div><h1>Transactions</h1></div><div className="topbar-actions"><span className="user-pill">{user.email}</span><button className="button" onClick={onLogout}>Sign out</button></div></header>
        <section className="toolbar"><div className="search-wrap"><label className="sr-only" htmlFor="charge-search">Search transactions</label><input id="charge-search" name="search" autoComplete="off" aria-label="Search customer or transaction" placeholder="Search customer or transaction…" value={filters.search} onChange={(e) => setFilters({ ...filters, search: e.target.value, page: 1 })} /></div><label className="sr-only" htmlFor="status-filter">Filter by status</label><select id="status-filter" name="status" className="status-filter" autoComplete="off" aria-label="Filter by status" value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value, page: 1 })}><option value="">All statuses</option><option value="authorized">Authorized</option><option value="captured">Captured</option><option value="refunded">Refunded</option><option value="voided">Voided</option></select>{user.permissions.includes('charges.create') && <button className="button button-primary" onClick={() => setModal({ type: 'new' })}>+ New payment</button>}</section>
        <Toast message={toast} /><ErrorMessage message={error} />{loading ? <div className="state-panel" aria-busy="true">Loading transactions…</div> : charges.length === 0 ? <div className="state-panel"><strong>No transactions found</strong><span>Try changing your filters or create a new payment.</span></div> : <><div className="grid-wrap ag-theme-quartz"><AgGridReact columnDefs={columns} defaultColDef={{ sortable: true, filter: true, resizable: true }} rowModelType="infinite" cacheBlockSize={25} maxBlocksInCache={5} domLayout="autoHeight" suppressCellFocus onGridReady={({ api }) => setGridApi(api)} onRowClicked={({ event, data }) => { if (!isGridActionClick(event?.target)) setSelected(data); }} /></div><div className="mobile-list">{charges.map((charge) => <ChargeCard key={charge.id} charge={charge} merchant={merchantFor(charge.account_id)} permissions={user.permissions} onSelect={setSelected} onRefund={(c) => setModal({ type: 'refund', charge: c })} onVoid={(c) => setModal({ type: 'void', charge: c })} />)}</div><div className="pagination"><span>{meta.total || charges.length} transactions</span></div></>}
        <ChargeDrawer charge={selected} merchant={selected ? merchantFor(selected.account_id) : ''} onClose={() => setSelected(null)} permissions={user.permissions} onRefund={(c) => setModal({ type: 'refund', charge: c })} onVoid={(c) => setModal({ type: 'void', charge: c })} />{modal?.type === 'new' && <NewPayment accounts={accounts} onClose={() => setModal(null)} onCreated={refresh} />}{(modal?.type === 'refund' || modal?.type === 'void') && <ActionDialog type={modal.type} charge={modal.charge} onClose={() => setModal(null)} onComplete={refresh} />}
    </main>;
}

export default function App() {
    const [user, setUser] = useState(null); const [checking, setChecking] = useState(true);
    async function loadUser() { try { const result = await api.me(); setUser(result.data); } catch { setUser(null); } finally { setChecking(false); } }
    useEffect(() => { if (tokenStore.get()) loadUser(); else setChecking(false); }, []);
    function logout() { tokenStore.clear(); setUser(null); }
    if (checking) return <div className="state-panel full-screen" aria-busy="true">Loading…</div>;
    if (!user) return <Login onLogin={loadUser} />;
    if (!user.permissions?.includes('charges.view')) return <div className="state-panel full-screen"><strong>No access</strong><span>Your account has no transaction-view permission.</span><button className="button" onClick={logout}>Sign out</button></div>;
    return <Transactions user={user} onLogout={logout} />;
}
