<?php
/**
 * 一覧画面の絞り込み条件（キーワード・顧客名・日付期間・ステータス）を
 * GETパラメータから組み立てる共通処理。
 */

/**
 * GETパラメータから絞り込み条件を取得する。
 */
function getListFilterValues(): array
{
    return [
        'search' => trim((string)($_GET['search'] ?? '')),
        'customer_name' => trim((string)($_GET['customer_name'] ?? '')),
        'date_from' => trim((string)($_GET['date_from'] ?? '')),
        'date_to' => trim((string)($_GET['date_to'] ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
    ];
}

/**
 * 絞り込み条件から SQL の AND 句とバインドパラメータを生成する。
 *
 * @param array $filters getListFilterValues() の戻り値
 * @param array $searchColumns キーワード検索対象のカラム（例: ['e.estimate_no', 'e.subject', 'c.name']）
 * @param string $dateColumn 期間検索対象の日付カラム（例: 'e.estimate_date'）
 * @param string $statusColumn ステータスカラム（例: 'e.status'）
 * @return array{0: string, 1: array} SQL断片とパラメータ配列
 */
function buildListFilterSql(array $filters, array $searchColumns, string $dateColumn, string $statusColumn): array
{
    $sql = '';
    $params = [];

    if ($filters['search'] !== '' && $searchColumns) {
        $conditions = array_map(fn($col) => "$col LIKE ?", $searchColumns);
        $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        foreach ($searchColumns as $unused) {
            $params[] = '%' . $filters['search'] . '%';
        }
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
 * @param array $filters getListFilterValues() の戻り値
 * @param array $statusLabels ステータス値 => ['label' => ..., 'class' => ...]
 * @param string $searchPlaceholder キーワード入力欄のプレースホルダ
 * @param string $dateLabel 日付項目のラベル（例: '見積日'）
 */
function renderListFilterForm(array $filters, array $statusLabels, string $searchPlaceholder, string $dateLabel): void
{
    ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">キーワード</label>
                    <input type="text" name="search" class="form-control" placeholder="<?= h($searchPlaceholder) ?>" value="<?= h($filters['search']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">顧客名</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="顧客名（部分一致）" value="<?= h($filters['customer_name']) ?>">
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
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">検索</button>
                    <a href="list.php" class="btn btn-outline-secondary">条件クリア</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
