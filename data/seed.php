<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "テストデータを投入します...\n";

$pdo->exec("INSERT OR IGNORE INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES
    ('C001', '株式会社サンプル商事', '100-0001', '東京都千代田区千代田1-1-1', '03-1234-5678', 'ACC001'),
    ('C002', '有限会社テスト工業', '150-0002', '東京都渋谷区渋谷2-2-2', '03-2345-6789', 'ACC002'),
    ('C003', '合同会社デモ企画', '160-0003', '東京都新宿区新宿3-3-3', '03-3456-7890', 'ACC003')
");
echo "顧客マスタ: 3件\n";

$pdo->exec("INSERT OR IGNORE INTO suppliers (code, name, postal_code, address, tel, accounting_code) VALUES
    ('S001', '株式会社仕入商会', '110-0001', '東京都台東区上野1-1-1', '03-4567-8901', 'SUP001'),
    ('S002', '有限会社部品センター', '120-0002', '東京都足立区千住2-2-2', '03-5678-9012', 'SUP002'),
    ('S003', '合同会社資材倉庫', '130-0003', '東京都墨田区押上3-3-3', '03-6789-0123', 'SUP003')
");
echo "仕入先マスタ: 3件\n";

$pdo->exec("INSERT OR IGNORE INTO accounts (code, name, tax_class_code) VALUES
    ('1310', '売掛金', '0000'),
    ('4110', '売上高', '0060'),
    ('4120', '売上高（軽減税率）', '0068'),
    ('5110', '仕入高', '0060')
");
echo "勘定科目マスタ: 4件\n";

$stmt = $pdo->query("SELECT id FROM customers WHERE code = 'C001'");
$customerId1 = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT id FROM customers WHERE code = 'C002'");
$customerId2 = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT id FROM suppliers WHERE code = 'S001'");
$supplierId1 = $stmt->fetchColumn();

$estimateNo = getNextNumber('estimate');
$pdo->exec("INSERT INTO estimates (estimate_no, customer_id, estimate_date, valid_until, subject, total_amount, tax_amount, status, notes) VALUES
    ('$estimateNo', $customerId1, '2026-01-10', '2026-02-10', 'システム開発案件', 1100000, 100000, 'sent', 'サンプル見積')
");
$estimateId = $pdo->lastInsertId();

$pdo->exec("INSERT INTO estimate_details (estimate_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, notes) VALUES
    ($estimateId, 1, 'システム設計', 1, '式', 300000, 300000, 10, ''),
    ($estimateId, 2, 'プログラム開発', 1, '式', 500000, 500000, 10, ''),
    ($estimateId, 3, 'テスト・検証', 1, '式', 200000, 200000, 10, '')
");
echo "見積: 1件（明細3件）\n";

$orderNo = getNextNumber('order');
$pdo->exec("INSERT INTO orders (order_no, estimate_id, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES
    ('$orderNo', $estimateId, $customerId1, '2026-01-12', '2026-02-28', 'システム開発案件', 1100000, 100000, 'in_progress', '見積から変換')
");
$orderId = $pdo->lastInsertId();

$pdo->exec("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES
    ($orderId, 1, 'システム設計', 1, '式', 300000, 300000, 10, 'received', ''),
    ($orderId, 2, 'プログラム開発', 1, '式', 500000, 500000, 10, 'ordered', ''),
    ($orderId, 3, 'テスト・検証', 1, '式', 200000, 200000, 10, 'none', '')
");
echo "受注: 1件（明細3件）\n";

$stmt = $pdo->query("SELECT id FROM order_details WHERE order_id = $orderId AND line_no = 1");
$orderDetailId1 = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT id FROM order_details WHERE order_id = $orderId AND line_no = 2");
$orderDetailId2 = $stmt->fetchColumn();

$purchaseNo1 = getNextNumber('purchase');
$pdo->exec("INSERT INTO purchases (purchase_no, order_id, supplier_id, purchase_date, delivery_date, total_amount, tax_amount, status, received_date, notes) VALUES
    ('$purchaseNo1', $orderId, $supplierId1, '2026-01-13', '2026-01-20', 165000, 15000, 'received', '2026-01-18', '設計外注')
");
$purchaseId1 = $pdo->lastInsertId();

$pdo->exec("INSERT INTO purchase_details (purchase_id, order_detail_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, notes) VALUES
    ($purchaseId1, $orderDetailId1, 1, 'システム設計（外注）', 1, '式', 150000, 150000, 10, '')
");

$purchaseNo2 = getNextNumber('purchase');
$pdo->exec("INSERT INTO purchases (purchase_no, order_id, supplier_id, purchase_date, delivery_date, total_amount, tax_amount, status, notes) VALUES
    ('$purchaseNo2', $orderId, $supplierId1, '2026-01-13', '2026-02-15', 275000, 25000, 'ordered', '開発外注')
");
$purchaseId2 = $pdo->lastInsertId();

$pdo->exec("INSERT INTO purchase_details (purchase_id, order_detail_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, notes) VALUES
    ($purchaseId2, $orderDetailId2, 1, 'プログラム開発（外注）', 1, '式', 250000, 250000, 10, '')
");
echo "発注: 2件\n";

$orderNo2 = getNextNumber('order');
$pdo->exec("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES
    ('$orderNo2', $customerId2, '2026-01-05', '2026-01-10', 'Webサイト制作', 550000, 50000, 'completed', '完了済み案件')
");
$orderId2 = $pdo->lastInsertId();

$pdo->exec("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES
    ($orderId2, 1, 'デザイン制作', 1, '式', 200000, 200000, 10, 'received', ''),
    ($orderId2, 2, 'コーディング', 1, '式', 300000, 300000, 10, 'received', '')
");

$salesNo = getNextNumber('sales');
$invoiceNo = getNextNumber('invoice');
$acceptanceNo = getNextNumber('acceptance');
$pdo->exec("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES
    ('$salesNo', $orderId2, $customerId2, '2026-01-10', 550000, 50000, '$invoiceNo', '$acceptanceNo', 0, '売上計上済み')
");
echo "売上: 1件\n";

$pdo->exec("UPDATE estimates SET status = 'accepted' WHERE id = $estimateId");

echo "\nテストデータの投入が完了しました。\n";
