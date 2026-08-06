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
            var t = localStorage.getItem('appTheme') || 'blue';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        :root, [data-theme="blue"] { --app-theme: #0d6efd; --app-theme-hover: #0b5ed7; --app-theme-border: #0a58ca; }
        [data-theme="green"] { --app-theme: #198754; --app-theme-hover: #157347; --app-theme-border: #146c43; }
        [data-theme="orange"] { --app-theme: #fd7e14; --app-theme-hover: #e96b02; --app-theme-border: #d76502; }
        [data-theme="dark"] { --app-theme: #343a40; --app-theme-hover: #23272b; --app-theme-border: #1d2124; }
        [data-theme="turquoise"] { --app-theme: #17a2b8; --app-theme-hover: #138496; --app-theme-border: #117a8b; }
        .navbar.bg-primary { background-color: var(--app-theme) !important; }
        .btn-primary {
            --bs-btn-bg: var(--app-theme);
            --bs-btn-border-color: var(--app-theme);
            --bs-btn-hover-bg: var(--app-theme-hover);
            --bs-btn-hover-border-color: var(--app-theme-border);
            --bs-btn-active-bg: var(--app-theme-border);
            --bs-btn-active-border-color: var(--app-theme-border);
            --bs-btn-disabled-bg: var(--app-theme);
            --bs-btn-disabled-border-color: var(--app-theme);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--app-theme);
            --bs-btn-border-color: var(--app-theme);
            --bs-btn-hover-bg: var(--app-theme);
            --bs-btn-hover-border-color: var(--app-theme);
            --bs-btn-active-bg: var(--app-theme-border);
            --bs-btn-active-border-color: var(--app-theme-border);
            --bs-btn-disabled-color: var(--app-theme);
            --bs-btn-disabled-border-color: var(--app-theme);
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
            <div class="ms-auto d-flex align-items-center">
                <label for="themeSelect" class="text-white me-2 mb-0 small">テーマ</label>
                <select id="themeSelect" class="form-select form-select-sm">
                    <option value="blue">ブルー</option>
                    <option value="green">グリーン</option>
                    <option value="orange">オレンジ</option>
                    <option value="dark">ダーク</option>
                    <option value="turquoise">ターコイズブルー</option>
                </select>
            </div>
        </div>
    </div>
</nav>
<script>
    (function () {
        var select = document.getElementById('themeSelect');
        if (!select) return;
        select.value = localStorage.getItem('appTheme') || 'blue';
        select.addEventListener('change', function () {
            localStorage.setItem('appTheme', select.value);
            document.documentElement.setAttribute('data-theme', select.value);
        });
    })();
</script>
<div class="container">
