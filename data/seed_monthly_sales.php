<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "月次売上推移グラフ確認用のサンプル売上を投入します...\n";

$customerIds = $pdo->query("SELECT id FROM customers ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
if (empty($customerIds)) {
    echo "顧客マスタが空です。先に data/seed.php を実行してください。\n";
    exit(1);
}

$amounts = [1200000, 850000, 1650000, 980000, 2100000, 1430000];
$count = 0;

for ($i = 5; $i >= 0; $i--) {
    $monthStart = strtotime("first day of -{$i} month");
    $salesDate = date('Y-m-15', $monthStart);
    $customerId = $customerIds[$count % count($customerIds)];
    $amount = $amounts[$count % count($amounts)];
    $taxAmount = (int)round($amount / 11);
    $subject = date('Y年n月', $monthStart) . '分 サンプル売上';

    $orderNo = getNextNumber('order');
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', 'サンプルデータ')
    ");
    $stmt->execute([$orderNo, $customerId, $salesDate, $salesDate, $subject, $amount, $taxAmount]);
    $orderId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes)
        VALUES (?, 1, ?, 1, '式', ?, ?, 10, 'received', '')
    ");
    $stmt->execute([$orderId, $subject, $amount - $taxAmount, $amount - $taxAmount]);

    $stmt = $pdo->prepare("
        INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'サンプルデータ')
    ");
    $stmt->execute([
        getNextNumber('sales'),
        $orderId,
        $customerId,
        $salesDate,
        $amount,
        $taxAmount,
        getNextNumber('invoice'),
        getNextNumber('acceptance'),
    ]);

    echo date('Y-m', $monthStart) . ": ¥" . number_format($amount) . "\n";
    $count++;
}

echo "\nサンプル売上 {$count}件の投入が完了しました。\n";
