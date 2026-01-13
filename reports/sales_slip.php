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
    die('売上IDが指定されていません');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, o.order_no, o.subject FROM sales s JOIN customers c ON s.customer_id = c.id JOIN orders o ON s.order_id = o.id WHERE s.id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    die('売上が見つかりません');
}

$stmt = $pdo->prepare("SELECT * FROM order_details WHERE order_id = ? ORDER BY line_no");
$stmt->execute([$sale['order_id']]);
$details = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>売上伝票 - <?= h($sale['sales_no']) ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Hiragino Kaku Gothic Pro", "Yu Gothic", sans-serif; font-size: 11pt; line-height: 1.6; }
        .container { max-width: 210mm; margin: 0 auto; padding: 10mm; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 20pt; border-bottom: 2px solid #333; display: inline-block; padding: 0 20px 5px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 8px; border: 1px solid #333; }
        .info-table .label { background: #e0e0e0; font-weight: bold; width: 120px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table th, .details-table td { border: 1px solid #333; padding: 8px; }
        .details-table th { background: #e0e0e0; font-weight: bold; }
        .details-table .text-right { text-align: right; }
        .details-table .text-center { text-align: center; }
        .summary { margin-top: 10px; }
        .summary table { width: 300px; margin-left: auto; border-collapse: collapse; }
        .summary td { padding: 5px 10px; border: 1px solid #333; }
        .summary .label { background: #e0e0e0; font-weight: bold; }
        .internal-note { margin-top: 20px; padding: 10px; background: #fffde7; border: 1px dashed #999; font-size: 10pt; }
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
        <h1>売 上 伝 票</h1>
        <div style="margin-top: 5px; font-size: 10pt; color: #666;">（内部管理用）</div>
    </div>
    
    <table class="info-table">
        <tr>
            <td class="label">売上番号</td>
            <td><?= h($sale['sales_no']) ?></td>
            <td class="label">売上日</td>
            <td><?= formatDate($sale['sales_date']) ?></td>
        </tr>
        <tr>
            <td class="label">受注番号</td>
            <td><?= h($sale['order_no']) ?></td>
            <td class="label">顧客名</td>
            <td><?= h($sale['customer_name']) ?></td>
        </tr>
        <tr>
            <td class="label">請求書番号</td>
            <td><?= h($sale['invoice_no']) ?></td>
            <td class="label">検収書番号</td>
            <td><?= h($sale['acceptance_no']) ?></td>
        </tr>
        <?php if ($sale['subject']): ?>
        <tr>
            <td class="label">件名</td>
            <td colspan="3"><?= h($sale['subject']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
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
                <td class="text-right">&yen;<?= formatNumber($sale['total_amount'] - $sale['tax_amount']) ?></td>
            </tr>
            <tr>
                <td class="label">消費税</td>
                <td class="text-right">&yen;<?= formatNumber($sale['tax_amount']) ?></td>
            </tr>
            <tr>
                <td class="label">合計</td>
                <td class="text-right"><strong>&yen;<?= formatNumber($sale['total_amount']) ?></strong></td>
            </tr>
        </table>
    </div>
    
    <div class="internal-note">
        <strong>会計出力状態:</strong> <?= $sale['exported'] ? '出力済' : '未出力' ?>
    </div>
    
    <?php if ($sale['notes']): ?>
    <div style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;">
        <div style="font-weight: bold; margin-bottom: 5px;">備考</div>
        <div><?= nl2br(h($sale['notes'])) ?></div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
