<?php
// ============================================================
//  admin.php  —  Admin Dashboard UI + Stats API
//  GET              → HTML admin page
//  GET ?action=xxx  → JSON API response
// ============================================================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    require_once 'db.php';

    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        respond(['success' => false, 'error' => 'Admin access required.'], 403);
    }

    $action = $_GET['action'] ?? 'all_branches';

    // Validate and sanitize date input
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    if ($action === 'all_branches') {
        $stmt = $pdo->prepare("SELECT b.id, b.name, b.address, COALESCE(SUM(t.total), 0) AS sales_today, COUNT(t.id) AS orders_today FROM branches b LEFT JOIN transactions t ON t.branch_id = b.id AND DATE(t.created_at) = ? AND t.status = 'completed' GROUP BY b.id, b.name, b.address ORDER BY b.id ASC");
        $stmt->execute([$date]);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'branch') {
        $branchId = (int)($_GET['id'] ?? 0);
        if (!$branchId) respond(['success' => false, 'error' => 'Branch ID required.'], 400);
        $kpi = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS sales_today, COUNT(*) AS orders_today FROM transactions WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'completed'");
        $kpi->execute([$branchId, $date]);
        $kpiRow = $kpi->fetch();
        $txns = $pdo->prepare("SELECT t.id, t.reference_no, t.order_type, t.payment_method, t.total, t.created_at, GROUP_CONCAT(CONCAT(ti.quantity, 'x ', ti.product_name) ORDER BY ti.id SEPARATOR ', ') AS items_summary FROM transactions t LEFT JOIN transaction_items ti ON ti.transaction_id = t.id WHERE t.branch_id = ? AND DATE(t.created_at) = ? AND t.status = 'completed' GROUP BY t.id ORDER BY t.created_at DESC LIMIT 50");
        $txns->execute([$branchId, $date]);
        respond(['success' => true, 'kpi' => $kpiRow, 'transactions' => $txns->fetchAll()]);
    }

    if ($action === 'totals') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS total_revenue, COUNT(*) AS total_orders FROM transactions WHERE DATE(created_at) = ? AND status = 'completed'");
        $stmt->execute([$date]);
        respond(['success' => true, 'data' => $stmt->fetch()]);
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
    <title>Admin - Complete Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>

    <style>
        .branch-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #branchModal,
        #historyModal,
        #stocksModal {
            transition: opacity 0.3s ease-in-out;
        }

        @keyframes pulse-red {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }

        .animate-pulse-red {
            animation: pulse-red 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .page-view {
            display: none !important;
            animation: fadeIn 0.4s ease-in-out;
        }

        .page-view.active {
            display: block !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .nav-link { transition: all 0.2s; }

        .nav-link.is-active {
            background-color: rgb(238 242 255);
            color: rgb(79 70 229);
            font-weight: 700;
        }

        .dark .nav-link.is-active {
            background-color: rgba(99, 102, 241, 0.1);
            color: rgb(129 140 248);
        }

        .nav-link.is-inactive {
            color: rgb(148 163 184);
            font-weight: 600;
        }

        .dark .nav-link.is-inactive { color: rgb(100 116 139); }

        .nav-link.is-inactive:hover {
            background-color: rgb(248 250 252);
            color: rgb(71 85 105);
        }

        .dark .nav-link.is-inactive:hover {
            background-color: rgb(51 65 85);
            color: rgb(203 213 225);
        }

        /* Logo shimmer fallback */
        .logo-fallback {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            color: white;
            font-size: 1.1rem;
            letter-spacing: -0.5px;
        }

        /* Date filter styles */
        .date-btn {
            padding: 5px 13px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .date-btn.active-date {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }
        .date-btn:not(.active-date) {
            background: transparent;
            color: #64748b;
            border-color: #e2e8f0;
        }
        .date-btn:not(.active-date):hover {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .dark .date-btn:not(.active-date) {
            color: #94a3b8;
            border-color: rgb(51 65 85);
        }
        .dark .date-btn:not(.active-date):hover {
            border-color: #6366f1;
            color: #818cf8;
        }
        input[type="date"].date-input {
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 5px 13px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        input[type="date"].date-input:focus {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .dark input[type="date"].date-input {
            border-color: rgb(51 65 85);
            color: #94a3b8;
            color-scheme: dark;
        }
    </style>
</head>

<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-sans mb-20 md:mb-0 selection:bg-indigo-100 selection:text-indigo-900 transition-colors duration-300">

    <!-- ── Branch Details Modal ── -->
    <div id="branchModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden p-4">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="bg-white border border-slate-200 w-full max-w-md rounded-[2rem] p-8 relative z-10 shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-black text-slate-800 uppercase tracking-tight">Report Details</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors bg-slate-100 hover:bg-slate-200 p-2 rounded-full h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-slate-500 text-[10px] font-bold uppercase mb-1 tracking-wider">Total Sales</p>
                    <p id="modalSales" class="text-xl font-black text-emerald-600">₱0.00</p>
                </div>
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-slate-500 text-[10px] font-bold uppercase mb-1 tracking-wider">Status</p>
                    <p id="modalStatus" class="text-xl font-black text-indigo-600">Active</p>
                </div>
            </div>
            <div class="mt-6 p-5 bg-indigo-50 border border-indigo-100 rounded-2xl">
                <p class="text-[10px] text-indigo-500 font-bold uppercase mb-2 tracking-widest flex items-center gap-2">
                    <i class="fas fa-map-marker-alt"></i> Location
                </p>
                <p id="modalLocation" class="text-sm text-slate-700 font-medium leading-relaxed">Loading...</p>
            </div>
            <button onclick="closeModal()" class="w-full mt-8 py-4 bg-slate-800 hover:bg-slate-900 text-white rounded-2xl font-bold uppercase text-xs tracking-widest transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                Close Report
            </button>
        </div>
    </div>

    <!-- ── History Modal ── -->
    <div id="historyModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden p-4 md:p-10">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeHistoryModal()"></div>
        <div class="bg-white border border-slate-200 w-full max-w-4xl rounded-[2rem] p-6 md:p-8 relative z-10 shadow-2xl flex flex-col max-h-[90vh]">
            <div class="flex justify-between items-center mb-6 shrink-0 border-b border-slate-100 pb-4">
                <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tight">
                    <i class="fas fa-history text-indigo-500 mr-2"></i> Complete Purchase History
                </h2>
                <button onclick="closeHistoryModal()" class="text-slate-400 hover:text-slate-600 transition-colors bg-slate-100 hover:bg-slate-200 p-2 rounded-full h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <div class="overflow-y-auto pr-2 flex-1 rounded-xl border border-slate-100">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase tracking-widest sticky top-0 border-b border-slate-100 z-10 shadow-sm">
                        <tr>
                            <th class="px-6 py-4 font-bold">Date &amp; Time</th>
                            <th class="px-6 py-4 font-bold">Items Bought</th>
                            <th class="px-6 py-4 font-bold text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="history-modal-body" class="text-sm divide-y divide-slate-100">
                        <tr><td colspan="3" class="text-center py-8 text-slate-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-100 flex justify-end shrink-0">
                <button id="downloadBtn" onclick="exportSalesData()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-md flex items-center gap-2">
                    <i class="fas fa-download"></i> Download Full Record
                </button>
            </div>
        </div>
    </div>

    <!-- ── Stocks Modal ── -->
    <div id="stocksModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden p-4 md:p-10">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeStocksModal()"></div>
        <div class="bg-white border border-slate-200 w-full max-w-4xl rounded-[2rem] p-6 md:p-8 relative z-10 shadow-2xl flex flex-col max-h-[90vh]">
            <div class="flex justify-between items-center mb-6 shrink-0 border-b border-slate-100 pb-4">
                <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tight">
                    <i class="fas fa-boxes text-orange-500 mr-2"></i> Complete Inventory List
                </h2>
                <button onclick="closeStocksModal()" class="text-slate-400 hover:text-slate-600 transition-colors bg-slate-100 hover:bg-slate-200 p-2 rounded-full h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <div class="overflow-y-auto pr-2 flex-1 rounded-xl border border-slate-100">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-400 text-[10px] uppercase tracking-widest sticky top-0 border-b border-slate-100 z-10 shadow-sm">
                        <tr>
                            <th class="px-6 py-4 font-bold">Item Name</th>
                            <th class="px-6 py-4 font-bold">Stock Count</th>
                            <th class="px-6 py-4 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody id="stocks-modal-body" class="text-sm divide-y divide-slate-100">
                        <tr><td colspan="3" class="text-center py-8 text-slate-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Main Layout ── -->
    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- ── Sidebar / Bottom Nav ── -->
        <aside class="fixed bottom-0 w-full bg-white dark:bg-slate-800 flex flex-row justify-around py-3 border-t border-slate-200 dark:border-slate-700 z-50 md:relative md:w-24 md:h-screen md:flex-col md:items-center md:py-6 md:gap-4 md:border-r md:border-t-0 shadow-[4px_0_24px_rgba(0,0,0,0.02)] transition-colors duration-300">

            <!-- Sidebar Logo (desktop only) -->
            <div class="hidden md:flex flex-col items-center w-full px-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                <div class="w-14 h-14 rounded-2xl overflow-hidden ring-2 ring-indigo-100 dark:ring-indigo-500/20 shadow-md shadow-indigo-100 dark:shadow-indigo-900/20">
                    <img
                        src="logo.jpg"
                        alt="Luna's Logo"
                        class="w-full h-full object-cover"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                    <div class="logo-fallback w-full h-full" style="display:none;">L</div>
                </div>
            </div>

            <!-- Nav Links -->
            <nav class="flex flex-row md:flex-col gap-2 w-full px-2">
                <a onclick="switchPage('home')" id="nav-home" class="nav-link is-active cursor-pointer flex-1 md:w-full flex flex-col items-center gap-1.5 p-3 rounded-xl">
                    <i class="fas fa-th-large fa-lg"></i>
                    <span class="text-[10px] md:text-xs">Home</span>
                </a>
                <a onclick="switchPage('stats')" id="nav-stats" class="nav-link is-inactive cursor-pointer flex-1 md:w-full flex flex-col items-center gap-1.5 p-3 rounded-xl">
                    <i class="fas fa-chart-pie fa-lg"></i>
                    <span class="text-[10px] md:text-xs">Stats</span>
                </a>
                <a onclick="switchPage('admin')" id="nav-admin" class="nav-link is-inactive cursor-pointer flex-1 md:w-full flex flex-col items-center gap-1.5 p-3 rounded-xl">
                    <i class="fas fa-store fa-lg"></i>
                    <span class="text-[10px] md:text-xs">Sales</span>
                </a>
                <a onclick="switchPage('live')" id="nav-live" class="nav-link is-inactive cursor-pointer flex-1 md:w-full flex flex-col items-center gap-1.5 p-3 rounded-xl">
                    <i class="fas fa-broadcast-tower fa-lg"></i>
                    <span class="text-[10px] md:text-xs">Live</span>
                </a>
                <a onclick="switchPage('settings')" id="nav-settings" class="nav-link is-inactive cursor-pointer flex-1 md:w-full flex flex-col items-center gap-1.5 p-3 rounded-xl">
                    <i class="fas fa-cog fa-lg"></i>
                    <span class="text-[10px] md:text-xs">Settings</span>
                </a>
            </nav>
        </aside>

        <!-- ── Page Content ── -->
        <div class="flex-1 w-full relative h-screen overflow-y-auto">

            <!-- ════════════════════════════════════
                 HOME PAGE
            ════════════════════════════════════ -->
            <main id="page-home" class="page-view active w-full p-6 md:p-10 lg:p-12 max-w-7xl mx-auto">

                <!-- Page Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

                    <!-- Left: Title -->
                    <div>
                        <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white uppercase tracking-tight leading-none">Dashboard</h1>
                        <p class="text-xs text-slate-400 dark:text-slate-500 font-medium mt-0.5">Overview &amp; Analytics</p>
                    </div>

                    <!-- Right: Date Filter + Branch Selector -->
                    <div class="flex flex-wrap items-center gap-3">

                        <!-- Date Filter pill -->
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 py-2.5 shadow-sm">
                            <i class="fas fa-calendar-alt text-indigo-400 text-sm shrink-0"></i>
                            <div class="flex items-center gap-1.5">
                                <button id="btn-today" class="date-btn active-date" onclick="setDate('today')">Today</button>
                                <button id="btn-yesterday" class="date-btn" onclick="setDate('yesterday')">Yesterday</button>
                                <input type="date" id="customDate" class="date-input" onchange="setDate('custom')" title="Pick any date">
                            </div>
                        </div>

                        <!-- Branch Selector -->
                        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 py-3 shadow-sm w-full sm:w-auto">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center shrink-0">
                                <i class="fas fa-store text-indigo-500 dark:text-indigo-400 text-sm"></i>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest leading-none mb-1">Branch</span>
                                <select id="branchSelect" onchange="switchBranch()" class="text-sm font-bold text-slate-800 dark:text-slate-200 bg-transparent border-none outline-none cursor-pointer dark:bg-slate-800">
                                    <option value="festive">Festive Mall</option>
                                    <option value="sm_central">SM Central Market</option>
                                    <option value="gen_luna" selected>General Luna</option>
                                    <option value="jaro">Jaro</option>
                                    <option value="molo">Molo</option>
                                    <option value="la_paz">La Paz</option>
                                    <option value="calumpang">Calumpang</option>
                                    <option value="tagbak">Tagbak</option>
                                </select>
                            </div>
                            <i class="fas fa-chevron-down text-slate-400 text-xs shrink-0"></i>
                        </div>
                    </div>
                </div>

                <!-- All-Branches Combined Banner -->
                <div class="w-full bg-gradient-to-r from-slate-900 to-indigo-900 rounded-[2rem] p-6 md:p-8 mb-6 shadow-xl flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                            <p id="banner-date-label" class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">All Branches Combined — Today</p>
                        </div>
                        <h2 id="all-branches-total" class="text-4xl md:text-5xl font-black text-white tracking-tight mb-1">₱ 0.00</h2>
                        <p class="text-slate-400 text-sm font-medium">Total revenue across all <span id="all-branches-count" class="text-indigo-400 font-bold">8</span> branches</p>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                        <div class="bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-center">
                            <p class="text-slate-400 text-[9px] font-bold uppercase tracking-widest mb-1">Orders</p>
                            <p id="all-branches-orders" class="text-white font-black text-xl">0</p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-center">
                            <p class="text-slate-400 text-[9px] font-bold uppercase tracking-widest mb-1">Top Branch</p>
                            <p id="all-branches-top" class="text-emerald-400 font-black text-sm leading-tight">—</p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-center">
                            <p class="text-slate-400 text-[9px] font-bold uppercase tracking-widest mb-1">Low Stock</p>
                            <p id="all-branches-lowstock" class="text-amber-400 font-black text-xl">0</p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-2xl px-4 py-3 text-center">
                            <p class="text-slate-400 text-[9px] font-bold uppercase tracking-widest mb-1">Out of Stock</p>
                            <p id="all-branches-outstock" class="text-rose-400 font-black text-xl">0</p>
                        </div>
                    </div>
                </div>

                <!-- Per-branch revenue bars -->
                <div id="all-branches-strip" class="w-full overflow-x-auto mb-6">
                    <div id="all-branches-bars" class="flex gap-2 min-w-max pb-2"></div>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-indigo-600 p-8 rounded-[2rem] shadow-xl shadow-indigo-200 dark:shadow-indigo-900/20 flex flex-col justify-center">
                        <p class="text-indigo-200 text-[10px] font-bold uppercase tracking-widest mb-2">Total Volume — <span id="home-branch-label">General Luna</span></p>
                        <h2 id="home-total-sales" class="text-4xl md:text-5xl font-black text-white mb-4 tracking-tight">₱ 0.00</h2>
                        <div class="flex items-center gap-2 text-indigo-100 text-xs mt-auto">
                            <span id="home-growth" class="bg-white/20 px-2.5 py-1 rounded-full font-bold">—</span>
                            <span class="font-medium">vs yesterday</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col justify-center">
                        <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">Completed Orders</p>
                        <h2 id="home-orders" class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">0</h2>
                        <div class="flex items-center gap-2 text-emerald-500 dark:text-emerald-400 text-xs font-bold mt-auto bg-emerald-50 dark:bg-emerald-500/10 w-fit px-3 py-1.5 rounded-full">
                            <i class="fas fa-arrow-up"></i>
                            <span>Trending upwards</span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Panels -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm flex flex-col">
                        <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                            <h3 class="font-black text-slate-800 dark:text-white uppercase tracking-tight text-sm"><i class="fas fa-receipt text-indigo-500 mr-2"></i>Recent Purchases</h3>
                            <a href="#" onclick="openHistoryModal(event)" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 bg-indigo-50 dark:bg-indigo-500/10 px-3 py-1.5 rounded-full transition-colors">View All</a>
                        </div>
                        <div class="overflow-x-auto p-4">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400 text-center py-4">Data loaded successfully.</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm flex flex-col">
                        <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                            <h3 class="font-black text-slate-800 dark:text-white uppercase tracking-tight text-sm"><i class="fas fa-boxes text-orange-500 mr-2"></i>Available Stocks</h3>
                            <a href="#" onclick="openStocksModal(event)" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 bg-indigo-50 dark:bg-indigo-500/10 px-3 py-1.5 rounded-full transition-colors">View All Stocks</a>
                        </div>
                        <div class="overflow-x-auto p-4">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400 text-center py-4">Inventory synced.</p>
                        </div>
                    </div>
                </div>
            </main>

            <!-- ════════════════════════════════════
                 STATS PAGE
            ════════════════════════════════════ -->
            <main id="page-stats" class="page-view w-full p-6 md:p-10 lg:p-12 max-w-7xl mx-auto">
                <!-- Stats Header -->
                <div class="flex items-center gap-4 mb-8">
                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white uppercase tracking-tight">Executive Summary</h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <div class="lg:col-span-2 bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-700 shadow-sm transition-colors duration-300">
                        <h3 class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mb-6">Revenue Forecast</h3>
                        <div class="h-[300px] w-full"><canvas id="revenueChart"></canvas></div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-700 shadow-sm transition-colors duration-300">
                        <h3 class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mb-6">Sales by Category</h3>
                        <div class="h-[220px] flex items-center justify-center relative">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-8 space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="flex items-center gap-3 text-slate-600 dark:text-slate-300 font-medium">
                                    <div class="w-2.5 h-2.5 rounded-full" style="background:#4f46e5;"></div>Rice Meals
                                </span>
                                <span class="font-bold text-slate-900 dark:text-white">₱6,500</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="flex items-center gap-3 text-slate-600 dark:text-slate-300 font-medium">
                                    <div class="w-2.5 h-2.5 rounded-full" style="background:#10b981;"></div>Burgers &amp; Sandwiches
                                </span>
                                <span class="font-bold text-slate-900 dark:text-white">₱3,200</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="flex items-center gap-3 text-slate-600 dark:text-slate-300 font-medium">
                                    <div class="w-2.5 h-2.5 rounded-full" style="background:#f59e0b;"></div>Desserts
                                </span>
                                <span class="font-bold text-slate-900 dark:text-white">₱1,500</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="flex items-center gap-3 text-slate-600 dark:text-slate-300 font-medium">
                                    <div class="w-2.5 h-2.5 rounded-full" style="background:#ec4899;"></div>Drinks
                                </span>
                                <span class="font-bold text-slate-900 dark:text-white">₱1,250</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm transition-colors duration-300">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="font-black text-slate-800 dark:text-white uppercase tracking-tight text-sm"><i class="fas fa-fire text-rose-500 mr-1"></i> Top Moving Items</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-400 text-[10px] uppercase tracking-widest border-b border-slate-100 dark:border-slate-700">
                                <tr>
                                    <th class="px-8 py-5 font-bold">Product Name</th>
                                    <th class="px-8 py-5 font-bold">Status</th>
                                    <th class="px-8 py-5 font-bold">Performance</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 dark:divide-slate-700">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-8 py-5 font-bold text-slate-800 dark:text-slate-200">Arrozcaldo</td>
                                    <td class="px-8 py-5 font-semibold text-emerald-600 dark:text-emerald-400">High Demand</td>
                                    <td class="px-8 py-5">
                                        <div class="w-32 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                            <div class="w-[85%] h-full bg-indigo-500 rounded-full"></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-8 py-5 font-bold text-slate-800 dark:text-slate-200">Classic Burger</td>
                                    <td class="px-8 py-5 font-semibold text-emerald-600 dark:text-emerald-400">Steady</td>
                                    <td class="px-8 py-5">
                                        <div class="w-32 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                            <div class="w-[60%] h-full bg-emerald-500 rounded-full"></div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>

            <!-- ════════════════════════════════════
                 SALES PAGE
            ════════════════════════════════════ -->
            <main id="page-admin" class="page-view w-full p-6 md:p-10 lg:p-12 max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-10 gap-6">
                    <div>
                        <!-- Branch badge row -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span id="sales-branch-status">General Luna</span> Online
                            </div>
                        </div>
                        <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white tracking-tight">Sales Overview</h1>
                        <p class="text-slate-500 dark:text-slate-400 mt-2 font-medium">Daily transaction summary</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Date filter (Sales page) -->
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 py-2.5 shadow-sm">
                            <i class="fas fa-calendar-alt text-indigo-400 text-sm shrink-0"></i>
                            <div class="flex items-center gap-1.5">
                                <button id="sbtn-today" class="date-btn active-date" onclick="setDate('today')">Today</button>
                                <button id="sbtn-yesterday" class="date-btn" onclick="setDate('yesterday')">Yesterday</button>
                                <input type="date" id="scustomDate" class="date-input" onchange="setSalesDate()" title="Pick any date">
                            </div>
                        </div>
                        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 py-3 shadow-sm">
                            <i class="fas fa-store text-indigo-500 text-sm"></i>
                            <select id="branchSelectSales" onchange="switchBranchFromSales()" class="text-sm font-bold text-slate-800 dark:text-slate-200 bg-transparent border-none outline-none cursor-pointer dark:bg-slate-800">
                                <option value="festive">Festive Mall</option>
                                <option value="sm_central">SM Central Market</option>
                                <option value="gen_luna" selected>General Luna</option>
                                <option value="jaro">Jaro</option>
                                <option value="molo">Molo</option>
                                <option value="la_paz">La Paz</option>
                                <option value="calumpang">Calumpang</option>
                                <option value="tagbak">Tagbak</option>
                            </select>
                        </div>
                        <a href="#" onclick="switchPage('live')" class="group flex items-center gap-2 px-6 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold transition-all shadow-lg shadow-indigo-200 dark:shadow-indigo-900/20 hover:-translate-y-0.5">
                            <i class="fas fa-satellite-dish animate-pulse"></i> Open Live View
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" id="branch-cards-grid"></div>
            </main>

            <!-- ════════════════════════════════════
                 LIVE PAGE
            ════════════════════════════════════ -->
            <main id="page-live" class="page-view w-full p-6 md:p-10 lg:p-12">
                <div class="flex flex-col items-center justify-center min-h-[80vh]">
                    <div class="w-full max-w-3xl flex flex-col items-center">
                        <!-- Live header -->
                        <div class="flex flex-col items-center mb-16 text-center">
                            <div class="flex items-center gap-4 mb-3">
                                <span class="w-5 h-5 bg-rose-500 rounded-full animate-pulse-red shadow-[0_0_15px_rgba(244,63,94,0.6)]"></span>
                                <h1 class="text-5xl md:text-6xl font-black text-slate-900 dark:text-white tracking-tight uppercase">Live</h1>
                            </div>
                            <p class="text-slate-500 text-lg md:text-xl font-medium">Real-time sales feed: <span id="live-branch-label" class="font-bold text-indigo-600 dark:text-indigo-400">General Luna</span></p>
                        </div>

                        <div class="space-y-6 w-full">
                            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-6 text-center">Recent Transactions</h3>
                            <div class="bg-white dark:bg-slate-800 p-8 md:p-10 rounded-[2.5rem] border border-slate-200 dark:border-slate-700 flex flex-col md:flex-row justify-between items-center group hover:border-indigo-300 dark:hover:border-indigo-500 transition-all shadow-lg hover:shadow-2xl hover:-translate-y-1 gap-6">
                                <div class="flex flex-col md:flex-row items-center gap-6 text-center md:text-left">
                                    <div class="w-20 h-20 md:w-24 md:h-24 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center text-indigo-500 dark:text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition-colors shrink-0">
                                        <i class="fas fa-shopping-cart fa-2x md:fa-3x"></i>
                                    </div>
                                    <div>
                                        <p id="live-branch-name" class="font-black text-slate-800 dark:text-white text-xl md:text-2xl mb-1">General Luna</p>
                                        <p class="text-sm md:text-base text-slate-400 font-medium">
                                            <span class="text-indigo-500 dark:text-indigo-400 font-bold">Just now</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="bg-emerald-50 dark:bg-emerald-500/10 px-6 py-4 rounded-2xl border border-emerald-100 dark:border-emerald-500/20">
                                    <p class="font-black text-emerald-600 dark:text-emerald-400 text-3xl md:text-4xl">+ ₱1,250.00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- ════════════════════════════════════
                 SETTINGS PAGE
            ════════════════════════════════════ -->
            <main id="page-settings" class="page-view w-full p-6 md:p-10 lg:p-12 max-w-7xl mx-auto">
                <!-- Settings header -->
                <div class="flex items-center gap-4 mb-8">
                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white uppercase tracking-tight transition-colors duration-300">System Settings</h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 p-8 shadow-sm transition-colors duration-300">
                        <h2 class="text-slate-900 dark:text-white font-black uppercase tracking-tight mb-6">Store Profile</h2>
                        <div class="space-y-4">
                            <div class="p-4 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-600">
                                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Store Name</p>
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">General Luna</p>
                            </div>
                            <div class="p-4 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-600">
                                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Address</p>
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">General Luna St., Iloilo City</p>
                            </div>
                            <div class="p-4 bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-100 dark:border-slate-600">
                                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">System Status</p>
                                <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Online &amp; Active</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700 overflow-hidden h-fit shadow-sm transition-colors duration-300">
                        <div class="p-8 flex justify-between items-center hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer group" onclick="toggleDarkMode()">
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white">Dark Mode</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">Manage visual preferences</p>
                            </div>
                            <div class="w-14 h-7 bg-slate-300 dark:bg-indigo-600 rounded-full relative shadow-inner transition-colors duration-300">
                                <div id="darkModeKnob" class="absolute left-1 top-1 w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-300 ease-in-out"></div>
                            </div>
                        </div>

                        <div class="hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors group cursor-pointer" onclick="logoutSession()">
                            <a class="p-8 flex items-center gap-3 w-full text-rose-600 dark:text-rose-400 font-bold text-sm uppercase tracking-wider">
                                <i class="fas fa-sign-out-alt group-hover:-translate-x-1 transition-transform"></i> Logout Session
                            </a>
                        </div>
                    </div>
                </div>
            </main>

        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        // ── Branch Maps ────────────────────────────────────────
        const branchIdMap = {
            festive: 1, sm_central: 2, gen_luna: 3, jaro: 4,
            molo: 5, la_paz: 6, calumpang: 7, tagbak: 8,
        };

        const branchInfo = {
            festive:    { name: 'Festive Mall',       location: 'Festive Walk Mall, Iloilo City',          status: 'Active' },
            sm_central: { name: 'SM Central Market',  location: 'SM City Iloilo, Central Market Area',     status: 'Active' },
            gen_luna:   { name: 'General Luna',       location: 'General Luna St., Iloilo City',           status: 'Active' },
            jaro:       { name: 'Jaro',               location: 'Jaro District, Iloilo City',              status: 'Active' },
            molo:       { name: 'Molo',               location: 'Molo District, Iloilo City',              status: 'Active' },
            la_paz:     { name: 'La Paz',             location: 'La Paz District, Iloilo City',            status: 'Active' },
            calumpang:  { name: 'Calumpang',          location: 'Calumpang, Iloilo City',                  status: 'Active' },
            tagbak:     { name: 'Tagbak',             location: 'Tagbak Terminal Area, Jaro, Iloilo City', status: 'Active' },
        };

        let currentBranch = 'gen_luna';
        let allBranchData = {};

        // ── Date helpers ───────────────────────────────────────
        function todayStr() {
            return new Date().toISOString().slice(0, 10);
        }
        function yesterdayStr() {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            return d.toISOString().slice(0, 10);
        }

        let selectedDate = todayStr();

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('customDate').value  = todayStr();
            document.getElementById('scustomDate').value = todayStr();
        });

        function setDate(mode) {
            if (mode === 'today') {
                selectedDate = todayStr();
            } else if (mode === 'yesterday') {
                selectedDate = yesterdayStr();
            } else if (mode === 'custom') {
                const v = document.getElementById('customDate').value;
                if (v) selectedDate = v;
            }
            syncDateUI();
            loadAllBranchesFromDB();
        }

        function setSalesDate() {
            const v = document.getElementById('scustomDate').value;
            if (v) {
                selectedDate = v;
                document.getElementById('customDate').value = v;
            }
            syncDateUI();
            loadAllBranchesFromDB();
        }

        function syncDateUI() {
            const isToday     = selectedDate === todayStr();
            const isYesterday = selectedDate === yesterdayStr();

            ['btn-today',  'btn-yesterday' ].forEach(id => document.getElementById(id).classList.remove('active-date'));
            ['sbtn-today', 'sbtn-yesterday'].forEach(id => document.getElementById(id).classList.remove('active-date'));

            if (isToday)          { document.getElementById('btn-today').classList.add('active-date');     document.getElementById('sbtn-today').classList.add('active-date'); }
            else if (isYesterday) { document.getElementById('btn-yesterday').classList.add('active-date'); document.getElementById('sbtn-yesterday').classList.add('active-date'); }

            document.getElementById('customDate').value  = selectedDate;
            document.getElementById('scustomDate').value = selectedDate;

            // Update banner label
            let label = 'All Branches Combined';
            if (isToday)          label += ' — Today';
            else if (isYesterday) label += ' — Yesterday';
            else {
                const d = new Date(selectedDate + 'T00:00:00');
                label += ' — ' + d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
            }
            const bannerLbl = document.getElementById('banner-date-label');
            if (bannerLbl) bannerLbl.textContent = label;
        }

        // ── Boot ───────────────────────────────────────────────
        async function adminBoot() {
            const me = await api.auth.me().catch(() => null);
            if (!me || !me.success || me.user.role !== 'admin') {
                window.location.href = 'login.php';
                return;
            }
            await loadAllBranchesFromDB();
        }

        // ── Load all branches from DB ──────────────────────────
        async function loadAllBranchesFromDB() {
            try {
                const res = await fetch(`admin.php?action=all_branches&date=${selectedDate}`, { credentials: 'same-origin' }).then(r => r.json());
                if (!res || !res.success) return;

                let totalRevenue = 0, totalOrders = 0, topBranch = null, topRevenue = 0;

                res.data.forEach(row => {
                    const key = Object.keys(branchIdMap).find(k => branchIdMap[k] === row.id);
                    if (!key) return;
                    const rev = parseFloat(row.sales_today || 0);
                    const ord = parseInt(row.orders_today || 0);
                    totalRevenue += rev;
                    totalOrders  += ord;
                    if (rev > topRevenue) { topRevenue = rev; topBranch = branchInfo[key].name; }

                    allBranchData[key] = {
                        ...branchInfo[key],
                        sales:    '₱ ' + rev.toLocaleString('en-PH', { minimumFractionDigits: 2 }),
                        salesRaw: rev,
                        orders:   ord,
                        growth:   '—',
                    };
                });

                Object.keys(branchInfo).forEach(k => {
                    if (!allBranchData[k]) allBranchData[k] = {
                        ...branchInfo[k], sales: '₱ 0.00', salesRaw: 0, orders: 0, growth: '—'
                    };
                });

                document.getElementById('all-branches-total').textContent =
                    '₱ ' + totalRevenue.toLocaleString('en-PH', { minimumFractionDigits: 2 });
                document.getElementById('all-branches-orders').textContent = totalOrders;
                document.getElementById('all-branches-top').textContent   = topBranch || '—';

                try {
                    const kpis = await fetch('dashboard.php?action=kpis', { credentials: 'same-origin' }).then(r => r.json());
                    if (kpis && kpis.success) {
                        document.getElementById('all-branches-lowstock').textContent = kpis.low_stock;
                        document.getElementById('all-branches-outstock').textContent = kpis.out_of_stock;
                    }
                } catch (_) {}

                const bars   = document.getElementById('all-branches-bars');
                const maxRev = Math.max(...Object.values(allBranchData).map(b => b.salesRaw), 1);
                bars.innerHTML = Object.entries(allBranchData).map(([key, b]) => {
                    const pct   = Math.round((b.salesRaw / maxRev) * 100);
                    const isTop = b.name === topBranch;
                    return `<div class="flex flex-col items-center gap-1 cursor-pointer" onclick="selectBranch('${key}')" style="min-width:80px;">
                        <div class="text-[9px] font-bold text-slate-400 uppercase text-center leading-tight mb-1" style="max-width:76px;">${b.name}</div>
                        <div class="w-16 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden" style="height:48px;display:flex;align-items:flex-end;">
                            <div style="width:100%;height:${Math.max(pct,4)}%;background:${isTop?'#4f46e5':'#94a3b8'};border-radius:4px;transition:height 0.5s ease;"></div>
                        </div>
                        <div class="text-[10px] font-bold ${isTop?'text-indigo-600':'text-slate-500'}">₱${(b.salesRaw/1000).toFixed(1)}k</div>
                    </div>`;
                }).join('');

                applyBranchData();
                await loadBranchTransactions(currentBranch);

            } catch (e) {
                console.error('loadAllBranchesFromDB:', e);
            }
        }

        // ── Load transactions for a branch ────────────────────
        async function loadBranchTransactions(branchKey) {
            const branchId = branchIdMap[branchKey];
            const tbody = document.getElementById('live-sales-table');
            if (!tbody) return;

            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-slate-400 text-sm">Loading transactions…</td></tr>`;

            try {
                const res = await fetch(`admin.php?action=branch&id=${branchId}&date=${selectedDate}`, { credentials: 'same-origin' }).then(r => r.json());

                if (!res || !res.success || !res.transactions.length) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10 text-slate-400">No transactions recorded for this branch on the selected date.</td></tr>`;
                    return;
                }

                tbody.innerHTML = res.transactions.map(t => {
                    const dt   = new Date(t.created_at);
                    const time = dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
                    const date = dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                    const typeColor = t.order_type === 'Dine-in' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700';
                    return `<tr class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                        <td class="py-3 px-4 text-sm text-slate-500">${date} ${time}</td>
                        <td class="py-3 px-4 text-sm font-bold text-indigo-600">${t.reference_no}</td>
                        <td class="py-3 px-4 text-sm text-slate-600 dark:text-slate-300 max-w-xs truncate">${t.items_summary || '—'}</td>
                        <td class="py-3 px-4 text-sm"><span class="px-2 py-1 rounded-full text-xs font-semibold ${typeColor}">${t.order_type}</span></td>
                        <td class="py-3 px-4 text-sm font-bold text-slate-800 dark:text-slate-100">₱${parseFloat(t.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                    </tr>`;
                }).join('');
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-red-400 text-sm">Failed to load transactions.</td></tr>`;
            }
        }

        // ── History Modal ──────────────────────────────────────
        async function openHistoryModal(e) {
            e.preventDefault();
            document.getElementById('historyModal').classList.remove('hidden');
            const branchId = branchIdMap[currentBranch];
            const tbody    = document.getElementById('history-modal-body');

            try {
                const res = await fetch(`admin.php?action=branch&id=${branchId}&date=${selectedDate}`, { credentials: 'same-origin' }).then(r => r.json());
                if (!res.success || !res.transactions.length) {
                    tbody.innerHTML = `<tr><td colspan="3" class="text-center py-10 text-slate-400">No transactions for this branch on the selected date.</td></tr>`;
                    return;
                }
                tbody.innerHTML = res.transactions.map(t => {
                    const dt        = new Date(t.created_at);
                    const time      = dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
                    const dateLabel = dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                    return `<tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase mb-1 inline-block">${dateLabel}</span>
                            <p class="text-xs text-slate-500 font-medium">${time}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-800">${t.items_summary || '—'}</p>
                            <p class="text-xs text-slate-400 mt-0.5"><i class="fas fa-hashtag"></i> ${t.reference_no}</p>
                        </td>
                        <td class="px-6 py-4 text-right font-black text-emerald-600 text-lg">
                            ₱ ${parseFloat(t.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                        </td>
                    </tr>`;
                }).join('');
            } catch (_) {
                tbody.innerHTML = `<tr><td colspan="3" class="text-center py-8 text-red-400">Failed to load.</td></tr>`;
            }
        }

        // ── Stocks Modal ───────────────────────────────────────
        async function openStocksModal(e) {
            e.preventDefault();
            document.getElementById('stocksModal').classList.remove('hidden');
            const tbody = document.getElementById('stocks-modal-body');

            try {
                const res = await api.products.list();
                if (!res.success || !res.data.length) {
                    tbody.innerHTML = `<tr><td colspan="3" class="text-center py-10 text-slate-400">No products found.</td></tr>`;
                    return;
                }
                tbody.innerHTML = res.data.map(p => {
                    let statusHtml;
                    if      (p.stock <= 0)  statusHtml = `<span class="bg-red-50 text-red-600 border border-red-100 px-2 py-1 rounded text-[10px] font-bold uppercase">Out of Stock</span>`;
                    else if (p.stock <= 10) statusHtml = `<span class="bg-amber-50 text-amber-600 border border-amber-100 px-2 py-1 rounded text-[10px] font-bold uppercase">Low Stock</span>`;
                    else                   statusHtml = `<span class="bg-emerald-50 text-emerald-600 border border-emerald-100 px-2 py-1 rounded text-[10px] font-bold uppercase">In Stock</span>`;
                    return `<tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-bold text-slate-800">${p.name}</td>
                        <td class="px-6 py-4 text-slate-600 font-medium">${p.stock} units</td>
                        <td class="px-6 py-4">${statusHtml}</td>
                    </tr>`;
                }).join('');
            } catch (_) {
                tbody.innerHTML = `<tr><td colspan="3" class="text-center py-8 text-red-400">Failed to load.</td></tr>`;
            }
        }

        // ── Branch selection helpers ───────────────────────────
        function selectBranch(key) {
            currentBranch = key;
            document.getElementById('branchSelect').value      = key;
            document.getElementById('branchSelectSales').value = key;
            applyBranchData();
            loadBranchTransactions(key);
        }

        function switchBranch() {
            currentBranch = document.getElementById('branchSelect').value;
            document.getElementById('branchSelectSales').value = currentBranch;
            applyBranchData();
            loadBranchTransactions(currentBranch);
        }

        function switchBranchFromSales() {
            currentBranch = document.getElementById('branchSelectSales').value;
            document.getElementById('branchSelect').value = currentBranch;
            applyBranchData();
            loadBranchTransactions(currentBranch);
        }

        function applyBranchData() {
            const b = allBranchData[currentBranch] || { ...branchInfo[currentBranch], sales: '₱ 0.00', orders: 0, growth: '—' };
            document.getElementById('home-branch-label').innerText   = b.name;
            document.getElementById('home-total-sales').innerText    = b.sales;
            document.getElementById('home-orders').innerText         = b.orders;
            document.getElementById('home-growth').innerText         = b.growth;
            document.getElementById('sales-branch-status').innerText = b.name;
            document.getElementById('live-branch-label').innerText   = b.name;
            document.getElementById('live-branch-name').innerText    = b.name;
            renderBranchCards(b);
        }

        function renderBranchCards(b) {
            const grid  = document.getElementById('branch-cards-grid');
            const cards = [
                { icon: 'fa-cash-register',  label: 'Gross Sales',  value: b.sales,  sub: 'Revenue for selected date', color: 'text-emerald-600 dark:text-emerald-400' },
                { icon: 'fa-receipt',        label: 'Total Orders', value: b.orders, sub: 'Completed orders',           color: 'text-indigo-600 dark:text-indigo-400'   },
                { icon: 'fa-arrow-trend-up', label: 'Growth',       value: b.growth, sub: 'vs yesterday',               color: 'text-sky-600 dark:text-sky-400'         },
                { icon: 'fa-map-marker-alt', label: 'Branch',       value: b.name,   sub: b.location,                   color: 'text-violet-600 dark:text-violet-400'   },
            ];
            grid.innerHTML = cards.map(c => `
                <div onclick="viewBranchDetails('${c.label}', '${c.value}', '${b.status}', '${b.location}')"
                     class="branch-card cursor-pointer group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-500 p-6 rounded-3xl shadow-sm hover:shadow-xl transition-all">
                    <div class="flex justify-between items-start mb-5">
                        <div class="w-10 h-10 rounded-full bg-slate-50 dark:bg-slate-700 border border-slate-100 dark:border-slate-600 flex items-center justify-center text-slate-400 group-hover:text-indigo-500 group-hover:bg-indigo-50 transition-colors">
                            <i class="fas ${c.icon}"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-slate-200 dark:text-slate-700 group-hover:text-indigo-300 transition-colors text-xs mt-1"></i>
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">${c.label}</p>
                    <p class="text-xl font-black ${c.color} mb-1 truncate">${c.value}</p>
                    <p class="text-xs text-slate-400 font-medium truncate">${c.sub}</p>
                </div>`).join('');
        }

        document.addEventListener('DOMContentLoaded', adminBoot);

        // ── SPA Routing ────────────────────────────────────────
        let chartsRendered = false;

        function switchPage(pageId) {
            document.querySelectorAll('.page-view').forEach(v => v.classList.remove('active'));
            document.getElementById('page-' + pageId).classList.add('active');
            document.querySelectorAll('.nav-link').forEach(l => { l.classList.remove('is-active'); l.classList.add('is-inactive'); });
            const al = document.getElementById('nav-' + pageId);
            if (al) { al.classList.remove('is-inactive'); al.classList.add('is-active'); }
            if (pageId === 'stats' && !chartsRendered) { initCharts(); chartsRendered = true; }
        }

        // ── Dark Mode ──────────────────────────────────────────
        const htmlEl = document.documentElement;
        const knob   = document.getElementById('darkModeKnob');

        function updateToggleUI(isDark) {
            isDark ? knob.classList.add('translate-x-7') : knob.classList.remove('translate-x-7');
        }

        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            htmlEl.classList.add('dark'); updateToggleUI(true);
        } else {
            htmlEl.classList.remove('dark'); updateToggleUI(false);
        }

        function toggleDarkMode() {
            if (htmlEl.classList.contains('dark')) {
                htmlEl.classList.remove('dark'); localStorage.theme = 'light'; updateToggleUI(false);
            } else {
                htmlEl.classList.add('dark'); localStorage.theme = 'dark'; updateToggleUI(true);
            }
        }

        // ── Modals ─────────────────────────────────────────────
        function viewBranchDetails(name, sales, status, location) {
            document.getElementById('modalTitle').innerText    = name;
            document.getElementById('modalSales').innerText    = sales;
            document.getElementById('modalStatus').innerText   = status;
            document.getElementById('modalLocation').innerText = location;
            document.getElementById('branchModal').classList.remove('hidden');
        }
        function closeModal()        { document.getElementById('branchModal').classList.add('hidden'); }
        function closeHistoryModal() { document.getElementById('historyModal').classList.add('hidden'); }
        function closeStocksModal()  { document.getElementById('stocksModal').classList.add('hidden'); }

        // ── Logout ─────────────────────────────────────────────
        function logoutSession() {
            if (confirm('Are you sure you want to log out of the system?')) {
                window.location.href = 'login.php';
            }
        }

        // ── Export CSV ─────────────────────────────────────────
        async function exportSalesData() {
            const branchId = branchIdMap[currentBranch];
            const b        = allBranchData[currentBranch] || branchInfo[currentBranch];
            const btn      = document.getElementById('downloadBtn');

            if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting…'; btn.disabled = true; }

            try {
                const res  = await fetch(`admin.php?action=branch&id=${branchId}&date=${selectedDate}`, { credentials: 'same-origin' }).then(r => r.json());
                const txns = (res.success && res.transactions) ? res.transactions : [];

                const headers = ['Branch', 'Date', 'Time', 'Reference', 'Items', 'Type', 'Amount (PHP)'];
                const rows    = txns.map(t => {
                    const dt = new Date(t.created_at);
                    return [
                        b.name,
                        dt.toLocaleDateString('en-PH'),
                        dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }),
                        t.reference_no,
                        t.items_summary || '',
                        t.order_type,
                        parseFloat(t.total).toFixed(2),
                    ];
                });

                const esc  = val => `"${String(val).replace(/"/g,'""')}"`;
                const csv  = [headers, ...rows].map(r => r.map(esc).join(',')).join('\n');
                const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
                const url  = URL.createObjectURL(blob);
                const a    = document.createElement('a');
                a.href     = url;
                a.download = `sales_${b.name.replace(/\s+/g,'_')}_${selectedDate}.csv`;
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                URL.revokeObjectURL(url);

                if (btn) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Downloaded!';
                    btn.classList.replace('bg-indigo-600','bg-emerald-600');
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fas fa-download"></i> Download Full Record';
                        btn.classList.replace('bg-emerald-600','bg-indigo-600');
                        btn.disabled  = false;
                    }, 2500);
                }
            } catch (_) {
                if (btn) { btn.innerHTML = '<i class="fas fa-download"></i> Download Full Record'; btn.disabled = false; }
                alert('Export failed. Please try again.');
            }
        }

        // ── Charts ─────────────────────────────────────────────
        function initCharts() {
            Chart.defaults.color       = '#64748b';
            Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";

            new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: {
                    labels:   ['W1','W2','W3','W4'],
                    datasets: [{
                        data:                [10000,11500,9500,12450],
                        borderColor:         '#4f46e5',
                        borderWidth:         3,
                        tension:             0.4,
                        fill:                true,
                        backgroundColor:     'rgba(79,70,229,0.08)',
                        pointBackgroundColor:'#ffffff',
                        pointBorderColor:    '#4f46e5',
                        pointBorderWidth:    2,
                        pointRadius:         4,
                        pointHoverRadius:    6,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend:  { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b', padding: 12, cornerRadius: 8, displayColors: false,
                            callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString() },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: {
                            grid: { color: '#f1f5f9', drawBorder: false }, border: { display: false },
                            ticks: { callback: v => '₱' + (v/1000) + 'k' },
                        },
                    },
                },
            });

            new Chart(document.getElementById('categoryChart'), {
                type: 'doughnut',
                data: {
                    labels:   ['Rice Meals','Burgers & Sandwiches','Desserts','Drinks'],
                    datasets: [{ data: [6500,3200,1500,1250], backgroundColor: ['#4f46e5','#10b981','#f59e0b','#ec4899'], borderWidth: 0, hoverOffset: 8 }],
                },
                options: {
                    cutout:  '75%',
                    plugins: {
                        legend:  { display: false },
                        tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 },
                    },
                },
            });
        }
    </script>

    <!-- PWA Registration -->
    <script src="js/pwa.js"></script>
</body>
</html>
