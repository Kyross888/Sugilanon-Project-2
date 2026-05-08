<?php
// ============================================================
//  dashboard.php  —  Dashboard UI + KPI/chart API
//  GET              → HTML dashboard page
//  GET ?action=xxx  → JSON API response
// ============================================================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    require_once 'db.php';

    $action   = $_GET['action'];
    $branchId = $_GET['branch_id'] ?? '';

    $branchFilter = '';
    $branchParam  = [];
    if ($branchId) {
        $branchFilter = " AND branch_id = ?";
        $branchParam  = [(int)$branchId];
    }

    if ($action === 'kpis') {
        requireAuth();
        $rev = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS orders FROM transactions WHERE DATE(created_at) = CURRENT_DATE AND status = 'completed' $branchFilter");
        $rev->execute($branchParam);
        $row = $rev->fetch();
        $low  = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 10 AND is_active = TRUE")->fetchColumn();
        $out  = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0 AND is_active = TRUE")->fetchColumn();
        respond(['success' => true, 'revenue_today' => (float)$row['revenue'], 'orders_today' => (int)$row['orders'], 'low_stock' => (int)$low, 'out_of_stock' => (int)$out]);
    }

    if ($action === 'revenue_trend') {
        requireAuth();
        $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, COALESCE(SUM(total), 0) AS revenue FROM transactions WHERE created_at >= CURRENT_DATE - INTERVAL '6 days' AND status = 'completed' $branchFilter GROUP BY DATE(created_at) ORDER BY day ASC");
        $stmt->execute($branchParam);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'order_sources') {
        requireAuth();
        $stmt = $pdo->prepare("SELECT order_type AS label, COUNT(*) AS value FROM transactions WHERE DATE(created_at) = CURRENT_DATE AND status = 'completed' $branchFilter GROUP BY order_type");
        $stmt->execute($branchParam);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'top_products') {
        requireAuth();
        $stmt = $pdo->prepare("SELECT ti.product_name AS name, SUM(ti.quantity) AS total_qty, SUM(ti.line_total) AS total_revenue FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE DATE(t.created_at) = CURRENT_DATE AND t.status = 'completed' $branchFilter GROUP BY ti.product_name ORDER BY total_qty DESC LIMIT 5");
        $stmt->execute($branchParam);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    respond(['success' => false, 'error' => 'Unknown action.'], 400);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
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
    <title>POS - Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --sidebar-w: 250px;
            --sidebar-w-collapsed: 70px;
            --header-h: 64px;
            --bottom-nav-h: 64px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        /* ── Layout shell ── */
        body {
            background: var(--bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* ══════════════════════════════════
           DESKTOP SIDEBAR
        ══════════════════════════════════ */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--white);
            display: flex;
            flex-direction: column;
            padding: 20px 16px;
            border-right: 1px solid var(--border);
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
            flex-shrink: 0;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-w-collapsed);
        }

        .brand {
            margin-bottom: 28px;
            cursor: pointer;
            text-align: center;
            padding: 0 4px;
        }

        .brand img {
            max-width: 100%;
            border-radius: 8px;
            transition: 0.3s;
            display: block;
            margin: 0 auto;
        }

        .sidebar.collapsed .brand img {
            width: 38px;
        }

        .nav-links {
            list-style: none;
            flex: 1;
        }

        .nav-links li {
            margin-bottom: 6px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-light);
            padding: 11px 14px;
            display: flex;
            align-items: center;
            border-radius: 10px;
            transition: 0.2s;
            white-space: nowrap;
            overflow: hidden;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }

        .nav-links i {
            font-size: 17px;
            flex-shrink: 0;
            width: 22px;
            text-align: center;
        }

        .sidebar.collapsed .nav-links span {
            display: none;
        }

        .sidebar.collapsed .nav-links a {
            justify-content: center;
            padding: 11px;
        }

        /* ══════════════════════════════════
           MAIN CONTENT
        ══════════════════════════════════ */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }

        .header {
            background: white;
            padding: 0 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: var(--header-h);
            gap: 16px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Hamburger for mobile (shows in header on mobile) */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: var(--text-dark);
            font-size: 20px;
            line-height: 1;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px 14px 6px 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
        }

        .admin-badge .avatar {
            width: 28px;
            height: 28px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 700;
        }

        /* ── Scrollable content area ── */
        .content {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
            /* Extra bottom padding on mobile for bottom nav */
            padding-bottom: 24px;
        }

        /* ══════════════════════════════════
           KPI CARDS
        ══════════════════════════════════ */
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .card {
            background: var(--white);
            padding: 20px;
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .card h3 {
            color: var(--text-light);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card .number {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.1;
            /* Allow long numbers to wrap */
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .card .number.smaller {
            font-size: 20px;
        }

        .card-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-bottom: 12px;
        }

        /* ══════════════════════════════════
           CHARTS
        ══════════════════════════════════ */
        .analytics-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .chart-wrapper {
            position: relative;
            height: 260px;
            width: 100%;
        }

        /* ══════════════════════════════════
           TOP PRODUCTS TABLE
        ══════════════════════════════════ */
        .top-products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .top-products-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .top-products-table td:last-child {
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .product-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: var(--bg);
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            margin-right: 10px;
            flex-shrink: 0;
        }

        .product-name-cell {
            display: flex;
            align-items: center;
        }

        /* ══════════════════════════════════
           MOBILE OVERLAY & BOTTOM NAV
        ══════════════════════════════════ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 90;
            backdrop-filter: blur(2px);
        }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: var(--bottom-nav-h);
            background: var(--white);
            border-top: 1px solid var(--border);
            z-index: 80;
            padding: 0 8px;
            box-shadow: 0 -4px 16px rgba(0,0,0,0.06);
        }

        .bottom-nav-inner {
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 100%;
            max-width: 480px;
            margin: 0 auto;
        }

        .bottom-nav a {
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: var(--text-light);
            font-size: 10px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 10px;
            transition: 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .bottom-nav a.active {
            color: var(--primary);
        }

        .bottom-nav a i {
            font-size: 18px;
        }

        /* ══════════════════════════════════
           RESPONSIVE BREAKPOINTS
        ══════════════════════════════════ */

        /* ── Large screens: collapse sidebar ── */
        @media (max-width: 1200px) {
            :root { --sidebar-w: 220px; }
            .grid-cards { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── Medium screens (tablets) ── */
        @media (max-width: 900px) {
            .analytics-container {
                grid-template-columns: 1fr;
            }
            .grid-cards { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── Mobile ── */
        @media (max-width: 640px) {
            /* Hide desktop sidebar completely */
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), width 0.3s;
                width: var(--sidebar-w) !important; /* always full width when open */
                box-shadow: 4px 0 24px rgba(0,0,0,0.12);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .sidebar-overlay.visible {
                display: block;
            }

            .main-content {
                width: 100%;
            }

            /* Show hamburger + bottom nav */
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
            .bottom-nav { display: flex; }

            /* Push content up above bottom nav */
            .content {
                padding: 16px;
                padding-bottom: calc(var(--bottom-nav-h) + 16px);
            }

            .header {
                padding: 0 16px;
            }

            /* 2-col KPI grid on mobile */
            .grid-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 16px;
            }

            .card {
                padding: 16px;
            }

            .card .number {
                font-size: 20px;
            }

            .analytics-container {
                gap: 12px;
                margin-bottom: 12px;
            }

            .chart-wrapper {
                height: 220px;
            }

            .header h2 { font-size: 16px; }
        }

        /* Very small phones: stack KPI cards 1 per row */
        @media (max-width: 360px) {
            .grid-cards { grid-template-columns: 1fr; }
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
        })();
    </script>
</head>

<body>

    <!-- Sidebar overlay (mobile tap-outside to close) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

    <!-- ── Sidebar ── -->
    <nav class="sidebar" id="sidebar">
        <div class="brand" onclick="toggleSidebar()">
            <img src="lunas.jpg" alt="Luna's Logo">
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a></li>
            <li><a href="pos_terminal.php"><i class="fa-solid fa-cash-register"></i><span>New Order</span></a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i><span>Inventory</span></a></li>
            <li><a href="salesreport.php"><i class="fa-solid fa-chart-line"></i><span>Sales Report</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a></li>
            <li><a href="customer.php"><i class="fa-solid fa-users"></i><span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i><span>Settings</span></a></li>
        </ul>
    </nav>

    <!-- ── Main ── -->
    <div class="main-content">

        <header class="header">
            <div class="header-left">
                <!-- Hamburger (mobile only) -->
                <button class="menu-toggle" onclick="openMobileSidebar()" aria-label="Open menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h2>Dashboard</h2>
            </div>
            <div class="header-right">
                <div class="admin-badge">
                    <div class="avatar">A</div>
                    <span>Admin</span>
                </div>
            </div>
        </header>

        <div class="content">

            <!-- KPI Cards -->
            <div class="grid-cards">
                <div class="card" style="border-left: 4px solid #5a67d8;">
                    <div class="card-icon" style="background:#eef0fd; color:#5a67d8;">
                        <i class="fa-solid fa-peso-sign"></i>
                    </div>
                    <h3>Total Sales Today</h3>
                    <div class="number" id="kpi-revenue">₱0.00</div>
                </div>
                <div class="card" style="border-left: 4px solid #48bb78;">
                    <div class="card-icon" style="background:#f0faf4; color:#38a169;">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>
                    <h3>Orders Completed</h3>
                    <div class="number" id="kpi-orders">0</div>
                </div>
                <div class="card" style="border-left: 4px solid #ed8936;">
                    <div class="card-icon" style="background:#fff7ed; color:#ed8936;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h3>Low Stock Items</h3>
                    <div class="number" id="kpi-lowstock">0</div>
                </div>
                <div class="card" style="border-left: 4px solid #e53e3e;">
                    <div class="card-icon" style="background:#fff5f5; color:#e53e3e;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                    <h3>Out of Stock</h3>
                    <div class="number" id="kpi-outstock">0</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="analytics-container">
                <div class="card">
                    <h3>Revenue Trend (Last 7 Days)</h3>
                    <div class="chart-wrapper">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3>Order Sources</h3>
                    <div class="chart-wrapper">
                        <canvas id="sourceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="card">
                <h3>Top Selling Items Today</h3>
                <div id="recentActivity">
                    <p style="color:#718096; margin-top:12px; font-size:14px;">Loading…</p>
                </div>
            </div>

        </div><!-- /content -->
    </div><!-- /main-content -->

    <!-- ── Bottom Nav (mobile only) ── -->
    <nav class="bottom-nav">
        <div class="bottom-nav-inner">
            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-gauge"></i>
                <span>Home</span>
            </a>
            <a href="pos_terminal.php">
                <i class="fa-solid fa-cash-register"></i>
                <span>Order</span>
            </a>
            <a href="inventory.php">
                <i class="fa-solid fa-box"></i>
                <span>Stock</span>
            </a>
            <a href="salesreport.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Sales</span>
            </a>
            <a href="settings.php">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <script src="api.js"></script>
    <script>
        if (typeof parseUTC === 'undefined') {
            function parseUTC(ts) {
                if (!ts) return new Date(NaN);
                var s = ts.replace(' ', 'T').replace(/[+Z].*$/, '');
                return new Date(s + '+08:00');
            }
        }

        // ── Sidebar toggle (desktop collapse) ──
        function toggleSidebar() {
            const isMobile = window.innerWidth <= 640;
            if (isMobile) { openMobileSidebar(); return; }
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        // ── Mobile sidebar open/close ──
        function openMobileSidebar() {
            document.getElementById('sidebar').classList.add('mobile-open');
            document.getElementById('sidebarOverlay').classList.add('visible');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileSidebar() {
            document.getElementById('sidebar').classList.remove('mobile-open');
            document.getElementById('sidebarOverlay').classList.remove('visible');
            document.body.style.overflow = '';
        }

        // ── Charts ──
        let revenueChart, sourceChart;

        async function loadDashboard() {
            const user = await requireLogin();
            if (!user) return;

            // KPI cards
            const kpis = await api.dashboard.kpis(user.branch_id || '');
            if (kpis.success) {
                const rev = parseFloat(kpis.revenue_today);
                const revEl = document.getElementById('kpi-revenue');
                revEl.textContent = fmt ? fmt(rev) : '₱' + rev.toLocaleString('en-PH', { minimumFractionDigits: 2 });
                // Shrink font for large amounts
                if (rev >= 100000) revEl.classList.add('smaller');
                document.getElementById('kpi-orders').textContent    = kpis.orders_today;
                document.getElementById('kpi-lowstock').textContent  = kpis.low_stock;
                document.getElementById('kpi-outstock').textContent  = kpis.out_of_stock;
            }

            // Revenue trend
            const trend = await api.dashboard.revenueTrend(user.branch_id || '');
            const labels = trend.data ? trend.data.map(d => {
                const dt = new Date(d.day);
                return dt.toLocaleDateString('en-PH', { weekday: 'short' });
            }) : ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
            const values = trend.data ? trend.data.map(d => parseFloat(d.revenue)) : [];

            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            if (revenueChart) revenueChart.destroy();
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: values,
                        borderColor: '#5a67d8',
                        backgroundColor: 'rgba(90,103,216,0.08)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#5a67d8',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f0f0f0' },
                            ticks: {
                                callback: v => v >= 1000 ? '₱' + (v/1000).toFixed(0) + 'k' : '₱' + v,
                                maxTicksLimit: 5,
                            }
                        },
                        x: { grid: { display: false } }
                    },
                }
            });

            // Order sources doughnut
            const sources = await api.dashboard.orderSources(user.branch_id || '');
            const srcLabels = sources.data ? sources.data.map(d => d.label) : ['Dine-in', 'Take-out', 'Coupon'];
            const srcValues = sources.data ? sources.data.map(d => parseInt(d.value)) : [0, 0, 0];

            const sourceCtx = document.getElementById('sourceChart').getContext('2d');
            if (sourceChart) sourceChart.destroy();
            sourceChart = new Chart(sourceCtx, {
                type: 'doughnut',
                data: {
                    labels: srcLabels,
                    datasets: [{
                        data: srcValues,
                        backgroundColor: ['#5a67d8','#48bb78','#ed8936'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 11, padding: 14, font: { size: 12 } }
                        }
                    },
                    cutout: '70%',
                }
            });

            // Top products
            const top = await api.dashboard.topProducts(user.branch_id || '');
            const activityEl = document.getElementById('recentActivity');
            if (top.success && top.data.length) {
                activityEl.innerHTML = `
                    <table class="top-products-table">
                        <tbody>
                            ${top.data.map((p, i) => `
                                <tr>
                                    <td>
                                        <div class="product-name-cell">
                                            <span class="product-rank">${i + 1}</span>
                                            ${p.name}
                                        </div>
                                    </td>
                                    <td>${p.total_qty} sold · ${fmt ? fmt(p.total_revenue) : '₱' + parseFloat(p.total_revenue).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                                </tr>`).join('')}
                        </tbody>
                    </table>`;
            } else {
                activityEl.innerHTML = '<p style="color:#718096; margin-top:12px; font-size:14px;">No sales data for today yet.</p>';
            }
        }

        loadDashboard();
    </script>

    <script src="pwa.js"></script>
</body>
</html>
