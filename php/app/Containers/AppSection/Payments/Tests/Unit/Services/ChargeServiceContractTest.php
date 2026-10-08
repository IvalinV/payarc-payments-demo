<?php

namespace App\Containers\AppSection\Payments\Tests\Unit\Services;

use App\Containers\AppSection\Payments\Exceptions\ChargeStateException;
use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Charge;
use App\Containers\AppSection\Payments\Services\ChargeService;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use App\Ship\Seeders\InitialDeploymentDataSeeder;
use Illuminate\Validation\ValidationException;

final class ChargeServiceContractTest extends TestCase
{
    public function testContractCurrenciesAndAmountBoundsAreEnforced(): void
    {
        $contract = $this->contract();
        $this->seed(InitialDeploymentDataSeeder::class);

        foreach ($contract['currencies'] as $currency) {
            $charge = $this->createCharge([
                'amount' => $contract['amount']['minimum'],
                'currency' => $currency,
            ]);

            self::assertSame($currency, $charge->currency);
        }

        $this->expectException(ValidationException::class);
        $this->createCharge(['amount' => $contract['amount']['maximum'] + 1]);
    }

    public function testContractCaptureTransitionsAuthorizedToCaptured(): void
    {
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge(['capture' => false]);
        $captured = app(ChargeService::class)->capture($charge);

        self::assertSame('captured', $captured->status);
        self::assertSame($captured->amount, $captured->amount_captured);
    }

    public function testContractRefundRejectsInvalidAmountsAndFullyRefunds(): void
    {
        $contract = $this->contract();
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge();

        foreach ([0, -1, $charge->amount + 1] as $amount) {
            try {
                app(ChargeService::class)->refund($charge, $amount);
                self::fail('Expected invalid refund amount to be rejected.');
            } catch (ChargeStateException $exception) {
                self::assertContains($exception->errorCode, [
                    'invalid_amount',
                    $contract['operations']['refund']['exceeds_refundable'],
                ]);
            }
        }

        $refunded = app(ChargeService::class)->refund($charge);

        self::assertSame('refunded', $refunded->status);
        self::assertSame($refunded->amount_captured, $refunded->amount_refunded);
    }

    public function testContractVoidTransitionsAuthorizedToVoided(): void
    {
        $contract = $this->contract();
        $this->seed(InitialDeploymentDataSeeder::class);
        $charge = $this->createCharge(['capture' => false]);
        $voided = app(ChargeService::class)->void($charge, 'Customer cancelled');

        self::assertSame($contract['operations']['void']['to'], $voided->status);
        self::assertSame($voided->amount, $voided->amount_voided);
    }

    /**
     * @return array{currencies: list<string>, amount: array{minimum: int, maximum: int}, operations: array<string, array<string, mixed>>}
     */
    private function contract(): array
    {
        return json_decode(
            file_get_contents(base_path('contracts/charge_rules.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
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
