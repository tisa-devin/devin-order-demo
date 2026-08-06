<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$exportedFilter = $_GET['exported'] ?? '';
$markExported = isset($_GET['mark_exported']);

$where = " WHERE s.sales_date >= ? AND s.sales_date <= ?";
$params = [$dateFrom, $dateTo];
if ($exportedFilter !== '') {
    $where .= " AND s.exported = ?";
    $params[] = $exportedFilter;
}

$sql = "
    SELECT s.*, o.order_no, c.code as customer_code, c.name as customer_name,
           c.accounting_code as customer_accounting_code
    FROM sales s
    JOIN orders o ON s.order_id = o.id
    JOIN customers c ON s.customer_id = c.id
" . $where . " ORDER BY s.sales_date ASC, s.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

$receivable = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetch()
    ?: ['code' => '1310', 'tax_class_code' => '0000'];
$salesAccount = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上高%' AND name NOT LIKE '%軽減%' LIMIT 1")->fetch()
    ?: ['code' => '4110', 'tax_class_code' => '0060'];

$csvHeader = [
    '売上番号', '売上日', '受注番号', '顧客コード', '顧客名', '顧客勘定科目コード',
    '借方勘定科目コード', '貸方勘定科目コード', '税区分コード',
    '売上金額', '消費税額', '税抜金額', '請求書番号', '検収番号', '摘要',
];

function csvRow(array $sale, array $receivable, array $salesAccount): array {
    return [
        $sale['sales_no'],
        date('Y/m/d', strtotime($sale['sales_date'])),
        $sale['order_no'],
        $sale['customer_code'],
        $sale['customer_name'],
        $sale['customer_accounting_code'] ?? '',
        $receivable['code'],
        $salesAccount['code'],
        $salesAccount['tax_class_code'] ?? '0060',
        (int)$sale['total_amount'],
        (int)$sale['tax_amount'],
        (int)$sale['total_amount'] - (int)$sale['tax_amount'],
        $sale['invoice_no'] ?? '',
        $sale['acceptance_no'] ?? '',
        "売上計上 {$sale['sales_no']} {$sale['customer_name']}",
    ];
}

if (isset($_GET['download'])) {
    if ($markExported && !empty($salesList)) {
        $ids = array_column($salesList, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    echo "\xEF\xBB\xBF";
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($salesList as $sale) {
        fputcsv($out, csvRow($sale, $receivable, $salesAccount), ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$totalAmount = array_sum(array_column($salesList, 'total_amount'));
$downloadQuery = http_build_query([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'exported' => $exportedFilter,
    'download' => 1,
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ戻る</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="date_from" class="form-label">売上日（自）</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="<?= h($dateFrom) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">売上日（至）</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="<?= h($dateTo) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="exported" class="form-label">出力状態</label>
                <select id="exported" name="exported" class="form-select">
                    <option value="">すべて</option>
                    <option value="0" <?= $exportedFilter === '0' ? 'selected' : '' ?>>未出力のみ</option>
                    <option value="1" <?= $exportedFilter === '1' ? 'selected' : '' ?>>出力済のみ</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>対象データ <?= formatNumber(count($salesList)) ?> 件 / 合計 &yen;<?= formatNumber($totalAmount) ?></span>
        <div>
            <?php if (!empty($salesList)): ?>
            <a href="export.php?<?= h($downloadQuery) ?>" class="btn btn-sm btn-success">
                <i class="bi bi-download"></i> CSVダウンロード
            </a>
            <a href="export.php?<?= h($downloadQuery) ?>&amp;mark_exported=1" class="btn btn-sm btn-outline-success"
               onclick="return confirm('CSVを出力し、対象データを出力済にしますか？')">
                <i class="bi bi-download"></i> CSVダウンロード（出力済にする）
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <?php foreach ($csvHeader as $col): ?>
                        <th><?= h($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesList as $sale): ?>
                    <tr>
                        <?php foreach (csvRow($sale, $receivable, $salesAccount) as $i => $value): ?>
                        <td class="<?= in_array($i, [9, 10, 11], true) ? 'text-end' : '' ?>"><?= h((string)$value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($salesList)): ?>
                    <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象データがありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">
            文字コードはUTF-8（BOM付き）です。Excelでそのまま開いても文字化けしません。
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
