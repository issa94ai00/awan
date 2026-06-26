/**
 * Customer Authentication & Subscription
 */
(function () {
    var TOKEN_KEY = 'customerAuthToken';
    var INFO_KEY = 'customerInfo';

    function getToken() {
        try { return localStorage.getItem(TOKEN_KEY); } catch (e) { return null; }
    }
    window.customerGetToken = getToken;

    function setToken(token) {
        try { localStorage.setItem(TOKEN_KEY, token); } catch (e) {}
    }

    function removeToken() {
        try { localStorage.removeItem(TOKEN_KEY); } catch (e) {}
    }

    function getCustomerInfo() {
        try {
            var s = localStorage.getItem(INFO_KEY);
            return s ? JSON.parse(s) : null;
        } catch (e) { return null; }
    }

    function setCustomerInfo(info) {
        try { localStorage.setItem(INFO_KEY, JSON.stringify(info)); } catch (e) {}
    }

    function removeCustomerInfo() {
        try { localStorage.removeItem(INFO_KEY); } catch (e) {}
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    // Profile dropdown toggle
    document.addEventListener('DOMContentLoaded', function () {
        var trigger = document.getElementById('profileTrigger');
        var menu = document.getElementById('profileMenu');
        var loggedOut = document.getElementById('profileLoggedOut');
        var loggedIn = document.getElementById('profileLoggedIn');
        var greeting = document.getElementById('profileGreeting');

        if (trigger && menu) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('show');
                updateProfileUI();
            });

            document.addEventListener('click', function () {
                menu.classList.remove('show');
            });
        }

        updateProfileUI();

        // Auto-restore customer info from token if not already in localStorage
        var token = getToken();
        var info = getCustomerInfo();
        if (token && !info) {
            fetchCustomerUser(token);
        }
    });

    function updateProfileUI() {
        var token = getToken();
        var info = getCustomerInfo();
        var loggedOut = document.getElementById('profileLoggedOut');
        var loggedIn = document.getElementById('profileLoggedIn');
        var greeting = document.getElementById('profileGreeting');

        if (!loggedOut || !loggedIn) return;

        if (token && info) {
            loggedOut.style.display = 'none';
            loggedIn.style.display = 'block';
            if (greeting) greeting.textContent = 'مرحباً، ' + info.name;
        } else {
            loggedOut.style.display = 'block';
            loggedIn.style.display = 'none';
        }
    }

    function fetchCustomerUser(token) {
        fetch('/api/v1/customer/auth/user', {
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token,
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result.success && result.data) {
                setCustomerInfo(result.data);
                updateProfileUI();
            } else {
                removeToken();
                removeCustomerInfo();
                updateProfileUI();
            }
        })
        .catch(function () {
            // Ignore
        });
    }

    // Modal functions
    window.openLoginModal = function () {
        document.getElementById('loginModal').style.display = 'flex';
        document.getElementById('loginError').style.display = 'none';
        document.getElementById('loginForm').reset();
    };

    window.openRegisterModal = function () {
        document.getElementById('registerModal').style.display = 'flex';
        document.getElementById('registerError').style.display = 'none';
        document.getElementById('registerForm').reset();
    };

    window.openSubscribeModal = function () {
        document.getElementById('subscribeModal').style.display = 'flex';
        document.getElementById('subscribeError').style.display = 'none';
        document.getElementById('subscribeForm').reset();
    };

    window.closeModal = function (el) {
        el.style.display = 'none';
    };

    // Customer Login
    window.customerLogin = function (e) {
        e.preventDefault();
        var btn = document.getElementById('loginBtn');
        var errorEl = document.getElementById('loginError');
        var phone = document.getElementById('loginPhone').value.trim();
        var password = document.getElementById('loginPassword').value;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري تسجيل الدخول...';
        errorEl.style.display = 'none';

        fetch('/api/v1/customer/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ phone: phone, password: password }),
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result.success) {
                setToken(result.data.token);
                setCustomerInfo(result.data.customer);
                closeModal(document.getElementById('loginModal'));
                updateProfileUI();
                showSuccess('تم تسجيل الدخول بنجاح');
            } else {
                errorEl.textContent = result.message || 'فشل تسجيل الدخول';
                errorEl.style.display = 'block';
            }
        })
        .catch(function () {
            errorEl.textContent = 'حدث خطأ في الاتصال بالخادم';
            errorEl.style.display = 'block';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> تسجيل دخول';
        });

        return false;
    };

    // Customer Register
    window.customerRegister = function (e) {
        e.preventDefault();
        var btn = document.getElementById('registerBtn');
        var errorEl = document.getElementById('registerError');
        var name = document.getElementById('regName').value.trim();
        var phone = document.getElementById('regPhone').value.trim();
        var email = document.getElementById('regEmail').value.trim();
        var password = document.getElementById('regPassword').value;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري إنشاء الحساب...';
        errorEl.style.display = 'none';

        fetch('/api/v1/customer/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({ name: name, phone: phone, email: email || undefined, password: password }),
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result.success) {
                setToken(result.data.token);
                setCustomerInfo(result.data.customer);
                closeModal(document.getElementById('registerModal'));
                updateProfileUI();
                showSuccess('تم إنشاء الحساب بنجاح');
            } else {
                var msg = result.message || 'فشل إنشاء الحساب';
                if (result.errors) {
                    var errs = Object.values(result.errors).flat();
                    msg = errs.join('<br>');
                }
                errorEl.innerHTML = msg;
                errorEl.style.display = 'block';
            }
        })
        .catch(function () {
            errorEl.textContent = 'حدث خطأ في الاتصال بالخادم';
            errorEl.style.display = 'block';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-plus"></i> إنشاء حساب';
        });

        return false;
    };

    // Customer Logout
    window.customerLogout = function () {
        var token = getToken();
        if (token) {
            fetch('/api/v1/customer/auth/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token,
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            }).catch(function () {});
        }
        removeToken();
        removeCustomerInfo();
        updateProfileUI();
        showSuccess('تم تسجيل الخروج بنجاح');
    };

    // Subscribe (with optional account creation)
    window.customerSubscribe = function (e) {
        e.preventDefault();
        var btn = document.getElementById('subscribeBtn');
        var errorEl = document.getElementById('subscribeError');
        var name = document.getElementById('subName').value.trim();
        var phone = document.getElementById('subPhone').value.trim();
        var email = document.getElementById('subEmail').value.trim();
        var address = document.getElementById('subAddress').value.trim();
        var password = document.getElementById('subPassword').value;

        if (!name) {
            errorEl.textContent = 'يرجى إدخال الاسم';
            errorEl.style.display = 'block';
            return false;
        }
        if (!phone) {
            errorEl.textContent = 'يرجى إدخال رقم الهاتف';
            errorEl.style.display = 'block';
            return false;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الاشتراك...';
        errorEl.style.display = 'none';

        var body = { name: name, phone: phone };
        if (email) body.email = email;
        if (address) body.address = address;
        if (password) body.password = password;

        fetch('/api/v1/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result.success) {
                closeModal(document.getElementById('subscribeModal'));

                // If password was set, auto-login
                if (password && result.data && result.data.token) {
                    setToken(result.data.token);
                    setCustomerInfo(result.data.customer);
                    updateProfileUI();
                }

                showSuccess(result.message || 'تم الاشتراك بنجاح');
            } else {
                errorEl.textContent = result.message || 'فشل الاشتراك';
                errorEl.style.display = 'block';
            }
        })
        .catch(function () {
            errorEl.textContent = 'حدث خطأ في الاتصال بالخادم';
            errorEl.style.display = 'block';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> اشتراك';
        });

        return false;
    };

    // Success notification
    function showSuccess(msg) {
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed; top:80px; left:50%; transform:translateX(-50%); background:var(--mobile-primary); color:white; padding:14px 28px; border-radius:12px; font-size:0.95rem; z-index:10000; box-shadow:0 4px 20px rgba(0,0,0,0.2); text-align:center; max-width:90%; opacity:0; transition:opacity 0.3s;';
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(function () { div.style.opacity = '1'; }, 50);
        setTimeout(function () {
            div.style.opacity = '0';
            setTimeout(function () { div.remove(); }, 300);
        }, 2500);
    }
})();
