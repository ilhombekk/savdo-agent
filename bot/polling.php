<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/scheduler.php';

date_default_timezone_set('Asia/Tashkent');

echo "Savdo Agent Bot ishga tushdi...\n";


/**
 * DO'KONLAR RO'YXATI: HAFTA KUNLARI
 */
function showStoreList($pdo, $chatId, $agentId)
{
    $counts = getWeekdayStoreCounts($pdo, $agentId);

    $total = array_sum($counts);

    if ($total === 0) {
        sendMessage($chatId, "🏪 Sizda hali do‘konlar mavjud emas.");
        return;
    }

    $dayNames = [
        1 => 'Dushanba',
        2 => 'Seshanba',
        3 => 'Chorshanba',
        4 => 'Payshanba',
        5 => 'Juma'
    ];

    $text = "🏪 DO‘KONLAR HAFTA KUNLARI BO‘YICHA

";
    $text .= "Qaysi kuni borishingiz kerakligini tanlang:

";

    $keyboard = [];

    foreach ($dayNames as $day => $name) {
        $text .= "📅 {$name}: {$counts[$day]} ta do‘kon
";
        $keyboard[] = [[
            'text' => "📅 {$name} ({$counts[$day]})",
            'callback_data' => 'day|' . $day
        ]];
    }

    $keyboard[] = [[
        'text' => '🏪 Barcha do‘konlar',
        'callback_data' => 'day|all'
    ]];

    sendInlineMessage($chatId, $text, $keyboard);
}

/**
 * TANLANGAN KUNNING DO'KONLARI
 */
function showStoresForWeekday($pdo, $chatId, $agentId, $weekday)
{
    $dayNames = [
        1 => 'Dushanba',
        2 => 'Seshanba',
        3 => 'Chorshanba',
        4 => 'Payshanba',
        5 => 'Juma'
    ];

    if ($weekday === 'all') {
        $stores = getAgentStores($pdo, $agentId);
        $heading = 'BARCHA DO‘KONLAR';
    } else {
        $weekday = (int) $weekday;

        if (!isset($dayNames[$weekday])) {
            sendMessage($chatId, "❌ Noto‘g‘ri hafta kuni tanlandi.");
            return;
        }

        $stores = getStoresByWeekday($pdo, $agentId, $weekday);
        $heading = strtoupper($dayNames[$weekday]) . ' KUNIDAGI DO‘KONLAR';
    }

    if (empty($stores)) {
        sendInlineMessage(
            $chatId,
            "📭 {$heading}

Bu kun uchun do‘konlar mavjud emas.",
            [[[
                'text' => '⬅️ Kunlar ro‘yxatiga qaytish',
                'callback_data' => 'day|menu'
            ]]]
        );
        return;
    }

    $text = "🏪 {$heading}

";
    $keyboard = [];

    foreach (array_slice($stores, 0, 50) as $index => $store) {
        $text .= ($index + 1) . ". " . $store['name'] . "
";

        $keyboard[] = [[
            'text' => '🏪 ' . $store['name'],
            'callback_data' => 'store|info|' . $store['id']
        ]];
    }

    if (count($stores) > 50) {
        $text .= "
Birinchi 50 ta do‘kon ko‘rsatildi.";
    }

    $keyboard[] = [[
        'text' => '⬅️ Kunlar ro‘yxatiga qaytish',
        'callback_data' => 'day|menu'
    ]];

    sendInlineMessage(
        $chatId,
        $text . "
Jami: " . count($stores) . " ta",
        $keyboard
    );
}


/**
 * DO'KON MA'LUMOTI
 */
