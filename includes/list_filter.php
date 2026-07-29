<?php
/**
 * 一覧画面の絞り込み（キーワード / 顧客名部分一致 / 日付期間 / ステータス）の共通処理。
 * 条件は GET パラメータ search / customer_name / date_from / date_to / status で保持する。
 */

function listFilterValues(): array {
    return [
        'search' => $_GET['search'] ?? '',
        'customer_name' => $_GET['customer_name'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];
}

/**
 * @param array $columns keyword（LIKE対象の列名配列） / customer / date / status のテーブル別列名
 * @return array{0: string, 1: array} WHERE への追記文字列とバインド値
 */
function listFilterCondition(array $columns): array {
    $values = listFilterValues();
    $where = '';
    $params = [];

    if ($values['search'] !== '' && !empty($columns['keyword'])) {
        $where .= ' AND (' . implode(' LIKE ? OR ', $columns['keyword']) . ' LIKE ?)';
        foreach ($columns['keyword'] as $ignored) {
            $params[] = '%' . $values['search'] . '%';
        }
    }
    if ($values['customer_name'] !== '' && !empty($columns['customer'])) {
        $where .= ' AND ' . $columns['customer'] . ' LIKE ?';
        $params[] = '%' . $values['customer_name'] . '%';
    }
    if ($values['date_from'] !== '' && !empty($columns['date'])) {
        $where .= ' AND ' . $columns['date'] . ' >= ?';
        $params[] = $values['date_from'];
    }
    if ($values['date_to'] !== '' && !empty($columns['date'])) {
        $where .= ' AND ' . $columns['date'] . ' <= ?';
        $params[] = $values['date_to'];
    }
    if ($values['status'] !== '' && !empty($columns['status'])) {
        $where .= ' AND ' . $columns['status'] . ' = ?';
        $params[] = $values['status'];
    }

    return [$where, $params];
}

function renderListFilter(array $statusLabels, string $keywordPlaceholder, string $dateLabel): void {
    $values = listFilterValues();
    $selfPath = basename($_SERVER['PHP_SELF']);
    ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">キーワード</label>
                    <input type="text" name="search" class="form-control" placeholder="<?= h($keywordPlaceholder) ?>" value="<?= h($values['search']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">顧客名（部分一致）</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="顧客名" value="<?= h($values['customer_name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">ステータス</label>
                    <select name="status" class="form-select">
                        <option value="">全てのステータス</option>
                        <?php foreach ($statusLabels as $key => $val): ?>
                        <option value="<?= h($key) ?>" <?= $values['status'] === (string)$key ? 'selected' : '' ?>><?= h($val['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1"><?= h($dateLabel) ?>（自）</label>
                    <input type="date" name="date_from" class="form-control" value="<?= h($values['date_from']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1"><?= h($dateLabel) ?>（至）</label>
                    <input type="date" name="date_to" class="form-control" value="<?= h($values['date_to']) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">検索</button>
                    <a href="<?= h($selfPath) ?>" class="btn btn-outline-secondary">条件クリア</a>
                </div>
            </form>
        </div>
    </div>
    <?php
}
