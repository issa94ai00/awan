/**
 * Picking what a line on an order or receipt is for: a product, or one
 * variant of it — the 4" floor drain rather than "floor drain".
 *
 * The line pickers search with `expand_variants=1`, which returns one row per
 * variant (and one for each product without variants). Every row carries a
 * `listing_key` — "12" for product 12, "12-40" for its variant 40 — and that is
 * what a picker binds to, since the product id alone no longer says which row
 * was chosen. A saved line is sent back as `product_id` + `product_variant_id`.
 */

/** The key a line or search row is picked by. */
export function pickKey(productId, variantId = null) {
    if (!productId) return '';
    return variantId ? `${productId}-${variantId}` : String(productId);
}

/** The key of a search row, whichever endpoint returned it. */
export function optionKey(option) {
    return option.listing_key || pickKey(option.id, option.variant_id);
}

/** "4\" / white" — what tells a variant apart from its siblings. */
export function variantLabelOf(variant) {
    if (!variant) return '';
    return [variant.size, variant.color, variant.material]
        .map((part) => (part == null ? '' : String(part).trim()))
        .filter(Boolean)
        .join(' / ');
}

/** The product's own name on a search row, without the variant appended. */
export function baseName(option) {
    return option.product_name_ar || option.name_ar || option.name || '';
}

/**
 * A saved line (with its `product` and `variant` relations) as a search row,
 * so a picker can show it before any search has run — editing, duplicating.
 */
export function optionFromLine(line) {
    const variant = line.variant || null;
    const product = line.product || {};
    const variantId = line.product_variant_id || variant?.id || null;
    const label = line.variant_label || variantLabelOf(variant);

    return {
        ...product,
        id: line.product_id,
        variant_id: variantId,
        listing_key: pickKey(line.product_id, variantId),
        product_name_ar: product.name_ar || line.product_name,
        name_ar: variantId ? (line.product_name || line.description || product.name_ar) : (product.name_ar || line.product_name),
        variant_label: label,
        sku: variant?.sku || line.variant_sku || product.sku,
        cost_price: variant && Number(variant.cost_price) > 0 ? variant.cost_price : product.cost_price,
        price: variant && Number(variant.price) > 0 ? variant.price : product.price,
    };
}

/**
 * Adds rows a picker must be able to show to its option list, skipping any
 * already there. Returns the new list.
 */
export function withOptions(options, extra) {
    const known = new Set(options.map(optionKey));
    const missing = extra.filter((option) => option && !known.has(optionKey(option)));
    return missing.length ? [...missing, ...options] : options;
}

/**
 * A product's `variants` relation (as the product endpoint returns it) as
 * search rows, shaped like the rows `expand_variants=1` returns so a picker
 * cannot tell them apart. A variant with no price of its own sells at the
 * product's price, sale included; one with its own price has no sale.
 */
export function variantRowsOf(product) {
    if (!product?.id) return [];
    return (product.variants || []).map((variant) => {
        const label = variant.label || variantLabelOf(variant);
        const suffix = label ? ` - ${label}` : '';
        const hasOwnPrice = Number(variant.price) > 0;
        return {
            ...product,
            variants: undefined,
            variant_id: variant.id,
            listing_key: pickKey(product.id, variant.id),
            variant_label: label,
            product_name_ar: product.name_ar,
            product_name_en: product.name_en,
            name_ar: `${product.name_ar || ''}${suffix}`,
            name_en: product.name_en ? `${product.name_en}${suffix}` : product.name_en,
            sku: variant.sku || product.sku,
            barcode: variant.barcode || product.barcode,
            size: variant.size,
            color: variant.color,
            material: variant.material,
            price: hasOwnPrice ? variant.price : product.price,
            sale_price: hasOwnPrice ? null : product.sale_price,
            has_sale: hasOwnPrice ? false : product.has_sale,
            cost_price: Number(variant.cost_price) > 0 ? variant.cost_price : product.cost_price,
            stock_quantity: variant.stock_quantity,
            product_stock_quantity: product.stock_quantity,
        };
    });
}
