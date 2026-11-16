// Strings for data-quality warnings: below-cost sales (api/index.js,
// products/Form.vue) and over-receipt on purchase receipts
// (components/admin/purchases/PurchaseReceiptForm.vue).
export default {
    en: {
        dq_warning_title: 'Please check',
        dq_below_cost_hint: 'Selling price is below cost ({cost})',
        rc_over_tolerance_badge: '{pct}% over',
        rc_over_tolerance_title: 'More than {limit}% over the order',
        rc_over_tolerance_message: 'These lines receive more than {limit}% above what the purchase order still expects: {lines}. Save the receipt anyway?',
    },
    ar: {
        dq_warning_title: 'تنبيه',
        dq_below_cost_hint: 'سعر البيع أقل من التكلفة ({cost})',
        rc_over_tolerance_badge: 'زيادة {pct}%',
        rc_over_tolerance_title: 'زيادة تتجاوز {limit}% عن طلب الشراء',
        rc_over_tolerance_message: 'هذه الأسطر تستلم أكثر من {limit}% فوق المتبقي في طلب الشراء: {lines}. هل تحفظ الإيصال رغم ذلك؟',
    },
};
