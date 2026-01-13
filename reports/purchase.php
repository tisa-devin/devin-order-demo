<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

function h($str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatNumber($num): string {
    return number_format((int)$num);
}

function formatDate($date): string {
    if (empty($date)) return '';
    return date('Y年m月d日', strtotime($date));
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die('発注IDが指定されていません');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name, s.postal_code, s.address, o.order_no FROM purchases p JOIN suppliers s ON p.supplier_id = s.id JOIN orders o ON p.order_id = o.id WHERE p.id = ?");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    die('発注が見つかりません');
}

$stmt = $pdo->prepare("SELECT * FROM purchase_details WHERE purchase_id = ? ORDER BY line_no");
$stmt->execute([$id]);
$details = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>発注書 - <?= h($purchase['purchase_no']) ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Hiragino Kaku Gothic Pro", "Yu Gothic", sans-serif; font-size: 11pt; line-height: 1.6; }
        .container { max-width: 210mm; margin: 0 auto; padding: 10mm; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 24pt; border-bottom: 3px double #333; display: inline-block; padding: 0 30px 5px; }
        .info-section { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .supplier-info { width: 45%; }
        .supplier-info .name { font-size: 14pt; font-weight: bold; border-bottom: 1px solid #333; padding-bottom: 5px; margin-bottom: 10px; }
        .supplier-info .name::after { content: " 御中"; font-weight: normal; }
        .company-info { width: 45%; text-align: right; }
        .meta-info { margin-bottom: 20px; }
        .meta-info table { width: 100%; border-collapse: collapse; }
        .meta-info td { padding: 5px 10px; }
        .meta-info .label { width: 100px; font-weight: bold; }
        .total-box { background: #f5f5f5; padding: 15px; margin-bottom: 20px; text-align: center; }
        .total-box .amount { font-size: 18pt; font-weight: bold; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table th, .details-table td { border: 1px solid #333; padding: 8px; }
        .details-table th { background: #e0e0e0; font-weight: bold; }
        .details-table .text-right { text-align: right; }
        .details-table .text-center { text-align: center; }
        .summary { margin-top: 10px; }
        .summary table { width: 300px; margin-left: auto; border-collapse: collapse; }
        .summary td { padding: 5px 10px; border: 1px solid #333; }
        .summary .label { background: #e0e0e0; font-weight: bold; }
        .notes { margin-top: 20px; padding: 10px; border: 1px solid #ccc; }
        .notes-title { font-weight: bold; margin-bottom: 5px; }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="no-print" style="padding: 10px; background: #333; color: #fff; text-align: center;">
    <button onclick="window.print()" style="padding: 10px 30px; font-size: 14pt; cursor: pointer;">印刷</button>
    <button onclick="window.close()" style="padding: 10px 30px; font-size: 14pt; cursor: pointer; margin-left: 10px;">閉じる</button>
</div>

<div class="container">
    <div class="header">
        <h1>発 注 書</h1>
    </div>
    
    <div class="info-section">
        <div class="supplier-info">
            <?php if ($purchase['postal_code']): ?>
            <div>〒<?= h($purchase['postal_code']) ?></div>
            <?php endif; ?>
            <?php if ($purchase['address']): ?>
            <div><?= h($purchase['address']) ?></div>
            <?php endif; ?>
            <div class="name"><?= h($purchase['supplier_name']) ?></div>
        </div>
        <div class="company-info">
            <div>発注番号: <?= h($purchase['purchase_no']) ?></div>
            <div>発注日: <?= formatDate($purchase['purchase_date']) ?></div>
            <?php if ($purchase['delivery_date']): ?>
            <div>希望納期: <?= formatDate($purchase['delivery_date']) ?></div>
            <?php endif; ?>
            <div>受注番号: <?= h($purchase['order_no']) ?></div>
        </div>
    </div>
    
    <div class="total-box">
        <div>発注金額（税込）</div>
        <div class="amount">&yen;<?= formatNumber($purchase['total_amount']) ?>-</div>
    </div>
    
    <table class="details-table">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:40%">品名</th>
                <th style="width:10%">数量</th>
                <th style="width:8%">単位</th>
                <th style="width:15%">単価</th>
                <th style="width:15%">金額</th>
                <th style="width:7%">税率</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $d): ?>
            <tr>
                <td class="text-center"><?= $d['line_no'] ?></td>
                <td><?= h($d['item_name']) ?></td>
                <td class="text-right"><?= formatNumber($d['quantity']) ?></td>
                <td class="text-center"><?= h($d['unit']) ?></td>
                <td class="text-right">&yen;<?= formatNumber($d['unit_price']) ?></td>
                <td class="text-right">&yen;<?= formatNumber($d['amount']) ?></td>
                <td class="text-center"><?= $d['tax_rate'] ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="summary">
        <table>
            <tr>
                <td class="label">小計</td>
                <td class="text-right">&yen;<?= formatNumber($purchase['total_amount'] - $purchase['tax_amount']) ?></td>
            </tr>
            <tr>
                <td class="label">消費税</td>
                <td class="text-right">&yen;<?= formatNumber($purchase['tax_amount']) ?></td>
            </tr>
            <tr>
                <td class="label">合計</td>
                <td class="text-right"><strong>&yen;<?= formatNumber($purchase['total_amount']) ?></strong></td>
            </tr>
        </table>
    </div>
    
    <?php if ($purchase['notes']): ?>
    <div class="notes">
        <div class="notes-title">備考</div>
        <div><?= nl2br(h($purchase['notes'])) ?></div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
