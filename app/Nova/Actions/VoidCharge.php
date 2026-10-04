<?php

namespace App\Nova\Actions;

use App\Containers\AppSection\Payments\Models\Charge as ChargeModel;
use App\Containers\AppSection\Payments\Services\ChargeService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

final class VoidCharge extends Action
{
    public $name = 'Void';

    public function __construct()
    {
        $this->canRun(static fn (NovaRequest $request, mixed $model): bool => $model instanceof ChargeModel
            && $model->status === 'authorized'
            && ! $model->disputed);
    }

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            app(ChargeService::class)->void($model, (string) $fields->get('reason'));
        }

        return Action::message('Charge voided.');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Reason')->rules('required', 'string', 'max:255'),
        ];
    }

}
