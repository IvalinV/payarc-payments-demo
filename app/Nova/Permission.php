<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Containers\AppSection\Authorization\Models\Permission as PermissionModel;

final class Permission extends Resource
{
    public static $model = PermissionModel::class;

    public static $title = 'name';

    public static $displayInNavigation = false;

    public static $search = [
        'id',
        'name',
        'guard_name',
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->sortable()->rules('required', 'max:255'),
            Text::make('Guard Name')->readonly(),
            BelongsToMany::make('Users'),
            BelongsToMany::make('Roles'),
        ];
    }
}
