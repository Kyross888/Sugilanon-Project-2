<?php
// ============================================================
//  customers.php  —  Customer Management UI + API
//  GET              → HTML customers page
//  GET ?action=xxx  → JSON API response
// ============================================================
if (!isset($_GET['action'])) {
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- PWA Manifest & Theme -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Luna's POS">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icon-96x96.png">
    <link rel="icon" href="favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Customers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
         :root {
            --primary: #5a67d8;
            --primary-hover: #434190;
            --bg: #f4f6f9;
            --white: #ffffff;
            --text-dark: #2d3748;
            --text-light: #718096;
            --danger: #e53e3e;
            --success: #38a169;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background: var(--bg);
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            color: var(--text-dark);
        }
        /* --- SIDEBAR --- */
        
        .sidebar {
            width: 250px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            padding: 20px;
            border-right: 1px solid var(--border);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
            flex-shrink: 0;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
       .brand {
    margin-bottom: 30px;
    cursor: pointer;
    text-align: center;
}
.brand img {
    max-width: 100%;
    border-radius: 8px;
    transition: 0.3s;
}
.sidebar.collapsed .brand img {
    width: 40px;
}
        
        .nav-links {
            list-style: none;
        }
        
        .nav-links li {
            margin-bottom: 10px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-light);
            padding: 12px 15px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            transition: 0.2s;
            white-space: nowrap;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-links i {
            min-width: 30px;
            font-size: 18px;
        }
        
        .sidebar.collapsed .nav-links span {
            display: none;
        }
        /* --- MAIN --- */
        
        .main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        
        .header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: 70px;
            gap: 20px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-input,
        .date-filter {
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            transition: 0.2s;
            font-size: 14px;
        }
        
        .search-input {
            width: 220px;
        }
        
        .search-input:focus,
        .date-filter:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }
        
        .btn-print {
            background: #f0fff4;
            color: var(--success);
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-print:hover {
            background: #c6f6d5;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th,
        td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        
        th {
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
            font-weight: 700;
        }
        
        tr:hover td {
            background: #fafbff;
        }
        
        .item-chip {
            background: #edf2f7;
            color: #4a5568;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            margin-right: 4px;
            margin-bottom: 3px;
            display: inline-block;
            font-weight: 600;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .badge-dinein {
            background: #ebf8ff;
            color: #2b6cb0;
        }
        
        .badge-takeout {
            background: #f0fff4;
            color: #276749;
        }
        
        .badge-coupon {
            background: #faf5ff;
            color: #6b46c1;
        }
        
        .btn-print {
            background: #f0fff4;
            color: var(--success);
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-print:hover {
            background: #c6f6d5;
        }
        
        .content {
            padding: 30px;
            overflow-y: auto;
            flex-grow: 1;
        }
        /* --- TABLE --- */
        
        #printable-receipt {
            display: none;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            #printable-receipt,
            #printable-receipt * {
                visibility: visible;
            }
            #printable-receipt {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                font-family: 'Courier New', monospace;
            }
        }
        
        .receipt-card {
            width: 80mm;
            margin: auto;
            text-align: center;
        }
        
        .receipt-header {
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 14px;
        }
        /* --- RESPONSIVE --- */
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                padding: 10px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            .brand {
                display: none;
            }
            .nav-links {
                display: flex;
                width: 100%;
                overflow-x: auto;
                gap: 5px;
            }
            .nav-links li {
                margin-bottom: 0;
            }
            .nav-links a span {
                display: none;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
                height: auto;
                padding: 15px;
            }
            .search-input {
                width: 100%;
            }
        }
    </style>
    <script>
        (function() {
            var s = localStorage.getItem("pos_theme");
            if (s) {
                try {
                    var t = JSON.parse(s);
                    document.documentElement.style.setProperty("--primary", t.primary);
                    document.documentElement.style.setProperty("--primary-hover", t.hover);
                } catch (e) {}
            }
        })()
    </script>
</head>

<body>

    <!-- Print Template -->
    <div id="printable-receipt">
        <div class="receipt-card">
            <div class="receipt-header">
                <h2 style="margin:0;">LUNA'S</h2>
                <p style="font-size:12px;margin:5px 0;">Ilonggo Legacy</p>
                <p style="font-size:11px;"><span id="print-ref"></span></p>
                <p style="font-size:11px;"><span id="print-date"></span></p>
            </div>
            <div class="receipt-row"><strong>Order Type:</strong><span id="print-type"></span></div>
            <div style="border-bottom:1px solid #eee;margin:10px 0;"></div>
            <div id="print-items-list" style="text-align:left;min-height:40px;"></div>
            <div style="border-bottom:1px dashed #000;margin:10px 0;"></div>
            <div class="receipt-row"><span>Subtotal</span><span id="print-subtotal"></span></div>
            <div class="receipt-row"><span>Discount</span><span id="print-discount"></span></div>
            <div class="receipt-row" style="font-size:16px;font-weight:bold;">
                <span>TOTAL</span><span id="print-total"></span>
            </div>
            <p style="margin-top:20px;font-size:11px;">Thank you for dining with us!</p>
        </div>
    </div>

    <nav class="sidebar" id="sidebar">
        <div class="brand" onclick="toggleSidebar()">
<img src="lunas.jpg" alt="Luna's Logo">          </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a></li>
            <li><a href="pos_terminal.php"><i class="fa-solid fa-cash-register"></i> <span>New Order</span></a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> <span>Inventory</span></a></li>
            <li><a href="salesreport.php"><i class="fa-solid fa-chart-line"></i> <span>Sales Report</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="customer.php" class="active"><i class="fa-solid fa-users"></i> <span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
        </ul>
    </nav>

    <div class="main">
        <header class="header">
            <h2 style="margin:0;">Customer Orders</h2>
            <div class="header-actions">
                <input type="date" class="date-filter" id="dateFilter" onchange="filterOrders()">
                <input type="text" class="search-input" id="searchInput" placeholder="Search ref, type, item..." onkeyup="filterOrders()">
            </div>
        </header>

        <div class="content">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Order Ref</th>
                            <th>Type</th>
                            <th>Items Ordered</th>
                            <th>Total</th>
                            <th style="text-align:right;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody">
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#718096;">Loading orders…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
// ============================================================
//  api.js — inlined
// ============================================================
const API_BASE = '';

function parseUTC(ts) {
    if (!ts) return new Date(NaN);
    var s = ts.replace(' ', 'T').replace(/[+Z].*$/, '');
    return new Date(s + '+08:00');
}

async function fetchWithTimeout(url, opts = {}, timeoutMs = 10000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const res = await fetch(url, { ...opts, signal: controller.signal });
        clearTimeout(timer);
        return res;
    } catch (err) {
        clearTimeout(timer);
        if (err.name === 'AbortError') throw new Error('Request timed out.');
        throw err;
    }
}

