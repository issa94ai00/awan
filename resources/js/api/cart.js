import api from './index';

const cartApi = {
    get() {
        return api.get('/cart');
    },
    add(productId, quantity = 1) {
        return api.post('/cart/add', { product_id: productId, quantity });
    },
    update(itemId, quantity) {
        return api.post(`/cart/update/${itemId}`, { quantity });
    },
    remove(itemId) {
        return api.post(`/cart/remove/${itemId}`);
    },
    clear() {
        return api.post('/cart/clear');
    },
    count() {
        return api.get('/cart/count');
    }
};

export default cartApi;
