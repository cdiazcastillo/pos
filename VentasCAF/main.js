document.addEventListener('DOMContentLoaded', () => {

    // --- Responsive Height Fix for iOS VH bug ---
    const setAppHeight = () => {
        const doc = document.documentElement;
        doc.style.setProperty('--app-height', `${window.innerHeight}px`);
    };
    window.addEventListener('resize', setAppHeight);
    window.addEventListener('orientationchange', setAppHeight);
    setAppHeight(); // Set initial value

    // --- STATE ---
    let cart = []; // Array of { id, name, price, quantity }

    // --- SELECTORS ---
    const productGrid = document.getElementById('product-grid');
    const cartItemsContainer = document.getElementById('cart-items');
    const cartTotalElem = document.getElementById('cart-total');
    const cartTotalItemsElem = document.getElementById('cart-total-items');
    const clearCartBtn = document.getElementById('clear-cart-btn');
    const startShiftForm = document.getElementById('start-shift-form');
    
    // Payment Buttons
    const cashPaymentBtn = document.getElementById('cash-payment-btn');
    const transferPaymentBtn = document.getElementById('transfer-payment-btn');

    // --- EVENT LISTENERS ---
    
    // Add product to cart
    if (productGrid) {
        productGrid.addEventListener('click', (e) => {
            const card = e.target.closest('.product-card');
            if (card && !card.hasAttribute('disabled')) {
                const product = {
                    id: card.dataset.id,
                    name: card.dataset.name,
                    price: parseInt(card.dataset.price, 10)
                };
                addToCart(product);
            }
        });
    }

    // Clear the entire cart
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', () => {
            cart = [];
            updateCart();
        });
    }

    // Process sale directly for cash payment
    if (cashPaymentBtn) {
        cashPaymentBtn.addEventListener('click', () => {
            if (cart.length > 0) {
                processSale('cash');
            }
        });
    }
    
    // Process sale directly for transfer payment
    if (transferPaymentBtn) {
        transferPaymentBtn.addEventListener('click', () => {
            if (cart.length > 0) {
                processSale('transfer');
            }
        });
    }

    // Handle "Start Shift" form submission
    if (startShiftForm) {
        startShiftForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const initialCash = document.getElementById('initial_cash').value;
            if (initialCash !== '' && parseInt(initialCash, 10) >= 0) {
                startShift(initialCash);
            }
        });
    }


    // --- FUNCTIONS ---

    function formatWithDots(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function addToCart(product) {
        const existingItem = cart.find(item => item.id === product.id);
        if (existingItem) {
            existingItem.quantity++;
        } else {
            cart.push({ ...product, quantity: 1 });
        }
        updateCart();
    }

    function updateCart() {
        if (!cartItemsContainer || !cartTotalElem) return;

        cartItemsContainer.innerHTML = '';
        let total = 0;
        let totalItems = 0;

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            totalItems += item.quantity;
            
            const itemElem = document.createElement('div');
            itemElem.classList.add('cart-item');
            itemElem.innerHTML = `
                <span class="cart-item-name">${item.name}</span>
                <span class="cart-item-qty">x${item.quantity}</span>
                <span class="cart-item-price">$${formatWithDots(itemTotal)}</span>
                <button class="remove-item-btn" data-id="${item.id}">&times;</button>
            `;
            cartItemsContainer.appendChild(itemElem);
        });

        cartTotalElem.textContent = `$${formatWithDots(total)}`;
        if (cartTotalItemsElem) cartTotalItemsElem.textContent = `${totalItems}`;

        // Add event listeners to remove buttons
        document.querySelectorAll('.remove-item-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                removeFromCart(id);
            });
        });

        // Disable payment buttons if cart is empty
        const isCartEmpty = cart.length === 0;
        if(cashPaymentBtn) cashPaymentBtn.disabled = isCartEmpty;
        if(transferPaymentBtn) transferPaymentBtn.disabled = isCartEmpty;
    }

    function removeFromCart(productId) {
        const itemIndex = cart.findIndex(item => item.id === productId);
        if (itemIndex > -1) {
            if (cart[itemIndex].quantity > 1) {
                cart[itemIndex].quantity--;
            } else {
                cart.splice(itemIndex, 1);
            }
        }
        updateCart();
    }
    
    // --- API CALLS ---
    
    async function startShift(initialCash) {
        const formData = new FormData();
        formData.append('initial_cash', initialCash);

        try {
            const response = await fetch('start_shift_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                showToast('Error al iniciar turno: ' + result.message, true);
            }
        } catch (error) {
            console.error('Failed to start shift:', error);
            showToast('Falla de conexión al intentar iniciar el turno.', true);
        }
    }
    
    async function processSale(paymentMethod) { // Modified to accept paymentMethod
        if (cart.length === 0) return;

        try {
            const response = await fetch('record_sale_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cart: cart, payment_method: paymentMethod }) // Pass payment_method
            });

            const result = await response.json();

            if (result.success) {
                showToast('Venta registrada con éxito!');
                cart = [];
                updateCart();
                setTimeout(() => window.location.reload(), 1000);

            } else {
                showToast('Error en la venta: ' + result.message, true);
            }
        } catch (error) {
            console.error('Failed to process sale:', error);
            showToast('Falla de conexión al registrar la venta.', true);
        }
    }

    function showToast(message, isError = false) {
    const toast = document.getElementById('toast-notification');
    if (!toast) return;

    toast.textContent = message;
    toast.style.backgroundColor = isError ? 'var(--danger-color)' : 'var(--success-color)';
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

    // Initial cart update to set correct state for buttons
    updateCart(); 
});
