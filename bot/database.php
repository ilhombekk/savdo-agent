
<?php

date_default_timezone_set('Asia/Tashkent');

$storageDir = dirname(__DIR__) . '/storage';

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

$dbPath = $storageDir . '/savdo_agent.sqlite';

$pdo = new PDO('sqlite:' . $dbPath);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("PRAGMA foreign_keys = ON");
$pdo->exec("PRAGMA journal_mode = WAL");

/**
 * Agentlar
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS agents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id INTEGER NOT NULL UNIQUE,
        full_name TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");

/**
 * Do'konlar
 * schedule_weekday:
 * 1 - Dushanba
 * 2 - Seshanba
 * 3 - Chorshanba
 * 4 - Payshanba
 * 5 - Juma
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS stores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agent_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        latitude REAL NOT NULL,
        longitude REAL NOT NULL,
        schedule_weekday INTEGER,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id)
    )
");

/**
 * Tashriflar
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        visit_date TEXT NOT NULL,
        weekday INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'visited',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id)
    )
");

/**
 * Agent holatlari
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS agent_states (
        chat_id INTEGER PRIMARY KEY,
        action TEXT NOT NULL,
        store_name TEXT,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");

/**
 * Scheduler loglari
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS scheduler_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        agent_id INTEGER NOT NULL,
        event_key TEXT NOT NULL,
        sent_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(agent_id, event_key),
        FOREIGN KEY (agent_id) REFERENCES agents(id)
    )
");

/**
 * Agentning oxirgi GPS manzili
 */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS agent_locations (
        agent_id INTEGER PRIMARY KEY,
        latitude REAL NOT NULL,
        longitude REAL NOT NULL,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id)
    )
");

/**
 * Eski bazalarda schedule_weekday ustuni bo'lmasa qo'shish.
 */
$columns = $pdo->query("PRAGMA table_info(stores)")->fetchAll();

$hasScheduleWeekday = false;

foreach ($columns as $column) {
    if ($column['name'] === 'schedule_weekday') {
        $hasScheduleWeekday = true;
        break;
    }
}

if (!$hasScheduleWeekday) {
    $pdo->exec("
        ALTER TABLE stores
        ADD COLUMN schedule_weekday INTEGER
    ");
}

/**
 * Eski do'konlarga qo'shilgan sanasi bo'yicha
 * haftaning kunini biriktirish.
 *
 * SQLite CURRENT_TIMESTAMP UTC bo'lgani uchun
 * O'zbekiston vaqti uchun +5 soat qo'shiladi.
 */
$pdo->exec("
    UPDATE stores
    SET schedule_weekday = (
        (
            CAST(
                strftime('%w', datetime(created_at, '+5 hours'))
                AS INTEGER
            ) + 6
        ) % 7
    ) + 1
    WHERE schedule_weekday IS NULL
");

/**
 * Indekslar
 */
$pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_stores_agent
    ON stores(agent_id)
");

$pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_stores_schedule
    ON stores(agent_id, schedule_weekday)
");

$pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_visits_store_date
    ON visits(store_id, visit_date)
");

$pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_visits_weekday
    ON visits(weekday)
");

echo "Ma'lumotlar bazasi muvaffaqiyatli tekshirildi!\n";
echo "Do'konlarning haftalik kunlari tayyor!\n";
