
<?php

$config = require __DIR__ . '/config.php';
$token = trim($config['bot_token'] ?? '');

if ($token === '' || $token === 'BU_YERGA_BOT_TOKENINGIZNI_QOYING') {
    exit("Xatolik: Bot token kiritilmagan.\n");
}

$url = "https://api.telegram.org/bot{$token}/getMe";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
        | CURL_SSLVERSION_MAX_TLSv1_2,
    CURLOPT_CAINFO => 'C:/php/certs/cacert.pem',
]);

$response = curl_exec($ch);

if ($response === false) {
    exit("Ulanish xatosi: " . curl_error($ch) . "\n");
}

curl_close($ch);

$data = json_decode($response, true);

if (!empty($data['ok'])) {
    echo "Bot muvaffaqiyatli ulandi!\n";
    echo "Bot nomi: " . $data['result']['first_name'] . "\n";
    echo "Username: @" . $data['result']['username'] . "\n";
} else {
    echo "Telegram xatosi: " . ($data['description'] ?? 'Noma’lum xato') . "\n";
}
