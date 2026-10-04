<?php

namespace App\Nova;

use App\Containers\AppSection\Payments\Models\Charge as ChargeModel;
use App\Containers\AppSection\Payments\Services\ChargeService;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Charge extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ChargeModel>
     */
    public static $model = ChargeModel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    public static function availableForNavigation(Request $request): bool
    {
        return (bool) $request->user()?->can('charges.view');
    }

    public static function beforeCreate(NovaRequest $request, $model): void
    {
        $created = app(ChargeService::class)->create([
            'account_id' => $request->integer('account'),
            'amount' => $model->getAttribute('amount'),
            'currency' => $request->input('currency'),
            'customer_email' => $request->input('customer_email'),
            'capture' => $request->boolean('capture', true),
            'description' => $request->input('description'),
            'idempotency_key' => $request->input('idempotency_key'),
            'created_by' => $request->user()->getKey(),
        ]);

        $model->setRawAttributes($created->getAttributes());
        $model->exists = true;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Account')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),

            Select::make('Currency')->options([
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP',
                'BGN' => 'BGN',
            ])->displayUsingLabels()->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            })->rules('required'),

            Boolean::make('Capture')->onlyOnForms()->default(true)->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),

            Currency::make('Amount')->currency($this->currency ?: 'USD')->asMinorUnits()
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $value = $request->input($requestAttribute);
                    $model->{$attribute} = $value === null || $value === ''
                        ? null
                        : (int) round((float) $value * 100);
                })
                ->readonly(function ($request) {
                    return $request->isUpdateOrUpdateAttachedRequest();
                }),
            Currency::make('Amount Captured')->currency($this->currency ?: 'USD')->asMinorUnits()->exceptOnForms(),
            Currency::make('Amount Refunded')->currency($this->currency ?: 'USD')->asMinorUnits()->exceptOnForms(),
            Currency::make('Amount Voided')->currency($this->currency ?: 'USD')->asMinorUnits()->exceptOnForms(),
            Text::make('Status')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
            Text::make('Transaction ID', 'external_transaction_id')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
            Text::make('Customer Email')->rules('required', 'email', 'max:254'),
            Text::make('Description')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
            Text::make('Idempotency Key')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
            Boolean::make('Refunded')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
            Boolean::make('Disputed'),

            BelongsTo::make('Created By', 'createdBy', User::class)->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),

            Text::make('Void Reason')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),

            DateTime::make('Created at')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
            DateTime::make('Updated at')->readonly(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest();
            }),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            new \App\Nova\Actions\RefundCharge,
            new \App\Nova\Actions\VoidCharge,
        ];
    }
}
