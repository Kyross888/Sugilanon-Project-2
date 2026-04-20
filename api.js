// ============================================================
//  js/api.js  —  Shared API helper for all POS pages
// ============================================================

const API_BASE = '';

// ── Fetch with timeout ────────────────────────────────────────
async function fetchWithTimeout(url, opts = {}, timeoutMs = 10000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const res = await fetch(url, { ...opts, signal: controller.signal });
        clearTimeout(timer);
        return res;
    } catch (err) {
        clearTimeout(timer);
        if (err.name === 'AbortError') {
            throw new Error('Request timed out. The server is taking too long to respond.');
        }
        throw err;
    }
}

const api = {
    // Generic fetch wrapper
    async request(endpoint, action, method = 'GET', body = null) {
        const url = `${endpoint}.php?action=${action}`;
        const opts = {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetchWithTimeout(url, opts);
        const data = await res.json();
        return data;
    },

    // ── AUTH ──────────────────────────────────────────────────
    auth: {
        login: (email, password, role) =>
            api.request('auth', 'login', 'POST', { email, password, role }),
        register: (payload) =>
            api.request('auth', 'register', 'POST', payload),
        logout: () => api.request('auth', 'logout', 'POST'),
        me: () => api.request('auth', 'me'),
    },

    // ── PRODUCTS ─────────────────────────────────────────────
    products: {
        list: (params = {}) => {
            const qs = new URLSearchParams({ action: 'list', ...params }).toString();
            return fetchWithTimeout(`products.php?${qs}`, { credentials: 'same-origin' }).then(r => r.json());
        },
        create: (formData) => {
            if (formData instanceof FormData) {
                return fetchWithTimeout(`products.php?action=create`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                }).then(r => r.json());
            }
            return api.request('products', 'create', 'POST', formData);
        },
        update: (id, data) => {
            if (data instanceof FormData) {
                return fetchWithTimeout(`products.php?action=update&id=${id}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data,
                }).then(r => r.json());
            }
            return fetchWithTimeout(`products.php?action=update&id=${id}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            }).then(r => r.json());
        },
        delete: (id) =>
            fetchWithTimeout(`products.php?action=delete&id=${id}`, {
                method: 'POST',
                credentials: 'same-origin',
            }).then(r => r.json()),
        adjustStock: (id, delta) =>
            fetchWithTimeout(`products.php?action=adjust_stock&id=${id}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ delta }),
            }).then(r => r.json()),
    },

    // ── ORDERS ───────────────────────────────────────────────
    orders: {
        place: (payload) => api.request('orders', 'place', 'POST', payload),
        list: (params = {}) => {
            const qs = new URLSearchParams({ action: 'list', ...params }).toString();
            return fetchWithTimeout(`orders.php?${qs}`, { credentials: 'same-origin' }).then(r => r.json());
        },
        get: (id) =>
            fetchWithTimeout(`orders.php?action=get&id=${id}`, { credentials: 'same-origin' }).then(r => r.json()),
        void: (id) =>
            fetchWithTimeout(`orders.php?action=void&id=${id}`, {
                method: 'POST',
                credentials: 'same-origin',
            }).then(r => r.json()),
    },

    // ── DASHBOARD ─────────────────────────────────────────────
    dashboard: {
        kpis: (branchId = '') =>
            fetchWithTimeout(`dashboard.php?action=kpis${branchId ? '&branch_id=' + branchId : ''}`, { credentials: 'same-origin' }).then(r => r.json()),
        revenueTrend: (branchId = '') =>
            fetchWithTimeout(`dashboard.php?action=revenue_trend${branchId ? '&branch_id=' + branchId : ''}`, { credentials: 'same-origin' }).then(r => r.json()),
        orderSources: (branchId = '') =>
            fetchWithTimeout(`dashboard.php?action=order_sources${branchId ? '&branch_id=' + branchId : ''}`, { credentials: 'same-origin' }).then(r => r.json()),
        topProducts: (branchId = '') =>
            fetchWithTimeout(`dashboard.php?action=top_products${branchId ? '&branch_id=' + branchId : ''}`, { credentials: 'same-origin' }).then(r => r.json()),
    },

    // ── SALES REPORT ─────────────────────────────────────────
    salesReport: {
        get: (params = {}) => {
            const qs = new URLSearchParams(params).toString();
            return fetchWithTimeout(`salesreport.php?${qs}`, { credentials: 'same-origin' }).then(r => r.json());
        },
    },

    // ── CUSTOMERS ────────────────────────────────────────────
    customers: {
        list: (search = '') =>
            fetchWithTimeout(`customers.php?action=list${search ? '&search=' + encodeURIComponent(search) : ''}`, { credentials: 'same-origin' }).then(r => r.json()),
        get: (id) =>
            fetchWithTimeout(`customers.php?action=get&id=${id}`, { credentials: 'same-origin' }).then(r => r.json()),
        create: (data) => api.request('customers', 'create', 'POST', data),
        update: (id, data) =>
            fetchWithTimeout(`customers.php?action=update&id=${id}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            }).then(r => r.json()),
        delete: (id) =>
            fetchWithTimeout(`customers.php?action=delete&id=${id}`, {
                method: 'POST',
                credentials: 'same-origin',
            }).then(r => r.json()),
    },
};

// ── Auth Guard ────────────────────────────────────────────────
async function requireLogin() {
    try {
        const res = await api.auth.me();
        if (!res || !res.success) {
            window.location.href = 'login.php';
            return null;
        }
        const el = document.getElementById('userGreeting');
        if (el) el.textContent = res.user.name;
        return res.user;
    } catch (err) {
        // Timeout or network error — still redirect to login
        window.location.href = 'login.php';
        return null;
    }
}

// Convenience: format Philippine Peso
function fmt(n) {
    return '₱' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
