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
    <title>POS - Analytics</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* CSS Variables: Centralizes color themes so changing a color updates the whole site instantly */
        
         :root {
            --primary: #5a67d8;
            --primary-hover: #434190;
            --bg: #f4f6f9;
            --white: #ffffff;
            --text-dark: #2d3748;
            --text-light: #718096;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #e53e3e;
            --border: #e2e8f0;
        }
        /* CSS Reset: Removes default browser margins/padding for consistent styling across browsers */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        /* Main Body Layout: Uses Flexbox to place the sidebar and main content side-by-side */
        
        body {
            background: var(--bg);
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            /* Prevents the whole page from scrolling; only the content area will scroll */
            color: var(--text-dark);
        }
        /* --- SIDEBAR STYLES --- */
        
        .sidebar {
            width: 250px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            padding: 20px;
            border-right: 1px solid var(--border);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            /* Smooth animation when collapsing */
            z-index: 100;
            flex-shrink: 0;
            /* Prevents the sidebar from squishing when screen size reduces */
        }
        /* Applied via JS to shrink the sidebar */
        
        .sidebar.collapsed {
            width: 80px;
        }
        /* Brand/Logo Area */
        
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
        /* Navigation Links */
        
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
            /* Prevents text from wrapping when sidebar collapses */
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
        /* Hides the text labels when the sidebar is collapsed */
        
        .sidebar.collapsed .nav-links span {
            display: none;
        }
        /* --- MAIN CONTENT AREA --- */
        
        .main {
            flex-grow: 1;
            /* Takes up all remaining space next to the sidebar */
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
        }
        /* Top Navigation Header */
        
        .header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: 70px;
        }
        /* The scrollable area containing the dashboard widgets */
        
        .content {
            padding: 25px;
            overflow-y: auto;
            flex-grow: 1;
        }
        /* --- DASHBOARD WIDGETS --- */
        /* Stats Cards: Uses CSS Grid to automatically flow cards onto the next row on smaller screens */
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
            border: 1px solid var(--border);
        }
        
        .stat-card small {
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
        }
        
        .stat-card h2 {
            margin: 5px 0;
            font-size: 24px;
        }
        
        .trend {
            font-size: 12px;
            font-weight: bold;
        }
        /* Charts: Grid layout prioritizing the main line chart (1.8fr) over the doughnut chart (1.2fr) */
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-box {
            background: white;
            height: 350px;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .chart-box h4 {
            margin-bottom: 15px;
            color: var(--text-light);
            font-size: 14px;
            display: flex;
            justify-content: space-between;
        }
        /* Table Area */
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            overflow-x: auto;
            /* Allows table to scroll horizontally on small screens */
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-light);
            border-bottom: 1px solid #edf2f7;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--primary);
        }
        /* Status Pills (In Stock, Low Stock) */
        
        .pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .pill-in {
            background: #f0fff4;
            color: var(--success);
        }
        
        .pill-low {
            background: #fffaf0;
            color: var(--warning);
        }
        /* --- MODAL STYLES --- */
        
        .modal-overlay {
            display: none;
            /* Hidden by default, toggled via JS */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-body {
            background: white;
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            border-radius: 12px;
            padding: 30px;
            overflow-y: auto;
            position: relative;
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        /* --- RESPONSIVE MEDIA QUERIES --- */
        /* Adjustments for Tablets */
        
        @media (max-width: 1024px) {
            .chart-grid {
                grid-template-columns: 1fr;
                /* Stacks charts vertically */
            }
        }
        /* Adjustments for Mobile Phones */
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
                /* Stacks sidebar and main content */
            }
            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                /* Horizontal navigation on mobile */
                padding: 10px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            .brand {
                display: none;
                /* Hides logo to save space */
            }
            .nav-links {
                display: flex;
                width: 100%;
                overflow-x: auto;
                /* Allows swiping through nav links */
                gap: 5px;
            }
            .nav-links li {
                margin-bottom: 0;
            }
            .nav-links a span {
                display: none;
                /* Shows icons only on mobile */
            }
            .header {
                flex-direction: column;
                height: auto;
                padding: 10px;
                gap: 10px;
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
    <div class="modal-overlay" id="allItemsModal">
        <div class="modal-body">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3>All Inventory Items</h3>
            <div style="overflow-x: auto;">
                <table id="fullItemsTable"></table>
            </div>
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
            <li><a href="analytics.php" class="active"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="customer.php"><i class="fa-solid fa-users"></i> <span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
        </ul>
    </nav>

    <div class="main">
        <header class="header">
            <h2 style="margin:0;">Analytics Overview</h2>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">

                <div style="display: flex; align-items: center; gap: 5px; background: #f8fafc; padding: 5px 10px; border-radius: 6px; border: 1px solid #ddd;">
                    <small style="color: var(--text-light); font-weight: bold;">History:</small>
                    <input type="date" id="historyDate" onchange="filterByDate()" style="border: none; background: transparent; outline: none; cursor: pointer; font-size: 13px;">
                </div>

                <select id="timeFilter" onchange="updateAnalytics()" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd; outline: none; cursor:pointer;">
                    <option value="today">Today</option>
                    <option value="7days" selected>Last 7 Days</option>
                    <option value="month">This Month</option>
                </select>

                <button onclick="downloadCSV()" style="background: var(--primary); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                    <i class="fa-solid fa-download"></i> Export
                </button>
            </div>
        </header>

        <div class="content">
            <div class="stats-row">
                <div class="stat-card">
                    <small>Revenue</small>
                    <h2 id="stat-sales">₱0.00</h2>
                    <span class="trend" style="color: var(--success)"><i class="fa-solid fa-arrow-up"></i> 12.5%</span>
                </div>
                <div class="stat-card">
                    <small>Total Orders</small>
                    <h2 id="stat-orders">0</h2>
                    <span class="trend" style="color: var(--text-light)">Avg. 45/hr</span>
                </div>
                <div class="stat-card">
                    <small>Avg. Order Value</small>
                    <h2 id="stat-avg-order">₱0.00</h2>
                    <span class="trend" id="stat-avg-order-label" style="color: var(--primary)">per order</span>
                </div>
                <div class="stat-card">
                    <small>Peak Hour</small>
                    <h2 id="stat-peak-hour">--:-- --</h2>
                    <span class="trend" id="stat-peak-traffic" style="color: var(--warning)"><i class="fa-solid fa-fire"></i> No data yet</span>
                </div>
            </div>

            <div class="chart-grid">
                <div class="chart-box">
                    <h4 id="chart-title">Revenue Trend <span><i class="fa-solid fa-ellipsis-vertical"></i></span></h4>
                    <div style="flex-grow: 1; min-height: 0;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <h4>Sales by Category <span><i class="fa-solid fa-ellipsis-vertical"></i></span></h4>
                    <div style="flex-grow: 1; min-height: 0;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 id="tableHeading" style="margin: 0;">Top Performing Items</h3>
                    <button onclick="showAllItems()" style="background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 12px;">View All Items</button>
                </div>
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Units Sold</th>
                            <th>Popularity</th>
                            <th>Stock Status</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:20px;color:#718096;">Loading data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        let revenueChart, categoryChart;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function showAllItems() {
            document.getElementById('fullItemsTable').innerHTML = document.getElementById('itemsTable').innerHTML;
            document.getElementById('allItemsModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('allItemsModal').style.display = 'none';
        }

        // ── All 7 real categories matching the DB ──────────────
        const CATEGORIES = [{
            key: 'Breakfast',
            label: 'Breakfast',
            color: '#5a67d8'
        }, {
            key: 'Merienda',
            label: 'Merienda',
            color: '#4fd1c5'
        }, {
            key: 'Burgers And Sandwiches',
            label: 'Burgers & Sand.',
            color: '#f6ad55'
        }, {
            key: 'Rice Meal',
            label: 'Rice Meal',
            color: '#fc8181'
        }, {
            key: 'Native',
            label: 'Native',
            color: '#68d391'
        }, {
            key: 'Dessert',
            label: 'Dessert',
            color: '#f687b3'
        }, {
            key: 'Drinks',
            label: 'Drinks',
            color: '#76e4f7'
        }, ];

        document.addEventListener('DOMContentLoaded', async() => {
            await requireLogin();

            // Revenue Line Chart
            const revCtx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(revCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: [],
                        borderColor: '#5a67d8',
                        tension: 0.4,
                        fill: true,
                        backgroundColor: 'rgba(90, 103, 216, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#5a67d8',
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
                    }
                }
            });

            // Category Doughnut Chart — all 7 real categories
            const catCtx = document.getElementById('categoryChart').getContext('2d');
            categoryChart = new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: CATEGORIES.map(c => c.label),
                    datasets: [{
                        data: CATEGORIES.map(() => 0),
                        backgroundColor: CATEGORIES.map(c => c.color),
                        borderWidth: 0,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 8,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            await updateAnalytics();
        });

        // ── Today / 7 Days / This Month dropdown ──────────────
        async function updateAnalytics() {
            const range = document.getElementById('timeFilter').value;
            document.getElementById('tableHeading').innerText = 'Top Performing Items';
            document.getElementById('historyDate').value = '';

            const today = new Date();
            const toDate = today.toISOString().split('T')[0];
            let fromDate = toDate;
            if (range === '7days') {
                const d = new Date(today);
                d.setDate(d.getDate() - 6);
                fromDate = d.toISOString().split('T')[0];
            } else if (range === 'month') {
                const d = new Date(today);
                d.setDate(1);
                fromDate = d.toISOString().split('T')[0];
            }
            await loadAnalyticsData(fromDate, toDate);
        }

        // ── Date picker ───────────────────────────────────────
        async function filterByDate() {
            const selectedDate = document.getElementById('historyDate').value;
            if (!selectedDate) return;
            document.getElementById('tableHeading').innerText = `History for ${selectedDate}`;
            await loadAnalyticsData(selectedDate, selectedDate);
        }

        // ── Core: fetch everything from real API ──────────────
        async function loadAnalyticsData(fromDate, toDate) {
            // ── KPI cards ──
            const report = await api.salesReport.get({
                date_from: fromDate,
                date_to: toDate
            });
            if (report.success) {
                const totalRevenue = parseFloat(report.summary.total_revenue) || 0;
                const totalOrders  = parseInt(report.summary.total_orders)   || 0;

                document.getElementById('stat-sales').innerText  = fmt(totalRevenue);
                document.getElementById('stat-orders').innerText = totalOrders;

                // ── Avg. Order Value = total revenue ÷ total orders ──
                const avgOrderValue = totalOrders > 0 ? totalRevenue / totalOrders : 0;
                document.getElementById('stat-avg-order').innerText = fmt(avgOrderValue);
                document.getElementById('stat-avg-order-label').innerText =
                    totalOrders > 0 ? `÷ ${totalOrders} orders` : 'no orders yet';
            }

            // ── Revenue trend line chart ──
            const trend = await api.dashboard.revenueTrend();
            if (trend.success && trend.data.length) {
                revenueChart.data.labels = trend.data.map(d => {
                    const dt = new Date(d.day);
                    return dt.toLocaleDateString('en-PH', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric'
                    });
                });
                revenueChart.data.datasets[0].data = trend.data.map(d => parseFloat(d.revenue));
                revenueChart.update();
            }

            // ── Category doughnut — count qty sold per category ──
            const top = await api.dashboard.topProducts();
            const tbody = document.querySelector('#itemsTable tbody');
            if (top.success && top.data.length) {
                const maxQty = Math.max(...top.data.map(p => parseInt(p.total_qty)));
                tbody.innerHTML = top.data.map(p => {
                    const pct = Math.round((p.total_qty / maxQty) * 100);
                    return `<tr>
                        <td class="item-name">${p.name}</td>
                        <td>${p.total_qty}</td>
                        <td>
                            <div style="width:100px;height:6px;background:#edf2f7;border-radius:3px;">
                                <div style="width:${pct}%;height:100%;background:var(--primary);border-radius:3px;"></div>
                            </div>
                        </td>
                        <td><span class="pill pill-in">● In Stock</span></td>
                        <td>${fmt(p.total_revenue)}</td>
                    </tr>`;
                }).join('');

                // Build category totals using products list lookup (case-insensitive)
                const products = await api.products.list();
                const catTotals = {};
                CATEGORIES.forEach(c => catTotals[c.key] = 0);
                if (products.success) {
                    const nameToCategory = {};
                    products.data.forEach(p => {
                        nameToCategory[p.name.trim().toLowerCase()] = p.category;
                    });
                    top.data.forEach(p => {
                        const cat = nameToCategory[p.name.trim().toLowerCase()];
                        if (cat && catTotals[cat] !== undefined) {
                            catTotals[cat] += parseInt(p.total_qty);
                        }
                    });
                }
                const totalsArr = CATEGORIES.map(c => catTotals[c.key]);
                const hasData = totalsArr.some(v => v > 0);
                categoryChart.data.datasets[0].data = hasData ? totalsArr : CATEGORIES.map(() => 1);
                categoryChart.data.datasets[0].backgroundColor = hasData
                    ? CATEGORIES.map(c => c.color)
                    : CATEGORIES.map(() => '#e2e8f0');
                categoryChart.update();
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:#718096;">No sales data for this period.</td></tr>';
                // Show empty grey donut when no data
                categoryChart.data.datasets[0].data = CATEGORIES.map(() => 1);
                categoryChart.data.datasets[0].backgroundColor = CATEGORIES.map(() => '#e2e8f0');
                categoryChart.update();
            }

            // ── Peak Hour — calculate from today's transactions ──
            if (report.success && report.transactions && report.transactions.length) {
                const hourCounts = {};
                report.transactions.forEach(t => {
                    const hrStr = new Date(t.created_at + ' UTC').toLocaleString('en-US', {
                        timeZone: 'Asia/Manila', hour: 'numeric', hour12: false
                    });
                    const hr = parseInt(hrStr);
                    hourCounts[hr] = (hourCounts[hr] || 0) + 1;
                });
                const peakEntry = Object.entries(hourCounts).sort((a, b) => b[1] - a[1])[0];
                if (peakEntry) {
                    const hr24 = parseInt(peakEntry[0]);
                    const ampm = hr24 >= 12 ? 'PM' : 'AM';
                    const hr12 = hr24 % 12 === 0 ? 12 : hr24 % 12;
                    document.getElementById('stat-peak-hour').innerText = `${hr12}:00 ${ampm}`;
                    document.getElementById('stat-peak-traffic').innerHTML =
                        `<i class="fa-solid fa-fire"></i> ${peakEntry[1]} order${peakEntry[1] !== 1 ? 's' : ''} that hour`;
                }
            } else {
                document.getElementById('stat-peak-hour').innerText = 'No orders';
                document.getElementById('stat-peak-traffic').innerHTML =
                    `<i class="fa-solid fa-clock"></i> No data today`;
            }
        }

        // ── CSV Export ────────────────────────────────────────
        function downloadCSV() {
            let csv = "Item Name,Units Sold,Stock Status,Total Revenue\n";
            document.querySelectorAll("#itemsTable tbody tr").forEach(row => {
                const cols = row.querySelectorAll("td");
                if (cols.length >= 5) {
                    csv += `"${cols[0].innerText}",${cols[1].innerText},"${cols[3].innerText.replace('● ','')}","${cols[4].innerText}"\n`;
                }
            });
            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'analytics.csv';
            a.click();
        }
    </script>

    <!-- PWA Registration -->
    <script src="js/pwa.js"></script>
</body>

</html>
