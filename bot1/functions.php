<?php
/**
 * Wielder of Power - Core Helper Functions & Database Engine
 * File: functions.php
 */

require_once __DIR__ . '/config.php';

// تابع تاریخ و زمان شمسی دقیق
function getShamsiDateTime() {
  if (class_exists('IntlDateFormatter')) {
    $formatter = new IntlDateFormatter(
      'fa_IR@calendar=persian',
      IntlDateFormatter::MEDIUM,
      IntlDateFormatter::MEDIUM,
      'Asia/Tehran',
      IntlDateFormatter::TRADITIONAL,
      "yyyy/MM/dd HH:mm:ss"
    );
    $res = $formatter->format(time());
    $parts = explode(' ', $res);
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return [
      'date' => str_replace($fa, $en, $parts[0] ?? date('Y/m/d')),
      'time' => str_replace($fa, $en, $parts[1] ?? date('H:i:s'))
    ];
  }
  return [
    'date' => date('Y/m/d'),
    'time' => date('H:i:s')
  ];
}

// ارسال درخواست به تلگرام با cURL
function tgCall($method, $body = []) {
  $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  $res = curl_exec($ch);
  curl_close($ch);
  return json_decode($res, true) ?: [];
}

// بارگذاری دیتابیس جیسون
function loadData() {
  if (!file_exists(DB_FILE)) {
    $defaultData = [
      'users' => [
        'Owner' => ['password' => "128138", 'chat_id' => null, 'channel_msg_id' => null]
      ],
      'user_states' => [],
      'temp_data' => [],
      'last_menu_msg' => [],
      'trades' => [],
      'gifts' => [],
      'challenges' => [],
      'reports' => [],
      'chat_messages' => [],
      'season_start' => time()
    ];
    file_put_contents(DB_FILE, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $defaultData;
  }

  $raw = file_get_contents(DB_FILE);
  $data = json_decode($raw, true) ?: [];
  if (!isset($data['users'])) $data['users'] = ['Owner' => ['password' => "128138", 'chat_id' => null, 'channel_msg_id' => null]];
  if (!isset($data['user_states'])) $data['user_states'] = [];
  if (!isset($data['temp_data'])) $data['temp_data'] = [];
  if (!isset($data['last_menu_msg'])) $data['last_menu_msg'] = [];
  if (!isset($data['trades'])) $data['trades'] = [];
  if (!isset($data['gifts'])) $data['gifts'] = [];
  if (!isset($data['challenges'])) $data['challenges'] = [];
  if (!isset($data['reports'])) $data['reports'] = [];
  if (!isset($data['chat_messages'])) $data['chat_messages'] = [];
  if (!isset($data['season_start'])) $data['season_start'] = time();

  return $data;
}

// ذخیره ایمن دیتابیس جیسون با قفل فایل
function saveData($data) {
  file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// فرمت اعداد با کاما
function formatNumber($num) {
  if ($num === null || !is_numeric($num)) return "0";
  return number_format((float)$num);
}

// تبدیل ثانیه به فرمت روز و ساعت و دقیقه و ثانیه
function formatSecondsToDHMS($seconds) {
  $seconds = max(0, (int)$seconds);
  $days = floor($seconds / 86400);
  $hours = floor(($seconds % 86400) / 3600);
  $minutes = floor(($seconds % 3600) / 60);
  $secs = floor($seconds % 60);
  if ($days > 0) {
    return sprintf("%d روز و %02d:%02d:%02d", $days, $hours, $minutes, $secs);
  }
  return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}

// محاسبه مجموع قدرت، استقامت و انرژی نیروها
function calculateTroopStats($troops) {
  $power = 0; $stamina = 0; $energy = 0;
  if (!$troops) return ['power' => $power, 'stamina' => $stamina, 'energy' => $energy];
  foreach ($troops as $tKey => $count) {
    if ($count > 0 && isset(TROOP_STATS[$tKey])) {
      $power += $count * TROOP_STATS[$tKey]['power'];
      $stamina += $count * TROOP_STATS[$tKey]['stamina'];
      $energy += $count * TROOP_STATS[$tKey]['energy'];
    }
  }
  return ['power' => $power, 'stamina' => $stamina, 'energy' => $energy];
}

// مقداردهی اولیه به ساختار دیتای کاربر جدید
function initUserGameData(&$user_info) {
  if (!isset($user_info['level'])) $user_info['level'] = 1;
  if (!isset($user_info['level_xp'])) $user_info['level_xp'] = 0;
  if (!isset($user_info['rank'])) $user_info['rank'] = "\u{200E}⚪️I";
  if (!isset($user_info['rank_xp'])) $user_info['rank_xp'] = 0;
  if (!isset($user_info['city_count'])) $user_info['city_count'] = 1;
  if (!isset($user_info['reg_date'])) $user_info['reg_date'] = "نامشخص";
  if (!isset($user_info['reg_time'])) $user_info['reg_time'] = "نامشخص";
  if (!isset($user_info['login_date'])) $user_info['login_date'] = "نامشخص";
  if (!isset($user_info['login_time'])) $user_info['login_time'] = "نامشخص";
  if (!isset($user_info['created_timestamp'])) $user_info['created_timestamp'] = time() * 1000;
  if (!isset($user_info['resources'])) {
    $user_info['resources'] = [
      'coin' => 500,
      'iron' => 50,
      'stone' => 100,
      'wood' => 100,
      'bread' => 10,
      'citizen' => 5,
      'force' => 0
    ];
  }
  if (!isset($user_info['regions'])) {
    $user_info['regions'] = [];
    for ($i = 1; $i <= ($user_info['city_count'] * 10); $i++) {
      $user_info['regions'][(string)$i] = null;
    }
  }
  if (!isset($user_info['workers'])) {
    $user_info['workers'] = [
      "1" => ['status' => "idle", 'target_region' => null],
      "2" => ['status' => "idle", 'target_region' => null]
    ];
  }
}

// تابع کسر ایکس‌پی رنک و تنزل سطح رنک با کف صفر
function deductRankXp(&$user_info, $amount) {
  if (!$user_info || $amount <= 0) return;

  $currentTierIdx = 0;
  foreach (RANK_TIERS as $i => $t) {
    if ($t['name'] === $user_info['rank']) {
      $currentTierIdx = $i;
      break;
    }
  }

  $user_info['rank_xp'] = ($user_info['rank_xp'] ?? 0) - $amount;

  while ($user_info['rank_xp'] < 0 && $currentTierIdx > 0) {
    $currentTierIdx--;
    $user_info['rank'] = RANK_TIERS[$currentTierIdx]['name'];
    $user_info['rank_xp'] += RANK_TIERS[$currentTierIdx]['req'];
  }

  if ($currentTierIdx === 0 && $user_info['rank_xp'] < 0) {
    $user_info['rank_xp'] = 0;
  }
}

// محاسبه موجودی انباشته‌شده در معادن و مسکن
function getAccumulatedResource(&$reg_data) {
  if (!$reg_data || $reg_data['type'] === "construction" || $reg_data['type'] === "market") return 0;
  if (!isset(BUILDING_STATS[$reg_data['type']])) return 0;
  $stats = BUILDING_STATS[$reg_data['type']];
  if (!isset($stats['production_interval'])) return 0;

  if (!isset($reg_data['last_harvest'])) {
    $reg_data['last_harvest'] = time();
  }

  $level = $reg_data['level'] ?? 1;
  $multiplier = pow(2, $level - 1);
  $amount = $stats['production_amount'] * $multiplier;
  $capacity = $stats['capacity'] * $multiplier;

  $elapsed = time() - $reg_data['last_harvest'];
  $ticks = floor($elapsed / $stats['production_interval']);
  return min($capacity, $ticks * $amount);
}

// به‌روزرسانی تایمرهای ساخت‌وساز کارگرها و اعلان پر شدن مخازن
function updateUserState($username, &$data) {
  if ($username === "Owner") return false;
  if (!isset($data['users'][$username]) || !isset($data['users'][$username]['regions'])) return false;

  $user_info = &$data['users'][$username];
  $now = time();
  $changed = false;

  foreach ($user_info['regions'] as $reg_id => &$reg_data) {
    if (!$reg_data) continue;

    if ($reg_data['type'] === "construction") {
      if ($now >= $reg_data['end_time']) {
        $b_type = $reg_data['building'];
        $reg_data = [
          'type' => $b_type,
          'level' => 1,
          'last_harvest' => $now
        ];
        $w_id = $reg_data['worker_id'] ?? "1";
        if (isset($user_info['workers'][$w_id])) {
          $user_info['workers'][$w_id]['status'] = "idle";
          $user_info['workers'][$w_id]['target_region'] = null;
        }
        $changed = true;

        $xpMap = ['housing' => 2, 'lumber' => 3, 'stone' => 3, 'bakery' => 5, 'market' => 5, 'iron' => 8, 'barracks' => 8];
        $gainedXp = $xpMap[$b_type] ?? 3;
        if (!isset($user_info['xp_vault'])) $user_info['xp_vault'] = ['level_xp' => 0, 'rank_xp' => 0];
        $user_info['xp_vault']['level_xp'] += $gainedXp;

        if (!empty($user_info['chat_id'])) {
          $b_name = BUILDING_STATS[$b_type]['name'];
          tgCall("sendMessage", [
            'chat_id' => $user_info['chat_id'],
            'text' => "🎉 {$b_name} شما در منطقه {$reg_id} ساخته شد."
          ]);
          tgCall("sendMessage", [
            'chat_id' => $user_info['chat_id'],
            'text' => "{$gainedXp} ایکس پی لول به صندوق شما اضافه شد"
          ]);
        }
      }
    } else {
      $stats = BUILDING_STATS[$reg_data['type']] ?? null;
      if ($stats && isset($stats['production_interval']) && !empty($reg_data['notify'])) {
        $level = $reg_data['level'] ?? 1;
        $multiplier = pow(2, $level - 1);
        $max_cap = $stats['capacity'] * $multiplier;
        $accumulated = getAccumulatedResource($reg_data);

        if ($accumulated >= $max_cap && empty($reg_data['notified_full'])) {
          $reg_data['notified_full'] = true;
          $changed = true;

          if (!empty($user_info['chat_id'])) {
            $regNum = (int)$reg_id;
            $cityNum = floor(($regNum - 1) / 10) + 1;
            $localRegionIndex = (($regNum - 1) % 10) + 1;

            $notifyText = "مخزن {$stats['name']} شما پر شده است \nادرس :\nشهر {$cityNum}\nمنطقه {$localRegionIndex}";
            tgCall("sendMessage", [
              'chat_id' => $user_info['chat_id'],
              'text' => $notifyText
            ]);
          }
        }
      }
    }
  }
  return $changed;
}

// بررسی و مدیریت خودکار پایان فصل ۹۰ روزه و اهدای جوایز
function processSeasonEnd(&$data) {
  if (empty($data['season_start'])) $data['season_start'] = time();
  $now = time();
  $elapsed = $now - $data['season_start'];

  if ($elapsed < SEASON_DURATION) return false;

  function getRankIndexHelper($rankName) {
    foreach (RANK_TIERS as $i => $t) {
      if ($t['name'] === $rankName) return $i;
    }
    return 0;
  }

  $userList = [];
  foreach ($data['users'] as $u => &$val) {
    if ($u === "Owner") continue;
    initUserGameData($val);
    $userList[] = [
      'username' => $u,
      'rank' => $val['rank'] ?? "\u{200E}⚪️I",
      'rankIndex' => getRankIndexHelper($val['rank'] ?? "\u{200E}⚪️I"),
      'rankXp' => $val['rank_xp'] ?? 0,
      'createdTimestamp' => $val['created_timestamp'] ?? 0,
      'chat_id' => $val['chat_id'] ?? null
    ];
  }

  usort($userList, function($a, $b) {
    if ($b['rankIndex'] !== $a['rankIndex']) return $b['rankIndex'] - $a['rankIndex'];
    if ($b['rankXp'] !== $a['rankXp']) return $b['rankXp'] - $a['rankXp'];
    return $a['createdTimestamp'] - $b['createdTimestamp'];
  });

  $top10 = array_slice($userList, 0, 10);
  $winner = count($top10) > 0 ? $top10[0] : null;

  $top10Text = "";
  foreach ($top10 as $index => $item) {
    $top10Text .= ($index + 1) . ". {$item['username']} با رنک {$item['rank']} و {$item['rankXp']} ایکس پی رنک\n";
  }
  if (!$top10Text) $top10Text = "هیچ بازیکنی ثبت نشده است.";

  // اعطای جوایز به ۱۰ نفر برتر
  foreach ($top10 as $index => $item) {
    if (isset($data['users'][$item['username']])) {
      $uinfo = &$data['users'][$item['username']];
      $xpReward = ($index === 0) ? 500 : 250;
      $uinfo['level_xp'] = ($uinfo['level_xp'] ?? 0) + $xpReward;

      $reqXp = ($uinfo['level'] ?? 1) + 1;
      while ($uinfo['level_xp'] >= $reqXp) {
        $uinfo['level_xp'] -= $reqXp;
        $uinfo['level'] = ($uinfo['level'] ?? 1) + 1;
        $reqXp = $uinfo['level'] + 1;
      }
    }
  }

  // ارسال پیام پایان فصل به کاربران
  foreach ($data['users'] as $uKey => $uVal) {
    if ($uKey === "Owner" || empty($uVal['chat_id'])) continue;

    if ($winner && $uKey === $winner['username']) {
      $winnerMsg = "{$winner['username']} عزیز\nاین فصل به پایان رسید اما استراتژِ های هوشمندانه و شجاعت تو در میدان نبرد باعث شد که به اوج برسی و با افتخار اعلام میکنم که تو در این فصل صاحب قدرت شدی \n\nو همچنین 10 نفر برتر این فصل :\n{$top10Text}";
      tgCall("sendMessage", ['chat_id' => $uVal['chat_id'], 'text' => $winnerMsg]);
    } else {
      $winnerName = $winner ? $winner['username'] : "نامشخص";
      $winnerRank = $winner ? $winner['rank'] : "⚪️I";
      $winnerXp = $winner ? $winner['rankXp'] : 0;

      $publicMsg = "هم اکنون این فصل به پایان رسید و خسته نباشید ویژه به بازیکنان عزیز که با رقابت های سرسختانه خود را به بالاترین رتبه رساندند \nبرنده این فصل کسی نیست جز {$winnerName} با رنک {$winnerRank} و با {$winnerXp} ایکس پی رنک\n\nو همچنین 10 نفر برتر این فصل :\n{$top10Text}";
      tgCall("sendMessage", ['chat_id' => $uVal['chat_id'], 'text' => $publicMsg]);
    }
  }

  // ریست کردن اطلاعات تمام کاربران (بجز لول و صندوق و پیام‌ها)
  foreach ($data['users'] as $uKey => &$uVal) {
    if ($uKey === "Owner") continue;
    $uVal['rank'] = "\u{200E}⚪️I";
    $uVal['rank_xp'] = 0;
    $uVal['city_count'] = 1;
    $uVal['resources'] = [
      'coin' => 500,
      'iron' => 50,
      'stone' => 100,
      'wood' => 100,
      'bread' => 10,
      'citizen' => 5,
      'force' => 0
    ];
    $uVal['regions'] = [];
    for ($i = 1; $i <= 10; $i++) {
      $uVal['regions'][(string)$i] = null;
    }
    $uVal['workers'] = [
      "1" => ['status' => "idle", 'target_region' => null],
      "2" => ['status' => "idle", 'target_region' => null]
    ];
  }

  $data['season_start'] = time();
  saveData($data);
  return true;
}

// بررسی و انقضای خودکار معاملات ۲ دقیقه‌ای
function checkExpiredTrades(&$data) {
  if (empty($data['trades'])) return false;
  $now = time();
  $changed = false;

  foreach ($data['trades'] as $tradeId => &$trade) {
    if ($trade['status'] === "pending" && $now >= $trade['expire_time']) {
      $trade['status'] = "expired";
      $changed = true;

      $target_info = $data['users'][$trade['target']] ?? null;
      if ($target_info && !empty($target_info['chat_id'])) {
        if (!empty($trade['target_msg_id'])) {
          tgCall("deleteMessage", ['chat_id' => $target_info['chat_id'], 'message_id' => $trade['target_msg_id']]);
        }
        tgCall("sendMessage", [
          'chat_id' => $target_info['chat_id'],
          'text' => "معامله ایی که برای شما فرستادن حذف شد."
        ]);
      }

      if (!empty($trade['sender_chat_id'])) {
        tgCall("sendMessage", [
          'chat_id' => $trade['sender_chat_id'],
          'text' => "معامله شما حذف شد."
        ]);
      }
    }
  }

  if ($changed) {
    saveData($data);
  }
  return $changed;
}

// بررسی بن بودن کاربر و پاسخ متناسب
function checkUserBanAndRespond($chatId, &$data, $messageId = null, $fromCallback = false) {
  $username = null;
  foreach ($data['users'] as $u => $val) {
    if ($val && ($val['chat_id'] ?? null) == $chatId) { $username = $u; break; }
  }
  if (!$username || $username === "Owner") return false;

  $user_info = &$data['users'][$username];
  $ban_info = $user_info['ban'] ?? null;
  if (!$ban_info) return false;

  $ban_type = $ban_info['type'];
  $reason = $ban_info['reason'] ?? "نامشخص";

  if ($ban_type === "temp") {
    $expires_at = $ban_info['expires_at'] ?? 0;
    $now = time();
    if ($now >= $expires_at) {
      unset($user_info['ban']);
      saveData($data);
      if (!empty($ban_info['ban_message_id'])) {
        tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $ban_info['ban_message_id']]);
      }
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "حساب شما باز شده است.",
        'reply_markup' => getUserKeyboard()
      ]);
      return false;
    } else {
      $remaining = (int)($expires_at - $now);
      $timeStr = formatSecondsToDHMS($remaining);
      $text = "حساب شما به صورت موقت بسته شده است\nدلیل : {$reason}\nزمان اتمام : {$timeStr}";
      $markup = ['inline_keyboard' => [[['text' => "بروزرسانی", 'callback_data' => "update_ban_status"]]]];
      renderBanMessage($chatId, $text, $markup, $ban_info, $data, $fromCallback, $messageId);
      return true;
    }
  } else if ($ban_type === "perm") {
    $text = "حساب شما به صورت دائمی بسته شده است\nدلیل : {$reason}";
    $markup = ['inline_keyboard' => [[['text' => "بروزرسانی", 'callback_data' => "update_ban_status"]]]];
    renderBanMessage($chatId, $text, $markup, $ban_info, $data, $fromCallback, $messageId);
    return true;
  }
  return false;
}

