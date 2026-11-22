
<?php

date_default_timezone_set('Asia/Tashkent');

/**
 * CONFIG
 */
$botConfig = require __DIR__ . '/config.php';

if (!is_array($botConfig) || empty($botConfig['bot_token'])) {
    die("Xatolik: config.php ichida bot_token topilmadi.\n");
}

$GLOBALS['BOT_CONFIG'] = $botConfig;


/**
 * TELEGRAM API
 */
function telegram($method, $data = [])
{
    $token = $GLOBALS['BOT_CONFIG']['bot_token'] ?? null;

    if (!$token) {
        throw new Exception("Bot token topilmadi.");
    }

    $url = "https://api.telegram.org/bot{$token}/{$method}";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
            | CURL_SSLVERSION_MAX_TLSv1_2,
        CURLOPT_CAINFO => 'C:/php/certs/cacert.pem',
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Telegram xatosi: " . $error);
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!is_array($result)) {
        throw new Exception("Telegram serveridan noto'g'ri javob.");
    }

    return $result;
}


/**
 * XABAR YUBORISH
 */
function sendMessage(
    $chatId,
    $text,
    $keyboard = [],
    $removeKeyboard = false,
    $parseMode = null
) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
    ];

    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }

    if ($removeKeyboard) {
        $data['reply_markup'] = json_encode([
            'remove_keyboard' => true
        ]);
    } elseif (!empty($keyboard)) {
        $data['reply_markup'] = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }

    return telegram('sendMessage', $data);
}


/**
 * INLINE XABAR
 */
function sendInlineMessage($chatId, $text, $keyboard)
{
    return telegram('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => json_encode([
            'inline_keyboard' => $keyboard
        ])
    ]);
}


/**
 * CALLBACK JAVOBI
 */
function answerCallbackQuery($callbackId, $text = '')
{
    return telegram('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text
    ]);
}


/**
 * ASOSIY MENYU
 */
function mainMenu($chatId)
{
    $keyboard = [
        [
            ['text' => '➕ Do‘kon qo‘shish'],
            ['text' => '🏪 Do‘konlar ro‘yxati']
        ],
        [
            ['text' => '📋 Bugungi reja'],
            ['text' => '📊 Bugungi tashriflar']
        ],
        [
            ['text' => '🧭 Marshrutni tuzish']
        ],
        [
            ['text' => '📅 Keyingi tashriflar'],
            ['text' => '📈 Haftalik hisobot']
        ],
        [
            ['text' => 'ℹ️ Yordam']
        ]
    ];

    sendMessage(
        $chatId,
        "🏪 SAVDO AGENT BOT\n\nKerakli bo‘limni tanlang:",
        $keyboard
    );
}


/**
 * GPS TUGMASI
 */
function locationKeyboard()
{
    return [
        [
            [
                'text' => '📍 GPS manzilni yuborish',
                'request_location' => true
            ]
        ],
        [
            ['text' => '❌ Bekor qilish']
        ]
    ];
}


/**
 * AGENTNI RO'YXATDAN O'TKAZISH
 */
