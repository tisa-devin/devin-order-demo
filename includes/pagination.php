<?php
// 一覧画面共通のページネーション処理

const PER_PAGE = 20;

/**
 * 現在のページ番号をGETパラメータから取得する（1以上）
 */
function getCurrentPage(): int {
    return max(1, (int)($_GET['page'] ?? 1));
}

/**
 * ページ番号以外の絞り込み条件を引き継いだページリンクのURLを組み立てる
 */
function pageUrl(int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

/**
 * 件数を数えた上で現在ページ分のレコードを取得する
 *
 * @param string $selectSql SELECT句（例: 'SELECT o.*, c.name as customer_name'）
 * @param string $fromSql FROM以降のWHERE句まで（件数取得と共用）
 * @param string $orderSql ORDER BY句
 * @param array $params 絞り込み条件のバインド値
 * @return array ['rows' => 取得行, 'total' => 全件数, 'page' => 最終ページを超えないよう補正したページ番号]
 */
function fetchPaginated(PDO $pdo, string $selectSql, string $fromSql, string $orderSql, array $params, int $currentPage): array {
    $countStmt = $pdo->prepare('SELECT COUNT(*)' . $fromSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $currentPage = min($currentPage, max(1, (int)ceil($totalCount / PER_PAGE)));

    $stmt = $pdo->prepare($selectSql . $fromSql . $orderSql . ' LIMIT ? OFFSET ?');
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, ($currentPage - 1) * PER_PAGE, PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'total' => $totalCount, 'page' => $currentPage];
}

/**
 * 表示件数の案内と Bootstrap のページネーションを描画する
 */
function renderPagination(int $currentPage, int $totalCount, int $perPage = PER_PAGE): void {
    if ($totalCount > 0) {
        $offset = ($currentPage - 1) * $perPage;
        echo '<div class="text-muted small">全' . formatNumber($totalCount) . '件中 '
            . formatNumber($offset + 1) . '～' . formatNumber(min($offset + $perPage, $totalCount)) . '件を表示</div>';
    }
    $totalPages = (int)ceil($totalCount / $perPage);
    if ($totalPages <= 1) return;
?>
<nav class="mt-3">
    <ul class="pagination justify-content-center mb-0">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(pageUrl($currentPage - 1)) ?>">前へ</a>
        </li>
        <?php for ($page = 1; $page <= $totalPages; $page++): ?>
        <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="<?= h(pageUrl($page)) ?>"><?= $page ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(pageUrl($currentPage + 1)) ?>">次へ</a>
        </li>
    </ul>
</nav>
<?php
}
