import api from './index';

/**
 * Currencies and their rates.
 *
 * Amounts everywhere in the system are stored and posted in the base currency;
 * the rates here drive *display* conversion only. See CurrencyService.
 */
const currenciesApi = {
    /** Public list (active currencies + base) for settings, products, storefront. */
    publicList() {
        return api.get('/currencies');
    },

    /** Every currency with its rate history, for the management screen. */
    list() {
        return api.get('/admin/currencies');
    },

    create(payload) {
        return api.post('/admin/currencies', payload);
    },

    update(id, payload) {
        return api.put(`/admin/currencies/${id}`, payload);
    },

    /** Appends a rate. The previous one stays readable, so history holds. */
    addRate(id, payload) {
        return api.post(`/admin/currencies/${id}/rates`, payload);
    },

    setBase(id) {
        return api.post(`/admin/currencies/${id}/base`);
    },
};

export default currenciesApi;
