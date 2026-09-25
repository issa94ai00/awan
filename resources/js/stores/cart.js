import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useCartStore = defineStore('cart', () => {
    const cart = ref(null);
    const loading = ref(false);
    const error = ref(null);
    const addingProductIds = ref(new Set());

    const totalItems = computed(() => cart.value?.total_items || 0);
    const totalAmount = computed(() => parseFloat(cart.value?.total || 0));

    // Keyed per listing row: a store card for one variant spins on its own,
    // not with every other variant of the same product.
    const addingKey = (productId, variantId = null) => (variantId ? `${productId}-${variantId}` : productId);
    const isAdding = (productId, variantId = null) => addingProductIds.value.has(addingKey(productId, variantId));

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

    // Add item to cart. A variant listed as its own product passes its id so
    // the cart holds that variant, at its price.
    async function addToCart(productId, quantity = 1, variantId = null) {
        const key = addingKey(productId, variantId);
        addingProductIds.value = new Set(addingProductIds.value).add(key);
        try {
            const res = await axios.post('/api/v1/cart/add', {
                product_id: productId,
                variant_id: variantId || undefined,
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
            error.value = err.response?.data?.message || err.message;
            throw err;
        } finally {
            const next = new Set(addingProductIds.value);
            next.delete(key);
            addingProductIds.value = next;
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
        isAdding,
        fetchCart,
        addToCart,
        updateQuantity,
        removeItem,
        clearCart
    };
});

export default useCartStore;
