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
<html lang="ja" data-theme="blue">
<head>
    <script>
        (function () {
            var theme = localStorage.getItem('themeColor');
            if (theme) document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? '受発注・売上管理システム') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html[data-theme="blue"] { --theme-color: #0d6efd; --theme-color-hover: #0b5ed7; }
        html[data-theme="green"] { --theme-color: #198754; --theme-color-hover: #157347; }
        html[data-theme="orange"] { --theme-color: #fd7e14; --theme-color-hover: #e96b02; }
        html[data-theme="dark"] { --theme-color: #212529; --theme-color-hover: #1a1e21; }
        html[data-theme="pink"] { --theme-color: #d63384; --theme-color-hover: #b32b6f; }

        .navbar.bg-primary { background-color: var(--theme-color) !important; }
        .btn-primary {
            --bs-btn-bg: var(--theme-color);
            --bs-btn-border-color: var(--theme-color);
            --bs-btn-hover-bg: var(--theme-color-hover);
            --bs-btn-hover-border-color: var(--theme-color-hover);
            --bs-btn-active-bg: var(--theme-color-hover);
            --bs-btn-active-border-color: var(--theme-color-hover);
            --bs-btn-disabled-bg: var(--theme-color);
            --bs-btn-disabled-border-color: var(--theme-color);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--theme-color);
            --bs-btn-border-color: var(--theme-color);
            --bs-btn-hover-bg: var(--theme-color);
            --bs-btn-hover-border-color: var(--theme-color);
            --bs-btn-active-bg: var(--theme-color);
            --bs-btn-active-border-color: var(--theme-color);
            --bs-btn-disabled-color: var(--theme-color);
            --bs-btn-disabled-border-color: var(--theme-color);
        }
        .theme-selector { width: auto; }
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
        </div>
        <select class="form-select form-select-sm theme-selector" id="themeSelector" aria-label="テーマカラー">
            <option value="blue">ブルー</option>
            <option value="green">グリーン</option>
            <option value="orange">オレンジ</option>
            <option value="dark">ダーク</option>
            <option value="pink">ピンク</option>
        </select>
    </div>
</nav>
<script>
    (function () {
        var selector = document.getElementById('themeSelector');
        selector.value = document.documentElement.getAttribute('data-theme') || 'blue';
        selector.addEventListener('change', function () {
            document.documentElement.setAttribute('data-theme', this.value);
            localStorage.setItem('themeColor', this.value);
        });
    })();
</script>
<div class="container">
