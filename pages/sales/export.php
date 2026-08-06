<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

const EXPORT_COLS_COOKIE = 'sales_export_cols';

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$exportedFilter = $_GET['exported'] ?? '';
$markExported = isset($_GET['mark_exported']);

$receivable = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetch()
    ?: ['code' => '1310', 'tax_class_code' => '0000'];
$salesAccount = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上高%' AND name NOT LIKE '%軽減%' LIMIT 1")->fetch()
    ?: ['code' => '4110', 'tax_class_code' => '0060'];

$columns = [
    'sales_no' => ['label' => '売上番号', 'value' => fn($s) => $s['sales_no']],
    'sales_date' => ['label' => '売上日', 'value' => fn($s) => date('Y/m/d', strtotime($s['sales_date']))],
    'order_no' => ['label' => '受注番号', 'value' => fn($s) => $s['order_no']],
    'customer_code' => ['label' => '顧客コード', 'value' => fn($s) => $s['customer_code']],
    'customer_name' => ['label' => '顧客名', 'value' => fn($s) => $s['customer_name']],
    'customer_accounting_code' => ['label' => '顧客勘定科目コード', 'value' => fn($s) => $s['customer_accounting_code'] ?? ''],
    'debit_code' => ['label' => '借方勘定科目コード', 'value' => fn($s) => $receivable['code']],
    'credit_code' => ['label' => '貸方勘定科目コード', 'value' => fn($s) => $salesAccount['code']],
    'tax_class_code' => ['label' => '税区分コード', 'value' => fn($s) => $salesAccount['tax_class_code'] ?? '0060'],
    'total_amount' => ['label' => '売上金額', 'value' => fn($s) => (int)$s['total_amount'], 'numeric' => true],
    'tax_amount' => ['label' => '消費税額', 'value' => fn($s) => (int)$s['tax_amount'], 'numeric' => true],
    'net_amount' => ['label' => '税抜金額', 'value' => fn($s) => (int)$s['total_amount'] - (int)$s['tax_amount'], 'numeric' => true],
    'invoice_no' => ['label' => '請求書番号', 'value' => fn($s) => $s['invoice_no'] ?? ''],
    'acceptance_no' => ['label' => '検収番号', 'value' => fn($s) => $s['acceptance_no'] ?? ''],
    'notes' => ['label' => '摘要', 'value' => fn($s) => "売上計上 {$s['sales_no']} {$s['customer_name']}"],
];

$allColumnKeys = array_keys($columns);

function resolveSelectedColumns(array $allColumnKeys): array {
    if (isset($_GET['cols_submitted'])) {
        $selected = array_values(array_intersect($allColumnKeys, (array)($_GET['cols'] ?? [])));
        return $selected ?: $allColumnKeys;
    }
    if (isset($_COOKIE[EXPORT_COLS_COOKIE])) {
        $saved = json_decode($_COOKIE[EXPORT_COLS_COOKIE], true);
        if (is_array($saved)) {
            $selected = array_values(array_intersect($allColumnKeys, $saved));
            if ($selected) return $selected;
        }
    }
    return $allColumnKeys;
}

$selectedCols = resolveSelectedColumns($allColumnKeys);

if (isset($_GET['cols_submitted'])) {
    setcookie(EXPORT_COLS_COOKIE, json_encode($selectedCols), [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => BASE_PATH,
        'samesite' => 'Lax',
    ]);
}

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

$csvHeader = array_map(fn($key) => $columns[$key]['label'], $selectedCols);

function csvRow(array $sale, array $selectedCols, array $columns): array {
    return array_map(fn($key) => $columns[$key]['value']($sale), $selectedCols);
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
        fputcsv($out, csvRow($sale, $selectedCols, $columns), ',', '"', '');
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
    'cols_submitted' => 1,
    'cols' => $selectedCols,
    'download' => 1,
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ戻る</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="cols_submitted" value="1">
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
            <div class="col-12">
                <label class="form-label mb-1">出力する列</label>
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-1">
                    <?php foreach ($columns as $key => $col): ?>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input col-check" type="checkbox" name="cols[]" value="<?= h($key) ?>"
                                   id="col_<?= h($key) ?>" <?= in_array($key, $selectedCols, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="col_<?= h($key) ?>"><?= h($col['label']) ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="checkAllCols">全て選択</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="uncheckAllCols">全て解除</button>
                    <span class="text-muted small ms-2">選択した列構成は次回アクセス時の初期値として保存されます（1列も選ばない場合は全列）</span>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary">この条件で表示</button>
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
                        <?php foreach (csvRow($sale, $selectedCols, $columns) as $i => $value): ?>
                        <td class="<?= !empty($columns[$selectedCols[$i]]['numeric']) ? 'text-end' : '' ?>"><?= h((string)$value) ?></td>
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

<script>
    document.getElementById('checkAllCols').addEventListener('click', function () {
        document.querySelectorAll('.col-check').forEach(function (cb) { cb.checked = true; });
    });
    document.getElementById('uncheckAllCols').addEventListener('click', function () {
        document.querySelectorAll('.col-check').forEach(function (cb) { cb.checked = false; });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
