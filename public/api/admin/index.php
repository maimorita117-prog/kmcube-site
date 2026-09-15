<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_start();
$pdo = db(); $message = ''; $error = '';
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function insurance_plan_label(array $booking): string
{
    $plan = (string)($booking['insurance_plan'] ?? (!empty($booking['insurance']) ? 'standard' : 'basic'));
    $labels = ['basic' => '基本補償', 'standard' => '安心保険プラン', 'wide' => '安心保険プラン・ワイド'];
    return $labels[$plan] ?? $labels['basic'];
}
function logged_in(): bool { return !empty($_SESSION['kmcube_admin']); }
function csrf_ok(): bool { return isset($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? '')); }
function local_datetime(string $value): ?DateTimeImmutable { $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', clean_text($value, 16)); return $date ?: null; }
function store_vehicle_image(array $file, string $vehicleId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new RuntimeException('写真を受信できませんでした。');
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('写真は5MB以内にしてください。');
    $imageInfo = getimagesize((string)$file['tmp_name']);
    if ($imageInfo !== false && ((int)$imageInfo[0] * (int)$imageInfo[1]) > 24000000) throw new RuntimeException('写真の縦横サイズが大きすぎます。2400万画素以内にしてください。');
    $mime = (string)($imageInfo['mime'] ?? '');
    if (function_exists('finfo_open') && ($finfo = finfo_open(FILEINFO_MIME_TYPE))) { $mime = (string)finfo_file($finfo, (string)$file['tmp_name']); finfo_close($finfo); }
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime]) || $imageInfo === false) throw new RuntimeException('JPG、PNG、WebP形式の写真を選択してください。');
    $directory = dirname(__DIR__) . '/uploads/vehicles';
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) throw new RuntimeException('写真の保存先を作成できませんでした。');
    $filename = $vehicleId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string)$file['tmp_name'], $directory . '/' . $filename)) throw new RuntimeException('写真を保存できませんでした。');
    return '/api/uploads/vehicles/' . $filename;
}
function remove_uploaded_vehicle_image(string $path): void
{
    $uploadPrefix = '/api/uploads/vehicles/';
    if (strncmp($path, $uploadPrefix, strlen($uploadPrefix)) !== 0) return;
    $file = dirname(__DIR__) . '/uploads/vehicles/' . basename($path);
    if (is_file($file)) @unlink($file);
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(20));

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (($config['admin_password'] ?? 'CHANGE_ME_NOW') === 'CHANGE_ME_NOW') $error = 'config.local.php の管理パスワードを変更してから利用してください。';
    elseif (hash_equals((string)$config['admin_password'], (string)$_POST['password'])) { session_regenerate_id(true); $_SESSION['kmcube_admin'] = true; header('Location: ./'); exit; }
    else $error = 'パスワードが正しくありません。';
}

if (logged_in() && isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="kmcube-bookings-' . date('Ymd') . '.csv"'); echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w'); fputcsv($output, ['予約番号','状態','出発日時','返却日時','料金体系','車両','氏名','メール','電話','受取場所','保険・補償プラン','金額','追加サービス','作成日時']);
    foreach ($pdo->query('SELECT * FROM bookings ORDER BY created_at DESC') as $row) fputcsv($output, [$row['code'],$row['status'],$row['start_date'].' '.($row['start_time']??''),$row['end_date'].' '.($row['end_time']??''),$row['billing_mode']??'daily',$row['car_class'],$row['name'],$row['email'],$row['phone'],$row['pickup_location'],insurance_plan_label($row),$row['total'],$row['additional_services'],$row['created_at']]);
    fclose($output); exit;
}

