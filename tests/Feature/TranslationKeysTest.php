<?php

/**
 * The translation file has to stay searchable and free of silent duplicates.
 *
 * `i18n.js` mixes two ways of writing the same key — `close:` and `'close':` —
 * so grepping for one form misses the other. That is not cosmetic: it is how a
 * key gets defined twice. A duplicate raises no error, because the later
 * definition simply wins, so the wrong label ships and nobody is told.
 *
 * It has already happened, more than once, and to me: nine keys were added in
 * this project that were already defined, and `analytics.inventory_value` then
 * rendered with the older wording because that copy came later in the file.
 *
 * Rewriting 3,499 keys into one style would be a large diff for no functional
 * gain. Catching the duplicate is the part that matters, and this does it in
 * either style.
 */
function translationKeyCounts(): array
{
    $source = file_get_contents(resource_path('js/i18n.js'));

    $counts = [];

    // Top-level keys of each locale object sit at exactly two levels of
    // indentation (8 spaces). Deeper keys belong to nested groups, where the
    // same short name may legitimately appear under different parents.
    preg_match_all("/^ {8}(?:'([^']+)'|([A-Za-z_][\w.]*))\s*:/m", $source, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

        if ($key === '') {
            continue;
        }

        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    return $counts;
}

it('defines every translation key at most once per locale', function () {
    $counts = translationKeyCounts();

    // Two locales ship (ar and en), so a key defined in both appears twice.
    // Anything beyond that is a second definition inside one locale, where the
    // later one silently wins.
    $duplicated = array_filter($counts, fn (int $n) => $n > 2);

    $this->assertSame([], $duplicated, sprintf(
        'These keys are defined more than once within a locale, so the last '
        .'definition silently wins and the earlier wording is dropped: %s',
        implode(', ', array_keys($duplicated))
    ));
});

it('finds a healthy number of keys, so the parser has not silently stopped matching', function () {
    // Guards the guard: if the file were restructured and the pattern stopped
    // matching, the duplicate check above would pass on an empty set and this
    // test would quietly protect nothing.
    expect(count(translationKeyCounts()))->toBeGreaterThan(2000);
});

it('defines both locales for every key', function () {
    $counts = translationKeyCounts();

    // A key present in only one locale renders as the raw key for readers of
    // the other — the failure mode that leaves an English identifier sitting in
    // an Arabic screen.
    //
    // Asserted at zero because that is where the file actually is. A tolerance
    // would have been worse than no test: set to "at most 60" it would have
    // waved through sixty regressions without a word.
    $oneSided = array_keys(array_filter($counts, fn (int $n) => $n === 1));

    $this->assertSame([], $oneSided, sprintf(
        'These keys exist in only one locale, so readers of the other see the '
        .'raw key instead of a translation: %s',
        implode(', ', array_slice($oneSided, 0, 20))
    ));
});
