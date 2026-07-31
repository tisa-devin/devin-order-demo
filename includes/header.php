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
        // ちらつき防止のため、描画前に保存済みテーマを適用する
        (function () {
            var theme = localStorage.getItem('themeColor') || 'blue';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        /* テーマカラー定義（data-theme 属性で切替） */
        html[data-theme="blue"] {
            --theme-primary: #0d6efd;
            --theme-primary-hover: #0b5ed7;
        }
        html[data-theme="green"] {
            --theme-primary: #198754;
            --theme-primary-hover: #157347;
        }
        html[data-theme="orange"] {
            --theme-primary: #fd7e14;
            --theme-primary-hover: #e8690b;
        }
        html[data-theme="dark"] {
            --theme-primary: #212529;
            --theme-primary-hover: #1a1e21;
        }
        /* 未指定時のフォールバック（デフォルト：ブルー） */
        html:not([data-theme]) {
            --theme-primary: #0d6efd;
            --theme-primary-hover: #0b5ed7;
        }

        .navbar-brand { font-weight: bold; }
        .table th { white-space: nowrap; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .status-badge { font-size: 0.75rem; }

        /* ヘッダー背景をテーマ色で上書き */
        .navbar.bg-primary {
            background-color: var(--theme-primary) !important;
        }

        /* 主要ボタンをテーマ色で上書き */
        .btn-primary {
            background-color: var(--theme-primary);
            border-color: var(--theme-primary);
        }
        .btn-primary:hover,
        .btn-primary:focus,
        .btn-primary:active {
            background-color: var(--theme-primary-hover);
            border-color: var(--theme-primary-hover);
        }
        .btn-outline-primary {
            color: var(--theme-primary);
            border-color: var(--theme-primary);
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary:active {
            background-color: var(--theme-primary);
            border-color: var(--theme-primary);
            color: #fff;
        }

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
            <div class="d-flex align-items-center ms-auto">
                <label for="themeSelect" class="text-white me-2 mb-0">テーマ</label>
                <select id="themeSelect" class="form-select form-select-sm" style="width: auto;">
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
    // テーマ選択の復元と保存
    (function () {
        var select = document.getElementById('themeSelect');
        if (!select) return;
        var current = localStorage.getItem('themeColor') || 'blue';
        select.value = current;
        document.documentElement.setAttribute('data-theme', current);
        select.addEventListener('change', function () {
            var value = select.value;
            localStorage.setItem('themeColor', value);
            document.documentElement.setAttribute('data-theme', value);
        });
    })();
</script>
<div class="container">
