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
    <title>POS - Terminal | Luna's Ilonggo Legacy</title>
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
            --success: #48bb78;
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
        
        .sidebar.collapsed .nav-links span {
            display: none;
        }
        
        .sidebar.collapsed .nav-links a {
            justify-content: center;
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
        
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .search-bar input {
            padding: 10px 15px;
            width: 300px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f8fafc;
            outline: none;
            transition: 0.2s;
        }
        
        .search-bar input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }
        
        .category-bar {
            padding: 15px 30px;
            background: white;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        
        .cat-btn {
            padding: 8px 16px;
            border-radius: 25px;
            border: 1px solid var(--border);
            background: #f8fafc;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
            transition: all 0.2s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .cat-btn:hover {
            border-color: var(--primary);
            background: #edf2f7;
        }
        
        .cat-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 6px -1px rgba(90, 103, 216, 0.2);
        }
        
        .product-area {
            padding: 25px;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: white;
            padding: 15px;
            text-align: center;
            border-radius: 12px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }
        
        .img-box {
            height: 120px;
            background: #edf2f7;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: var(--primary);
            font-size: 28px;
            overflow: hidden;
        }
        
        .img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .price {
            color: var(--primary);
            font-weight: bold;
            margin-top: 5px;
            font-size: 1.1em;
        }
        
        .cart-panel {
            width: 380px;
            background: white;
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 6px rgba(0, 0, 0, 0.02);
        }
        
        .cart-header {
            padding: 20px;
            font-weight: bold;
            font-size: 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-items {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f7fafc;
        }
        
        .qty-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }
        
        .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .qty-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .cart-footer {
            padding: 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
        }
        
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .total {
            color: var(--text-dark);
            font-weight: bold;
            font-size: 20px;
            margin: 15px 0;
            padding-top: 10px;
            border-top: 2px solid var(--border);
        }
        
        .dining-options {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .opt-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }
        
        .opt-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-place {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .btn-place:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }
        
        .order-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .order-modal {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            animation: modalPop 0.3s ease-out;
        }
        
        @keyframes modalPop {
            from {
                transform: scale(0.8);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked+.slider {
            background-color: var(--success);
        }
        
        input:checked+.slider:before {
            transform: translateX(20px);
        }
        
        .coupon-section {
            margin-bottom: 15px;
            display: flex;
            gap: 5px;
        }
        
        .coupon-input {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
        }
        
        .btn-apply {
            padding: 0 12px;
            background: var(--text-dark);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        /* ── Payment Method Overlay ── */
        
        .pay-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(6px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .pay-modal {
            background: #fff;
            border-radius: 24px;
            text-align: center;
            max-width: 400px;
            width: 92%;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,0.18);
            animation: modalPop 0.25s ease-out;
        }

        .pay-modal-header {
            padding: 28px 28px 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .pay-modal-header h2 {
            font-size: 20px;
            font-weight: 800;
            color: #1a202c;
            margin: 0 0 6px;
            letter-spacing: -0.3px;
        }

        .pay-modal-header p {
            font-size: 14px;
            color: #718096;
            margin: 0;
        }

        .pay-modal-header .pay-total-amount {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
            margin-top: 4px;
            display: block;
        }

        .pay-method-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin: 0;
        }

        .pay-method-btn {
            padding: 28px 16px;
            border: none;
            border-right: 1px solid #f0f0f0;
            cursor: pointer;
            background: #fff;
            transition: background 0.18s, transform 0.15s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 15px;
            color: #2d3748;
            position: relative;
        }

        .pay-method-btn:last-child {
            border-right: none;
        }

        .pay-method-btn .pay-icon-wrap {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            transition: transform 0.15s;
        }

        .pay-method-btn.cash-btn .pay-icon-wrap {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
        }

        .pay-method-btn.gcash-btn .pay-icon-wrap {
            background: linear-gradient(135deg, #bee3f8, #90cdf4);
            color: #1a365d;
        }

        .pay-method-btn.cash-btn:hover {
            background: #f0fff4;
        }

        .pay-method-btn.gcash-btn:hover {
            background: #ebf8ff;
        }

        .pay-method-btn:hover .pay-icon-wrap {
            transform: scale(1.1);
        }

        .pay-method-btn .pay-label {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .pay-method-btn .pay-sublabel {
            font-size: 11px;
            font-weight: 500;
            color: #a0aec0;
            margin-top: -6px;
        }

        .pay-cancel-btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-top: 1px solid #f0f0f0;
            background: #fafafa;
            color: #a0aec0;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            letter-spacing: 0.2px;
        }

        .pay-cancel-btn:hover {
            background: #fff5f5;
            color: #e53e3e;
        }

        

        /* ── GCash QR Modal ── */
        
        .gcash-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 1100;
        }
        
        .gcash-modal {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 400px;
            overflow: hidden;
            animation: modalPop 0.3s ease-out;
        }
        
        .gcash-header {
            background: linear-gradient(135deg, #0070e0, #00a8ff);
            padding: 22px 20px 18px;
            text-align: center;
            color: white;
        }
        
        .gcash-header .gcash-logo {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -1px;
            margin-bottom: 4px;
        }
        
        .gcash-header .gcash-subtitle {
            font-size: 13px;
            opacity: 0.85;
        }
        
        .gcash-body {
            padding: 22px 24px 26px;
            text-align: center;
        }
        
        .gcash-amount {
            font-size: 36px;
            font-weight: 800;
            color: #0070e0;
            margin: 8px 0 18px;
        }
        
        .gcash-amount-label {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        
        #qrCodeCanvas {
            margin: 0 auto 14px;
            display: flex;
            justify-content: center;
        }
        
        #qrCodeCanvas canvas,
        #qrCodeCanvas img {
            border-radius: 12px;
            border: 3px solid #e8f4ff;
            padding: 8px;
        }
        
        .gcash-instructions {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 20px;
            line-height: 1.6;
            background: #f0f8ff;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid #bee3f8;
        }
        
        .gcash-confirm-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0070e0, #00a8ff);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            margin-bottom: 10px;
        }
        
        .gcash-confirm-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .gcash-back-btn {
            width: 100%;
            padding: 12px;
            background: #f7fafc;
            color: var(--text-light);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .gcash-back-btn:hover {
            background: #edf2f7;
        }
    </style>
</head>

<body>
    <!-- ── Apply saved theme instantly on load ── -->
    <script>
        (function() {
            const saved = localStorage.getItem('pos_theme');
            if (saved) {
                try {
                    const t = JSON.parse(saved);
                    document.documentElement.style.setProperty('--primary', t.primary);
                    document.documentElement.style.setProperty('--primary-hover', t.hover);
                } catch (e) {}
            }
        })();
    </script>

    <!-- ── Payment Method Selection Overlay ── -->
    <div class="pay-overlay" id="payOverlay">
        <div class="pay-modal">
            <div class="pay-modal-header">
                <h2>Select Payment Method</h2>
                <p>Total Amount</p>
                <span class="pay-total-amount" id="payModalTotal"></span>
            </div>
            <div class="pay-method-grid">
                <button class="pay-method-btn cash-btn" onclick="selectPayment('Cash')">
                    <div class="pay-icon-wrap">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <span class="pay-label">Cash</span>
                    <span class="pay-sublabel">Pay at counter</span>
                </button>
                <button class="pay-method-btn gcash-btn" onclick="selectPayment('GCash')">
                    <div class="pay-icon-wrap">
                        <i class="fa-solid fa-mobile-screen-button"></i>
                    </div>
                    <span class="pay-label">GCash</span>
                    <span class="pay-sublabel">Scan QR to pay</span>
                </button>
            </div>
            <button class="pay-cancel-btn" onclick="closePayOverlay()">Cancel</button>
        </div>
    </div>

    <!-- ── GCash QR Modal ── -->
    <div class="gcash-overlay" id="gcashOverlay">
        <div class="gcash-modal">
            <div class="gcash-header">
                <div class="gcash-logo">&#x1F4F1; GCash</div>
                <div class="gcash-subtitle">Scan to Pay — Luna's Ilonggo Legacy</div>
            </div>
            <div class="gcash-body">
                <div class="gcash-amount-label">Amount to Pay</div>
                <div class="gcash-amount" id="gcashAmount">₱0.00</div>
                <div id="qrCodeCanvas">
<img src="Qr.jpg"                        alt="GCash QR" style="width:280px;height:280px;object-fit:contain;border-radius:12px;border:3px solid #e8f4ff;padding:6px;"></div>
                <div class="gcash-instructions">
                    📱 Open your <strong>GCash app</strong> → tap <strong>Pay QR</strong> → scan the code above.<br> Ask customer to show payment confirmation before proceeding.
                </div>
                <button class="gcash-confirm-btn" onclick="confirmGCashPayment()">
                    <i class="fa-solid fa-circle-check" style="margin-right:8px;"></i>Payment Received
                </button>
                <button class="gcash-back-btn" onclick="backToPayMethods()">← Change Payment Method</button>
            </div>
        </div>
    </div>

    <!-- ── Success Overlay (original, unchanged) ── -->
    <div class="order-overlay" id="orderOverlay">
        <div class="order-modal">
            <i class="fa-solid fa-circle-check" style="font-size:60px;color:var(--success);margin-bottom:20px;"></i>
            <h2 style="margin-bottom:10px;">Payment Successful!</h2>
            <p id="final-total-msg" style="color:var(--text-light);margin-bottom:25px;"></p>
            <button class="btn-place" onclick="closeOrder()">Start New Transaction</button>
        </div>
    </div>

    <nav class="sidebar" id="sidebar">
        <div class="brand" onclick="toggleSidebar()">
<img src="lunas.jpg" alt="Luna's Logo">          </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> <span>Dashboard</span></a></li>
            <li><a href="pos_terminal.php" class="active"><i class="fa-solid fa-cash-register"></i> <span>New Order</span></a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-box"></i> <span>Inventory</span></a></li>
            <li><a href="salesreport.php"><i class="fa-solid fa-chart-line"></i> <span>Sales Report</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="customer.php"><i class="fa-solid fa-users"></i> <span>Customers</span></a></li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> <span>Settings</span></a></li>
        </ul>
    </nav>

    <div class="main-content">
        <header class="header">
            <h2 id="order-id" style="font-size:20px;">New Order</h2>
            <div class="search-bar">
                <input type="text" id="productSearch" placeholder="Search menu items..." onkeyup="filterProducts()">
            </div>
        </header>

        <div class="category-bar">
            <button class="cat-btn active" onclick="setCategory('All', this)">
                <i class="fa-solid fa-border-all"></i> All Items
            </button>
            <button class="cat-btn" onclick="setCategory('Breakfast', this)">
                <i class="fa-solid fa-sun"></i> All Day Breakfast
            </button>
            <button class="cat-btn" onclick="setCategory('Merienda', this)">
                <i class="fa-solid fa-bowl-rice"></i> Merienda
            </button>
            <button class="cat-btn" onclick="setCategory('Burgers And Sandwiches', this)">
                <i class="fa-solid fa-burger"></i> Burgers &amp; Sandwiches
            </button>
            <button class="cat-btn" onclick="setCategory('Rice Meal', this)">
                <i class="fa-solid fa-bowl-rice"></i> Rice Meal
            </button>
            <button class="cat-btn" onclick="setCategory('Native', this)">
                <i class="fa-solid fa-star"></i> Native Specials
            </button>
            <button class="cat-btn" onclick="setCategory('Dessert', this)">
                <i class="fa-solid fa-ice-cream"></i> Desserts
            </button>
            <button class="cat-btn" onclick="setCategory('Drinks', this)">
                <i class="fa-solid fa-glass-water"></i> Drinks
            </button>
        </div>

        <div class="product-area">
            <div class="grid" id="productGrid">
                <div style="grid-column:1/-1;text-align:center;padding:40px;color:#718096;">
                    Loading menu…
                </div>
            </div>
        </div>
    </div>

    <div class="cart-panel">
        <div class="cart-header">
            <span>Current Cart</span>
            <button onclick="voidTransaction()" style="background:none;border:none;color:var(--danger);font-size:13px;font-weight:600;cursor:pointer;">
                <i class="fa-solid fa-trash-can"></i> Clear
            </button>
        </div>

        <div class="cart-items" id="cart-items"></div>

        <div class="cart-footer">
            <div class="dining-options">
                <button class="opt-btn active" id="btn-dinein" onclick="setDining('Dine-in')">
                    <i class="fa-solid fa-utensils"></i> Dine-in
                </button>
                <button class="opt-btn" id="btn-takeout" onclick="setDining('Take-out')">
                    <i class="fa-solid fa-bag-shopping"></i> Take-out
                </button>
            </div>

            <div class="coupon-section">
                <input type="text" id="couponCode" class="coupon-input" placeholder="Enter Coupon (e.g. SAVE500)">
                <button class="btn-apply" onclick="applyCoupon()">Apply</button>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding:10px;background:#fff;border:1px dashed var(--border);border-radius:8px;">
                <span style="font-size:13px;font-weight:600;">Apply Senior/PWD (20%)</span>
                <label class="switch">
                    <input type="checkbox" id="applyDiscount" onchange="renderCart()">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="row"><span>Subtotal</span><span id="subtotal">₱0.00</span></div>
            <div class="row"><span>Discount (Senior/PWD)</span><span id="discount" style="color:var(--danger);">₱0.00</span></div>
            <div class="row" id="couponRow" style="display:none;"><span>Coupon Discount</span><span id="couponValue" style="color:var(--danger);">- ₱0.00</span></div>
            <div class="row total"><span>Total</span><span id="total">₱0.00</span></div>

            <button class="btn-place" onclick="placeOrder()">Proceed to Payment</button>
        </div>
    </div>

    <script src="js/api.js"></script>
    <script>
        // ── Fallback hardcoded menu (shown if DB has no products) ──
        const fallbackProducts = [{
            name: 'Tocino',
            price: 129,
            category: 'Breakfast',
            image: 'img/img/6626932.webp'
        }, {
            name: 'Spam',
            price: 142,
            category: 'Breakfast',
            image: 'img/6626929.webp'
        }, {
            name: 'Bacon',
            price: 129,
            category: 'Breakfast',
            image: 'img/8160046.webp'
        }, {
            name: 'Hungarian Sausage',
            price: 142,
            category: 'Breakfast',
            image: 'img/6626931.webp'
        }, {
            name: 'Tapa',
            price: 129,
            category: 'Breakfast',
            image: 'img/6626928.webp'
        }, {
            name: 'Burger Steak',
            price: 91,
            category: 'Breakfast',
            image: 'img/13552550.webp'
        }, {
            name: 'Tapsilog',
            price: 170,
            category: 'Breakfast',
            image: 'img/17233968.webp'
        }, {
            name: 'Tocilog',
            price: 145,
            category: 'Breakfast',
            image: 'img/5380943.webp'
        }, {
            name: 'Longsilog',
            price: 145,
            category: 'Breakfast',
            image: 'img/17233961.webp'
        }, {
            name: 'Cornsilog',
            price: 145,
            category: 'Breakfast',
            image: 'img/17233959.webp'
        }, {
            name: 'Embosilog',
            price: 145,
            category: 'Breakfast',
            image: 'img/17233963.webp'
        }, {
            name: 'Porksilog',
            price: 180,
            category: 'Breakfast',
            image: 'img/17233962.webp'
        }, {
            name: 'Hotsilog',
            price: 145,
            category: 'Breakfast',
            image: 'img/17233960.webp'
        }, {
            name: 'Spamsilog',
            price: 175,
            category: 'Breakfast',
            image: 'img/17233964.webp'
        }, {
            name: 'Hamsilog',
            price: 145,
            category: 'Breakfast',
            image: 'img/37233529.webp'
        }, {
            name: 'Bangsilog',
            price: 180,
            category: 'Breakfast',
            image: 'img/17233965.webp'
        }, {
            name: 'Hungsilog',
            price: 230,
            category: 'Breakfast',
            image: 'img/41915584.webp'
        }, {
            name: 'Longganisa',
            price: 116,
            category: 'Breakfast',
            icon: 'fa-bowl-rice'
        }, {
            name: 'La Paz Batchoy',
            price: 140,
            category: 'Merienda',
            image: 'img/17233972.webp'
        }, {
            name: 'Lomi',
            price: 150,
            category: 'Merienda',
            image: 'img/5380962.webp'
        }, {
            name: 'Bihon Guisado',
            price: 150,
            category: 'Merienda',
            image: 'img/17233969.webp'
        }, {
            name: 'Sotanghon Guisado',
            price: 170,
            category: 'Merienda',
            image: 'img/37233252.webp'
        }, {
            name: 'Pancit Molo',
            price: 120,
            category: 'Merienda',
            icon: 'fa-bowl-rice'
        }, {
            name: 'Classic Burger',
            price: 49,
            category: 'Burgers And Sandwiches',
            image: 'img/11314900.webp'
        }, {
            name: 'Cheese Burger',
            price: 66,
            category: 'Burgers And Sandwiches',
            image: 'img/11466934.webp'
        }, {
            name: 'Egg CheeseBurger',
            price: 79,
            category: 'Burgers And Sandwiches',
            image: 'img/11314899.webp'
        }, {
            name: 'Bacon CheeseBurger',
            price: 97,
            category: 'Burgers And Sandwiches',
            image: 'img/11466936.webp'
        }, {
            name: 'DBL Cheese Burger',
            price: 111,
            category: 'Burgers And Sandwiches',
            image: 'img/11466937.webp'
        }, {
            name: 'Tuna Sandwich (Doubles)',
            price: 128,
            category: 'Burgers And Sandwiches',
            image: 'img/11573049.webp'
        }, {
            name: 'SPAM, Cheese, And Egg',
            price: 84,
            category: 'Burgers And Sandwiches',
            image: 'img/11466935.webp'
        }, {
            name: 'Ham, Cheese & Egg Sandwich With Fries',
            price: 95,
            category: 'Burgers And Sandwiches',
            image: 'img/23858673.webp'
        }, {
            name: 'Ham & Cheese Sandwich With Fries',
            price: 80,
            category: 'Burgers And Sandwiches',
            image: 'img/23858672.webp'
        }, {
            name: 'Spam & Egg Sandwich With Fries',
            price: 120,
            category: 'Burgers And Sandwiches',
            image: 'img/23858674.webp'
        }, {
            name: 'Burger With Fries',
            price: 100,
            category: 'Burgers And Sandwiches',
            image: 'img/23858675.webp'
        }, {
            name: 'Cheeseburger With Fries',
            price: 110,
            category: 'Burgers And Sandwiches',
            image: 'img/23858676.webp'
        }, {
            name: 'Bacon Cheeseburger With Fries',
            price: 125,
            category: 'Burgers And Sandwiches',
            image: 'img/23858677.webp'
        }, {
            name: 'Nachos',
            price: 120,
            category: 'Burgers And Sandwiches',
            image: 'img/38386890.webp'
        }, {
            name: 'Meaty Hotdog Bunwich',
            price: 120,
            category: 'Burgers And Sandwiches',
            icon: 'fa-bowl-rice'
        }, {
            name: 'Roasted Chicken',
            price: 190,
            category: 'Rice Meal',
            image: 'img/24576958.webp'
        }, {
            name: 'Crispy Pork Belly',
            price: 200,
            category: 'Rice Meal',
            image: 'img/24576959.webp'
        }, {
            name: '2pcs Honey Sriracha',
            price: 125,
            category: 'Rice Meal',
            image: 'img/32770491.webp'
        }, {
            name: '4pcs Honey Sriracha',
            price: 200,
            category: 'Rice Meal',
            image: 'img/32770491.webp'
        }, {
            name: '2pcs Garlic Parmesan',
            price: 125,
            category: 'Rice Meal',
            image: 'img/32770495.webp'
        }, {
            name: '4 pcs Garlic Parmesan',
            price: 200,
            category: 'Rice Meal',
            image: 'img/32770604.webp'
        }, {
            name: '2pcs Teriyaki',
            price: 125,
            category: 'Rice Meal',
            image: 'img/32770492.webp'
        }, {
            name: '4pcs Teriyaki',
            price: 200,
            category: 'Rice Meal',
            image: 'img/32770492.webp'
        }, {
            name: '2pcs Buffalo',
            price: 125,
            category: 'Rice Meal',
            image: 'img/32770496.webp'
        }, {
            name: '4 pcs Buffalo',
            price: 200,
            category: 'Rice Meal',
            image: 'img/32770496.webp'
        }, {
            name: 'Garlic Beef Broccoli',
            price: 129,
            category: 'Rice Meal',
            image: 'img/13811640.webp'
        }, {
            name: 'Chicken Ala King',
            price: 129,
            category: 'Rice Meal',
            image: 'img/13954807.webp'
        }, {
            name: '2 pcs Salted Egg',
            price: 125,
            category: 'Rice Meal',
            icon: 'fa-bowl-food'
        }, {
            name: '4 pcs Salted Egg',
            price: 125,
            category: 'Rice Meal',
            icon: 'fa-bowl-food'
        }, {
            name: 'Egg Arrozcaldo',
            price: 75,
            category: 'Native',
            image: 'img/5380781.webp'
        }, {
            name: 'Native Chicken Arrozcaldo',
            price: 100,
            category: 'Native',
            image: 'img/5380783.webp'
        }, {
            name: 'Native Chicken & Egg',
            price: 110,
            category: 'Native',
            image: 'img/97961544_529250581103841_2780739574651944960_n.jpg'
        }, {
            name: 'Atay Baticolon',
            price: 85,
            category: 'Native',
            image: 'img/101128895_533058067389759_2367654116953423872_n.jpg'
        }, {
            name: 'Native Chicken Bits',
            price: 95,
            category: 'Native',
            image: 'img/99279139_530294434332789_2364842025771073536_n.jpg'
        }, {
            name: 'Arrozcaldo Plain',
            price: 60,
            category: 'Native',
            icon: 'fa-bowl-food'
        }, {
            name: 'Leche Flan',
            price: 160,
            category: 'Dessert',
            image: 'img/17233978.webp'
        }, {
            name: 'Leche Flan (Small)',
            price: 60,
            category: 'Dessert',
            image: 'img/17233981.webp'
        }, {
            name: 'Tapioca (Buko Pandan)',
            price: 60,
            category: 'Dessert',
            image: 'img/17233979.webp'
        }, {
            name: 'Black Sambo (Small)',
            price: 60,
            category: 'Dessert',
            image: 'img/17233980.webp'
        }, {
            name: 'Halo-Halo',
            price: 95,
            category: 'Dessert',
            icon: 'fa-bowl-rice'
        }, {
            name: 'Iced Tea',
            price: 35,
            category: 'Drinks',
            icon: 'fa-glass-water'
        }, ];

        // ── State ──────────────────────────────────────────────
        let allProducts = [];
        let cart = [];
        let diningOption = 'Dine-in';
        let currentCategory = 'All';
        let isCouponApplied = false;
        let usingDB = false; // true = products came from database

        // ── Boot ───────────────────────────────────────────────
        async function init() {
            // Try to log in; if it fails just load products anyway (offline mode)
            const user = await api.auth.me().catch(() => null);
            if (!user || !user.success) {
                // Not logged in — redirect to login
                window.location.href = 'login.php';
                return;
            }
            await loadProducts();
        }

        // ── Load products: DB first, fallback to hardcoded list ─
        async function loadProducts() {
            const grid = document.getElementById('productGrid');
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#718096;">Loading menu…</div>';

            try {
                const res = await api.products.list();
                if (res.success && res.data && res.data.length > 0) {
                    // DB has products — use them, but map image_path → image so renderProducts works
                    allProducts = res.data.map(p => ({
                        ...p,
                        image: p.image_path || null, // normalize field name
                        stock: parseInt(p.stock),
                    }));
                    usingDB = true;
                } else {
                    // DB empty or unavailable — use original hardcoded list with images
                    allProducts = fallbackProducts.map((p, i) => ({
                        ...p,
                        id: i + 1,
                        stock: 999, // no stock tracking in fallback mode
                        is_active: 1,
                    }));
                    usingDB = false;
                }
            } catch (e) {
                // Network/API error — use fallback
                allProducts = fallbackProducts.map((p, i) => ({
                    ...p,
                    id: i + 1,
                    stock: 999,
                    is_active: 1,
                }));
                usingDB = false;
            }

            renderProducts();
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function setCategory(cat, btn) {
            currentCategory = cat;
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderProducts();
        }

        function filterProducts() {
            renderProducts();
        }

        function renderProducts() {
            const grid = document.getElementById('productGrid');
            const search = document.getElementById('productSearch').value.toLowerCase();
            grid.innerHTML = '';

            const filtered = allProducts.filter(p => {
                const matchesCat = currentCategory === 'All' || p.category === currentCategory;
                const matchesSearch = p.name.toLowerCase().includes(search);
                return matchesCat && matchesSearch && p.is_active != 0;
            });

            if (!filtered.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#718096;">No items found.</div>';
                return;
            }

            filtered.forEach(p => {
                const outOfStock = usingDB && parseInt(p.stock) <= 0;

                let visual;
                const isValidImage = p.image && (p.image.startsWith('data:') || p.image.startsWith('http'));
                if (isValidImage) visual = `<img src="${p.image}" alt="${p.name}">`;
                else if (p.icon) visual = `<i class="fa-solid ${p.icon}"></i>`;
                else visual = `<i class="fa-solid fa-utensils"></i>`;

                const clickAction = outOfStock ?
                    '' :
                    `addToCart(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.price})`;

                grid.innerHTML += `
                    <div class="card"
                         onclick="${clickAction}"
                         style="${outOfStock ? 'opacity:.5;cursor:not-allowed;' : ''}">
                        <div class="img-box">${visual}</div>
                        <div style="font-weight:600;font-size:14px;">${p.name}</div>
                        <div class="price">${fmt(p.price)}</div>
                        ${outOfStock ? '<div style="font-size:11px;color:#e53e3e;font-weight:700;margin-top:4px;">OUT OF STOCK</div>' : ''}
                    </div>`;
            });
        }
function addToCart(productId, name, price) {
    // Find the product's available stock
    const product = allProducts.find(p => p.id === productId);
    const availableStock = (usingDB && product) ? parseInt(product.stock) : 999;

    const item = cart.find(i => i.product_id === productId);
    const currentQty = item ? item.quantity : 0;

    // Block if adding one more would exceed stock
    if (usingDB && currentQty >= availableStock) {
        alert(`Sorry! Only ${availableStock} unit(s) of "${name}" available in stock.`);
        return;
    }

    if (item) item.quantity++;
    else cart.push({ product_id: productId, name, price, quantity: 1 });
    renderCart();
}

        function minusQty(productId) {
            const item = cart.find(i => i.product_id === productId);
            if (item) {
                item.quantity--;
                if (item.quantity <= 0) cart = cart.filter(i => i.product_id !== productId);
            }
            renderCart();
        }

        function setDining(opt) {
            diningOption = opt;
            document.getElementById('btn-dinein').classList.toggle('active', opt === 'Dine-in');
            document.getElementById('btn-takeout').classList.toggle('active', opt === 'Take-out');
        }

        function applyCoupon() {
            const code = document.getElementById('couponCode').value.toUpperCase();
            let subtotal = 0;
            cart.forEach(item => subtotal += item.price * item.quantity);

            if (code === '500') {
                if (subtotal <= 500 && subtotal > 0) {
                    isCouponApplied = true;
                    alert('Coupon applied successfully!');
                } else if (subtotal > 500) {
                    isCouponApplied = false;
                    alert('Order exceeds ₱500. Coupon cannot be applied.');
                } else {
                    alert('Cart is empty.');
                }
            } else {
                alert('Invalid coupon code.');
                isCouponApplied = false;
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cart-items');
            container.innerHTML = '';
            let subtotal = 0;

            cart.forEach(item => {
                subtotal += item.price * item.quantity;
                container.innerHTML += `
                    <div class="item">
                        <div>
                            <div style="font-weight:600;font-size:14px;">${item.name}</div>
                            <div class="qty-controls">
                                <button class="qty-btn" onclick="minusQty(${item.product_id})">-</button>
                                <span style="font-weight:bold;font-size:14px;">${item.quantity}</span>
                                <button class="qty-btn" onclick="addToCart(${item.product_id},'${item.name.replace(/'/g,"\\'")}',${item.price})">+</button>
                            </div>
                        </div>
                        <div style="font-weight:bold;">${fmt(item.price * item.quantity)}</div>
                    </div>`;
            });

            if (isCouponApplied && subtotal > 500) {
                isCouponApplied = false;
                document.getElementById('couponCode').value = '';
                alert('Coupon deactivated: Total exceeds ₱500.');
            }

            let discount = 0;
            if (document.getElementById('applyDiscount').checked) {
                discount = subtotal * 0.20;
            }

            let couponDiscount = 0;
            if (isCouponApplied) {
                couponDiscount = Math.min(subtotal - discount, 500);
                document.getElementById('couponRow').style.display = 'flex';
                document.getElementById('couponValue').innerText = `- ${fmt(couponDiscount)}`;
            } else {
                document.getElementById('couponRow').style.display = 'none';
            }

            const total = Math.max(0, subtotal - discount - couponDiscount);
            document.getElementById('subtotal').innerText = fmt(subtotal);
            document.getElementById('discount').innerText = `- ${fmt(discount)}`;
            document.getElementById('total').innerText = fmt(total);
        }

        function voidTransaction() {
            if (confirm('Clear current cart?')) {
                cart = [];
                isCouponApplied = false;
                document.getElementById('couponCode').value = '';
                document.getElementById('applyDiscount').checked = false;
                renderCart();
            }
        }

        // ── selected payment method (set by modal) ────────────
        let selectedPaymentMethod = 'Cash';

        function placeOrder() {
            if (cart.length === 0) return alert('Cart is empty!');
            const total = document.getElementById('total').innerText;
            // Show payment method selection instead of going straight to success
            document.getElementById('payModalTotal').innerText = total;
            document.getElementById('payOverlay').style.display = 'flex';
        }

        function closePayOverlay() {
            document.getElementById('payOverlay').style.display = 'none';
        }

        function selectPayment(method) {
            selectedPaymentMethod = method;
            closePayOverlay();

            if (method === 'GCash') {
                showGCashQR();
            } else {
                // Cash — go straight to success
                const total = document.getElementById('total').innerText;
                document.getElementById('final-total-msg').innerText =
                    `${method} Payment — Total: ${total}`;
                document.getElementById('orderOverlay').style.display = 'flex';
            }
        }

        function showGCashQR() {
            const totalText = document.getElementById('total').innerText;
            document.getElementById('gcashAmount').innerText = totalText;
            document.getElementById('gcashOverlay').style.display = 'flex';
        }

        function backToPayMethods() {
            document.getElementById('gcashOverlay').style.display = 'none';
            const total = document.getElementById('total').innerText;
            document.getElementById('payModalTotal').innerText = total;
            document.getElementById('payOverlay').style.display = 'flex';
        }

        function confirmGCashPayment() {
            document.getElementById('gcashOverlay').style.display = 'none';
            const total = document.getElementById('total').innerText;
            document.getElementById('final-total-msg').innerText =
                `GCash Payment — Total: ${total}`;
            document.getElementById('orderOverlay').style.display = 'flex';
        }

        async function closeOrder() {
            const subtotalRaw = cart.reduce((s, i) => s + i.price * i.quantity, 0);
            const discountRaw = document.getElementById('applyDiscount').checked ? subtotalRaw * 0.20 : 0;
            let couponDiscRaw = 0;
            if (isCouponApplied) couponDiscRaw = Math.min(subtotalRaw - discountRaw, 500);
            const totalRaw = Math.max(0, subtotalRaw - discountRaw - couponDiscRaw);

            // Only send to DB if we're actually connected to it
            if (usingDB) {
                const payload = {
                    items: cart.map(i => ({
                        product_id: i.product_id,
                        name: i.name,
                        price: i.price,
                        quantity: i.quantity,
                    })),
                    order_type: diningOption,
                    payment_method: selectedPaymentMethod,
                    subtotal: subtotalRaw,
                    discount: discountRaw,
                    coupon_discount: couponDiscRaw,
                    total: totalRaw,
                };

                const res = await api.orders.place(payload);
                if (!res.success) {
                    alert('Order failed: ' + (res.error || 'Unknown error'));
                    return;
                }
                document.getElementById('order-id').innerText = res.reference_no || 'New Order';
            } else {
                // Fallback mode — just show a random reference
                document.getElementById('order-id').innerText = `Order #${Math.floor(1000 + Math.random() * 9000)}`;
            }

            // Reset state
            cart = [];
            isCouponApplied = false;
            document.getElementById('couponCode').value = '';
            document.getElementById('applyDiscount').checked = false;
            document.getElementById('orderOverlay').style.display = 'none';
            renderCart();

            // Refresh grid so stock counts update (DB mode only)
            if (usingDB) await loadProducts();
        }

        // Peso formatter
        function fmt(n) {
            return '₱' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        init();
    </script>

    <!-- PWA Registration -->
    <script src="js/pwa.js"></script>
</body>

</html>
