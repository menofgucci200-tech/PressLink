<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rôle d'un membre de l'équipe dans un pressing : un nom (« Gérant »,
 * « Caissière »…) et les accès cochés (App\Enums\Permission). Chaque pressing
 * a ses propres rôles ; « Propriétaire » est verrouillé sur tous les accès.
 */
class Role extends Model
{
    public const OWNER = 'owner';

    public const MANAGER = 'manager';

    public const EMPLOYEE = 'employee';

    /** Coursier : seulement la tournée (ramasser, livrer, encaisser à la porte). */
    public const COURIER = 'courier';

    public const COURIER_NAME = 'Coursier';

    protected $fillable = ['pressing_id', 'key', 'name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    /** @return array<string, array{name: string, permissions: list<string>}> rôles créés pour chaque pressing */
    public static function defaults(): array
    {
        $employee = [
            Permission::OrdersCreate, Permission::PaymentsRecord, Permission::OrdersWorkshop,
            Permission::OrdersCancel, Permission::IssuesManage, Permission::DeliveriesRun,
        ];
        $manager = [
            ...$employee,
            Permission::OrdersCancelInProgress, Permission::OpenExceptionally, Permission::CashClose, Permission::CashExpenses,
            Permission::CatalogManage, Permission::TeamManage,
        ];

        return [
            self::OWNER => ['name' => 'Propriétaire', 'permissions' => Permission::all()],
            self::MANAGER => ['name' => 'Gérant', 'permissions' => array_column($manager, 'value')],
            self::EMPLOYEE => ['name' => 'Employé', 'permissions' => array_column($employee, 'value')],
            self::COURIER => ['name' => self::COURIER_NAME, 'permissions' => [Permission::DeliveriesRun->value]],
        ];
    }

    public static function createDefaultsFor(Pressing $pressing): void
    {
        foreach (self::defaults() as $key => $role) {
            if ($pressing->roles()->where('key', $key)->exists()) {
                continue;
            }

            // Rôle du même nom déjà créé par le pressing (ex. son propre « Coursier ») :
            // il devient le rôle de base, avec les accès qu'il lui a donnés.
            $sameName = $pressing->roles()->whereNull('key')->where('name', $role['name'])->first();
            $sameName ? $sameName->update(['key' => $key]) : $pressing->roles()->create(['key' => $key, ...$role]);
        }
    }

    public function isOwner(): bool
    {
        return $this->key === self::OWNER;
    }

    /** @return list<string> */
    public function permissionValues(): array
    {
        return $this->isOwner() ? Permission::all() : array_values(array_intersect(Permission::all(), $this->permissions ?? []));
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissionValues(), true);
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return HasMany<PressingUser, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(PressingUser::class);
    }
}
