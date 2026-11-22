
<?php

$ch = curl_init('https://api.telegram.org/');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2 | CURL_SSLVERSION_MAX_TLSv1_2,
]);

$response = curl_exec($ch);

echo "\n--- NATIJA ---\n";

if ($response === false) {
    echo "XATO: " . curl_error($ch) . "\n";
} else {
    echo "HTTP: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
    echo $response . "\n";
}

curl_close($ch);