const api = {
    async request(endpoint, action, method = 'GET', body = null) {
        const url = `${endpoint}.php?action=${action}`;
        const opts = { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetchWithTimeout(url, opts);
        return res.json();
    },
    auth: {
        login: (email, password, role) => api.request('auth', 'login', 'POST', { email, password, role }),
        register: (payload) => api.request('auth', 'register', 'POST', payload),
        logout: () => api.request('auth', 'logout', 'POST'),
        me: () => api.request('auth', 'me'),
    },
    orders: {
        place: (payload) => api.request('orders', 'place', 'POST', payload),
        list: (params = {}) => {
            const qs = new URLSearchParams({ action: 'list', ...params }).toString();
            return fetchWithTimeout(`orders.php?${qs}`, { credentials: 'same-origin' }).then(r => r.json());
        },
        get: (id) => fetchWithTimeout(`orders.php?action=get&id=${id}`, { credentials: 'same-origin' }).then(r => r.json()),
        void: (id) => fetchWithTimeout(`orders.php?action=void&id=${id}`, { method: 'POST', credentials: 'same-origin' }).then(r => r.json()),
    },
    customers: {
        list: (search = '') => fetchWithTimeout(`customers.php?action=list${search ? '&search=' + encodeURIComponent(search) : ''}`, { credentials: 'same-origin' }).then(r => r.json()),
        get: (id) => fetchWithTimeout(`customers.php?action=get&id=${id}`, { credentials: 'same-origin' }).then(r => r.json()),
        create: (data) => api.request('customers', 'create', 'POST', data),
        update: (id, data) => fetchWithTimeout(`customers.php?action=update&id=${id}`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }).then(r => r.json()),
        delete: (id) => fetchWithTimeout(`customers.php?action=delete&id=${id}`, { method: 'POST', credentials: 'same-origin' }).then(r => r.json()),
    },
};

async function requireLogin() {
    try {
        const res = await api.auth.me();
        if (!res || !res.success) { window.location.href = 'login.php'; return null; }
        const el = document.getElementById('userGreeting');
        if (el) el.textContent = res.user.name;
        return res.user;
    } catch (err) {
        window.location.href = 'login.php';
        return null;
    }
}

