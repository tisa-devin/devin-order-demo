<?php
const PER_PAGE = 20;

/**
 * 件数取得と LIMIT/OFFSET 付きの一覧取得をまとめて行う。
 * $fromWhere は "FROM ... WHERE ..." 部分（先頭の SELECT 句を含まない）。
 */
function fetchPaginated(PDO $pdo, string $selectColumns, string $fromWhere, string $orderBy, array $params, int $perPage = PER_PAGE): array {
    $countStmt = $pdo->prepare("SELECT COUNT(*) " . $fromWhere);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT " . $selectColumns . " " . $fromWhere . " ORDER BY " . $orderBy . " LIMIT ? OFFSET ?");
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows' => $stmt->fetchAll(),
        'page' => $page,
        'totalPages' => $totalPages,
        'totalCount' => $totalCount,
        'perPage' => $perPage,
        'offset' => $offset,
    ];
}

function paginationUrl(int $page): string {
    return 'list.php?' . http_build_query(array_merge($_GET, ['page' => $page]));
}

function renderPagination(array $pagination): void {
    $page = $pagination['page'];
    $totalPages = $pagination['totalPages'];
    $totalCount = $pagination['totalCount'];
    $offset = $pagination['offset'];
    if ($totalCount === 0) return;

    $start = max(1, $page - 2);
    $end = min($totalPages, $start + 4);
    $start = max(1, $end - 4);
    ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted">
            全 <?= formatNumber($totalCount) ?> 件中 <?= formatNumber($offset + 1) ?>～<?= formatNumber(min($offset + $pagination['perPage'], $totalCount)) ?> 件を表示
        </div>
        <?php if ($totalPages > 1): ?>
        <nav aria-label="ページネーション">
            <ul class="pagination mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(paginationUrl($page - 1)) ?>">前へ</a>
                </li>
                <?php if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= h(paginationUrl(1)) ?>">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h(paginationUrl($i)) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= h(paginationUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(paginationUrl($page + 1)) ?>">次へ</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php
}
