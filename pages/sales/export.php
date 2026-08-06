<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$dateFrom = $_REQUEST['date_from'] ?? date('Y-m-01');
$dateTo = $_REQUEST['date_to'] ?? date('Y-m-t');
$unexportedOnly = !empty($_REQUEST['unexported_only']);
$markExported = !empty($_POST['mark_exported']);

function fetchExportRows(PDO $pdo, string $dateFrom, string $dateTo, bool $unexportedOnly): array {
    $sql = "
        SELECT s.id, s.sales_no, s.sales_date, s.invoice_no, s.total_amount, s.tax_amount, s.exported,
               o.order_no, c.code as customer_code, c.name as customer_name,
               c.accounting_code as customer_accounting_code
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        JOIN orders o ON s.order_id = o.id
        WHERE s.sales_date >= ? AND s.sales_date <= ?
    ";
    $params = [$dateFrom, $dateTo];
    if ($unexportedOnly) {
        $sql .= " AND s.exported = 0";
    }
    $sql .= " ORDER BY s.sales_date ASC, s.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchAccount(PDO $pdo, string $nameLike, string $fallbackCode, string $fallbackTaxClass): array {
    $stmt = $pdo->prepare("SELECT code, tax_class_code FROM accounts WHERE name LIKE ? ORDER BY code LIMIT 1");
    $stmt->execute([$nameLike]);
    $row = $stmt->fetch();
    return [
        'code' => $row['code'] ?? $fallbackCode,
        'tax_class_code' => $row['tax_class_code'] ?? $fallbackTaxClass,
    ];
}

$csvHeader = [
    '売上日', '売上番号', '受注番号', '請求書番号',
    '顧客コード', '顧客名', '顧客勘定科目コード',
    '借方科目コード', '貸方科目コード', '税区分コード',
    '税抜金額', '消費税額', '税込金額', '摘要',
];

function buildCsvRow(array $row, array $receivable, array $salesAccount): array {
    return [
        date('Y/m/d', strtotime($row['sales_date'])),
        $row['sales_no'],
        $row['order_no'],
        $row['invoice_no'],
        $row['customer_code'],
        $row['customer_name'],
        $row['customer_accounting_code'],
        $receivable['code'],
        $salesAccount['code'],
        $salesAccount['tax_class_code'],
        (int)$row['total_amount'] - (int)$row['tax_amount'],
        (int)$row['tax_amount'],
        (int)$row['total_amount'],
        "売上計上 {$row['sales_no']} {$row['customer_name']}",
    ];
}

$receivable = fetchAccount($pdo, '%売掛金%', '1310', '0000');
$salesAccount = fetchAccount($pdo, '%売上高%', '4110', '0060');

if (($_POST['action'] ?? '') === 'download') {
    $rows = fetchExportRows($pdo, $dateFrom, $dateTo, $unexportedOnly);

    if ($markExported && !empty($rows)) {
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // Excel が UTF-8 と認識できるよう BOM を先頭に付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, buildCsvRow($row, $receivable, $salesAccount), ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = '売上CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$rows = fetchExportRows($pdo, $dateFrom, $dateTo, $unexportedOnly);
$totalAmount = array_sum(array_column($rows, 'total_amount'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 売上CSV出力（会計システム連携）</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>" required>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" name="unexported_only" value="1" id="unexportedOnly" class="form-check-input" <?= $unexportedOnly ? 'checked' : '' ?>>
                    <label class="form-check-label" for="unexportedOnly">未出力のみ</label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>対象データ <?= count($rows) ?>件（税込合計 &yen;<?= formatNumber($totalAmount) ?>）</span>
        <form method="post" class="d-flex align-items-center gap-3 mb-0">
            <input type="hidden" name="date_from" value="<?= h($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= h($dateTo) ?>">
            <input type="hidden" name="unexported_only" value="<?= $unexportedOnly ? '1' : '' ?>">
            <div class="form-check mb-0">
                <input type="checkbox" name="mark_exported" value="1" id="markExported" class="form-check-input">
                <label class="form-check-label" for="markExported">出力済にする</label>
            </div>
            <button type="submit" name="action" value="download" class="btn btn-sm btn-success" <?= empty($rows) ? 'disabled' : '' ?>>
                <i class="bi bi-download"></i> CSVダウンロード
            </button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <?php foreach ($csvHeader as $column): ?>
                        <th><?= h($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (buildCsvRow($row, $receivable, $salesAccount) as $i => $value): ?>
                        <td class="<?= in_array($i, [10, 11, 12], true) ? 'text-end' : '' ?>"><?= h(is_int($value) ? formatNumber($value) : $value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象データがありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
