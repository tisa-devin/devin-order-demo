<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$include_exported = isset($_GET['include_exported']);
$mark_exported = isset($_GET['mark_exported']);

$fromSql = " FROM sales s
             JOIN customers c ON s.customer_id = c.id
             LEFT JOIN orders o ON s.order_id = o.id
             WHERE s.sales_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];
if (!$include_exported) {
    $fromSql .= " AND s.exported = 0";
}

$selectSql = "SELECT s.*, c.code as customer_code, c.name as customer_name, c.accounting_code as customer_accounting_code, o.order_no";
$orderSql = " ORDER BY s.sales_date ASC, s.id ASC";

// 会計システム側の相手科目（売掛金・売上高）はマスタから取得する
$receivable = $pdo->query("SELECT code, name, tax_class_code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetch()
    ?: ['code' => '1310', 'name' => '売掛金', 'tax_class_code' => '0000'];
$salesAccount = $pdo->query("SELECT code, name, tax_class_code FROM accounts WHERE name LIKE '%売上高%' LIMIT 1")->fetch()
    ?: ['code' => '4110', 'name' => '売上高', 'tax_class_code' => '0060'];

$csvHeader = [
    '売上日', '売上番号', '請求書番号', '受注番号', '顧客コード', '顧客名', '顧客勘定科目コード',
    '借方科目コード', '借方科目名', '貸方科目コード', '貸方科目名', '税区分コード',
    '税抜金額', '消費税額', '合計金額', '摘要'
];

/**
 * 売上1件を会計連携用のCSV1行に変換する
 */
function buildExportRow(array $sale, array $receivable, array $salesAccount): array {
    return [
        date('Y/m/d', strtotime($sale['sales_date'])),
        $sale['sales_no'],
        $sale['invoice_no'] ?? '',
        $sale['order_no'] ?? '',
        $sale['customer_code'],
        $sale['customer_name'],
        $sale['customer_accounting_code'] ?? '',
        $receivable['code'],
        $receivable['name'],
        $salesAccount['code'],
        $salesAccount['name'],
        $salesAccount['tax_class_code'] ?? '',
        $sale['total_amount'] - $sale['tax_amount'],
        $sale['tax_amount'],
        $sale['total_amount'],
        "売上計上 {$sale['sales_no']} {$sale['customer_name']}",
    ];
}

if (isset($_GET['download'])) {
    $stmt = $pdo->prepare($selectSql . $fromSql . $orderSql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // Excelで開いたときに文字化けしないようUTF-8のBOMを先頭に付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($sales as $sale) {
        fputcsv($out, buildExportRow($sale, $receivable, $salesAccount), ',', '"', '');
    }
    fclose($out);

    if ($mark_exported && $sales) {
        $ids = array_column($sales, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute($ids);
    }
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(s.total_amount), 0)" . $fromSql);
$stmt->execute($params);
[$targetCount, $targetAmount] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = $pdo->prepare($selectSql . $fromSql . $orderSql . " LIMIT 10");
$stmt->execute($params);
$previewSales = $stmt->fetchAll();

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ戻る</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>" required>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="include_exported" name="include_exported" value="1" <?= $include_exported ? 'checked' : '' ?>>
                    <label class="form-check-label" for="include_exported">出力済も含める</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="mark_exported" name="mark_exported" value="1" <?= $mark_exported ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mark_exported">出力後に出力済にする</label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
                <button type="submit" name="download" value="1" class="btn btn-success" <?= $targetCount === 0 ? 'disabled' : '' ?>><i class="bi bi-download"></i> CSV出力</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象</span>
        <span class="text-muted small">対象<?= formatNumber($targetCount) ?>件 / 合計&yen;<?= formatNumber($targetAmount) ?>（先頭10件をプレビュー表示）</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <?php foreach ($csvHeader as $column): ?>
                        <th><?= h($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewSales as $sale): ?>
                    <tr>
                        <?php foreach (buildExportRow($sale, $receivable, $salesAccount) as $value): ?>
                        <td><?= h($value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($previewSales)): ?>
                    <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象の売上がありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">文字コードはUTF-8（BOM付き）です。Excelでそのまま開けます。</p>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
