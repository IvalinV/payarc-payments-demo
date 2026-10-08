import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import App from './App';
import { api, tokenStore } from './api';

let latestGridProps;

vi.mock('@ag-grid-community/react', () => ({
    AgGridReact: (props) => {
        latestGridProps = props;
        return <div data-testid="transactions-grid" />;
    },
}));

vi.mock('./api', () => ({
    api: {
        login: vi.fn(),
        me: vi.fn(),
        accounts: vi.fn(),
        charges: vi.fn(),
        createCharge: vi.fn(),
        refund: vi.fn(),
        void: vi.fn(),
    },
    tokenStore: { get: vi.fn(), set: vi.fn(), clear: vi.fn() },
}));

describe('portal application states', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        latestGridProps = undefined;
        tokenStore.get.mockReturnValue(null);
    });

    it('shows the no-access state after a successful login without permissions', async () => {
        api.login.mockResolvedValue({ token: 'token' });
        api.me.mockResolvedValue({ data: { email: 'norole@payarc.test', permissions: [] } });

        render(<App />);
        fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        expect(await screen.findByText('No access')).toBeInTheDocument();
    });

    it('renders refund actions only when permission and charge state allow them', async () => {
        api.login.mockResolvedValue({ token: 'token' });
        api.me.mockResolvedValue({ data: { email: 'merchant@payarc.test', permissions: ['charges.view', 'charges.refund'] } });
        api.accounts.mockResolvedValue({ data: [{ id: 1, business_name: 'Acme' }] });
        api.charges.mockResolvedValue({ data: [{ id: 1, account_id: 1, status: 'captured', amount: 1000, amount_refunded: 0, currency: 'USD', customer_email: 'a@example.com' }, { id: 2, account_id: 1, status: 'authorized', amount: 1000, amount_refunded: 0, currency: 'USD', customer_email: 'b@example.com' }], meta: { total: 2 } });

        render(<App />);
        fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        await waitFor(() => expect(screen.getByRole('button', { name: 'Refund' })).toBeInTheDocument());
        expect(screen.getAllByRole('button', { name: 'Refund' })).toHaveLength(1);
    });

    it('uses sortable API fields and disables sorting for actions', async () => {
        api.login.mockResolvedValue({ token: 'token' });
        api.me.mockResolvedValue({ data: { email: 'merchant@payarc.test', permissions: ['charges.view'] } });
        api.accounts.mockResolvedValue({ data: [{ id: 1, business_name: 'Acme' }] });
        api.charges.mockResolvedValue({ data: [{ id: 1, account_id: 1, status: 'captured', amount: 1000, amount_captured: 1000, amount_refunded: 0, currency: 'USD', customer_email: 'a@example.com' }], meta: { total: 1 } });

        render(<App />);
        fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        await waitFor(() => expect(latestGridProps).toBeDefined());
        const columns = latestGridProps.columnDefs;

        expect(columns.find(({ headerName }) => headerName === 'Txn ID')).toMatchObject({ colId: 'external_transaction_id' });
        expect(columns.find(({ headerName }) => headerName === 'Merchant')).toMatchObject({ colId: 'merchant' });
        expect(columns.find(({ headerName }) => headerName === 'Amount')).toMatchObject({ colId: 'amount' });
        expect(columns.find(({ headerName }) => headerName === 'Captured')).toMatchObject({ colId: 'amount_captured' });
        expect(columns.find(({ headerName }) => headerName === 'Refunded')).toMatchObject({ colId: 'amount_refunded' });
        expect(columns.filter(({ sortable }) => sortable !== false).map(({ colId, field }) => colId || field)).toEqual([
            'created_at',
            'external_transaction_id',
            'merchant',
            'customer_email',
            'amount',
            'amount_captured',
            'amount_refunded',
            'status',
        ]);
        expect(columns.find(({ headerName }) => headerName === 'Actions')).toMatchObject({ sortable: false, filter: false });
    });

    it('opens the transaction drawer without an entrance animation state', async () => {
        api.login.mockResolvedValue({ token: 'token' });
        api.me.mockResolvedValue({ data: { email: 'merchant@payarc.test', permissions: ['charges.view'] } });
        api.accounts.mockResolvedValue({ data: [{ id: 1, business_name: 'Acme' }] });
        api.charges.mockResolvedValue({ data: [{ id: 1, account_id: 1, status: 'captured', amount: 1000, amount_refunded: 0, currency: 'USD', customer_email: 'a@example.com' }], meta: { total: 1 } });

        render(<App />);
        fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        fireEvent.click(await screen.findByText('a@example.com'));

        expect(screen.getByRole('dialog', { name: 'Charge #1' }).parentElement).not.toHaveClass('is-opening');
    });

    it('reopens the drawer for another transaction after closing it', async () => {
        api.login.mockResolvedValue({ token: 'token' });
        api.me.mockResolvedValue({ data: { email: 'merchant@payarc.test', permissions: ['charges.view'] } });
        api.accounts.mockResolvedValue({ data: [{ id: 1, business_name: 'Acme' }] });
        api.charges.mockResolvedValue({ data: [
            { id: 1, account_id: 1, status: 'captured', amount: 1000, amount_refunded: 0, currency: 'USD', customer_email: 'a@example.com' },
            { id: 2, account_id: 1, status: 'captured', amount: 2000, amount_refunded: 0, currency: 'USD', customer_email: 'b@example.com' },
        ], meta: { total: 2 } });

        render(<App />);
        fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
        fireEvent.click(await screen.findByText('a@example.com'));

        const firstDialog = screen.getByRole('dialog', { name: 'Charge #1' });
        const firstBackdrop = firstDialog.parentElement;
        fireEvent.click(screen.getByRole('button', { name: 'Close transaction details' }));
        fireEvent.transitionEnd(firstBackdrop);

        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Charge #1' })).not.toBeInTheDocument());
        fireEvent.click(screen.getByText('b@example.com'));

        const secondDialog = await screen.findByRole('dialog', { name: 'Charge #2' });
        expect(secondDialog.parentElement).not.toHaveClass('is-closing');
    });
});
