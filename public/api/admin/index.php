<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
$pdo = db();
$message = '';
$error = '';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function logged_in(): bool { return !empty($_SESSION['kmcube_admin']); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(20));

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (($config['admin_password'] ?? 'CHANGE_ME_NOW') === 'CHANGE_ME_NOW') {
        $error = 'config.php の管理パスワードを変更してから利用してください。';
    } elseif (hash_equals((string)$config['admin_password'], (string)$_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['kmcube_admin'] = true;
        header('Location: ./');
        exit;
    } else {
        $error = 'パスワードが正しくありません。';
    }
}

if (logged_in() && isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kmcube-bookings-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['予約番号', '状態', '出発日', '返却日', '車両', '氏名', 'メール', '電話', '受取場所', '金額', '追加サービス', '作成日時']);
    foreach ($pdo->query('SELECT * FROM bookings ORDER BY created_at DESC') as $row) {
        fputcsv($output, [$row['code'], $row['status'], $row['start_date'], $row['end_date'], $row['car_class'], $row['name'], $row['email'], $row['phone'], $row['pickup_location'], $row['total'], $row['additional_services'], $row['created_at']]);
    }
    fclose($output);
    exit;
}

if (logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = '操作を確認できませんでした。画面を再読み込みしてください。';
    } else {
        $id = (int)$_POST['booking_id'];
        $status = (string)($_POST['status'] ?? 'pending');
        $allowed = ['pending', 'confirmed', 'cancelled', 'change_requested'];
        if (!in_array($status, $allowed, true)) {
            $error = '状態が正しくありません。';
        } else {
            $stmt = $pdo->prepare('UPDATE bookings SET status = :status, updated_at = :now, cancelled_at = CASE WHEN :status = "cancelled" THEN :now ELSE cancelled_at END WHERE id = :id');
            $stmt->execute([':status' => $status, ':now' => date('Y-m-d H:i:s'), ':id' => $id]);
            $message = '予約状態を更新しました。';
        }
    }
}

