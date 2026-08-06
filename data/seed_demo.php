<?php
/**
 * 動作確認用の追加デモデータ投入スクリプト。
 * 絞り込み（顧客名・期間・ステータス）やCSV出力を試せるよう、
 * 複数の顧客・期間・ステータスにまたがるデータを作成する。
 *
 * 実行: php data/seed_demo.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

$pdo = getDB();

echo "デモデータを投入します...\n";

$customers = [
    ['D001', '株式会社デモ電機', '101-0011', '東京都千代田区外神田1-1-1', '03-1111-2222', 'ACC101'],
    ['D002', '株式会社みらい物流', '210-0002', '神奈川県川崎市川崎区2-2-2', '044-222-3333', 'ACC102'],
    ['D003', '有限会社さくら印刷', '460-0003', '愛知県名古屋市中区栄3-3-3', '052-333-4444', 'ACC103'],
    ['D004', '合同会社北海道フーズ', '060-0004', '北海道札幌市中央区大通4-4-4', '011-444-5555', 'ACC104'],
];
$stmt = $pdo->prepare("INSERT OR IGNORE INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($customers as $c) {
    $stmt->execute($c);
}
echo "顧客マスタ: " . count($customers) . "件\n";

$suppliers = [
    ['D101', '株式会社デモ資材', '111-0011', '東京都台東区浅草1-1-1', '03-6666-7777', 'SUP101'],
    ['D102', '有限会社ネクスト工販', '330-0012', '埼玉県さいたま市大宮区2-2-2', '048-777-8888', 'SUP102'],
];
$stmt = $pdo->prepare("INSERT OR IGNORE INTO suppliers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($suppliers as $s) {
    $stmt->execute($s);
}
echo "仕入先マスタ: " . count($suppliers) . "件\n";

$customerIds = [];
foreach (['D001', 'D002', 'D003', 'D004'] as $code) {
    $customerIds[$code] = (int)$pdo->query("SELECT id FROM customers WHERE code = '$code'")->fetchColumn();
}
$supplierId = (int)$pdo->query("SELECT id FROM suppliers WHERE code = 'D101'")->fetchColumn();

/** 明細から税込合計と消費税額を計算する */
function demoTotals(array $lines): array
{
    $subtotal = 0;
    foreach ($lines as $line) {
        $subtotal += $line[1] * $line[2];
    }
    $tax = (int)floor($subtotal * 0.1);
    return [$subtotal + $tax, $tax];
}

// 見積: 顧客・見積日・ステータスを散らす
$estimateSeeds = [
    ['D001', '2025-11-05', '2025-12-05', '基幹システム更新', 'draft', [['要件定義', 1, 400000], ['基本設計', 1, 300000]]],
    ['D002', '2025-12-18', '2026-01-18', '倉庫管理アプリ開発', 'sent', [['アプリ開発', 1, 800000]]],
    ['D003', '2026-02-03', '2026-03-03', '会社案内パンフレット制作', 'accepted', [['デザイン', 1, 150000], ['印刷', 5000, 40]]],
    ['D004', '2026-03-15', '2026-04-15', 'ECサイトリニューアル', 'rejected', [['サイト改修', 1, 600000]]],
];
$estimateIds = [];
foreach ($estimateSeeds as [$custCode, $date, $validUntil, $subject, $status, $lines]) {
    [$total, $tax] = demoTotals($lines);
    $no = getNextNumber('estimate');
    $stmt = $pdo->prepare("INSERT INTO estimates (estimate_no, customer_id, estimate_date, valid_until, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'デモデータ')");
    $stmt->execute([$no, $customerIds[$custCode], $date, $validUntil, $subject, $total, $tax, $status]);
    $estimateId = (int)$pdo->lastInsertId();
    $estimateIds[$custCode] = $estimateId;

    $detail = $pdo->prepare("INSERT INTO estimate_details (estimate_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, notes) VALUES (?, ?, ?, ?, '式', ?, ?, 10, '')");
    foreach ($lines as $i => [$name, $qty, $price]) {
        $detail->execute([$estimateId, $i + 1, $name, $qty, $price, $qty * $price]);
    }
}
echo "見積: " . count($estimateSeeds) . "件\n";

