<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Which rep a public request may credit.
 *
 * The storefront and the rep app share public endpoints (order, register,
 * subscribe). They used to take `employee_id` from the body as given, so any
 * visitor could credit an order — and the customer and its commission — to
 * any rep. Now it is honoured only when the request carries a staff login
 * token: a rep can name themselves, an admin can name anyone. Anything else
 * is dropped and the record arrives unassigned, as a storefront one does.
 *
 * The routes have no auth middleware (guests must still reach them), so the
 * token is read through the sanctum guard directly.
 */
final class TrustedEmployee
{
    public static function resolve(mixed $claimed): ?int
    {
        if ($claimed === null || $claimed === '') {
            return null;
        }

        $user = self::staff();
        if (! $user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return (int) $claimed;
        }

        $own = $user->employee?->id;

        return $own !== null && (int) $own === (int) $claimed ? (int) $own : null;
    }

    /** The signed-in staff user behind a public request, if any. */
    public static function staff(): ?User
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof User ? $user : null;
    }
}
