<?php
/**
 * Simple Greeting Bot - Bot 2
 * File: bot2/index.php
 */

define('BOT_TOKEN', '8202840322:AAHKy__CS5ZtiK9BjFWHHKYWhRVI3ztPZo8');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// دریافت اطلاعات ارسالی از تلگرام
$content = file_get_contents("php://input");

// اگر آدرس در مرورگر باز شود
if (!$content) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>🤖 ربات دوم (bot2) با موفقیت فعال و در حال اجرا است!</h2>";
    exit;
}

$update = json_decode($content, true);
if (!$update || !isset($update['message'])) {
    http_response_code(200);
    exit;
}

$chat_id = $update['message']['chat']['id'] ?? null;
$text    = trim($update['message']['text'] ?? '');
$name    = $update['message']['chat']['first_name'] ?? 'دوست عزیز';

// پاسخ به دستور استارت
if ($text === '/start') {
    $reply = "سلام {$name}! خوش آمدید.";
    sendMessage($chat_id, $reply);
}

// تابع ارسال پیام به تلگرام
function sendMessage($chat_id, $text) {
    $url = API_URL . "sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text'    => $text
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

http_response_code(200);
echo "OK";