if (logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_action'])) {
    if (!csrf_ok()) $error = '操作を確認できませんでした。画面を再読み込みしてください。';
    else {
        $action = (string)$_POST['vehicle_action']; $id = clean_text($_POST['vehicle_id'] ?? '', 20);
        if ($action === 'delete') {
            $existing = find_vehicle($pdo, $id, false);
            $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM bookings WHERE car_class=:id)+(SELECT COUNT(*) FROM availability_blocks WHERE car_class=:id)'); $stmt->execute([':id'=>$id]);
            if ((int)$stmt->fetchColumn() > 0) { $stmt=$pdo->prepare('UPDATE vehicles SET active=0,updated_at=:now WHERE id=:id'); $stmt->execute([':now'=>date('Y-m-d H:i:s'),':id'=>$id]); $message='予約履歴または満車設定があるため削除せず、受付停止にしました。履歴は安全に保持されます。'; }
            else { $stmt=$pdo->prepare('DELETE FROM vehicles WHERE id=:id'); $stmt->execute([':id'=>$id]); if ($existing) remove_uploaded_vehicle_image((string)($existing['image_path']??'')); $message='車両と登録写真を削除し、本サイトへ反映しました。'; }
        } elseif (in_array($action, ['add','update'], true)) {
            $label=clean_text($_POST['vehicle_label']??'',50); $model=clean_text($_POST['vehicle_model']??'',80); $daily=max(0,(int)($_POST['vehicle_daily_price']??0)); $hourly=max(0,(int)($_POST['vehicle_hourly_price']??0)); $inventory=max(0,min(99,(int)($_POST['vehicle_inventory']??0))); $sort=max(0,min(9999,(int)($_POST['vehicle_display_order']??0))); $active=!empty($_POST['vehicle_active'])?1:0;
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,19}$/',$id)||$label===''||$model===''||$daily<1||$hourly<1) $error='車両ID、名称、モデル、料金をご確認ください。IDは半角英小文字・数字・ハイフン・下線の2～20文字です。';
            else try {
                $existing = find_vehicle($pdo, $id, false);
                $oldImage = (string)($existing['image_path'] ?? '');
                $uploadedImage = store_vehicle_image($_FILES['vehicle_image'] ?? [], $id);
                $image = $uploadedImage ?? (!empty($_POST['vehicle_remove_image']) ? '' : $oldImage);
                $now=date('Y-m-d H:i:s');
                $sql=$action==='add'?'INSERT INTO vehicles(id,label,model,image_path,daily_price,hourly_price,inventory,active,display_order,created_at,updated_at) VALUES(:id,:label,:model,:image,:daily,:hourly,:inventory,:active,:sort,:now,:now)':'UPDATE vehicles SET label=:label,model=:model,image_path=:image,daily_price=:daily,hourly_price=:hourly,inventory=:inventory,active=:active,display_order=:sort,updated_at=:now WHERE id=:id';
                $stmt=$pdo->prepare($sql); $stmt->execute([':id'=>$id,':label'=>$label,':model'=>$model,':image'=>$image,':daily'=>$daily,':hourly'=>$hourly,':inventory'=>$inventory,':active'=>$active,':sort'=>$sort,':now'=>$now]);
                if ($oldImage !== '' && $oldImage !== $image) remove_uploaded_vehicle_image($oldImage);
                $message=$action==='add'?'車両と写真を登録し、本サイトへ反映しました。':'車両情報と写真を更新し、本サイトへ反映しました。';
            } catch (Throwable $exception) {
                if (!empty($uploadedImage)) remove_uploaded_vehicle_image((string)$uploadedImage);
                $error=strpos($exception->getMessage(),'UNIQUE')!==false?'同じ車両IDが登録されています。別のIDにしてください。':($exception instanceof RuntimeException?$exception->getMessage():'車両情報を保存できませんでした。');
            }
        }
    }
}

