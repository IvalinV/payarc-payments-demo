<?php

namespace App\Containers\AppSection\User\Models;

use App\Containers\AppSection\Payments\Models\Account;
use App\Containers\AppSection\Payments\Models\Agent;
use App\Containers\AppSection\User\Observers\UserRoleObserver;
use App\Containers\AppSection\Authorization\Enums\Role as RoleEnum;
use App\Containers\AppSection\User\Data\Collections\UserCollection;
use App\Containers\AppSection\User\Enums\Gender;
use App\Ship\Parents\Models\UserModel as ParentUserModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class User extends ParentUserModel
{
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'gender',
        'birth',
        'is_admin',
        'is_agent',
        'is_merchant',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'immutable_datetime',
        'password' => 'hashed',
        'gender' => Gender::class,
        'birth' => 'immutable_date',
        'is_admin' => 'boolean',
        'is_agent' => 'boolean',
        'is_merchant' => 'boolean',
    ];

    public function assignRole(...$roles)
    {
        $result = parent::assignRole(...$roles);
        app(UserRoleObserver::class)->syncFlags($this);

        return $result;
    }

    public function removeRole(...$roles)
    {
        $result = parent::removeRole(...$roles);
        app(UserRoleObserver::class)->syncFlags($this);

        return $result;
    }

    public function syncRoles(...$roles)
    {
        $result = parent::syncRoles(...$roles);
        app(UserRoleObserver::class)->syncFlags($this);

        return $result;
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_user');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_user');
    }

    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }

    /**
     * Allows Passport to find the user by email (case-insensitive).
     */
    public function findForPassport(string $username): self|null
    {
        return self::orWhereRaw('lower(email) = lower(?)', [$username])->first();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleEnum::SUPER_ADMIN, 'web');
    }

    protected function email(): Attribute
    {
        return new Attribute(
            get: static fn (string|null $value): string|null => is_null($value) ? null : strtolower($value),
        );
    }
}
