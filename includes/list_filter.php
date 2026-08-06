<?php
/**
 * 一覧画面（見積・受注）で共通利用する絞り込み条件のヘルパー。
 * 条件はGETパラメータ search / customer / date_from / date_to / status で保持する。
 */

function getListFilters(): array {
    return [
        'search' => $_GET['search'] ?? '',
        'customer' => $_GET['customer'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];
}

/**
 * 絞り込み条件からWHERE句とバインド値を組み立てる。
 *
 * @param array $filters getListFilters() の戻り値
 * @param array $searchColumns キーワード検索の対象カラム（例: ['e.estimate_no', 'e.subject', 'c.name']）
 * @param string $dateColumn 期間絞り込みの対象カラム（例: 'e.estimate_date'）
 * @param string $statusColumn ステータスのカラム（例: 'e.status'）
 * @param string $customerColumn 顧客名のカラム（例: 'c.name'）
 * @return array{0: string, 1: array} [WHERE句に追記するSQL, バインド値]
 */
function buildListFilterSql(
    array $filters,
    array $searchColumns,
    string $dateColumn,
    string $statusColumn,
    string $customerColumn = 'c.name'
): array {
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
    if ($filters['customer'] !== '') {
        $sql .= " AND $customerColumn LIKE ?";
        $params[] = '%' . $filters['customer'] . '%';
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
 * 絞り込みフォームを出力する。
 *
 * @param array $filters getListFilters() の戻り値
 * @param array $statusLabels ステータスコード => ['label' => 表示名, ...]
 * @param string $searchPlaceholder キーワード欄のプレースホルダ
 * @param string $dateLabel 期間欄のラベル（例: '見積日'）
 */
function renderListFilterForm(
    array $filters,
    array $statusLabels,
    string $searchPlaceholder,
    string $dateLabel
): void {
    ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">キーワード</label>
                    <input type="text" name="search" class="form-control" placeholder="<?= h($searchPlaceholder) ?>" value="<?= h($filters['search']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">顧客名（部分一致）</label>
                    <input type="text" name="customer" class="form-control" placeholder="顧客名" value="<?= h($filters['customer']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ステータス</label>
                    <select name="status" class="form-select">
                        <option value="">全てのステータス</option>
                        <?php foreach ($statusLabels as $key => $val): ?>
                        <option value="<?= h($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= h($val['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h($dateLabel) ?>（自）</label>
                    <input type="date" name="date_from" class="form-control" value="<?= h($filters['date_from']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= h($dateLabel) ?>（至）</label>
                    <input type="date" name="date_to" class="form-control" value="<?= h($filters['date_to']) ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-outline-primary">検索</button>
                    <a href="list.php" class="btn btn-outline-secondary">条件クリア</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