if (logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_action'])) {
    if (!csrf_ok()) $error='操作を確認できませんでした。画面を再読み込みしてください。';
    elseif ($_POST['block_action']==='delete') { $stmt=$pdo->prepare('DELETE FROM availability_blocks WHERE id=:id'); $stmt->execute([':id'=>(int)($_POST['block_id']??0)]); $message='利用不可設定を解除し、空車検索へ反映しました。'; }
    elseif (in_array($_POST['block_action'],['add','update'],true)) {
        $carClass=clean_text($_POST['block_car_class']??'',20); $start=local_datetime((string)($_POST['block_start_at']??'')); $end=local_datetime((string)($_POST['block_end_at']??'')); $vehicle=find_vehicle($pdo,$carClass,false);
        if (!$vehicle||(int)$vehicle['inventory']<1||!$start||!$end||$end<=$start) $error='車両クラスと利用不可期間をご確認ください。';
        else { $quantity=max(1,min((int)$vehicle['inventory'],(int)($_POST['block_quantity']??1))); $params=[':car'=>$carClass,':start'=>$start->format('Y-m-d H:i:s'),':end'=>$end->format('Y-m-d H:i:s'),':quantity'=>$quantity,':reason'=>clean_text($_POST['block_reason']??'',160)];
            if ($_POST['block_action']==='update') { $stmt=$pdo->prepare('UPDATE availability_blocks SET car_class=:car,start_at=:start,end_at=:end,quantity=:quantity,reason=:reason WHERE id=:id'); $params[':id']=(int)($_POST['block_id']??0); }
            else { $stmt=$pdo->prepare('INSERT INTO availability_blocks(car_class,start_at,end_at,quantity,reason,created_at) VALUES(:car,:start,:end,:quantity,:reason,:created)'); $params[':created']=date('Y-m-d H:i:s'); }
            $stmt->execute($params); $message=$_POST['block_action']==='update'?'満車・利用不可設定を更新しました。':'満車・利用不可設定を登録し、本サイトへ反映しました。'; }
    }
}

if (logged_in() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    if (!csrf_ok()) $error='操作を確認できませんでした。画面を再読み込みしてください。';
    else {
        $status = (string)($_POST['status'] ?? 'pending');
        if (!in_array($status, ['pending','confirmed','cancelled','change_requested'], true)) $error = '状態が正しくありません。';
        else {
            $bookingId = (int)$_POST['booking_id'];
            $beforeStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
            $beforeStmt->execute([':id' => $bookingId]);
            $before = $beforeStmt->fetch();
            $stmt = $pdo->prepare('UPDATE bookings SET status = :status, updated_at = :now, cancelled_at = CASE WHEN :status = "cancelled" THEN :now ELSE cancelled_at END WHERE id = :id');
            $stmt->execute([':status' => $status, ':now' => date('Y-m-d H:i:s'), ':id' => $bookingId]);
            $message = '予約状態を更新しました。';
            if ($before && $before['status'] !== $status && in_array($status, ['confirmed','cancelled'], true)) {
                $afterStmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
                $afterStmt->execute([':id' => $bookingId]);
                $after = $afterStmt->fetch();
                $mailStatus = $after ? send_booking_mail($after, '', $config, $status) : ['customer' => false];
                if ($mailStatus['customer']) $message = $status === 'confirmed' ? '予約を確定し、お客様へ確定メールを送信しました。' : '予約をキャンセルし、お客様へ案内メールを送信しました。';
                else $error = '予約状態は更新しましたが、お客様へのメール送信を確認できませんでした。メール設定をご確認ください。';
            }
        }
    }
}

