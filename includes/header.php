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
    <style>
        :root {
            --theme-main: #0d6efd;
            --theme-hover: #0b5ed7;
        }
        [data-theme="green"] { --theme-main: #198754; --theme-hover: #157347; }
        [data-theme="orange"] { --theme-main: #fd7e14; --theme-hover: #e8690b; }
        [data-theme="dark"] { --theme-main: #5a6570; --theme-hover: #6c757d; }
        [data-theme="dark"] .navbar.bg-primary { background-color: #343a40 !important; }
        [data-theme="dark"] .btn-outline-primary {
            --bs-btn-color: #8ab4ff;
            --bs-btn-border-color: #8ab4ff;
            --bs-btn-hover-bg: #8ab4ff;
            --bs-btn-hover-color: #1e2125;
        }
        [data-theme="dark"] .alert-success {
            background-color: #1c3b2a;
            border-color: #2f6b47;
            color: #a3e0bd;
        }
        [data-theme="dark"] .alert-danger {
            background-color: #3d2226;
            border-color: #6e343c;
            color: #f0a8b0;
        }
        [data-theme="dark"] .table > thead {
            --bs-table-bg: #3a4046;
            --bs-table-color: #fff;
        }
        [data-theme="dark"] body {
            background-color: #1e2125;
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
        [data-theme="dark"] .card-footer {
            background-color: #343a40;
            border-color: #495057;
        }
        [data-theme="dark"] .dropdown-item { color: #e9ecef; }
        [data-theme="dark"] .dropdown-item:hover,
        [data-theme="dark"] .dropdown-item:focus {
            background-color: #495057;
            color: #fff;
        }
        [data-theme="dark"] .table {
            --bs-table-bg: #2b3035;
            --bs-table-color: #e9ecef;
            --bs-table-border-color: #495057;
            --bs-table-striped-bg: #32383e;
            --bs-table-striped-color: #e9ecef;
            --bs-table-hover-bg: #3a4046;
            --bs-table-hover-color: #fff;
        }
        [data-theme="dark"] .table-light > :not(caption) > * > * {
            background-color: #343a40;
            color: #e9ecef;
        }
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #2b3035;
            border-color: #495057;
            color: #e9ecef;
        }
        [data-theme="dark"] .form-control::placeholder { color: #adb5bd; }
        [data-theme="dark"] .text-muted { color: #adb5bd !important; }
        [data-theme="dark"] .bg-light {
            background-color: #2b3035 !important;
            color: #e9ecef;
        }
        [data-theme="dark"] .btn-outline-secondary {
            --bs-btn-color: #dee2e6;
            --bs-btn-border-color: #6c757d;
            --bs-btn-hover-bg: #495057;
            --bs-btn-hover-border-color: #6c757d;
            --bs-btn-hover-color: #fff;
        }
        [data-theme="dark"] a:not(.btn):not(.nav-link):not(.navbar-brand):not(.dropdown-item) { color: #6ea8fe; }
        .bg-primary { background-color: var(--theme-main) !important; }
        .btn-primary {
            --bs-btn-bg: var(--theme-main);
            --bs-btn-border-color: var(--theme-main);
            --bs-btn-hover-bg: var(--theme-hover);
            --bs-btn-hover-border-color: var(--theme-hover);
            --bs-btn-active-bg: var(--theme-hover);
            --bs-btn-active-border-color: var(--theme-hover);
        }
        .navbar-brand { font-weight: bold; }
        .table th { white-space: nowrap; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .status-badge { font-size: 0.75rem; }
        @media print {
            .no-print { display: none !important; }
            .container { max-width: 100% !important; }
        }
    </style>
    <script>
        document.documentElement.setAttribute('data-theme', localStorage.getItem('themeColor') || 'blue');
    </script>
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
                <select id="themeSelect" class="form-select form-select-sm" aria-label="テーマカラー">
                    <option value="blue">ブルー</option>
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
    const STORAGE_KEY = 'themeColor';
    const select = document.getElementById('themeSelect');
    select.value = localStorage.getItem(STORAGE_KEY) || 'blue';
    select.addEventListener('change', function () {
        document.documentElement.setAttribute('data-theme', select.value);
        localStorage.setItem(STORAGE_KEY, select.value);
    });
})();
</script>
<div class="container">
