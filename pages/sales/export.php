<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$exported = trim((string)($_GET['exported'] ?? ''));
$download = ($_GET['download'] ?? '') === '1';

$sql = "SELECT s.*, c.name AS customer_name, c.accounting_code AS customer_accounting_code, o.order_no
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        JOIN orders o ON s.order_id = o.id
        WHERE 1=1";
$params = [];

if ($dateFrom !== '') {
    $sql .= " AND s.sales_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND s.sales_date <= ?";
    $params[] = $dateTo;
}
if ($exported !== '') {
    $sql .= " AND s.exported = ?";
    $params[] = $exported;
}

$sql .= " ORDER BY s.sales_date ASC, s.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

$accountingCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売上%' LIMIT 1")->fetchColumn() ?: '4110';

/** 出力可能な列の定義（キー => [見出し, 値を返すクロージャ]） */
$columnDefs = [
    'sales_no' => ['売上番号', fn(array $s) => $s['sales_no']],
    'sales_date' => ['売上日', fn(array $s) => date('Y/m/d', strtotime($s['sales_date']))],
    'customer_code' => ['顧客コード', fn(array $s) => $s['customer_accounting_code'] ?? ''],
    'customer_name' => ['顧客名', fn(array $s) => $s['customer_name']],
    'accounting_code' => ['勘定科目コード', fn(array $s) => $accountingCode],
    'order_no' => ['受注番号', fn(array $s) => $s['order_no']],
    'invoice_no' => ['請求書番号', fn(array $s) => $s['invoice_no'] ?? ''],
    'amount_excl_tax' => ['税抜金額', fn(array $s) => (int)$s['total_amount'] - (int)$s['tax_amount']],
    'tax_amount' => ['消費税額', fn(array $s) => (int)$s['tax_amount']],
    'total_amount' => ['合計金額', fn(array $s) => (int)$s['total_amount']],
    'note' => ['摘要', fn(array $s) => "売上計上 {$s['sales_no']}"],
];

$requestedColumns = $_GET['columns'] ?? null;
$selectedColumns = is_array($requestedColumns)
    ? array_values(array_intersect(array_keys($columnDefs), $requestedColumns))
    : array_keys($columnDefs);
if (empty($selectedColumns)) {
    $selectedColumns = array_keys($columnDefs);
}

$csvHeader = array_map(fn($key) => $columnDefs[$key][0], $selectedColumns);

function buildExportRow(array $sale, array $columnDefs, array $selectedColumns): array
{
    return array_map(fn($key) => $columnDefs[$key][1]($sale), $selectedColumns);
}

if ($download) {
    $filename = 'sales_accounting_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // Excelで文字化けしないようUTF-8 BOMを付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '', "\r\n");
    foreach ($salesList as $sale) {
        fputcsv($out, buildExportRow($sale, $columnDefs, $selectedColumns), ',', '"', '', "\r\n");
    }
    fclose($out);
    exit;
}

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$downloadQuery = http_build_query([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'exported' => $exported,
    'columns' => $selectedColumns,
    'download' => '1',
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3" id="exportForm">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">出力状態</label>
                <select name="exported" class="form-select">
                    <option value="">すべて</option>
                    <option value="0" <?= $exported === '0' ? 'selected' : '' ?>>未出力</option>
                    <option value="1" <?= $exported === '1' ? 'selected' : '' ?>>出力済</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">絞り込み</button>
                <a href="export.php?reset_columns=1" class="btn btn-outline-secondary">条件クリア</a>
            </div>
            <div class="col-12">
                <label class="form-label">出力する列</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($columnDefs as $key => [$label, $unused]): ?>
                    <div class="form-check">
                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="<?= h($key) ?>" id="col_<?= h($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="col_<?= h($key) ?>"><?= h($label) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllColumns">全選択</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllColumns">全解除</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象 <?= count($salesList) ?> 件（UTF-8 BOM付きCSV）</span>
        <a href="export.php?<?= h($downloadQuery) ?>" id="downloadCsv" class="btn btn-sm btn-success <?= empty($salesList) ? 'disabled' : '' ?>">
            <i class="bi bi-download"></i> CSVダウンロード
        </a>
    </div>
    <div class="card-body">
        <table class="table table-striped">
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
                    <?php foreach (buildExportRow($sale, $columnDefs, $selectedColumns) as $value): ?>
                    <td><?= h((string)$value) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($salesList)): ?>
                <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const STORAGE_KEY = 'salesExportColumns';
    const checks = Array.from(document.querySelectorAll('.column-check'));
    const params = new URLSearchParams(location.search);

    if (params.has('reset_columns')) {
        localStorage.removeItem(STORAGE_KEY);
    } else if (!params.has('columns[]')) {
        // 前回選んだ列構成を初期値として復元する
        const saved = localStorage.getItem(STORAGE_KEY);
        const keys = saved ? JSON.parse(saved) : null;
        if (Array.isArray(keys) && keys.length > 0 && keys.length < checks.length) {
            checks.forEach(cb => { cb.checked = keys.includes(cb.value); });
            // プレビューを復元した列構成に揃えるため一度だけ再送信する
            document.getElementById('exportForm').submit();
            return;
        }
    }

    function save() {
        const keys = checks.filter(cb => cb.checked).map(cb => cb.value);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
        return keys;
    }

    checks.forEach(cb => cb.addEventListener('change', save));
    document.getElementById('selectAllColumns').addEventListener('click', function () {
        checks.forEach(cb => { cb.checked = true; });
        save();
    });
    document.getElementById('clearAllColumns').addEventListener('click', function () {
        checks.forEach(cb => { cb.checked = false; });
        save();
    });

    // チェック状態を復元した直後のダウンロードリンクを実際の選択列に合わせる
    const downloadLink = document.getElementById('downloadCsv');
    downloadLink.addEventListener('click', function (e) {
        const keys = save();
        const url = new URL(downloadLink.href, location.href);
        url.searchParams.delete('columns[]');
        keys.forEach(k => url.searchParams.append('columns[]', k));
        downloadLink.href = url.toString();
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
