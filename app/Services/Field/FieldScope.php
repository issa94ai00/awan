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
     * The warehouse a branch falls back on when its own shelf runs short.
     *
     * This is a *supply source*, not an extension of the person's authority.
     * A rep confined to their branch may see what the main warehouse holds and
     * may draw on it to complete a sale — they still cannot raise an order
     * against it, receive into it, or read its movement history, because
     * `warehouseIds()` is what those endpoints ask and it is untouched.
     *
     * Keeping the two apart is the whole point: "where may I sell from" and
     * "where may the company source from on my behalf" are different questions,
     * and answering them with one list is how a branch account quietly gains
     * the run of the company's stock.
     */
    public function supplyWarehouse(): ?Warehouse
    {
        $primary = Warehouse::where('is_primary', true)->where('is_active', true)->first();

        // Nothing to fall back on when the rep already works out of the main
        // warehouse — a top-up from yourself is not a top-up.
        if (!$primary || $primary->id === $this->homeWarehouseId()) {
            return null;
        }

        return $primary;
    }

    public function supplyWarehouseId(): ?int
    {
        return $this->supplyWarehouse()?->id;
    }

    /**
     * Warehouses whose stock figures this person may *read* — their own, plus
     * the supply source. Never use this to authorise a write.
     *
     * @return array<int,int>
     */
    public function visibleWarehouseIds(): array
    {
        $ids = $this->warehouseIds();

        if ($supply = $this->supplyWarehouseId()) {
            $ids[] = $supply;
        }

        return array_values(array_unique($ids));
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
        $supply = $this->supplyWarehouse();

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
            // Named up front so the app can word its shortage prompt with the
            // real warehouse rather than a generic "the main warehouse".
            'supply_warehouse' => $supply ? [
                'id' => $supply->id,
                'name' => $supply->name,
                'code' => $supply->code,
                'location_type' => $supply->location_type,
            ] : null,
        ];
    }
}
