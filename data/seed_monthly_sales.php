<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "直近6ヶ月の月次売上サンプルデータを投入します...\n";

$pdo->exec("INSERT OR IGNORE INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES
    ('C001', '株式会社サンプル商事', '100-0001', '東京都千代田区千代田1-1-1', '03-1234-5678', 'ACC001')
");
$customerId = $pdo->query("SELECT id FROM customers ORDER BY id LIMIT 1")->fetchColumn();

$amounts = [1200000, 850000, 1650000, 2100000, 980000, 1430000];

foreach ($amounts as $i => $amount) {
    $offset = 5 - $i;
    $salesDate = date('Y-m-15', strtotime("first day of -$offset month"));
    $orderDate = date('Y-m-05', strtotime("first day of -$offset month"));
    $taxAmount = (int)round($amount / 11);

    $orderNo = getNextNumber('order');
    $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', '月次売上サンプル')");
    $stmt->execute([$orderNo, $customerId, $orderDate, $salesDate, date('Y年n月', strtotime($salesDate)) . '度 サンプル案件', $amount, $taxAmount]);
    $orderId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES (?, 1, 'サンプル作業', 1, '式', ?, ?, 10, 'received', '')");
    $stmt->execute([$orderId, $amount - $taxAmount, $amount - $taxAmount]);

    $salesNo = getNextNumber('sales');
    $invoiceNo = getNextNumber('invoice');
    $acceptanceNo = getNextNumber('acceptance');
    $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, '月次売上サンプル')");
    $stmt->execute([$salesNo, $orderId, $customerId, $salesDate, $amount, $taxAmount, $invoiceNo, $acceptanceNo]);

    echo sprintf("%s: ¥%s\n", date('Y/m', strtotime($salesDate)), number_format($amount));
}

echo "\n月次売上サンプルデータの投入が完了しました。\n";
