<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "直近6ヶ月の売上サンプルデータを投入します...\n";

$stmt = $pdo->query("SELECT id FROM customers ORDER BY id LIMIT 1");
$customerId = $stmt->fetchColumn();
if (!$customerId) {
    echo "顧客マスタが空です。先に data/seed.php を実行してください。\n";
    exit(1);
}

$amounts = [1200000, 850000, 1650000, 980000, 2100000, 1430000];

for ($i = 5; $i >= 0; $i--) {
    $monthIndex = 5 - $i;
    $salesDate = date('Y-m-15', strtotime("first day of -$i month"));
    $totalAmount = $amounts[$monthIndex];
    $taxAmount = (int)round($totalAmount / 11);

    $orderNo = getNextNumber('order');
    $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', '月次売上サンプル')");
    $stmt->execute([$orderNo, $customerId, $salesDate, $salesDate, date('Y年n月', strtotime($salesDate)) . '分 サンプル案件', $totalAmount, $taxAmount]);
    $orderId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES (?, 1, 'サンプル作業一式', 1, '式', ?, ?, 10, 'received', '')");
    $stmt->execute([$orderId, $totalAmount - $taxAmount, $totalAmount - $taxAmount]);

    $salesNo = getNextNumber('sales');
    $invoiceNo = getNextNumber('invoice');
    $acceptanceNo = getNextNumber('acceptance');
    $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, '月次売上サンプル')");
    $stmt->execute([$salesNo, $orderId, $customerId, $salesDate, $totalAmount, $taxAmount, $invoiceNo, $acceptanceNo]);

    echo date('Y/m', strtotime($salesDate)) . ": ¥" . number_format($totalAmount) . "\n";
}

echo "\n売上サンプルデータの投入が完了しました。\n";