// رندر پیام بن و بستن کیبوردهای بازی
function renderBanMessage($chatId, $text, $markup, &$ban_info, &$data, $fromCallback, $messageId) {
  $old_msg_id = $ban_info['ban_message_id'] ?? null;
  if ($fromCallback && $messageId) {
    tgCall("editMessageText", ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'reply_markup' => $markup]);
  } else {
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
    }
    
    if (!empty($data['last_menu_msg'][$chatId])) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $data['last_menu_msg'][$chatId]]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $res = tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $text, 'reply_markup' => $markup]);
    if (!empty($res['ok'])) {
      $ban_info['ban_message_id'] = $res['result']['message_id'];
      saveData($data);
      $rmRes = tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "🚫", 'reply_markup' => ['remove_keyboard' => true]]);
      if (!empty($rmRes['ok'])) {
        tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $rmRes['result']['message_id']]);
      }
    }
  }
}

// ارسال همگانی چالش به تمام بازیکنان فعال
function broadcastChallenge($chalId, &$data) {
  $chal = &$data['challenges'][$chalId];
  if (!$chal) return;

  $inline_keyboard = [
    [['text' => "گزینه 1", 'callback_data' => "c_ans_{$chalId}_1"], ['text' => "گزینه 2", 'callback_data' => "c_ans_{$chalId}_2"]],
    [['text' => "گزینه 3", 'callback_data' => "c_ans_{$chalId}_3"], ['text' => "گزینه 4", 'callback_data' => "c_ans_{$chalId}_4"]]
  ];

  $chal['broadcast_messages'] = [];

  foreach ($data['users'] as $uKey => $uVal) {
    if ($uKey === "Owner" || empty($uVal['chat_id'])) continue;
    $res = tgCall("sendMessage", [
      'chat_id' => $uVal['chat_id'],
      'text' => $chal['text'],
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $chal['broadcast_messages'][] = ['chat_id' => $uVal['chat_id'], 'message_id' => $res['result']['message_id']];
    }
  }
}