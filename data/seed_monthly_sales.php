<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "直近6ヶ月のサンプル売上データを投入します...\n";

$stmt = $pdo->query("SELECT id FROM customers ORDER BY code LIMIT 1");
$customerId = $stmt->fetchColumn();
if (!$customerId) {
    echo "顧客マスタが空です。先に data/seed.php を実行してください。\n";
    exit(1);
}

$amounts = [1_200_000, 850_000, 1_650_000, 990_000, 2_100_000, 1_430_000];

foreach ($amounts as $i => $amount) {
    $monthsAgo = 5 - $i;
    $salesDate = date('Y-m-15', strtotime("-{$monthsAgo} month", strtotime(date('Y-m-01'))));
    $taxAmount = (int)round($amount / 11);
    $subject = date('Y年n月', strtotime($salesDate)) . 'サンプル案件';

    $orderNo = getNextNumber('order');
    $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', 'グラフ動作確認用')");
    $stmt->execute([$orderNo, $customerId, $salesDate, $salesDate, $subject, $amount, $taxAmount]);
    $orderId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES (?, 1, ?, 1, '式', ?, ?, 10, 'received', '')");
    $stmt->execute([$orderId, $subject, $amount - $taxAmount, $amount - $taxAmount]);

    $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'グラフ動作確認用')");
    $stmt->execute([getNextNumber('sales'), $orderId, $customerId, $salesDate, $amount, $taxAmount, getNextNumber('invoice'), getNextNumber('acceptance')]);

    echo date('Y/m', strtotime($salesDate)) . ": ¥" . number_format($amount) . "\n";
}

echo "\nサンプル売上データの投入が完了しました。\n";
