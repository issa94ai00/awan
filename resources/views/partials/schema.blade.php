@php
    // Basic site/contact info from settings
    $siteName = get_setting('site_name') ?? '';
    $description = get_setting('site_description') ?? '';
    $siteUrl = url('/');
    $logoPath = get_setting('site_logo') ? (str_starts_with(get_setting('site_logo'), 'assets/') ? asset(get_setting('site_logo')) : asset('storage/' . get_setting('site_logo'))) : asset('assets/images/logo.png');

    $telephone = get_setting('contact_phone') ?: null;
    $email = get_setting('contact_email') ?: null;
    $address = get_setting('address') ?: get_setting('contact_address') ?: null;

    // Social links (filter empty or '#')
    $socialKeys = ['facebook','instagram','twitter','youtube','linkedin','contact_facebook','contact_instagram','contact_twitter'];
    $sameAs = [];
    foreach ($socialKeys as $k) {
        $val = get_setting($k);
        if ($val && $val !== '#') $sameAs[] = $val;
    }
    $sameAs = array_values(array_unique($sameAs));

    // Opening hours: prefer admin `working_hours` setting (free text). Try to normalize Arabic day names to schema abbreviations.
    $working = get_setting('working_hours') ?: null;
    $openingHours = [];

    if ($working) {
        // Normalize separators and shorten spaces
        $w = trim($working);
        $w = str_replace(['إلى', 'الى', '–', '—'], '-', $w);
        $w = preg_replace('/\s*[-–—]\s*/u', '-', $w);
        $w = preg_replace('/\s*[,]\s*/u', ';', $w);

        // Arabic day name to schema abbreviation
        $map = [
            'الأحد' => 'Su', 'الاحد' => 'Su',
            'الاثنين' => 'Mo', 'الإثنين' => 'Mo',
            'الثلاثاء' => 'Tu',
            'الأربعاء' => 'We', 'الاربعاء' => 'We',
            'الخميس' => 'Th',
            'الجمعة' => 'Fr',
            'السبت' => 'Sa',
            'الاحد' => 'Su'
        ];

        // Replace Arabic day names with abbreviations
        $pattern = array_keys($map);
        $replacements = array_values($map);
        $w_trans = str_replace($pattern, $replacements, $w);

        // Split multiple rules by semicolon or newline
        $parts = preg_split('/[;\\n\\r]+/', $w_trans);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!$p) continue;
            // Ensure time range uses dash without spaces e.g. 09:00-17:00
            $p = preg_replace('/(\d{1,2}[:.]?\d{0,2})\s*[-–—to]+\s*(\d{1,2}[:.]?\d{0,2})/i', '$1-$2', $p);
            // Replace dots in time with colon
            $p = preg_replace('/(\d{1,2})\.(\d{2})/', '$1:$2', $p);
            // If resulting string contains day abbreviations or digits, add as openingHours entry
            if (preg_match('/(Su|Mo|Tu|We|Th|Fr|Sa|\d{1,2}:\d{2})/i', $p)) {
                $openingHours[] = $p;
            }
        }

        $openingHours = array_values(array_filter($openingHours));
    }

    // Build main schema
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Store',
        'name' => $siteName,
        'description' => $description,
        'url' => $siteUrl,
        'logo' => $logoPath,
    ];

    if ($telephone) $schema['telephone'] = $telephone;
    if ($email) $schema['email'] = $email;
    if ($address) {
        $schema['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
        ];
    }
    if (!empty($sameAs)) $schema['sameAs'] = $sameAs;
    if (!empty($openingHours)) $schema['openingHours'] = $openingHours;

    // Output JSON-LD
@endphp

<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
