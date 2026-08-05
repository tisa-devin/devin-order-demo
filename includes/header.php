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
            background-color: #495057 !important;
            border-color: #495057 !important;
            color: #f8f9fa !important;
        }
        [data-theme="dark"] .btn-primary:hover,
        [data-theme="dark"] .btn-primary:focus,
        [data-theme="dark"] .btn-primary:active {
            background-color: #5c636a !important;
            border-color: #5c636a !important;
        }
        [data-theme="dark"] .navbar.bg-primary .navbar-brand,
        [data-theme="dark"] .navbar.bg-primary .nav-link,
        [data-theme="dark"] .navbar.bg-primary .nav-link:hover,
        [data-theme="dark"] .navbar.bg-primary .nav-link:focus {
            color: #f8f9fa !important;
        }

        /* ダークテーマ: 画面全体の配色 */
        [data-theme="dark"] {
            color-scheme: dark;
        }
        [data-theme="dark"] body {
            background-color: #1a1d20;
            color: #e9ecef;
        }
        [data-theme="dark"] .card,
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .modal-content,
        [data-theme="dark"] .dropdown-menu {
            background-color: #2b3035;
            border-color: #495057;
            color: #e9ecef;
        }
        [data-theme="dark"] .card-header,
        [data-theme="dark"] .card-footer,
        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            background-color: #343a40;
            border-color: #495057;
            color: #e9ecef;
        }
        [data-theme="dark"] .dropdown-item { color: #e9ecef; }
        [data-theme="dark"] .dropdown-item:hover,
        [data-theme="dark"] .dropdown-item:focus {
            background-color: #495057;
            color: #fff;
        }
        [data-theme="dark"] .dropdown-divider { border-color: #495057; }
        [data-theme="dark"] .table {
            --bs-table-color: #e9ecef;
            --bs-table-bg: #2b3035;
            --bs-table-border-color: #495057;
            --bs-table-striped-color: #e9ecef;
            --bs-table-striped-bg: #31363b;
            --bs-table-hover-color: #fff;
            --bs-table-hover-bg: #3d4349;
            color: #e9ecef;
        }
        [data-theme="dark"] .table > thead {
            background-color: #343a40;
            color: #f8f9fa;
        }
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select,
        [data-theme="dark"] .input-group-text {
            background-color: #2b3035;
            border-color: #495057;
            color: #e9ecef;
        }
        [data-theme="dark"] .form-control::placeholder { color: #adb5bd; }
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #2b3035;
            color: #e9ecef;
            border-color: #6c757d;
            box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.35);
        }
        [data-theme="dark"] .form-control:disabled,
        [data-theme="dark"] .form-control[readonly] {
            background-color: #343a40;
            color: #adb5bd;
        }
        [data-theme="dark"] .text-muted { color: #adb5bd !important; }
        [data-theme="dark"] .text-danger { color: #ea868f !important; }
        [data-theme="dark"] a { color: #8bb9fe; }
        [data-theme="dark"] .btn-outline-primary,
        [data-theme="dark"] .btn-outline-secondary {
            color: #dee2e6;
            border-color: #6c757d;
        }
        [data-theme="dark"] .btn-outline-primary:hover,
        [data-theme="dark"] .btn-outline-secondary:hover {
            background-color: #495057;
            border-color: #6c757d;
            color: #fff;
        }
        [data-theme="dark"] .btn-outline-danger { color: #ea868f; border-color: #ea868f; }
        [data-theme="dark"] .btn-outline-danger:hover { background-color: #b02a37; border-color: #b02a37; color: #fff; }
        [data-theme="dark"] .alert-danger {
            background-color: #3b1f22;
            border-color: #842029;
            color: #f5c2c7;
        }
        [data-theme="dark"] hr { border-color: #495057; }

        /* 印刷時は常に白背景・黒文字 */
        @media print {
            [data-theme="dark"] body,
            [data-theme="dark"] .card,
            [data-theme="dark"] .card-header,
            [data-theme="dark"] .table,
            [data-theme="dark"] .table > thead {
                background-color: #fff !important;
                color: #000 !important;
            }
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
