<?php
// ============================================================
//  admin.php  —  Admin Dashboard UI + Stats API
//  GET              → HTML admin page
//  GET ?action=xxx  → JSON API response
// ============================================================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    date_default_timezone_set('Asia/Manila');
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
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        $stmt = $pdo->prepare("
            SELECT
                b.id, b.name, b.address,
                COALESCE(SUM(CASE WHEN DATE(t.created_at) = ?::date THEN t.total ELSE 0 END), 0) AS sales_today,
                COUNT(CASE WHEN DATE(t.created_at) = ?::date THEN 1 END) AS orders_today,
                COALESCE(SUM(CASE WHEN DATE(t.created_at) = ?::date THEN t.total ELSE 0 END), 0) AS sales_yesterday
            FROM branches b
            LEFT JOIN transactions t ON t.branch_id = b.id
                AND t.status = 'completed'
                AND DATE(t.created_at) IN (?::date, ?::date)
            GROUP BY b.id, b.name, b.address
            ORDER BY b.id ASC
        ");
        $stmt->execute([$date, $date, $yesterday, $date, $yesterday]);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'branch') {
        $branchId = (int)($_GET['id'] ?? 0);
        // Include NULL branch_id rows (transactions placed before branch was assigned)
        if ($branchId) {
            $kpi = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS sales_today, COUNT(*) AS orders_today FROM transactions WHERE (branch_id = ? OR branch_id IS NULL) AND DATE(created_at) = ?::date AND status = 'completed'");
            $kpi->execute([$branchId, $date]);
            $kpiRow = $kpi->fetch();
            $txns = $pdo->prepare("SELECT t.id, t.reference_no, t.order_type, t.payment_method, t.total, t.created_at, STRING_AGG(ti.quantity || 'x ' || ti.product_name, ', ' ORDER BY ti.id) AS items_summary FROM transactions t LEFT JOIN transaction_items ti ON ti.transaction_id = t.id WHERE (t.branch_id = ? OR t.branch_id IS NULL) AND DATE(t.created_at) = ?::date AND t.status = 'completed' GROUP BY t.id ORDER BY t.created_at DESC LIMIT 50");
            $txns->execute([$branchId, $date]);
        } else {
            $kpi = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS sales_today, COUNT(*) AS orders_today FROM transactions WHERE DATE(created_at) = ?::date AND status = 'completed'");
            $kpi->execute([$date]);
            $kpiRow = $kpi->fetch();
            $txns = $pdo->prepare("SELECT t.id, t.reference_no, t.order_type, t.payment_method, t.total, t.created_at, STRING_AGG(ti.quantity || 'x ' || ti.product_name, ', ' ORDER BY ti.id) AS items_summary FROM transactions t LEFT JOIN transaction_items ti ON ti.transaction_id = t.id WHERE DATE(t.created_at) = ?::date AND t.status = 'completed' GROUP BY t.id ORDER BY t.created_at DESC LIMIT 50");
            $txns->execute([$date]);
        }
        respond(['success' => true, 'kpi' => $kpiRow, 'transactions' => $txns->fetchAll()]);
    }

    if ($action === 'totals') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) AS total_revenue, COUNT(*) AS total_orders FROM transactions WHERE DATE(created_at) = ?::date AND status = 'completed'");
        $stmt->execute([$date]);
        respond(['success' => true, 'data' => $stmt->fetch()]);
    }

    if ($action === 'monthly_revenue') {
        // Show all-time daily revenue from the very first transaction
        $stmt = $pdo->prepare("
            SELECT
                DATE(created_at) AS day,
                COALESCE(SUM(total), 0) AS revenue
            FROM transactions
            WHERE status = 'completed'
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ");
        $stmt->execute();
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'monthly_categories') {
        // All-time category totals
        $stmt = $pdo->prepare("
            SELECT
                p.category,
                COALESCE(SUM(ti.line_total), 0) AS total_revenue
            FROM transaction_items ti
            JOIN transactions t ON ti.transaction_id = t.id
            JOIN products p ON LOWER(TRIM(p.name)) = LOWER(TRIM(ti.product_name))
            WHERE t.status = 'completed'
            GROUP BY p.category
            ORDER BY total_revenue DESC
        ");
        $stmt->execute();
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'top_moving') {
        // All-time top products by quantity sold
        $stmt = $pdo->prepare("
            SELECT
                ti.product_name AS name,
                SUM(ti.quantity) AS total_qty,
                COALESCE(SUM(ti.line_total), 0) AS total_revenue
            FROM transaction_items ti
            JOIN transactions t ON ti.transaction_id = t.id
            WHERE t.status = 'completed'
            GROUP BY ti.product_name
            ORDER BY total_qty DESC
            LIMIT 10
        ");
        $stmt->execute();
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
    <title>Admin - Complete Dashboard</title>

    <style>
*,:after,:before{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgba(59,130,246,.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: }::backdrop{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-pan-x: ;--tw-pan-y: ;--tw-pinch-zoom: ;--tw-scroll-snap-strictness:proximity;--tw-gradient-from-position: ;--tw-gradient-via-position: ;--tw-gradient-to-position: ;--tw-ordinal: ;--tw-slashed-zero: ;--tw-numeric-figure: ;--tw-numeric-spacing: ;--tw-numeric-fraction: ;--tw-ring-inset: ;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgba(59,130,246,.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000;--tw-blur: ;--tw-brightness: ;--tw-contrast: ;--tw-grayscale: ;--tw-hue-rotate: ;--tw-invert: ;--tw-saturate: ;--tw-sepia: ;--tw-drop-shadow: ;--tw-backdrop-blur: ;--tw-backdrop-brightness: ;--tw-backdrop-contrast: ;--tw-backdrop-grayscale: ;--tw-backdrop-hue-rotate: ;--tw-backdrop-invert: ;--tw-backdrop-opacity: ;--tw-backdrop-saturate: ;--tw-backdrop-sepia: ;--tw-contain-size: ;--tw-contain-layout: ;--tw-contain-paint: ;--tw-contain-style: }/*! tailwindcss v3.4.19 | MIT License | https://tailwindcss.com*/*,:after,:before{box-sizing:border-box;border:0 solid #e5e7eb}:after,:before{--tw-content:""}:host,html{line-height:1.5;-webkit-text-size-adjust:100%;-moz-tab-size:4;-o-tab-size:4;tab-size:4;font-family:ui-sans-serif,system-ui,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;font-feature-settings:normal;font-variation-settings:normal;-webkit-tap-highlight-color:transparent}body{margin:0;line-height:inherit}hr{height:0;color:inherit;border-top-width:1px}abbr:where([title]){-webkit-text-decoration:underline dotted;text-decoration:underline dotted}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}a{color:inherit;text-decoration:inherit}b,strong{font-weight:bolder}code,kbd,pre,samp{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,Courier New,monospace;font-feature-settings:normal;font-variation-settings:normal;font-size:1em}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}table{text-indent:0;border-color:inherit;border-collapse:collapse}button,input,optgroup,select,textarea{font-family:inherit;font-feature-settings:inherit;font-variation-settings:inherit;font-size:100%;font-weight:inherit;line-height:inherit;letter-spacing:inherit;color:inherit;margin:0;padding:0}button,select{text-transform:none}button,input:where([type=button]),input:where([type=reset]),input:where([type=submit]){-webkit-appearance:button;background-color:transparent;background-image:none}:-moz-focusring{outline:auto}:-moz-ui-invalid{box-shadow:none}progress{vertical-align:baseline}::-webkit-inner-spin-button,::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}summary{display:list-item}blockquote,dd,dl,figure,h1,h2,h3,h4,h5,h6,hr,p,pre{margin:0}fieldset{margin:0}fieldset,legend{padding:0}menu,ol,ul{list-style:none;margin:0;padding:0}dialog{padding:0}textarea{resize:vertical}input::-moz-placeholder,textarea::-moz-placeholder{opacity:1;color:#9ca3af}input::placeholder,textarea::placeholder{opacity:1;color:#9ca3af}[role=button],button{cursor:pointer}:disabled{cursor:default}audio,canvas,embed,iframe,img,object,svg,video{display:block;vertical-align:middle}img,video{max-width:100%;height:auto}[hidden]:where(:not([hidden=until-found])){display:none}.fixed{position:fixed}.absolute{position:absolute}.relative{position:relative}.sticky{position:sticky}.inset-0{inset:0}.bottom-0{bottom:0}.left-1{left:.25rem}.top-0{top:0}.top-1{top:.25rem}.z-10{z-index:10}.z-50{z-index:50}.z-\[100\]{z-index:100}.mx-auto{margin-left:auto;margin-right:auto}.mb-0\.5{margin-bottom:.125rem}.mb-1{margin-bottom:.25rem}.mb-10{margin-bottom:2.5rem}.mb-2{margin-bottom:.5rem}.mb-20{margin-bottom:5rem}.mb-3{margin-bottom:.75rem}.mb-4{margin-bottom:1rem}.mb-5{margin-bottom:1.25rem}.mb-6{margin-bottom:1.5rem}.mb-8{margin-bottom:2rem}.mr-1{margin-right:.25rem}.mr-2{margin-right:.5rem}.mt-0\.5{margin-top:.125rem}.mt-1{margin-top:.25rem}.mt-2{margin-top:.5rem}.mt-6{margin-top:1.5rem}.mt-8{margin-top:2rem}.mt-auto{margin-top:auto}.block{display:block}.inline-block{display:inline-block}.flex{display:flex}.inline-flex{display:inline-flex}.table{display:table}.grid{display:grid}.hidden{display:none}.h-10{height:2.5rem}.h-14{height:3.5rem}.h-2{height:.5rem}.h-2\.5{height:.625rem}.h-5{height:1.25rem}.h-7{height:1.75rem}.h-8{height:2rem}.h-\[220px\]{height:220px}.h-\[300px\]{height:300px}.h-fit{height:-moz-fit-content;height:fit-content}.h-full{height:100%}.h-screen{height:100vh}.max-h-\[90vh\]{max-height:90vh}.min-h-screen{min-height:100vh}.w-10{width:2.5rem}.w-14{width:3.5rem}.w-16{width:4rem}.w-2{width:.5rem}.w-2\.5{width:.625rem}.w-32{width:8rem}.w-5{width:1.25rem}.w-8{width:2rem}.w-fit{width:-moz-fit-content;width:fit-content}.w-full{width:100%}.min-w-0{min-width:0}.min-w-max{min-width:-moz-max-content;min-width:max-content}.max-w-3xl{max-width:48rem}.max-w-4xl{max-width:56rem}.max-w-7xl{max-width:80rem}.max-w-md{max-width:28rem}.max-w-xs{max-width:20rem}.flex-1{flex:1 1 0%}.flex-shrink-0,.shrink-0{flex-shrink:0}.translate-x-7{--tw-translate-x:1.75rem}.transform,.translate-x-7{transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.animate-\[fadeIn_0\.4s_ease\]{animation:fadeIn .4s ease}@keyframes pulse{50%{opacity:.5}}.animate-pulse{animation:pulse 2s cubic-bezier(.4,0,.6,1) infinite}.cursor-pointer{cursor:pointer}.grid-cols-1{grid-template-columns:repeat(1,minmax(0,1fr))}.grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.flex-row{flex-direction:row}.flex-col{flex-direction:column}.flex-wrap{flex-wrap:wrap}.items-start{align-items:flex-start}.items-center{align-items:center}.justify-end{justify-content:flex-end}.justify-center{justify-content:center}.justify-between{justify-content:space-between}.justify-around{justify-content:space-around}.gap-1{gap:.25rem}.gap-1\.5{gap:.375rem}.gap-2{gap:.5rem}.gap-3{gap:.75rem}.gap-4{gap:1rem}.gap-6{gap:1.5rem}.gap-8{gap:2rem}.space-y-3>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(.75rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(.75rem*var(--tw-space-y-reverse))}.space-y-4>:not([hidden])~:not([hidden]){--tw-space-y-reverse:0;margin-top:calc(1rem*(1 - var(--tw-space-y-reverse)));margin-bottom:calc(1rem*var(--tw-space-y-reverse))}.divide-y>:not([hidden])~:not([hidden]){--tw-divide-y-reverse:0;border-top-width:calc(1px*(1 - var(--tw-divide-y-reverse)));border-bottom-width:calc(1px*var(--tw-divide-y-reverse))}.divide-slate-100>:not([hidden])~:not([hidden]){--tw-divide-opacity:1;border-color:rgb(241 245 249/var(--tw-divide-opacity,1))}.overflow-hidden{overflow:hidden}.overflow-x-auto{overflow-x:auto}.overflow-y-auto{overflow-y:auto}.truncate{overflow:hidden;text-overflow:ellipsis}.truncate,.whitespace-nowrap{white-space:nowrap}.break-all{word-break:break-all}.rounded{border-radius:.25rem}.rounded-2xl{border-radius:1rem}.rounded-3xl{border-radius:1.5rem}.rounded-\[2rem\]{border-radius:2rem}.rounded-full{border-radius:9999px}.rounded-xl{border-radius:.75rem}.border{border-width:1px}.border-b{border-bottom-width:1px}.border-t{border-top-width:1px}.border-none{border-style:none}.border-amber-100{--tw-border-opacity:1;border-color:rgb(254 243 199/var(--tw-border-opacity,1))}.border-blue-200{--tw-border-opacity:1;border-color:rgb(191 219 254/var(--tw-border-opacity,1))}.border-emerald-100{--tw-border-opacity:1;border-color:rgb(209 250 229/var(--tw-border-opacity,1))}.border-emerald-200{--tw-border-opacity:1;border-color:rgb(167 243 208/var(--tw-border-opacity,1))}.border-indigo-100{--tw-border-opacity:1;border-color:rgb(224 231 255/var(--tw-border-opacity,1))}.border-indigo-300{--tw-border-opacity:1;border-color:rgb(165 180 252/var(--tw-border-opacity,1))}.border-red-100{--tw-border-opacity:1;border-color:rgb(254 226 226/var(--tw-border-opacity,1))}.border-slate-100{--tw-border-opacity:1;border-color:rgb(241 245 249/var(--tw-border-opacity,1))}.border-slate-200{--tw-border-opacity:1;border-color:rgb(226 232 240/var(--tw-border-opacity,1))}.border-white\/10{border-color:hsla(0,0%,100%,.1)}.bg-amber-400{--tw-bg-opacity:1;background-color:rgb(251 191 36/var(--tw-bg-opacity,1))}.bg-amber-50{--tw-bg-opacity:1;background-color:rgb(255 251 235/var(--tw-bg-opacity,1))}.bg-blue-100{--tw-bg-opacity:1;background-color:rgb(219 234 254/var(--tw-bg-opacity,1))}.bg-blue-50{--tw-bg-opacity:1;background-color:rgb(239 246 255/var(--tw-bg-opacity,1))}.bg-emerald-400{--tw-bg-opacity:1;background-color:rgb(52 211 153/var(--tw-bg-opacity,1))}.bg-emerald-400\/30{background-color:rgba(52,211,153,.3)}.bg-emerald-50{--tw-bg-opacity:1;background-color:rgb(236 253 245/var(--tw-bg-opacity,1))}.bg-emerald-500{--tw-bg-opacity:1;background-color:rgb(16 185 129/var(--tw-bg-opacity,1))}.bg-emerald-600{--tw-bg-opacity:1;background-color:rgb(5 150 105/var(--tw-bg-opacity,1))}.bg-green-100{--tw-bg-opacity:1;background-color:rgb(220 252 231/var(--tw-bg-opacity,1))}.bg-indigo-50{--tw-bg-opacity:1;background-color:rgb(238 242 255/var(--tw-bg-opacity,1))}.bg-indigo-500{--tw-bg-opacity:1;background-color:rgb(99 102 241/var(--tw-bg-opacity,1))}.bg-indigo-600{--tw-bg-opacity:1;background-color:rgb(79 70 229/var(--tw-bg-opacity,1))}.bg-red-50{--tw-bg-opacity:1;background-color:rgb(254 242 242/var(--tw-bg-opacity,1))}.bg-rose-100{--tw-bg-opacity:1;background-color:rgb(255 228 230/var(--tw-bg-opacity,1))}.bg-rose-400\/30{background-color:rgba(251,113,133,.3)}.bg-rose-500{--tw-bg-opacity:1;background-color:rgb(244 63 94/var(--tw-bg-opacity,1))}.bg-slate-100{--tw-bg-opacity:1;background-color:rgb(241 245 249/var(--tw-bg-opacity,1))}.bg-slate-300{--tw-bg-opacity:1;background-color:rgb(203 213 225/var(--tw-bg-opacity,1))}.bg-slate-50{--tw-bg-opacity:1;background-color:rgb(248 250 252/var(--tw-bg-opacity,1))}.bg-slate-50\/50{background-color:rgba(248,250,252,.5)}.bg-slate-800{--tw-bg-opacity:1;background-color:rgb(30 41 59/var(--tw-bg-opacity,1))}.bg-slate-900\/40{background-color:rgba(15,23,42,.4)}.bg-transparent{background-color:transparent}.bg-white{--tw-bg-opacity:1;background-color:rgb(255 255 255/var(--tw-bg-opacity,1))}.bg-white\/20{background-color:hsla(0,0%,100%,.2)}.bg-white\/5{background-color:hsla(0,0%,100%,.05)}.bg-gradient-to-r{background-image:linear-gradient(to right,var(--tw-gradient-stops))}.from-slate-900{--tw-gradient-from:#0f172a var(--tw-gradient-from-position);--tw-gradient-to:rgba(15,23,42,0) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-from),var(--tw-gradient-to)}.to-indigo-900{--tw-gradient-to:#312e81 var(--tw-gradient-to-position)}.object-cover{-o-object-fit:cover;object-fit:cover}.p-2{padding:.5rem}.p-3{padding:.75rem}.p-4{padding:1rem}.p-5{padding:1.25rem}.p-6{padding:1.5rem}.p-8{padding:2rem}.px-2{padding-left:.5rem;padding-right:.5rem}.px-2\.5{padding-left:.625rem;padding-right:.625rem}.px-3{padding-left:.75rem;padding-right:.75rem}.px-4{padding-left:1rem;padding-right:1rem}.px-5{padding-left:1.25rem;padding-right:1.25rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.px-8{padding-left:2rem;padding-right:2rem}.py-0\.5{padding-top:.125rem;padding-bottom:.125rem}.py-1{padding-top:.25rem;padding-bottom:.25rem}.py-1\.5{padding-top:.375rem;padding-bottom:.375rem}.py-10{padding-top:2.5rem;padding-bottom:2.5rem}.py-12{padding-top:3rem;padding-bottom:3rem}.py-2\.5{padding-top:.625rem;padding-bottom:.625rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.py-3\.5{padding-top:.875rem;padding-bottom:.875rem}.py-4{padding-top:1rem;padding-bottom:1rem}.py-5{padding-top:1.25rem;padding-bottom:1.25rem}.py-8{padding-top:2rem;padding-bottom:2rem}.pb-2{padding-bottom:.5rem}.pb-4{padding-bottom:1rem}.pr-2{padding-right:.5rem}.pt-4{padding-top:1rem}.text-left{text-align:left}.text-center{text-align:center}.text-right{text-align:right}.font-sans{font-family:ui-sans-serif,system-ui,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji}.text-2xl{font-size:1.5rem;line-height:2rem}.text-3xl{font-size:1.875rem;line-height:2.25rem}.text-4xl{font-size:2.25rem;line-height:2.5rem}.text-5xl{font-size:3rem;line-height:1}.text-\[10px\]{font-size:10px}.text-\[9px\]{font-size:9px}.text-base{font-size:1rem;line-height:1.5rem}.text-lg{font-size:1.125rem;line-height:1.75rem}.text-sm{font-size:.875rem;line-height:1.25rem}.text-xl{font-size:1.25rem;line-height:1.75rem}.text-xs{font-size:.75rem;line-height:1rem}.font-black{font-weight:900}.font-bold{font-weight:700}.font-medium{font-weight:500}.font-semibold{font-weight:600}.uppercase{text-transform:uppercase}.leading-none{line-height:1}.leading-relaxed{line-height:1.625}.leading-tight{line-height:1.25}.tracking-tight{letter-spacing:-.025em}.tracking-wider{letter-spacing:.05em}.tracking-widest{letter-spacing:.1em}.text-amber-400{--tw-text-opacity:1;color:rgb(251 191 36/var(--tw-text-opacity,1))}.text-amber-600{--tw-text-opacity:1;color:rgb(217 119 6/var(--tw-text-opacity,1))}.text-blue-600{--tw-text-opacity:1;color:rgb(37 99 235/var(--tw-text-opacity,1))}.text-blue-700{--tw-text-opacity:1;color:rgb(29 78 216/var(--tw-text-opacity,1))}.text-emerald-200{--tw-text-opacity:1;color:rgb(167 243 208/var(--tw-text-opacity,1))}.text-emerald-400{--tw-text-opacity:1;color:rgb(52 211 153/var(--tw-text-opacity,1))}.text-emerald-500{--tw-text-opacity:1;color:rgb(16 185 129/var(--tw-text-opacity,1))}.text-emerald-600{--tw-text-opacity:1;color:rgb(5 150 105/var(--tw-text-opacity,1))}.text-emerald-700{--tw-text-opacity:1;color:rgb(4 120 87/var(--tw-text-opacity,1))}.text-green-700{--tw-text-opacity:1;color:rgb(21 128 61/var(--tw-text-opacity,1))}.text-indigo-100{--tw-text-opacity:1;color:rgb(224 231 255/var(--tw-text-opacity,1))}.text-indigo-200{--tw-text-opacity:1;color:rgb(199 210 254/var(--tw-text-opacity,1))}.text-indigo-400{--tw-text-opacity:1;color:rgb(129 140 248/var(--tw-text-opacity,1))}.text-indigo-500{--tw-text-opacity:1;color:rgb(99 102 241/var(--tw-text-opacity,1))}.text-indigo-600{--tw-text-opacity:1;color:rgb(79 70 229/var(--tw-text-opacity,1))}.text-orange-500{--tw-text-opacity:1;color:rgb(249 115 22/var(--tw-text-opacity,1))}.text-red-400{--tw-text-opacity:1;color:rgb(248 113 113/var(--tw-text-opacity,1))}.text-red-600{--tw-text-opacity:1;color:rgb(220 38 38/var(--tw-text-opacity,1))}.text-rose-200{--tw-text-opacity:1;color:rgb(254 205 211/var(--tw-text-opacity,1))}.text-rose-400{--tw-text-opacity:1;color:rgb(251 113 133/var(--tw-text-opacity,1))}.text-rose-500{--tw-text-opacity:1;color:rgb(244 63 94/var(--tw-text-opacity,1))}.text-rose-600{--tw-text-opacity:1;color:rgb(225 29 72/var(--tw-text-opacity,1))}.text-sky-600{--tw-text-opacity:1;color:rgb(2 132 199/var(--tw-text-opacity,1))}.text-slate-200{--tw-text-opacity:1;color:rgb(226 232 240/var(--tw-text-opacity,1))}.text-slate-400{--tw-text-opacity:1;color:rgb(148 163 184/var(--tw-text-opacity,1))}.text-slate-500{--tw-text-opacity:1;color:rgb(100 116 139/var(--tw-text-opacity,1))}.text-slate-600{--tw-text-opacity:1;color:rgb(71 85 105/var(--tw-text-opacity,1))}.text-slate-700{--tw-text-opacity:1;color:rgb(51 65 85/var(--tw-text-opacity,1))}.text-slate-800{--tw-text-opacity:1;color:rgb(30 41 59/var(--tw-text-opacity,1))}.text-slate-900{--tw-text-opacity:1;color:rgb(15 23 42/var(--tw-text-opacity,1))}.text-violet-600{--tw-text-opacity:1;color:rgb(124 58 237/var(--tw-text-opacity,1))}.text-white{--tw-text-opacity:1;color:rgb(255 255 255/var(--tw-text-opacity,1))}.shadow-2xl{--tw-shadow:0 25px 50px -12px rgba(0,0,0,.25);--tw-shadow-colored:0 25px 50px -12px var(--tw-shadow-color)}.shadow-2xl,.shadow-\[0_0_15px_rgba\(244\2c 63\2c 94\2c 0\.6\)\]{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-\[0_0_15px_rgba\(244\2c 63\2c 94\2c 0\.6\)\]{--tw-shadow:0 0 15px rgba(244,63,94,.6);--tw-shadow-colored:0 0 15px var(--tw-shadow-color)}.shadow-\[4px_0_24px_rgba\(0\2c 0\2c 0\2c 0\.02\)\]{--tw-shadow:4px 0 24px rgba(0,0,0,.02);--tw-shadow-colored:4px 0 24px var(--tw-shadow-color)}.shadow-\[4px_0_24px_rgba\(0\2c 0\2c 0\2c 0\.02\)\],.shadow-inner{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-inner{--tw-shadow:inset 0 2px 4px 0 rgba(0,0,0,.05);--tw-shadow-colored:inset 0 2px 4px 0 var(--tw-shadow-color)}.shadow-lg{--tw-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1);--tw-shadow-colored:0 10px 15px -3px var(--tw-shadow-color),0 4px 6px -4px var(--tw-shadow-color)}.shadow-lg,.shadow-md{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-md{--tw-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -2px rgba(0,0,0,.1);--tw-shadow-colored:0 4px 6px -1px var(--tw-shadow-color),0 2px 4px -2px var(--tw-shadow-color)}.shadow-sm{--tw-shadow:0 1px 2px 0 rgba(0,0,0,.05);--tw-shadow-colored:0 1px 2px 0 var(--tw-shadow-color)}.shadow-sm,.shadow-xl{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.shadow-xl{--tw-shadow:0 20px 25px -5px rgba(0,0,0,.1),0 8px 10px -6px rgba(0,0,0,.1);--tw-shadow-colored:0 20px 25px -5px var(--tw-shadow-color),0 8px 10px -6px var(--tw-shadow-color)}.shadow-indigo-100{--tw-shadow-color:#e0e7ff;--tw-shadow:var(--tw-shadow-colored)}.shadow-indigo-200{--tw-shadow-color:#c7d2fe;--tw-shadow:var(--tw-shadow-colored)}.outline-none{outline:2px solid transparent;outline-offset:2px}.outline{outline-style:solid}.ring-2{--tw-ring-offset-shadow:var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);--tw-ring-shadow:var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);box-shadow:var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow,0 0 #0000)}.ring-indigo-100{--tw-ring-opacity:1;--tw-ring-color:rgb(224 231 255/var(--tw-ring-opacity,1))}.filter{filter:var(--tw-blur) var(--tw-brightness) var(--tw-contrast) var(--tw-grayscale) var(--tw-hue-rotate) var(--tw-invert) var(--tw-saturate) var(--tw-sepia) var(--tw-drop-shadow)}.backdrop-blur-sm{--tw-backdrop-blur:blur(4px);-webkit-backdrop-filter:var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia);backdrop-filter:var(--tw-backdrop-blur) var(--tw-backdrop-brightness) var(--tw-backdrop-contrast) var(--tw-backdrop-grayscale) var(--tw-backdrop-hue-rotate) var(--tw-backdrop-invert) var(--tw-backdrop-opacity) var(--tw-backdrop-saturate) var(--tw-backdrop-sepia)}.transition{transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,-webkit-backdrop-filter;transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter;transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter,-webkit-backdrop-filter;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.transition-all{transition-property:all;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.transition-colors{transition-property:color,background-color,border-color,text-decoration-color,fill,stroke;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.transition-transform{transition-property:transform;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.duration-300{transition-duration:.3s}.ease-in-out{transition-timing-function:cubic-bezier(.4,0,.2,1)}.selection\:bg-indigo-100 ::-moz-selection{--tw-bg-opacity:1;background-color:rgb(224 231 255/var(--tw-bg-opacity,1))}.selection\:bg-indigo-100 ::selection{--tw-bg-opacity:1;background-color:rgb(224 231 255/var(--tw-bg-opacity,1))}.selection\:text-indigo-900 ::-moz-selection{--tw-text-opacity:1;color:rgb(49 46 129/var(--tw-text-opacity,1))}.selection\:text-indigo-900 ::selection{--tw-text-opacity:1;color:rgb(49 46 129/var(--tw-text-opacity,1))}.selection\:bg-indigo-100::-moz-selection{--tw-bg-opacity:1;background-color:rgb(224 231 255/var(--tw-bg-opacity,1))}.selection\:bg-indigo-100::selection{--tw-bg-opacity:1;background-color:rgb(224 231 255/var(--tw-bg-opacity,1))}.selection\:text-indigo-900::-moz-selection{--tw-text-opacity:1;color:rgb(49 46 129/var(--tw-text-opacity,1))}.selection\:text-indigo-900::selection{--tw-text-opacity:1;color:rgb(49 46 129/var(--tw-text-opacity,1))}.hover\:-translate-y-0\.5:hover{--tw-translate-y:-0.125rem;transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.hover\:border-indigo-300:hover{--tw-border-opacity:1;border-color:rgb(165 180 252/var(--tw-border-opacity,1))}.hover\:bg-indigo-700:hover{--tw-bg-opacity:1;background-color:rgb(67 56 202/var(--tw-bg-opacity,1))}.hover\:bg-rose-50:hover{--tw-bg-opacity:1;background-color:rgb(255 241 242/var(--tw-bg-opacity,1))}.hover\:bg-slate-200:hover{--tw-bg-opacity:1;background-color:rgb(226 232 240/var(--tw-bg-opacity,1))}.hover\:bg-slate-50:hover{--tw-bg-opacity:1;background-color:rgb(248 250 252/var(--tw-bg-opacity,1))}.hover\:bg-slate-900:hover{--tw-bg-opacity:1;background-color:rgb(15 23 42/var(--tw-bg-opacity,1))}.hover\:text-indigo-800:hover{--tw-text-opacity:1;color:rgb(55 48 163/var(--tw-text-opacity,1))}.hover\:text-slate-600:hover{--tw-text-opacity:1;color:rgb(71 85 105/var(--tw-text-opacity,1))}.hover\:shadow-lg:hover{--tw-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -4px rgba(0,0,0,.1);--tw-shadow-colored:0 10px 15px -3px var(--tw-shadow-color),0 4px 6px -4px var(--tw-shadow-color)}.hover\:shadow-lg:hover,.hover\:shadow-xl:hover{box-shadow:var(--tw-ring-offset-shadow,0 0 #0000),var(--tw-ring-shadow,0 0 #0000),var(--tw-shadow)}.hover\:shadow-xl:hover{--tw-shadow:0 20px 25px -5px rgba(0,0,0,.1),0 8px 10px -6px rgba(0,0,0,.1);--tw-shadow-colored:0 20px 25px -5px var(--tw-shadow-color),0 8px 10px -6px var(--tw-shadow-color)}.group:hover .group-hover\:-translate-x-1{--tw-translate-x:-0.25rem;transform:translate(var(--tw-translate-x),var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y))}.group:hover .group-hover\:bg-indigo-50{--tw-bg-opacity:1;background-color:rgb(238 242 255/var(--tw-bg-opacity,1))}.group:hover .group-hover\:text-indigo-300{--tw-text-opacity:1;color:rgb(165 180 252/var(--tw-text-opacity,1))}.group:hover .group-hover\:text-indigo-500{--tw-text-opacity:1;color:rgb(99 102 241/var(--tw-text-opacity,1))}.dark\:divide-slate-700:is(.dark *)>:not([hidden])~:not([hidden]){--tw-divide-opacity:1;border-color:rgb(51 65 85/var(--tw-divide-opacity,1))}.dark\:border-emerald-500\/20:is(.dark *){border-color:rgba(16,185,129,.2)}.dark\:border-indigo-500:is(.dark *){--tw-border-opacity:1;border-color:rgb(99 102 241/var(--tw-border-opacity,1))}.dark\:border-slate-600:is(.dark *){--tw-border-opacity:1;border-color:rgb(71 85 105/var(--tw-border-opacity,1))}.dark\:border-slate-700:is(.dark *){--tw-border-opacity:1;border-color:rgb(51 65 85/var(--tw-border-opacity,1))}.dark\:bg-emerald-500\/10:is(.dark *){background-color:rgba(16,185,129,.1)}.dark\:bg-indigo-500\/10:is(.dark *){background-color:rgba(99,102,241,.1)}.dark\:bg-indigo-600:is(.dark *){--tw-bg-opacity:1;background-color:rgb(79 70 229/var(--tw-bg-opacity,1))}.dark\:bg-slate-700:is(.dark *){--tw-bg-opacity:1;background-color:rgb(51 65 85/var(--tw-bg-opacity,1))}.dark\:bg-slate-700\/50:is(.dark *){background-color:rgba(51,65,85,.5)}.dark\:bg-slate-800:is(.dark *){--tw-bg-opacity:1;background-color:rgb(30 41 59/var(--tw-bg-opacity,1))}.dark\:bg-slate-800\/50:is(.dark *){background-color:rgba(30,41,59,.5)}.dark\:bg-slate-800\/80:is(.dark *){background-color:rgba(30,41,59,.8)}.dark\:bg-slate-900:is(.dark *){--tw-bg-opacity:1;background-color:rgb(15 23 42/var(--tw-bg-opacity,1))}.dark\:text-emerald-400:is(.dark *){--tw-text-opacity:1;color:rgb(52 211 153/var(--tw-text-opacity,1))}.dark\:text-indigo-400:is(.dark *){--tw-text-opacity:1;color:rgb(129 140 248/var(--tw-text-opacity,1))}.dark\:text-rose-400:is(.dark *){--tw-text-opacity:1;color:rgb(251 113 133/var(--tw-text-opacity,1))}.dark\:text-sky-400:is(.dark *){--tw-text-opacity:1;color:rgb(56 189 248/var(--tw-text-opacity,1))}.dark\:text-slate-100:is(.dark *){--tw-text-opacity:1;color:rgb(241 245 249/var(--tw-text-opacity,1))}.dark\:text-slate-200:is(.dark *){--tw-text-opacity:1;color:rgb(226 232 240/var(--tw-text-opacity,1))}.dark\:text-slate-300:is(.dark *){--tw-text-opacity:1;color:rgb(203 213 225/var(--tw-text-opacity,1))}.dark\:text-slate-400:is(.dark *){--tw-text-opacity:1;color:rgb(148 163 184/var(--tw-text-opacity,1))}.dark\:text-slate-500:is(.dark *){--tw-text-opacity:1;color:rgb(100 116 139/var(--tw-text-opacity,1))}.dark\:text-slate-700:is(.dark *){--tw-text-opacity:1;color:rgb(51 65 85/var(--tw-text-opacity,1))}.dark\:text-violet-400:is(.dark *){--tw-text-opacity:1;color:rgb(167 139 250/var(--tw-text-opacity,1))}.dark\:text-white:is(.dark *){--tw-text-opacity:1;color:rgb(255 255 255/var(--tw-text-opacity,1))}.dark\:shadow-indigo-900\/20:is(.dark *){--tw-shadow-color:rgba(49,46,129,.2);--tw-shadow:var(--tw-shadow-colored)}.dark\:ring-indigo-500\/20:is(.dark *){--tw-ring-color:rgba(99,102,241,.2)}.dark\:hover\:border-indigo-500:hover:is(.dark *){--tw-border-opacity:1;border-color:rgb(99 102 241/var(--tw-border-opacity,1))}.dark\:hover\:bg-rose-500\/10:hover:is(.dark *){background-color:rgba(244,63,94,.1)}.dark\:hover\:bg-slate-700\/50:hover:is(.dark *){background-color:rgba(51,65,85,.5)}@media (min-width:640px){.sm\:w-auto{width:auto}.sm\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.sm\:flex-row{flex-direction:row}.sm\:items-center{align-items:center}.sm\:justify-between{justify-content:space-between}}@media (min-width:768px){.md\:relative{position:relative}.md\:mb-0{margin-bottom:0}.md\:block{display:block}.md\:flex{display:flex}.md\:hidden{display:none}.md\:h-screen{height:100vh}.md\:w-24{width:6rem}.md\:w-auto{width:auto}.md\:w-full{width:100%}.md\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.md\:grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.md\:flex-row{flex-direction:row}.md\:flex-col{flex-direction:column}.md\:items-end{align-items:flex-end}.md\:items-center{align-items:center}.md\:justify-between{justify-content:space-between}.md\:gap-4{gap:1rem}.md\:border-r{border-right-width:1px}.md\:border-t-0{border-top-width:0}.md\:p-10{padding:2.5rem}.md\:p-8{padding:2rem}.md\:py-6{padding-top:1.5rem;padding-bottom:1.5rem}.md\:text-2xl{font-size:1.5rem;line-height:2rem}.md\:text-3xl{font-size:1.875rem;line-height:2.25rem}.md\:text-4xl{font-size:2.25rem;line-height:2.5rem}.md\:text-5xl{font-size:3rem;line-height:1}.md\:text-6xl{font-size:3.75rem;line-height:1}.md\:text-xl{font-size:1.25rem;line-height:1.75rem}.md\:text-xs{font-size:.75rem;line-height:1rem}}@media (min-width:1024px){.lg\:col-span-2{grid-column:span 2/span 2}.lg\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.lg\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.lg\:grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.lg\:p-12{padding:3rem}}
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

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
            padding: 7px 15px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
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
            padding: 7px 12px;
            font-size: 12.5px;
            font-weight: 700;
            color: #64748b;
            outline: none;
            cursor: pointer;
            transition: all 0.2s;
            max-width: 128px;
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
        /* On narrow phone screens, shrink the date pill contents a bit more
           so Today / Yesterday / date-picker never get pushed off-screen. */
        @media (max-width: 420px) {
            .date-btn {
                padding: 6px 11px;
                font-size: 11.5px;
            }
            input[type="date"].date-input {
                max-width: 104px;
                padding: 6px 9px;
                font-size: 11.5px;
            }
        }

        /* ============================================================
           SENIOR-FRIENDLY LIGHT MODE
           Only applies when dark mode is OFF (html does not have .dark).
           Bigger text, stronger contrast, clearer borders — easier for
           older clients to read and understand. Dark mode is untouched.
        ============================================================ */
        html:not(.dark) body {
            font-size: 17px;
            line-height: 1.65;
        }
        /* Boost small/secondary text that was too light to read comfortably */
        html:not(.dark) .text-xs {
            font-size: 0.85rem !important;
        }
        html:not(.dark) .text-sm {
            font-size: 0.95rem !important;
        }
        html:not(.dark) .text-slate-400 {
            color: #475569 !important; /* was very light gray, now clearly readable */
        }
        html:not(.dark) .text-slate-500 {
            color: #334155 !important;
        }
        /* Make card and section borders more visible/defined */
        html:not(.dark) .border-slate-100 {
            border-color: #cbd5e1 !important;
        }
        html:not(.dark) .border-slate-200 {
            border-color: #94a3b8 !important;
        }
        html:not(.dark) .divide-slate-100 > :not([hidden]) ~ :not([hidden]) {
            border-color: #cbd5e1 !important;
        }
        /* Larger, bolder buttons and inputs — easier to see and tap
           (but NOT the compact date-filter pill, which needs to stay
           small so Today/Yesterday/date fit on one line without overflow) */
        html:not(.dark) button:not(.date-btn) {
            font-size: 1.02em;
        }
        html:not(.dark) input:not(.date-input),
        html:not(.dark) select,
        html:not(.dark) textarea {
            font-size: 1rem !important;
        }
        /* Headings slightly bolder for clearer visual hierarchy */
        html:not(.dark) h1,
        html:not(.dark) h2,
        html:not(.dark) h3 {
            font-weight: 800;
        }
        /* Stronger focus outline for accessibility */
        html:not(.dark) input:focus,
        html:not(.dark) select:focus,
        html:not(.dark) textarea:focus,
        html:not(.dark) button:focus-visible {
            outline: 2px solid #4f46e5 !important;
            outline-offset: 1px;
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
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 py-2.5 shadow-sm w-full sm:w-auto">
                            <i class="fas fa-calendar-alt text-indigo-400 text-sm shrink-0"></i>
                            <div class="flex flex-wrap items-center gap-1.5">
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
                        <h3 class="text-slate-700 dark:text-slate-300 font-black text-base uppercase tracking-tight mb-6">Money Earned Each Month</h3>
                        <div class="h-[300px] md:h-[320px] w-full"><canvas id="revenueChart"></canvas></div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-700 shadow-sm transition-colors duration-300">
                        <h3 class="text-slate-400 font-bold text-[10px] uppercase tracking-widest mb-6">Sales by Category</h3>
                        <div class="h-[220px] flex items-center justify-center relative">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        <div class="mt-8 space-y-3" id="categoryLegend">
                            <p class="text-xs text-slate-400 text-center">Loading…</p>
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
                                    <th class="px-8 py-5 font-bold">Units Sold</th>
                                    <th class="px-8 py-5 font-bold">Status</th>
                                    <th class="px-8 py-5 font-bold">Performance</th>
                                </tr>
                            </thead>
                            <tbody id="top-moving-tbody" class="text-sm divide-y divide-slate-100 dark:divide-slate-700">
                                <tr><td colspan="4" class="px-8 py-8 text-center text-slate-400">Loading…</td></tr>
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
                        <div class="flex items-center gap-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-4 py-2.5 shadow-sm w-full sm:w-auto">
                            <i class="fas fa-calendar-alt text-indigo-400 text-sm shrink-0"></i>
                            <div class="flex flex-wrap items-center gap-1.5">
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

                <!-- Transactions Table -->
                <div class="mt-8 bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                        <h3 class="font-black text-slate-800 dark:text-white uppercase tracking-tight text-sm">
                            <i class="fas fa-receipt text-indigo-500 mr-2"></i>Transactions
                        </h3>
                        <a href="#" onclick="openHistoryModal(event)" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-3 py-1.5 rounded-full">View All</a>
                    </div>
                    <!-- Desktop table -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-400 text-[10px] uppercase tracking-widest border-b border-slate-100 dark:border-slate-700">
                                <tr>
                                    <th class="py-3 px-4 font-bold">Date & Time</th>
                                    <th class="py-3 px-4 font-bold">Ref #</th>
                                    <th class="py-3 px-4 font-bold">Items</th>
                                    <th class="py-3 px-4 font-bold">Type</th>
                                    <th class="py-3 px-4 font-bold">Total</th>
                                    <th class="py-3 px-4 font-bold">Method</th>
                                </tr>
                            </thead>
                            <tbody id="live-sales-table" class="text-sm divide-y divide-slate-100 dark:divide-slate-700">
                                <tr><td colspan="6" class="text-center py-8 text-slate-400">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile card list -->
                    <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700" id="live-sales-cards">
                        <div class="p-4 text-center text-slate-400 text-sm">Loading…</div>
                    </div>
                </div>
            </main>

            <!-- ════════════════════════════════════
                 LIVE PAGE
            ════════════════════════════════════ -->
            <main id="page-live" class="page-view w-full p-6 md:p-10 lg:p-12">
                <div class="w-full max-w-3xl mx-auto flex flex-col items-center">
                    <!-- Live header -->
                    <div class="flex flex-col items-center mb-10 text-center">
                        <div class="flex items-center gap-4 mb-3">
                            <span class="w-5 h-5 bg-rose-500 rounded-full animate-pulse-red shadow-[0_0_15px_rgba(244,63,94,0.6)]"></span>
                            <h1 class="text-5xl md:text-6xl font-black text-slate-900 dark:text-white tracking-tight uppercase">Live</h1>
                        </div>
                        <p class="text-slate-500 text-lg md:text-xl font-medium">Real-time sales feed: <span id="live-branch-label" class="font-bold text-indigo-600 dark:text-indigo-400">General Luna</span></p>
                        <p id="live-last-updated" class="text-xs text-slate-400 mt-2 font-medium">Refreshing every 10 seconds…</p>
                    </div>

                    <!-- Live KPI strip -->
                    <div class="grid grid-cols-3 gap-4 w-full mb-8">
                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 text-center shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Today's Sales</p>
                          <p id="live-kpi-sales" class="text-sm font-black text-emerald-600 dark:text-emerald-400 break-all leading-tight">₱0.00</p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 text-center shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Orders</p>
                           <p id="live-kpi-orders" class="text-sm font-black text-indigo-600 dark:text-indigo-400 break-all leading-tight">0</p>
                        </div>
                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-4 text-center shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Latest</p>
                           <p id="live-kpi-latest" class="text-sm font-black text-slate-800 dark:text-white break-all leading-tight">—</p>
                        </div>
                    </div>

                    <!-- Transactions feed -->
                    <div class="w-full">
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-4 text-center">Recent Transactions</h3>
                        <div id="live-feed" class="space-y-4 w-full">
                            <div class="text-center py-12 text-slate-400">
                                <i class="fas fa-satellite-dish fa-2x mb-3 animate-pulse"></i>
                                <p class="font-medium">Connecting to live feed…</p>
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
            // Use Philippine time (UTC+8), not browser/UTC time
            return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
        }
        function yesterdayStr() {
            const d = new Date();
            d.setDate(d.getDate() - 1);
            return d.toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
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
                    const rev      = parseFloat(row.sales_today || 0);
                    const ord      = parseInt(row.orders_today || 0);
                    const prevRev  = parseFloat(row.sales_yesterday || 0);
                    totalRevenue += rev;
                    totalOrders  += ord;
                    if (rev > topRevenue) { topRevenue = rev; topBranch = branchInfo[key].name; }

                    // Calculate growth vs yesterday
                    let growth = '—';
                    if (prevRev > 0) {
                        const pct = ((rev - prevRev) / prevRev * 100).toFixed(1);
                        growth = (pct >= 0 ? '+' : '') + pct + '%';
                    } else if (rev > 0) {
                        growth = '+100%';
                    } else {
                        growth = '0%';
                    }

                    allBranchData[key] = {
                        ...branchInfo[key],
                        sales:    '₱ ' + rev.toLocaleString('en-PH', { minimumFractionDigits: 2 }),
                        salesRaw: rev,
                        orders:   ord,
                        growth,
                        prevRev,
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

            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-slate-400 text-sm">Loading transactions…</td></tr>`;

            try {
                const res = await fetch(`admin.php?action=branch&id=${branchId}&date=${selectedDate}`, { credentials: 'same-origin' }).then(r => r.json());

                if (!res || !res.success || !res.transactions.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-slate-400">No transactions recorded for this branch on the selected date.</td></tr>`;
                    const cardsEl = document.getElementById('live-sales-cards');
                    if (cardsEl) cardsEl.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">No transactions for this date.</div>';
                    return;
                }

                const rows = res.transactions.map(t => {
                    const dt   = parseUTC(t.created_at);
                    const time = dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Manila' });
                    const date = dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', timeZone: 'Asia/Manila' });
                    const typeColor   = t.order_type === 'Dine-in' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700';
                    const method      = (t.payment_method || '').toLowerCase();
                    const methodColor = method === 'gcash' ? 'bg-blue-50 text-blue-600 border border-blue-200' :
                                                             'bg-emerald-50 text-emerald-700 border border-emerald-200';
                    return { t, dt, time, date, typeColor, method, methodColor };
                });

                // Desktop table rows
                tbody.innerHTML = rows.map(({ t, date, time, typeColor, methodColor }) =>
                    `<tr class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                        <td class="py-3 px-4 text-sm text-slate-500">${date} ${time}</td>
                        <td class="py-3 px-4 text-sm font-bold text-indigo-600">${t.reference_no}</td>
                        <td class="py-3 px-4 text-sm text-slate-600 dark:text-slate-300 max-w-xs truncate">${t.items_summary || '—'}</td>
                        <td class="py-3 px-4 text-sm"><span class="px-2 py-1 rounded-full text-xs font-semibold ${typeColor}">${t.order_type}</span></td>
                        <td class="py-3 px-4 text-sm font-bold text-slate-800 dark:text-slate-100">₱${parseFloat(t.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                        <td class="py-3 px-4 text-sm"><span class="px-2 py-1 rounded-full text-xs font-bold ${methodColor}">${(t.payment_method || '—').toUpperCase()}</span></td>
                    </tr>`
                ).join('');

                // Mobile cards
                const cards = document.getElementById('live-sales-cards');
                if (cards) {
                    cards.innerHTML = rows.map(({ t, date, time, typeColor, methodColor }) =>
                        `<div class="p-4 flex items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="font-bold text-indigo-600 text-sm">${t.reference_no}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ${typeColor}">${t.order_type}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${methodColor}">${(t.payment_method || '—').toUpperCase()}</span>
                                </div>
                                <p class="text-xs text-slate-500 truncate">${t.items_summary || '—'}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">${date} · ${time}</p>
                            </div>
                            <p class="font-black text-slate-800 dark:text-white text-base shrink-0">₱${parseFloat(t.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                        </div>`
                    ).join('');
                }
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
                    const dt        = parseUTC(t.created_at);
                    const time      = dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Manila' });
                    const dateLabel = dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', timeZone: 'Asia/Manila' });
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
            const growthEl = document.getElementById('home-growth');
            growthEl.innerText = b.growth;
            growthEl.className = 'px-2.5 py-1 rounded-full font-bold ';
            if (b.growth && b.growth.startsWith('+') && b.growth !== '+0%') {
                growthEl.className += 'bg-emerald-400/30 text-emerald-200';
            } else if (b.growth && b.growth.startsWith('-')) {
                growthEl.className += 'bg-rose-400/30 text-rose-200';
            } else {
                growthEl.className += 'bg-white/20 text-indigo-100';
            }
            document.getElementById('sales-branch-status').innerText = b.name;
            document.getElementById('live-branch-label').innerText   = b.name;
            // Restart live feed when branch changes
            if (liveInterval) { lastTxnId = null; loadLiveFeed(); }
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
            if (pageId === 'stats' && !chartsRendered) { initCharts(); loadTopMoving(); chartsRendered = true; }
            if (pageId === 'live') { startLiveFeed(); } else { stopLiveFeed(); }
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
        async function logoutSession() {
            if (confirm('Are you sure you want to log out of the system?')) {
                try {
                    await api.auth.logout();
                } catch (err) {
                    // even if the request fails, still proceed to the login page
                }
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
                    const dt = parseUTC(t.created_at);
                    return [
                        b.name,
                        dt.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' }),
                        dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Manila' }),
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

        // ── Live Feed ──────────────────────────────────────────
        let liveInterval = null;
        let lastTxnId    = null;

        async function loadLiveFeed() {
            const branchId = branchIdMap[currentBranch];
            const today    = todayStr();

            try {
                const res = await fetch(`admin.php?action=branch&id=${branchId}&date=${today}`, { credentials: 'same-origin' }).then(r => r.json());

                if (!res || !res.success) return;

                // Update KPIs
                const kpi = res.kpi || {};
                document.getElementById('live-kpi-sales').textContent =
                    '₱' + parseFloat(kpi.sales_today || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                document.getElementById('live-kpi-orders').textContent = kpi.orders_today || 0;

                const txns = res.transactions || [];
                const feed = document.getElementById('live-feed');

                if (!txns.length) {
                    feed.innerHTML = `<div class="text-center py-12 text-slate-400">
                        <i class="fas fa-clock fa-2x mb-3"></i>
                        <p class="font-medium">No transactions yet today for ${branchInfo[currentBranch].name}.</p>
                    </div>`;
                    document.getElementById('live-kpi-latest').textContent = '—';
                    return;
                }

                // Show latest time
                const latest = parseUTC(txns[0].created_at);
                document.getElementById('live-kpi-latest').textContent =
                    latest.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Manila' });

                // Only re-render if something changed
                if (txns[0].id === lastTxnId && feed.children.length > 1) return;
                lastTxnId = txns[0].id;

                feed.innerHTML = txns.slice(0, 10).map((t, i) => {
                    const dt     = parseUTC(t.created_at);
                    const time   = dt.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Manila' });
                    const isNew  = i === 0;
                    return `<div class="bg-white dark:bg-slate-800 p-6 md:p-8 rounded-[2rem] border ${isNew ? 'border-indigo-300 dark:border-indigo-500 shadow-lg shadow-indigo-100 dark:shadow-indigo-900/20' : 'border-slate-200 dark:border-slate-700 shadow-sm'}
                        flex flex-col md:flex-row justify-between items-center gap-4 transition-all ${isNew ? 'animate-[fadeIn_0.4s_ease]' : ''}">
                        <div class="flex items-center gap-4 w-full md:w-auto">
                            <div class="w-14 h-14 ${isNew ? 'bg-indigo-600' : 'bg-indigo-50 dark:bg-indigo-500/10'} rounded-xl flex items-center justify-center ${isNew ? 'text-white' : 'text-indigo-500'} shrink-0">
                                <i class="fas fa-shopping-cart fa-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <p class="font-black text-slate-800 dark:text-white text-base">${branchInfo[currentBranch].name}</p>
                                    ${isNew ? '<span class="text-[9px] font-bold bg-rose-100 text-rose-600 px-2 py-0.5 rounded-full uppercase">New</span>' : ''}
                                </div>
                                <p class="text-xs text-slate-400 font-medium truncate">${t.items_summary || 'Order #' + t.reference_no}</p>
                                <p class="text-xs text-indigo-500 font-bold mt-0.5">${time} · ${t.order_type} · ${t.payment_method}</p>
                            </div>
                        </div>
                        <div class="bg-emerald-50 dark:bg-emerald-500/10 px-5 py-3 rounded-xl border border-emerald-100 dark:border-emerald-500/20 shrink-0">
                            <p class="font-black text-emerald-600 dark:text-emerald-400 text-xl md:text-2xl whitespace-nowrap">
                                + ₱${parseFloat(t.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                            </p>
                        </div>
                    </div>`;
                }).join('');

                // Update last refreshed time
                const now = new Date();
                document.getElementById('live-last-updated').textContent =
                    'Last updated: ' + now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            } catch (e) {
                console.error('Live feed error:', e);
            }
        }

        function startLiveFeed() {
            lastTxnId = null;
            loadLiveFeed();
            if (liveInterval) clearInterval(liveInterval);
            liveInterval = setInterval(loadLiveFeed, 10000); // refresh every 10s
        }

        function stopLiveFeed() {
            if (liveInterval) { clearInterval(liveInterval); liveInterval = null; }
        }

        // ── Charts ─────────────────────────────────────────────
        async function loadTopMoving() {
            const tbody = document.getElementById('top-moving-tbody');
            try {
                const res = await fetch('admin.php?action=top_moving', { credentials: 'same-origin' }).then(r => r.json());
                if (!res.success || !res.data.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="px-8 py-8 text-center text-slate-400">No sales data yet.</td></tr>';
                    return;
                }
                const maxQty = Math.max(...res.data.map(p => parseInt(p.total_qty)));
                tbody.innerHTML = res.data.map(p => {
                    const qty = parseInt(p.total_qty);
                    const pct = Math.round((qty / maxQty) * 100);
                    let status, statusColor, barColor;
                    if (pct >= 75) {
                        status = 'High Demand'; statusColor = 'text-emerald-400'; barColor = 'bg-indigo-500';
                    } else if (pct >= 40) {
                        status = 'Steady';      statusColor = 'text-emerald-400'; barColor = 'bg-emerald-500';
                    } else {
                        status = 'Low';         statusColor = 'text-amber-400';   barColor = 'bg-amber-400';
                    }
                    return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-8 py-5 font-bold text-slate-800 dark:text-slate-200">${p.name}</td>
                        <td class="px-8 py-5 text-slate-500 dark:text-slate-400">${qty.toLocaleString()}</td>
                        <td class="px-8 py-5 font-semibold ${statusColor}">${status}</td>
                        <td class="px-8 py-5">
                            <div class="w-32 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full ${barColor} rounded-full" style="width:${pct}%"></div>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            } catch (_) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-8 py-8 text-center text-red-400">Failed to load.</td></tr>';
            }
        }

        async function initCharts() {
            Chart.defaults.color       = '#64748b';
            Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";

            // ── Revenue: fetch all-time data, then group into simple monthly totals ──
            let revLabels = [];
            let revData   = [];
            try {
                const trend = await fetch('admin.php?action=monthly_revenue', { credentials: 'same-origin' }).then(r => r.json());
                if (trend && trend.success && trend.data.length) {
                    // Group daily rows into month buckets (e.g. "May 2026")
                    const monthTotals = {};
                    trend.data.forEach(d => {
                        const dt  = new Date(d.day);
                        const key = dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0');
                        monthTotals[key] = (monthTotals[key] || 0) + parseFloat(d.revenue);
                    });
                    const sortedKeys = Object.keys(monthTotals).sort();
                    revLabels = sortedKeys.map(key => {
                        const [y, m] = key.split('-');
                        return new Date(y, m - 1, 1).toLocaleDateString('en-PH', { month: 'short', year: 'numeric' });
                    });
                    revData = sortedKeys.map(key => monthTotals[key]);
                }
            } catch (_) {}

            if (!revLabels.length) {
                revLabels = ['No Data'];
                revData   = [0];
            }

            // Plugin that writes the exact peso amount directly above each bar,
            // so nothing needs to be tapped or hovered to see the value.
            const barValueLabelPlugin = {
                id: 'barValueLabel',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = 'bold 14px Segoe UI, system-ui, sans-serif';
                    ctx.fillStyle = '#334155';
                    ctx.textAlign = 'center';
                    chart.data.datasets[0].data.forEach((value, i) => {
                        const meta = chart.getDatasetMeta(0);
                        const bar  = meta.data[i];
                        if (!bar) return;
                        const label = '₱' + (value >= 1000 ? (value / 1000).toFixed(1) + 'k' : value.toFixed(0));
                        ctx.fillText(label, bar.x, bar.y - 10);
                    });
                    ctx.restore();
                },
            };

            new Chart(document.getElementById('revenueChart'), {
                type: 'bar',
                data: {
                    labels:   revLabels,
                    datasets: [{
                        data:            revData,
                        backgroundColor: '#4f46e5',
                        borderRadius:    8,
                        maxBarThickness: 56,
                    }],
                },
                plugins: [barValueLabelPlugin],
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { top: 28 } },
                    plugins: {
                        legend:  { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b', padding: 14, cornerRadius: 10, displayColors: false,
                            titleFont: { size: 15, weight: 'bold' },
                            bodyFont:  { size: 15 },
                            callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString('en-PH', { minimumFractionDigits: 2 }) },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false }, border: { display: false },
                            ticks: { font: { size: 15, weight: '700' }, color: '#334155' },
                        },
                        y: {
                            display: false,
                            beginAtZero: true,
                        },
                    },
                },
            });

            // ── Sales by Category: fetch this month's real category totals ──
            const ADMIN_CATEGORIES = [
                { key: 'Breakfast',             label: 'Breakfast',         color: '#5a67d8' },
                { key: 'Merienda',              label: 'Merienda',          color: '#4fd1c5' },
                { key: 'Burgers And Sandwiches',label: 'Burgers & Sand.',   color: '#f6ad55' },
                { key: 'Rice Meal',             label: 'Rice Meal',         color: '#fc8181' },
                { key: 'Native',                label: 'Native',            color: '#68d391' },
                { key: 'Dessert',               label: 'Dessert',           color: '#f687b3' },
                { key: 'Drinks',                label: 'Drinks',            color: '#76e4f7' },
            ];

            let catLabels = ADMIN_CATEGORIES.map(c => c.label);
            let catData   = ADMIN_CATEGORIES.map(() => 0);
            let catColors = ADMIN_CATEGORIES.map(c => c.color);
            let hasRealCatData = false;
            let catRevenues    = {};

            try {
                const catRes = await fetch('admin.php?action=monthly_categories', { credentials: 'same-origin' }).then(r => r.json());
                if (catRes && catRes.success && catRes.data.length) {
                    catRes.data.forEach(row => { catRevenues[row.category] = parseFloat(row.total_revenue); });
                    catData = ADMIN_CATEGORIES.map(c => catRevenues[c.key] || 0);
                    hasRealCatData = catData.some(v => v > 0);
                }
            } catch (_) {}

            if (!hasRealCatData) {
                catData   = ADMIN_CATEGORIES.map(() => 1);
                catColors = ADMIN_CATEGORIES.map(() => '#e2e8f0');
            }

            // ── Render dynamic legend ──────────────────────────
            const legend = document.getElementById('categoryLegend');
            if (hasRealCatData) {
                const activeCategories = ADMIN_CATEGORIES.filter(c => (catRevenues[c.key] || 0) > 0);
                legend.innerHTML = activeCategories.map(c => `
                    <div class="flex justify-between items-center text-sm">
                        <span class="flex items-center gap-3 text-slate-600 dark:text-slate-300 font-medium">
                            <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:${c.color};"></div>${c.label}
                        </span>
                        <span class="font-bold text-slate-900 dark:text-white">
                            ₱${(catRevenues[c.key] || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                        </span>
                    </div>`).join('');
            } else {
                legend.innerHTML = '<p class="text-xs text-slate-400 text-center">No sales data this month yet.</p>';
            }

            new Chart(document.getElementById('categoryChart'), {
                type: 'doughnut',
                data: {
                    labels:   catLabels,
                    datasets: [{ data: catData, backgroundColor: catColors, borderWidth: 0, hoverOffset: 8 }],
                },
                options: {
                    cutout:  '75%',
                    plugins: {
                        legend:  { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b', padding: 12, cornerRadius: 8,
                            callbacks: {
                                label: ctx => hasRealCatData
                                    ? ctx.label + ': ₱' + ctx.parsed.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                                    : 'No data this month yet',
                            },
                        },
                    },
                },
            });
        }
    </script>

    <!-- PWA Registration -->
    <script src="pwa.js"></script>
</body>
</html>
