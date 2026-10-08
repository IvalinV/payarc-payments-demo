<?php

namespace App\Nova;

use App\Containers\AppSection\Payments\Models\Agent as AgentModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Agent extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<AgentModel>
     */
    public static $model = AgentModel::class;

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
        return (bool) $request->user()?->can('agents.manage');
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
            Text::make('First Name')->sortable()->rules('required', 'max:255'),
            Text::make('Last Name')->sortable()->rules('required', 'max:255'),
            Text::make('Email')->sortable()->rules('required', 'email', 'max:254'),
            Text::make('Company Legal Name')->rules('required', 'max:255'),
            Text::make('Company DBA')->rules('required', 'max:255'),
            Text::make('Type')->rules('required', 'max:255'),
            Number::make('Default Split')->step(0.01)->min(0)->max(100)->rules('required', 'numeric', 'between:0,100'),
            BelongsTo::make('Parent Agent', 'parentAgent', self::class)->nullable(),
            BelongsToMany::make('Accounts'),
            BelongsToMany::make('Users'),
            HasMany::make('Children', 'children', self::class),
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
