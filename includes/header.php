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
        body {
            --theme-bg: #0d6efd;
            --theme-btn: #0d6efd;
            --theme-btn-hover: #0b5ed7;
        }
        body[data-theme="blue"] {
            --theme-bg: #0d6efd;
            --theme-btn: #0d6efd;
            --theme-btn-hover: #0b5ed7;
        }
        body[data-theme="green"] {
            --theme-bg: #198754;
            --theme-btn: #198754;
            --theme-btn-hover: #146c43;
        }
        body[data-theme="orange"] {
            --theme-bg: #fd7e14;
            --theme-btn: #fd7e14;
            --theme-btn-hover: #ca6510;
        }
        body[data-theme="dark"] {
            --theme-bg: #212529;
            --theme-btn: #212529;
            --theme-btn-hover: #1a1d20;
        }
        .navbar.bg-primary {
            background-color: var(--theme-bg) !important;
        }
        .btn-primary {
            background-color: var(--theme-btn) !important;
            border-color: var(--theme-btn) !important;
        }
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active,
        .btn-primary:first-child:active,
        .btn-check:checked + .btn-primary {
            background-color: var(--theme-btn-hover) !important;
            border-color: var(--theme-btn-hover) !important;
        }
        #theme-selector { width: auto; }
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
<script>
    (function () {
        var theme = localStorage.getItem('theme') || 'blue';
        document.body.setAttribute('data-theme', theme);
    })();
</script>
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
            <select id="theme-selector" class="form-select form-select-sm ms-auto" aria-label="テーマ切替">
                <option value="blue">ブルー</option>
                <option value="green">グリーン</option>
                <option value="orange">オレンジ</option>
                <option value="dark">ダーク</option>
            </select>
        </div>
    </div>
</nav>
<script>
    (function () {
        function initThemeSelector() {
            var selector = document.getElementById('theme-selector');
            if (!selector) return;
            var theme = localStorage.getItem('theme') || 'blue';
            document.body.setAttribute('data-theme', theme);
            selector.value = theme;
            selector.addEventListener('change', function () {
                document.body.setAttribute('data-theme', selector.value);
                localStorage.setItem('theme', selector.value);
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initThemeSelector);
        } else {
            initThemeSelector();
        }
    })();
</script>
<div class="container">
