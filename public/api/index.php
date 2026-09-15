<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');

try {
    $pdo = db();
    $action = clean_text($_GET['action'] ?? 'health', 80);
    // Older cached builds appended the cache-buster as "reserve?_=...".
    // Normalize that value so bookings, cancellations and changes still work
    // while visitors' browsers receive the corrected JavaScript bundle.
    $questionMark = strpos($action, '?');
    if ($questionMark !== false) $action = substr($action, 0, $questionMark);

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') json_response(['ok' => true]);

    if ($action === 'health') {
        json_response(['ok' => true, 'service' => 'KMCUBE Booking API', 'time' => date(DATE_ATOM)]);
    }

    if ($action === 'catalog') {
        json_response(['ok' => true, 'cars' => array_map('public_vehicle', vehicle_catalog($pdo)), 'rates' => booking_rates()]);
    }

    if ($action === 'availability') {
        $start = clean_text($_GET['start'] ?? '', 10);
        $end = clean_text($_GET['end'] ?? '', 10);
        $startTime = clean_text($_GET['startTime'] ?? '09:00', 5);
        $endTime = clean_text($_GET['endTime'] ?? '17:00', 5);
        $billingMode = ($_GET['billingMode'] ?? 'daily') === 'hourly' ? 'hourly' : 'daily';
        if (!valid_date($start) || !valid_date($end) || !valid_time($startTime) || !valid_time($endTime)) json_response(['ok' => false, 'message' => '利用日時をご確認ください。'], 422);
        $startAt = $start . ' ' . $startTime . ':00';
        $endAt = $end . ' ' . $endTime . ':00';
        $hours = booking_hours($startAt, $endAt);
        if ($endAt <= $startAt || ($billingMode === 'hourly' && $hours > 24)) json_response(['ok' => false, 'message' => '返却日時または料金体系をご確認ください。'], 422);
        $cars = [];
        foreach (vehicle_catalog($pdo) as $car) {
            $id = (string)$car['id'];
            $booked = active_booking_count($pdo, $id, $startAt, $endAt);
            $blocked = blocked_vehicle_count($pdo, $id, $startAt, $endAt);
            $cars[] = array_merge(public_vehicle($car), [
                'booked' => $booked,
                'blocked' => $blocked,
                'available' => max(0, (int)$car['inventory'] - $booked - $blocked),
            ]);
        }
        json_response(['ok' => true, 'cars' => $cars]);
    }

    if ($action === 'reservation') {
        $booking = require_booking($pdo, clean_text($_GET['code'] ?? '', 32), clean_text($_GET['token'] ?? '', 128));
        json_response(['ok' => true, 'booking' => public_booking($booking, $pdo)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'message' => 'この操作は利用できません。'], 405);
    $body = body_json();

    if ($action === 'reserve') {
        client_rate_limit($pdo);
        if (!empty($body['website'])) json_response(['ok' => true]);
        $start = clean_text($body['startDate'] ?? '', 10);
        $end = clean_text($body['endDate'] ?? '', 10);
        $startTime = clean_text($body['startTime'] ?? '', 5);
        $endTime = clean_text($body['endTime'] ?? '', 5);
        $billingMode = ($body['billingMode'] ?? 'daily') === 'hourly' ? 'hourly' : 'daily';
        $carClass = clean_text($body['carClass'] ?? '', 20);
        $name = clean_text($body['name'] ?? '', 80);
        $email = clean_text($body['email'] ?? '', 180);
        $phone = clean_text($body['phone'] ?? '', 40);
        if (!valid_date($start) || !valid_date($end) || !valid_time($startTime) || !valid_time($endTime) || $start < date('Y-m-d')) json_response(['ok' => false, 'message' => '利用日時をご確認ください。'], 422);
        $startAt = $start . ' ' . $startTime . ':00';
        $endAt = $end . ' ' . $endTime . ':00';
        $hours = booking_hours($startAt, $endAt);
        if ($endAt <= $startAt || ($billingMode === 'hourly' && $hours > 24)) json_response(['ok' => false, 'message' => '返却日時は出発日時より後にしてください。時間制は24時間以内です。'], 422);
        $car = find_vehicle($pdo, $carClass);
        if (!$car) json_response(['ok' => false, 'message' => '現在受付中の車両からお選びください。'], 422);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') json_response(['ok' => false, 'message' => 'お名前、メールアドレス、電話番号をご確認ください。'], 422);

        $people = max(1, min(8, (int)($body['people'] ?? 1)));
        $allowedInsurancePlans = ['basic', 'standard', 'wide'];
        $insurancePlan = (string)($body['insurancePlan'] ?? (!empty($body['insurance']) ? 'standard' : 'basic'));
        if (!in_array($insurancePlan, $allowedInsurancePlans, true)) $insurancePlan = 'basic';
        $insurance = $insurancePlan === 'basic' ? 0 : 1;
        $childSeats = max(0, min(3, (int)($body['childSeats'] ?? 0)));
        $allowedExtras = ['stay', 'hike', 'activity', 'boat'];
        $extras = array_values(array_intersect($allowedExtras, is_array($body['additionalServices'] ?? null) ? $body['additionalServices'] : []));
        $billingUnits = $billingMode === 'hourly' ? $hours : max(1, (int)ceil($hours / 24));
        $optionUnits = $billingMode === 'hourly' ? 1 : $billingUnits;
        $rates = booking_rates();
        $basePrice = $billingMode === 'hourly' ? min((int)$car['daily_price'], (int)$car['hourly_price'] * $billingUnits) : (int)$car['daily_price'] * $billingUnits;
        $serviceTotal = 0;
        foreach ($extras as $extra) {
            $quantity = $people * ($extra === 'stay' ? max(1, (int)ceil($hours / 24)) : 1);
            $serviceTotal += (int)$rates['services'][$extra] * $quantity;
        }
        $insuranceRate = $insurancePlan === 'wide' ? (int)$rates['insuranceWidePerDay'] : ($insurancePlan === 'standard' ? (int)$rates['insurancePerDay'] : 0);
        $total = $basePrice + ($insuranceRate * $optionUnits) + ($childSeats * (int)$rates['childSeatPerDay'] * $optionUnits) + $serviceTotal;
        $token = bin2hex(random_bytes(18));
        $now = date('Y-m-d H:i:s');
        $code = 'KMC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        try {
            // Start the transaction through PDO so PDO::commit()/rollBack()
            // recognise it correctly on Sakura's PHP 7.4 PDO_SQLITE driver.
            $pdo->beginTransaction();
            $booked = active_booking_count($pdo, $carClass, $startAt, $endAt);
            $blocked = blocked_vehicle_count($pdo, $carClass, $startAt, $endAt);
            if ($booked + $blocked >= (int)$car['inventory']) {
                $pdo->rollBack();
                json_response(['ok' => false, 'message' => '申し訳ありません。選択中に満車となりました。別の車両をお選びください。'], 409);
            }
            $stmt = $pdo->prepare('INSERT INTO bookings (
                code, access_token_hash, status, car_class, start_date, end_date, start_time, end_time, billing_mode, start_at, end_at, pickup_location, people, insurance, insurance_plan,
                child_seats, additional_services, payment_method, name, email, phone, arrival, notes, language,
                base_price, total, created_at, updated_at
            ) VALUES (
                :code, :token, "pending", :car, :start, :end, :start_time, :end_time, :billing_mode, :start_at, :end_at, :pickup, :people, :insurance, :insurance_plan,
                :seats, :extras, "onsite", :name, :email, :phone, :arrival, :notes, :language,
                :base_price, :total, :created, :updated
            )');
            $stmt->execute([
                ':code' => $code, ':token' => hash('sha256', $token), ':car' => $carClass, ':start' => $start, ':end' => $end,
                ':start_time' => $startTime, ':end_time' => $endTime, ':billing_mode' => $billingMode, ':start_at' => $startAt, ':end_at' => $endAt,
                ':pickup' => clean_text($body['pickupLocation'] ?? '', 80), ':people' => $people, ':insurance' => $insurance, ':insurance_plan' => $insurancePlan,
                ':seats' => $childSeats, ':extras' => json_encode($extras, JSON_UNESCAPED_UNICODE), ':name' => $name, ':email' => $email,
                ':phone' => $phone, ':arrival' => clean_text($body['arrival'] ?? '', 120), ':notes' => clean_text($body['notes'] ?? '', 1000),
                ':language' => ($body['language'] ?? 'ja') === 'en' ? 'en' : 'ja', ':base_price' => $basePrice, ':total' => $total,
                ':created' => $now, ':updated' => $now,
            ]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();
        $mailStatus = ['customer' => false, 'admin' => false];
        try {
            $mailStatus = send_booking_mail($booking, $token, $config, 'created');
        } catch (Throwable $mailError) {
            // The booking is already safely stored. A mail transport problem
            // must not turn the completed reservation into a server error.
            error_log('[KMCUBE booking mail] ' . $mailError->getMessage());
        }
        json_response(['ok' => true, 'booking' => public_booking($booking, $pdo), 'accessToken' => $token, 'mailSent' => $mailStatus['customer'], 'adminMailSent' => $mailStatus['admin']], 201);
    }

    if ($action === 'cancel') {
        client_rate_limit($pdo);
        $token = clean_text($body['token'] ?? '', 128);
        $booking = require_booking($pdo, clean_text($body['code'] ?? '', 32), $token);
        if ($booking['status'] !== 'cancelled') {
            $stmt = $pdo->prepare('UPDATE bookings SET status = "cancelled", cancelled_at = :now, updated_at = :now WHERE id = :id');
            $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $booking['id']]);
            $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
            $stmt->execute([':id' => $booking['id']]);
            $booking = $stmt->fetch();
            send_booking_mail($booking, '', $config, 'cancelled');
        }
        json_response(['ok' => true, 'booking' => public_booking($booking, $pdo)]);
    }

    if ($action === 'change') {
        client_rate_limit($pdo);
        $token = clean_text($body['token'] ?? '', 128);
        $booking = require_booking($pdo, clean_text($body['code'] ?? '', 32), $token);
        if ($booking['status'] === 'cancelled') json_response(['ok' => false, 'message' => 'キャンセル済みの予約は変更できません。'], 409);
        $requestedStart = clean_text($body['requestedStart'] ?? '', 10);
        $requestedEnd = clean_text($body['requestedEnd'] ?? '', 10);
        $requestedStartTime = clean_text($body['requestedStartTime'] ?? '', 5);
        $requestedEndTime = clean_text($body['requestedEndTime'] ?? '', 5);
        $requestedBillingMode = ($body['requestedBillingMode'] ?? 'daily') === 'hourly' ? 'hourly' : 'daily';
        $requestedCar = clean_text($body['requestedCar'] ?? '', 20);
        $requestedStartAt = $requestedStart . ' ' . $requestedStartTime . ':00';
        $requestedEndAt = $requestedEnd . ' ' . $requestedEndTime . ':00';
        if (!valid_date($requestedStart) || !valid_date($requestedEnd) || !valid_time($requestedStartTime) || !valid_time($requestedEndTime) || $requestedEndAt <= $requestedStartAt || ($requestedBillingMode === 'hourly' && booking_hours($requestedStartAt, $requestedEndAt) > 24) || !find_vehicle($pdo, $requestedCar)) {
            json_response(['ok' => false, 'message' => '変更希望の日時・車両をご確認ください。'], 422);
        }
        $request = ['startDate' => $requestedStart, 'endDate' => $requestedEnd, 'startTime' => $requestedStartTime, 'endTime' => $requestedEndTime, 'billingMode' => $requestedBillingMode, 'carClass' => $requestedCar, 'message' => clean_text($body['message'] ?? '', 600), 'requestedAt' => date(DATE_ATOM)];
        $stmt = $pdo->prepare('UPDATE bookings SET status = "change_requested", change_request = :request, updated_at = :now WHERE id = :id');
        $stmt->execute([':request' => json_encode($request, JSON_UNESCAPED_UNICODE), ':now' => date('Y-m-d H:i:s'), ':id' => $booking['id']]);
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id');
        $stmt->execute([':id' => $booking['id']]);
        $booking = $stmt->fetch();
        send_booking_mail($booking, '', $config, 'change');
        json_response(['ok' => true, 'booking' => public_booking($booking, $pdo)]);
    }

    json_response(['ok' => false, 'message' => '操作が見つかりません。'], 404);
} catch (Throwable $error) {
    error_log('[KMCUBE booking] ' . $error->getMessage());
    json_response(['ok' => false, 'message' => '予約システムでエラーが発生しました。時間をおいてお試しください。'], 500);
}
