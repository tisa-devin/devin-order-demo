<?php
// 一覧画面（見積・受注）共通の絞り込み処理

/**
 * GETパラメータから絞り込み条件を取得する
 */
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
 * 絞り込み条件から WHERE 句の追加分を組み立てる
 *
 * @param array $filters getListFilters() の戻り値
 * @param array $searchColumns フリーワード検索の対象カラム（例: ['e.estimate_no', 'e.subject', 'c.name']）
 * @param string $customerColumn 顧客名カラム（例: 'c.name'）
 * @param string $dateColumn 期間絞り込み対象の日付カラム（例: 'e.estimate_date'）
 * @param string $statusColumn ステータスカラム（例: 'e.status'）
 * @param array $params バインド値（参照渡しで追記される）
 */
function buildListFilterSql(array $filters, array $searchColumns, string $customerColumn, string $dateColumn, string $statusColumn, array &$params): string {
    $sql = '';
    if ($filters['search']) {
        $conditions = [];
        foreach ($searchColumns as $column) {
            $conditions[] = "$column LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
    }
    if ($filters['customer_name']) {
        $sql .= " AND $customerColumn LIKE ?";
        $params[] = '%' . $filters['customer_name'] . '%';
    }
    if ($filters['date_from']) {
        $sql .= " AND $dateColumn >= ?";
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to']) {
        $sql .= " AND $dateColumn <= ?";
        $params[] = $filters['date_to'];
    }
    if ($filters['status']) {
        $sql .= " AND $statusColumn = ?";
        $params[] = $filters['status'];
    }
    return $sql;
}

/**
 * 絞り込みフォームを描画する
 *
 * @param array $filters getListFilters() の戻り値
 * @param string $searchPlaceholder フリーワード検索欄のプレースホルダ
 * @param string $dateLabel 期間絞り込みのラベル（例: '受注日'）
 * @param array $statusLabels ステータスの定義（キー => ['label' => ..., 'class' => ...]）
 */
function renderListFilterForm(array $filters, string $searchPlaceholder, string $dateLabel, array $statusLabels): void {
?>
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="<?= h($searchPlaceholder) ?>" value="<?= h($filters['search']) ?>">
            </div>
            <div class="col-md-4">
                <input type="text" name="customer_name" class="form-control" placeholder="顧客名（部分一致）" value="<?= h($filters['customer_name']) ?>">
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><?= h($dateLabel) ?></span>
                    <input type="date" name="date_from" class="form-control" value="<?= h($filters['date_from']) ?>">
                    <span class="input-group-text">〜</span>
                    <input type="date" name="date_to" class="form-control" value="<?= h($filters['date_to']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">全てのステータス</option>
                    <?php foreach ($statusLabels as $key => $val): ?>
                    <option value="<?= h($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= h($val['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">検索</button>
                <a href="list.php" class="btn btn-outline-secondary">条件クリア</a>
            </div>
        </form>
    </div>
</div>
<?php
}
