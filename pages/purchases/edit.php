<?php
$pageTitle = '発注登録・編集';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

$id = $_GET['id'] ?? null;
$order_id = $_GET['order_id'] ?? null;
$purchase = null;
$details = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch();
    
    if ($purchase) {
        $stmt = $pdo->prepare("SELECT * FROM purchase_details WHERE purchase_id = ? ORDER BY line_no");
        $stmt->execute([$id]);
        $details = $stmt->fetchAll();
        $order_id = $purchase['order_id'];
    }
}

$orderDetails = [];
if ($order_id) {
    $stmt = $pdo->prepare("SELECT od.*, o.order_no FROM order_details od JOIN orders o ON od.order_id = o.id WHERE od.order_id = ? ORDER BY od.line_no");
    $stmt->execute([$order_id]);
    $orderDetails = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $supplier_id = $_POST['supplier_id'] ?? '';
        $order_id = $_POST['order_id'] ?? '';
        $purchase_date = $_POST['purchase_date'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? '';
        $status = $_POST['status'] ?? 'ordered';
        $notes = trim($_POST['notes'] ?? '');
        
        $items = $_POST['items'] ?? [];
        
        if (empty($supplier_id) || empty($order_id) || empty($purchase_date)) {
            $error = '仕入先、受注、発注日は必須です';
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
                    $stmt = $pdo->prepare("UPDATE purchases SET supplier_id = ?, purchase_date = ?, delivery_date = ?, total_amount = ?, tax_amount = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$supplier_id, $purchase_date, $delivery_date ?: null, $total_amount, $tax_amount, $status, $notes, $id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM purchase_details WHERE purchase_id = ?");
                    $stmt->execute([$id]);
                } else {
                    $purchase_no = getNextNumber('purchase');
                    $stmt = $pdo->prepare("INSERT INTO purchases (purchase_no, order_id, supplier_id, purchase_date, delivery_date, total_amount, tax_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$purchase_no, $order_id, $supplier_id, $purchase_date, $delivery_date ?: null, $total_amount, $tax_amount, $status, $notes]);
                    $id = $pdo->lastInsertId();
                }
                
                $line_no = 1;
                foreach ($items as $item) {
                    if (!empty($item['item_name'])) {
                        $amount = (int)$item['quantity'] * (int)$item['unit_price'];
                        $stmt = $pdo->prepare("INSERT INTO purchase_details (purchase_id, order_detail_id, line_no, item_name, quantity, unit, unit_price, amount, tax_rate, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$id, $item['order_detail_id'] ?: null, $line_no, $item['item_name'], (int)$item['quantity'], $item['unit'], (int)$item['unit_price'], $amount, (int)$item['tax_rate'], $item['notes'] ?? '']);
                        
                        if (!empty($item['order_detail_id'])) {
                            $stmt = $pdo->prepare("UPDATE order_details SET purchase_status = 'ordered' WHERE id = ?");
                            $stmt->execute([$item['order_detail_id']]);
                        }
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
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT order_detail_id FROM purchase_details WHERE purchase_id = ?");
            $stmt->execute([$id]);
            $detailIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($detailIds as $detailId) {
                if ($detailId) {
                    $stmt = $pdo->prepare("UPDATE order_details SET purchase_status = 'none' WHERE id = ?");
                    $stmt->execute([$detailId]);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            header("Location: list.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '削除できません';
        }
    }
}

$stmt = $pdo->query("SELECT id, code, name FROM suppliers ORDER BY code");
$suppliers = $stmt->fetchAll();

$stmt = $pdo->query("SELECT o.id, o.order_no, c.name as customer_name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.status IN ('ordered', 'in_progress') ORDER BY o.order_date DESC");
$orders = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart"></i> <?= $id ? '発注編集' : '新規発注' ?></h2>
    <a href="list.php" class="btn btn-outline-secondary">一覧に戻る</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" id="purchaseForm">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="order_id" value="<?= h($order_id) ?>">
    
    <div class="card mb-4">
        <div class="card-header">基本情報</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">発注番号</label>
                    <input type="text" class="form-control" value="<?= h($purchase['purchase_no'] ?? '（自動採番）') ?>" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">仕入先 <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($purchase['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= h($s['code']) ?> - <?= h($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">発注日 <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control" required value="<?= h($purchase['purchase_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">希望納期</label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= h($purchase['delivery_date'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">受注</label>
                    <select class="form-select" disabled>
                        <?php foreach ($orders as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $order_id == $o['id'] ? 'selected' : '' ?>><?= h($o['order_no']) ?> - <?= h($o['customer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">ステータス</label>
                    <select name="status" class="form-select">
                        <option value="ordered" <?= ($purchase['status'] ?? 'ordered') === 'ordered' ? 'selected' : '' ?>>発注済</option>
                        <option value="received" <?= ($purchase['status'] ?? '') === 'received' ? 'selected' : '' ?>>検収済</option>
                        <option value="cancelled" <?= ($purchase['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>キャンセル</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($purchase['notes'] ?? '') ?></textarea>
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
            <?php if (!empty($orderDetails) && empty($details)): ?>
            <div class="alert alert-info">
                <strong>受注明細から選択:</strong>
                <?php foreach ($orderDetails as $od): ?>
                <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="addFromOrder(<?= htmlspecialchars(json_encode($od)) ?>)"><?= h($od['item_name']) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <table class="table" id="detailsTable">
                <thead>
                    <tr>
                        <th style="width:25%">品名</th>
                        <th style="width:8%">数量</th>
                        <th style="width:8%">単位</th>
                        <th style="width:12%">仕入単価</th>
                        <th style="width:12%">金額</th>
                        <th style="width:8%">税率</th>
                        <th style="width:17%">備考</th>
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
                        <td><input type="text" name="items[0][notes]" class="form-control form-control-sm"></td>
                        <td>
                            <input type="hidden" name="items[0][order_detail_id]" value="">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                        </td>
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
                        <td><input type="text" name="items[<?= $i ?>][notes]" class="form-control form-control-sm" value="<?= h($d['notes']) ?>"></td>
                        <td>
                            <input type="hidden" name="items[<?= $i ?>][order_detail_id]" value="<?= $d['order_detail_id'] ?>">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>小計</strong></td>
                        <td id="subtotal" class="text-end">0</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>消費税</strong></td>
                        <td id="taxTotal" class="text-end">0</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>合計</strong></td>
                        <td id="grandTotal" class="text-end"><strong>0</strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">保存</button>
        <?php if ($id): ?>
        <a href="<?= BASE_PATH ?>/reports/purchase.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">発注書印刷</a>
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
        <td><input type="text" name="items[${rowIndex}][notes]" class="form-control form-control-sm"></td>
        <td>
            <input type="hidden" name="items[${rowIndex}][order_detail_id]" value="">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
    attachEvents(tr);
}

function addFromOrder(od) {
    const tbody = document.getElementById('detailsBody');
    const tr = document.createElement('tr');
    tr.className = 'detail-row';
    tr.innerHTML = `
        <td><input type="text" name="items[${rowIndex}][item_name]" class="form-control form-control-sm" value="${od.item_name}"></td>
        <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control form-control-sm qty" value="${od.quantity}" min="1"></td>
        <td><input type="text" name="items[${rowIndex}][unit]" class="form-control form-control-sm" value="${od.unit || '式'}"></td>
        <td><input type="number" name="items[${rowIndex}][unit_price]" class="form-control form-control-sm price" value="${od.unit_price}"></td>
        <td><input type="text" class="form-control form-control-sm amount" value="0" readonly></td>
        <td><select name="items[${rowIndex}][tax_rate]" class="form-select form-select-sm tax"><option value="10" ${od.tax_rate == 10 ? 'selected' : ''}>10%</option><option value="8" ${od.tax_rate == 8 ? 'selected' : ''}>8%</option></select></td>
        <td><input type="text" name="items[${rowIndex}][notes]" class="form-control form-control-sm"></td>
        <td>
            <input type="hidden" name="items[${rowIndex}][order_detail_id]" value="${od.id}">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
    attachEvents(tr);
    calculateTotals();
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
