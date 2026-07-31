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
        .navbar-brand { font-weight: bold; }
        .table th { white-space: nowrap; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .status-badge { font-size: 0.75rem; }
        @media print {
            .no-print { display: none !important; }
            .container { max-width: 100% !important; }
        }

        .theme-select {
            width: auto;
            display: inline-block;
        }

        /* テーマカラー: グリーン */
        body[data-theme="green"] .navbar.bg-primary { background-color: #198754 !important; }
        body[data-theme="green"] .btn-primary {
            --bs-btn-bg: #198754;
            --bs-btn-border-color: #198754;
            --bs-btn-hover-bg: #157347;
            --bs-btn-hover-border-color: #146c43;
            --bs-btn-active-bg: #146c43;
            --bs-btn-active-border-color: #13653f;
            --bs-btn-disabled-bg: #198754;
            --bs-btn-disabled-border-color: #198754;
        }

        /* テーマカラー: オレンジ */
        body[data-theme="orange"] .navbar.bg-primary { background-color: #fd7e14 !important; }
        body[data-theme="orange"] .btn-primary {
            --bs-btn-bg: #fd7e14;
            --bs-btn-border-color: #fd7e14;
            --bs-btn-hover-bg: #e26d0b;
            --bs-btn-hover-border-color: #d6670a;
            --bs-btn-active-bg: #d6670a;
            --bs-btn-active-border-color: #ca6109;
            --bs-btn-disabled-bg: #fd7e14;
            --bs-btn-disabled-border-color: #fd7e14;
        }

        /* テーマカラー: ダーク */
        body[data-theme="dark"] .navbar.bg-primary { background-color: #212529 !important; }
        body[data-theme="dark"] .btn-primary {
            --bs-btn-bg: #212529;
            --bs-btn-border-color: #212529;
            --bs-btn-color: #fff;
            --bs-btn-hover-bg: #343a40;
            --bs-btn-hover-border-color: #343a40;
            --bs-btn-hover-color: #fff;
            --bs-btn-active-bg: #000;
            --bs-btn-active-border-color: #000;
            --bs-btn-active-color: #fff;
            --bs-btn-disabled-bg: #212529;
            --bs-btn-disabled-border-color: #212529;
            --bs-btn-disabled-color: #fff;
        }
    </style>
    <script>
        // FOUC回避のため、body描画前にテーマを決定しておく
        var THEME_STORAGE_KEY = 'appThemeColor';
        var initialTheme = 'blue';
        try {
            initialTheme = localStorage.getItem(THEME_STORAGE_KEY) || 'blue';
        } catch (e) {}
    </script>
</head>
<body>
<script>
    document.body.setAttribute('data-theme', initialTheme);
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
            <div class="ms-auto d-flex align-items-center">
                <label class="text-white me-2 mb-0" for="themeSelect">テーマ</label>
                <select class="form-select form-select-sm theme-select" id="themeSelect" aria-label="テーマカラー選択">
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
        var select = document.getElementById('themeSelect');
        if (!select) return;
        select.value = document.body.getAttribute('data-theme') || 'blue';
        if (!select.value) {
            select.value = 'blue';
            document.body.setAttribute('data-theme', 'blue');
        }
        select.addEventListener('change', function () {
            document.body.setAttribute('data-theme', select.value);
            try {
                localStorage.setItem(THEME_STORAGE_KEY, select.value);
            } catch (e) {}
        });
    })();
</script>
<div class="container">
