<?php
// ============================================================
//  carwash_services_lib.php — Shared Hero Carwash service catalog
//  Used by:
//    - carwash_services.php        (admin CRUD page)
//    - h_carwash_sales_report.php  (Transactions service search)
//  Keeping this in one place means a service added on the admin
//  page is instantly available in the sales report's dropdown —
//  there is only one source of truth (the DB table below).
// ============================================================

function ensureCarwashServicesTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `h_carwash_services` (
        `id`          int(11) NOT NULL AUTO_INCREMENT,
        `store_name`  varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
        `category`    varchar(100) NOT NULL DEFAULT 'ADD-ONS / OTHERS',
        `name`        varchar(150) NOT NULL,
        `price`       decimal(12,2) NOT NULL DEFAULT 0.00,
        `sort_order`  int(6) NOT NULL DEFAULT 0,
        `is_active`   tinyint(1) NOT NULL DEFAULT 1,
        `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_store_name` (`store_name`,`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

// The original static price list — used ONLY to seed a brand-new/empty
// table so existing reports keep working. After the first run, all
// reads/writes go through the DB table above.
function carwashDefaultServices(): array {
    return [
        '4 WHEELS' => [
            'BASIC SMALL 4 WHEELS'        => 350.00,
            'BASIC MEDIUM 4 WHEELS'       => 400.00,
            'BASIC LARGE 4 WHEELS'        => 450.00,
            'BASIC XL 4 WHEELS'           => 500.00,
            'ADVANCED SMALL 4 WHEELS'     => 400.00,
            'ADVANCED MEDIUM 4 WHEELS'    => 450.00,
            'ADVANCE LARGE 4 WHEELS'      => 500.00,
            'ADVANCE XL 4 WHEELS'         => 550.00,
            'PREMIUM SMALL 4 WHEELS'      => 500.00,
            'PREMIUM MEDIUM 4 WHEELS'     => 550.00,
            'PREMIUM LARGE 4 WHEELS'      => 600.00,
            'PREMIUM XL 4 WHEELS'         => 700.00,
        ],
        'MOTORCYCLE' => [
            'BASIC REGULAR MOTORCYCLE'      => 150.00,
            'ADVANCE REGULAR MOTORCYCLE'    => 250.00,
            'ADVANCE BIG BIKE MOTORCYCLE'   => 380.00,
        ],
        'ADD-ONS / OTHERS' => [
            'ARMOR ALL'         => 200.00,
            'BACK TO ZERO'      => 500.00,
            'ENGINE WASH'       => 400.00,
            'ASPHALT REMOVAL'   => 400.00,
            'QUICK WASH'        => 250.00,
            'VACUUM SMALL'      => 100.00,
            'DEFAULT'           => 0.00,
        ],
    ];
}

function seedCarwashServicesIfEmpty(PDO $pdo, string $store): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM h_carwash_services WHERE store_name=?");
    $stmt->execute([$store]);
    if ((int)$stmt->fetchColumn() > 0) return;

    $ins = $pdo->prepare("INSERT INTO h_carwash_services
        (store_name,category,name,price,sort_order,is_active) VALUES (?,?,?,?,?,1)");
    $order = 0;
    foreach (carwashDefaultServices() as $category => $items) {
        foreach ($items as $name => $price) {
            $ins->execute([$store, $category, $name, $price, $order++]);
        }
    }
}

// Grouped [category => [name => price]] of ACTIVE services only.
// Shape matches the old static $SERVICES array exactly, so the
// existing render/JS code in h_carwash_sales_report.php keeps working
// without any further changes downstream.
function getCarwashServicesGrouped(PDO $pdo, string $store): array {
    $stmt = $pdo->prepare("SELECT category, name, price FROM h_carwash_services
        WHERE store_name=? AND is_active=1 ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$store]);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $grouped[$r['category']][$r['name']] = (float)$r['price'];
    }
    return $grouped;
}

// Full raw rows (incl. id + is_active) — for the admin management page.
function getCarwashServicesAll(PDO $pdo, string $store): array {
    $stmt = $pdo->prepare("SELECT * FROM h_carwash_services WHERE store_name=? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$store]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}