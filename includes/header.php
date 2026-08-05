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
            var themes = ['blue', 'green', 'orange', 'dark'];
            var theme = localStorage.getItem('theme');
            if (themes.indexOf(theme) === -1) theme = 'blue';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        :root {
            --theme-color: #0d6efd;
            --theme-color-hover: #0b5ed7;
            --theme-text: #ffffff;
        }
        [data-theme="green"] {
            --theme-color: #198754;
            --theme-color-hover: #157347;
            --theme-text: #ffffff;
        }
        [data-theme="orange"] {
            --theme-color: #fd7e14;
            --theme-color-hover: #e8690b;
            --theme-text: #212529;
        }
        [data-theme="dark"] {
            --theme-color: #212529;
            --theme-color-hover: #1a1e21;
            --theme-text: #ffffff;
        }
        .navbar { background-color: var(--theme-color) !important; }
        .navbar .navbar-brand,
        .navbar .nav-link,
        .navbar .navbar-toggler { color: var(--theme-text) !important; }
        .btn-primary {
            background-color: var(--theme-color) !important;
            border-color: var(--theme-color) !important;
            color: var(--theme-text) !important;
        }
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: var(--theme-color-hover) !important;
            border-color: var(--theme-color-hover) !important;
            color: var(--theme-text) !important;
        }
        #themeSelect { width: auto; }
        .navbar-brand { font-weight: bold; }
        .table th { white-space: nowrap; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .status-badge { font-size: 0.75rem; }
        @media print {
            .no-print { display: none !important; }
            .container { max-width: 100% !important; }
        }
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
                <select class="form-select form-select-sm" id="themeSelect" aria-label="テーマ選択">
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
        var select = document.getElementById('themeSelect');
        if (!select) return;
        select.value = document.documentElement.getAttribute('data-theme') || 'blue';
        select.addEventListener('change', function () {
            document.documentElement.setAttribute('data-theme', select.value);
            localStorage.setItem('theme', select.value);
        });
    })();
</script>
<div class="container">
