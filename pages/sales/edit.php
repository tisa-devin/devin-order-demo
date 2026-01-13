<?php
$pageTitle = '売上登録・編集';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

$id = $_GET['id'] ?? null;
$order_id = $_GET['order_id'] ?? null;
$sale = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT s.*, o.order_no, o.subject, o.total_amount as order_total, o.tax_amount as order_tax FROM sales s JOIN orders o ON s.order_id = o.id WHERE s.id = ?");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
    if ($sale) {
        $order_id = $sale['order_id'];
    }
}

$orderDetails = [];
if ($order_id) {
    $stmt = $pdo->prepare("SELECT * FROM order_details WHERE order_id = ? ORDER BY line_no");
    $stmt->execute([$order_id]);
    $orderDetails = $stmt->fetchAll();
}

$orderData = null;
if ($order_id && !$sale) {
    $stmt = $pdo->prepare("SELECT o.*, c.name as customer_name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.id = ? AND o.status != 'cancelled'");
    $stmt->execute([$order_id]);
    $orderData = $stmt->fetch();
    
    if ($orderData) {
        $sale = [
            'order_id' => $orderData['id'],
            'customer_id' => $orderData['customer_id'],
            'total_amount' => $orderData['total_amount'],
            'tax_amount' => $orderData['tax_amount'],
            'order_no' => $orderData['order_no'],
            'subject' => $orderData['subject']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $order_id = $_POST['order_id'] ?? '';
        $customer_id = $_POST['customer_id'] ?? '';
        $sales_date = $_POST['sales_date'] ?? '';
        $total_amount = $_POST['total_amount'] ?? 0;
        $tax_amount = $_POST['tax_amount'] ?? 0;
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($order_id) || empty($customer_id) || empty($sales_date)) {
            $error = '受注、顧客、売上日は必須です';
        } else {
            try {
                $pdo->beginTransaction();
                
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE sales SET sales_date = ?, total_amount = ?, tax_amount = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$sales_date, $total_amount, $tax_amount, $notes, $id]);
                } else {
                    $sales_no = getNextNumber('sales');
                    $invoice_no = getNextNumber('invoice');
                    $acceptance_no = getNextNumber('acceptance');
                    
                    $stmt = $pdo->prepare("INSERT INTO sales (sales_no, order_id, customer_id, sales_date, total_amount, tax_amount, invoice_no, acceptance_no, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$sales_no, $order_id, $customer_id, $sales_date, $total_amount, $tax_amount, $invoice_no, $acceptance_no, $notes]);
                    $id = $pdo->lastInsertId();
                }
                
                $pdo->commit();
                header("Location: list.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'エラーが発生しました: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && $id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: list.php");
            exit;
        } catch (Exception $e) {
            $error = '削除できません';
        }
    }
}

