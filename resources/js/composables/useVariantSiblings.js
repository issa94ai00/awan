import { reactive } from 'vue';
import { productsApi } from '@/api/products';
import { variantRowsOf } from '@/utils/productPick';

/**
 * Every size seen of each product, by product id, so a line on an order can
 * offer its sibling sizes. Search results feed it as they arrive, but a
 * search by one size's code returns only that size, so a picked product's
 * full set is fetched once from the product endpoint and replaces whatever
 * partial list was there.
 */
export function useVariantSiblings() {
    const variantsByProduct = reactive({});
    const loaded = new Set();

    const rememberVariantRows = (rows = [], { complete = false } = {}) => {
        const byProduct = new Map();
        rows.forEach((row) => {
            if (!row?.id || !row.variant_id) return;
            if (!byProduct.has(row.id)) byProduct.set(row.id, []);
            byProduct.get(row.id).push(row);
        });
        byProduct.forEach((fresh, productId) => {
            const merged = new Map((complete ? [] : variantsByProduct[productId] || []).map((r) => [r.variant_id, r]));
            fresh.forEach((r) => merged.set(r.variant_id, r));
            variantsByProduct[productId] = [...merged.values()].sort((a, b) => a.variant_id - b.variant_id);
        });
    };

    const ensureVariantsLoaded = async (productId) => {
        if (!productId || loaded.has(productId)) return;
        loaded.add(productId);
        try {
            const res = await productsApi.getById(productId);
            const product = res.data?.data;
            if (product) rememberVariantRows(variantRowsOf(product), { complete: true });
        } catch {
            // Try again next time the product is picked.
            loaded.delete(productId);
        }
    };

    const siblingsOf = (productId) => variantsByProduct[productId] || [];

    return { variantsByProduct, rememberVariantRows, ensureVariantsLoaded, siblingsOf };
}
