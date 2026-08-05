<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "月次売上グラフ用のサンプル売上データを投入します...\n";

// 顧客が無ければ用意する（seed.php 未実行でも動作するように）
$pdo->exec("INSERT OR IGNORE INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES
    ('C001', '株式会社サンプル商事', '100-0001', '東京都千代田区千代田1-1-1', '03-1234-5678', 'ACC001')
");
$customerId = (int)$pdo->query("SELECT id FROM customers ORDER BY id LIMIT 1")->fetchColumn();

// 直近6ヶ月ぶんの売上金額（税抜相当のダミー額）
$amounts = [1200000, 850000, 1500000, 980000, 1750000, 1320000];

$count = 0;
for ($i = 5; $i >= 0; $i--) {
    $salesDate = date('Y-m-15', strtotime("first day of -$i month"));
    $total = $amounts[5 - $i];
    $tax = (int)round($total * 0.1);

    // 売上には order_id が必須のため、対応する受注を先に作成する
    $orderNo = getNextNumber('order');
    $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', 'グラフ用サンプル')");
    $stmt->execute([$orderNo, $customerId, $salesDate, $salesDate, '月次売上サンプル', $total + $tax, $tax]);
    $orderId = (int)$pdo->lastInsertId();

    $salesNo = getNextNumber('sales');
    $invoiceNo = getNextNumber('invoice');
    $acceptanceNo = getNextNumber('acceptance');
    $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'グラフ用サンプル')");
    $stmt->execute([$salesNo, $orderId, $customerId, $salesDate, $total + $tax, $tax, $invoiceNo, $acceptanceNo]);

    echo sprintf("  %s: %s 円\n", date('Y/m', strtotime($salesDate)), number_format($total + $tax));
    $count++;
}

echo "\n売上サンプルデータ {$count} 件の投入が完了しました。\n";