function showStoreInfo($pdo, $chatId, $agentId, $storeId)
{
    $stmt = $pdo->prepare("
        SELECT id, name, latitude, longitude, schedule_weekday
        FROM stores
        WHERE id = ? AND agent_id = ?
    ");

    $stmt->execute([$storeId, $agentId]);

    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        sendMessage($chatId, "❌ Do‘kon topilmadi.");
        return;
    }

    $map = getMapUrl(
        $store['latitude'],
        $store['longitude']
    );

    $text = "🏪 DO‘KON MA'LUMOTI\n\n"
        . "Nomi: " . $store['name'] . "\n"
        . "ID: " . $store['id'] . "\n"
        . "📅 Reja kuni: " . getWeekdayName($store['schedule_weekday']) . "\n"
        . "📍 Manzil: " . $map . "\n\n"
        . "Bugungi tashrif holatini belgilang:";

    $keyboard = [
        [
            [
                'text' => '✅ Borildi',
                'callback_data' => 'visit|' . $store['id'] . '|visited'
            ],
            [
                'text' => '❌ Borilmadi',
                'callback_data' => 'visit|' . $store['id'] . '|not_visited'
            ]
        ]
    ];

    sendInlineMessage($chatId, $text, $keyboard);
}


/**
 * BUGUNGI REJA
 */
function showTodayPlan($pdo, $chatId, $agentId)
{
    $stores = getPlannedStores($pdo, $agentId);

    if (empty($stores)) {
        sendMessage(
            $chatId,
            "📋 Bugungi reja topilmadi.\n\n"
            . "Bugungi kun uchun biriktirilgan do‘konlar mavjud emas."
        );
        return;
    }

    $text = "📋 BUGUNGI TASHRIF REJASI\n\n";

    foreach ($stores as $index => $store) {
        $text .= ($index + 1) . ". " . $store['name'] . "\n";
    }

    $text .= "\nJami do‘konlar: " . count($stores);

    sendMessage($chatId, $text);

    foreach ($stores as $store) {
        $keyboard = [
            [
                [
                    'text' => '✅ Borildi',
                    'callback_data' => 'visit|' . $store['id'] . '|visited'
                ],
                [
                    'text' => '❌ Borilmadi',
                    'callback_data' => 'visit|' . $store['id'] . '|not_visited'
                ]
            ]
        ];

        sendInlineMessage(
            $chatId,
            "🏪 " . $store['name'] . "\n"
            . "📍 " . getMapUrl(
                $store['latitude'],
                $store['longitude']
            ) . "\n\n"
            . "Tashrif holatini belgilang:",
            $keyboard
        );
    }
}


/**
 * BUGUNGI TASHRIFLAR HISOBOTI
 */
function showTodayVisits($pdo, $chatId, $agentId)
{
    $visits = getTodayVisits($pdo, $agentId);

    if (empty($visits)) {
        sendMessage(
            $chatId,
            "📊 Bugun hali tashriflar qayd etilmagan."
        );
        return;
    }

    $visited = 0;
    $notVisited = 0;

    $text = "📊 BUGUNGI TASHRIFLAR\n\n";

    foreach ($visits as $index => $visit) {
        if ($visit['status'] === 'visited') {
            $visited++;
            $status = "✅ Borildi";
        } else {
            $notVisited++;
            $status = "❌ Borilmadi";
        }

        $text .= ($index + 1) . ". " . $visit['name'] . "\n";
        $text .= $status . "\n";
        $text .= "📍 " . getMapUrl(
            $visit['latitude'],
            $visit['longitude']
        ) . "\n\n";
    }

    $total = count($visits);

    $percent = $total > 0
        ? round(($visited / $total) * 100, 1)
        : 0;

    $text .= "━━━━━━━━━━━━━━\n";
    $text .= "Jami: " . $total . "\n";
    $text .= "✅ Borildi: " . $visited . "\n";
    $text .= "❌ Borilmadi: " . $notVisited . "\n";
    $text .= "📈 Bajarilish: " . $percent . "%";

    sendMessage($chatId, $text);
}


/**
 * KEYINGI TASHRIFLAR
 */
