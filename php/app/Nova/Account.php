<?php

namespace App\Nova;

use App\Containers\AppSection\Payments\Models\Account as AccountModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Account extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<AccountModel>
     */
    public static $model = AccountModel::class;

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

    public static function beforeCreate(NovaRequest $request, $model): void
    {
        $model->created_by = $request->user()->getKey();
    }

    public static function availableForNavigation(Request $request): bool
    {
        return (bool) $request->user()?->can('merchants.manage');
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
            Text::make('Business Name')->sortable()->rules('required', 'max:255'),
            Text::make('DBA Name')->sortable()->rules('required', 'max:255'),
            Text::make('Legal Name')->sortable()->rules('required', 'max:255'),
            Text::make('Email')->sortable()->rules('required', 'email', 'max:254'),
            Text::make('MID')->sortable()->rules('required', 'max:255'),
            Select::make('Status')->options([
                'active' => 'Active',
                'suspended' => 'Suspended',
                'closed' => 'Closed',
            ])->displayUsingLabels()->rules('required'),
            Boolean::make('Active', 'active_yn'),
            BelongsTo::make('Created By', 'createdBy', User::class)->readonly(),
            BelongsToMany::make('Users'),
            BelongsToMany::make('Agents'),
            HasMany::make('Charges'),
            DateTime::make('Created At')->readonly(),
            DateTime::make('Updated At')->readonly(),
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
        return [];
    }
}
