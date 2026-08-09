<?php

namespace App\Services\Field;

use App\Models\Employee;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Decides which warehouses the signed-in person may act on.
 *
 * Every field endpoint is scoped to a warehouse, and that scope is the only
 * thing standing between a rep in one branch and another branch's stock. It is
 * resolved in one place so no endpoint can quietly widen it: a controller asks
 * this class, it never reads `warehouse_id` off the request and trusts it.
 *
 * The rule:
 *
 *  - A user with an employee record attached to a warehouse is confined to it.
 *    That is the field case — a rep sells out of the branch they stand in.
 *  - A user with no employee record (back office, admin) is not confined, so
 *    the same app can be used to cover any location.
 *
 * A user whose employee record has no warehouse is treated as unconfined rather
 * than locked out, because a half-configured staff record should not silently
 * disable the app for them.
 */
class FieldScope
{
    public function __construct(private User $user)
    {
    }

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function employee(): ?Employee
    {
        return $this->user->employee;
    }

    /** The warehouse this person works out of, if they are tied to one. */
    public function homeWarehouseId(): ?int
    {
        $id = $this->employee()?->warehouse_id;

        return $id ? (int) $id : null;
    }

    public function isConfined(): bool
    {
        return $this->homeWarehouseId() !== null;
    }

    /** @return Collection<int,Warehouse> */
    public function warehouses(): Collection
    {
        if ($this->isConfined()) {
            return Warehouse::where('id', $this->homeWarehouseId())->get();
        }

        return Warehouse::where('is_active', true)->orderBy('id')->get();
    }

    /** @return array<int,int> */
    public function warehouseIds(): array
    {
        return $this->warehouses()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Resolves the warehouse an operation should run against.
     *
     * A requested warehouse is honoured only if the person is allowed to use it;
     * anything else is refused by name rather than silently redirected, because
     * quietly serving a different warehouse than the one asked for is how stock
     * ends up moving in the wrong building.
     *
     * @throws RuntimeException when the requested warehouse is out of scope
     */
    public function resolveWarehouseId(?int $requested): int
    {
        $allowed = $this->warehouseIds();

        if ($allowed === []) {
            throw new RuntimeException('لا يوجد مستودع نشط متاح لحسابك.');
        }

        if (!$requested) {
            return $this->homeWarehouseId() ?? $allowed[0];
        }

        if (!in_array($requested, $allowed, true)) {
            $name = Warehouse::whereKey($requested)->value('name') ?? ('#' . $requested);

            throw new RuntimeException('غير مصرّح لك بالعمل على مستودع "' . $name . '".');
        }

        return $requested;
    }

    /** How the app should describe the signed-in person. */
    public function profile(): array
    {
        $employee = $this->employee();
        $home = $this->homeWarehouseId() ? Warehouse::find($this->homeWarehouseId()) : null;

        return [
            'user_id' => $this->user->id,
            'name' => $employee?->name ?: $this->user->name,
            'email' => $this->user->email,
            'employee_id' => $employee?->id,
            'job_title' => $employee?->job_title ?? $employee?->position,
            'is_confined_to_warehouse' => $this->isConfined(),
            'home_warehouse' => $home ? [
                'id' => $home->id,
                'name' => $home->name,
                'code' => $home->code,
                'location_type' => $home->location_type,
            ] : null,
        ];
    }
}
