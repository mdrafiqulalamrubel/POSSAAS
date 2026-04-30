<?php
/**
 * api-csv-import.php
 * Handles bulk CSV import for Items and Clients/Customers.
 *
 * POST params:
 *   type     = 'items' | 'clients'
 *   csv_file = uploaded .csv file
 *
 * Items CSV columns (row 1 = header):
 *   name, sku, brand, category, unit, unit_price, quantity, reorder_level
 *
 * Clients CSV columns (row 1 = header):
 *   name, phone, email, address, opening_balance
 */

require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

function csv_error(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$type = trim($_POST['type'] ?? '');
if (!in_array($type, ['items', 'clients'])) {
    csv_error('Invalid import type. Must be "items" or "clients".');
}

// ── File validation ───────────────────────────────────────────
if (empty($_FILES['csv_file']['tmp_name'])) {
    csv_error('No file uploaded.');
}

$file = $_FILES['csv_file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    csv_error('Only .csv files are accepted.');
}
if ($file['size'] > 5 * 1024 * 1024) {
    csv_error('File too large. Maximum size is 5MB.');
}

// ── Parse CSV ─────────────────────────────────────────────────
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    csv_error('Could not read the uploaded file.');
}

// Read header row
$headers = fgetcsv($handle);
if (!$headers) {
    fclose($handle);
    csv_error('CSV file is empty or could not be read.');
}

// Normalize headers: trim + lowercase
$headers = array_map(fn($h) => strtolower(trim($h)), $headers);

$tid = tid();
$bid = brid();
$uid = uid();

$imported = 0;
$skipped  = 0;
$errors   = [];
$row_num  = 1; // header was row 1

// ── ITEMS IMPORT ──────────────────────────────────────────────
if ($type === 'items') {
    $required = ['name'];
    foreach ($required as $col) {
        if (!in_array($col, $headers)) {
            fclose($handle);
            csv_error("Missing required column: \"$col\". Headers found: " . implode(', ', $headers));
        }
    }

    $col = array_flip($headers); // column name → index

    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;
        if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue; // skip blank rows

        $name     = trim($row[$col['name']]          ?? '');
        $sku      = trim($row[$col['sku']]           ?? '');
        $brand    = trim($row[$col['brand']]         ?? '');
        $category = trim($row[$col['category']]      ?? '');
        $unit     = trim($row[$col['unit']]          ?? 'pcs') ?: 'pcs';
        $price    = (float)($row[$col['unit_price']] ?? 0);
        $qty      = (float)($row[$col['quantity']]   ?? 0);
        $reorder  = (float)($row[$col['reorder_level']] ?? 0);

        if (!$name) {
            $errors[] = "Row $row_num: skipped — name is empty.";
            $skipped++;
            continue;
        }

        // If SKU given, check for duplicate
        if ($sku) {
            $chk = db()->prepare('SELECT id FROM items WHERE tenant_id=? AND branch_id=? AND sku=? LIMIT 1');
            $chk->execute([$tid, $bid, $sku]);
            if ($chk->fetchColumn()) {
                $errors[] = "Row $row_num: skipped \"$name\" — SKU \"$sku\" already exists.";
                $skipped++;
                continue;
            }
        }

        try {
            db()->prepare('INSERT INTO items
                (tenant_id, branch_id, name, sku, brand, category, unit, quantity, unit_price, reorder_level, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
               ->execute([$tid, $bid, $name, $sku, $brand, $category, $unit, $qty, $price, $reorder, $uid]);
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $row_num: DB error — " . $e->getMessage();
            $skipped++;
        }
    }
}

// ── CLIENTS IMPORT ────────────────────────────────────────────
if ($type === 'clients') {
    $required = ['name'];
    foreach ($required as $col) {
        if (!in_array($col, $headers)) {
            fclose($handle);
            csv_error("Missing required column: \"$col\". Headers found: " . implode(', ', $headers));
        }
    }

    $col = array_flip($headers);

    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;
        if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;

        $name    = trim($row[$col['name']]            ?? '');
        $phone   = trim($row[$col['phone']]           ?? '');
        $email   = trim($row[$col['email']]           ?? '');
        $address = trim($row[$col['address']]         ?? '');
        $balance = (float)($row[$col['opening_balance']] ?? 0);

        if (!$name) {
            $errors[] = "Row $row_num: skipped — name is empty.";
            $skipped++;
            continue;
        }

        // Skip duplicate phone
        if ($phone) {
            $chk = db()->prepare('SELECT id FROM customers WHERE tenant_id=? AND phone=? LIMIT 1');
            $chk->execute([$tid, $phone]);
            if ($chk->fetchColumn()) {
                $errors[] = "Row $row_num: skipped \"$name\" — phone \"$phone\" already exists.";
                $skipped++;
                continue;
            }
        }

        try {
            db()->prepare('INSERT INTO customers (tenant_id, name, phone, email, address, opening_balance)
                           VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$tid, $name, $phone, $email, $address, $balance]);
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Row $row_num: DB error — " . $e->getMessage();
            $skipped++;
        }
    }
}

fclose($handle);

$msg = "Import complete: $imported imported";
if ($skipped > 0) $msg .= ", $skipped skipped";
if ($errors)      $msg .= '. Issues: ' . implode(' | ', array_slice($errors, 0, 5));

echo json_encode([
    'success'  => true,
    'imported' => $imported,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'message'  => $msg,
]);