function showNextVisits($pdo, $chatId, $agentId)
{
    $stores = getPlannedStores($pdo, $agentId);

    if (empty($stores)) {
        sendMessage(
            $chatId,
            "📅 Bugungi hafta kunida avval tashrif buyurilgan do‘konlar topilmadi."
        );
        return;
    }

    sendMessage(
        $chatId,
        "📅 BUGUNGI HAFTA KUNI UCHUN AVVALGI TASHRIFLAR\n\n"
        . "Quyidagi do‘konlarga tashrif holatini belgilang:"
    );

    foreach ($stores as $store) {
        $keyboard = [
            [
                [
                    'text' => '✅ Borildi',
                    'callback_data' => 'visit|' . $store['id'] . '|visited'
                ],
                [
                    'text' => '❌ Borilmadi',
                    'callback_data' => 'visit|' . $store['id'] . '|not_visited'
                ]
            ]
        ];

        sendInlineMessage(
            $chatId,
            "🏪 " . $store['name'] . "\n"
            . "📍 " . getMapUrl(
                $store['latitude'],
                $store['longitude']
            ),
            $keyboard
        );
    }
}


/**
 * HAFTALIK HISOBOT
 */
function showWeeklyReport($pdo, $chatId, $agentId)
{
    $stats = buildWeeklyReport($pdo, $agentId);

    sendMessage(
        $chatId,
        weeklyReportText($stats)
    );
}


/**
 * MARSHRUT TUZISH
 */
function createAgentRoute($pdo, $chatId, $agentId, $latitude, $longitude)
{
    saveAgentLocation(
        $pdo,
        $agentId,
        $latitude,
        $longitude
    );

    $stores = getPlannedStores($pdo, $agentId);

    if (empty($stores)) {
        clearAgentState($pdo, $chatId);

        sendMessage(
            $chatId,
            "🧭 Bugun uchun marshrut tuzishga do‘kon topilmadi.",
            [],
            true
        );

        mainMenu($chatId);
        return;
    }

    $orderedStores = optimizeRoute(
        $stores,
        $latitude,
        $longitude
    );

    $routeUrl = buildYandexRouteUrl(
        $orderedStores,
        $latitude,
        $longitude
    );

    $text = "🧭 OPTIMALLASHTIRILGAN MARSHRUT\n\n";
    $text .= "📍 Boshlanish: Sizning GPS manzilingiz\n\n";

    foreach ($orderedStores as $index => $store) {
        $text .= ($index + 1) . ". " . $store['name'] . "\n";
        $text .= "📏 Oldingi nuqtadan: "
            . $store['distance_km'] . " km\n";
        $text .= "🗺 " . getMapUrl(
            $store['latitude'],
            $store['longitude']
        ) . "\n\n";
    }

    $text .= "🏪 Jami do‘konlar: " . count($orderedStores);
    $text .= "\n\nYandex Maps orqali marshrutni oching:\n";
    $text .= $routeUrl;

    clearAgentState($pdo, $chatId);

    sendMessage(
        $chatId,
        $text,
        [],
        true
    );

    mainMenu($chatId);
}


/**
 * MAVJUD DO'KONNI TANLASH
 */
function reuseExistingStore($pdo, $chatId, $agentId, $storeId)
{
    $stmt = $pdo->prepare("
        SELECT id, name, latitude, longitude
        FROM stores
        WHERE id = ? AND agent_id = ?
    ");

    $stmt->execute([$storeId, $agentId]);

    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        sendMessage($chatId, "❌ Do‘kon topilmadi.");
        return;
    }

    saveTodayVisit($pdo, $store['id'], 'visited');

    clearAgentState($pdo, $chatId);

    sendMessage(
        $chatId,
        "✅ Mavjud do‘kon tanlandi!\n\n"
        . "🏪 " . $store['name'] . "\n"
        . "📍 " . getMapUrl(
            $store['latitude'],
            $store['longitude']
        ) . "\n\n"
        . "Bugungi tashrif qayd etildi."
    );

    mainMenu($chatId);
}


/**
 * BOT SIKLI
 */
$offset = 0;

