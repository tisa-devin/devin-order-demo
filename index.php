<?php
$pageTitle = 'ダッシュボード';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

$currentMonth = date('Y-m');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE strftime('%Y-%m', sales_date) = ?");
$stmt->execute([$currentMonth]);
$monthlySales = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM sales WHERE strftime('%Y-%m', sales_date) = '$currentMonth'");
$salesCount = $stmt->fetch()['count'];

// 直近6ヶ月の月次売上（実績のない月は0円として表示）
$monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i month"));
    $monthlyTrend[$month] = 0;
}
$stmt = $pdo->prepare("
    SELECT strftime('%Y-%m', sales_date) as month, COALESCE(SUM(total_amount), 0) as total
    FROM sales
    WHERE strftime('%Y-%m', sales_date) >= ?
    GROUP BY month
");
$stmt->execute([array_key_first($monthlyTrend)]);
foreach ($stmt->fetchAll() as $row) {
    if (isset($monthlyTrend[$row['month']])) {
        $monthlyTrend[$row['month']] = (int)$row['total'];
    }
}
$trendLabels = array_map(fn($month) => date('Y/m', strtotime($month . '-01')), array_keys($monthlyTrend));

// 受注ステータス別の件数
$orderStatusLabels = [
    'ordered' => ['label' => '受注', 'color' => '#0d6efd'],
    'in_progress' => ['label' => '進行中', 'color' => '#ffc107'],
    'completed' => ['label' => '完了', 'color' => '#198754'],
    'cancelled' => ['label' => 'キャンセル', 'color' => '#dc3545'],
];
$statusCounts = array_fill_keys(array_keys($orderStatusLabels), 0);
foreach ($pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status") as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }
}

$stmt = $pdo->query("
    SELECT o.*, c.name as customer_name 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    WHERE o.status IN ('ordered', 'in_progress') 
    ORDER BY o.delivery_date ASC 
    LIMIT 10
");
$pendingOrders = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT od.*, o.order_no, c.name as customer_name
    FROM order_details od
    JOIN orders o ON od.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    WHERE od.purchase_status = 'none'
    ORDER BY o.order_date ASC
    LIMIT 10
");
$pendingPurchases = $stmt->fetchAll();
?>

<h2 class="mb-4"><i class="bi bi-speedometer2"></i> ダッシュボード</h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">今月の売上</h5>
                <h2 class="mb-0">&yen;<?= formatNumber($monthlySales) ?></h2>
                <small><?= $salesCount ?>件</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning">
            <div class="card-body">
                <h5 class="card-title">未完了受注</h5>
                <h2 class="mb-0"><?= count($pendingOrders) ?>件</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">発注待ち明細</h5>
                <h2 class="mb-0"><?= count($pendingPurchases) ?>件</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-bar-chart"></i> 月次売上推移（直近6ヶ月）
            </div>
            <div class="card-body">
                <canvas id="monthlySalesChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-pie-chart"></i> 受注ステータス別件数
            </div>
            <div class="card-body">
                <canvas id="orderStatusChart" height="240"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-list-check"></i> 未完了の受注一覧
            </div>
            <div class="card-body">
                <?php if (empty($pendingOrders)): ?>
                    <p class="text-muted">未完了の受注はありません</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>受注番号</th>
                                <th>顧客名</th>
                                <th>納期</th>
                                <th>金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingOrders as $order): ?>
                            <tr>
                                <td><a href="<?= BASE_PATH ?>/pages/orders/edit.php?id=<?= $order['id'] ?>"><?= h($order['order_no']) ?></a></td>
                                <td><?= h($order['customer_name']) ?></td>
                                <td><?= formatDate($order['delivery_date']) ?></td>
                                <td class="text-end">&yen;<?= formatNumber($order['total_amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-cart"></i> 発注待ち明細一覧
            </div>
            <div class="card-body">
                <?php if (empty($pendingPurchases)): ?>
                    <p class="text-muted">発注待ちの明細はありません</p>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>受注番号</th>
                                <th>顧客名</th>
                                <th>品名</th>
                                <th>数量</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingPurchases as $item): ?>
                            <tr>
                                <td><?= h($item['order_no']) ?></td>
                                <td><?= h($item['customer_name']) ?></td>
                                <td><?= h($item['item_name']) ?></td>
                                <td><?= h($item['quantity']) ?><?= h($item['unit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // 金額は3桁区切りで表示する
    const formatYen = value => '\u00a5' + Number(value).toLocaleString('ja-JP');

    new Chart(document.getElementById('monthlySalesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: '売上金額',
                data: <?= json_encode(array_values($monthlyTrend)) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.6)'
            }]
        },
        options: {
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: context => formatYen(context.parsed.y) } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: value => formatYen(value) } }
            }
        }
    });

    new Chart(document.getElementById('orderStatusChart'), {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_column($orderStatusLabels, 'label'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statusCounts)) ?>,
                backgroundColor: <?= json_encode(array_column($orderStatusLabels, 'color')) ?>
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: context => context.label + ': ' + Number(context.parsed).toLocaleString('ja-JP') + '件' } }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
