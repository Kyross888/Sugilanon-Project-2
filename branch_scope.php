<?php
// ============================================================
//  branch_scope.php  —  Keeps every branch's data separate
//  Include AFTER db.php:   require_once 'branch_scope.php';
//
//  Staff accounts  → can only ever see / change their OWN branch
//                    (whatever the browser sends is ignored).
//  Admin accounts  → see all branches, or one via ?branch_id=
// ============================================================

function isAdminUser(?array $u = null): bool {
    $u = $u ?? ($_SESSION['user'] ?? null);
    return $u && (($u['role'] ?? '') === 'admin');
}

/**
 * Branch id to filter by ('' = no filter, i.e. admin looking at everything).
 * Staff with no branch assigned get -1 so they match nothing.
 */
function scopedBranchId() {
    $u = $_SESSION['user'] ?? null;
    if (!$u) return $_GET['branch_id'] ?? '';
    if (isAdminUser($u)) return $_GET['branch_id'] ?? '';
    return !empty($u['branch_id']) ? (int)$u['branch_id'] : -1;
}

/** Stops a staff account from touching a row that belongs to another branch. */
function assertBranchAccess($rowBranchId): void {
    $u = $_SESSION['user'] ?? null;
    if (!$u || isAdminUser($u)) return;
    $mine = !empty($u['branch_id']) ? (int)$u['branch_id'] : 0;
    if ((int)$rowBranchId !== $mine) {
        respond(['success' => false, 'error' => 'This record belongs to another branch.'], 403);
    }
}