$bookings=[]; $blocks=[]; $vehicles=[]; $vehicleLabels=[];
if (logged_in()) {
    $vehicles=vehicle_catalog($pdo,false); foreach($vehicles as $vehicle) $vehicleLabels[$vehicle['id']]=$vehicle['label']; $filter=(string)($_GET['status']??'all');
    if (in_array($filter,['pending','confirmed','cancelled','change_requested'],true)) { $stmt=$pdo->prepare('SELECT * FROM bookings WHERE status=:status ORDER BY start_date ASC,created_at DESC LIMIT 300'); $stmt->execute([':status'=>$filter]); $bookings=$stmt->fetchAll(); } else $bookings=$pdo->query('SELECT * FROM bookings ORDER BY start_date ASC,created_at DESC LIMIT 300')->fetchAll();
    $blocks=$pdo->query("SELECT * FROM availability_blocks ORDER BY end_at>=datetime('now','localtime') DESC,start_at ASC LIMIT 200")->fetchAll();
}
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>KMCUBE 予約・車両管理</title><style>
:root{--g:#174c3f;--y:#f8cd51;--p:#f7f4e9;--r:#b84f40;--l:#d6dfce}*{box-sizing:border-box}body{margin:0;color:#183f35;background:var(--p);font-family:"Yu Gothic",Meiryo,sans-serif}button,input,select{font:inherit}.login{display:grid;place-items:center;min-height:100vh;padding:20px}.card,.manager{background:#fff;border:1px solid var(--l);border-radius:22px}.card{width:min(430px,100%);padding:35px;box-shadow:0 20px 60px #174c3f1c}.card h1{margin:0}.card p,.manager>p{color:#687e76;line-height:1.7}.card label{display:grid;gap:8px;margin:25px 0 14px;font-weight:700}.card input{min-height:48px;padding:0 12px;border:1px solid #bdcbb9;border-radius:10px}.button,.manager button{min-height:42px;padding:0 14px;color:#fff;background:var(--g);border:0;border-radius:9px;font-weight:800;cursor:pointer}.card .button{width:100%}.notice,.error{padding:12px 15px;border-radius:10px;font-size:13px}.notice{background:#e5efdd}.error{color:#7a3027;background:#ffe2d9}.admin-header{display:flex;justify-content:space-between;gap:20px;padding:18px max(20px,calc((100vw - 1240px)/2));color:#fff;background:var(--g)}.admin-header h1{margin:0;font-size:21px}.admin-header a{color:#fff}.wrap{width:min(1240px,calc(100% - 40px));margin:35px auto}.manager{margin-bottom:26px;padding:24px}.manager h2{margin:0}.manager>p{margin:6px 0 18px;font-size:12px}.vehicle-add,.vehicle-row,.block-form,.block-item{display:grid;gap:9px;align-items:end}.vehicle-add{grid-template-columns:.8fr 1fr 1.2fr .8fr .8fr .55fr .55fr .65fr auto;padding:14px;background:#eef4e9;border-radius:12px}.vehicle-row{grid-template-columns:.8fr 1fr 1.2fr .8fr .8fr .55fr .55fr .65fr auto auto;padding:12px 0;border-bottom:1px solid #e3e9df}.vehicle-row form{display:contents}.manager label{display:grid;gap:5px;color:#60746d;font-size:10px;font-weight:800}.manager input,.manager select{width:100%;min-height:40px;padding:0 8px;border:1px solid #c5d1c1;border-radius:8px;background:#fff}.check{display:flex!important;align-items:center;gap:6px;min-height:40px}.check input{width:auto;min-height:auto}.vehicle-id{align-self:center;font:700 12px Arial}.state{display:inline-block;padding:4px 7px;border-radius:999px;background:#dcebdd;font-size:10px}.state.off{background:#eee}.manager .danger{color:var(--r);background:#fff;border:1px solid #d9a89f}.manager .pause{color:#664c13;background:var(--y)}.block-form{grid-template-columns:1fr 1.2fr 1.2fr .65fr 1fr auto}.block-list{display:grid;gap:8px;margin-top:16px}.block-item{grid-template-columns:1fr 1.2fr 1.2fr .6fr 1fr auto auto;padding:11px;background:#f5f2e8;border-radius:10px}.toolbar{display:flex;justify-content:space-between;gap:20px;margin-bottom:15px}.filters{display:flex;gap:7px;flex-wrap:wrap}.filters a,.export{padding:9px 13px;color:var(--g);background:#fff;border:1px solid #bdcbb9;border-radius:999px;text-decoration:none;font-size:12px;font-weight:800}.export{color:#fff;background:var(--g)}.table-wrap{overflow:auto;background:#fff;border:1px solid var(--l);border-radius:18px}table{width:100%;min-width:1120px;border-collapse:collapse}th,td{padding:12px 10px;border-bottom:1px solid #e3e9df;text-align:left;font-size:12px;vertical-align:top}th{background:#edf2e8}.status{display:inline-block;padding:5px 8px;color:#fff;background:var(--g);border-radius:999px;font-size:10px}.status-cancelled{background:#8a9691}.status-change_requested{color:#614715;background:var(--y)}.row-form{display:flex;gap:6px}.row-form button{min-height:34px}.change{margin-top:7px;padding:8px;background:#fff4c9;border-radius:8px}.empty{padding:35px;text-align:center;color:#71837c}.section-label{margin:28px 0 10px}@media(max-width:1080px){.vehicle-add,.vehicle-row{grid-template-columns:repeat(4,1fr)}.block-form,.block-item{grid-template-columns:repeat(3,1fr)}}@media(max-width:650px){.admin-header,.toolbar{flex-direction:column}.wrap{width:calc(100% - 24px);margin-top:20px}.card,.manager{padding:20px 15px}.vehicle-add,.vehicle-row,.block-form,.block-item{grid-template-columns:1fr}.vehicle-row form{display:grid;gap:9px}.manager button{width:100%}.check{justify-content:flex-start}}
.vehicle-add{grid-template-columns:repeat(4,minmax(0,1fr))}.vehicle-add .photo-field{grid-column:span 2}.vehicle-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:14px;margin-top:16px}.vehicle-row{display:block;padding:13px;background:#fbfaf4;border:1px solid #dfe6dc;border-radius:16px}.vehicle-row form.vehicle-edit{display:block}.vehicle-photo{display:grid;place-items:center;width:100%;aspect-ratio:4/3;margin-bottom:13px;overflow:hidden;color:#55766c;background:#e9f0e4;border-radius:12px}.vehicle-photo img{width:100%;height:100%;object-fit:cover}.vehicle-photo svg{width:42px;height:42px}.vehicle-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.vehicle-fields .wide{grid-column:1/-1}.vehicle-actions{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:12px}.vehicle-delete{margin-top:8px}.vehicle-delete button{width:100%}.photo-remove{display:flex!important;align-items:center;gap:7px;margin-top:8px}.photo-remove input{width:auto;min-height:auto}@media(max-width:650px){.vehicle-add,.vehicle-fields{grid-template-columns:1fr}.vehicle-add .photo-field,.vehicle-fields .wide{grid-column:auto}.vehicle-list{grid-template-columns:1fr}}
</style></head><body>
<?php if(!logged_in()): ?><main class="login"><form class="card" method="post"><small>KMCUBE YAKUSHIMA</small><h1>予約・車両管理</h1><p>車両、在庫、料金、満車期間、予約状況をまとめて管理します。</p><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><label>管理パスワード<input type="password" name="password" required autocomplete="current-password"></label><button class="button">ログイン</button></form></main>
<?php else: ?><header class="admin-header"><h1>KMCUBE 予約・車両管理</h1><a href="?logout=1">ログアウト</a></header><main class="wrap"><?php if($message):?><p class="notice"><?=e($message)?></p><?php endif;?><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?>
<section class="manager"><h2>車両・写真・料金・登録台数</h2><p>代表写真、名称、モデル、料金、台数、受付状態をまとめて管理できます。保存した内容は本サイトの予約画面へ即時反映されます。「受付中」を外すと公開画面から非表示になります。</p>
<form class="vehicle-add" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="vehicle_action" value="add"><label>車両ID<input name="vehicle_id" placeholder="suv" maxlength="20" required></label><label>表示名<input name="vehicle_label" placeholder="SUV" required></label><label>モデル・説明<input name="vehicle_model" placeholder="5人乗りクラス" required></label><label class="photo-field">代表写真（JPG・PNG・WebP／5MB以内）<input type="file" name="vehicle_image" accept="image/jpeg,image/png,image/webp"></label><label>日額（税込）<input type="number" name="vehicle_daily_price" min="1" value="8800" required></label><label>時間単価（税込）<input type="number" name="vehicle_hourly_price" min="1" value="1500" required></label><label>登録台数<input type="number" name="vehicle_inventory" min="0" max="99" value="1" required></label><label>表示順<input type="number" name="vehicle_display_order" min="0" max="9999" value="40"></label><label class="check"><input type="checkbox" name="vehicle_active" value="1" checked>受付中</label><button>写真付きで新規登録</button></form>
<div class="vehicle-list"><?php foreach($vehicles as $vehicle):?><article class="vehicle-row"><form class="vehicle-edit" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="vehicle_action" value="update"><input type="hidden" name="vehicle_id" value="<?=e($vehicle['id'])?>"><div class="vehicle-photo"><?php if(!empty($vehicle['image_path'])):?><img src="<?=e($vehicle['image_path'])?>" alt="<?=e($vehicle['label'])?>の登録写真"><?php else:?><span>写真未登録</span><?php endif;?></div><div class="vehicle-fields"><div class="vehicle-id wide"><?=e($vehicle['id'])?>　<span class="state <?=$vehicle['active']?'':'off'?>"><?=$vehicle['active']?'受付中':'受付停止'?></span></div><label>表示名<input name="vehicle_label" value="<?=e($vehicle['label'])?>" required></label><label>モデル・説明<input name="vehicle_model" value="<?=e($vehicle['model'])?>" required></label><label>日額（税込）<input type="number" name="vehicle_daily_price" min="1" value="<?=e($vehicle['daily_price'])?>" required></label><label>時間単価（税込）<input type="number" name="vehicle_hourly_price" min="1" value="<?=e($vehicle['hourly_price'])?>" required></label><label>登録台数<input type="number" name="vehicle_inventory" min="0" max="99" value="<?=e($vehicle['inventory'])?>" required></label><label>表示順<input type="number" name="vehicle_display_order" min="0" max="9999" value="<?=e($vehicle['display_order'])?>"></label><label class="wide">写真を差し替える<input type="file" name="vehicle_image" accept="image/jpeg,image/png,image/webp"></label><?php if(!empty($vehicle['image_path'])):?><label class="photo-remove wide"><input type="checkbox" name="vehicle_remove_image" value="1">現在の写真を削除</label><?php endif;?></div><div class="vehicle-actions"><label class="check"><input type="checkbox" name="vehicle_active" value="1" <?=$vehicle['active']?'checked':''?>>受付中</label><button>変更を保存</button></div></form><form class="vehicle-delete" method="post" onsubmit="return confirm('この車両を削除しますか？予約履歴がある場合は受付停止になります。')"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="vehicle_action" value="delete"><input type="hidden" name="vehicle_id" value="<?=e($vehicle['id'])?>"><button class="danger">車両を削除</button></form></article><?php endforeach;?></div></section>
<section class="manager"><h2>空車・満車期間</h2><p>整備・貸出停止などで予約を受けない台数と期間を登録します。既存の設定も一覧から編集・解除できます。</p><?php if($vehicles):?><form class="block-form" method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="block_action" value="add"><label>車両クラス<select name="block_car_class"><?php foreach($vehicles as $vehicle):if((int)$vehicle['inventory']<1)continue;?><option value="<?=e($vehicle['id'])?>"><?=e($vehicle['label'])?>（全<?=e($vehicle['inventory'])?>台）</option><?php endforeach;?></select></label><label>利用不可・開始<input type="datetime-local" name="block_start_at" value="<?=date('Y-m-d\TH:i',strtotime('tomorrow 09:00'))?>" required></label><label>利用不可・終了<input type="datetime-local" name="block_end_at" value="<?=date('Y-m-d\TH:i',strtotime('tomorrow 18:00'))?>" required></label><label>利用不可台数<input type="number" name="block_quantity" min="1" max="99" value="1" required></label><label>理由<input name="block_reason" placeholder="整備、貸出停止など"></label><button class="pause">満車設定を登録</button></form><?php else:?><p class="empty">先に車両を登録してください。</p><?php endif;?>
<?php if($blocks):?><div class="block-list"><?php foreach($blocks as $block):?><form class="block-item" method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="block_id" value="<?=e($block['id'])?>"><label>車両<select name="block_car_class"><?php foreach($vehicles as $vehicle):?><option value="<?=e($vehicle['id'])?>" <?=$vehicle['id']===$block['car_class']?'selected':''?>><?=e($vehicle['label'])?></option><?php endforeach;?></select></label><label>開始<input type="datetime-local" name="block_start_at" value="<?=e(str_replace(' ','T',substr($block['start_at'],0,16)))?>" required></label><label>終了<input type="datetime-local" name="block_end_at" value="<?=e(str_replace(' ','T',substr($block['end_at'],0,16)))?>" required></label><label>台数<input type="number" name="block_quantity" min="1" max="99" value="<?=e($block['quantity'])?>" required></label><label>理由<input name="block_reason" value="<?=e($block['reason'])?>"></label><button name="block_action" value="update">更新</button><button class="danger" name="block_action" value="delete" formnovalidate onclick="return confirm('この満車設定を解除しますか？')">解除</button></form><?php endforeach;?></div><?php endif;?></section>
<div class="toolbar"><nav class="filters"><a href="./">すべて</a><a href="?status=pending">確認待ち</a><a href="?status=confirmed">予約確定</a><a href="?status=change_requested">変更確認中</a><a href="?status=cancelled">キャンセル</a></nav><a class="export" href="?export=1">CSV出力</a></div><h2 class="section-label">予約一覧</h2><div class="table-wrap"><table><thead><tr><th>予約番号</th><th>状態</th><th>利用日時</th><th>車両</th><th>お客様</th><th>場所・人数</th><th>料金</th><th>追加・要望</th><th>操作</th></tr></thead><tbody>
<?php foreach($bookings as $booking):$change=json_decode($booking['change_request']?:'null',true);?><tr><td><strong><?=e($booking['code'])?></strong><br><?=e($booking['created_at'])?></td><td><span class="status status-<?=e($booking['status'])?>"><?=e($booking['status'])?></span></td><td><?=e($booking['start_date'])?> <?=e($booking['start_time']??'09:00')?><br>～ <?=e($booking['end_date'])?> <?=e($booking['end_time']??'17:00')?><br><small><?=($booking['billing_mode']??'daily')==='hourly'?'時間制':'日数制'?></small></td><td><?=e($vehicleLabels[$booking['car_class']]??$booking['car_class'])?><br><small><?=e(insurance_plan_label($booking))?></small></td><td><?=e($booking['name'])?><br><a href="mailto:<?=e($booking['email'])?>"><?=e($booking['email'])?></a><br><?=e($booking['phone'])?></td><td><?=e($booking['pickup_location'])?><br><?=e($booking['people'])?>名</td><td>¥<?=number_format((int)$booking['total'])?></td><td><?=e(implode(', ',json_decode($booking['additional_services']?:'[]',true)?:[]))?><br><?=nl2br(e($booking['notes']))?><?php if($change):?><div class="change">変更希望: <?=e($change['startDate']??'')?> <?=e($change['startTime']??'')?> ～ <?=e($change['endDate']??'')?> <?=e($change['endTime']??'')?> / <?=e($change['billingMode']??'')?> / <?=e($vehicleLabels[$change['carClass']??'']??($change['carClass']??''))?><br><?=e($change['message']??'')?></div><?php endif;?></td><td><form class="row-form" method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="booking_id" value="<?=e($booking['id'])?>"><select name="status"><option value="pending" <?=$booking['status']==='pending'?'selected':''?>>確認待ち</option><option value="confirmed" <?=$booking['status']==='confirmed'?'selected':''?>>予約確定</option><option value="change_requested" <?=$booking['status']==='change_requested'?'selected':''?>>変更確認中</option><option value="cancelled" <?=$booking['status']==='cancelled'?'selected':''?>>キャンセル</option></select><button>更新</button></form></td></tr><?php endforeach;?><?php if(!$bookings):?><tr><td class="empty" colspan="9">該当する予約はありません。</td></tr><?php endif;?></tbody></table></div></main><?php endif;?></body></html>
