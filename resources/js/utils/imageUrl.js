/**
 * Utility: getImageUrl
 * 
 * Normalizes image paths returned from the API.
 * The API (via Laravel's asset()) already returns full absolute URLs,
 * so we must NOT prepend /storage/ again. We simply return absolute
 * URLs as-is, and only prepend /storage/ for relative paths.
 *
 * @param {string|null} path - Image path or full URL from the API
 * @param {string} [fallback] - Fallback image path
 * @returns {string}
 */
export function getImageUrl(path, fallback = '/assets/images/placeholder.jpg') {
    if (!path) return fallback;

    // Already an absolute URL (returned by Laravel's asset() helper)
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    // Static assets bundled with the frontend
    if (path.startsWith('/assets/') || path.startsWith('assets/')) {
        return path.startsWith('/') ? path : '/' + path;
    }

    // Already has /storage/ prefix
    if (path.startsWith('/storage/')) {
        return path;
    }

    // Relative path — prepend /storage/
    return '/storage/' + path;
}
