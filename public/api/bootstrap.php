<?php
declare(strict_types=1);

$configFile = is_file(__DIR__ . '/config.local.php') ? __DIR__ . '/config.local.php' : __DIR__ . '/config.sample.php';
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tokyo');

function db(): PDO
{
    global $config;
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $storage = __DIR__ . '/storage';
    if (!is_dir($storage) && !mkdir($storage, 0770, true) && !is_dir($storage)) {
        throw new RuntimeException('予約データ保存先を作成できません。');
    }
    $pdo = new PDO('sqlite:' . $storage . '/kmcube-bookings.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=8000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        access_token_hash TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "pending",
        car_class TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        start_time TEXT NOT NULL DEFAULT "09:00",
        end_time TEXT NOT NULL DEFAULT "17:00",
        billing_mode TEXT NOT NULL DEFAULT "daily",
        start_at TEXT,
        end_at TEXT,
        pickup_location TEXT NOT NULL,
        people INTEGER NOT NULL,
        insurance INTEGER NOT NULL DEFAULT 0,
        insurance_plan TEXT NOT NULL DEFAULT "standard",
        child_seats INTEGER NOT NULL DEFAULT 0,
        additional_services TEXT NOT NULL DEFAULT "[]",
        payment_method TEXT NOT NULL DEFAULT "onsite",
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL,
        arrival TEXT NOT NULL DEFAULT "",
        notes TEXT NOT NULL DEFAULT "",
        language TEXT NOT NULL DEFAULT "ja",
        base_price INTEGER NOT NULL,
        total INTEGER NOT NULL,
        change_request TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        cancelled_at TEXT
    )');
    $columns = array_column($pdo->query('PRAGMA table_info(bookings)')->fetchAll(), 'name');
    $insurancePlanColumnAdded = !in_array('insurance_plan', $columns, true);
    $migrations = [
        'start_time' => 'ALTER TABLE bookings ADD COLUMN start_time TEXT NOT NULL DEFAULT "09:00"',
        'end_time' => 'ALTER TABLE bookings ADD COLUMN end_time TEXT NOT NULL DEFAULT "17:00"',
        'billing_mode' => 'ALTER TABLE bookings ADD COLUMN billing_mode TEXT NOT NULL DEFAULT "daily"',
        'start_at' => 'ALTER TABLE bookings ADD COLUMN start_at TEXT',
        'end_at' => 'ALTER TABLE bookings ADD COLUMN end_at TEXT',
        'insurance_plan' => 'ALTER TABLE bookings ADD COLUMN insurance_plan TEXT NOT NULL DEFAULT "standard"',
    ];
    foreach ($migrations as $column => $sql) if (!in_array($column, $columns, true)) $pdo->exec($sql);
    if ($insurancePlanColumnAdded) $pdo->exec('UPDATE bookings SET insurance_plan = CASE WHEN insurance = 1 THEN "standard" ELSE "basic" END');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_booking_dates ON bookings(car_class, start_date, end_date, status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_booking_times ON bookings(car_class, start_at, end_at, status)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS vehicles (
        id TEXT PRIMARY KEY,
        label TEXT NOT NULL,
        model TEXT NOT NULL DEFAULT "",
        image_path TEXT NOT NULL DEFAULT "",
        daily_price INTEGER NOT NULL,
        hourly_price INTEGER NOT NULL,
        inventory INTEGER NOT NULL DEFAULT 1,
        active INTEGER NOT NULL DEFAULT 1,
        display_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $vehicleColumns = array_column($pdo->query('PRAGMA table_info(vehicles)')->fetchAll(), 'name');
    $vehicleImageColumnAdded = !in_array('image_path', $vehicleColumns, true);
    if ($vehicleImageColumnAdded) $pdo->exec('ALTER TABLE vehicles ADD COLUMN image_path TEXT NOT NULL DEFAULT ""');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vehicles_active_order ON vehicles(active, display_order, created_at)');
    $defaultVehicleImages = [
        'kei' => '/vehicle-kei.webp',
        'compact' => '/vehicle-compact.webp',
        'van' => '/vehicle-minivan.webp',
    ];
    if ((int)$pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn() === 0) {
        $seed = $pdo->prepare('INSERT INTO vehicles(id, label, model, image_path, daily_price, hourly_price, inventory, active, display_order, created_at, updated_at) VALUES(:id, :label, :model, :image, :daily, :hourly, :inventory, 1, :sort, :created, :updated)');
        $now = date('Y-m-d H:i:s');
        $sort = 10;
        foreach (($config['cars'] ?? []) as $id => $car) {
            $seed->execute([
                ':id' => (string)$id,
                ':label' => (string)($car['label'] ?? $id),
                ':model' => (string)($car['model'] ?? ''),
                ':image' => $defaultVehicleImages[(string)$id] ?? '',
                ':daily' => max(0, (int)($car['price'] ?? 0)),
                ':hourly' => max(0, (int)($car['hourly_price'] ?? 0)),
                ':inventory' => max(0, (int)($car['inventory'] ?? 0)),
                ':sort' => $sort,
                ':created' => $now,
                ':updated' => $now,
            ]);
            $sort += 10;
        }
    }
    if ($vehicleImageColumnAdded) {
        $setDefaultImage = $pdo->prepare('UPDATE vehicles SET image_path = :image WHERE id = :id AND image_path = ""');
        foreach ($defaultVehicleImages as $id => $image) $setDefaultImage->execute([':id' => $id, ':image' => $image]);
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS availability_blocks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        car_class TEXT NOT NULL,
        start_at TEXT NOT NULL,
        end_at TEXT NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 1,
        reason TEXT NOT NULL DEFAULT "",
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_availability_blocks ON availability_blocks(car_class, start_at, end_at)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS request_log (ip_hash TEXT NOT NULL, created_at INTEGER NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_request_log_time ON request_log(created_at)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS mail_delivery_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        booking_code TEXT NOT NULL DEFAULT "",
        recipient_type TEXT NOT NULL,
        recipient TEXT NOT NULL,
        subject TEXT NOT NULL,
        accepted INTEGER NOT NULL DEFAULT 0,
        transport TEXT NOT NULL DEFAULT "php-mail",
        error_message TEXT NOT NULL DEFAULT "",
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mail_delivery_log_created ON mail_delivery_log(created_at)');
    $pdo->exec('PRAGMA optimize');
    return $pdo;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body_json(): array
{
    $raw = file_get_contents('php://input');
    $value = json_decode($raw ?: '{}', true);
    if (!is_array($value)) json_response(['ok' => false, 'message' => '送信内容を読み取れません。'], 400);
    return $value;
}

function booking_rates(): array
{
    global $config;
    $serviceDefaults = ['stay' => 6600, 'hike' => 8800, 'activity' => 6600, 'boat' => 8800];
    $configuredServices = is_array($config['service_prices'] ?? null) ? $config['service_prices'] : [];
    $services = [];
    foreach ($serviceDefaults as $id => $price) $services[$id] = max(0, (int)($configuredServices[$id] ?? $price));
    return [
        'insurancePerDay' => max(0, (int)($config['insurance_per_day'] ?? 1100)),
        'insuranceWidePerDay' => max(0, (int)($config['insurance_wide_per_day'] ?? 2200)),
        'childSeatPerDay' => max(0, (int)($config['child_seat_per_day'] ?? 550)),
        'services' => $services,
    ];
}

function clean_text($value, int $max = 500): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, $max, 'UTF-8');
}

function normalize_phone($value): string
{
    $phone = clean_text($value, 40);
    if (function_exists('mb_convert_kana')) $phone = mb_convert_kana($phone, 'n', 'UTF-8');
    $phone = str_replace(['‐', '‑', '‒', '–', '—', '―', 'ー', '−'], '-', $phone);
    return preg_replace('/[\s\x{3000}]+/u', ' ', trim($phone)) ?? '';
}

function valid_phone(string $phone): bool
{
    if (!preg_match('/^[+()0-9\s-]+$/', $phone)) return false;
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    return strlen($digits) >= 7 && strlen($digits) <= 15;
}

function valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function booking_days(string $start, string $end): int
{
    $from = new DateTimeImmutable($start);
    $to = new DateTimeImmutable($end);
    return max(1, (int)$from->diff($to)->days);
}

function booking_hours(string $startAt, string $endAt): int
{
    $seconds = (new DateTimeImmutable($endAt))->getTimestamp() - (new DateTimeImmutable($startAt))->getTimestamp();
    return max(1, (int)ceil($seconds / 3600));
}

function valid_time(string $value): bool
{
    return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
}

function active_booking_count(PDO $pdo, string $carClass, string $startAt, string $endAt, ?int $excludeId = null): int
{
    $sql = 'SELECT COUNT(*) FROM bookings WHERE car_class = :car AND status != "cancelled" AND NOT (COALESCE(end_at, end_date || " 23:59") <= :start OR COALESCE(start_at, start_date || " 00:00") >= :end)';
    if ($excludeId !== null) $sql .= ' AND id != :exclude_id';
    $stmt = $pdo->prepare($sql);
    $params = [':car' => $carClass, ':start' => $startAt, ':end' => $endAt];
    if ($excludeId !== null) $params[':exclude_id'] = $excludeId;
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function blocked_vehicle_count(PDO $pdo, string $carClass, string $startAt, string $endAt): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM availability_blocks WHERE car_class = :car AND NOT (end_at <= :start OR start_at >= :end)');
    $stmt->execute([':car' => $carClass, ':start' => $startAt, ':end' => $endAt]);
    return (int)$stmt->fetchColumn();
}

function vehicle_catalog(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM vehicles';
    if ($activeOnly) $sql .= ' WHERE active = 1 AND inventory > 0';
    $sql .= ' ORDER BY display_order ASC, created_at ASC, id ASC';
    return $pdo->query($sql)->fetchAll();
}

function find_vehicle(PDO $pdo, string $id, bool $activeOnly = true): ?array
{
    $sql = 'SELECT * FROM vehicles WHERE id = :id';
    if ($activeOnly) $sql .= ' AND active = 1 AND inventory > 0';
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $vehicle = $stmt->fetch();
    return $vehicle ?: null;
}

function public_vehicle(array $vehicle): array
{
    return [
        'id' => $vehicle['id'], 'label' => $vehicle['label'], 'model' => $vehicle['model'],
        'imageUrl' => $vehicle['image_path'] ?? '',
        'price' => (int)$vehicle['daily_price'], 'hourlyPrice' => (int)$vehicle['hourly_price'],
        'inventory' => (int)$vehicle['inventory'], 'available' => (int)$vehicle['inventory'],
    ];
}

function public_booking(array $row, PDO $pdo): array
{
    $car = find_vehicle($pdo, (string)$row['car_class'], false) ?? ['label' => $row['car_class']];
    return [
        'code' => $row['code'], 'status' => $row['status'], 'carClass' => $row['car_class'], 'carLabel' => $car['label'],
        'startDate' => $row['start_date'], 'endDate' => $row['end_date'], 'startTime' => $row['start_time'] ?? '09:00',
        'endTime' => $row['end_time'] ?? '17:00', 'billingMode' => $row['billing_mode'] ?? 'daily', 'pickupLocation' => $row['pickup_location'],
        'people' => (int)$row['people'], 'total' => (int)$row['total'], 'paymentMethod' => $row['payment_method'],
        'insurance' => (bool)$row['insurance'],
        'insurancePlan' => $row['insurance_plan'] ?? (!empty($row['insurance']) ? 'standard' : 'basic'),
        'childSeats' => (int)$row['child_seats'],
        'additionalServices' => json_decode($row['additional_services'] ?: '[]', true) ?: [],
    ];
}

function require_booking(PDO $pdo, string $code, string $token): array
{
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => strtoupper($code)]);
    $booking = $stmt->fetch();
    if (!$booking || !hash_equals($booking['access_token_hash'], hash('sha256', $token))) {
        json_response(['ok' => false, 'message' => '予約番号または管理キーが正しくありません。'], 404);
    }
    return $booking;
}

function client_rate_limit(PDO $pdo): void
{
    $now = time();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $hash = hash('sha256', $ip . '|kmcube-booking');
    $pdo->exec('DELETE FROM request_log WHERE created_at < ' . ($now - 3600));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM request_log WHERE ip_hash = :hash');
    $stmt->execute([':hash' => $hash]);
    if ((int)$stmt->fetchColumn() >= 12) json_response(['ok' => false, 'message' => '送信回数が上限に達しました。時間をおいてお試しください。'], 429);
    $stmt = $pdo->prepare('INSERT INTO request_log(ip_hash, created_at) VALUES(:hash, :time)');
    $stmt->execute([':hash' => $hash, ':time' => $now]);
}

function mail_config(array $config): array
{
    $adminEmail = (string)($config['admin_notification_email'] ?? 'm-morita@nn-cube.com');
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strpos($adminEmail, 'CHANGE_ME') !== false) $adminEmail = 'm-morita@nn-cube.com';
    $shopEmail = (string)($config['shop_email'] ?? $adminEmail);
    if (!filter_var($shopEmail, FILTER_VALIDATE_EMAIL) || strpos($shopEmail, 'CHANGE_ME') !== false) $shopEmail = $adminEmail;
    $mailFrom = (string)($config['mail_from'] ?? 'no-reply@k-mcube.com');
    if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) $mailFrom = 'no-reply@k-mcube.com';
    $envelopeFrom = (string)($config['mail_envelope_from'] ?? $mailFrom);
    if (!filter_var($envelopeFrom, FILTER_VALIDATE_EMAIL)) $envelopeFrom = $mailFrom;
    $fromName = preg_replace('/[\r\n]+/', ' ', clean_text($config['mail_from_name'] ?? 'KMCUBE Yakushima', 80)) ?? 'KMCUBE Yakushima';
    return ['admin' => $adminEmail, 'shop' => $shopEmail, 'from' => $mailFrom, 'envelope' => $envelopeFrom, 'name' => $fromName];
}

