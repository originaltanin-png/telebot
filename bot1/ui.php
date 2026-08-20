<?php
/**
 * Wielder of Power - UI, Menus & Keyboard Handlers
 * File: ui.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// کیبورد اصلی کاربر لاگین شده
function getUserKeyboard() {
  return [
    'keyboard' => [
      [['text' => "👤 مشخصات شما 👤"]],
      [['text' => "📖 راهنمای بازی 📖"]],
      [['text' => "🛠️ کارگاه 🛠️"], ['text' => "🏆 برترین ها 🏆"]],
      [['text' => "📩 ارسال پیام 📩"], ['text' => "📦 صندوق ایکس پی 📦"]],
      [['text' => "🎁 دریافت هدیه روزانه 🎁"], ['text' => "🎟️ کد هدیه 🎟️"]],
      [['text' => "🔑 تغییر رمز عبور 🔑"], ['text' => "🚪 خروج از حساب 🚪"]],
      [['text' => "🎧 گزارش به پشتیبانی 🎧"]]
    ],
    'resize_keyboard' => true
  ];
}

// کیبورد پنل مدیریت (Owner)
function getOwnerKeyboard() {
  return [
    'keyboard' => [
      [['text' => "پاسخگویی به گزارشات"], ['text' => "ارسال پیام"]],
      [['text' => "پیام همگانی"], ['text' => "تعداد کاربران"]],
      [['text' => "حذف حساب کاربران"], ['text' => "انتقال منابع نامحدود"]],
      [['text' => "ساخت کد هدیه"], ['text' => "مشاهده هدیه ها"]],
      [['text' => "ساخت چالش"], ['text' => "مشاهده چالش ها"]],
      [['text' => "سطح لول ها"], ['text' => "ban time"]],
      [['text' => "ban"], ['text' => "un ban"]],
      [['text' => "خروج از حساب"]]
    ],
    'resize_keyboard' => true
  ];
}

// کیبورد دکمه انصراف
function getCancelKeyboard() {
  return [
    'keyboard' => [[['text' => "❌ انصراف ❌"]]],
    'resize_keyboard' => true
  ];
}

// رندر منوی اصلی راهنمای بازی
function showGameGuideMenu($chatId, &$data, $messageId = null) {
  $text = "❓ در چه مورد به راهنمایی نیاز دارید ❓";
  $inline_keyboard = [
    [['text' => "❓ چرا باید این بازی رو بازی کنم ❓", 'callback_data' => "guide_why"]],
    [['text' => "🎯 هدف از بازی کردن چیست 🎯", 'callback_data' => "guide_goal"]],
    [['text' => "🚀 از کجا باید شروع کنم 🚀", 'callback_data' => "guide_start"]],
    [['text' => "💬 چگونه با کاربران دیگر ارتباط بگیرم 💬", 'callback_data' => "guide_chat"]],
    [['text' => "🗺️ درمورد منطقه ها برایم توضیح بده 🗺️", 'callback_data' => "guide_regions"]],
    [['text' => "🛡️ چگونه از مناطق خودم دفاع کنم 🛡️", 'callback_data' => "guide_defend"]],
    [['text' => "🔄 چگونه منابع خود را انتقال دهم 🔄", 'callback_data' => "guide_transfer"]],
    [['text' => "🎧 چگونه با پشتیبانی ارتباط برقرار کنم 🎧", 'callback_data' => "guide_support"]],
    [['text' => "⚔️ چگونه به بازیکنان دیگر حمله کنم ⚔️", 'callback_data' => "guide_attack"]],
    [['text' => "🏆 توضیح درمورد رنک ها 🏆", 'callback_data' => "guide_ranks"]],
    [['text' => "⭐ توضیح درمورد لول ⭐", 'callback_data' => "guide_level"]],
    [['text' => "🧩 توضیح درمورد چالش ها 🧩", 'callback_data' => "guide_challenges"]],
    [['text' => "🎟️ توضیح درمورد کد هدیه 🎟️", 'callback_data' => "guide_gifts"]],
    [['text' => "🔥 توضیح درمورد جنگ 🔥", 'callback_data' => "guide_war"]]
  ];

  if ($messageId) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
  } else {
    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
    }

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
  }
}

// نمایش جدول برترین‌ها (Leaderboard)
function showLeaderboard($chatId, &$data, $messageId = null) {
  function getRankIdxLeaderboard($rankName) {
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
      'rankIndex' => getRankIdxLeaderboard($val['rank'] ?? "\u{200E}⚪️I"),
      'rankXp' => $val['rank_xp'] ?? 0,
      'createdTimestamp' => $val['created_timestamp'] ?? 0
    ];
  }

  usort($userList, function($a, $b) {
    if ($b['rankIndex'] !== $a['rankIndex']) {
      return $b['rankIndex'] - $a['rankIndex'];
    }
    if ($b['rankXp'] !== $a['rankXp']) {
      return $b['rankXp'] - $a['rankXp'];
    }
    return $a['createdTimestamp'] - $b['createdTimestamp'];
  });

  $top10 = array_slice($userList, 0, 10);
  $inline_keyboard = [];
  foreach ($top10 as $uItem) {
    $inline_keyboard[] = [['text' => "{$uItem['username']} | {$uItem['rank']}", 'callback_data' => "leaderboard_user_{$uItem['username']}"]];
  }

  $text = "لیست برترین ها :";

  if ($messageId) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
  } else {
    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
    }

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
  }
}

// نمایش مشخصات شما (Profile)
function showUserProfile($chatId, &$data) {
  $username = null;
  foreach ($data['users'] as $u => $val) {
    if ($val && ($val['chat_id'] ?? null) == $chatId) { $username = $u; break; }
  }
  if (!$username) {
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب کاربری خود شوید."]);
    return;
  }

  $user_info = &$data['users'][$username];
  initUserGameData($user_info);
  $res = $user_info['resources'];

  $currentRankReq = 20;
  foreach (RANK_TIERS as $t) {
    if ($t['name'] === $user_info['rank']) {
      $currentRankReq = $t['req'];
      break;
    }
  }

  $reqLevelXp = ($user_info['level'] ?? 1) + 1;

  $text = "
👤 نام حساب شما : {$username}

⭐ لول شما : " . formatNumber($user_info['level']) . "
⭐ ایکس پی لول شما : (" . formatNumber($user_info['level_xp']) . "/" . formatNumber($reqLevelXp) . ")

🏆 رنک شما : {$user_info['rank']}
🏆 ایکس پی رنک شما : (" . formatNumber($user_info['rank_xp']) . "/" . formatNumber($currentRankReq) . ")


📦 منابع موجود شما :
🪙 سکه " . formatNumber($res['coin']) . "
⚙️ آهن " . formatNumber($res['iron']) . "
🪨 سنگ " . formatNumber($res['stone']) . "
🪵 چوب " . formatNumber($res['wood']) . "
🍞 نان " . formatNumber($res['bread']) . "
👥 شهروند " . formatNumber($res['citizen']) . "
";

  $cityCount = $user_info['city_count'] ?? 1;
  $inline_keyboard = [];
  for ($c = 1; $c <= $cityCount; $c++) {
    $inline_keyboard[] = [['text' => "🏛️ شهر {$c} 🏛️", 'callback_data' => "city_view_{$c}"]];
  }
  $inline_keyboard[] = [['text' => "🏗️ ساخت شهر 🏗️", 'callback_data' => "create_city_start"]];
  $inline_keyboard[] = [['text' => "⏳ زمان پایان فصل ⏳", 'callback_data' => "season_time_view"]];

  $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
  if ($old_msg_id) {
    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
  }

  $sendRes = tgCall("sendMessage", [
    'chat_id' => $chatId,
    'text' => trim($text),
    'reply_markup' => ['inline_keyboard' => $inline_keyboard]
  ]);
  if (!empty($sendRes['ok'])) {
    $data['last_menu_msg'][$chatId] = $sendRes['result']['message_id'];
    saveData($data);
  }
}

// رندر گرافیکی و داینامیک منوی یک منطقه
function renderRegionMenu($chatId, $regId, &$data, $messageId = null) {
  $username = null;
  foreach ($data['users'] as $u => $val) {
    if ($val && ($val['chat_id'] ?? null) == $chatId) { $username = $u; break; }
  }
  if (!$username) return;

  $user_info = &$data['users'][$username];
  $reg = &$user_info['regions'][(string)$regId];

  $cityNum = floor(((int)$regId - 1) / 10) + 1;
  $backCallback = "city_view_{$cityNum}";

  if ($reg && $reg['type'] !== "construction" && $reg['type'] !== "market" && empty($reg['last_harvest'])) {
    $reg['last_harvest'] = time();
    saveData($data);
  }

  // 0. Ruin Region (خرابه)
  if ($reg && $reg['type'] === "ruin") {
    $rebuildCost = 200 * ($reg['ruin_multiplier'] ?? 1);
    $text = "🏚️ این منطقه به خرابه تبدیل شده است \n🪙 هزینه بازسازی این منطقه : " . formatNumber($rebuildCost) . " سکه";
    $inline_keyboard = [
      [['text' => "💰 پرداخت 💰", 'callback_data' => "rebuild_ruin_{$regId}"]],
      [['text' => "🔙 بازگشت 🔙", 'callback_data' => $backCallback]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  // 1. Empty Region
  if (!$reg) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "🔲 این منطقه خالی میباشد \n🛠️ برای ساخت و ساز و پر کردن این منطقه به بخش کارگاه مراجعه کنید.",
      'reply_markup' => ['inline_keyboard' => [[['text' => "🔙 بازگشت 🔙", 'callback_data' => $backCallback]]]]
    ]);
    return;
  }

  // 2. Under Construction Region
  if ($reg['type'] === "construction") {
    $b_name = BUILDING_STATS[$reg['building']]['name'];
    $remaining = max(0, (int)($reg['end_time'] - time()));
    $time_str = formatSecondsToDHMS($remaining);

    $text = "🏗️ این منطقه درحال ساخت و ساز {$b_name} است\n⏳ زمان پایان : \n{$time_str}";
    $inline_keyboard = [
      [['text' => "🔄 بروزرسانی پیام 🔄", 'callback_data' => "const_update_{$regId}"]],
      [['text' => "❌ لغو ساخت و ساز ❌", 'callback_data' => "const_cancel_{$regId}"]],
      [['text' => "🔙 بازگشت 🔙", 'callback_data' => $backCallback]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  // 2.5 Built Region - Barracks (پادگان)
  if ($reg['type'] === "barracks") {
    $level = $reg['level'] ?? 1;
    $stats = BUILDING_STATS['barracks'];
    $costText = "";
    if ($level < 10) {
      $nextCost = [
        'coin' => $stats['build_cost']['coin'] * pow(2, $level),
        'citizen' => $stats['build_cost']['citizen'] * pow(2, $level),
        'wood' => $stats['build_cost']['wood'] * pow(2, $level),
        'stone' => $stats['build_cost']['stone'] * pow(2, $level)
      ];
      $costText = "\n\n🛠️ هزینه ارتقا به سطح بعدی (" . ($level + 1) . ") :\n🪙 سکه " . formatNumber($nextCost['coin']) . "\n👥 شهروند " . formatNumber($nextCost['citizen']) . "\n🪵 چوب " . formatNumber($nextCost['wood']) . "\n🪨 سنگ " . formatNumber($nextCost['stone']);
    } else {
      $costText = "\n\n🏰 پادگان در حداکثر سطح (۱۰) قرار دارد.";
    }

    $text = "🏰 به پادگان خوش آمدید\n\n📊 سطح : {$level}{$costText}";
    $inline_keyboard = [
      [['text' => "⚔️ ساخت نیرو ⚔️", 'callback_data' => "barracks_recruit_start_{$regId}"]],
      [['text' => "⬆️ ارتقا پادگان ⬆️", 'callback_data' => "upgrade_{$regId}"]],
      [['text' => "🛡️ نیروهای مستقر شده 🛡️", 'callback_data' => "troops_{$regId}"]],
      [['text' => "💥 حمله 💥", 'callback_data' => "attack_start_{$regId}"]],
      [['text' => "🔄 جابجایی سازه 🔄", 'callback_data' => "relocate_start_{$regId}"]],
      [['text' => "💣 تخریب سازه 💣", 'callback_data' => "demolish_start_{$regId}"]],
      [['text' => "🔙 بازگشت 🔙", 'callback_data' => $backCallback]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  // 3. Built Region - Market
  if ($reg['type'] === "market") {
    $text = "🏪 به بازار خوش آمدید\n🛒 برای خرید یا فروش منابع روی دکمه های زیر کلیک کنید";
    $inline_keyboard = [
      [['text' => "⚙️ خرید آهن (۱۵ سکه) 🪙", 'callback_data' => "market_buy_iron_{$regId}"], ['text' => "⚙️ فروش آهن (۱۰ سکه) 🪙", 'callback_data' => "market_sell_iron_{$regId}"]],
      [['text' => "🪨 خرید سنگ (۸ سکه) 🪙", 'callback_data' => "market_buy_stone_{$regId}"], ['text' => "🪨 فروش سنگ (۵ سکه) 🪙", 'callback_data' => "market_sell_stone_{$regId}"]],
      [['text' => "🪵 خرید چوب (۶ سکه) 🪙", 'callback_data' => "market_buy_wood_{$regId}"], ['text' => "🪵 فروش چوب (۴ سکه) 🪙", 'callback_data' => "market_sell_wood_{$regId}"]],
      [['text' => "🍞 خرید نان (۵ سکه) 🪙", 'callback_data' => "market_buy_bread_{$regId}"], ['text' => "🍞 فروش نان (۳ سکه) 🪙", 'callback_data' => "market_sell_bread_{$regId}"]],
      [['text' => "🔄 انتقال منابع 🔄", 'callback_data' => "market_transfer_{$regId}"]],
      [['text' => "🛡️ نیروهای مستقر شده 🛡️", 'callback_data' => "market_troops_{$regId}"]],
      [['text' => "🔄 جابجایی سازه 🔄", 'callback_data' => "relocate_start_{$regId}"]],
      [['text' => "💣 تخریب سازه 💣", 'callback_data' => "demolish_start_{$regId}"]],
      [['text' => "🔙 بازگشت 🔙", 'callback_data' => $backCallback]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  // 4. Built Region - Production Mines & Housing
  $stats = BUILDING_STATS[$reg['type']];
  $level = $reg['level'] ?? 1;
  $multiplier = pow(2, $level - 1);

  if (empty($reg['last_harvest'])) {
    $reg['last_harvest'] = time();
  }

  $accumulated = getAccumulatedResource($reg);
  $prod_amount = $stats['production_amount'] * $multiplier;
  $max_cap = $stats['capacity'] * $multiplier;

  if ($accumulated >= $max_cap) {
    $remaining_prod_time = 0;
  } else {
    $elapsed = time() - $reg['last_harvest'];
    $elapsed_in_current_cycle = $elapsed % $stats['production_interval'];
    $remaining_prod_time = max(0, (int)($stats['production_interval'] - $elapsed_in_current_cycle));
  }
  $prod_countdown_str = formatSecondsToDHMS($remaining_prod_time);

  $next_up_cost = [
    'coin' => $stats['build_cost']['coin'] * pow(2, $level),
    'citizen' => $stats['build_cost']['citizen'] * pow(2, $level),
    'wood' => $stats['build_cost']['wood'] * pow(2, $level),
    'stone' => $stats['build_cost']['stone'] * pow(2, $level)
  ];

  $notifyStatusText = !empty($reg['notify']) ? "🔔 وضعیت اعلان : روشن 🔔" : "🔕 وضعیت اعلان : خاموش 🔕";

  $text = "به {$stats['name']} خوش آمدید

📊 سطح : {$level}
⏱️ زمان سود دهی:
" . ($stats['production_interval'] / 60) . " دقیقه 

💰 مقدار سودهی :
" . formatNumber($prod_amount) . " {$stats['resource_name']}

⏳ اتمام زمان سودهی :
{$prod_countdown_str}

📦 ظرفیت : (" . formatNumber($accumulated) . "/" . formatNumber($max_cap) . ")

🛠️ هزینه ارتقا به سطح بعدی :
🪙 سکه " . formatNumber($next_up_cost['coin']) . "
👥 شهروند " . formatNumber($next_up_cost['citizen']) . "
🪵 چوب " . formatNumber($next_up_cost['wood']) . "
🪨 سنگ " . formatNumber($next_up_cost['stone']) . "

📈 سودهی لول بعد :
" . formatNumber($prod_amount * 2) . "
📦 ظرفیت لول بعد :
" . formatNumber($max_cap * 2);

  $inline_keyboard = [
    [['text' => "🌾 برداشت 🌾", 'callback_data' => "harvest_{$regId}"], ['text' => "⬆️ ارتقا ⬆️", 'callback_data' => "upgrade_{$regId}"]],
    [['text' => "🔄 بروزرسانی زمان سود 🔄", 'callback_data' => "region_click_{$regId}"]],
    [['text' => $notifyStatusText, 'callback_data' => "toggle_notify_{$regId}"]],
    [['text' => "🛡️ نیروهای مستقر شده 🛡️", 'callback_data' => "troops_{$regId}"]],
    [['text' => "🔄 جابجایی سازه 🔄", 'callback_data' => "relocate_start_{$regId}"]],
    [['text' => "💣 تخریب سازه 💣", 'callback_data' => "demolish_start_{$regId}"]],
    [['text' => "🔙 بازگشت 🔙", 'callback_data' => $backCallback]]
  ];

  tgCall("editMessageText", [
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'text' => $text,
    'reply_markup' => ['inline_keyboard' => $inline_keyboard]
  ]);
}

// نمایش لیست سازه‌ها برای ساخت توسط کارگر
function showBuildingsList($chatId, $workerId, &$data, $messageId) {
  $inline_keyboard = [];
  foreach (BUILDING_STATS as $key => $stats) {
    $inline_keyboard[] = [['text' => $stats['name'], 'callback_data' => "build_desc_{$key}_{$workerId}"]];
  }
  $inline_keyboard[] = [['text' => "🔙 بازگشت 🔙", 'callback_data' => "back_to_workers"]];

  tgCall("editMessageText", [
    'chat_id' => $chatId,
    'message_id' => $messageId,
    'text' => "لطفا یکی از سازه های زیر را انتخاب کنید",
    'reply_markup' => ['inline_keyboard' => $inline_keyboard]
  ]);
}

// نمایش چالش‌های فعال برای پنل مالک
function showOwnerChallenges($chatId, &$data) {
  $challenges = $data['challenges'] ?? [];
  $activeIds = array_filter(array_keys($challenges), function($id) use ($challenges) {
    return isset($challenges[$id]) && ($challenges[$id]['status'] ?? '') !== "expired";
  });

  if (empty($activeIds)) {
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "هیچ چالشی وجود ندارد"]);
    return;
  }

  foreach ($activeIds as $id) {
    $chal = $challenges[$id];
    $stats = $chal['stats'] ?? ['opt1' => 0, 'opt2' => 0, 'opt3' => 0, 'opt4' => 0];
    $msgText = "متن چالش :\n{$chal['text']}\n\nگزینه درست : گزینه {$chal['correct_option']}\nتاریخ ساخت چالش (به شمسی): {$chal['created_date']}\nساعت ساخت چالش : {$chal['created_time']}\n\nتعداد افرادی که گزینه 1 را انتخاب کردن : " . ($stats['opt1'] ?? 0) . "\nتعداد افرادی که گزینه 2 را انتخاب کردن : " . ($stats['opt2'] ?? 0) . "\nتعداد افرادی که گزینه 3 را انتخاب کردن : " . ($stats['opt3'] ?? 0) . "\nتعداد افرادی که گزینه 4 را انتخاب کردن : " . ($stats['opt4'] ?? 0);

    $inline_keyboard = [
      [['text' => "منقضی کردن", 'callback_data' => "chal_exp_ask_{$chal['id']}"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $msgText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
  }
}

// نمایش کدهای هدیه فعال برای پنل مالک
function showOwnerGifts($chatId, &$data) {
  $gifts = $data['gifts'] ?? [];
  $activeCodes = array_filter(array_keys($gifts), function($c) use ($gifts) {
    return isset($gifts[$c]) && ($gifts[$c]['status'] ?? '') !== "expired";
  });

  if (empty($activeCodes)) {
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "هیچ هدیه ایی وجود ندارد"]);
    return;
  }

  $nameMap = ['level_xp' => "ایکس پی لول", 'coin' => "سکه", 'stone' => "سنگ", 'iron' => "آهن", 'wood' => "چوب", 'bread' => "نان"];
  foreach ($activeCodes as $code) {
    $gift = $gifts[$code];
    $itemsText = "";
    foreach (($gift['items'] ?? []) as $item) {
      $name = $nameMap[$item['res']] ?? $item['res'];
      $itemsText .= "{$name} : " . formatNumber($item['amt']) . "\n";
    }
    $msgText = "کد هدیه :\n{$gift['code']}\n\nتاریخ ساخت (به شمسی) : {$gift['created_date']}\nساعت ساخت : {$gift['created_time']}\n\nهدیه ها (ایتم ها و تعدادشون):\n" . trim($itemsText) . "\n\nتعداد افرادی که هدیه را دریافت کردن (یعنی کاربرایی که کد هدیه رو وارد کردن): " . (isset($gift['claimed_users']) ? count($gift['claimed_users']) : 0);

    $inline_keyboard = [
      [['text' => "منقضی کردن هدیه", 'callback_data' => "gift_exp_ask_{$gift['code']}"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $msgText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
  }
}
