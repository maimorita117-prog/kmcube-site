<?php
declare(strict_types=1);

$configFile = is_file(__DIR__ . '/config.local.php') ? __DIR__ . '/config.local.php' : __DIR__ . '/config.sample.php';
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tokyo');

function db(): PDO
{
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
        pickup_location TEXT NOT NULL,
        people INTEGER NOT NULL,
        insurance INTEGER NOT NULL DEFAULT 0,
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
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_booking_dates ON bookings(car_class, start_date, end_date, status)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS request_log (ip_hash TEXT NOT NULL, created_at INTEGER NOT NULL)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_request_log_time ON request_log(created_at)');
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

function clean_text($value, int $max = 500): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, $max, 'UTF-8');
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

function active_booking_count(PDO $pdo, string $carClass, string $start, string $end, ?int $excludeId = null): int
{
    $sql = 'SELECT COUNT(*) FROM bookings WHERE car_class = :car AND status != "cancelled" AND NOT (end_date < :start OR start_date > :end)';
    if ($excludeId !== null) $sql .= ' AND id != :exclude_id';
    $stmt = $pdo->prepare($sql);
    $params = [':car' => $carClass, ':start' => $start, ':end' => $end];
    if ($excludeId !== null) $params[':exclude_id'] = $excludeId;
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function public_booking(array $row, array $config): array
{
    $car = $config['cars'][$row['car_class']] ?? ['label' => $row['car_class']];
    return [
        'code' => $row['code'], 'status' => $row['status'], 'carClass' => $row['car_class'], 'carLabel' => $car['label'],
        'startDate' => $row['start_date'], 'endDate' => $row['end_date'], 'pickupLocation' => $row['pickup_location'],
        'people' => (int)$row['people'], 'total' => (int)$row['total'], 'paymentMethod' => $row['payment_method'],
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

function send_booking_mail(array $booking, string $accessToken, array $config, string $kind = 'created'): bool
{
    $labels = ['created' => '予約リクエスト受付', 'cancelled' => '予約キャンセル受付', 'change' => '予約変更リクエスト受付'];
    $subject = '【KMCUBE】' . ($labels[$kind] ?? '予約のお知らせ') . ' ' . $booking['code'];
    $manageUrl = rtrim($config['base_url'], '/') . '/?manage=' . rawurlencode($booking['code']) . '#booking';
    $message = $config['shop_name'] . "\n\n" . ($labels[$kind] ?? '') . "\n予約番号: {$booking['code']}\n";
    $message .= "利用日: {$booking['start_date']} ～ {$booking['end_date']}\n車両: {$booking['car_class']}\n概算金額: ¥" . number_format((int)$booking['total']) . "\n";
    if ($accessToken !== '') $message .= "管理キー: {$accessToken}\n予約照会: {$manageUrl}\n";
    $message .= "\nこのメールは予約の受付をお知らせするものです。担当者からの予約確定連絡をお待ちください。";
    $headers = ['From: ' . $config['mail_from'], 'Content-Type: text/plain; charset=UTF-8'];
    $encoded = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8') : $subject;
    $sentUser = @mail($booking['email'], $encoded, $message, implode("\r\n", $headers));
    $shopEmail = (string)($config['shop_email'] ?? '');
    if ($shopEmail !== '' && strpos($shopEmail, 'CHANGE_ME') === false) {
        @mail($shopEmail, $encoded, $message . "\n\nお客様: {$booking['name']}\n電話: {$booking['phone']}\n", implode("\r\n", $headers));
    }
    return $sentUser;
}
