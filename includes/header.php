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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? '受発注・売上管理システム') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script>
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('appTheme'); } catch (e) {}
            document.documentElement.setAttribute('data-theme', theme || 'blue');
        })();
    </script>
    <style>
        .navbar-brand { font-weight: bold; }
        .table th { white-space: nowrap; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .status-badge { font-size: 0.75rem; }
        @media print {
            .no-print { display: none !important; }
            .container { max-width: 100% !important; }
        }

        /* テーマカラー切替 */
        [data-theme="blue"] .bg-primary { background-color: #0d6efd !important; }
        [data-theme="blue"] .btn-primary {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }
        [data-theme="blue"] .btn-primary:hover,
        [data-theme="blue"] .btn-primary:focus,
        [data-theme="blue"] .btn-primary:active {
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
        }

        [data-theme="green"] .bg-primary { background-color: #198754 !important; }
        [data-theme="green"] .btn-primary {
            background-color: #198754 !important;
            border-color: #198754 !important;
        }
        [data-theme="green"] .btn-primary:hover,
        [data-theme="green"] .btn-primary:focus,
        [data-theme="green"] .btn-primary:active {
            background-color: #157347 !important;
            border-color: #146c43 !important;
        }

        [data-theme="orange"] .bg-primary { background-color: #fd7e14 !important; }
        [data-theme="orange"] .btn-primary {
            background-color: #fd7e14 !important;
            border-color: #fd7e14 !important;
        }
        [data-theme="orange"] .btn-primary:hover,
        [data-theme="orange"] .btn-primary:focus,
        [data-theme="orange"] .btn-primary:active {
            background-color: #e8710e !important;
            border-color: #dc6a0d !important;
        }

        [data-theme="dark"] .bg-primary { background-color: #212529 !important; }
        [data-theme="dark"] .btn-primary {
            background-color: #212529 !important;
            border-color: #212529 !important;
        }
        [data-theme="dark"] .btn-primary:hover,
        [data-theme="dark"] .btn-primary:focus,
        [data-theme="dark"] .btn-primary:active {
            background-color: #343a40 !important;
            border-color: #343a40 !important;
        }
        [data-theme="dark"] .navbar.bg-primary .navbar-brand,
        [data-theme="dark"] .navbar.bg-primary .nav-link,
        [data-theme="dark"] .navbar.bg-primary .nav-link:hover,
        [data-theme="dark"] .navbar.bg-primary .nav-link:focus {
            color: #f8f9fa !important;
        }

        .theme-selector { width: auto; }
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
            <div class="ms-auto">
                <label class="visually-hidden" for="themeSelector">テーマカラー</label>
                <select id="themeSelector" class="form-select form-select-sm theme-selector">
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
            document.documentElement.setAttribute('data-theme', selector.value);
            try { localStorage.setItem('appTheme', selector.value); } catch (e) {}
        });
    })();
</script>
<div class="container">
