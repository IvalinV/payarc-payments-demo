<?php

namespace App\Containers\AppSection\Payments\Services;

use App\Containers\AppSection\Payments\Exceptions\ChargeStateException;
use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Charge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChargeService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Charge
    {
        $rules = $this->rules();

        $this->rejectUnknownFields($data, [
            'account_id', 'amount', 'currency', 'customer_email', 'capture',
            'description', 'idempotency_key', 'created_by',
        ]);

        $validated = Validator::make($data, [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'integer', 'between:'.$rules['amount']['minimum'].','.$rules['amount']['maximum']],
            'currency' => ['required', 'string', 'size:3', 'in:'.implode(',', $rules['currencies'])],
            'customer_email' => ['required', 'email'],
            'capture' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'created_by' => ['required', 'integer', 'exists:users,id'],
        ])->validate();

        if (isset($validated['idempotency_key']) && trim((string) $validated['idempotency_key']) === '') {
            $validated['idempotency_key'] = null;
        }

        return DB::transaction(function () use ($validated): Charge {
            $account = Account::query()->lockForUpdate()->findOrFail($validated['account_id']);

            if ($account->status !== 'active' || ! $account->active_yn) {
                throw ValidationException::withMessages([
                    'account_id' => 'The account must be active and enabled.',
                ]);
            }

            if (! empty($validated['idempotency_key'])) {
                $existing = Charge::query()
                    ->where('account_id', $account->id)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $amount = (int) $validated['amount'];
            $captured = (bool) ($validated['capture'] ?? true);

            return Charge::query()->create([
                'account_id' => $account->id,
                'amount' => $amount,
                'amount_captured' => $captured ? $amount : 0,
                'amount_refunded' => 0,
                'amount_voided' => 0,
                'currency' => strtoupper($validated['currency']),
                'status' => $captured ? 'captured' : 'authorized',
                'external_transaction_id' => 'txn_'.Str::ulid(),
                'customer_email' => $validated['customer_email'],
                'refunded' => false,
                'disputed' => false,
                'description' => $validated['description'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'created_by' => $validated['created_by'],
            ]);
        });
    }

    public function capture(Charge $charge): Charge
    {
        return DB::transaction(function () use ($charge): Charge {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($charge->status !== 'authorized') {
                throw new ChargeStateException('invalid_state', 'Only authorized charges can be captured.');
            }

            $charge->forceFill([
                'status' => 'captured',
                'amount_captured' => $charge->amount,
            ])->save();

            return $charge->refresh();
        });
    }

    public function refund(Charge $charge, ?int $amount = null, ?string $reason = null): Charge
    {
        return DB::transaction(function () use ($charge, $amount, $reason): Charge {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($charge->status === 'refunded') {
                throw new ChargeStateException('charge_already_refunded', 'The charge is already fully refunded.');
            }

            if ($charge->status !== 'captured' || $charge->disputed) {
                throw new ChargeStateException('invalid_state', 'Only non-disputed captured charges can be refunded.');
            }

            $remaining = $charge->amount_captured - $charge->amount_refunded;
            $refundAmount = $amount ?? $remaining;

            if ($refundAmount <= 0) {
                throw new ChargeStateException('invalid_amount', 'The refund amount must be greater than zero.', 422);
            }

            if ($refundAmount > $remaining) {
                throw new ChargeStateException('amount_exceeds_refundable', 'The refund amount exceeds the refundable amount.', 422);
            }

            $refunded = $charge->amount_refunded + $refundAmount;
            $charge->forceFill([
                'amount_refunded' => $refunded,
                'refunded' => true,
                'status' => $refunded === $charge->amount_captured ? 'refunded' : 'captured',
            ])->save();

            return $charge->refresh();
        });
    }

    public function void(Charge $charge, string $reason): Charge
    {
        return DB::transaction(function () use ($charge, $reason): Charge {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);

            if ($charge->status === 'voided') {
                throw new ChargeStateException('charge_already_voided', 'The charge is already voided.');
            }

            if ($charge->status !== 'authorized' || $charge->disputed) {
                throw new ChargeStateException('invalid_state', 'Only non-disputed authorized charges can be voided.');
            }

            if (trim($reason) === '') {
                throw new ChargeStateException('invalid_reason', 'A void reason is required.', 422);
            }

            $charge->forceFill([
                'status' => 'voided',
                'amount_voided' => $charge->amount,
                'void_reason' => $reason,
            ])->save();

            return $charge->refresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     */
    private function rejectUnknownFields(array $data, array $allowed): void
    {
        $unknown = array_diff(array_keys($data), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'data' => 'Unknown fields: '.implode(', ', $unknown),
            ]);
        }
    }

    /**
     * @return array{currencies: list<string>, amount: array{minimum: int, maximum: int}}
     */
    private function rules(): array
    {
        return json_decode(
            file_get_contents(base_path('contracts/charge_rules.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
