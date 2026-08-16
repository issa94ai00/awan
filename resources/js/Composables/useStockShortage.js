import { h } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessageBox } from 'element-plus';
import { useAuthStore } from '@/stores/auth';

/**
 * Turning a refused confirmation into the purchase order that would fix it.
 *
 * Confirming an order the network cannot cover fails with a sentence naming the
 * short products. That told the operator what was wrong and left them to do the
 * rest by hand: open purchasing, find each product again, work out how many were
 * missing, and type it in — from numbers they could only read out of an error
 * message. Most of the way to the answer, and then a wall.
 *
 * The API now returns the shortfall as data, so the refusal can offer the next
 * step instead of describing the problem. Answering "yes" opens the purchase
 * order screen already filled in.
 */
export function useStockShortage() {
    const router = useRouter();
    const { t } = useI18n();
    const authStore = useAuthStore();

    /**
     * Whether this user can actually raise a purchase order.
     *
     * Purchasing is an admin area — both in the sidebar and, since the
     * authorisation pass, on the API. Offering the button to a sales account
     * would send them to a screen that refuses them, which is a worse answer
     * than not offering it: they would read the refusal as the system being
     * broken rather than as the task belonging to someone else.
     */
    const canRaisePurchaseOrder = () => {
        const user = authStore.user;
        if (!user) return false;

        const role = (user.role?.name || user.role_name || '').toLowerCase();
        return Boolean(user.is_admin) || role === 'admin';
    };

    /**
     * Handles an error from a confirm attempt.
     *
     * @param {unknown} error The rejected axios error.
     * @param {number|string} orderId
     * @returns {Promise<boolean>} true when this was a stock shortage and has
     *   been shown, so the caller does not also raise a generic error toast.
     */
    const handleStockShortage = async (error, orderId) => {
        const shortages = error?.response?.data?.data?.shortages;

        if (!Array.isArray(shortages) || shortages.length === 0) {
            return false;
        }

        const mayPurchase = canRaisePurchaseOrder();

        try {
            await ElMessageBox.confirm(
                buildShortageBody(shortages, t, mayPurchase),
                t('sales.stock_shortage_title'),
                {
                    type: 'warning',
                    // Without the rights to buy, the dialog is purely
                    // informative: it names the shortfall and closes.
                    confirmButtonText: mayPurchase ? t('sales.create_purchase_order') : t('close'),
                    showCancelButton: mayPurchase,
                    cancelButtonText: t('close'),
                    // The body is a VNode table, not text.
                    dangerouslyUseHTMLString: false,
                    customClass: 'stock-shortage-box',
                }
            );
        } catch {
            // Dismissed: the operator has seen the shortfall and chosen to deal
            // with it another way. Still handled — no second error toast.
            return true;
        }

        if (mayPurchase) {
            router.push(`/admin/purchases/orders?shortage_for_order=${orderId}`);
        }

        return true;
    };

    return { handleStockShortage };
}

/**
 * The shortfall as a small table.
 *
 * Built as VNodes rather than an HTML string so product names coming from the
 * database cannot inject markup into the dialog.
 */
function buildShortageBody(shortages, t, mayPurchase = true) {
    const header = h('div', { class: 'shortage-row shortage-row--head' }, [
        h('span', t('product')),
        h('span', t('sales.required')),
        h('span', t('sales.available')),
        h('span', t('sales.shortfall')),
    ]);

    const rows = shortages.map((row) => h('div', { class: 'shortage-row' }, [
        h('span', { class: 'shortage-name', title: row.name }, row.name),
        h('span', String(row.required)),
        h('span', String(row.available)),
        h('span', { class: 'shortage-missing' }, String(row.shortfall)),
    ]));

    return h('div', { class: 'shortage-body' }, [
        h('p', { class: 'shortage-lead' }, t('sales.stock_shortage_lead')),
        h('div', { class: 'shortage-table' }, [header, ...rows]),
        // Offer the next step to whoever can take it; tell everyone else who can.
        h('p', { class: 'shortage-foot' }, mayPurchase
            ? t('sales.stock_shortage_prompt')
            : t('sales.stock_shortage_notify_purchasing')),
    ]);
}
