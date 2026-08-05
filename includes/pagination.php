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
 * Bootstrap のページネーションを描画する
 */
function renderPagination(int $currentPage, int $totalCount, int $perPage = PER_PAGE): void {
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
