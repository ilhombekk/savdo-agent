
<?php

require_once __DIR__ . '/functions.php';

date_default_timezone_set('Asia/Tashkent');


/**
 * Ushbu xabar oldin yuborilganmi?
 */
function schedulerAlreadySent($pdo, $agentId, $eventKey)
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM scheduler_logs
        WHERE agent_id = ? AND event_key = ?
    ");

    $stmt->execute([$agentId, $eventKey]);

    return (bool) $stmt->fetchColumn();
}


/**
 * Yuborilgan xabarni qayd etish
 */
function schedulerLog($pdo, $agentId, $eventKey)
{
    $stmt = $pdo->prepare("
        INSERT OR IGNORE INTO scheduler_logs
        (agent_id, event_key)
        VALUES (?, ?)
    ");

    return $stmt->execute([
        $agentId,
        $eventKey
    ]);
}


/**
 * Barcha agentlar
 */
function getAgents($pdo)
{
    return $pdo->query("
        SELECT id, chat_id, full_name
        FROM agents
    ")->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * KUNLIK ESLATMA
 */
function sendDailyReminder($pdo, $agent)
{
    $today = date('Y-m-d');
    $eventKey = 'daily_' . $today;

    if (schedulerAlreadySent($pdo, $agent['id'], $eventKey)) {
        return;
    }

    $weekday = (int) date('N');

    $stores = getPlannedStores(
        $pdo,
        $agent['id'],
        $weekday
    );

    $text = "☀️ XAYRLI TONG!\n\n"
        . "Bugungi savdo tashriflaringiz vaqti keldi.\n"
        . "📅 Sana: " . date('d.m.Y') . "\n\n";

    if (empty($stores)) {
        $text .= "Bugun uchun avvalgi tashriflar asosida reja topilmadi.\n";
    } else {
        $text .= "🏪 BUGUN BORILADIGAN DO‘KONLAR:\n\n";

        foreach ($stores as $index => $store) {
            $text .= ($index + 1) . ". " . $store['name'] . "\n";
            $text .= getMapUrl(
                $store['latitude'],
                $store['longitude']
            ) . "\n\n";
        }
    }

    $text .= "Omadli ish kuni tilaymiz!";

    $result = sendMessage($agent['chat_id'], $text);

    if (!empty($result['ok'])) {
        schedulerLog($pdo, $agent['id'], $eventKey);
    }
}


/**
 * HAFTALIK HISOBOT
 */
function sendWeeklyReport($pdo, $agent)
{
    $today = date('Y-m-d');
    $eventKey = 'weekly_' . $today;

    if (schedulerAlreadySent($pdo, $agent['id'], $eventKey)) {
        return;
    }

    $stats = buildWeeklyReport($pdo, $agent['id']);

    $text = weeklyReportText($stats);

    $result = sendMessage($agent['chat_id'], $text);

    if (!empty($result['ok'])) {
        schedulerLog($pdo, $agent['id'], $eventKey);
    }
}


/**
 * REJALASHTIRILGAN VAZIFALAR
 */
function runScheduler($pdo)
{
    $weekday = (int) date('N');
    $time = date('H:i');

    // Dushanba - juma, 08:30 dan keyin
    if ($weekday >= 1 && $weekday <= 5 && $time >= '08:30') {
        foreach (getAgents($pdo) as $agent) {
            try {
                sendDailyReminder($pdo, $agent);
            } catch (Throwable $e) {
                error_log("Daily reminder: " . $e->getMessage());
            }
        }
    }

    // Juma kuni, 17:00 dan keyin
    if ($weekday === 5 && $time >= '17:00') {
        foreach (getAgents($pdo) as $agent) {
            try {
                sendWeeklyReport($pdo, $agent);
            } catch (Throwable $e) {
                error_log("Weekly report: " . $e->getMessage());
            }
        }
    }
}
