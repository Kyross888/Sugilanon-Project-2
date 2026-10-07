<?php
// ============================================================
//  api/orders.php  — Place, list, get, void orders
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

$action = $_GET['action'] ?? '';

// ── PLACE ORDER ──────────────────────────────────────────────
if ($action === 'place') {
    $user = requireAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $items          = $body['items']           ?? [];
    $order_type     = $body['order_type']      ?? 'Dine-in';
    $payment_method = $body['payment_method']  ?? 'Cash';
    $subtotal       = (float)($body['subtotal']       ?? 0);
    $discount       = (float)($body['discount']       ?? 0);
    $coupon_discount= (float)($body['coupon_discount'] ?? 0);
    $total          = (float)($body['total']          ?? 0);
    $customer_id    = $body['customer_id']     ?? null;
    $gcash_reference= trim($body['gcash_reference'] ?? '');

    if (empty($items)) respond(['success' => false, 'error' => 'No items in order.'], 400);

    if ($payment_method === 'GCash' && $gcash_reference !== '') {
        // Use the real GCash reference number the cashier read off the
        // customer's phone, so it matches what the customer sees.
        $ref = $gcash_reference;
    } else {
        // Non-GCash orders (or GCash without a captured ref, e.g. legacy
        // clients) fall back to an internally generated reference number.
        $ref = 'REF-' . strtoupper(substr(uniqid(), -6));
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO transactions
             (reference_no, branch_id, user_id, order_type, payment_method,
              subtotal, discount, coupon_discount, total, customer_id, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,'completed') RETURNING id"
        );
        $stmt->execute([
            $ref,
            $user['branch_id'] ?? null,
            $user['id'],
            $order_type,
            $payment_method,
            $subtotal,
            $discount,
            $coupon_discount,
            $total,
            $customer_id ?: null,
        ]);
        $txnRow = $stmt->fetch(); $txnId = $txnRow['id'];

        foreach ($items as $item) {
            $qty       = (int)($item['quantity'] ?? 1);
            $unitPrice = (float)($item['price'] ?? 0);
            $lineTotal = $qty * $unitPrice;

            $pdo->prepare(
                "INSERT INTO transaction_items
                 (transaction_id, product_id, product_name, unit_price, quantity, line_total)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $txnId,
                $item['product_id'] ?? null,
                $item['name']       ?? '',
                $unitPrice,
                $qty,
                $lineTotal,
            ]);

            // Deduct stock
            if (!empty($item['product_id'])) {
                $pdo->prepare(
                    "UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?"
                )->execute([$qty, $item['product_id']]);
            }
        }

        $pdo->commit();
        respond(['success' => true, 'reference_no' => $ref, 'transaction_id' => $txnId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Order failed: ' . $e->getMessage()], 500);
    }
}

// ── LIST ORDERS ──────────────────────────────────────────────
if ($action === 'list') {
    requireAuth();
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset= ($page - 1) * $limit;

    $stmt = $pdo->prepare(
        "SELECT * FROM transactions
         WHERE status = 'completed'
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$limit, $offset]);
    $orders = $stmt->fetchAll();

    if (!empty($orders)) {
        $ids = implode(',', array_map('intval', array_column($orders, 'id')));
        $itemsStmt = $pdo->query(
            "SELECT * FROM transaction_items WHERE transaction_id IN ($ids)"
        );
        $allItems = $itemsStmt->fetchAll();

        // Group items by transaction_id
        $itemsMap = [];
        foreach ($allItems as $item) {
            $itemsMap[$item['transaction_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsMap[$order['id']] ?? [];
        }
        unset($order);
    }

    respond(['success' => true, 'data' => $orders]);
}

// ── GET SINGLE ORDER (with items) ────────────────────────────
if ($action === 'get') {
    requireAuth();
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$id]);
    $txn  = $stmt->fetch();
    if (!$txn) respond(['success' => false, 'error' => 'Order not found.'], 404);

    $items = $pdo->prepare(
        "SELECT * FROM transaction_items WHERE transaction_id = ?"
    );
    $items->execute([$id]);
    $txn['items'] = $items->fetchAll();

    respond(['success' => true, 'data' => $txn]);
}

// ── VOID ORDER ───────────────────────────────────────────────
if ($action === 'void') {
    requireAuth();
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("UPDATE transactions SET status = 'voided' WHERE id = ?")->execute([$id]);
    respond(['success' => true]);
}

// ── DELETE ORDER (permanent) ─────────────────────────────────
// Removes the transaction and its items from the database, so it
// disappears everywhere (Customers, Sales Report, Dashboard,
// Analytics, Admin). Stock deducted by the sale is put back.
if ($action === 'delete') {
    requireAuth();
    // To limit deleting to admins only, uncomment the next line:
    // if (($_SESSION['user']['role'] ?? '') !== 'admin') respond(['success' => false, 'error' => 'Admins only.'], 403);

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) respond(['success' => false, 'error' => 'Invalid order id.'], 400);

    $pdo->beginTransaction();
    try {
        $chk = $pdo->prepare("SELECT status FROM transactions WHERE id = ?");
        $chk->execute([$id]);
        $txn = $chk->fetch();
        if (!$txn) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'Order not found.'], 404);
        }

        // Put stock back (only if the order was still completed, i.e. stock was still deducted)
        if ($txn['status'] === 'completed') {
            $pdo->prepare(
                "UPDATE products p SET stock = p.stock + ti.quantity
                 FROM transaction_items ti
                 WHERE ti.transaction_id = ? AND ti.product_id = p.id"
            )->execute([$id]);
        }

        // transaction_items are removed automatically (ON DELETE CASCADE),
        // but delete them explicitly too in case the constraint is missing.
        $pdo->prepare("DELETE FROM transaction_items WHERE transaction_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);

        $pdo->commit();
        respond(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()], 500);
    }
}

respond(['success' => false, 'error' => 'Unknown action.'], 400);
