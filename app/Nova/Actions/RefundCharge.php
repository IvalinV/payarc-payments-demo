<?php

namespace App\Nova\Actions;

use App\Containers\AppSection\Payments\Models\Charge as ChargeModel;
use App\Containers\AppSection\Payments\Services\ChargeService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

final class RefundCharge extends Action
{
    public $name = 'Refund';

    public function __construct()
    {
        $this->canRun(static fn (NovaRequest $request, mixed $model): bool => $model instanceof ChargeModel
            && $model->status === 'captured'
            && ! $model->disputed);
    }

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        foreach ($models as $model) {
            app(ChargeService::class)->refund(
                $model,
                $fields->get('amount') === null ? null : (int) $fields->get('amount'),
                $fields->get('reason'),
            );
        }

        return Action::message('Charge refund processed.');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Amount')->nullable()->min(1)->rules('nullable', 'integer', 'min:1'),
            Text::make('Reason')->nullable()->rules('nullable', 'max:255'),
        ];
    }

}
