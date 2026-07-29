<?php
/**
 * ダッシュボードの月次売上推移グラフ確認用に、直近6ヶ月分のサンプル受注・売上を投入する。
 *   php data/seed_monthly_sales.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();
$pdo = getDB();

$customerIds = $pdo->query("SELECT id FROM customers ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
if (!$customerIds) {
    exit("顧客マスタが空です。先に data/seed.php を実行してください。\n");
}

$subjects = ['保守サービス', 'システム改修', 'Webサイト制作', '機器導入', 'データ移行', '運用支援'];
$totalCount = 0;
$totalAmount = 0;

for ($i = 5; $i >= 0; $i--) {
    $monthStart = strtotime(date('Y-m') . '-01 -' . $i . ' month');
    $month = date('Y-m', $monthStart);
    $daysInMonth = (int)date('t', $monthStart);
    $countInMonth = random_int(3, 6);

    for ($n = 0; $n < $countInMonth; $n++) {
        $date = $month . '-' . str_pad((string)random_int(1, $daysInMonth), 2, '0', STR_PAD_LEFT);
        $customerId = $customerIds[array_rand($customerIds)];
        $subject = $subjects[array_rand($subjects)] . '（' . date('n月', $monthStart) . '）';
        $amountExcl = random_int(2, 30) * 50000;
        $tax = (int)floor($amountExcl * 0.1);
        $total = $amountExcl + $tax;

        $orderNo = getNextNumber('order');
        $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', '月次売上サンプル')");
        $stmt->execute([$orderNo, $customerId, $date, $date, $subject, $total, $tax]);
        $orderId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES (?, 1, ?, 1, '式', ?, ?, 10, 'received', '')");
        $stmt->execute([$orderId, $subject, $amountExcl, $amountExcl]);

        $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, '月次売上サンプル')");
        $stmt->execute([getNextNumber('sales'), $orderId, $customerId, $date, $total, $tax, getNextNumber('invoice'), getNextNumber('acceptance')]);

        $totalCount++;
        $totalAmount += $total;
    }
    echo $month . ": " . $countInMonth . "件\n";
}

echo "\n直近6ヶ月のサンプル売上を投入しました（計 {$totalCount} 件 / " . number_format($totalAmount) . " 円）。\n";
