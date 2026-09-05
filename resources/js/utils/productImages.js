/**
 * One place that knows how a product's pictures are addressed.
 *
 * Three screens each grew their own copy of "main image, then the gallery":
 * the products table, the detail page and the offer table. They disagreed —
 * one threw on a malformed `image_gallery` string mid-render, none dropped
 * blanks, and none noticed when the main image was also a gallery entry, so
 * the lightbox showed the same photo twice.
 */

/**
 * The prefixes the server's image_url() helper serves straight out of public/.
 * Everything else it serves through the storage/ symlink — prepending
 * /storage/ to one of these (which is what utils/imageUrl.js used to do)
 * produces a 404, and most of this catalogue's pictures live in images_items/.
 */
const PUBLIC_PREFIXES = ['assets/', 'images/', 'images_items/', 'img/', 'css/', 'js/', 'fonts/'];

const isAbsolute = (value) => /^(https?:)?\/\//i.test(value);

/**
 * Reduce any image reference — absolute URL, "/storage/..." path, bare path —
 * to the relative path the API stores. Mirrors the server's image_path().
 * Images genuinely hosted elsewhere keep their absolute URL: there is no
 * local path for them.
 */
export function toImagePath(value) {
    if (typeof value !== 'string' || !value.trim()) return '';

    let path = value.trim();

    if (isAbsolute(path)) {
        let parsed;
        try {
            parsed = new URL(path, window.location.origin);
        } catch {
            return path;
        }
        if (parsed.origin !== window.location.origin) return path;
        path = parsed.pathname;
    }

    path = path.split('?')[0].replace(/^\/+/, '');

    return path.startsWith('storage/') ? path.slice('storage/'.length) : path;
}

/**
 * Turn a stored path back into something the browser can load.
 *
 * The query string survives on purpose. The server stamps "?v=<mtime>" onto
 * the pictures it hosts itself, which is what lets a replaced file — or one
 * whose 404 a browser cached while it was briefly missing — be seen at all:
 * the cache is keyed on the whole URL. Rebuilding from the bare path threw
 * that away and put the stale entry straight back. `toImagePath()` still
 * drops it, because the bare path is what belongs in the database.
 */
export function resolveImageUrl(value) {
    const path = toImagePath(value);
    if (!path) return '';
    // A picture hosted elsewhere comes back whole, query and all.
    if (isAbsolute(path)) return path;

    // A non-string never reaches here: toImagePath() returns '' for one.
    const mark = value.trim().indexOf('?');
    const query = mark === -1 ? '' : value.trim().slice(mark);
    const base = PUBLIC_PREFIXES.some(prefix => path.startsWith(prefix)) ? `/${path}` : `/storage/${path}`;

    return base + query;
}

/** The gallery column holds JSON; older rows and some endpoints hand back arrays. */
function readGallery(product) {
    const gallery = product?.image_gallery;

    if (Array.isArray(gallery)) return gallery;
    if (typeof gallery !== 'string' || !gallery) return [];

    try {
        const parsed = JSON.parse(gallery);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

/**
 * Every picture a product has, main one first, ready to hand to a viewer:
 * blanks dropped, duplicates collapsed however they were written.
 */
export function productImages(product) {
    if (!product) return [];

    const entries = [product.image_main, ...readGallery(product)];
    const seen = new Set();

    return entries
        .map(entry => (typeof entry === 'string' ? entry : (entry?.url || entry?.path || '')))
        .map(resolveImageUrl)
        .filter((url) => {
            if (!url) return false;
            const key = toImagePath(url);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
}

/** True when both references point at the same stored file. */
export function isSameImage(a, b) {
    const left = toImagePath(a);
    return !!left && left === toImagePath(b);
}
