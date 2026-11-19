<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A table or column name that is about to be written into SQL text.
 *
 * Identifiers cannot be bound as parameters, so wherever one has to be placed
 * in a raw expression it goes through here first: a plain name, optionally
 * qualified (`table.column`), or it is refused. That keeps "the name comes
 * from the code" a checked fact rather than an assumption.
 */
final class SqlIdentifier
{
    /** @throws InvalidArgumentException when the name is not a plain identifier */
    public static function assert(string $name): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name)) {
            throw new InvalidArgumentException("Not a plain SQL identifier: {$name}");
        }

        return $name;
    }
}