function registerAgent($pdo, $chatId, $fullName = null)
{
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO agents (chat_id, full_name)
        VALUES (?, ?)
    ");

    $stmt->execute([$chatId, $fullName]);

    $stmt = $pdo->prepare("
        SELECT id FROM agents WHERE chat_id = ?
    ");

    $stmt->execute([$chatId]);

    return $stmt->fetchColumn();
}


/**
 * AGENT ID
 */
function getAgentId($pdo, $chatId)
{
    $stmt = $pdo->prepare("
        SELECT id FROM agents WHERE chat_id = ?
    ");

    $stmt->execute([$chatId]);

    return $stmt->fetchColumn();
}


/**
 * AGENT HOLATI
 */
function setAgentState($pdo, $chatId, $action, $storeName = null)
{
    $stmt = $pdo->prepare("
        INSERT INTO agent_states
        (chat_id, action, store_name, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(chat_id)
        DO UPDATE SET
            action = excluded.action,
            store_name = excluded.store_name,
            updated_at = CURRENT_TIMESTAMP
    ");

    return $stmt->execute([
        $chatId,
        $action,
        $storeName
    ]);
}


function getAgentState($pdo, $chatId)
{
    $stmt = $pdo->prepare("
        SELECT * FROM agent_states WHERE chat_id = ?
    ");

    $stmt->execute([$chatId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}


function clearAgentState($pdo, $chatId)
{
    $stmt = $pdo->prepare("
        DELETE FROM agent_states WHERE chat_id = ?
    ");

    return $stmt->execute([$chatId]);
}


/**
 * TAKRORIY DO'KONLARNI TOPISH
 */
function findDuplicateStores($pdo, $agentId, $storeName)
{
    $stmt = $pdo->prepare("
        SELECT id, name, latitude, longitude
        FROM stores
        WHERE agent_id = ?
        AND LOWER(TRIM(name)) = LOWER(TRIM(?))
        ORDER BY id DESC
    ");

    $stmt->execute([$agentId, $storeName]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * DO'KONLAR RO'YXATI
 */
function getAgentStores($pdo, $agentId)
{
    $stmt = $pdo->prepare("
        SELECT id, name, latitude, longitude, schedule_weekday, created_at
        FROM stores
        WHERE agent_id = ?
        ORDER BY schedule_weekday, name
    ");

    $stmt->execute([$agentId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * HAFTA KUNLARI NOMLARI
 */
function getWeekdayName($weekday)
{
    $days = [
        1 => 'Dushanba',
        2 => 'Seshanba',
        3 => 'Chorshanba',
        4 => 'Payshanba',
        5 => 'Juma',
        6 => 'Shanba',
        7 => 'Yakshanba'
    ];

    return $days[(int) $weekday] ?? 'Noma’lum kun';
}

/**
 * AGENTNING HAFTA KUNLARI BO'YICHA DO'KONLAR SONI
 */
function getWeekdayStoreCounts($pdo, $agentId)
{
    $stmt = $pdo->prepare("
        SELECT schedule_weekday, COUNT(*) AS total
        FROM stores
        WHERE agent_id = ?
        AND schedule_weekday BETWEEN 1 AND 5
        GROUP BY schedule_weekday
    ");

    $stmt->execute([$agentId]);

    $counts = [
        1 => 0,
        2 => 0,
        3 => 0,
        4 => 0,
        5 => 0
    ];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['schedule_weekday']] = (int) $row['total'];
    }

    return $counts;
}

/**
 * TANLANGAN HAFTA KUNIDAGI DO'KONLAR
 */
function getStoresByWeekday($pdo, $agentId, $weekday)
{
    $weekday = (int) $weekday;

    if ($weekday < 1 || $weekday > 5) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT id, name, latitude, longitude, schedule_weekday, created_at
        FROM stores
        WHERE agent_id = ?
        AND schedule_weekday = ?
        ORDER BY name
    ");

    $stmt->execute([$agentId, $weekday]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * XARITA HAVOLASI
 */
function getMapUrl($latitude, $longitude)
{
    // Yandex Maps nuqta havolasi: pt parametri longitude,latitude tartibida.
    return "https://yandex.uz/maps/?pt="
        . rawurlencode($longitude . ',' . $latitude)
        . "&z=17&l=map";
}


/**
 * TASHRIFNI SAQLASH
 */
function saveTodayVisit($pdo, $storeId, $status = 'visited')
{
    if (!in_array($status, ['visited', 'not_visited'], true)) {
        throw new InvalidArgumentException("Noto'g'ri tashrif holati.");
    }

    $today = date('Y-m-d');
    $weekday = (int) date('N');

    $stmt = $pdo->prepare("
        SELECT id FROM visits
        WHERE store_id = ? AND visit_date = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$storeId, $today]);

    $visitId = $stmt->fetchColumn();

    if ($visitId) {
        $stmt = $pdo->prepare("
            UPDATE visits
            SET status = ?, weekday = ?
            WHERE id = ?
        ");

        $stmt->execute([$status, $weekday, $visitId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO visits
            (store_id, visit_date, weekday, status)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $storeId,
            $today,
            $weekday,
            $status
        ]);
    }

    return true;
}


/**
 * BUGUNGI / TANLANGAN KUN REJASI
 * Do'konlar tashrif tarixiga emas, qo'shilgan kuniga biriktiriladi.
 */
function getPlannedStores($pdo, $agentId, $weekday = null)
{
    if ($weekday === null) {
        $weekday = (int) date('N');
    }

    return getStoresByWeekday($pdo, $agentId, $weekday);
}


/**
 * BUGUNGI TASHRIFLAR
 */
function getTodayVisits($pdo, $agentId)
{
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.name,
            s.latitude,
            s.longitude,
            v.status,
            v.visit_date
        FROM visits v
        INNER JOIN stores s ON s.id = v.store_id
        WHERE s.agent_id = ?
        AND v.visit_date = ?
        ORDER BY s.name
    ");

    $stmt->execute([
        $agentId,
        date('Y-m-d')
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * HAFTALIK HISOBOT
 */
function buildWeeklyReport($pdo, $agentId)
{
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN v.status = 'visited' THEN 1 ELSE 0 END) AS visited,
            SUM(CASE WHEN v.status = 'not_visited' THEN 1 ELSE 0 END) AS not_visited
        FROM visits v
        INNER JOIN stores s ON s.id = v.store_id
        WHERE s.agent_id = ?
        AND v.visit_date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $agentId,
        $monday,
        $sunday
    ]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = (int) ($stats['total'] ?? 0);
    $visited = (int) ($stats['visited'] ?? 0);
    $notVisited = (int) ($stats['not_visited'] ?? 0);

    $percent = $total > 0
        ? round(($visited / $total) * 100, 1)
        : 0;

    return [
        'from' => $monday,
        'to' => $sunday,
        'total' => $total,
        'visited' => $visited,
        'not_visited' => $notVisited,
        'percent' => $percent
    ];
}


/**
 * HISOBOT MATNI
 */
function weeklyReportText($stats)
{
    return
        "📈 HAFTALIK SAVDO AGENT HISOBOTI\n\n"
        . "📅 Davr: "
        . date('d.m.Y', strtotime($stats['from']))
        . " – "
        . date('d.m.Y', strtotime($stats['to']))
        . "\n\n"
        . "📋 Jami tashriflar: " . $stats['total'] . "\n"
        . "✅ Borilgan: " . $stats['visited'] . "\n"
        . "❌ Borilmagan: " . $stats['not_visited'] . "\n\n"
        . "📊 Bajarilish: " . $stats['percent'] . "%";
}


/**
 * MASOFANI HISOBLASH (HAVERSINE)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1))
        * cos(deg2rad($lat2))
        * sin($dLon / 2)
        * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}


/**
 * ENG YAQIN DO'KONDAN BOSHLAB MARSHRUT TUZISH
 */
function optimizeRoute($stores, $startLat, $startLon)
{
    $remaining = array_values($stores);
    $ordered = [];

    $currentLat = $startLat;
    $currentLon = $startLon;

    while (!empty($remaining)) {
        $nearestIndex = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($remaining as $index => $store) {
            $distance = calculateDistance(
                $currentLat,
                $currentLon,
                (float) $store['latitude'],
                (float) $store['longitude']
            );

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestIndex = $index;
            }
        }

        $next = $remaining[$nearestIndex];

        $next['distance_km'] = round($nearestDistance, 2);

        $ordered[] = $next;

        $currentLat = (float) $next['latitude'];
        $currentLon = (float) $next['longitude'];

        array_splice($remaining, $nearestIndex, 1);
    }

    return $ordered;
}


/**
 * YANDEX MAPS MARSHRUT HAVOLASI
 * Boshlanish nuqtasi agent GPS'i, keyingi nuqtalar reja bo'yicha do'konlar.
 */
function buildYandexRouteUrl($stores, $startLat, $startLon)
{
    if (empty($stores)) {
        return null;
    }

    $points = [$startLat . ',' . $startLon];

    foreach ($stores as $store) {
        $points[] = $store['latitude'] . ',' . $store['longitude'];
    }

    return "https://yandex.uz/maps/?rtext="
        . rawurlencode(implode('~', $points))
        . "&rtt=auto";
}


/**
 * AGENT GPS MANZILINI SAQLASH
 */
function saveAgentLocation($pdo, $agentId, $latitude, $longitude)
{
    $stmt = $pdo->prepare("
        INSERT INTO agent_locations
        (agent_id, latitude, longitude, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(agent_id)
        DO UPDATE SET
            latitude = excluded.latitude,
            longitude = excluded.longitude,
            updated_at = CURRENT_TIMESTAMP
    ");

    return $stmt->execute([
        $agentId,
        $latitude,
        $longitude
    ]);
}
