<?php

namespace App\Containers\AppSection\Payments\Tests\Unit\Services;

use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Charge;
use App\Containers\AppSection\Payments\Services\ChargeService;
use App\Containers\AppSection\Payments\Exceptions\ChargeStateException;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use App\Ship\Seeders\InitialDeploymentDataSeeder;
use Illuminate\Validation\ValidationException;

final class ChargeServiceTest extends TestCase
{
    public function testItCreatesAnAuthorizedCharge(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        $account = Account::query()->where('mid', 'MID_DEMO_001')->firstOrFail();
        $user = User::query()->where('email', 'admin@payarc.test')->firstOrFail();

        $charge = app(ChargeService::class)->create([
            'account_id' => $account->id,
            'amount' => 1250,
            'currency' => 'USD',
            'customer_email' => 'customer@example.com',
            'capture' => false,
            'created_by' => $user->id,
        ]);

        self::assertSame('authorized', $charge->status);
        self::assertSame(1250, $charge->amount);
        self::assertSame(0, $charge->amount_captured);
        self::assertStringStartsWith('txn_', $charge->external_transaction_id);
    }

    public function testItRejectsAnInactiveAccount(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        $account = Account::query()->where('mid', 'MID_DEMO_001')->firstOrFail();
        $account->update(['active_yn' => false]);
        $user = User::query()->where('email', 'admin@payarc.test')->firstOrFail();

        $this->expectException(ValidationException::class);

        app(ChargeService::class)->create([
            'account_id' => $account->id,
            'amount' => 1250,
            'currency' => 'USD',
            'customer_email' => 'customer@example.com',
            'created_by' => $user->id,
        ]);
    }

    public function testItRejectsAnEmptyCreationPayload(): void
    {
        $this->expectException(ValidationException::class);

        app(ChargeService::class)->create([]);
    }

    public function testItCapturesAnAuthorizedCharge(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge(['capture' => false]);

        $captured = app(ChargeService::class)->capture($charge);

        self::assertSame('captured', $captured->status);
        self::assertSame($captured->amount, $captured->amount_captured);
    }

    public function testItRefundsAChargeAndMarksItFullyRefunded(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge();

        $refunded = app(ChargeService::class)->refund($charge, 1250, 'Customer request');

        self::assertSame('refunded', $refunded->status);
        self::assertSame(1250, $refunded->amount_refunded);
        self::assertTrue($refunded->refunded);
    }

    public function testItSupportsPartialRefundsWithoutChangingTheCapturedStatus(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge();

        $refunded = app(ChargeService::class)->refund($charge, 250);

        self::assertSame('captured', $refunded->status);
        self::assertSame(250, $refunded->amount_refunded);
        self::assertSame(1250, $refunded->amount_captured);
    }

    public function testItRejectsRefundsLargerThanTheRemainingCapturedAmount(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge();

        $this->expectException(ChargeStateException::class);

        app(ChargeService::class)->refund($charge, 1251);
    }

    public function testItRejectsRefundsForDisputedCharges(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge();
        $charge->update(['disputed' => true]);

        $this->expectException(ChargeStateException::class);

        app(ChargeService::class)->refund($charge, 100);
    }

    public function testItReturnsTheOriginalChargeForAnExistingIdempotencyKey(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $first = $this->createCharge(['idempotency_key' => 'same-request']);

        $second = $this->createCharge([
            'idempotency_key' => 'same-request',
            'amount' => 999,
        ]);

        self::assertSame($first->id, $second->id);
        self::assertSame(1250, $second->amount);
    }

    public function testItTreatsABlankIdempotencyKeyAsAbsent(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);

        $first = $this->createCharge(['idempotency_key' => '']);
        $second = $this->createCharge(['idempotency_key' => '']);

        self::assertNotSame($first->id, $second->id);
        self::assertNull($first->idempotency_key);
        self::assertNull($second->idempotency_key);
    }

    public function testItRejectsCapturingAChargeThatIsNotAuthorized(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge();

        $this->expectException(ChargeStateException::class);

        app(ChargeService::class)->capture($charge);
    }

    public function testItVoidsAnAuthorizedCharge(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge(['capture' => false]);

        $voided = app(ChargeService::class)->void($charge, 'Customer cancelled');

        self::assertSame('voided', $voided->status);
        self::assertSame($voided->amount, $voided->amount_voided);
        self::assertSame('Customer cancelled', $voided->void_reason);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCharge(array $overrides = []): Charge
    {
        $account = Account::query()->where('mid', 'MID_DEMO_001')->firstOrFail();
        $user = User::query()->where('email', 'admin@payarc.test')->firstOrFail();

        return app(ChargeService::class)->create(array_merge([
            'account_id' => $account->id,
            'amount' => 1250,
            'currency' => 'USD',
            'customer_email' => 'customer@example.com',
            'created_by' => $user->id,
        ], $overrides));
    }
}