function log_mail_delivery(string $bookingCode, string $recipientType, string $recipient, string $subject, bool $accepted, string $errorMessage = ''): void
{
    try {
        $stmt = db()->prepare('INSERT INTO mail_delivery_log(booking_code, recipient_type, recipient, subject, accepted, transport, error_message, created_at) VALUES(:code,:type,:recipient,:subject,:accepted,"php-mail",:error,:created)');
        $stmt->execute([
            ':code' => clean_text($bookingCode, 40), ':type' => clean_text($recipientType, 20),
            ':recipient' => clean_text($recipient, 180), ':subject' => clean_text($subject, 240),
            ':accepted' => $accepted ? 1 : 0, ':error' => clean_text($errorMessage, 500), ':created' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $logError) {
        error_log('[KMCUBE mail log] ' . $logError->getMessage());
    }
}

function send_text_mail(string $to, string $subject, string $body, array $config, string $replyTo, string $recipientType, string $bookingCode = ''): bool
{
    $settings = mail_config($config);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        log_mail_delivery($bookingCode, $recipientType, $to, $subject, false, '宛先または返信先メールアドレスが不正です。');
        return false;
    }
    if (function_exists('mb_language')) @mb_language('Japanese');
    if (function_exists('mb_internal_encoding')) @mb_internal_encoding('UTF-8');
    $encodedName = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($settings['name'], 'UTF-8') : $settings['name'];
    $headers = [
        'From: ' . $encodedName . ' <' . $settings['from'] . '>',
        'Sender: ' . $settings['envelope'],
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: KMCUBE Booking System',
    ];
    $headerText = implode("\r\n", $headers);
    $envelopeOption = '-f' . $settings['envelope'];
    $lastErrorBefore = error_get_last();
    if (function_exists('mb_send_mail')) {
        $accepted = @mb_send_mail($to, $subject, $body, $headerText, $envelopeOption);
    } else {
        $encodedSubject = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8') : $subject;
        $accepted = @mail($to, $encodedSubject, $body, $headerText, $envelopeOption);
    }
    $lastErrorAfter = error_get_last();
    $errorMessage = '';
    if (!$accepted && $lastErrorAfter !== $lastErrorBefore && is_array($lastErrorAfter)) $errorMessage = (string)($lastErrorAfter['message'] ?? 'PHPのメール送信処理が失敗しました。');
    if (!$accepted && $errorMessage === '') $errorMessage = 'PHPのメール送信処理が受け付けられませんでした。';
    log_mail_delivery($bookingCode, $recipientType, $to, $subject, (bool)$accepted, $errorMessage);
    return (bool)$accepted;
}

function send_test_mail(string $to, array $config): bool
{
    $settings = mail_config($config);
    $subject = '【KMCUBE】メール送信テスト ' . date('Y-m-d H:i:s');
    $body = "KMCUBE予約管理画面からのメール送信テストです。\n\n受信日時: " . date(DATE_ATOM) . "\n差出人: {$settings['from']}\n配送元: {$settings['envelope']}\n\nこのメールを受信できれば、PHPからメールサーバーまでの送信経路は動作しています。";
    return send_text_mail($to, $subject, $body, $config, $settings['shop'], 'test');
}

function send_booking_mail(array $booking, string $accessToken, array $config, string $kind = 'created'): array
{
    $labels = ['created' => '予約受付完了', 'confirmed' => '予約確定', 'cancelled' => '予約キャンセル受付', 'change' => '予約変更リクエスト受付'];
    $eventLabel = $labels[$kind] ?? '予約のお知らせ';
    $subject = '【KMCUBE】' . ($kind === 'created' ? 'ご予約ありがとうございます ' : $eventLabel . ' ') . $booking['code'];
    $manageUrl = rtrim($config['base_url'], '/') . '/?manage=' . rawurlencode($booking['code']) . '#booking';
    $adminUrl = rtrim($config['base_url'], '/') . '/api/admin/';
    $vehicle = find_vehicle(db(), (string)$booking['car_class'], false);
    $vehicleLabel = (string)($vehicle['label'] ?? $booking['car_class']);
    $serviceLabels = ['stay' => '民泊', 'hike' => '登山案内', 'activity' => 'アクティビティ', 'boat' => '漁船遊覧'];
    $selectedServices = json_decode($booking['additional_services'] ?: '[]', true) ?: [];
    $selectedServiceLabels = [];
    foreach ($selectedServices as $id) $selectedServiceLabels[] = $serviceLabels[$id] ?? (string)$id;
    $servicesText = $selectedServiceLabels ? implode('、', $selectedServiceLabels) : 'なし';
    $billingLabel = ($booking['billing_mode'] ?? 'daily') === 'hourly' ? '時間制' : '日数制';
    $insurancePlan = (string)($booking['insurance_plan'] ?? (!empty($booking['insurance']) ? 'standard' : 'basic'));
    $insuranceLabels = ['basic' => '基本補償', 'standard' => '安心保険プラン', 'wide' => '安心保険プラン・ワイド'];
    $coverageText = $insuranceLabels[$insurancePlan] ?? $insuranceLabels['basic'];
    $childSeatText = (int)$booking['child_seats'] > 0 ? 'チャイルドシート ' . (int)$booking['child_seats'] . '台' : 'チャイルドシートなし';
    $details = "予約番号: {$booking['code']}\n";
    $details .= "利用日時: {$booking['start_date']} {$booking['start_time']} ～ {$booking['end_date']} {$booking['end_time']}\n";
    $details .= "料金体系: {$billingLabel}\n車両: {$vehicleLabel}\n受取・返却場所: {$booking['pickup_location']}\n利用人数: {$booking['people']}名\n";
    $details .= "補償・オプション: {$coverageText}、{$childSeatText}\n";
    $details .= "追加サービス: {$servicesText}\n支払方法: 現地払い\n概算金額（税込）: ¥" . number_format((int)$booking['total']) . "\n";
    $customerInfo = "お名前: {$booking['name']}\nメール: {$booking['email']}\n電話: {$booking['phone']}\n";
    $customerInfo .= "到着便・船便: " . ($booking['arrival'] ?: '未入力') . "\nご要望: " . ($booking['notes'] ?: 'なし') . "\n";

    $changeDetails = '';
    if ($kind === 'change') {
        $change = json_decode($booking['change_request'] ?: 'null', true);
        if (is_array($change)) {
            $requestedVehicle = find_vehicle(db(), (string)($change['carClass'] ?? ''), false);
            $requestedVehicleLabel = (string)($requestedVehicle['label'] ?? ($change['carClass'] ?? ''));
            $changeDetails = "\n変更希望内容\n希望日時: " . ($change['startDate'] ?? '') . ' ' . ($change['startTime'] ?? '') . ' ～ ' . ($change['endDate'] ?? '') . ' ' . ($change['endTime'] ?? '') . "\n";
            $changeDetails .= "希望車両: {$requestedVehicleLabel}\nメッセージ: " . (($change['message'] ?? '') ?: 'なし') . "\n";
        }
    }

    $customerMessage = "{$booking['name']} 様\n\nKMCUBE Yakushimaへのご予約ありがとうございます。\n以下の内容で{$eventLabel}いたしました。\n\n{$details}\n{$customerInfo}{$changeDetails}";
    if ($accessToken !== '') {
        $customerMessage .= "\n予約内容の照会・変更に必要な情報\n管理キー: {$accessToken}\n予約照会ページ: {$manageUrl}\n";
    }
    if ($kind === 'confirmed') $customerMessage .= "\n予約が確定しました。当日は運転免許証をご持参のうえ、お気をつけてお越しください。";
    elseif ($kind === 'cancelled') $customerMessage .= "\nキャンセルを受け付けました。キャンセル規定に基づく料金が発生する場合は、担当者からご連絡します。";
    else $customerMessage .= "\n※この時点では予約確定ではありません。担当者が内容を確認し、改めて予約確定をご連絡します。";
    $customerMessage .= "\n※変更やキャンセルは予約照会ページからお手続きください。\n\nKMCUBE Yakushima";

    $adminMessage = "KMCUBE予約管理者様\n\n{$eventLabel}がありました。\n管理画面で内容をご確認ください。\n\n{$details}\n";
    $adminMessage .= $customerInfo;
    $adminMessage .= $changeDetails;
    $adminMessage .= "\n管理画面: {$adminUrl}";

    $settings = mail_config($config);
    $sentUser = send_text_mail((string)$booking['email'], $subject, $customerMessage, $config, $settings['shop'], 'customer', (string)$booking['code']);
    $adminSubjectLabel = $kind === 'created' ? '新規予約通知' : $eventLabel;
    $sentAdmin = send_text_mail($settings['admin'], '【KMCUBE管理】' . $adminSubjectLabel . ' ' . $booking['code'], $adminMessage, $config, (string)$booking['email'], 'admin', (string)$booking['code']);
    return ['customer' => $sentUser, 'admin' => $sentAdmin];
}
