<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Word-by-word search: "خلاط مغسلة ذهبي" finds a row holding all three
 * words, in any order and with anything between them, spread over any of
 * the searched columns. A plain LIKE '%whole phrase%' only found the words
 * when they sat side by side in that exact order.
 */
class SearchTerms
{
    /** The words of a query: split on whitespace, blanks and repeats dropped. */
    public static function words(?string $term): array
    {
        $words = preg_split('/\s+/u', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($words));
    }

    /**
     * A LIKE pattern matching the word anywhere, with its own % and _ taken
     * literally. It escapes with "!", spelt out in every LIKE as ESCAPE '!'
     * because SQLite has no default escape character and MySQL's is "\".
     */
    public static function pattern(string $word): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
    }

    /**
     * Every word has to turn up in at least one of the columns. A column is
     * a plain column ("name", "products.sku"), a relation column on an
     * Eloquent query ("customer.name", "items.product.name_ar"), or a raw
     * SQL expression wrapped as DB::raw(...).
     */
    public static function apply(BuilderContract $query, array $columns, ?string $term): BuilderContract
    {
        foreach (static::words($term) as $word) {
            $like = static::pattern($word);

            $query->where(function ($q) use ($columns, $like, $query) {
                foreach ($columns as $column) {
                    static::orMatch($q, $query, $column, $like);
                }
            });
        }

        return $query;
    }

    private static function orMatch($q, BuilderContract $root, $column, string $like): void
    {
        if ($column instanceof \Illuminate\Contracts\Database\Query\Expression) {
            $q->orWhereRaw($column->getValue($q->getGrammar()) . " LIKE ? ESCAPE '!'", [$like]);

            return;
        }

        // "relation.column" on an Eloquent model is a relation when the model
        // has a method by that name; otherwise it is "table.column".
        if ($root instanceof EloquentBuilder && str_contains($column, '.')) {
            $relation = substr($column, 0, strrpos($column, '.'));
            $first = explode('.', $relation)[0];

            if (method_exists($root->getModel(), $first)) {
                $field = substr($column, strrpos($column, '.') + 1);
                // An AND here: an OR would slip past the relation's own key
                // constraint and match every row.
                $q->orWhereHas($relation, fn ($r) => static::like($r, $field, $like));

                return;
            }
        }

        static::like($q, $column, $like, 'or');
    }

    private static function like($q, string $column, string $like, string $boolean = 'and'): void
    {
        $q->whereRaw($q->getGrammar()->wrap($column) . " LIKE ? ESCAPE '!'", [$like], $boolean);
    }
}