while (true) {
    try {

        // Avtomatik eslatmalar
        runScheduler($pdo);

        $response = telegram('getUpdates', [
            'offset' => $offset,
            'timeout' => 25,
            'allowed_updates' => json_encode([
                'message',
                'callback_query'
            ])
        ]);

        if (empty($response['ok']) || !isset($response['result'])) {
            sleep(1);
            continue;
        }

        foreach ($response['result'] as $update) {

            $offset = $update['update_id'] + 1;


            /**
             * CALLBACK
             */
            if (isset($update['callback_query'])) {

                $callback = $update['callback_query'];

                $callbackId = $callback['id'];
                $chatId = $callback['message']['chat']['id'];
                $data = $callback['data'] ?? '';

                answerCallbackQuery($callbackId);

                $agentId = getAgentId($pdo, $chatId);

                if (!$agentId) {
                    sendMessage(
                        $chatId,
                        "Avval /start buyrug‘ini yuboring."
                    );
                    continue;
                }

                $parts = explode('|', $data);


                // TASHRIF HOLATINI BELGILASH
                if (
                    count($parts) === 3 &&
                    $parts[0] === 'visit'
                ) {
                    $storeId = (int) $parts[1];
                    $status = $parts[2];

                    if (!in_array(
                        $status,
                        ['visited', 'not_visited'],
                        true
                    )) {
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM stores
                        WHERE id = ? AND agent_id = ?
                    ");

                    $stmt->execute([$storeId, $agentId]);

                    if (!$stmt->fetchColumn()) {
                        sendMessage(
                            $chatId,
                            "❌ Bu do‘kon sizga tegishli emas."
                        );
                        continue;
                    }

                    saveTodayVisit(
                        $pdo,
                        $storeId,
                        $status
                    );

                    sendMessage(
                        $chatId,
                        $status === 'visited'
                            ? "✅ Tashrif bajarildi deb belgilandi."
                            : "❌ Tashrif bajarilmadi deb belgilandi."
                    );

                    continue;
                }


                // HAFTA KUNLARI RO'YXATIGA QAYTISH
                if ($data === 'day|menu') {
                    showStoreList($pdo, $chatId, $agentId);
                    continue;
                }

                // TANLANGAN KUN YOKI BARCHA DO'KONLAR
                if (
                    count($parts) === 2 &&
                    $parts[0] === 'day'
                ) {
                    if ($parts[1] === 'all') {
                        showStoresForWeekday($pdo, $chatId, $agentId, 'all');
                    } elseif (ctype_digit($parts[1])) {
                        showStoresForWeekday($pdo, $chatId, $agentId, (int) $parts[1]);
                    }
                    continue;
                }

                // DO'KON MA'LUMOTI
                if (
                    count($parts) === 3 &&
                    $parts[0] === 'store' &&
                    $parts[1] === 'info'
                ) {
                    showStoreInfo(
                        $pdo,
                        $chatId,
                        $agentId,
                        (int) $parts[2]
                    );

                    continue;
                }


                // MAVJUD DO'KONNI TANLASH
                if (
                    count($parts) === 3 &&
                    $parts[0] === 'store' &&
                    $parts[1] === 'reuse'
                ) {
                    $state = getAgentState($pdo, $chatId);

                    if (
                        !$state ||
                        $state['action'] !== 'awaiting_duplicate_choice'
                    ) {
                        sendMessage(
                            $chatId,
                            "❌ Bu tanlov muddati tugagan. Qaytadan boshlang."
                        );
                        continue;
                    }

                    reuseExistingStore(
                        $pdo,
                        $chatId,
                        $agentId,
                        (int) $parts[2]
                    );

                    continue;
                }


                // YANGI FILIAL
                if ($data === 'store|new') {

                    $state = getAgentState($pdo, $chatId);

                    if (
                        !$state ||
                        $state['action'] !== 'awaiting_duplicate_choice'
                    ) {
                        sendMessage(
                            $chatId,
                            "❌ Bu tanlov muddati tugagan. Qaytadan boshlang."
                        );
                        continue;
                    }

                    setAgentState(
                        $pdo,
                        $chatId,
                        'awaiting_store_location',
                        $state['store_name']
                    );

                    sendMessage(
                        $chatId,
                        "➕ Yangi filial qo‘shilmoqda.\n\n"
                        . "📍 Filialning GPS manzilini yuboring:",
                        locationKeyboard()
                    );

                    continue;
                }

                continue;
            }


            /**
             * MESSAGE
             */
            if (!isset($update['message'])) {
                continue;
            }

            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = trim($message['text'] ?? '');

            $agentId = getAgentId($pdo, $chatId);


            // START
            if ($text === '/start') {

                $firstName = $message['from']['first_name'] ?? '';
                $lastName = $message['from']['last_name'] ?? '';

                registerAgent(
                    $pdo,
                    $chatId,
                    trim($firstName . ' ' . $lastName)
                );

                clearAgentState($pdo, $chatId);

                mainMenu($chatId);

                continue;
            }


            if (!$agentId) {
                sendMessage(
                    $chatId,
                    "Botdan foydalanish uchun /start buyrug‘ini yuboring."
                );
                continue;
            }


            // BEKOR QILISH
            if ($text === '❌ Bekor qilish') {

                clearAgentState($pdo, $chatId);

                sendMessage(
                    $chatId,
                    "Amal bekor qilindi.",
                    [],
                    true
                );

                mainMenu($chatId);

                continue;
            }


            // YORDAM
            if ($text === 'ℹ️ Yordam') {

                sendMessage(
                    $chatId,
                    "ℹ️ BOTDAN FOYDALANISH\n\n"
                    . "➕ Do‘kon qo‘shish — yangi do‘konni GPS bilan saqlash.\n"
                    . "🏪 Do‘konlar ro‘yxati — barcha do‘konlarni ko‘rish.\n"
                    . "📋 Bugungi reja — bugun boriladigan do‘konlar.\n"
                    . "📊 Bugungi tashriflar — bajarilgan tashriflar.\n"
                    . "🧭 Marshrutni tuzish — GPS asosida eng yaqin do‘konlardan yo‘l tuzish.\n"
                    . "📈 Haftalik hisobot — haftalik natijalar.\n\n"
                    . "Eslatma: ish kunlari 08:30 da avtomatik yuboriladi."
                );

                continue;
            }


            // DO'KON QO'SHISH
            if ($text === '➕ Do‘kon qo‘shish') {

                setAgentState(
                    $pdo,
                    $chatId,
                    'awaiting_store_name'
                );

                sendMessage(
                    $chatId,
                    "🏪 Do‘kon nomini kiriting:"
                );

                continue;
            }


            // DO'KONLAR RO'YXATI
            if ($text === '🏪 Do‘konlar ro‘yxati') {

                showStoreList($pdo, $chatId, $agentId);

                continue;
            }


            // BUGUNGI REJA
            if ($text === '📋 Bugungi reja') {

                showTodayPlan($pdo, $chatId, $agentId);

                continue;
            }


            // BUGUNGI TASHRIFLAR
            if ($text === '📊 Bugungi tashriflar') {

                showTodayVisits($pdo, $chatId, $agentId);

                continue;
            }


            // KEYINGI TASHRIFLAR
            if ($text === '📅 Keyingi tashriflar') {

                showNextVisits($pdo, $chatId, $agentId);

                continue;
            }


            // HAFTALIK HISOBOT
            if ($text === '📈 Haftalik hisobot') {

                showWeeklyReport($pdo, $chatId, $agentId);

                continue;
            }


            // MARSHRUT TUZISH
            if ($text === '🧭 Marshrutni tuzish') {

                setAgentState(
                    $pdo,
                    $chatId,
                    'awaiting_route_location'
                );

                sendMessage(
                    $chatId,
                    "🧭 Marshrut tuzish uchun hozirgi GPS manzilingizni yuboring.\n\n"
                    . "Bot eng yaqin do‘kondan boshlab tartiblaydi:",
                    locationKeyboard()
                );

                continue;
            }


            // AGENT HOLATI
            $state = getAgentState($pdo, $chatId);

            if (!$state) {
                continue;
            }


            // GPS QABUL QILISH
            if (isset($message['location'])) {

                $latitude = $message['location']['latitude'];
                $longitude = $message['location']['longitude'];


                // MARSHRUT GPS
                if ($state['action'] === 'awaiting_route_location') {

                    createAgentRoute(
                        $pdo,
                        $chatId,
                        $agentId,
                        $latitude,
                        $longitude
                    );

                    continue;
                }


                // YANGI DO'KON GPS
                if ($state['action'] === 'awaiting_store_location') {

                    $storeName = trim($state['store_name'] ?? '');

                    if ($storeName === '') {
                        clearAgentState($pdo, $chatId);

                        sendMessage(
                            $chatId,
                            "❌ Do‘kon nomi topilmadi. Qaytadan boshlang."
                        );

                        mainMenu($chatId);
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO stores
                        (agent_id, name, latitude, longitude, schedule_weekday)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $agentId,
                        $storeName,
                        $latitude,
                        $longitude,
                        (int) date('N')
                    ]);

                    $storeId = $pdo->lastInsertId();

                    saveTodayVisit(
                        $pdo,
                        $storeId,
                        'visited'
                    );

                    clearAgentState($pdo, $chatId);

                    sendMessage(
                        $chatId,
                        "✅ DO‘KON MUVAFFAQIYATLI QO‘SHILDI!\n\n"
                        . "🏪 Nomi: " . $storeName . "\n"
                        . "📅 Sana: " . date('d.m.Y') . "\n"
                        . "🗓 Haftalik reja: " . getWeekdayName((int) date('N')) . "\n"
                        . "📍 " . getMapUrl($latitude, $longitude) . "\n\n"
                        . "Bugungi tashrif ham qayd etildi.",
                        [],
                        true
                    );

                    mainMenu($chatId);

                    continue;
                }
            }


            // DO'KON NOMI KIRITISH
            if ($state['action'] === 'awaiting_store_name') {

                if ($text === '') {
                    sendMessage(
                        $chatId,
                        "Iltimos, do‘kon nomini matn ko‘rinishida kiriting."
                    );
                    continue;
                }

                $duplicates = findDuplicateStores(
                    $pdo,
                    $agentId,
                    $text
                );

                if (!empty($duplicates)) {

                    setAgentState(
                        $pdo,
                        $chatId,
                        'awaiting_duplicate_choice',
                        $text
                    );

                    $keyboard = [];

                    foreach ($duplicates as $store) {
                        $keyboard[] = [
                            [
                                'text' => '🏪 ' . $store['name'] . ' (#' . $store['id'] . ')',
                                'callback_data' => 'store|reuse|' . $store['id']
                            ]
                        ];
                    }

                    $keyboard[] = [
                        [
                            'text' => '➕ Yangi filial sifatida qo‘shish',
                            'callback_data' => 'store|new'
                        ]
                    ];

                    sendInlineMessage(
                        $chatId,
                        "⚠️ BU NOMLI DO‘KON AVVALDAN MAVJUD!\n\n"
                        . "🏪 Kiritilgan nom: " . $text . "\n\n"
                        . "Mavjud do‘konni tanlaysizmi yoki yangi filial qo‘shasizmi?",
                        $keyboard
                    );

                    continue;
                }

                setAgentState(
                    $pdo,
                    $chatId,
                    'awaiting_store_location',
                    $text
                );

                sendMessage(
                    $chatId,
                    "✅ Do‘kon nomi qabul qilindi: " . $text
                    . "\n\n📍 Endi GPS manzilni yuboring:",
                    locationKeyboard()
                );

                continue;
            }


            // GPS KUTILAYOTGANDA BOSHQA MATN
            if (
                $state['action'] === 'awaiting_store_location' ||
                $state['action'] === 'awaiting_route_location'
            ) {
                sendMessage(
                    $chatId,
                    "📍 Iltimos, GPS manzilni yuborish tugmasini bosing.",
                    locationKeyboard()
                );

                continue;
            }


            // DUBLIKAT TANLOVI KUTILAYOTGANDA
            if ($state['action'] === 'awaiting_duplicate_choice') {

                sendMessage(
                    $chatId,
                    "Iltimos, yuqoridagi tanlov tugmalaridan foydalaning."
                );

                continue;
            }
        }

    } catch (Throwable $e) {

        echo "Xatolik: " . $e->getMessage() . "\n";

        sleep(3);
    }
}
