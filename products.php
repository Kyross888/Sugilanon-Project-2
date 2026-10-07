<?php
// ============================================================
//  api/products.php  —  Full CRUD for inventory
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';
require_once 'branch_scope.php';

$action = $_GET['action'] ?? 'list';

// Loads a product's branch and blocks staff from other branches
function assertProductAccess(PDO $pdo, int $id): void {
    $q = $pdo->prepare("SELECT branch_id FROM products WHERE id = ?");
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) respond(['success' => false, 'error' => 'Product not found.'], 404);
    assertBranchAccess($row['branch_id']);
}


// ── LIST ─────────────────────────────────────────────────────
if ($action === 'list') {
    $cat      = $_GET['category'] ?? '';
    $search   = $_GET['search']   ?? '';
    requireAuth();
    // Staff: always their own branch. Admin: all, or one branch via ?branch_id=
    $branchId = scopedBranchId();

    $sql    = "SELECT * FROM products WHERE is_active = TRUE";
    $params = [];

    if ($cat) {
        $sql .= " AND category = ?";
        $params[] = $cat;
    }
    if ($search) {
        $sql .= " AND name LIKE ?";
        $params[] = "%{$search}%";
    }
    if ($branchId !== '' && $branchId !== null) {
        $sql .= " AND branch_id = ?";
        $params[] = (int)$branchId;
    }

    $sql .= " ORDER BY CASE category
        WHEN 'Breakfast' THEN 1
        WHEN 'Merienda' THEN 2
        WHEN 'Burgers And Sandwiches' THEN 3
        WHEN 'Rice Meal' THEN 4
        WHEN 'Native' THEN 5
        WHEN 'Dessert' THEN 6
        WHEN 'Drinks' THEN 7
        ELSE 8 END, name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── GET SINGLE ───────────────────────────────────────────────
if ($action === 'get') {
    requireAuth();
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) respond(['success' => false, 'error' => 'Product not found.'], 404);
    assertBranchAccess($product['branch_id']);
    respond(['success' => true, 'data' => $product]);
}

// ── CREATE ───────────────────────────────────────────────────
if ($action === 'create') {
    requireAuth();

    $isMultipart = !empty($_POST);

    if ($isMultipart) {
        $name      = trim($_POST['name']      ?? '');
        $category  = trim($_POST['category']  ?? 'Breakfast');
        $price     = (float)($_POST['price']  ?? 0);
        $stock     = (int)  ($_POST['stock']  ?? 0);
        $branch_id = $_POST['branch_id']      ?? null;
        $icon      = $_POST['icon']           ?? null;
    } else {
        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $name      = trim($body['name']      ?? '');
        $category  = trim($body['category']  ?? 'Breakfast');
        $price     = (float)($body['price']  ?? 0);
        $stock     = (int)  ($body['stock']  ?? 0);
        $branch_id = $body['branch_id']      ?? null;
        $icon      = $body['icon']           ?? null;
    }

    if (!$name || $price < 0) {
        respond(['success' => false, 'error' => 'Name and a valid price are required.'], 400);
    }

    // Staff can only add products to their own branch.
    // Admin picks a branch; leaving it blank adds the product to EVERY branch.
    $authUser = $_SESSION['user'];
    if (!isAdminUser($authUser)) {
        if (empty($authUser['branch_id'])) {
            respond(['success' => false, 'error' => 'Your account has no branch assigned.'], 403);
        }
        $branch_id = (int)$authUser['branch_id'];
    }

    // Handle optional image upload — store as base64 data URI (works on Railway)
    $image_path = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            respond(['success' => false, 'error' => 'Invalid image type.'], 400);
        }
        $mimeMap  = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
        $mime     = $mimeMap[$ext] ?? 'image/jpeg';
        $data     = file_get_contents($_FILES['image']['tmp_name']);
        $image_path = 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    try {
        if ($branch_id) {
            $targets = [(int)$branch_id];
        } else {
            $targets = $pdo->query("SELECT id FROM branches ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO products (name, category, price, stock, image_path, icon, branch_id)
             VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $firstId = null;
        foreach ($targets as $bid) {
            $stmt->execute([$name, $category, $price, $stock, $image_path, $icon, $bid]);
            $row = $stmt->fetch();
            if ($firstId === null) $firstId = $row['id'];
        }
        respond(['success' => true, 'id' => $firstId, 'message' => 'Product added.']);
    } catch (PDOException $e) {
        respond(['success' => false, 'error' => 'Insert failed: ' . $e->getMessage()], 500);
    }
}

// ── UPDATE ───────────────────────────────────────────────────
if ($action === 'update') {
    requireAuth();
    $id = (int)($_GET['id'] ?? 0);
    assertProductAccess($pdo, $id);

    // Support FormData (multipart) for image uploads during update
    $isMultipart = !empty($_POST);

    if ($isMultipart) {
        $fields = ['name', 'category', 'price', 'stock', 'icon', 'branch_id', 'is_active'];
        $sets   = [];
        $params = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $sets[]   = "$field = ?";
                $params[] = $_POST[$field];
            }
        }
    } else {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $allowed = ['name', 'category', 'price', 'stock', 'image_path', 'icon', 'branch_id', 'is_active'];
        $sets    = [];
        $params  = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]   = "$field = ?";
                $params[] = $body[$field];
            }
        }
    }

    // Staff can't move a product to another branch
    if (!isAdminUser()) {
        foreach ($sets as $i => $set) {
            if (strpos($set, 'branch_id') === 0) { unset($sets[$i], $params[$i]); }
        }
        $sets = array_values($sets); $params = array_values($params);
    }

    // Handle image upload during update — store as base64 data URI (works on Railway)
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed)) {
            $mimeMap  = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
            $mime     = $mimeMap[$ext] ?? 'image/jpeg';
            $data     = file_get_contents($_FILES['image']['tmp_name']);
            $sets[]   = "image_path = ?";
            $params[] = 'data:' . $mime . ';base64,' . base64_encode($data);
        }
    }

    if (empty($sets)) respond(['success' => false, 'error' => 'Nothing to update.'], 400);

    $params[] = $id;
    $pdo->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

    respond(['success' => true, 'message' => 'Product updated.']);
}

// ── ADJUST STOCK ─────────────────────────────────────────────
if ($action === 'adjust_stock') {
    requireAuth();
    $id    = (int)($_GET['id'] ?? 0);
    assertProductAccess($pdo, $id);
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $delta = (int)($body['delta'] ?? 0);
    $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock + ?) WHERE id = ?")->execute([$delta, $id]);
    respond(['success' => true, 'message' => 'Stock adjusted.']);
}

// ── DELETE (soft) ────────────────────────────────────────────
if ($action === 'delete') {
    requireAuth();
    $id = (int)($_GET['id'] ?? 0);
    assertProductAccess($pdo, $id);
    $pdo->prepare("UPDATE products SET is_active = FALSE WHERE id = ?")->execute([$id]);
    respond(['success' => true, 'message' => 'Product removed.']);
}

respond(['success' => false, 'error' => 'Unknown action.'], 400);
