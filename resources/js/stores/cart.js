import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useCartStore = defineStore('cart', () => {
    const cart = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const totalItems = computed(() => cart.value?.total_items || 0);
    const totalAmount = computed(() => parseFloat(cart.value?.total || 0));

    // Fetch current cart data
    async function fetchCart() {
        loading.value = true;
        try {
            const res = await axios.get('/api/v1/cart/data');
            if (res.data?.success) {
                cart.value = res.data.cart;
            }
        } catch (err) {
            console.error('Failed to fetch cart', err);
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    }

    // Add item to cart
    async function addToCart(productId, quantity = 1) {
        loading.value = true;
        try {
            const res = await axios.post('/api/v1/cart/add', {
                product_id: productId,
                quantity: quantity
            });
            if (res.data?.success) {
                await fetchCart();
                return res.data;
            } else {
                throw new Error(res.data?.message || 'Failed to add item');
            }
        } catch (err) {
            console.error('Add to cart failed', err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Update item quantity
    async function updateQuantity(itemId, quantity) {
        loading.value = true;
        try {
            const res = await axios.post(`/api/v1/cart/update/${itemId}`, {
                quantity: quantity
            });
            if (res.data?.success) {
                await fetchCart();
                return res.data;
            } else {
                throw new Error(res.data?.message || 'Failed to update quantity');
            }
        } catch (err) {
            console.error('Update quantity failed', err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Remove item from cart
    async function removeItem(itemId) {
        loading.value = true;
        try {
            const res = await axios.post(`/api/v1/cart/remove/${itemId}`);
            if (res.data?.success) {
                await fetchCart();
                return res.data;
            } else {
                throw new Error(res.data?.message || 'Failed to remove item');
            }
        } catch (err) {
            console.error('Remove item failed', err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Clear entire cart
    async function clearCart() {
        loading.value = true;
        try {
            const res = await axios.post('/api/v1/cart/clear');
            if (res.data?.success) {
                cart.value = {
                    id: cart.value?.id,
                    session_id: cart.value?.session_id,
                    user_id: cart.value?.user_id,
                    total: 0,
                    total_items: 0,
                    items: []
                };
                return res.data;
            }
        } catch (err) {
            console.error('Clear cart failed', err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        cart,
        loading,
        error,
        totalItems,
        totalAmount,
        fetchCart,
        addToCart,
        updateQuantity,
        removeItem,
        clearCart
    };
});

export default useCartStore;
