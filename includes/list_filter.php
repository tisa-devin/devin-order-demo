<?php
// 一覧画面（見積・受注）で共通利用する絞り込み条件の取得・SQL生成・フォーム描画

function getListFilters(): array {
    return [
        'search' => $_GET['search'] ?? '',
        'customer_name' => $_GET['customer_name'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];
}

/**
 * 絞り込み条件から WHERE 句の追加部分とバインド値を生成する。
 *
 * @param array $filters getListFilters() の戻り値
 * @param array $searchColumns 横断検索の対象カラム（例: ['o.order_no', 'o.subject', 'c.name']）
 * @param string $dateColumn 期間絞り込みの対象カラム（例: 'o.order_date'）
 * @param string $statusColumn ステータス絞り込みの対象カラム（例: 'o.status'）
 * @return array [SQL断片, バインド値の配列]
 */
function buildListFilterSql(array $filters, array $searchColumns, string $dateColumn, string $statusColumn): array {
    $sql = '';
    $params = [];

    if ($filters['search'] !== '') {
        $conditions = [];
        foreach ($searchColumns as $column) {
            $conditions[] = "$column LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
    }
    if ($filters['customer_name'] !== '') {
        $sql .= ' AND c.name LIKE ?';
        $params[] = '%' . $filters['customer_name'] . '%';
    }
    if ($filters['date_from'] !== '') {
        $sql .= " AND $dateColumn >= ?";
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= " AND $dateColumn <= ?";
        $params[] = $filters['date_to'];
    }
    if ($filters['status'] !== '') {
        $sql .= " AND $statusColumn = ?";
        $params[] = $filters['status'];
    }

    return [$sql, $params];
}

/**
 * 絞り込みフォームを描画する。
 *
 * @param array $filters getListFilters() の戻り値
 * @param array $statusLabels ステータス値 => ['label' => 表示名, ...] の配列
 * @param string $searchPlaceholder 横断検索欄のプレースホルダー
 * @param string $dateLabel 期間絞り込みの項目名（例: '受注日'）
 */
function renderListFilterForm(array $filters, array $statusLabels, string $searchPlaceholder, string $dateLabel): void {
?>
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="<?= h($searchPlaceholder) ?>" value="<?= h($filters['search']) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="customer_name" class="form-control" placeholder="顧客名（部分一致）" value="<?= h($filters['customer_name']) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" aria-label="<?= h($dateLabel) ?>（自）" value="<?= h($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" aria-label="<?= h($dateLabel) ?>（至）" value="<?= h($filters['date_to']) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">全てのステータス</option>
                    <?php foreach ($statusLabels as $key => $val): ?>
                    <option value="<?= h($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= h($val['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary">検索</button>
                <a href="list.php" class="btn btn-outline-secondary">クリア</a>
            </div>
        </form>
    </div>
</div>
<?php
}
