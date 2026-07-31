<?php
// 一覧画面の検索・絞り込み機能の共通処理。
// 受注一覧・見積一覧など、キーワード／顧客名／日付期間／ステータスで
// 絞り込む一覧ページで共有する。

/**
 * GETパラメータから絞り込み条件を取得する。
 *
 * @return array{search:string, customer_name:string, date_from:string, date_to:string, status:string}
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
 * 絞り込み条件に応じてWHERE句とバインドパラメータを組み立てる。
 *
 * @param array  $filters getListFilters() の戻り値
 * @param array  $config  カラム設定:
 *   - keyword_columns: array キーワード検索対象カラム（OR結合）
 *   - customer_column: string 顧客名カラム
 *   - date_column:     string 日付期間の対象カラム
 *   - status_column:   string ステータスカラム
 * @param array  $params  バインドパラメータ（参照渡しで追記される）
 * @return string WHERE句に連結するSQL断片（先頭にスペース付き）
 */
function buildListFilterSql(array $filters, array $config, array &$params): string {
    $sql = '';

    if ($filters['search'] !== '') {
        $conds = [];
        foreach ($config['keyword_columns'] as $col) {
            $conds[] = "$col LIKE ?";
            $params[] = "%{$filters['search']}%";
        }
        $sql .= ' AND (' . implode(' OR ', $conds) . ')';
    }
    if ($filters['customer_name'] !== '') {
        $sql .= " AND {$config['customer_column']} LIKE ?";
        $params[] = "%{$filters['customer_name']}%";
    }
    if ($filters['date_from'] !== '') {
        $sql .= " AND {$config['date_column']} >= ?";
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $sql .= " AND {$config['date_column']} <= ?";
        $params[] = $filters['date_to'];
    }
    if ($filters['status'] !== '') {
        $sql .= " AND {$config['status_column']} = ?";
        $params[] = $filters['status'];
    }

    return $sql;
}

/**
 * 絞り込みフォームを描画する。
 *
 * @param array  $filters            getListFilters() の戻り値
 * @param array  $statusLabels       ['key' => ['label' => '...', ...]] 形式のステータス定義
 * @param string $keywordPlaceholder キーワード入力欄のプレースホルダ
 * @param string $dateLabel          日付期間欄のラベル（例「受注日」「見積日」）
 */
function renderListFilterForm(array $filters, array $statusLabels, string $keywordPlaceholder, string $dateLabel): void {
?>
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">キーワード</label>
                <input type="text" name="search" class="form-control" placeholder="<?= h($keywordPlaceholder) ?>" value="<?= h($filters['search']) ?>">
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
                <button type="submit" class="btn btn-outline-primary">検索</button>
                <a href="list.php" class="btn btn-outline-secondary ms-2">クリア</a>
            </div>
        </form>
    </div>
</div>
<?php
}
