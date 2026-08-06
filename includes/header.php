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
            --theme-nav: #0d6efd;
        }
        [data-theme="green"] {
            --theme-main: #198754;
            --theme-hover: #157347;
            --theme-nav: #198754;
        }
        [data-theme="orange"] {
            --theme-main: #fd7e14;
            --theme-hover: #e8690b;
            --theme-nav: #fd7e14;
        }
        [data-theme="dark"] {
            --theme-main: #343a40;
            --theme-hover: #23272b;
            --theme-nav: #212529;
        }
        [data-theme="pink"] {
            --theme-main: #d63384;
            --theme-hover: #b02a6c;
            --theme-nav: #d63384;
        }
        .navbar.bg-primary { background-color: var(--theme-nav) !important; }
        .btn-primary {
            --bs-btn-bg: var(--theme-main);
            --bs-btn-border-color: var(--theme-main);
            --bs-btn-hover-bg: var(--theme-hover);
            --bs-btn-hover-border-color: var(--theme-hover);
            --bs-btn-active-bg: var(--theme-hover);
            --bs-btn-active-border-color: var(--theme-hover);
            --bs-btn-disabled-bg: var(--theme-main);
            --bs-btn-disabled-border-color: var(--theme-main);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--theme-main);
            --bs-btn-border-color: var(--theme-main);
            --bs-btn-hover-bg: var(--theme-main);
            --bs-btn-hover-border-color: var(--theme-main);
            --bs-btn-active-bg: var(--theme-main);
            --bs-btn-active-border-color: var(--theme-main);
        }
        .theme-select {
            width: auto;
            min-width: 8rem;
            background-color: rgba(255, 255, 255, 0.15);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.4);
        }
        .theme-select option { color: #212529; }
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
        (function () {
            var themes = ['blue', 'green', 'orange', 'dark', 'pink'];
            var saved = null;
            try { saved = localStorage.getItem('themeColor'); } catch (e) {}
            document.documentElement.setAttribute('data-theme', themes.indexOf(saved) >= 0 ? saved : 'blue');
        })();
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
                <label class="visually-hidden" for="themeColorSelect">テーマカラー</label>
                <select class="form-select form-select-sm theme-select" id="themeColorSelect">
                    <option value="blue">ブルー</option>
                    <option value="green">グリーン</option>
                    <option value="orange">オレンジ</option>
                    <option value="dark">ダーク</option>
                    <option value="pink">ピンク</option>
                </select>
            </div>
        </div>
    </div>
</nav>
<script>
    (function () {
        var select = document.getElementById('themeColorSelect');
        if (!select) return;
        select.value = document.documentElement.getAttribute('data-theme') || 'blue';
        select.addEventListener('change', function () {
            document.documentElement.setAttribute('data-theme', select.value);
            try { localStorage.setItem('themeColor', select.value); } catch (e) {}
        });
    })();
</script>
<div class="container">
