<?php
// ============================================================
//  salesreport.php  —  Sales Report UI + API
//  GET              → HTML report page
//  GET ?date_from=  → JSON API response
// ============================================================

if (isset($_GET['date_from']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    require_once 'db.php';
    requireAuth();

    $date_from = $_GET['date_from'] ?? (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $date_to   = $_GET['date_to']   ?? $date_from;
    $branch_id = $_GET['branch_id'] ?? '';

    $branchFilter = '';
    $params       = [$date_from, $date_to];
    if ($branch_id) { $branchFilter = " AND t.branch_id = ?"; $params[] = (int)$branch_id; }

    $kpi = $pdo->prepare("SELECT COALESCE(SUM(t.total), 0) AS total_revenue, COUNT(t.id) AS total_orders, COALESCE(SUM(t.discount + t.coupon_discount), 0) AS total_discounts FROM transactions t WHERE DATE(t.created_at AT TIME ZONE 'Asia/Manila') BETWEEN ? AND ? AND t.status = 'completed' $branchFilter");
    $kpi->execute($params);
    $summary = $kpi->fetch();

    $rows = $pdo->prepare("SELECT t.id, t.reference_no, t.order_type, t.payment_method, t.subtotal, t.discount, t.coupon_discount, t.total, t.created_at, u.first_name, u.last_name, (SELECT COUNT(*) FROM transaction_items WHERE transaction_id = t.id) AS item_count FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE DATE(t.created_at AT TIME ZONE 'Asia/Manila') BETWEEN ? AND ? AND t.status = 'completed' $branchFilter ORDER BY t.created_at DESC");
    $rows->execute($params);

    $best = $pdo->prepare("SELECT ti.product_name, SUM(ti.quantity) AS qty, p.image_path, p.icon FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id LEFT JOIN products p ON ti.product_id = p.id WHERE DATE(t.created_at AT TIME ZONE 'Asia/Manila') BETWEEN ? AND ? AND t.status = 'completed' $branchFilter GROUP BY ti.product_name, p.image_path, p.icon ORDER BY qty DESC LIMIT 1");
    $best->execute($params);
    $bestSeller = $best->fetch();

    respond(['success' => true, 'summary' => $summary, 'best_seller' => $bestSeller, 'transactions' => $rows->fetchAll()]);
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
    <title>POS - Sales Report | Luna's Ilonggo Legacy</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
         :root {
            --primary: #5a67d8;
            --bg: #f4f6f9;
            --dark: #2d3748;
            --white: #ffffff;
            --border: #e2e8f0;
            --success: #48bb78;
            --info: #4299e1;
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
        }
        
        .sidebar {
            width: 250px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            padding: 20px;
            border-right: 1px solid var(--border);
            transition: 0.3s;
            flex-shrink: 0;
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .nav-links span {
            display: none;
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
            color: #718096;
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
        
        .main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .header {
            background: var(--white);
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            min-height: 70px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-picker {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--dark);
        }
        
        .content {
            padding: 25px;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .icon-box {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        
        .rev-icon {
            background: linear-gradient(135deg, #ebf4ff, #c3d9ff);
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.18);
        }
        
        .ord-icon {
            background: linear-gradient(135deg, #f0fff4, #b2f5c8);
            color: var(--success);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.18);
        }
        
        .dish-card {
            flex-direction: row;
            align-items: center;
            gap: 15px;
            padding: 16px 20px;
        }
        
        .dish-img-wrap {
            width: 68px;
            height: 68px;
            border-radius: 14px;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #fde9c4;
            box-shadow: 0 4px 14px rgba(237, 137, 54, 0.22);
        }
        
        .dish-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .dish-label {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ed8936;
            background: #fffaf0;
            border: 1px solid #fde9c4;
            border-radius: 20px;
            padding: 2px 8px;
            margin-bottom: 4px;
        }
        
        .summary-card h3 {
            font-size: 13px;
            color: #718096;
            margin-bottom: 3px;
        }
        
        .summary-card p {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .kpi-sub {
            font-size: 11px;
            color: #a0aec0;
            margin-top: 2px;
            font-weight: 500;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: var(--dark);
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-cash {
            background: #e6fffa;
            color: #2c7a7b;
        }
        
        .badge-gcash {
            background: #ebf8ff;
            color: #2b6cb0;
        }
        
        .badge-card {
            background: #faf5ff;
            color: #6b46c1;
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
<img src="lunas.jpg" alt="Luna's Logo">          </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a></li>
            <li><a href="pos_terminal.php"><i class="fa-solid fa-cash-register"></i> <span>New Order</span></a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> <span>Inventory</span></a></li>
            <li><a href="salesreport.php" class="active"><i class="fa-solid fa-chart-line"></i> <span>Sales Report</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="customer.php"><i class="fa-solid fa-users"></i> <span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
        </ul>
    </nav>

    <div class="main">
        <header class="header">
            <h2>Sales Report</h2>
            <div class="header-actions">
                <input type="date" class="date-picker" value="2026-02-22">
            </div>
        </header>

        <div class="content">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="icon-box rev-icon"><i class="fa-solid fa-philippine-peso-sign"></i></div>
                    <div>
                        <h3>Total Revenue</h3>
                        <p id="kpi-revenue">₱0.00</p>
                        <div class="kpi-sub"><i class="fa-solid fa-arrow-trend-up" style="color:#48bb78; margin-right:4px;"></i>Today's earnings</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-box ord-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                    <div>
                        <h3>Total Orders</h3>
                        <p id="kpi-orders">0</p>
                        <div class="kpi-sub"><i class="fa-solid fa-circle-check" style="color:#48bb78; margin-right:4px;"></i>All completed</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-box" style="background:linear-gradient(135deg,#fff5f5,#fed7d7);color:#e53e3e;box-shadow:0 4px 12px rgba(229,62,62,.18);">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                    <div>
                        <h3>Total Discounts</h3>
                        <p id="kpi-discount">₱0.00</p>
                        <div class="kpi-sub">Discounts & coupons applied</div>
                    </div>
                </div>
                <div class="summary-card dish-card">
                    <div class="dish-img-wrap" id="best-dish-img-wrap" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#fffaf0,#feebc8);">
                        <span id="best-dish-emoji" style="font-size:32px;"></span>
                        <i class="fa-solid fa-utensils" id="best-dish-icon-fallback" style="font-size:28px;color:#ed8936;"></i>
                        <img id="best-dish-img" src="" alt="Best Dish" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:12px;">
                    </div>
                    <div>
                        <span class="dish-label"><i class="fa-solid fa-fire-flame-curved" style="margin-right:3px;"></i>Top Selling</span>
                        <h3>Best Dish Today</h3>
                        <p style="font-size:17px;" id="best-dish">—</p>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time/Date</th>
                            <th>Order ID</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody">
                        <tr>
                            <td>12:45 PM | Feb 22</td>
                            <td style="font-weight: 600;">#4022</td>
                            <td>Dine-in</td>
                            <td>3 Items</td>
                            <td style="font-weight: 700;">₱470.40</td>
                            <td><span class="status-badge badge-cash">CASH</span></td>
                        </tr>
                        <tr>
                            <td>11:20 AM | Feb 22</td>
                            <td style="font-weight: 600;">#4021</td>
                            <td>Take-out</td>
                            <td>1 Item</td>
                            <td style="font-weight: 700;">₱120.00</td>
                            <td><span class="status-badge badge-gcash">CASH</span></td>
                        </tr>
                        <tr>
                            <td>09:15 AM | Feb 22</td>
                            <td style="font-weight: 600;">#4020</td>
                            <td>Dine-in</td>
                            <td>5 Items</td>
                            <td style="font-weight: 700;">₱850.00</td>
                            <td><span class="status-badge badge-card">CASH</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        async function loadReport(selectedDate) {
            const tbody = document.getElementById('salesTableBody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#718096;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</td></tr>';

            let res;
            try {
                res = await api.salesReport.get({
                    date_from: selectedDate,
                    date_to: selectedDate
                });
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#e53e3e;">Failed to load report. Please try again.</td></tr>';
                return;
            }

            if (!res || !res.success) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#e53e3e;">Error loading report data.</td></tr>';
                return;
            }

            const {
                summary,
                best_seller,
                transactions
            } = res;

            // KPI cards
            document.getElementById('kpi-revenue').textContent = fmt(summary.total_revenue);
            document.getElementById('kpi-orders').textContent = summary.total_orders;
            document.getElementById('kpi-discount').textContent = fmt(summary.total_discounts);
            // Best dish name
            document.getElementById('best-dish').textContent = best_seller ? best_seller.product_name : 'N/A';

            // Best dish image / emoji / icon fallback
            const imgEl      = document.getElementById('best-dish-img');
            const emojiEl    = document.getElementById('best-dish-emoji');
            const faIconEl   = document.getElementById('best-dish-icon-fallback');

            // Reset all
            imgEl.style.display   = 'none';
            emojiEl.textContent   = '';
            faIconEl.style.display = 'inline-block';

            if (best_seller) {
                if (best_seller.image_path) {
                    // Has a product image (base64 or URL)
                    imgEl.src           = best_seller.image_path;
                    imgEl.style.display = 'block';
                    emojiEl.textContent = '';
                    faIconEl.style.display = 'none';
                } else if (best_seller.icon) {
                    // Has an emoji icon
                    emojiEl.textContent    = best_seller.icon;
                    faIconEl.style.display = 'none';
                }
                // else: keep the default FA utensils icon
            }

            // Table
            tbody.innerHTML = '';
            if (!transactions.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#718096;">No transactions for this date.</td></tr>';
                return;
            }
            transactions.forEach(t => {
                const dt = new Date(t.created_at + ' UTC');
                const timeStr = dt.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const dateStr = dt.toLocaleDateString('en-PH', {
                    month: 'short',
                    day: 'numeric'
                });
                const payMethod = (t.payment_method || '').toLowerCase();
                const badgeClass = payMethod === 'cash' ? 'badge-cash' : payMethod === 'gcash' ? 'badge-gcash' : 'badge-card';
                tbody.innerHTML += `
                    <tr>
                        <td>${timeStr} | ${dateStr}</td>
                        <td style="font-weight:600;">${t.reference_no}</td>
                        <td>${t.order_type}</td>
                        <td>${t.item_count} Item${t.item_count !== 1 ? 's' : ''}</td>
                        <td style="font-weight:700;">${fmt(t.total)}</td>
                        <td><span class="status-badge ${badgeClass}">${(t.payment_method || '').toUpperCase()}</span></td>
                    </tr>`;
            });
        }

        async function init() {
            const user = await requireLogin();
            if (!user) return;
            const datePicker = document.querySelector('.date-picker');
            const now = new Date();
            const today = now.toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' }); // 'en-CA' gives YYYY-MM-DD format
            datePicker.value = today;
            await loadReport(today);
            datePicker.addEventListener('change', () => loadReport(datePicker.value));
        }

        init();
    </script>

    <!-- PWA Registration -->
    <script src="js/pwa.js"></script>
</body>

</html>
