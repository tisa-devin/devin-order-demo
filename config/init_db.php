<?php
require_once __DIR__ . '/database.php';

function initializeDatabase(): void {
    $pdo = getDB();
    
    $pdo->exec("
        -- 顧客マスタ
        CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            postal_code TEXT,
            address TEXT,
            tel TEXT,
            accounting_code TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- 仕入先マスタ
        CREATE TABLE IF NOT EXISTS suppliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            postal_code TEXT,
            address TEXT,
            tel TEXT,
            accounting_code TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- 勘定科目マスタ
        CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            tax_class_code TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- 見積ヘッダ
        CREATE TABLE IF NOT EXISTS estimates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estimate_no TEXT NOT NULL UNIQUE,
            customer_id INTEGER NOT NULL,
            estimate_date DATE NOT NULL,
            valid_until DATE,
            subject TEXT,
            total_amount INTEGER DEFAULT 0,
            tax_amount INTEGER DEFAULT 0,
            status TEXT DEFAULT 'draft',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        );

        -- 見積明細
        CREATE TABLE IF NOT EXISTS estimate_details (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estimate_id INTEGER NOT NULL,
            line_no INTEGER NOT NULL,
            item_name TEXT NOT NULL,
            quantity INTEGER DEFAULT 1,
            unit TEXT,
            unit_price INTEGER DEFAULT 0,
            amount INTEGER DEFAULT 0,
            tax_rate INTEGER DEFAULT 10,
            notes TEXT,
            FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE
        );

        -- 受注ヘッダ
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_no TEXT NOT NULL UNIQUE,
            estimate_id INTEGER,
            customer_id INTEGER NOT NULL,
            order_date DATE NOT NULL,
            delivery_date DATE,
            subject TEXT,
            total_amount INTEGER DEFAULT 0,
            tax_amount INTEGER DEFAULT 0,
            status TEXT DEFAULT 'ordered',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (estimate_id) REFERENCES estimates(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        );

        -- 受注明細
        CREATE TABLE IF NOT EXISTS order_details (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            line_no INTEGER NOT NULL,
            item_name TEXT NOT NULL,
            quantity INTEGER DEFAULT 1,
            unit TEXT,
            unit_price INTEGER DEFAULT 0,
            amount INTEGER DEFAULT 0,
            tax_rate INTEGER DEFAULT 10,
            purchase_status TEXT DEFAULT 'none',
            notes TEXT,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        );

        -- 発注ヘッダ
        CREATE TABLE IF NOT EXISTS purchases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_no TEXT NOT NULL UNIQUE,
            order_id INTEGER NOT NULL,
            supplier_id INTEGER NOT NULL,
            purchase_date DATE NOT NULL,
            delivery_date DATE,
            total_amount INTEGER DEFAULT 0,
            tax_amount INTEGER DEFAULT 0,
            status TEXT DEFAULT 'ordered',
            received_date DATE,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        );

        -- 発注明細
        CREATE TABLE IF NOT EXISTS purchase_details (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_id INTEGER NOT NULL,
            order_detail_id INTEGER,
            line_no INTEGER NOT NULL,
            item_name TEXT NOT NULL,
            quantity INTEGER DEFAULT 1,
            unit TEXT,
            unit_price INTEGER DEFAULT 0,
            amount INTEGER DEFAULT 0,
            tax_rate INTEGER DEFAULT 10,
            notes TEXT,
            FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
            FOREIGN KEY (order_detail_id) REFERENCES order_details(id)
        );

        -- 売上ヘッダ
        CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sales_no TEXT NOT NULL UNIQUE,
            order_id INTEGER NOT NULL,
            customer_id INTEGER NOT NULL,
            sales_date DATE NOT NULL,
            total_amount INTEGER DEFAULT 0,
            tax_amount INTEGER DEFAULT 0,
            invoice_no TEXT,
            acceptance_no TEXT,
            exported INTEGER DEFAULT 0,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        );

        -- 採番テーブル
        CREATE TABLE IF NOT EXISTS sequences (
            name TEXT PRIMARY KEY,
            current_value INTEGER DEFAULT 0
        );

        -- 初期採番データ
        INSERT OR IGNORE INTO sequences (name, current_value) VALUES ('estimate', 0);
        INSERT OR IGNORE INTO sequences (name, current_value) VALUES ('order', 0);
        INSERT OR IGNORE INTO sequences (name, current_value) VALUES ('purchase', 0);
        INSERT OR IGNORE INTO sequences (name, current_value) VALUES ('sales', 0);
        INSERT OR IGNORE INTO sequences (name, current_value) VALUES ('invoice', 0);
        INSERT OR IGNORE INTO sequences (name, current_value) VALUES ('acceptance', 0);
    ");
}

function getNextNumber(string $type): string {
    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE sequences SET current_value = current_value + 1 WHERE name = ?");
        $stmt->execute([$type]);
        
        $stmt = $pdo->prepare("SELECT current_value FROM sequences WHERE name = ?");
        $stmt->execute([$type]);
        $row = $stmt->fetch();
        
        $pdo->commit();
        
        $prefixes = [
            'estimate' => 'EST',
            'order' => 'ORD',
            'purchase' => 'PUR',
            'sales' => 'SLS',
            'invoice' => 'INV',
            'acceptance' => 'ACC'
        ];
        
        $prefix = $prefixes[$type] ?? 'NUM';
        return $prefix . '-' . str_pad($row['current_value'], 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0])) {
    initializeDatabase();
    echo "Database initialized successfully.\n";
}