$stmt = $pdo->query("SELECT o.id, o.order_no, o.total_amount, o.tax_amount, o.customer_id, c.name as customer_name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.status != 'cancelled' ORDER BY o.order_date DESC");
$completedOrders = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up"></i> <?= $id ? '売上編集' : '新規売上' ?></h2>
    <a href="list.php" class="btn btn-outline-secondary">一覧に戻る</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" id="salesForm">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="order_id" value="<?= h($order_id) ?>">
    <input type="hidden" name="customer_id" value="<?= h($sale['customer_id'] ?? '') ?>">
    
    <div class="card mb-4">
        <div class="card-header">基本情報</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">売上番号</label>
                    <input type="text" class="form-control" value="<?= h($sale['sales_no'] ?? '（自動採番）') ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">受注</label>
                    <?php if ($order_id): ?>
                    <input type="text" class="form-control" value="<?= h($sale['order_no'] ?? '') ?>" readonly>
                    <?php else: ?>
                    <select name="order_id" class="form-select" id="orderSelect" required onchange="updateOrderInfo()">
                        <option value="">選択してください</option>
                        <?php foreach ($completedOrders as $o): ?>
                        <option value="<?= $o['id'] ?>" data-customer="<?= $o['customer_id'] ?>" data-total="<?= $o['total_amount'] ?>" data-tax="<?= $o['tax_amount'] ?>"><?= h($o['order_no']) ?> - <?= h($o['customer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">売上日 <span class="text-danger">*</span></label>
                    <input type="date" name="sales_date" class="form-control" required value="<?= h($sale['sales_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">請求書番号</label>
                    <input type="text" class="form-control" value="<?= h($sale['invoice_no'] ?? '（自動採番）') ?>" readonly>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">検収書番号</label>
                    <input type="text" class="form-control" value="<?= h($sale['acceptance_no'] ?? '（自動採番）') ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">税抜金額</label>
                    <input type="number" name="total_amount_ex" class="form-control" id="totalAmountEx" value="<?= ($sale['total_amount'] ?? 0) - ($sale['tax_amount'] ?? 0) ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">消費税額</label>
                    <input type="number" name="tax_amount" class="form-control" id="taxAmount" value="<?= $sale['tax_amount'] ?? 0 ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">合計金額（税込）</label>
                    <input type="number" name="total_amount" class="form-control" id="totalAmount" value="<?= $sale['total_amount'] ?? 0 ?>" readonly>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($sale['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($orderDetails)): ?>
    <div class="card mb-4">
        <div class="card-header">受注明細</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>品名</th>
                        <th class="text-end">数量</th>
                        <th>単位</th>
                        <th class="text-end">単価</th>
                        <th class="text-end">金額</th>
                        <th>税率</th>
                        <th>備考</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderDetails as $d): ?>
                    <tr>
                        <td><?= $d['line_no'] ?></td>
                        <td><?= h($d['item_name']) ?></td>
                        <td class="text-end"><?= formatNumber($d['quantity']) ?></td>
                        <td><?= h($d['unit']) ?></td>
                        <td class="text-end">&yen;<?= formatNumber($d['unit_price']) ?></td>
                        <td class="text-end">&yen;<?= formatNumber($d['amount']) ?></td>
                        <td><?= $d['tax_rate'] ?>%</td>
                        <td><?= h($d['notes']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-end"><strong>小計</strong></td>
                        <td class="text-end"><strong>&yen;<?= formatNumber(($sale['total_amount'] ?? 0) - ($sale['tax_amount'] ?? 0)) ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end"><strong>消費税</strong></td>
                        <td class="text-end"><strong>&yen;<?= formatNumber($sale['tax_amount'] ?? 0) ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end"><strong>合計（税込）</strong></td>
                        <td class="text-end"><strong>&yen;<?= formatNumber($sale['total_amount'] ?? 0) ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">保存</button>
        <?php if ($id): ?>
                <a href="<?= BASE_PATH ?>/reports/invoice.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">請求書印刷</a>
                <a href="<?= BASE_PATH ?>/reports/acceptance.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">検収書印刷</a>
                <a href="<?= BASE_PATH ?>/reports/sales_slip.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">売上伝票印刷</a>
        <button type="button" class="btn btn-outline-danger" onclick="if(confirm('削除しますか？')){document.getElementById('deleteForm').submit();}">削除</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($id): ?>
<form id="deleteForm" method="post" style="display:none">
    <input type="hidden" name="action" value="delete">
</form>
<?php endif; ?>

<script>
function updateOrderInfo() {
    const select = document.getElementById('orderSelect');
    if (!select) return;
    
    const option = select.options[select.selectedIndex];
    if (option.value) {
        document.querySelector('input[name="customer_id"]').value = option.dataset.customer;
        document.getElementById('totalAmount').value = option.dataset.total;
        document.getElementById('taxAmount').value = option.dataset.tax;
        document.getElementById('totalAmountEx').value = option.dataset.total - option.dataset.tax;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