function fmt(n) {
    return '₱' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
    </script>
    <script>
        let allOrders = [];

        // ── Boot ──────────────────────────────────────────────
        async function init() {
            await requireLogin();
            await loadOrders();
        }

        // ── Load all transactions + their items ───────────────
        async function loadOrders() {
            const tbody = document.getElementById('customerTableBody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#718096;">Loading orders\u2026</td></tr>';

            const res = await api.orders.list({ page: 1 });
            if (!res.success) {
                tbody.innerHTML = `<tr><td colspan="6" style="color:red;padding:20px;">Failed to load orders: ${res.error}</td></tr>`;
                return;
            }

            allOrders = res.data;
            filterOrders();
        }

        // ── Filter ────────────────────────────────────────────
        function filterOrders() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const dateVal = document.getElementById('dateFilter').value;

            const filtered = allOrders.filter(o => {
                const dt = parseUTC(o.created_at);
                const dateStr = dt.toISOString().split('T')[0];
                const itemNames = (o.items || []).map(i => (i.product_name || '').toLowerCase()).join(' ');

                const matchDate = !dateVal || dateStr === dateVal;
                const matchQ = !query ||
                    o.reference_no.toLowerCase().includes(query) ||
                    (o.order_type || '').toLowerCase().includes(query) ||
                    itemNames.includes(query);

                return matchDate && matchQ;
            });

            renderTable(filtered);
        }

        // ── Render ────────────────────────────────────────────
        function renderTable(orders) {
            const tbody = document.getElementById('customerTableBody');

            if (!orders.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#718096;">No orders found. Orders placed in the POS Terminal will appear here automatically.</td></tr>';
                return;
            }

            tbody.innerHTML = orders.map(o => {
                const dt = parseUTC(o.created_at);
                const date = dt.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                const time = dt.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const typeClass = o.order_type === 'Dine-in' ? 'badge-dinein' :
                    o.order_type === 'Take-out' ? 'badge-takeout' :
                    'badge-coupon';

                const total = parseFloat(o.total || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2
                });

                // Build item chips from actual order items
                const items = o.items || [];
                const itemsHtml = items.length ?
                    items.map(i =>
                        `<span class="item-chip">${i.product_name} <strong style="color:var(--primary);">x${i.quantity}</strong></span>`
                    ).join('') :
                    '<span style="color:#ccc;font-size:12px;">—</span>';

                return `
                    <tr>
                        <td style="color:var(--text-light);font-size:13px;">
                            <div style="font-weight:600;color:var(--text-dark);">${date}</div>
                            <div style="font-size:11px;">${time}</div>
                        </td>
                        <td style="font-weight:700;color:var(--primary);">${o.reference_no}</td>
                        <td><span class="badge ${typeClass}">${o.order_type}</span></td>
                        <td style="max-width:300px;">${itemsHtml}</td>
                        <td style="font-weight:700;">₱${total}</td>
                        <td style="text-align:right;">
                            <button class="btn-print" onclick="printReceipt(${o.id})" title="Print Receipt">
                                <i class="fa-solid fa-print"></i>
                            </button>
                        </td>
                    </tr>`;
            }).join('');
        }

        // ── Print Receipt ─────────────────────────────────────
        function printReceipt(id) {
            const o = allOrders.find(x => x.id === id);
            if (!o) return;

            const dt = parseUTC(o.created_at);
            document.getElementById('print-ref').innerText = o.reference_no;
            document.getElementById('print-date').innerText = dt.toLocaleString('en-PH');
            document.getElementById('print-type').innerText = o.order_type;
            document.getElementById('print-subtotal').innerText = '₱' + parseFloat(o.subtotal || 0).toFixed(2);
            document.getElementById('print-discount').innerText = '- ₱' + (parseFloat(o.discount || 0) + parseFloat(o.coupon_discount || 0)).toFixed(2);
            document.getElementById('print-total').innerText = '₱' + parseFloat(o.total || 0).toFixed(2);

            const items = o.items || [];
            document.getElementById('print-items-list').innerHTML = items.length ?
                items.map(i => `
                    <div class="receipt-row">
                        <span>${i.product_name} x${i.quantity}</span>
                        <span>₱${parseFloat(i.line_total).toFixed(2)}</span>
                    </div>`).join('') :
                '<div style="color:#999;font-size:12px;">No item details.</div>';

            window.print();
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        init();
    </script>

    <!-- PWA Registration -->
    <script src="pwa.js"></script>
</body>

</html><?php
    exit; // Stop here for page requests — nothing below should run
} // end if no action

// ============================================================
//  api/customers.php  —  CRUD for customer records
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    requireAuth();
    $search = $_GET['search'] ?? '';
    $sql    = "SELECT c.*, b.name AS branch_name FROM customers c LEFT JOIN branches b ON c.branch_id = b.id WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }
    $sql .= " ORDER BY c.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($action === 'get') {
    requireAuth();
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) respond(['success' => false, 'error' => 'Customer not found.'], 404);

    // Last 10 transactions
    $txns = $pdo->prepare(
        "SELECT reference_no, total, order_type, created_at FROM transactions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10"
    );
    $txns->execute([$id]);
    $c['recent_transactions'] = $txns->fetchAll();

    respond(['success' => true, 'data' => $c]);
}

if ($action === 'create') {
    requireAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name      = trim($body['name']      ?? '');
    $email     = trim($body['email']     ?? '');
    $phone     = trim($body['phone']     ?? '');
    $branch_id = $body['branch_id']      ?? null;

    if (!$name) respond(['success' => false, 'error' => 'Name is required.'], 400);

    $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, branch_id) VALUES (?,?,?,?)");
    $stmt->execute([$name, $email, $phone, $branch_id ?: null]);
    respond(['success' => true, 'id' => $pdo->lastInsertId()]);
}

if ($action === 'update') {
    requireAuth();
    $id   = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $allowed = ['name', 'email', 'phone', 'branch_id'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (empty($sets)) respond(['success' => false, 'error' => 'Nothing to update.'], 400);

    $params[] = $id;
    $pdo->prepare("UPDATE customers SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    respond(['success' => true]);
}

if ($action === 'delete') {
    requireAuth();
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Unknown action.'], 400);
