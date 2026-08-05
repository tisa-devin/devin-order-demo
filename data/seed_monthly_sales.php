<?php
// ダッシュボードの月次売上推移グラフ確認用に、直近6ヶ月分のサンプル売上データを投入する
// 実行: php data/seed_monthly_sales.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

$customerId = $pdo->query("SELECT id FROM customers ORDER BY id LIMIT 1")->fetchColumn();
$orderId = $pdo->query("SELECT id FROM orders ORDER BY id LIMIT 1")->fetchColumn();

if (!$customerId || !$orderId) {
    echo "顧客・受注データがありません。先に data/seed.php を実行してください。\n";
    exit(1);
}

echo "直近6ヶ月のサンプル売上データを投入します...\n";

$stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");

$count = 0;
for ($i = 5; $i >= 0; $i--) {
    // 月あたり2〜3件の売上を計上する
    $salesPerMonth = 2 + ($i % 2);
    for ($j = 1; $j <= $salesPerMonth; $j++) {
        $salesDate = date('Y-m-', strtotime("-$i month")) . sprintf('%02d', $j * 5);
        $totalAmount = 300000 + ($i * 120000) + ($j * 50000);
        $taxAmount = (int)round($totalAmount / 11);
        $salesNo = getNextNumber('sales');
        $invoiceNo = getNextNumber('invoice');
        $acceptanceNo = getNextNumber('acceptance');
        $stmt->execute([$salesNo, $orderId, $customerId, $salesDate, $totalAmount, $taxAmount, $invoiceNo, $acceptanceNo, '月次売上推移グラフ確認用']);
        $count++;
    }
}

echo "売上: {$count}件\n";
echo "\nサンプル売上データの投入が完了しました。\n";
