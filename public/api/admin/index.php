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
        $error = 'config.local.php の管理パスワードを変更してから利用してください。';
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
    fputcsv($output, ['予約番号', '状態', '出発日時', '返却日時', '料金体系', '車両', '氏名', 'メール', '電話', '受取場所', '金額', '追加サービス', '作成日時']);
    foreach ($pdo->query('SELECT * FROM bookings ORDER BY created_at DESC') as $row) {
        fputcsv($output, [$row['code'], $row['status'], $row['start_date'] . ' ' . ($row['start_time'] ?? ''), $row['end_date'] . ' ' . ($row['end_time'] ?? ''), $row['billing_mode'] ?? 'daily', $row['car_class'], $row['name'], $row['email'], $row['phone'], $row['pickup_location'], $row['total'], $row['additional_services'], $row['created_at']]);
    }
    fclose($output);
    exit;
}

if (logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_action'])) {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = '操作を確認できませんでした。画面を再読み込みしてください。';
    } elseif ($_POST['block_action'] === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM availability_blocks WHERE id = :id');
        $stmt->execute([':id' => (int)($_POST['block_id'] ?? 0)]);
        $message = '利用不可設定を解除し、空車検索へ反映しました。';
    } elseif ($_POST['block_action'] === 'add') {
        $carClass = clean_text($_POST['block_car_class'] ?? '', 20);
        $startDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', clean_text($_POST['block_start_at'] ?? '', 16));
        $endDateTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', clean_text($_POST['block_end_at'] ?? '', 16));
        if (!isset($config['cars'][$carClass]) || !$startDateTime || !$endDateTime || $endDateTime <= $startDateTime) {
            $error = '車両クラスと利用不可期間をご確認ください。';
        } else {
            $quantity = max(1, min((int)$config['cars'][$carClass]['inventory'], (int)($_POST['block_quantity'] ?? 1)));
            $stmt = $pdo->prepare('INSERT INTO availability_blocks(car_class, start_at, end_at, quantity, reason, created_at) VALUES(:car, :start, :end, :quantity, :reason, :created)');
            $stmt->execute([':car' => $carClass, ':start' => $startDateTime->format('Y-m-d H:i:s'), ':end' => $endDateTime->format('Y-m-d H:i:s'), ':quantity' => $quantity, ':reason' => clean_text($_POST['block_reason'] ?? '', 160), ':created' => date('Y-m-d H:i:s')]);
            $message = '満車・利用不可設定を登録し、本サイトへ反映しました。';
        }
    }
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
$blocks = [];
if (logged_in()) {
    $filter = (string)($_GET['status'] ?? 'all');
    if (in_array($filter, ['pending', 'confirmed', 'cancelled', 'change_requested'], true)) {
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE status = :status ORDER BY start_date ASC, created_at DESC LIMIT 300');
        $stmt->execute([':status' => $filter]);
        $bookings = $stmt->fetchAll();
    } else {
        $bookings = $pdo->query('SELECT * FROM bookings ORDER BY start_date ASC, created_at DESC LIMIT 300')->fetchAll();
    }
    $blocks = $pdo->query("SELECT * FROM availability_blocks ORDER BY end_at >= datetime('now', 'localtime') DESC, start_at ASC LIMIT 200")->fetchAll();
}
?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>KMCUBE 予約管理</title>
<style>
:root{--green:#174c3f;--yellow:#f8cd51;--paper:#f7f4e9;--red:#b84f40}*{box-sizing:border-box}body{margin:0;color:#183f35;background:var(--paper);font-family:"Yu Gothic",Meiryo,sans-serif}button,input,select{font:inherit}.login{display:grid;place-items:center;min-height:100vh;padding:20px}.card{width:min(430px,100%);padding:35px;background:#fff;border:1px solid #d6dfce;border-radius:25px;box-shadow:0 20px 60px #174c3f1c}.card h1{margin:0 0 8px}.card p{color:#687e76;line-height:1.7}.card label{display:grid;gap:8px;margin:25px 0 14px;font-weight:700}.card input{min-height:48px;padding:0 12px;border:1px solid #bdcbb9;border-radius:10px}.button{min-height:46px;padding:0 18px;color:#fff;background:var(--green);border:0;border-radius:999px;font-weight:800;cursor:pointer}.card .button{width:100%}.notice,.error{padding:12px 15px;border-radius:10px;font-size:13px}.notice{background:#e5efdd}.error{color:#7a3027;background:#ffe2d9}.admin-header{display:flex;align-items:center;justify-content:space-between;gap:25px;padding:18px max(20px,calc((100vw - 1240px)/2));color:#fff;background:var(--green)}.admin-header h1{margin:0;font-size:21px}.admin-header a{color:#fff;text-decoration:none;font-size:13px}.wrap{width:min(1240px,calc(100% - 40px));margin:35px auto}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}.filters{display:flex;gap:7px;flex-wrap:wrap}.filters a,.export{padding:9px 13px;color:var(--green);background:#fff;border:1px solid #bdcbb9;border-radius:999px;text-decoration:none;font-size:12px;font-weight:800}.export{color:#fff;background:var(--green)}.table-wrap{overflow:auto;background:#fff;border:1px solid #d6dfce;border-radius:18px}table{width:100%;border-collapse:collapse;min-width:1120px}th,td{padding:13px 11px;border-bottom:1px solid #e3e9df;text-align:left;font-size:12px;vertical-align:top}th{position:sticky;top:0;color:#61766e;background:#edf2e8;font-size:11px}td strong{font-family:Arial,sans-serif}.status{display:inline-block;padding:5px 8px;color:#fff;background:var(--green);border-radius:999px;font-size:10px;font-weight:800}.status-cancelled{background:#8a9691}.status-change_requested{color:#614715;background:var(--yellow)}.row-form{display:flex;gap:6px}.row-form select{min-height:34px;border:1px solid #c6d2c2;border-radius:8px}.row-form button{padding:0 10px;color:#fff;background:var(--green);border:0;border-radius:8px}.change{max-width:260px;margin-top:7px;padding:8px;background:#fff4c9;border-radius:8px;font-size:10px;white-space:pre-wrap}.empty{padding:50px;text-align:center;color:#71837c}@media(max-width:650px){.admin-header{align-items:flex-start;flex-direction:column}.wrap{width:calc(100% - 24px);margin-top:20px}.toolbar{align-items:flex-start;flex-direction:column}.card{padding:27px 20px}}
.availability-manager{margin:0 0 26px;padding:24px;background:#fff;border:1px solid #d6dfce;border-radius:20px}.availability-manager h2{margin:0;font-size:20px}.availability-manager>p{margin:6px 0 18px;color:#667c74;font-size:12px;line-height:1.7}.block-form{display:grid;grid-template-columns:1fr 1.25fr 1.25fr .75fr 1.2fr auto;gap:10px;align-items:end}.block-form label{display:grid;gap:6px;color:#60746d;font-size:11px;font-weight:800}.block-form input,.block-form select{width:100%;min-height:42px;padding:0 9px;border:1px solid #c5d1c1;border-radius:9px}.block-form button{min-height:42px;padding:0 14px;color:#fff;background:var(--red);border:0;border-radius:9px;font-weight:800}.block-list{display:grid;gap:8px;margin-top:18px}.block-item{display:grid;grid-template-columns:1fr 1.4fr auto auto;gap:15px;align-items:center;padding:11px 13px;background:#f5f2e8;border-radius:10px;font-size:12px}.block-item strong{color:var(--red)}.block-item form button{padding:7px 10px;color:var(--red);background:#fff;border:1px solid #d9a89f;border-radius:8px}.section-label{margin:30px 0 10px;font-size:19px}@media(max-width:900px){.block-form{grid-template-columns:1fr 1fr}.block-form button{grid-column:1/-1}.block-item{grid-template-columns:1fr 1fr}}@media(max-width:650px){.block-form,.block-item{grid-template-columns:1fr}.block-form button{grid-column:auto}}
</style></head><body>
<?php if (!logged_in()): ?>
<main class="login"><form class="card" method="post"><small>KMCUBE YAKUSHIMA</small><h1>予約管理</h1><p>予約一覧、確定・キャンセル、変更依頼を管理します。</p><?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif; ?><label>管理パスワード<input type="password" name="password" required autocomplete="current-password"></label><button class="button">ログイン</button></form></main>
<?php else: ?>
<header class="admin-header"><h1>KMCUBE 予約管理</h1><a href="?logout=1">ログアウト</a></header><main class="wrap">
<?php if ($message): ?><p class="notice"><?=e($message)?></p><?php endif; ?><?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif; ?>
<section class="availability-manager"><h2>空車・満車管理</h2><p>整備・貸出停止などで予約を受けない車両数と期間を登録します。登録内容は本サイトの空車検索へ即時反映されます。全台を満車にする場合は、そのクラスの在庫台数を入力してください。</p>
<form class="block-form" method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="block_action" value="add">
<label>車両クラス<select name="block_car_class"><?php foreach ($config['cars'] as $carId => $car): ?><option value="<?=e($carId)?>"><?=e($car['label'])?>（全<?=e($car['inventory'])?>台）</option><?php endforeach; ?></select></label>
<label>利用不可・開始<input type="datetime-local" name="block_start_at" value="<?=date('Y-m-d\TH:i', strtotime('tomorrow 09:00'))?>" required></label><label>利用不可・終了<input type="datetime-local" name="block_end_at" value="<?=date('Y-m-d\TH:i', strtotime('tomorrow 18:00'))?>" required></label>
<label>利用不可台数<input type="number" name="block_quantity" min="1" max="8" value="1" required></label><label>理由<input name="block_reason" placeholder="整備、貸出停止など"></label><button>満車・利用不可を登録</button></form>
<?php if ($blocks): ?><div class="block-list"><?php foreach ($blocks as $block): ?><div class="block-item"><strong><?=e($config['cars'][$block['car_class']]['label'] ?? $block['car_class'])?>　<?=e($block['quantity'])?>台</strong><span><?=e($block['start_at'])?> ～ <?=e($block['end_at'])?></span><span><?=e($block['reason'] ?: '理由なし')?></span><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="block_action" value="delete"><input type="hidden" name="block_id" value="<?=e($block['id'])?>"><button>解除</button></form></div><?php endforeach; ?></div><?php endif; ?>
</section>
<div class="toolbar"><nav class="filters"><a href="./">すべて</a><a href="?status=pending">確認待ち</a><a href="?status=confirmed">予約確定</a><a href="?status=change_requested">変更確認中</a><a href="?status=cancelled">キャンセル</a></nav><a class="export" href="?export=1">CSV出力</a></div>
<h2 class="section-label">予約一覧</h2><div class="table-wrap"><table><thead><tr><th>予約番号</th><th>状態</th><th>利用日時</th><th>車両</th><th>お客様</th><th>場所・人数</th><th>料金</th><th>追加・要望</th><th>操作</th></tr></thead><tbody>
<?php foreach ($bookings as $booking): $change = json_decode($booking['change_request'] ?: 'null', true); ?>
<tr><td><strong><?=e($booking['code'])?></strong><br><?=e($booking['created_at'])?></td><td><span class="status status-<?=e($booking['status'])?>"><?=e($booking['status'])?></span></td><td><?=e($booking['start_date'])?> <?=e($booking['start_time'] ?? '09:00')?><br>～ <?=e($booking['end_date'])?> <?=e($booking['end_time'] ?? '17:00')?><br><small><?=e(($booking['billing_mode'] ?? 'daily') === 'hourly' ? '時間制' : '日数制')?></small></td><td><?=e($booking['car_class'])?></td><td><?=e($booking['name'])?><br><a href="mailto:<?=e($booking['email'])?>"><?=e($booking['email'])?></a><br><?=e($booking['phone'])?></td><td><?=e($booking['pickup_location'])?><br><?=e($booking['people'])?>名</td><td>¥<?=number_format((int)$booking['total'])?></td><td><?=e(implode(', ', json_decode($booking['additional_services'] ?: '[]', true) ?: []))?><br><?=nl2br(e($booking['notes']))?><?php if ($change): ?><div class="change">変更希望: <?=e($change['startDate'] ?? '')?> <?=e($change['startTime'] ?? '')?> ～ <?=e($change['endDate'] ?? '')?> <?=e($change['endTime'] ?? '')?> / <?=e($change['billingMode'] ?? '')?> / <?=e($change['carClass'] ?? '')?><br><?=e($change['message'] ?? '')?></div><?php endif; ?></td><td><form class="row-form" method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="booking_id" value="<?=e($booking['id'])?>"><select name="status"><option value="pending" <?=$booking['status']==='pending'?'selected':''?>>確認待ち</option><option value="confirmed" <?=$booking['status']==='confirmed'?'selected':''?>>予約確定</option><option value="change_requested" <?=$booking['status']==='change_requested'?'selected':''?>>変更確認中</option><option value="cancelled" <?=$booking['status']==='cancelled'?'selected':''?>>キャンセル</option></select><button>更新</button></form></td></tr>
<?php endforeach; ?>
<?php if (!$bookings): ?><tr><td class="empty" colspan="9">該当する予約はありません。</td></tr><?php endif; ?>
</tbody></table></div></main>
<?php endif; ?>
</body></html>
