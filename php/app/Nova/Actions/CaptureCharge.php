<?php

namespace App\Nova\Actions;

use App\Containers\AppSection\Payments\Models\Charge as ChargeModel;
use App\Containers\AppSection\Payments\Services\ChargeService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

final class CaptureCharge extends Action
{
    public $name = 'Capture';

    public function __construct()
    {
        $this->canRun(static fn (NovaRequest $request, mixed $model): bool => $model instanceof ChargeModel
            && 'authorized' === $model->status);
    }

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            app(ChargeService::class)->capture($model);
        }

        return Action::message('Charge captured.');
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
