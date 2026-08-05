<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

initializeDatabase();

function h($str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatNumber($num): string {
    return number_format((int)$num);
}

function formatDate($date): string {
    if (empty($date)) return '';
    return date('Y/m/d', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <script>
        (function () {
            var theme = localStorage.getItem('themeColor') || 'blue';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? '受発注・売上管理システム') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .navbar-brand { font-weight: bold; }
        .table th { white-space: nowrap; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .status-badge { font-size: 0.75rem; }
        @media print {
            .no-print { display: none !important; }
            .container { max-width: 100% !important; }
        }

        /* テーマカラー: グリーン */
        html[data-theme="green"] .navbar.bg-primary { background-color: #198754 !important; }
        html[data-theme="green"] .bg-primary { background-color: #198754 !important; }
        html[data-theme="green"] .btn-primary {
            background-color: #198754;
            border-color: #198754;
        }
        html[data-theme="green"] .btn-primary:hover,
        html[data-theme="green"] .btn-primary:focus,
        html[data-theme="green"] .btn-primary:active {
            background-color: #157347;
            border-color: #146c43;
        }

        /* テーマカラー: オレンジ */
        html[data-theme="orange"] .navbar.bg-primary { background-color: #fd7e14 !important; }
        html[data-theme="orange"] .bg-primary { background-color: #fd7e14 !important; }
        html[data-theme="orange"] .btn-primary {
            background-color: #fd7e14;
            border-color: #fd7e14;
        }
        html[data-theme="orange"] .btn-primary:hover,
        html[data-theme="orange"] .btn-primary:focus,
        html[data-theme="orange"] .btn-primary:active {
            background-color: #e8590c;
            border-color: #dc5308;
        }

        /* テーマカラー: ダーク */
        html[data-theme="dark"] .navbar.bg-primary { background-color: #212529 !important; }
        html[data-theme="dark"] .bg-primary { background-color: #212529 !important; }
        html[data-theme="dark"] .btn-primary {
            background-color: #343a40;
            border-color: #343a40;
        }
        html[data-theme="dark"] .btn-primary:hover,
        html[data-theme="dark"] .btn-primary:focus,
        html[data-theme="dark"] .btn-primary:active {
            background-color: #23272b;
            border-color: #1d2124;
        }

        .theme-selector { max-width: 160px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 no-print">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_PATH ?>/index.php">受発注・売上管理</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/index.php">ダッシュボード</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">マスタ</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>/pages/masters/customers.php">顧客マスタ</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>/pages/masters/suppliers.php">仕入先マスタ</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>/pages/masters/accounts.php">勘定科目マスタ</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/pages/estimates/list.php">見積管理</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/pages/orders/list.php">受注管理</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/pages/purchases/list.php">発注管理</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/pages/sales/list.php">売上管理</a>
                </li>
            </ul>
            <div class="ms-auto no-print">
                <select id="themeSelector" class="form-select form-select-sm theme-selector" aria-label="テーマカラー選択">
                    <option value="blue">ブルー（現行）</option>
                    <option value="green">グリーン</option>
                    <option value="orange">オレンジ</option>
                    <option value="dark">ダーク</option>
                </select>
            </div>
        </div>
    </div>
</nav>
<script>
    (function () {
        var selector = document.getElementById('themeSelector');
        if (!selector) return;
        var current = document.documentElement.getAttribute('data-theme') || 'blue';
        selector.value = current;
        selector.addEventListener('change', function () {
            var value = selector.value;
            document.documentElement.setAttribute('data-theme', value);
            localStorage.setItem('themeColor', value);
        });
    })();
</script>
<div class="container">
