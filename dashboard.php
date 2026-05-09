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
    <title>POS - Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* --- THEME VARIABLES --- */
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

        /* --- MAIN CONTENT --- */
        .main-content {
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

        .content {
            padding: 30px;
            overflow-y: auto;
            flex-grow: 1;
        }

        /* --- CARDS --- */
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .card h3 {
            color: var(--text-light);
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .card .number {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-dark);
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* --- CHARTS --- */
        .analytics-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* --- RESPONSIVE BREAKPOINTS --- */

        /* Tablets: stack charts vertically */
        @media (max-width: 992px) {
            .analytics-container {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile: top nav bar instead of sidebar */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
                overflow: auto;
            }

            .sidebar {
                width: 100% !important;
                height: auto;
                flex-direction: row;
                padding: 10px;
                border-right: none;
                border-bottom: 1px solid var(--border);
                overflow-x: auto;
            }

            .brand {
                display: none;
            }

            .nav-links {
                display: flex;
                width: 100%;
                gap: 5px;
            }

            .nav-links li {
                margin-bottom: 0;
                flex-shrink: 0;
            }

            .nav-links a span {
                display: none;
            }

            .nav-links i {
                min-width: unset;
            }

            .main-content {
                overflow: visible;
            }

            .content {
                overflow-y: visible;
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                height: auto;
                padding: 15px;
            }

            .grid-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 16px;
            }

            .card .number {
                font-size: 22px;
            }
        }

        /* Small phones: single column */
        @media (max-width: 400px) {
            .grid-cards {
                grid-template-columns: 1fr;
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
    <nav class="sidebar" id="sidebar">
        <div class="brand" onclick="toggleSidebar()">
<img src="lunas.jpg" alt="Luna's Logo">        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a></li>
            <li><a href="pos_terminal.php"><i class="fa-solid fa-cash-register"></i> <span>New Order</span></a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> <span>Inventory</span></a></li>
            <li><a href="salesreport.php"><i class="fa-solid fa-chart-line"></i> <span>Sales Report</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="customer.php"><i class="fa-solid fa-users"></i> <span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
        </ul>
    </nav>

    <div class="main-content">
        <header class="header">
            <h2 style="margin:0;">Dashboard</h2>
            <div style="color: var(--text-light); font-weight: 600;">Admin User</div>
        </header>

        <div class="content">
            <div class="grid-cards">
                <div class="card" style="border-left: 5px solid #5a67d8;">
                    <h3>Total Sales Today</h3>
                    <div class="number" id="kpi-revenue">₱0.00</div>
                </div>
                <div class="card" style="border-left: 5px solid #48bb78;">
                    <h3>Orders Completed</h3>
                    <div class="number" id="kpi-orders">0</div>
                </div>
                <div class="card" style="border-left: 5px solid #ed8936;">
                    <h3>Low Stock Items</h3>
                    <div class="number" id="kpi-lowstock">0</div>
                </div>
                <div class="card" style="border-left: 5px solid #e53e3e;">
                    <h3>OUT OF STOCK</h3>
                    <div class="number" id="kpi-outstock">0</div>
                </div>
            </div>

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

            <div class="card" style="margin-top: 20px;">
                <h3>Top Selling Items Today</h3>
                <div id="recentActivity">
                    <p style="color:#718096;margin-top:15px;">Loading…</p>
                </div>
            </div>
        </div>
    </div>

    <script src="api.js"></script>
    <script>
        // Inline fallback: parse DB timestamp as PH time (UTC+8)
        // Works even if api.js path is wrong or fails to load
        if (typeof parseUTC === 'undefined') {
            function parseUTC(ts) {
                if (!ts) return new Date(NaN);
                var s = ts.replace(' ', 'T').replace(/[+Z].*$/, '');
                return new Date(s + '+08:00');
            }
        }
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        // ── Charts ────────────────────────────────────────────
        let revenueChart, sourceChart;

        async function loadDashboard() {
            // Auth guard
            const user = await requireLogin();
            if (!user) return;

            // KPI cards
            const kpis = await api.dashboard.kpis(user.branch_id || '');
            if (kpis.success) {
                document.getElementById('kpi-revenue').textContent = fmt(kpis.revenue_today);
                document.getElementById('kpi-orders').textContent = kpis.orders_today;
                document.getElementById('kpi-lowstock').textContent = kpis.low_stock;
                document.getElementById('kpi-outstock').textContent = kpis.out_of_stock;
            }

            // Revenue trend
            const trend = await api.dashboard.revenueTrend(user.branch_id || '');
            const labels = trend.data ? trend.data.map(d => {
                const dt = new Date(d.day);
                return dt.toLocaleDateString('en-PH', {
                    weekday: 'short'
                });
            }) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
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
                        backgroundColor: 'rgba(90,103,216,0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f0f0f0'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
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
                        backgroundColor: ['#5a67d8', '#48bb78', '#ed8936'],
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
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        }
                    },
                    cutout: '70%',
                }
            });

            // Recent activity - top products
            const top = await api.dashboard.topProducts(user.branch_id || '');
            const activityEl = document.getElementById('recentActivity');
            if (top.success && top.data.length) {
                activityEl.innerHTML = top.data.map(p =>
                    `<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                        <span>${p.name}</span>
                        <span style="font-weight:600;">${p.total_qty} sold · ${fmt(p.total_revenue)}</span>
                    </div>`
                ).join('');
            } else {
                activityEl.innerHTML = '<p style="color:#718096;margin-top:15px;">No sales data for today yet.</p>';
            }
        }

        loadDashboard();
    </script>

    <!-- PWA Registration -->
    <script src="pwa.js"></script>
</body>
</html>
