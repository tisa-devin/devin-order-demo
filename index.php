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

$monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("first day of -$i month"));
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
    if (array_key_exists($row['month'], $monthlyTrend)) {
        $monthlyTrend[$row['month']] = (int)$row['total'];
    }
}
$trendLabels = array_map(fn($m) => date('Y/n月', strtotime($m . '-01')), array_keys($monthlyTrend));

$orderStatusLabels = [
    'ordered' => ['label' => '受注', 'color' => 'rgba(13, 110, 253, 0.7)'],
    'in_progress' => ['label' => '進行中', 'color' => 'rgba(255, 193, 7, 0.7)'],
    'completed' => ['label' => '完了', 'color' => 'rgba(25, 135, 84, 0.7)'],
    'cancelled' => ['label' => 'キャンセル', 'color' => 'rgba(220, 53, 69, 0.7)'],
];
$statusCounts = array_fill_keys(array_keys($orderStatusLabels), 0);
foreach ($pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status") as $row) {
    if (array_key_exists($row['status'], $statusCounts)) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }
}
$statusTotal = array_sum($statusCounts);
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

<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-bar-chart"></i> 月次売上推移（直近6ヶ月）
            </div>
            <div class="card-body">
                <canvas id="monthlySalesChart" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-pie-chart"></i> 受注ステータス別件数
            </div>
            <div class="card-body">
                <?php if ($statusTotal === 0): ?>
                    <p class="text-muted mb-0">受注データがありません</p>
                <?php else: ?>
                    <canvas id="orderStatusChart" height="260"></canvas>
                <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var formatter = new Intl.NumberFormat('ja-JP');
        new Chart(document.getElementById('monthlySalesChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: '売上高',
                    data: <?= json_encode(array_values($monthlyTrend)) ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.6)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return '¥' + formatter.format(ctx.parsed.y); }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) { return '¥' + formatter.format(value); }
                        }
                    }
                }
            }
        });

        var statusCanvas = document.getElementById('orderStatusChart');
        if (statusCanvas) {
            new Chart(statusCanvas, {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($orderStatusLabels, 'label'), JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($statusCounts)) ?>,
                        backgroundColor: <?= json_encode(array_column($orderStatusLabels, 'color')) ?>

                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    var pct = total ? Math.round(ctx.parsed / total * 100) : 0;
                                    return ctx.label + ': ' + formatter.format(ctx.parsed) + '件 (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
