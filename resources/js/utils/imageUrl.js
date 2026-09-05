/**
 * Utility: getImageUrl
 *
 * Normalizes image paths returned from the API. Thin wrapper over
 * `resolveImageUrl` in utils/productImages.js so every screen resolves a
 * picture the same way; this one adds the placeholder callers rely on.
 *
 * It used to prepend /storage/ to any relative path, which broke every image
 * the server serves straight out of public/ — images_items/, assets/, img/ —
 * and that is where most of the catalogue's pictures live.
 *
 * @param {string|null} path - Image path or full URL from the API
 * @param {string} [fallback] - Fallback image path
 * @returns {string}
 */
import { resolveImageUrl } from './productImages';

export function getImageUrl(path, fallback = '/assets/images/placeholder.jpg') {
    return resolveImageUrl(path) || fallback;
}
