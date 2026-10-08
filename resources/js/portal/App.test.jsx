import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import App from './App';
import { api, tokenStore } from './api';

vi.mock('@ag-grid-community/react', () => ({
    AgGridReact: () => <div data-testid="transactions-grid" />,
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
});
