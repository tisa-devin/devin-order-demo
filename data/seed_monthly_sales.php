<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "直近6ヶ月の売上サンプルデータを投入します...\n";

$customerIds = $pdo->query("SELECT id FROM customers ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
if (empty($customerIds)) {
    fwrite(STDERR, "顧客マスタが空です。先に data/seed.php を実行してください。\n");
    exit(1);
}

$amountsByMonth = [1200000, 850000, 1650000, 2100000, 980000, 1750000];

$insertOrder = $pdo->prepare("
    INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', '月次売上サンプル')
");
$insertOrderDetail = $pdo->prepare("
    INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes)
    VALUES (?, 1, ?, 1, '式', ?, ?, 10, 'received', '')
");
$insertSales = $pdo->prepare("
    INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, '月次売上サンプル')
");

$total = 0;
foreach ($amountsByMonth as $index => $totalAmount) {
    $offset = 5 - $index;
    $month = date('Y-m', strtotime("-{$offset} months"));
    $salesDate = $month . '-15';
    $orderDate = $month . '-01';
    $customerId = $customerIds[$index % count($customerIds)];
    $taxAmount = (int)round($totalAmount / 11);
    $netAmount = $totalAmount - $taxAmount;
    $subject = $month . ' 月次サンプル案件';

    $orderNo = getNextNumber('order');
    $insertOrder->execute([$orderNo, $customerId, $orderDate, $salesDate, $subject, $totalAmount, $taxAmount]);
    $orderId = (int)$pdo->lastInsertId();
    $insertOrderDetail->execute([$orderId, $subject, $netAmount, $netAmount]);

    $insertSales->execute([
        getNextNumber('sales'),
        $orderId,
        $customerId,
        $salesDate,
        $totalAmount,
        $taxAmount,
        getNextNumber('invoice'),
        getNextNumber('acceptance'),
    ]);

    echo sprintf("  %s: ¥%s\n", $month, number_format($totalAmount));
    $total += $totalAmount;
}

echo sprintf("\n売上6件（合計 ¥%s）を投入しました。\n", number_format($total));
