<?php
/**
 * Wielder of Power - Main Webhook Entry Point
 * File: index.php
 */

// فراخوانی تمام ماژول‌های پروژه
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/callbacks.php';
require_once __DIR__ . '/messages.php';

// دریافت داده ارسالی از سمت تلگرام
$raw_input = file_get_contents('php://input');

// در صورتی که آدرس در مرورگر باز شود
if (!$raw_input) {
  header('Content-Type: text/html; charset=utf-8');
  echo "<h2>🎮 ربات Wielder of Power روی هاست PaaS پارس‌پک با موفقیت فعال و در حال اجرا است!</h2>";
  exit;
}

$update = json_decode($raw_input, true);
if (!$update) {
  http_response_code(200);
  exit;
}

// بارگذاری دیتابیس بازی
$data = loadData();

// ۱. اجرای خودکارسازی‌های پس‌زمینه (بررسی پایان فصل و انقضای معاملات)
processSeasonEnd($data);
checkExpiredTrades($data);

// ۲. تشخیص چت آیدی فعال در درخواست
$activeChatId = null;
if (isset($update['message']['chat']['id'])) {
  $activeChatId = $update['message']['chat']['id'];
} else if (isset($update['callback_query']['message']['chat']['id'])) {
  $activeChatId = $update['callback_query']['message']['chat']['id'];
}

// ۳. بررسی دسترسی، وضعیت بن و به‌روزرسانی منابع و کارگرهای کاربر فعال
if ($activeChatId) {
  $activeUser = null;
  foreach ($data['users'] as $u => $val) {
    if ($val && ($val['chat_id'] ?? null) == $activeChatId) {
      $activeUser = $u;
      break;
    }
  }

  if ($activeUser && $activeUser !== "Owner") {
    $isCallback = isset($update['callback_query']);
    $cbData = $isCallback ? ($update['callback_query']['data'] ?? '') : null;
    $msgId = $isCallback ? ($update['callback_query']['message']['message_id'] ?? null) : null;

    if ($cbData !== "update_ban_status") {
      $isBanned = checkUserBanAndRespond($activeChatId, $data, $msgId, $isCallback);
      if ($isBanned) {
        if ($isCallback) {
          tgCall("answerCallbackQuery", ['callback_query_id' => $update['callback_query']['id']]);
        }
        http_response_code(200);
        exit;
      }
    }

    // به‌روزرسانی سازه‌ها و تولیدات معادن
    if (updateUserState($activeUser, $data)) {
      saveData($data);
    }
  }
}

// ۴. هدایت درخواست به هندلر مناسب (دکمه شیشه‌ای یا پیام متنی)
if (isset($update['callback_query'])) {
  handleCallback($update['callback_query'], $data);
} else if (isset($update['message'])) {
  handleMessage($update['message'], $data);
}

// ارسال پاسخ موفق به سرور تلگرام
http_response_code(200);
echo "OK";