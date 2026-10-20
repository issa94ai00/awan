<?php

namespace App\Models\Concerns;

use App\Models\ProductUnit;

/**
 * A sale line counted in the unit the customer bought in — a box, a kilo —
 * while stock is counted in the product's base unit.
 *
 * `quantity` stays in the sold unit, because that is what the price, the
 * document and the customer refer to. Anything that reserves, moves or checks
 * stock has to ask for the base units instead: selling two boxes of twelve
 * takes twenty-four pieces off the shelf, not two.
 */
trait SoldInUnits
{
    /** Base units one of this line's units holds; 1 for a line with no unit. */
    public function stockFactor(): int
    {
        return ProductUnit::stockFactorOf($this->unit_multiplier);
    }

    /** The line's quantity — or the given part of it — in base units. */
    public function baseQuantity(?int $quantity = null): int
    {
        return ($quantity ?? (int) $this->quantity) * $this->stockFactor();
    }
}