$bookings = [];
if (logged_in()) {
    $filter = (string)($_GET['status'] ?? 'all');
    if (in_array($filter, ['pending', 'confirmed', 'cancelled', 'change_requested'], true)) {
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE status = :status ORDER BY start_date ASC, created_at DESC LIMIT 300');
        $stmt->execute([':status' => $filter]);
        $bookings = $stmt->fetchAll();
    } else {
        $bookings = $pdo->query('SELECT * FROM bookings ORDER BY start_date ASC, created_at DESC LIMIT 300')->fetchAll();
    }
}
?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>KMCUBE 予約管理</title>
<style>
:root{--green:#174c3f;--yellow:#f8cd51;--paper:#f7f4e9;--red:#b84f40}*{box-sizing:border-box}body{margin:0;color:#183f35;background:var(--paper);font-family:"Yu Gothic",Meiryo,sans-serif}button,input,select{font:inherit}.login{display:grid;place-items:center;min-height:100vh;padding:20px}.card{width:min(430px,100%);padding:35px;background:#fff;border:1px solid #d6dfce;border-radius:25px;box-shadow:0 20px 60px #174c3f1c}.card h1{margin:0 0 8px}.card p{color:#687e76;line-height:1.7}.card label{display:grid;gap:8px;margin:25px 0 14px;font-weight:700}.card input{min-height:48px;padding:0 12px;border:1px solid #bdcbb9;border-radius:10px}.button{min-height:46px;padding:0 18px;color:#fff;background:var(--green);border:0;border-radius:999px;font-weight:800;cursor:pointer}.card .button{width:100%}.notice,.error{padding:12px 15px;border-radius:10px;font-size:13px}.notice{background:#e5efdd}.error{color:#7a3027;background:#ffe2d9}.admin-header{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px max(20px,calc((100vw - 1240px)/2));color:#fff;background:var(--green)}.admin-header h1{margin:0;font-size:21px}.admin-header a{color:#fff;text-decoration:none;font-size:13px}.wrap{width:min(1240px,calc(100% - 40px));margin:35px auto}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}.filters{display:flex;gap:7px;flex-wrap:wrap}.filters a,.export{padding:9px 13px;color:var(--green);background:#fff;border:1px solid #bdcbb9;border-radius:999px;text-decoration:none;font-size:12px;font-weight:800}.export{color:#fff;background:var(--green)}.table-wrap{overflow:auto;background:#fff;border:1px solid #d6dfce;border-radius:18px}table{width:100%;border-collapse:collapse;min-width:1120px}th,td{padding:13px 11px;border-bottom:1px solid #e3e9df;text-align:left;font-size:12px;vertical-align:top}th{position:sticky;top:0;color:#61766e;background:#edf2e8;font-size:11px}td strong{font-family:Arial,sans-serif}.status{display:inline-block;padding:5px 8px;color:#fff;background:var(--green);border-radius:999px;font-size:10px;font-weight:800}.status-cancelled{background:#8a9691}.status-change_requested{color:#614715;background:var(--yellow)}.row-form{display:flex;gap:6px}.row-form select{min-height:34px;border:1px solid #c6d2c2;border-radius:8px}.row-form button{padding:0 10px;color:#fff;background:var(--green);border:0;border-radius:8px}.change{max-width:260px;margin-top:7px;padding:8px;background:#fff4c9;border-radius:8px;font-size:10px;white-space:pre-wrap}.empty{padding:50px;text-align:center;color:#71837c}@media(max-width:650px){.admin-header{align-items:flex-start;flex-direction:column}.wrap{width:calc(100% - 24px);margin-top:20px}.toolbar{align-items:flex-start;flex-direction:column}.card{padding:27px 20px}}
</style></head><body>
<?php if (!logged_in()): ?>
<main class="login"><form class="card" method="post"><small>KMCUBE YAKUSHIMA</small><h1>予約管理</h1><p>予約一覧、確定・キャンセル、変更依頼を管理します。</p><?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif; ?><label>管理パスワード<input type="password" name="password" required autocomplete="current-password"></label><button class="button">ログイン</button></form></main>
<?php else: ?>
<header class="admin-header"><h1>KMCUBE 予約管理</h1><a href="?logout=1">ログアウト</a></header><main class="wrap">
<?php if ($message): ?><p class="notice"><?=e($message)?></p><?php endif; ?><?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif; ?>
<div class="toolbar"><nav class="filters"><a href="./">すべて</a><a href="?status=pending">確認待ち</a><a href="?status=confirmed">予約確定</a><a href="?status=change_requested">変更確認中</a><a href="?status=cancelled">キャンセル</a></nav><a class="export" href="?export=1">CSV出力</a></div>
<div class="table-wrap"><table><thead><tr><th>予約番号</th><th>状態</th><th>利用日</th><th>車両</th><th>お客様</th><th>場所・人数</th><th>料金</th><th>追加・要望</th><th>操作</th></tr></thead><tbody>
<?php foreach ($bookings as $booking): $change = json_decode($booking['change_request'] ?: 'null', true); ?>
<tr><td><strong><?=e($booking['code'])?></strong><br><?=e($booking['created_at'])?></td><td><span class="status status-<?=e($booking['status'])?>"><?=e($booking['status'])?></span></td><td><?=e($booking['start_date'])?><br>～ <?=e($booking['end_date'])?></td><td><?=e($booking['car_class'])?></td><td><?=e($booking['name'])?><br><a href="mailto:<?=e($booking['email'])?>"><?=e($booking['email'])?></a><br><?=e($booking['phone'])?></td><td><?=e($booking['pickup_location'])?><br><?=e($booking['people'])?>名</td><td>¥<?=number_format((int)$booking['total'])?></td><td><?=e(implode(', ', json_decode($booking['additional_services'] ?: '[]', true) ?: []))?><br><?=nl2br(e($booking['notes']))?><?php if ($change): ?><div class="change">変更希望: <?=e($change['startDate'] ?? '')?> ～ <?=e($change['endDate'] ?? '')?> / <?=e($change['carClass'] ?? '')?><br><?=e($change['message'] ?? '')?></div><?php endif; ?></td><td><form class="row-form" method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="booking_id" value="<?=e($booking['id'])?>"><select name="status"><option value="pending" <?=$booking['status']==='pending'?'selected':''?>>確認待ち</option><option value="confirmed" <?=$booking['status']==='confirmed'?'selected':''?>>予約確定</option><option value="change_requested" <?=$booking['status']==='change_requested'?'selected':''?>>変更確認中</option><option value="cancelled" <?=$booking['status']==='cancelled'?'selected':''?>>キャンセル</option></select><button>更新</button></form></td></tr>
<?php endforeach; ?>
<?php if (!$bookings): ?><tr><td class="empty" colspan="9">該当する予約はありません。</td></tr><?php endif; ?>
</tbody></table></div></main>
<?php endif; ?>
</body></html>
