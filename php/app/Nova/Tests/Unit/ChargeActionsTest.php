<?php

namespace App\Nova\Tests\Unit;

use App\Containers\AppSection\Payments\Models\Charge as ChargeModel;
use App\Nova\Actions\CaptureCharge;
use App\Nova\Actions\RefundCharge;
use App\Nova\Charge;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

final class ChargeActionsTest extends TestCase
{
    public function testCaptureActionIsRegisteredForCharges(): void
    {
        $actions = (new Charge())->actions(NovaRequest::create('/'));

        self::assertCount(1, array_filter(
            $actions,
            static fn (object $action): bool => $action instanceof CaptureCharge,
        ));
    }

    public function testCaptureActionOnlyRunsForAuthorizedCharges(): void
    {
        $action = new CaptureCharge();
        $request = NovaRequest::create('/');

        self::assertTrue(($action->runCallback)($request, new ChargeModel(['status' => 'authorized'])));
        self::assertFalse(($action->runCallback)($request, new ChargeModel(['status' => 'captured'])));
    }

    public function testRefundAmountUsesMinorUnitCurrencyInput(): void
    {
        $fields = (new RefundCharge())->fields(NovaRequest::create('/'));
        $amount = $fields[0];

        self::assertInstanceOf(Currency::class, $amount);
        self::assertTrue($amount->minorUnits);
    }
}