// 受注: 期間とステータスを散らす（1件は見積から変換した形にする）
$orderSeeds = [
    ['D002', '2025-12-25', '2026-02-20', '倉庫管理アプリ開発', 'ordered', [['アプリ開発', 1, 800000]], 'D002'],
    ['D003', '2026-02-10', '2026-03-10', '会社案内パンフレット制作', 'in_progress', [['デザイン', 1, 150000], ['印刷', 5000, 40]], null],
    ['D001', '2026-03-01', '2026-03-31', 'ネットワーク保守', 'completed', [['保守作業', 12, 50000]], null],
    ['D004', '2026-03-20', '2026-04-30', 'ECサイトリニューアル', 'cancelled', [['サイト改修', 1, 600000]], null],
];
$orderIds = [];
foreach ($orderSeeds as [$custCode, $date, $delivery, $subject, $status, $lines, $fromEstimate]) {
    [$total, $tax] = demoTotals($lines);
    $no = getNextNumber('order');
    $stmt = $pdo->prepare("INSERT INTO orders (order_no, estimate_id, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'デモデータ')");
    $stmt->execute([$no, $fromEstimate ? $estimateIds[$fromEstimate] : null, $customerIds[$custCode], $date, $delivery, $subject, $total, $tax, $status]);
    $orderId = (int)$pdo->lastInsertId();
    $orderIds[$custCode] = $orderId;

    $detail = $pdo->prepare("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES (?, ?, ?, ?, '式', ?, ?, 10, 'none', '')");
    foreach ($lines as $i => [$name, $qty, $price]) {
        $detail->execute([$orderId, $i + 1, $name, $qty, $price, $qty * $price]);
    }
}
echo "受注: " . count($orderSeeds) . "件\n";

// 発注（受注に紐づく）
$purchaseSeeds = [
    ['D002', '2025-12-26', '2026-01-15', 'ordered', null, [['アプリ開発（外注）', 1, 500000]]],
    ['D003', '2026-02-12', '2026-02-25', 'received', '2026-02-24', [['印刷（外注）', 5000, 25]]],
];
foreach ($purchaseSeeds as [$custCode, $date, $delivery, $status, $receivedDate, $lines]) {
    [$total, $tax] = demoTotals($lines);
    $no = getNextNumber('purchase');
    $stmt = $pdo->prepare("INSERT INTO purchases (purchase_no, order_id, supplier_id, purchase_date, delivery_date, total_amount, tax_amount, status, received_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'デモデータ')");
    $stmt->execute([$no, $orderIds[$custCode], $supplierId, $date, $delivery, $total, $tax, $status, $receivedDate]);
    $purchaseId = (int)$pdo->lastInsertId();

    $detail = $pdo->prepare("INSERT INTO purchase_details (purchase_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, notes) VALUES (?, ?, ?, ?, '式', ?, ?, 10, '')");
    foreach ($lines as $i => [$name, $qty, $price]) {
        $detail->execute([$purchaseId, $i + 1, $name, $qty, $price, $qty * $price]);
    }
}
echo "発注: " . count($purchaseSeeds) . "件\n";

// 売上: CSV出力の期間絞り込み・出力済フラグを試せるよう複数月・両フラグを用意
$salesSeeds = [
    ['D002', '2026-01-31', 880000, 80000, 0],
    ['D003', '2026-02-28', 264000, 24000, 1],
    ['D001', '2026-03-31', 660000, 60000, 0],
];
foreach ($salesSeeds as [$custCode, $date, $total, $tax, $exported]) {
    $no = getNextNumber('sales');
    $invoiceNo = getNextNumber('invoice');
    $acceptanceNo = getNextNumber('acceptance');
    $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, exported, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'デモデータ')");
    $stmt->execute([$no, $orderIds[$custCode], $customerIds[$custCode], $date, $total, $tax, $invoiceNo, $acceptanceNo, $exported]);
}
echo "売上: " . count($salesSeeds) . "件\n";

echo "\nデモデータの投入が完了しました。\n";
