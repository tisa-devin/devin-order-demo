<?php
$pageTitle = '受注登録・編集';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

$id = $_GET['id'] ?? null;
$from_estimate = $_GET['from_estimate'] ?? null;
$order = null;
$details = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if ($order) {
        $stmt = $pdo->prepare("SELECT * FROM order_details WHERE order_id = ? ORDER BY line_no");
        $stmt->execute([$id]);
        $details = $stmt->fetchAll();
    }
} elseif ($from_estimate) {
    $stmt = $pdo->prepare("SELECT * FROM estimates WHERE id = ?");
    $stmt->execute([$from_estimate]);
    $estimate = $stmt->fetch();
    
    if ($estimate) {
        $order = [
            'customer_id' => $estimate['customer_id'],
            'subject' => $estimate['subject'],
            'notes' => $estimate['notes'],
            'estimate_id' => $estimate['id']
        ];
        
        $stmt = $pdo->prepare("SELECT * FROM estimate_details WHERE estimate_id = ? ORDER BY line_no");
        $stmt->execute([$from_estimate]);
        $details = $stmt->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $customer_id = $_POST['customer_id'] ?? '';
        $order_date = $_POST['order_date'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $status = $_POST['status'] ?? 'ordered';
        $notes = trim($_POST['notes'] ?? '');
        $estimate_id = $_POST['estimate_id'] ?? null;
        
        $items = $_POST['items'] ?? [];
        
        if (empty($customer_id) || empty($order_date)) {
            $error = '顧客と受注日は必須です';
        } else {
            try {
                $pdo->beginTransaction();
                
                $subtotal = 0;
                $tax_amount = 0;
                foreach ($items as $item) {
                    if (!empty($item['item_name'])) {
                        $amount = (int)$item['quantity'] * (int)$item['unit_price'];
                        $subtotal += $amount;
                        $tax_amount += (int)floor($amount * (int)$item['tax_rate'] / 100);
                    }
                }
                $total_amount = $subtotal + $tax_amount;
                
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE orders SET customer_id = ?, order_date = ?, delivery_date = ?, subject = ?, total_amount = ?, tax_amount = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$customer_id, $order_date, $delivery_date ?: null, $subject, $total_amount, $tax_amount, $status, $notes, $id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
                    $stmt->execute([$id]);
                } else {
                    $order_no = getNextNumber('order');
                    $stmt = $pdo->prepare("INSERT INTO orders (order_no, estimate_id, customer_id, order_date, delivery_date, subject, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$order_no, $estimate_id ?: null, $customer_id, $order_date, $delivery_date ?: null, $subject, $total_amount, $tax_amount, $status, $notes]);
                    $id = $pdo->lastInsertId();
                    
                    if ($estimate_id) {
                        $stmt = $pdo->prepare("UPDATE estimates SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$estimate_id]);
                    }
                }
                
                $line_no = 1;
                foreach ($items as $item) {
                    if (!empty($item['item_name'])) {
                        $amount = (int)$item['quantity'] * (int)$item['unit_price'];
                        $stmt = $pdo->prepare("INSERT INTO order_details (order_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, purchase_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'none', ?)");
                        $stmt->execute([$id, $line_no, $item['item_name'], (int)$item['quantity'], $item['unit'], (int)$item['unit_price'], $amount, (int)$item['tax_rate'], $item['notes'] ?? '']);
                        $line_no++;
                    }
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
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: list.php");
            exit;
        } catch (Exception $e) {
            $error = '削除できません（関連データが存在します）';
        }
    }
}

$stmt = $pdo->query("SELECT id, code, name FROM customers ORDER BY code");
$customers = $stmt->fetchAll();

$purchaseStatusLabels = [
    'none' => ['label' => '未発注', 'class' => 'secondary'],
    'ordered' => ['label' => '発注済', 'class' => 'primary'],
    'received' => ['label' => '検収済', 'class' => 'success']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clipboard-check"></i> <?= $id ? '受注編集' : '新規受注' ?></h2>
    <a href="list.php" class="btn btn-outline-secondary">一覧に戻る</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" id="orderForm">
    <input type="hidden" name="action" value="save">
    <?php if ($from_estimate): ?>
    <input type="hidden" name="estimate_id" value="<?= h($from_estimate) ?>">
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">基本情報</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">受注番号</label>
                    <input type="text" class="form-control" value="<?= h($order['order_no'] ?? '（自動採番）') ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">顧客 <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($order['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= h($c['code']) ?> - <?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">受注日 <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control" required value="<?= h($order['order_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">納期</label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= h($order['delivery_date'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">件名</label>
                    <input type="text" name="subject" class="form-control" value="<?= h($order['subject'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">ステータス</label>
                    <select name="status" class="form-select">
                        <option value="ordered" <?= ($order['status'] ?? 'ordered') === 'ordered' ? 'selected' : '' ?>>受注</option>
                        <option value="in_progress" <?= ($order['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>進行中</option>
                        <option value="completed" <?= ($order['status'] ?? '') === 'completed' ? 'selected' : '' ?>>完了</option>
                        <option value="cancelled" <?= ($order['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>キャンセル</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($order['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>明細</span>
            <button type="button" class="btn btn-sm btn-success" onclick="addRow()"><i class="bi bi-plus"></i> 行追加</button>
        </div>
        <div class="card-body">
            <table class="table" id="detailsTable">
                <thead>
                    <tr>
                        <th style="width:25%">品名</th>
                        <th style="width:7%">数量</th>
                        <th style="width:7%">単位</th>
                        <th style="width:10%">単価</th>
                        <th style="width:10%">金額</th>
                        <th style="width:7%">税率</th>
                        <th style="width:10%">発注状況</th>
                        <th style="width:14%">備考</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="detailsBody">
                    <?php if (empty($details)): ?>
                    <tr class="detail-row">
                        <td><input type="text" name="items[0][item_name]" class="form-control form-control-sm"></td>
                        <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm qty" value="1" min="1"></td>
                        <td><input type="text" name="items[0][unit]" class="form-control form-control-sm" value="式"></td>
                        <td><input type="number" name="items[0][unit_price]" class="form-control form-control-sm price" value="0"></td>
                        <td><input type="text" class="form-control form-control-sm amount" value="0" readonly></td>
                        <td><select name="items[0][tax_rate]" class="form-select form-select-sm tax"><option value="10">10%</option><option value="8">8%</option></select></td>
                        <td><span class="badge bg-secondary">未発注</span></td>
                        <td><input type="text" name="items[0][notes]" class="form-control form-control-sm"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($details as $i => $d): ?>
                    <tr class="detail-row">
                        <td><input type="text" name="items[<?= $i ?>][item_name]" class="form-control form-control-sm" value="<?= h($d['item_name']) ?>"></td>
                        <td><input type="number" name="items[<?= $i ?>][quantity]" class="form-control form-control-sm qty" value="<?= $d['quantity'] ?>" min="1"></td>
                        <td><input type="text" name="items[<?= $i ?>][unit]" class="form-control form-control-sm" value="<?= h($d['unit']) ?>"></td>
                        <td><input type="number" name="items[<?= $i ?>][unit_price]" class="form-control form-control-sm price" value="<?= $d['unit_price'] ?>"></td>
                        <td><input type="text" class="form-control form-control-sm amount" value="<?= formatNumber($d['amount']) ?>" readonly></td>
                        <td><select name="items[<?= $i ?>][tax_rate]" class="form-select form-select-sm tax"><option value="10" <?= $d['tax_rate'] == 10 ? 'selected' : '' ?>>10%</option><option value="8" <?= $d['tax_rate'] == 8 ? 'selected' : '' ?>>8%</option></select></td>
                        <td>
                            <?php $ps = $purchaseStatusLabels[$d['purchase_status'] ?? 'none'] ?? $purchaseStatusLabels['none']; ?>
                            <span class="badge bg-<?= $ps['class'] ?>"><?= $ps['label'] ?></span>
                        </td>
                        <td><input type="text" name="items[<?= $i ?>][notes]" class="form-control form-control-sm" value="<?= h($d['notes']) ?>"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>小計</strong></td>
                        <td id="subtotal" class="text-end">0</td>
                        <td colspan="4"></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>消費税</strong></td>
                        <td id="taxTotal" class="text-end">0</td>
                        <td colspan="4"></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>合計</strong></td>
                        <td id="grandTotal" class="text-end"><strong>0</strong></td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">保存</button>
        <?php if ($id): ?>
        <a href="<?= BASE_PATH ?>/pages/purchases/edit.php?order_id=<?= $id ?>" class="btn btn-outline-info">発注作成</a>
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
let rowIndex = <?= max(count($details), 1) ?>;

function addRow() {
    const tbody = document.getElementById('detailsBody');
    const tr = document.createElement('tr');
    tr.className = 'detail-row';
    tr.innerHTML = `
        <td><input type="text" name="items[${rowIndex}][item_name]" class="form-control form-control-sm"></td>
        <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control form-control-sm qty" value="1" min="1"></td>
        <td><input type="text" name="items[${rowIndex}][unit]" class="form-control form-control-sm" value="式"></td>
        <td><input type="number" name="items[${rowIndex}][unit_price]" class="form-control form-control-sm price" value="0"></td>
        <td><input type="text" class="form-control form-control-sm amount" value="0" readonly></td>
        <td><select name="items[${rowIndex}][tax_rate]" class="form-select form-select-sm tax"><option value="10">10%</option><option value="8">8%</option></select></td>
        <td><span class="badge bg-secondary">未発注</span></td>
        <td><input type="text" name="items[${rowIndex}][notes]" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
    attachEvents(tr);
}

function removeRow(btn) {
    btn.closest('tr').remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    let taxTotal = 0;
    document.querySelectorAll('.detail-row').forEach(row => {
        const qty = parseInt(row.querySelector('.qty').value) || 0;
        const price = parseInt(row.querySelector('.price').value) || 0;
        const taxRate = parseInt(row.querySelector('.tax').value) || 10;
        const amount = qty * price;
        row.querySelector('.amount').value = amount.toLocaleString();
        subtotal += amount;
        taxTotal += Math.floor(amount * taxRate / 100);
    });
    document.getElementById('subtotal').textContent = subtotal.toLocaleString();
    document.getElementById('taxTotal').textContent = taxTotal.toLocaleString();
    document.getElementById('grandTotal').innerHTML = '<strong>' + (subtotal + taxTotal).toLocaleString() + '</strong>';
}

function attachEvents(row) {
    row.querySelectorAll('.qty, .price, .tax').forEach(el => {
        el.addEventListener('change', calculateTotals);
        el.addEventListener('input', calculateTotals);
    });
}

document.querySelectorAll('.detail-row').forEach(attachEvents);
calculateTotals();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
