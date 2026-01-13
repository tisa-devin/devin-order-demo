<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

initializeDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        $pdo = getDB();
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE purchases SET status = 'received', received_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([date('Y-m-d'), $id]);
            
            $stmt = $pdo->prepare("SELECT order_detail_id FROM purchase_details WHERE purchase_id = ?");
            $stmt->execute([$id]);
            $detailIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($detailIds as $detailId) {
                if ($detailId) {
                    $stmt = $pdo->prepare("UPDATE order_details SET purchase_status = 'received' WHERE id = ?");
                    $stmt->execute([$detailId]);
                }
            }
            
            $stmt = $pdo->prepare("SELECT order_id FROM purchases WHERE id = ?");
            $stmt->execute([$id]);
            $orderId = $stmt->fetchColumn();
            
            if ($orderId) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_details WHERE order_id = ? AND purchase_status != 'received'");
                $stmt->execute([$orderId]);
                $pendingCount = $stmt->fetchColumn();
                
                if ($pendingCount == 0) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$orderId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'in_progress', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'ordered'");
                    $stmt->execute([$orderId]);
                }
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

header("Location: list.php");
exit;
