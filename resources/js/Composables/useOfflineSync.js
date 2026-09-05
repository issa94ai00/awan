import { ref, computed, onBeforeUnmount } from 'vue';

const DEFAULT_STORAGE_KEY = 'price_offer_pending_changes';

function readQueue(key) {
    try {
        const raw = localStorage.getItem(key);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function useOfflineSync(options = {}) {
    const {
        storageKey = DEFAULT_STORAGE_KEY,
        send = async () => {},
        onSynced = () => {},
        onPermanentError = () => {},
        onStateChange = () => {},
    } = options;

    const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const queue = ref(readQueue(storageKey));
    const syncing = ref(false);
    const lastSyncAt = ref(null);

    const pendingCount = computed(() => queue.value.length);

    function persist() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(queue.value));
        } catch {
            // Private mode / quota exceeded — the queue just won't survive reload.
        }
    }

    /**
     * Queue a change for a row (`p-{id}` / `v-{id}` + a field patch). Editing
     * the same row again while it is still pending merges the patch (latest
     * field value wins) instead of stacking redundant API calls.
     */
    function enqueue(id, patch) {
        const existing = queue.value.find((e) => e.id === id);
        if (existing) {
            existing.patch = { ...existing.patch, ...patch };
        } else {
            queue.value.push({ id, patch });
        }
        persist();
        onStateChange();
    }

    function remove(id) {
        queue.value = queue.value.filter((e) => e.id !== id);
        persist();
        onStateChange();
    }

    function clear() {
        queue.value = [];
        persist();
        onStateChange();
    }

    // A failed request caused by being offline has no HTTP response; real
    // server rejections (validation, permissions…) do, and must not loop.
    const isNetworkError = (err) => !err || !err.response;

    async function sendEntry(entry) {
        try {
            await send(entry);
            remove(entry.id);
            return { ok: true };
        } catch (err) {
            if (isNetworkError(err)) {
                return { ok: false, transient: true, error: err };
            }
            remove(entry.id);
            return { ok: false, transient: false, error: err };
        }
    }

    /**
     * Push every queued change to the server. `force` ignores the online flag
     * (the manual "Sync now" button); otherwise a sync while offline is a no-op.
     */
    async function syncPending(force = false) {
        if (syncing.value) return { sent: 0, failed: 0, kept: 0 };
        if (!force && !isOnline.value) return { sent: 0, failed: 0, kept: queue.value.length };
        if (queue.value.length === 0) return { sent: 0, failed: 0, kept: 0 };

        syncing.value = true;
        let sent = 0;
        let failed = 0;
        const snapshot = [...queue.value];
        for (const entry of snapshot) {
            if (!isOnline.value && !force) break;
            const result = await sendEntry(entry);
            if (result.ok) {
                sent += 1;
            } else if (result.transient) {
                onStateChange();
            } else {
                failed += 1;
                onPermanentError(entry, result.error);
            }
        }
        syncing.value = false;
        lastSyncAt.value = new Date();
        if (sent > 0) onSynced(sent);
        return { sent, failed, kept: queue.value.length };
    }

    function goOnline() {
        isOnline.value = true;
    }

    function goOffline() {
        isOnline.value = false;
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('online', goOnline);
        window.addEventListener('offline', goOffline);
        onBeforeUnmount(() => {
            window.removeEventListener('online', goOnline);
            window.removeEventListener('offline', goOffline);
        });
    }

    return {
        isOnline,
        queue,
        pendingCount,
        syncing,
        lastSyncAt,
        enqueue,
        remove,
        clear,
        syncPending,
        isNetworkError,
    };
}