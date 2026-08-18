<?php
/**
 * Wielder of Power - Inline Keyboard & Callback Query Handler
 * File: callbacks.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';

function handleCallback($call, &$data) {
  $chatId = $call['message']['chat']['id'];
  $messageId = $call['message']['message_id'];
  $cbData = $call['data'];

  // بروزرسانی بن
  if ($cbData === "update_ban_status") {
    $stillBanned = checkUserBanAndRespond($chatId, $data, $messageId, true);
    tgCall("answerCallbackQuery", [
      'callback_query_id' => $call['id'],
      'text' => $stillBanned ? "پیام بروزرسانی شد." : "محرومیت شما به پایان رسیده است!"
    ]);
    return;
  }

  // ۱. دکمه‌های ثبت نام، لاگین و راهنمای مهمان
  if ($cbData === "btn_register") {
    $existingUser = null;
    foreach ($data['users'] as $uKey => $uVal) {
      if ($uKey !== "Owner" && $uVal && (($uVal['registered_chat_id'] ?? null) == $chatId || ($uVal['chat_id'] ?? null) == $chatId)) {
        $existingUser = $uKey;
        break;
      }
    }

    if ($existingUser) {
      tgCall("answerCallbackQuery", [
        'callback_query_id' => $call['id'],
        'text' => "شما قبلاً یک حساب کاربری ساخته‌اید و مجاز به ساخت حساب جدید نیستید.",
        'show_alert' => true
      ]);
      return;
    }

    $data['user_states'][$chatId] = "reg_user";
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا نام کاربری خود را وارد کنید :\n(اگه نام کاربری شما دارای فحاشی باشد حساب شما توسط ادمین حذف میگردد)",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "btn_login") {
    $data['user_states'][$chatId] = "login_user";
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا نام کاربری خود را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "btn_help") {
    $helpText = "📖 راهنمای ثبت نام و ورود به بازی:\n\nبرای شروع بازی می‌توانید از گزینه‌های زیر استفاده کنید:\n\n۱) ثبت نام:\n- اگر برای اولین بار وارد بازی شده‌اید، روی دکمه «ثبت نام» کلیک کنید.\n- یک نام کاربری انگلیسی (بین ۴ تا ۱۰ کاراکتر) انتخاب و ارسال کنید.\n- سپس یک رمز عبور تعیین کرده و تکرار آن را جهت تایید وارد نمایید.\n\n۲) ورود:\n- اگر قبلاً حساب کاربری ساخته‌اید، روی دکمه «ورود» کلیک کنید.\n- نام کاربری و رمز عبور خود را وارد نمایید تا وارد بازی شوید.";

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $helpText,
      'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "btn_back_to_main"]]]]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "btn_back_to_main") {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
      'reply_markup' => [
        'inline_keyboard' => [
          [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
          [['text' => "راهنما", 'callback_data' => "btn_help"]]
        ]
      ]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // ۲. احراز هویت کاربر برای ادامه اکشن‌ها
  $username = null;
  foreach ($data['users'] as $u => $val) {
    if ($val && ($val['chat_id'] ?? null) == $chatId) { $username = $u; break; }
  }
  if (!$username) {
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "ابتدا وارد شوید."]);
    return;
  }
  $user_info = &$data['users'][$username];
  initUserGameData($user_info);

  // --- منوی راهنمای بازی ---
  if ($cbData === "guide_menu_main") {
    showGameGuideMenu($chatId, $data, $messageId);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "guide_") === 0) {
    $guideTexts = [
      'guide_why' => "🎮 این بازی بهترین سرگرمی برای اوقات فراغت شما است وقتی منتظر پاسخی در تلگرام هستید یا میخواهید چند دقیقه ای از کارهای روزمره فاصله بگیرید میتوانید وارد بازی شوید و قلمرو خود را مدیریت کنید سادگی بازی در کنار رقابت هیجان انگیز باعث میشود بدون هیچ دردسری لذت ببرید و در زمان های خالی روز خود سرگرم شوید 🎮",
      'guide_goal' => "🎯 هدف اصلی افزایش اقتدار در قلمرو و رسیدن به رتبه اول در جدول برترین ها است شما با ارتقای سازه ها دریافت ایکس پی و کسب رنک های بالاتر قدرت خود را به بقیه بازیکنان نشان میدهید همچنین در پایان هر فصل به نفرات برتر جوایز ارزشمندی داده میشود که شما را در فصل های بعدی جلو می اندازد 🏆",
      'guide_start' => "🚀 در شروع کار بهتر است ابتدا وارد بخش کارگاه شوید و کارگرهای خود را فعال کنید ساخت کارگاه چوب بری و معدن سنگ اولین قدم برای جمع آوری منابع اولیه است بعد از آن میتوانید نانوایی و مسکن بسازید تا نان و شهروند کافی برای ساخت نیروهای نظامی داشته باشید 🛠️",
      'guide_chat' => "💬 برای گفتگو با سایر بازیکنان میتوانید از دکمه ارسال پیام در کیبورد اصلی استفاده کنید با وارد کردن نام کاربری فرد مورد نظر پیام شما برای او فرستاده میشود و او میتواند مستقیم به شما پاسخ دهد تمامی پیام های شما به صورت محرمانه بین شما و کاربر مقابل تبادل میگردد 📩",
      'guide_regions' => "🗺️ هر شهر دارای ۱۰ منطقه مختلف است که شما میتوانید در هر منطقه یک سازه دلخواه بسازید این منطقه ها محل استقرار معادن بازار پادگان و بقیه بخش های حیاتی قلمرو شما هستند شما میتوانید سازه های خود را بین مناطق جابجا کنید یا در صورت نیاز آنها را تخریب کنید 🏰",
      'guide_defend' => "🛡️ برای دفاع از مناطق خود باید در پادگان نیروهای نظامی بسازید نیروهای ساخته شده در همان منطقه مستقر میشوند و اگر بازیکنی به آن منطقه حمله کند نیروهای شما به صورت خودکار دفاع میکنند هرچه نیروهای قوی تری داشته باشید احتمال پیروزی شما در دفاع بیشتر میشود ⚔️",
      'guide_transfer' => "🔄 برای انتقال یا معامله منابع با سایرین ابتدا باید سازه بازار را در یکی از مناطق خود ساخته باشید پس از آن با رفتن به بخش بازار میتوانید دکمه انتقال منابع یا معامله را بزنید و مقدار مورد نظر خود را برای کاربر دیگری ارسال کنید 🏪",
      'guide_support' => "🎧 اگر با مشکلی روبرو شدید یا سوالی داشتید میتوانید روی دکمه گزارش به پشتیبانی بزنید و متن خود را بفرستید گزارش شما ثبت میشود و ادمین های بازی در سریع ترین زمان ممکن بررسی های لازم را انجام داده و پاسخ را مستقیم برای شما ارسال میکنند ✉️",
      'guide_attack' => "⚔️ برای حمله به دیگران ابتدا باید در پادگان خود نیرو ساخته باشید سپس با ورود به پادگان دکمه حمله را بزنید شما میتوانید به یک کاربر مشخص یا به صورت رندوم حمله کنید در صورت پیروزی در نبرد منابع منطقه حریف را غارت میکنید و ایکس پی رنک به دست می آورید 💥",
      'guide_ranks' => "🏆 رنک ها نشان دهنده میزان پیشرفت و قدرت شما در بازی هستند رنک ها از رتبه های پایین شروع شده و تا رتبه های بالاتر ادامه پیدا میکنند با شرکت در نبردها ایکس پی رنک کسب میکنید و رنک شما ارتقا مییابد در پایان هر فصل نیز بر اساس رنک به برترین ها جایزه داده میشود 🌟\n\nلیست رنک ها :\n🟠V\n🟠IV\n🟠III\n🟠II\n🟠I\n🟣V\n🟣IV\n🟣III\n🟣II\n🟣I\n🔵V\n🔵IV\n🔵III\n🔵II\n🔵I\n🟢V\n🟢IV\n🟢III\n🟢II\n🟢I\n⚪V\n⚪IV\n⚪III\n⚪II\n⚪I",
      'guide_level' => "⭐ لول شما نشان دهنده میزان فعالیت کل شما در بازی است با ساخت و ارتقای سازه ها ایکس پی لول به دست می آورید این ایکس پی ها در صندوق ایکس پی ذخیره میشوند و پس از دریافت لول شما را بالا میبرند بر خلاف رنک لول شما در پایان فصل ها ریست نمیشود 📦",
      'guide_challenges' => "🧩 چالش ها سوالات و مسابقاتی هستند که در طول بازی به صورت همگانی برای همه بازیکنان فرستاده میشوند با پاسخ درست به چالش ها میتوانید جوایز عالی مانند سکه منابع و ایکس پی لول به دست آورید پس همیشه گوش به زنگ باشید 🎁",
      'guide_gifts' => "🎟️ کد هدیه کدهایی هستند که توسط مدیریت بازی ساخته میشوند شما با زدن دکمه کد هدیه و وارد کردن این کدها میتوانید جوایز رایگانی مثل سکه منابع یا ایکس پی لول دریافت کنید هر کد هدیه یک بار برای هر کاربر قابل استفاده است 🎁",
      'guide_war' => "🔥 جنگ ها بر اساس مجموع قدرت استقامت و انرژی نیروهای دو طرف محاسبه میشوند اگر قدرت دفاعی حریف از قدرت حمله شما بیشتر باشد شکست میخورید و نیروهایتان از دست میروند اما اگر پیروز شوید سازه حریف تخریب شده و منابع موجود در آن به غنیمت شما در می آید ⚔️"
    ];

    if (isset($guideTexts[$cbData])) {
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $guideTexts[$cbData],
        'reply_markup' => ['inline_keyboard' => [[['text' => "🔙 بازگشت 🔙", 'callback_data' => "guide_menu_main"]]]]
      ]);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- نمایش زمان پایان فصل ---
  if ($cbData === "season_time_view") {
    if (empty($data['season_start'])) $data['season_start'] = time();
    $now = time();
    $remaining = max(0, (int)(($data['season_start'] + SEASON_DURATION) - $now));
    $time_str = formatSecondsToDHMS($remaining);

    $text = "زمان باقی مانده تا پایان فصل {$time_str}";
    $inline_keyboard = [
      [['text' => "بروزرسانی زمان", 'callback_data' => "season_time_view"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "زمان بروزرسانی شد."]);
    return;
  }

  // --- منوی برترین‌ها ---
  if ($cbData === "leaderboard_main") {
    showLeaderboard($chatId, $data, $messageId);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "leaderboard_user_") === 0) {
    $targetUser = substr($cbData, strlen("leaderboard_user_"));
    $targetInfo = $data['users'][$targetUser] ?? null;
    if ($targetInfo) {
      initUserGameData($targetInfo);
      $text = "نام کاربری : {$targetUser}\nتاریخ ثبت نام  : " . ($targetInfo['reg_date'] ?? "نامشخص") . "\nتاریخ ورود : " . ($targetInfo['login_date'] ?? "نامشخص") . "\nساعت ثبت نام : " . ($targetInfo['reg_time'] ?? "نامشخص") . "\nساعت ورود : " . ($targetInfo['login_time'] ?? "نامشخص") . "\nلول : " . ($targetInfo['level'] ?? 1);
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "leaderboard_main"]]]]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "کاربر یافت نشد."]);
    }
    return;
  }

  // --- منوی صندوق ایکس پی ---
  if ($cbData === "vault_main") {
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
    $inline_keyboard = [
      [['text' => "⭐ ایکس پی لول ⭐", 'callback_data' => "vault_view_level"], ['text' => "🏆 ایکس پی رنک 🏆", 'callback_data' => "vault_view_rank"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "📦 صندوق مورد نظر خود را انتخاب کنید",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "vault_view_level") {
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
    $text = "⭐ ایکس پی لول شما : " . ($user_info['xp_vault']['level_xp'] ?? 0);
    $inline_keyboard = [
      [['text' => "🎁 دریافت 🎁", 'callback_data' => "vault_claim_level"]],
      [['text' => "🔙 بازگشت 🔙", 'callback_data' => "vault_main"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "vault_view_rank") {
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
    $text = "🏆 ایکس پی رنک شما : " . ($user_info['xp_vault']['rank_xp'] ?? 0);
    $inline_keyboard = [
      [['text' => "🎁 دریافت 🎁", 'callback_data' => "vault_claim_rank"]],
      [['text' => "🔙 بازگشت 🔙", 'callback_data' => "vault_main"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "vault_claim_level") {
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
    $claimedXp = $user_info['xp_vault']['level_xp'] ?? 0;

    if ($claimedXp <= 0) {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ هیچ ایکس پی لولی در صندوق شما وجود ندارد.", 'show_alert' => true]);
      return;
    }

    $user_info['level'] = $user_info['level'] ?? 1;
    $user_info['level_xp'] = ($user_info['level_xp'] ?? 0) + $claimedXp;
    $user_info['xp_vault']['level_xp'] = 0;

    $reqXp = $user_info['level'] + 1;
    while ($user_info['level_xp'] >= $reqXp) {
      $user_info['level_xp'] -= $reqXp;
      $user_info['level'] += 1;
      $reqXp = $user_info['level'] + 1;
    }

    saveData($data);

    $updatedText = "مقدار {$claimedXp} ایکس پی لول به حساب شما اضافه شد";
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $updatedText,
      'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "vault_main"]]]]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "vault_claim_rank") {
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
    $claimedXp = $user_info['xp_vault']['rank_xp'] ?? 0;

    if ($claimedXp <= 0) {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ هیچ ایکس پی رنکی در صندوق شما وجود ندارد.", 'show_alert' => true]);
      return;
    }

    $currentTierIdx = 0;
    foreach (RANK_TIERS as $i => $t) {
      if ($t['name'] === $user_info['rank']) {
        $currentTierIdx = $i;
        break;
      }
    }

    $user_info['rank_xp'] = ($user_info['rank_xp'] ?? 0) + $claimedXp;
    $user_info['xp_vault']['rank_xp'] = 0;

    while ($currentTierIdx < count(RANK_TIERS) - 1 && $user_info['rank_xp'] >= RANK_TIERS[$currentTierIdx]['req']) {
      $user_info['rank_xp'] -= RANK_TIERS[$currentTierIdx]['req'];
      $currentTierIdx++;
      $user_info['rank'] = RANK_TIERS[$currentTierIdx]['name'];
    }

    if ($currentTierIdx === count(RANK_TIERS) - 1 && $user_info['rank_xp'] > RANK_TIERS[$currentTierIdx]['req']) {
      $user_info['rank_xp'] = RANK_TIERS[$currentTierIdx]['req'];
    }

    saveData($data);

    $updatedText = "مقدار {$claimedXp} ایکس پی رنک به حساب شما اضافه شد";
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $updatedText,
      'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "vault_main"]]]]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- مدیریت شهرها و ساخت شهر ---
  if (strpos($cbData, "city_view_") === 0) {
    $cityNum = (int)substr($cbData, strlen("city_view_"));
    $startReg = ($cityNum - 1) * 10 + 1;
    $endReg = $cityNum * 10;

    $cityTroops = [];
    for ($i = $startReg; $i <= $endReg; $i++) {
      $reg = $user_info['regions'][(string)$i] ?? null;
      if ($reg && !empty($reg['troops'])) {
        foreach ($reg['troops'] as $tKey => $count) {
          $cityTroops[$tKey] = ($cityTroops[$tKey] ?? 0) + $count;
        }
      }
    }

    $cStats = calculateTroopStats($cityTroops);
    $text = "قدرت در این شهر : {$cStats['power']}\nاستقامت در این شهر : {$cStats['stamina']}\nانرژی در این شهر : {$cStats['energy']}";

    $inline_keyboard = [];
    for ($i = $startReg; $i <= $endReg; $i++) {
      $reg = $user_info['regions'][(string)$i] ?? null;
      $localIndex = $i - $startReg + 1;
      $btnText = "{$localIndex}";
      if ($reg) {
        if ($reg['type'] === "ruin") {
          $btnText .= " (خرابه)";
        } else if ($reg['type'] === "construction") {
          $btnText .= " (درحال ساخت " . BUILDING_STATS[$reg['building']]['name'] . ")";
        } else {
          $btnText .= " (" . (BUILDING_STATS[$reg['type']]['name'] ?? $reg['type']) . ")";
        }
      }
      $inline_keyboard[] = [['text' => $btnText, 'callback_data' => "region_click_{$i}"]];
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "back_to_profile"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "create_city_start") {
    $currentCityCount = $user_info['city_count'] ?? 1;
    $nextCity = $currentCityCount + 1;
    $cost = 10000 * pow(2, $nextCity - 2);

    $confirmText = "هزینه ساخت شهر{$nextCity} : " . formatNumber($cost) . " سکه\nآیا از کار خود مطمن هستید ؟";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "create_city_confirm_yes_{$nextCity}"], ['text' => "خیر", 'callback_data' => "create_city_confirm_no"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $confirmText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "create_city_confirm_no") {
    showUserProfile($chatId, $data);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "create_city_confirm_yes_") === 0) {
    $nextCity = (int)substr($cbData, strlen("create_city_confirm_yes_"));
    $cost = 10000 * pow(2, $nextCity - 2);

    if (($user_info['resources']['coin'] ?? 0) >= $cost) {
      $user_info['resources']['coin'] -= $cost;
      $user_info['city_count'] = $nextCity;

      $startReg = ($nextCity - 1) * 10 + 1;
      $endReg = $nextCity * 10;
      for ($i = $startReg; $i <= $endReg; $i++) {
        if (!isset($user_info['regions'][(string)$i])) {
          $user_info['regions'][(string)$i] = null;
        }
      }

      $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
      $user_info['xp_vault']['level_xp'] += 25;

      saveData($data);

      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "25 ایکس پی لول به صندوق شما اضافه شد"]);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "شهر {$nextCity} با موفقیت ساخته شد",
        'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت به مشخصات شما", 'callback_data' => "back_to_profile"]]]]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ سکه کافی ندارید.", 'show_alert' => true]);
    }
    return;
  }

  if (strpos($cbData, "region_click_") === 0) {
    $regId = substr($cbData, strlen("region_click_"));
    renderRegionMenu($chatId, $regId, $data, $messageId);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "const_update_") === 0) {
    $regId = substr($cbData, strlen("const_update_"));
    renderRegionMenu($chatId, $regId, $data, $messageId);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "پیام بروزرسانی شد."]);
    return;
  }

  if (strpos($cbData, "const_cancel_") === 0) {
    $regId = substr($cbData, strlen("const_cancel_"));
    $b_name = BUILDING_STATS[$user_info['regions'][$regId]['building']]['name'];
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "const_cancel_confirm_yes_{$regId}"], ['text' => "خیر", 'callback_data' => "const_cancel_confirm_no_{$regId}"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "آیا میخواهید ساخت سازه {$b_name} لغو شود؟ نصف منابع پرداختی به شما برگشت داده خواهد شد.",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "const_cancel_confirm_yes_") === 0) {
    $regId = substr($cbData, strlen("const_cancel_confirm_yes_"));
    $reg = $user_info['regions'][$regId] ?? null;
    if ($reg && $reg['type'] === "construction") {
      $b_type = $reg['building'];
      $costs = BUILDING_STATS[$b_type]['build_cost'];
      
      $user_info['resources']['coin'] += floor($costs['coin'] / 2);
      $user_info['resources']['citizen'] += floor($costs['citizen'] / 2);
      $user_info['resources']['wood'] += floor($costs['wood'] / 2);
      $user_info['resources']['stone'] += floor($costs['stone'] / 2);
      $user_info['resources']['iron'] += floor($costs['iron'] / 2);

      $w_id = $reg['worker_id'] ?? "1";
      if (isset($user_info['workers'][$w_id])) {
        $user_info['workers'][$w_id]['status'] = "idle";
        $user_info['workers'][$w_id]['target_region'] = null;
      }
      $user_info['regions'][$regId] = null;
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "ساخت و ساز لغو شد و نصف منابع به شما عودت گردید.",
        'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت به پروفایل", 'callback_data' => "back_to_profile"]]]]
      ]);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "const_cancel_confirm_no_") === 0) {
    $regId = substr($cbData, strlen("const_cancel_confirm_no_"));
    renderRegionMenu($chatId, $regId, $data, $messageId);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "rebuild_ruin_") === 0) {
    $regId = substr($cbData, strlen("rebuild_ruin_"));
    $reg = $user_info['regions'][$regId] ?? null;
    $rebuildCost = 200 * ($reg ? ($reg['ruin_multiplier'] ?? 1) : 1);
    if (($user_info['resources']['coin'] ?? 0) >= $rebuildCost) {
      $user_info['resources']['coin'] -= $rebuildCost;
      $user_info['regions'][$regId] = null;
      saveData($data);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "✅ منطقه با موفقیت بازسازی و خالی گردید."]);
      renderRegionMenu($chatId, $regId, $data, $messageId);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ سکه شما برای بازسازی کافی نیست.", 'show_alert' => true]);
    }
    return;
  }

  // --- سیستم کامل حمله و نبرد (Attack Engine) ---
  if (strpos($cbData, "attack_start_") === 0) {
    $regId = substr($cbData, strlen("attack_start_"));
    $reg = $user_info['regions'][$regId] ?? null;

    $now = time();
    $lastAttack = $reg ? ($reg['last_attack_time'] ?? 0) : 0;
    $elapsed = $now - $lastAttack;
    $remaining = max(0, (int)(ATTACK_COOLDOWN - $elapsed));

    if ($lastAttack > 0 && $remaining > 0) {
      $text = "شما به محدودیت حمله برخوردید \nزمان اتمام محدودیت شما : " . formatSecondsToDHMS($remaining);
      $inline_keyboard = [
        [['text' => "بروزسانی زمان", 'callback_data' => "attack_limit_update_{$regId}"]],
        [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]
      ];
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
      return;
    }

    $troopCountTotal = 0;
    if ($reg && !empty($reg['troops'])) {
      foreach (TROOP_STATS as $tKey => $tData) {
        $count = $reg['troops'][$tKey] ?? 0;
        if ($count > 0) $troopCountTotal += $count;
      }
    }

    if ($troopCountTotal <= 0) {
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "شما نیرویی در این منطقه ندارید",
        'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]]]
      ]);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
      return;
    }

    $text = "به چه صورت میخواهید حمله کنید ؟";
    $inline_keyboard = [
      [['text' => "حمله به شخص مورد نظر", 'callback_data' => "atk_type_target_{$regId}"]],
      [['text' => "حمله به شخص رندوم (رنکینگ)", 'callback_data' => "atk_type_random_{$regId}"]],
      [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "atk_type_target_") === 0 || strpos($cbData, "atk_type_random_") === 0) {
    $isRandom = strpos($cbData, "atk_type_random_") === 0;
    $regId = $isRandom ? substr($cbData, strlen("atk_type_random_")) : substr($cbData, strlen("atk_type_target_"));
    $reg = $user_info['regions'][$regId] ?? null;

    $cStats = calculateTroopStats($reg ? ($reg['troops'] ?? null) : null);

    $troopListText = "تعداد نیرو های مستقر شده در پادگان :\n";
    if ($reg && !empty($reg['troops'])) {
      foreach (TROOP_STATS as $tKey => $tData) {
        $count = $reg['troops'][$tKey] ?? 0;
        if ($count > 0) {
          $troopListText .= "{$tData['name']} ( {$count} )\n";
        }
      }
    }

    $text = "{$troopListText}\nقدرت در این منطقه : {$cStats['power']}\nاستقامت در این منطقه : {$cStats['stamina']}\nانرژی در این منطقه : {$cStats['energy']}\n\nآیا از حمله خود مطمن هستید ؟";
    $mode = $isRandom ? "random" : "target";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "attack_confirm_yes_{$regId}_{$mode}"], ['text' => "خیر", 'callback_data' => "region_click_{$regId}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "attack_limit_update_") === 0) {
    $regId = substr($cbData, strlen("attack_limit_update_"));
    $reg = $user_info['regions'][$regId] ?? null;

    $now = time();
    $lastAttack = $reg ? ($reg['last_attack_time'] ?? 0) : 0;
    $elapsed = $now - $lastAttack;
    $remaining = max(0, (int)(ATTACK_COOLDOWN - $elapsed));

    if ($remaining > 0) {
      $text = "شما به محدودیت حمله برخوردید \nزمان اتمام محدودیت شما : " . formatSecondsToDHMS($remaining);
      $inline_keyboard = [
        [['text' => "بروزسانی زمان", 'callback_data' => "attack_limit_update_{$regId}"]],
        [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]
      ];
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "زمان پیام بروزرسانی شد."]);
    } else {
      renderRegionMenu($chatId, $regId, $data, $messageId);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "محدودیت حمله شما به پایان رسید!"]);
    }
    return;
  }

  if (strpos($cbData, "attack_confirm_yes_") === 0) {
    $parts = explode("_", $cbData);
    $regId = $parts[3];
    $mode = $parts[4] ?? "target";

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    if ($mode === "random") {
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "درحال جستجوی حریف...",
        'reply_markup' => null
      ]);

      function getRankIdxAtk($rankName) {
        $idx = array_search($rankName, RANK_TIERS_LIST);
        return $idx !== false ? $idx : 0;
      }

      $user_info['recent_targets'] = $user_info['recent_targets'] ?? [];

      $candidates = [];
      foreach ($data['users'] as $u => $val) {
        if ($u === "Owner" || strtolower($u) === strtolower($username)) continue;
        if (!empty($val['ban'])) continue;
        $candidates[] = ['username' => $u, 'info' => $val];
      }

      if (empty($candidates)) {
        tgCall("editMessageText", [
          'chat_id' => $chatId,
          'message_id' => $messageId,
          'text' => "حریفی یافت نشد، لطفاً بعداً تلاش کنید",
          'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]]]
        ]);
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
        return;
      }

      $unvisitedCandidates = array_values(array_filter($candidates, function($c) use ($user_info) {
        return !in_array($c['username'], $user_info['recent_targets']);
      }));

      if (empty($unvisitedCandidates)) {
        $user_info['recent_targets'] = [];
        $unvisitedCandidates = $candidates;
      }

      $myRankIdx = getRankIdxAtk($user_info['rank'] ?? "\u{200E}⚪️I");
      $myLevel = $user_info['level'] ?? 1;

      usort($unvisitedCandidates, function($a, $b) use ($myRankIdx, $myLevel) {
        $aRankIdx = getRankIdxAtk($a['info']['rank'] ?? "\u{200E}⚪️I");
        $bRankIdx = getRankIdxAtk($b['info']['rank'] ?? "\u{200E}⚪️I");
        $aRankDiff = abs($aRankIdx - $myRankIdx);
        $bRankDiff = abs($bRankIdx - $myRankIdx);

        if ($aRankDiff !== $bRankDiff) {
          return $aRankDiff - $bRankDiff;
        }

        $aLvlDiff = abs(($a['info']['level'] ?? 1) - $myLevel);
        $bLvlDiff = abs(($b['info']['level'] ?? 1) - $myLevel);
        return $aLvlDiff - $bLvlDiff;
      });

      $matchedCandidate = $unvisitedCandidates[0];
      $user_info['recent_targets'][] = $matchedCandidate['username'];
      if (count($user_info['recent_targets']) > 10) {
        array_shift($user_info['recent_targets']);
      }
      saveData($data);

      $matchedTarget = $matchedCandidate['username'];
      $targetInfo = $matchedCandidate['info'];

      $retreatKeyboard = [
        'keyboard' => [[['text' => "عقب نشینی از حمله"]]],
        'resize_keyboard' => true
      ];

      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "حریف پیدا شد! جهت لغو می‌توانید از دکمه زیر استفاده کنید:",
        'reply_markup' => $retreatKeyboard
      ]);

      $cityCount = $targetInfo['city_count'] ?? 1;
      $inline_keyboard = [];
      for ($c = 1; $c <= $cityCount; $c++) {
        $inline_keyboard[] = [['text' => "شهر {$c}", 'callback_data' => "attack_select_city_{$regId}_{$matchedTarget}_{$c}_random"]];
      }

      $targetSummaryText = "نام کاربری : {$matchedTarget}\nلول : " . ($targetInfo['level'] ?? 1) . "\nرنک : " . ($targetInfo['rank'] ?? "\u{200E}⚪️I");

      $res = tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => $targetSummaryText,
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
      if (!empty($res['ok'])) {
        $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
        saveData($data);
      }
    } else {
      $data['user_states'][$chatId] = "STATE_ATTACK_TARGET_USER_{$regId}";
      saveData($data);

      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "نام کربری شخصی که میخواهید به اون حمله کنید را وارد کنید :",
        'reply_markup' => getCancelKeyboard()
      ]);
    }

    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "attack_back_to_target_") === 0) {
    $parts = explode("_", $cbData);
    $sourceRegId = $parts[4];
    $targetUser = $parts[5];
    $mode = $parts[6] ?? "target";

    $targetInfo = $data['users'][$targetUser] ?? null;
    if ($targetInfo) {
      $cityCount = $targetInfo['city_count'] ?? 1;
      $inline_keyboard = [];
      for ($c = 1; $c <= $cityCount; $c++) {
        $inline_keyboard[] = [['text' => "شهر {$c}", 'callback_data' => "attack_select_city_{$sourceRegId}_{$targetUser}_{$c}_{$mode}"]];
      }

      $text = "نام کاربری : {$targetUser}\nلول : " . ($targetInfo['level'] ?? 1) . "\nرنک : " . ($targetInfo['rank'] ?? "\u{200E}⚪️I");
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "attack_select_city_") === 0) {
    $parts = explode("_", $cbData);
    $sourceRegId = $parts[3];
    $targetUser = $parts[4];
    $cityNum = (int)$parts[5];
    $mode = $parts[6] ?? "target";

    $targetInfo = $data['users'][$targetUser] ?? null;
    if ($targetInfo) {
      $startReg = ($cityNum - 1) * 10 + 1;
      $endReg = $cityNum * 10;

      $inline_keyboard = [];
      for ($i = $startReg; $i <= $endReg; $i++) {
        $localIndex = $i - $startReg + 1;
        $inline_keyboard[] = [['text' => "منطقه {$localIndex}", 'callback_data' => "attack_select_reg_{$sourceRegId}_{$targetUser}_{$i}_{$mode}"]];
      }
      $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "attack_back_to_target_{$sourceRegId}_{$targetUser}_{$mode}"]];

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "لطفا روی یکی از منطقه های زیر جهت حمله کلیک کنید :",
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "attack_select_reg_") === 0) {
    $parts = explode("_", $cbData);
    $sourceRegId = $parts[3];
    $targetUser = $parts[4];
    $targetRegId = $parts[5];
    $mode = $parts[6] ?? "target";
    $cityNum = floor(((int)$targetRegId - 1) / 10) + 1;

    $text = "آیا از حمله به این منطقه مطمن هستید ؟ \nزیرا اگر در حمله شکست بخوردی تمام نیرو های شما از بین میروند";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "attack_exec_{$sourceRegId}_{$targetUser}_{$targetRegId}_{$mode}"], ['text' => "خیر", 'callback_data' => "attack_select_city_{$sourceRegId}_{$targetUser}_{$cityNum}_{$mode}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "attack_exec_") === 0) {
    $parts = explode("_", $cbData);
    $sourceRegId = $parts[2];
    $targetUser = $parts[3];
    $targetRegId = $parts[4];
    $mode = $parts[5] ?? "target";

    $sourceReg = &$user_info['regions'][$sourceRegId];
    if ($sourceReg) {
      $sourceReg['last_attack_time'] = time();
    }
    $targetUserInfo = &$data['users'][$targetUser];
    $targetReg = &$targetUserInfo['regions'][$targetRegId];

    $attackerScore = 0;
    $attackerTroopCount = 0;
    $attackerLossDetail = [];
    if ($sourceReg && !empty($sourceReg['troops'])) {
      foreach ($sourceReg['troops'] as $tKey => $count) {
        if ($count > 0 && isset(TROOP_STATS[$tKey])) {
          $attackerTroopCount += $count;
          $attackerScore += $count * (TROOP_STATS[$tKey]['power'] + TROOP_STATS[$tKey]['stamina'] + TROOP_STATS[$tKey]['energy']);
          $attackerLossDetail[] = TROOP_STATS[$tKey]['name'] . " : " . $count;
        }
      }
    }

    $defenderScore = 0;
    $defenderTroopCount = 0;
    $defenderTroopStr = [];
    if ($targetReg && !empty($targetReg['troops'])) {
      foreach ($targetReg['troops'] as $tKey => $count) {
        if ($count > 0 && isset(TROOP_STATS[$tKey])) {
          $defenderTroopCount += $count;
          $defenderScore += $count * (TROOP_STATS[$tKey]['power'] + TROOP_STATS[$tKey]['stamina'] + TROOP_STATS[$tKey]['energy']);
          $defenderTroopStr[] = TROOP_STATS[$tKey]['name'] . " ( " . $count . " )";
        }
      }
    }

    $targetRegNum = (int)$targetRegId;
    $targetCityNum = floor(($targetRegNum - 1) / 10) + 1;
    $localTargetRegionIndex = (($targetRegNum - 1) % 10) + 1;

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "⚔️ نبرد به پایان رسید.",
      'reply_markup' => getUserKeyboard()
    ]);

    $hasDefenderTroops = ($defenderTroopCount > 0);

    if ($hasDefenderTroops && $defenderScore >= $attackerScore) {
      // مدافع برنده شد - مهاجم شکست خورد
      $hadAttackerTroops = ($attackerTroopCount > 0);
      if ($sourceReg && isset($sourceReg['troops'])) {
        $sourceReg['troops'] = [];
      }

      if ($mode === "random") {
        deductRankXp($user_info, 5);
        tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "5 ایکس پی رنک از شما کسر شد"]);

        if ($hadAttackerTroops) {
          deductRankXp($user_info, 10);
          tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "10 ایکس پی رنک از شما کسر شد"]);
        }
      }

      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "متاسفانه شما در حمله شکست خوردید",
        'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت به مشخصات شما", 'callback_data' => "back_to_profile"]]]]
      ]);

      if ($targetUserInfo && !empty($targetUserInfo['chat_id'])) {
        $defReport = "کاربر {$username} به شما حمله کرد و موفق نشد \nتعداد نیرو های از دست رفته مهاجم : (" . implode(" | ", $attackerLossDetail) . ")";
        tgCall("sendMessage", [
          'chat_id' => $targetUserInfo['chat_id'],
          'text' => $defReport
        ]);
      }
    } else {
      // مهاجم برنده شد
      if ($targetReg && isset($targetReg['troops'])) {
        $targetReg['troops'] = [];
      }

      $bName = "هیچ";
      $lootText = "هیچ";

      if ($targetReg) {
        if ($targetReg['type'] === "construction") {
          $bName = "درحال ساخت " . BUILDING_STATS[$targetReg['building']]['name'];
          $wId = $targetReg['worker_id'] ?? "1";
          if (isset($targetUserInfo['workers'][$wId])) {
            $targetUserInfo['workers'][$wId]['status'] = "idle";
            $targetUserInfo['workers'][$wId]['target_region'] = null;
          }
        } else if (!empty($targetReg['type']) && $targetReg['type'] !== "ruin") {
          $stats = BUILDING_STATS[$targetReg['type']] ?? null;
          if ($stats) {
            $bName = $stats['name'];
            if (isset($stats['production_interval'])) {
              $accumulated = getAccumulatedResource($targetReg);
              if ($accumulated > 0) {
                $resourceKey = $targetReg['type'] === "lumber" ? "wood" : ($targetReg['type'] === "housing" ? "citizen" : ($targetReg['type'] === "bakery" ? "bread" : $targetReg['type']));
                $user_info['resources'][$resourceKey] = ($user_info['resources'][$resourceKey] ?? 0) + $accumulated;
                $lootText = "{$accumulated} " . (RES_NAMES[$resourceKey] ?? $resourceKey);
              }
            }
          }
        }
      }

      if ($mode === "random") {
        $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
        
        $user_info['xp_vault']['rank_xp'] += 5;
        tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "5 ایکس پی رنک به صندوق شما اضافه شد"]);

        if ($hasDefenderTroops) {
          $user_info['xp_vault']['rank_xp'] += 15;
          tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "15 ایکس پی رنک به صندوق شما اضافه شد"]);
        }

        $destXp = 2;
        if ($targetReg && $targetReg['type'] === "construction") {
          $destroyXpMap = ['housing' => 4, 'lumber' => 4, 'stone' => 4, 'bakery' => 6, 'market' => 6, 'iron' => 10, 'barracks' => 10];
          $destXp = $destroyXpMap[$targetReg['building']] ?? 4;
        } else if ($targetReg && !empty($targetReg['type']) && $targetReg['type'] !== "ruin") {
          $destroyXpMap = ['housing' => 4, 'lumber' => 4, 'stone' => 4, 'bakery' => 6, 'market' => 6, 'iron' => 10, 'barracks' => 10];
          $destXp = $destroyXpMap[$targetReg['type']] ?? 4;
        }
        $user_info['xp_vault']['rank_xp'] += $destXp;
        tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "{$destXp} ایکس پی رنک به صندوق شما اضافه شد"]);

        if ($targetUserInfo) {
          deductRankXp($targetUserInfo, 5);
          if (!empty($targetUserInfo['chat_id'])) {
            tgCall("sendMessage", ['chat_id' => $targetUserInfo['chat_id'], 'text' => "5 ایکس پی رنک از شما کسر شد"]);
          }

          if ($hasDefenderTroops) {
            deductRankXp($targetUserInfo, 10);
            if (!empty($targetUserInfo['chat_id'])) {
              tgCall("sendMessage", ['chat_id' => $targetUserInfo['chat_id'], 'text' => "10 ایکس پی رنک از شما کسر شد"]);
            }
          }

          deductRankXp($targetUserInfo, $destXp);
          if (!empty($targetUserInfo['chat_id'])) {
            tgCall("sendMessage", ['chat_id' => $targetUserInfo['chat_id'], 'text' => "{$destXp} ایکس پی رنک از شما کسر شد"]);
          }
        }
      }

      $prevMultiplier = 1;
      if ($targetReg && $targetReg['type'] === "ruin") {
        $prevMultiplier = ($targetReg['ruin_multiplier'] ?? 1) * 2;
      }
      $targetUserInfo['regions'][$targetRegId] = ['type' => "ruin", 'ruin_multiplier' => $prevMultiplier];
      saveData($data);

      $defTroopFormatted = count($defenderTroopStr) > 0 ? implode(" ، ", $defenderTroopStr) : "هیچ";
      $atkReport = "تبریک شما با موفقیت در حمله پیروز شدید \n\nسازه ایی که در این مکان وجود داشت : {$bName}\nغنیمت : {$lootText}\nتعداد نیرو های : {$defTroopFormatted}";

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $atkReport,
        'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت به مشخصات شما", 'callback_data' => "back_to_profile"]]]]
      ]);

      if ($targetUserInfo && !empty($targetUserInfo['chat_id'])) {
        $defReport = "کاربر {$username} به شما حمله کرد و پیروز شد \nخسارات ها :\n\n" . ($bName !== "هیچ" ? $bName : "سازه شما") . " در شهر {$targetCityNum} و در منطقه {$localTargetRegionIndex} تخریب شده و آن منطقه به یک خرابه تبدیل شده است\n\nمنابع غارت شده : {$lootText}\nنیروهای از دست رفته: {$defTroopFormatted}";
        tgCall("sendMessage", [
          'chat_id' => $targetUserInfo['chat_id'],
          'text' => $defReport
        ]);
      }
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- تنظیمات اعلان و برداشت و ارتقا و تخریب و جابجایی ---
  if (strpos($cbData, "toggle_notify_") === 0) {
    $regId = substr($cbData, strlen("toggle_notify_"));
    $reg = &$user_info['regions'][$regId];
    if ($reg) {
      $reg['notify'] = empty($reg['notify']);
      saveData($data);
      $statusStr = $reg['notify'] ? "روشن" : "خاموش";
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "وضعیت اعلان به {$statusStr} تغییر یافت."]);
      renderRegionMenu($chatId, $regId, $data, $messageId);
    }
    return;
  }

  if (strpos($cbData, "harvest_") === 0) {
    $regId = substr($cbData, strlen("harvest_"));
    $reg = &$user_info['regions'][$regId];
    if ($reg) {
      $stats = BUILDING_STATS[$reg['type']] ?? null;
      if ($stats && isset($stats['production_interval'])) {
        $level = $reg['level'] ?? 1;
        $multiplier = pow(2, $level - 1);
        $amount = $stats['production_amount'] * $multiplier;
        $capacity = $stats['capacity'] * $multiplier;

        if (empty($reg['last_harvest'])) {
          $reg['last_harvest'] = time();
        }

        $elapsed = time() - $reg['last_harvest'];
        $ticks = floor($elapsed / $stats['production_interval']);
        $total_produced = $ticks * $amount;
        $accumulated = min($capacity, $total_produced);

        if ($accumulated > 0) {
          $resourceKey = $reg['type'] === "lumber" ? "wood" : ($reg['type'] === "housing" ? "citizen" : ($reg['type'] === "bakery" ? "bread" : $reg['type']));
          $user_info['resources'][$resourceKey] = ($user_info['resources'][$resourceKey] ?? 0) + $accumulated;
          $reg['notified_full'] = false;

          if ($total_produced >= $capacity) {
            $reg['last_harvest'] = time();
          } else {
            $reg['last_harvest'] = $reg['last_harvest'] + ($ticks * $stats['production_interval']);
          }

          saveData($data);
          tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "{$accumulated} عدد محصول برداشت و ذخیره شد."]);
        } else {
          tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "محصولی برای برداشت وجود ندارد."]);
        }
      }
      renderRegionMenu($chatId, $regId, $data, $messageId);
    }
    return;
  }

  if (strpos($cbData, "upgrade_") === 0) {
    $regId = substr($cbData, strlen("upgrade_"));
    $reg = &$user_info['regions'][$regId];
    if ($reg) {
      $level = $reg['level'] ?? 1;
      $maxLvl = $reg['type'] === "barracks" ? 10 : 20;
      if ($level >= $maxLvl) {
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "سازه در بالاترین لول ({$maxLvl}) قرار دارد."]);
        return;
      }

      $stats = BUILDING_STATS[$reg['type']];
      $upgrade_cost = [
        'coin' => $stats['build_cost']['coin'] * pow(2, $level),
        'citizen' => $stats['build_cost']['citizen'] * pow(2, $level),
        'wood' => $stats['build_cost']['wood'] * pow(2, $level),
        'stone' => $stats['build_cost']['stone'] * pow(2, $level)
      ];

      $res = &$user_info['resources'];
      if (($res['coin'] ?? 0) >= $upgrade_cost['coin'] && ($res['citizen'] ?? 0) >= $upgrade_cost['citizen'] && ($res['wood'] ?? 0) >= $upgrade_cost['wood'] && ($res['stone'] ?? 0) >= $upgrade_cost['stone']) {
        $res['coin'] -= $upgrade_cost['coin'];
        $res['citizen'] -= $upgrade_cost['citizen'];
        $res['wood'] -= $upgrade_cost['wood'];
        $res['stone'] -= $upgrade_cost['stone'];

        $newLevel = $level + 1;
        $reg['level'] = $newLevel;
        $reg['last_harvest'] = time();

        $upgradeXp = 1;
        if ($newLevel >= 2 && $newLevel <= 5) $upgradeXp = 1;
        else if ($newLevel >= 6 && $newLevel <= 10) $upgradeXp = 2;
        else if ($newLevel >= 11 && $newLevel <= 15) $upgradeXp = 4;
        else if ($newLevel >= 16 && $newLevel <= 19) $upgradeXp = 7;
        else if ($newLevel === 20) $upgradeXp = 100;

        $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
        $user_info['xp_vault']['level_xp'] += $upgradeXp;

        saveData($data);

        tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "{$upgradeXp} ایکس پی لول به صندوق شما اضافه شد"]);
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "سازه با موفقیت به سطح بعدی ارتقا یافت."]);
        renderRegionMenu($chatId, $regId, $data, $messageId);
      } else {
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ منابع کافی ندارید.", 'show_alert' => true]);
      }
    }
    return;
  }

  if (strpos($cbData, "relocate_start_") === 0) {
    $regId = substr($cbData, strlen("relocate_start_"));
    $cityCount = $user_info['city_count'] ?? 1;
    $inline_keyboard = [];
    for ($c = 1; $c <= $cityCount; $c++) {
      $inline_keyboard[] = [['text' => "🏛️ شهر {$c} 🏛️", 'callback_data' => "relocate_city_{$regId}_{$c}"]];
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا شهر مورد نظر جهت جابجایی سازه را انتخاب کنید:",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "relocate_city_") === 0) {
    $parts = explode("_", $cbData);
    $regId = $parts[2];
    $cityNum = (int)$parts[3];

    $startReg = ($cityNum - 1) * 10 + 1;
    $endReg = $cityNum * 10;

    $inline_keyboard = [];
    for ($i = $startReg; $i <= $endReg; $i++) {
      $reg = $user_info['regions'][(string)$i] ?? null;
      $localIndex = $i - $startReg + 1;
      $b_name = "خالی";
      if ($reg) {
        if ($reg['type'] === "ruin") {
          $b_name = "خرابه";
        } else if ($reg['type'] === "construction") {
          $b_name = "درحال ساخت " . BUILDING_STATS[$reg['building']]['name'];
        } else {
          $b_name = BUILDING_STATS[$reg['type']]['name'] ?? $reg['type'];
        }
      }
      $inline_keyboard[] = [['text' => "منطقه {$localIndex} ({$b_name})", 'callback_data' => "relocate_exec_{$regId}_{$i}"]];
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "relocate_start_{$regId}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا روی یکی از مناطق زیر کلیک کنید تا این سازه با آنجا جابجا یا منتقل شود:",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "relocate_exec_") === 0) {
    $parts = explode("_", $cbData);
    $fromReg = $parts[2];
    $toReg = $parts[3];

    if ($fromReg === $toReg) {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "مبدا و مقصد نمی‌توانند یکی باشند.", 'show_alert' => true]);
      return;
    }

    $temp = $user_info['regions'][$fromReg] ?? null;
    $user_info['regions'][$fromReg] = $user_info['regions'][$toReg] ?? null;
    $user_info['regions'][$toReg] = $temp;
    saveData($data);

    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "سازه با موفقیت جابجا گردید."]);
    renderRegionMenu($chatId, $toReg, $data, $messageId);
    return;
  }

  if (strpos($cbData, "demolish_start_") === 0) {
    $regId = substr($cbData, strlen("demolish_start_"));
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "demolish_confirm_yes_{$regId}"], ['text' => "خیر", 'callback_data' => "region_click_{$regId}"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "آیا مطمئن هستید که میخواهید این سازه را تخریب کنید؟",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "demolish_confirm_yes_") === 0) {
    $regId = substr($cbData, strlen("demolish_confirm_yes_"));
    $user_info['regions'][$regId] = null;
    saveData($data);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "سازه با موفقیت تخریب و منطقه خالی شد."]);
    showUserProfile($chatId, $data);
    return;
  }

  // --- نیروهای مستقر شده، ساخت نیرو و جابجایی نیرو ---
  if ((strpos($cbData, "troops_") === 0 && strpos($cbData, "troops_detail_") !== 0) || strpos($cbData, "market_troops_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $reg = $user_info['regions'][$regId] ?? null;
    if ($reg) {
      $reg['troops'] = $reg['troops'] ?? [];
      $cStats = calculateTroopStats($reg['troops']);
      $hasTroops = false;
      $inline_keyboard = [];

      foreach (TROOP_STATS as $tKey => $tData) {
        $count = $reg['troops'][$tKey] ?? 0;
        if ($count > 0) {
          $hasTroops = true;
          $inline_keyboard[] = [['text' => $tData['name'], 'callback_data' => "troops_detail_{$tKey}_{$regId}"]];
        }
      }

      if ($hasTroops) {
        $text = "قدرت در این منطقه : {$cStats['power']}\nاستقامت در این منطقه : {$cStats['stamina']}\nانرژی در این منطقه : {$cStats['energy']}\n\nنیرو های شما :";
        $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]];

        tgCall("editMessageText", [
          'chat_id' => $chatId,
          'message_id' => $messageId,
          'text' => $text,
          'reply_markup' => ['inline_keyboard' => $inline_keyboard]
        ]);
      } else {
        tgCall("editMessageText", [
          'chat_id' => $chatId,
          'message_id' => $messageId,
          'text' => "هیچ نیرویی اینجا مستقر نیست.",
          'reply_markup' => ['inline_keyboard' => [[['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]]]
        ]);
      }
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "barracks_recruit_start_") === 0) {
    $regId = substr($cbData, strlen("barracks_recruit_start_"));
    $reg = $user_info['regions'][$regId] ?? null;
    $bLevel = $reg['level'] ?? 1;

    $inline_keyboard = [];
    foreach (TROOP_STATS as $tKey => $tData) {
      if ($bLevel >= $tData['reqLevel']) {
        $inline_keyboard[] = [['text' => $tData['name'], 'callback_data' => "recruit_desc_{$tKey}_{$regId}"]];
      } else {
        $inline_keyboard[] = [['text' => "{$tData['name']} 🔒", 'callback_data' => "recruit_locked_{$tData['reqLevel']}"]];
      }
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "نیرو های قابل ساخت :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "recruit_locked_") === 0) {
    $reqLevel = substr($cbData, strlen("recruit_locked_"));
    tgCall("answerCallbackQuery", [
      'callback_query_id' => $call['id'],
      'text' => "برای دریافت این نیرو باید پادگان خود را به سطح {$reqLevel} ارتقا دهید",
      'show_alert' => true
    ]);
    return;
  }

  if (strpos($cbData, "recruit_desc_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 2, count($parts) - 3));
    $tData = TROOP_STATS[$tKey] ?? null;

    if (!$tData) return;

    $costStr = "";
    if (!empty($tData['cost']['bread'])) $costStr .= "{$tData['cost']['bread']} نان \n";
    if (!empty($tData['cost']['citizen'])) $costStr .= "{$tData['cost']['citizen']} شهروند \n";
    if (!empty($tData['cost']['wood'])) $costStr .= "{$tData['cost']['wood']} چوب \n";
    if (!empty($tData['cost']['stone'])) $costStr .= "{$tData['cost']['stone']} سنگ \n";
    if (!empty($tData['cost']['iron'])) $costStr .= "{$tData['cost']['iron']} آهن \n";

    $text = "{$tData['name']}\n\nقدرت: {$tData['power']}\nاستقامت : {$tData['stamina']} \nانرژی : {$tData['energy']}\n\nهزینه ساخت هر یک : \n{$costStr}";

    $inline_keyboard = [
      [['text' => "ساخت", 'callback_data' => "recruit_input_{$tKey}_{$regId}"]],
      [['text' => "بازگشت", 'callback_data' => "barracks_recruit_start_{$regId}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "recruit_input_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 2, count($parts) - 3));
    $tData = TROOP_STATS[$tKey];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "STATE_RECRUIT_AMT_{$tKey}_{$regId}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "تعداد {$tData['name']}های خود را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "recruit_confirm_yes_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $amount = (int)$parts[count($parts) - 2];
    $tKey = implode("_", array_slice($parts, 3, count($parts) - 5));
    $tData = TROOP_STATS[$tKey];

    $reg = &$user_info['regions'][$regId];
    $res = &$user_info['resources'];

    $hasEnough = true;
    foreach ($tData['cost'] as $rKey => $rVal) {
      if (($res[$rKey] ?? 0) < $rVal * $amount) {
        $hasEnough = false;
        break;
      }
    }

    if ($hasEnough) {
      foreach ($tData['cost'] as $rKey => $rVal) {
        $res[$rKey] -= $rVal * $amount;
      }

      $reg['troops'] = $reg['troops'] ?? [];
      $reg['troops'][$tKey] = ($reg['troops'][$tKey] ?? 0) + $amount;
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "نیرو های شما با موفقیت ساخته شدند (نیرو های شما در پادگان مستقر هستن)",
        'reply_markup' => ['inline_keyboard' => [[['text' => "ورود به پادگان", 'callback_data' => "region_click_{$regId}"]]]]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ منابع شما کافی نیست.", 'show_alert' => true]);
    }
    return;
  }

  if (strpos($cbData, "recruit_confirm_no_") === 0) {
    $regId = substr($cbData, strlen("recruit_confirm_no_"));
    renderRegionMenu($chatId, $regId, $data, $messageId);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "troops_detail_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 2, count($parts) - 3));
    $tData = TROOP_STATS[$tKey];
    $reg = $user_info['regions'][$regId] ?? null;
    $count = $reg && !empty($reg['troops']) ? ($reg['troops'][$tKey] ?? 0) : 0;

    if ($tData) {
      $text = "{$tData['name']}\n\nتعداد : {$count} \nقدرت  : " . ($count * $tData['power']) . " \nاستقامت: " . ($count * $tData['stamina']) . "\nانرژی : " . ($count * $tData['energy']);
      $inline_keyboard = [
        [['text' => "جابجایی نیرو", 'callback_data' => "troop_move_start_{$tKey}_{$regId}"]],
        [['text' => "معاف کردن نیرو", 'callback_data' => "troop_dismiss_start_{$tKey}_{$regId}"]],
        [['text' => "بازگشت", 'callback_data' => "troops_{$regId}"]]
      ];
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "troop_move_start_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 3, count($parts) - 4));
    $tData = TROOP_STATS[$tKey];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "STATE_MOVE_TROOPS_AMT_{$tKey}_{$regId}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد {$tData['name']}هایی که میخواهید جابجا شوند را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "troop_move_back_city_") === 0) {
    $parts = explode("_", $cbData);
    $amount = (int)end($parts);
    $regId = $parts[count($parts) - 2];
    $tKey = implode("_", array_slice($parts, 4, count($parts) - 6));

    $cityCount = $user_info['city_count'] ?? 1;
    $inline_keyboard = [];
    for ($c = 1; $c <= $cityCount; $c++) {
      $inline_keyboard[] = [['text' => "🏛️ شهر {$c} 🏛️", 'callback_data' => "troop_move_city_{$tKey}_{$regId}_{$c}_{$amount}"]];
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "troops_detail_{$tKey}_{$regId}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا شهر خود را انتخاب کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "troop_move_city_") === 0) {
    $parts = explode("_", $cbData);
    $amount = (int)end($parts);
    $cityNum = (int)$parts[count($parts) - 2];
    $regId = $parts[count($parts) - 3];
    $tKey = implode("_", array_slice($parts, 3, count($parts) - 5));

    $startReg = ($cityNum - 1) * 10 + 1;
    $endReg = $cityNum * 10;

    $inline_keyboard = [];
    for ($i = $startReg; $i <= $endReg; $i++) {
      $targetReg = $user_info['regions'][(string)$i] ?? null;
      if ($targetReg && !empty($targetReg['type']) && $targetReg['type'] !== "construction" && $targetReg['type'] !== "ruin" && (string)$i !== $regId) {
        $localIndex = $i - $startReg + 1;
        $inline_keyboard[] = [['text' => "منطقه {$localIndex} (" . BUILDING_STATS[$targetReg['type']]['name'] . ")", 'callback_data' => "troop_move_exec_{$tKey}_{$regId}_{$i}_{$amount}"]];
      }
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "troop_move_back_city_{$tKey}_{$regId}_{$amount}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا روی یکی از منطقه های زیر جهت جابجایی کلیک کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "troop_move_exec_") === 0) {
    $parts = explode("_", $cbData);
    $amount = (int)end($parts);
    $toReg = $parts[count($parts) - 2];
    $fromReg = $parts[count($parts) - 3];
    $tKey = implode("_", array_slice($parts, 3, count($parts) - 5));

    $f_reg = &$user_info['regions'][$fromReg];
    $t_reg = &$user_info['regions'][$toReg];

    if ($f_reg && $t_reg && isset($f_reg['troops'][$tKey]) && $f_reg['troops'][$tKey] >= $amount) {
      $f_reg['troops'][$tKey] -= $amount;
      $t_reg['troops'] = $t_reg['troops'] ?? [];
      $t_reg['troops'][$tKey] = ($t_reg['troops'][$tKey] ?? 0) + $amount;
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "نیرو های شما با موفقیت به منطقه {$toReg} اعزام شدند",
        'reply_markup' => ['inline_keyboard' => [[['text' => "ورود به منطقه جدید", 'callback_data' => "region_click_{$toReg}"]]]]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ جابجایی با خطا مواجه شد."]);
    }
    return;
  }

  if (strpos($cbData, "troop_dismiss_start_") === 0) {
    $parts = explode("_", $cbData);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 3, count($parts) - 4));
    $tData = TROOP_STATS[$tKey];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "STATE_DISMISS_TROOPS_AMT_{$tKey}_{$regId}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد {$tData['name']}هایی که میخواهید معاف شوند را وارد کنید (هر معافیت ۱ شهروند به شما برمی‌گرداند):",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- سیستم کامل خرید و فروش بازار ---
  if (strpos($cbData, "market_buy_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[2];
    $regId = $parts[3];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "market_buy_amt_{$resKey}_{$regId}";
    saveData($data);

    $inline_keyboard = [
      [['text' => "خرید کل منبع", 'callback_data' => "m_buy_max_{$resKey}_{$regId}"]]
    ];

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "⚠️ کیبورد انصراف فعال شد:",
      'reply_markup' => getCancelKeyboard()
    ]);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد " . MARKET_RATES[$resKey]['name'] . " هایی که میخواهید خریداری کنید را وارد کنید:",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "m_buy_max_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[3];
    $regId = $parts[4];

    $rate = MARKET_RATES[$resKey];
    $userCoins = $user_info['resources']['coin'] ?? 0;
    $maxAmount = floor($userCoins / $rate['buy']);

    if ($maxAmount <= 0) {
      tgCall("answerCallbackQuery", [
        'callback_query_id' => $call['id'],
        'text' => "❌ سکه کافی برای خرید این منبع ندارید.",
        'show_alert' => true
      ]);
      return;
    }

    $totalCost = $rate['buy'] * $maxAmount;
    $myResource = $user_info['resources'][$resKey] ?? 0;

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    $summaryText = "مقدار {$rate['name']} شما : " . formatNumber($myResource) . "\nمقدار درخواستی : " . formatNumber($maxAmount) . "\nقیمت خرید هر یک {$rate['name']} : " . formatNumber($rate['buy']) . " سکه\nقیمت کل : " . formatNumber($totalCost) . " سکه\nموجوزی سکه شما : " . formatNumber($userCoins) . " سکه\n\nآیا از خرید خود اطمینان دارید ؟";

    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "m_buy_yes_{$resKey}_{$maxAmount}_{$regId}"], ['text' => "خیر", 'callback_data' => "m_buy_no_{$regId}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $summaryText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "m_buy_yes_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[3];
    $amount = (int)$parts[4];
    $regId = $parts[5];

    $rate = MARKET_RATES[$resKey];
    $totalCost = $rate['buy'] * $amount;

    if (($user_info['resources']['coin'] ?? 0) >= $totalCost) {
      $user_info['resources']['coin'] -= $totalCost;
      $user_info['resources'][$resKey] = ($user_info['resources'][$resKey] ?? 0) + $amount;
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "سکه کافی ندارید."]);
    }
    return;
  }

  if (strpos($cbData, "m_buy_no_") === 0) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "market_sell_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[2];
    $regId = $parts[3];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "market_sell_amt_{$resKey}_{$regId}";
    saveData($data);

    $inline_keyboard = [
      [['text' => "فروش کل منبع", 'callback_data' => "m_sell_max_{$resKey}_{$regId}"]]
    ];

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "⚠️ کیبورد انصراف فعال شد:",
      'reply_markup' => getCancelKeyboard()
    ]);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا مقدار " . MARKET_RATES[$resKey]['name'] . " خودتان که میخواهید بفروشید را وارد کنید:",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "m_sell_max_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[3];
    $regId = $parts[4];

    $rate = MARKET_RATES[$resKey];
    $maxAmount = $user_info['resources'][$resKey] ?? 0;

    if ($maxAmount <= 0) {
      tgCall("answerCallbackQuery", [
        'callback_query_id' => $call['id'],
        'text' => "❌ شما مقداری از این منبع برای فروش ندارید.",
        'show_alert' => true
      ]);
      return;
    }

    $totalEarnAmount = $rate['sell'] * $maxAmount;

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    $summaryText = "مقدار {$rate['name']} شما : " . formatNumber($maxAmount) . "\nمقدار برای فروش : " . formatNumber($maxAmount) . "\nقیمت فروش هر یک {$rate['name']} : " . formatNumber($rate['sell']) . " سکه\nقیمت کل درآمد : " . formatNumber($totalEarnAmount) . " سکه\n\nآیا از فروش خود اطمینان دارید ؟";

    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "m_sell_yes_{$resKey}_{$maxAmount}_{$regId}"], ['text' => "خیر", 'callback_data' => "m_sell_no_{$regId}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $summaryText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "m_sell_yes_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[3];
    $amount = (int)$parts[4];
    $regId = $parts[5];

    $rate = MARKET_RATES[$resKey];
    $totalEarn = $rate['sell'] * $amount;

    if (($user_info['resources'][$resKey] ?? 0) >= $amount) {
      $user_info['resources'][$resKey] -= $amount;
      $user_info['resources']['coin'] = ($user_info['resources']['coin'] ?? 0) + $totalEarn;
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "مقدار کافی برای فروش ندارید."]);
    }
    return;
  }

  if (strpos($cbData, "m_sell_no_") === 0) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- سیستم کارگاه و ساخت‌وساز ---
  if ($cbData === "back_to_workers") {
    $inline_keyboard = [];
    foreach (($user_info['workers'] ?? []) as $w_id => $worker) {
      $statusText = "بیکار";
      if ($worker['status'] === "building") {
        $statusText = "درحال ساخت " . BUILDING_STATS[$worker['building']]['name'];
      }
      $inline_keyboard[] = [['text' => "کارگر شماره {$w_id} ({$statusText})", 'callback_data' => "worker_click_{$w_id}"]];
    }
    $inline_keyboard[] = [['text' => "ساخت کارگر", 'callback_data' => "create_worker_start"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا روی یکی از کارگر های زیر کلیک کنید",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "create_worker_start") {
    $workerCount = count($user_info['workers'] ?? []);
    $nextWorker = $workerCount + 1;
    $cost = 5000 * pow(2, $nextWorker - 3);

    $text = "هزینه خرید کارگر شماره {$nextWorker} : " . formatNumber($cost) . " سکه\nآیا از خرید خود مطمن هستید؟";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "create_worker_confirm_yes_{$nextWorker}"], ['text' => "خیر", 'callback_data' => "back_to_workers"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "create_worker_confirm_yes_") === 0) {
    $nextWorker = (int)substr($cbData, strlen("create_worker_confirm_yes_"));
    $cost = 5000 * pow(2, $nextWorker - 3);

    if (($user_info['resources']['coin'] ?? 0) >= $cost) {
      $user_info['resources']['coin'] -= $cost;
      $user_info['workers'][(string)$nextWorker] = ['status' => "idle", 'target_region' => null];

      $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
      $user_info['xp_vault']['level_xp'] += 10;

      saveData($data);

      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "10 ایکس پی لول به صندوق شما اضافه شد"]);

      $inline_keyboard = [];
      foreach ($user_info['workers'] as $w_id => $worker) {
        $statusText = "بیکار";
        if ($worker['status'] === "building") {
          $statusText = "درحال ساخت " . BUILDING_STATS[$worker['building']]['name'];
        }
        $inline_keyboard[] = [['text' => "کارگر شماره {$w_id} ({$statusText})", 'callback_data' => "worker_click_{$w_id}"]];
      }
      $inline_keyboard[] = [['text' => "ساخت کارگر", 'callback_data' => "create_worker_start"]];

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "لطفا روی یکی از کارگر های زیر کلیک کنید",
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "✅ کارگر شماره {$nextWorker} با موفقیت خریداری شد."]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ سکه کافی ندارید.", 'show_alert' => true]);
    }
    return;
  }

  if (strpos($cbData, "worker_click_") === 0) {
    $wId = substr($cbData, strlen("worker_click_"));
    $worker = $user_info['workers'][$wId] ?? null;
    if ($worker && $worker['status'] === "building") {
      renderRegionMenu($chatId, $worker['target_region'], $data, $messageId);
    } else {
      showBuildingsList($chatId, $wId, $data, $messageId);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "build_desc_") === 0) {
    $parts = explode("_", $cbData);
    $bType = $parts[2];
    $wId = $parts[3];

    $stats = BUILDING_STATS[$bType];
    $text = "{$stats['desc']}\n\n";
    $text .= "هزینه ساخت :\nسکه {$stats['build_cost']['coin']}\nشهروند {$stats['build_cost']['citizen']}\nچوب {$stats['build_cost']['wood']}\nسنگ {$stats['build_cost']['stone']}\n";

    if (isset($stats['production_interval'])) {
      $text .= "سود : هر " . ($stats['production_interval'] / 60) . " دقیقه،  {$stats['production_amount']} {$stats['resource_name']}\n";
    }

    $text .= "زمان ساخت: " . formatSecondsToDHMS($stats['build_time']);

    $inline_keyboard = [
      [['text' => "ساخت", 'callback_data' => "build_start_{$bType}_{$wId}"]],
      [['text' => "بازگشت", 'callback_data' => "worker_click_{$wId}"]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $text,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "build_start_") === 0) {
    $parts = explode("_", $cbData);
    $bType = $parts[2];
    $wId = $parts[3];

    $stats = BUILDING_STATS[$bType];
    $res = $user_info['resources'];

    if (($res['coin'] ?? 0) < $stats['build_cost']['coin'] || ($res['citizen'] ?? 0) < $stats['build_cost']['citizen'] || ($res['wood'] ?? 0) < $stats['build_cost']['wood'] || ($res['stone'] ?? 0) < $stats['build_cost']['stone']) {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ منابع شما برای ساخت این سازه کافی نیست."]);
      return;
    }

    $cityCount = $user_info['city_count'] ?? 1;
    $inline_keyboard = [];
    for ($c = 1; $c <= $cityCount; $c++) {
      $inline_keyboard[] = [['text' => "شهر {$c}", 'callback_data' => "build_city_{$bType}_{$wId}_{$c}"]];
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "build_desc_{$bType}_{$wId}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا شهر مورد نظر را انتخاب کنید:",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "build_city_") === 0) {
    $parts = explode("_", $cbData);
    $bType = $parts[2];
    $wId = $parts[3];
    $cityNum = (int)$parts[4];

    $stats = BUILDING_STATS[$bType];
    $res = $user_info['resources'];

    if (($res['coin'] ?? 0) < $stats['build_cost']['coin'] || ($res['citizen'] ?? 0) < $stats['build_cost']['citizen'] || ($res['wood'] ?? 0) < $stats['build_cost']['wood'] || ($res['stone'] ?? 0) < $stats['build_cost']['stone']) {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ منابع شما برای ساخت این سازه کافی نیست."]);
      return;
    }

    $startReg = ($cityNum - 1) * 10 + 1;
    $endReg = $cityNum * 10;

    $inline_keyboard = [];
    for ($i = $startReg; $i <= $endReg; $i++) {
      if (empty($user_info['regions'][(string)$i])) {
        $localIndex = $i - $startReg + 1;
        $inline_keyboard[] = [['text' => "منطقه {$localIndex}", 'callback_data' => "build_confirm_{$bType}_{$wId}_{$i}"]];
      }
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "build_start_{$bType}_{$wId}"]];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا یکی از مناطق خالی زیر را انتخاب کنید:",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "build_confirm_") === 0) {
    $parts = explode("_", $cbData);
    $bType = $parts[2];
    $wId = $parts[3];
    $regId = $parts[4];

    $stats = BUILDING_STATS[$bType];
    $res = &$user_info['resources'];

    if (($res['coin'] ?? 0) >= $stats['build_cost']['coin'] && ($res['citizen'] ?? 0) >= $stats['build_cost']['citizen'] && ($res['wood'] ?? 0) >= $stats['build_cost']['wood'] && ($res['stone'] ?? 0) >= $stats['build_cost']['stone']) {
      $res['coin'] -= $stats['build_cost']['coin'];
      $res['citizen'] -= $stats['build_cost']['citizen'];
      $res['wood'] -= $stats['build_cost']['wood'];
      $res['stone'] -= $stats['build_cost']['stone'];

      $now = time();
      $user_info['regions'][$regId] = [
        'type' => "construction",
        'building' => $bType,
        'start_time' => $now,
        'end_time' => $now + $stats['build_time'],
        'worker_id' => $wId
      ];

      $user_info['workers'][$wId] = [
        'status' => "building",
        'target_region' => $regId,
        'building' => $bType,
        'end_time' => $now + $stats['build_time']
      ];

      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "سازه {$stats['name']} شما در منطقه {$regId} با موفقیت درحال ساخت است.",
        'reply_markup' => ['inline_keyboard' => [[['text' => "ورود به بخش مشخصات شما", 'callback_data' => "back_to_profile"]]]]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "❌ خطا؛ منابع نامعتبر است."]);
    }
    return;
  }

  // --- سیستم انتقال مستقیم و معامله همتا به همتا ---
  if (strpos($cbData, "market_transfer_") === 0) {
    $regId = substr($cbData, strlen("market_transfer_"));
    $data['last_menu_msg'][$chatId] = $messageId;
    saveData($data);

    $inline_keyboard = [
      [['text' => "سکه", 'callback_data' => "tx_res_coin_{$regId}"], ['text' => "آهن", 'callback_data' => "tx_res_iron_{$regId}"]],
      [['text' => "سنگ", 'callback_data' => "tx_res_stone_{$regId}"], ['text' => "چوب", 'callback_data' => "tx_res_wood_{$regId}"]],
      [['text' => "نان", 'callback_data' => "tx_res_bread_{$regId}"]],
      [['text' => "بازگشت", 'callback_data' => "region_click_{$regId}"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا یکی از منابع زیر را انتخاب کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "tx_res_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[2];
    $regId = $parts[3] ?? "";

    $amount = $user_info['resources'][$resKey] ?? 0;
    $resName = RES_NAMES[$resKey];

    $data['last_menu_msg'][$chatId] = $messageId;
    saveData($data);

    $textMsg = "مقدار {$resName} های شما : {$amount}\n\nتوضیح دکمه های زیر :\nمعامله : شما با استفاده از این دکمه میتوانید به کاربر مقابل {$resName} بدهید و در ازاش منبعی دریافت کنید بدون اینکه کلاه برداری اتفاق بیوفتد\nانتقال : شما فقط مقدار {$resName} مشخص شده را به فرد مورد نظر انتقال میدهید";

    $backCb = $regId ? "tx_back_to_select_{$regId}" : "tx_back_to_select";
    $inline_keyboard = [
      [['text' => "انتقال", 'callback_data' => "tx_action_transfer_{$resKey}"], ['text' => "معامله", 'callback_data' => "tx_action_trade_{$resKey}"]],
      [['text' => "بازگشت", 'callback_data' => $backCb]]
    ];

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => $textMsg,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "tx_back_to_select") === 0) {
    $parts = explode("_", $cbData);
    $regId = $parts[4] ?? "";

    $coinCb = $regId ? "tx_res_coin_{$regId}" : "tx_res_coin";
    $ironCb = $regId ? "tx_res_iron_{$regId}" : "tx_res_iron";
    $stoneCb = $regId ? "tx_res_stone_{$regId}" : "tx_res_stone";
    $woodCb = $regId ? "tx_res_wood_{$regId}" : "tx_res_wood";
    $breadCb = $regId ? "tx_res_bread_{$regId}" : "tx_res_bread";
    $backCb = $regId ? "region_click_{$regId}" : "back_to_profile";

    $inline_keyboard = [
      [['text' => "سکه", 'callback_data' => $coinCb], ['text' => "آهن", 'callback_data' => $ironCb]],
      [['text' => "سنگ", 'callback_data' => $stoneCb], ['text' => "چوب", 'callback_data' => $woodCb]],
      [['text' => "نان", 'callback_data' => $breadCb]],
      [['text' => "بازگشت", 'callback_data' => $backCb]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "لطفا یکی از منابع زیر را انتخاب کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "tx_action_trade_") === 0) {
    $resKey = substr($cbData, strlen("tx_action_trade_"));
    $resName = RES_NAMES[$resKey];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "STATE_TRADE_GIVE_AMT_{$resKey}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد {$resName} هایی که میخواهید بدهید را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "trade_getres_") === 0) {
    $parts = explode("_", $cbData);
    $getResKey = $parts[2];
    $giveResKey = $parts[3];
    $giveAmt = $parts[4];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "STATE_TRADE_GET_AMT_{$giveResKey}_{$giveAmt}_{$getResKey}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد " . RES_NAMES[$getResKey] . " هایی که میخواهید بگیرید را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "trade_accept_") === 0 || strpos($cbData, "trade_reject_") === 0) {
    $isAccept = strpos($cbData, "trade_accept_") === 0;
    $tradeId = $isAccept ? substr($cbData, strlen("trade_accept_")) : substr($cbData, strlen("trade_reject_"));
    $data['trades'] = $data['trades'] ?? [];
    $trade = &$data['trades'][$tradeId];

    if (!$trade || $trade['status'] !== "pending") {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "این معامله منقضی، لغو یا قبلاً انجام شده است."]);
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
      return;
    }

    if ($isAccept) {
      $sender_info = &$data['users'][$trade['sender']];
      $target_info = &$data['users'][$trade['target']];

      if (!$sender_info || !$target_info) {
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "خطا در بارگذاری اطلاعات معامله."]);
        return;
      }

      if (($sender_info['resources'][$trade['give_res']] ?? 0) < $trade['give_amt']) {
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "خطا: منابع کاربر ارسال‌کننده معامله دیگر کافی نیست."]);
        $trade['status'] = "cancelled";
        saveData($data);
        tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
        return;
      }

      if (($target_info['resources'][$trade['get_res']] ?? 0) < $trade['get_amt']) {
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "شما این مقدار " . RES_NAMES[$trade['get_res']] . " را ندارید.", 'show_alert' => true]);
        return;
      }

      $sender_info['resources'][$trade['give_res']] -= $trade['give_amt'];
      $sender_info['resources'][$trade['get_res']] = ($sender_info['resources'][$trade['get_res']] ?? 0) + $trade['get_amt'];

      $target_info['resources'][$trade['get_res']] -= $trade['get_amt'];
      $target_info['resources'][$trade['give_res']] = ($target_info['resources'][$trade['give_res']] ?? 0) + $trade['give_amt'];

      $trade['status'] = "completed";
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);

      $targetSuccessText = "شما " . formatNumber($trade['give_amt']) . " " . RES_NAMES[$trade['give_res']] . " دریافت کردید و " . formatNumber($trade['get_amt']) . " " . RES_NAMES[$trade['get_res']] . " به {$trade['sender']} دادید.";
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $targetSuccessText]);

      if (!empty($trade['sender_chat_id'])) {
        $senderSuccessText = "شما " . formatNumber($trade['give_amt']) . " " . RES_NAMES[$trade['give_res']] . " را به کاربر {$trade['target']} دادید و " . formatNumber($trade['get_amt']) . " " . RES_NAMES[$trade['get_res']] . " از آن گرفتید.";
        tgCall("sendMessage", ['chat_id' => $trade['sender_chat_id'], 'text' => $senderSuccessText]);
      }
    } else {
      $trade['status'] = "rejected";
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات لغو شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);

      if (!empty($trade['sender_chat_id'])) {
        tgCall("sendMessage", [
          'chat_id' => $trade['sender_chat_id'],
          'text' => "کاربر {$trade['target']} معامله شما را رد کرد."
        ]);
      }
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "tx_action_transfer_") === 0) {
    $resKey = substr($cbData, strlen("tx_action_transfer_"));
    $resName = RES_NAMES[$resKey];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
      $data['last_menu_msg'][$chatId] = null;
    }

    $data['user_states'][$chatId] = "STATE_TRANSFER_AMT_{$resKey}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد {$resName} هایی که میخواهید انتقال دهید را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "transfer_confirm_yes_") === 0) {
    $parts = explode("_", $cbData);
    $resKey = $parts[3];
    $amount = (int)$parts[4];
    $targetUser = $parts[5];

    $sender_info = &$data['users'][$username];
    $target_info = &$data['users'][$targetUser];

    if ($sender_info && $target_info) {
      if (($sender_info['resources'][$resKey] ?? 0) >= $amount) {
        $sender_info['resources'][$resKey] -= $amount;
        $target_info['resources'][$resKey] = ($target_info['resources'][$resKey] ?? 0) + $amount;
        saveData($data);

        tgCall("editMessageText", [
          'chat_id' => $chatId,
          'message_id' => $messageId,
          'text' => "عملیات انجام شد",
          'reply_markup' => ['inline_keyboard' => []]
        ]);

        if (!empty($target_info['chat_id'])) {
          tgCall("sendMessage", [
            'chat_id' => $target_info['chat_id'],
            'text' => "📬 کاربر {$username} مقدار " . formatNumber($amount) . " " . RES_NAMES[$resKey] . " به حساب شما انتقال داد."
          ]);
        }
      } else {
        tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "منابع شما برای این انتقال کافی نیست."]);
      }
    }
    return;
  }

  if ($cbData === "transfer_confirm_no") {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "back_to_profile") {
    showUserProfile($chatId, $data);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- سیستم پیام‌رسانی مخفی بین کاربران ---
  if ($cbData === "msg_send_no") {
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "msg_send_yes") {
    $msgTarget = $data['temp_data'][$chatId]['msg_target'] ?? null;
    $msgBody = $data['temp_data'][$chatId]['msg_body'] ?? null;

    if ($msgTarget && $msgBody) {
      $msgId = "chat_msg_" . time() . "_" . mt_rand(100, 999);
      $dt = getShamsiDateTime();

      $data['chat_messages'][$msgId] = [
        'sender' => $username,
        'target' => $msgTarget,
        'text' => $msgBody,
        'date' => $dt['date'],
        'time' => $dt['time']
      ];

      $logText = "نام کاربری فرستنده : {$username}\nنام کاربری گیرنده : {$msgTarget}\nتاریخ ارسال (به شمسی) : {$dt['date']}\nساعت : {$dt['time']}\nمتن پیام :\n{$msgBody}";
      tgCall("sendMessage", ['chat_id' => DATA_CHAT_CHANNEL_ID, 'text' => $logText]);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);

      $targetInfo = $data['users'][$msgTarget] ?? null;
      if ($targetInfo && !empty($targetInfo['chat_id'])) {
        $targetInline = [[['text' => "مشاهده پیام", 'callback_data' => "msg_view_{$msgId}"]]];
        tgCall("sendMessage", [
          'chat_id' => $targetInfo['chat_id'],
          'text' => "کاربر {$username} به شما این پیام را داد",
          'reply_markup' => ['inline_keyboard' => $targetInline]
        ]);
      }
    } else {
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);
    }
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "msg_view_") === 0) {
    $msgId = substr($cbData, strlen("msg_view_"));
    $msgObj = $data['chat_messages'][$msgId] ?? null;

    if ($msgObj) {
      $inline_keyboard = [
        [['text' => "پاسخ", 'callback_data' => "msg_reply_start_{$msgId}"]]
      ];
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $msgObj['text'],
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
    } else {
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "پیام یافت نشد یا حذف شده است.", 'show_alert' => true]);
    }
    return;
  }

  if (strpos($cbData, "msg_reply_start_") === 0) {
    $msgId = substr($cbData, strlen("msg_reply_start_"));
    $msgObj = $data['chat_messages'][$msgId] ?? null;

    if ($msgObj) {
      $data['user_states'][$chatId] = "STATE_MSG_REPLY_TEXT_{$msgId}";
      saveData($data);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "لطفا پاسخ خود را بنویسید",
        'reply_markup' => null
      ]);

      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "⚠️ کیبورد انصراف فعال شد:",
        'reply_markup' => getCancelKeyboard()
      ]);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "msg_reply_send_no") {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "msg_reply_send_yes_") === 0) {
    $origMsgId = substr($cbData, strlen("msg_reply_send_yes_"));
    $origMsgObj = $data['chat_messages'][$origMsgId] ?? null;
    $replyBody = $data['temp_data'][$chatId]['reply_body'] ?? null;

    if ($origMsgObj && $replyBody) {
      $targetUser = $origMsgObj['sender'];
      $newMsgId = "chat_msg_" . time() . "_" . mt_rand(100, 999);
      $dt = getShamsiDateTime();

      $data['chat_messages'][$newMsgId] = [
        'sender' => $username,
        'target' => $targetUser,
        'text' => $replyBody,
        'date' => $dt['date'],
        'time' => $dt['time']
      ];
      saveData($data);

      $logText = "نام کاربری فرستنده : {$username}\nنام کاربری گیرنده : {$targetUser}\nتاریخ ارسال (به شمسی) : {$dt['date']}\nساعت : {$dt['time']}\nمتن پیام :\n{$replyBody}";
      tgCall("sendMessage", ['chat_id' => DATA_CHAT_CHANNEL_ID, 'text' => $logText]);

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);

      $targetInfo = $data['users'][$targetUser] ?? null;
      if ($targetInfo && !empty($targetInfo['chat_id'])) {
        $targetInline = [[['text' => "مشاهده پاسخ", 'callback_data' => "msg_view_{$newMsgId}"]]];
        tgCall("sendMessage", [
          'chat_id' => $targetInfo['chat_id'],
          'text' => "کاربر {$username} به پیام شما پاسخ داد",
          'reply_markup' => ['inline_keyboard' => $targetInline]
        ]);
      }
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- سیستم چالش‌ها ---
  if (strpos($cbData, "c_ans_") === 0) {
    $lastUnderscore = strrpos($cbData, "_");
    $selectedOpt = (int)substr($cbData, $lastUnderscore + 1);
    $chalId = substr($cbData, strlen("c_ans_"), $lastUnderscore - strlen("c_ans_"));

    $chal = &$data['challenges'][$chalId];
    if (!$chal || ($chal['status'] ?? '') === "expired") {
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "این چالش منقضی شد",
        'reply_markup' => null
      ]);
      tgCall("answerCallbackQuery", ['callback_query_id' => $call['id'], 'text' => "این چالش منقضی شده است."]);
      return;
    }

    $chal['participants'] = $chal['participants'] ?? [];
    if (in_array($username, $chal['participants'])) {
      tgCall("answerCallbackQuery", [
        'callback_query_id' => $call['id'],
        'text' => "شما قبلاً در این چالش شرکت کرده‌اید.",
        'show_alert' => true
      ]);
      return;
    }

    $chal['participants'][] = $username;
    $chal['stats'] = $chal['stats'] ?? ['opt1' => 0, 'opt2' => 0, 'opt3' => 0, 'opt4' => 0];
    $chal['stats']['opt' . $selectedOpt] = ($chal['stats']['opt' . $selectedOpt] ?? 0) + 1;

    if ($selectedOpt === (int)$chal['correct_option']) {
      $itemsText = "";
      $nameMap = ['level_xp' => "ایکس پی لول", 'coin' => "سکه", 'stone' => "سنگ", 'iron' => "آهن", 'wood' => "چوب", 'bread' => "نان", 'citizen' => "شهروند"];

      foreach (($chal['items'] ?? []) as $item) {
        $resName = $nameMap[$item['res']] ?? $item['res'];
        $itemsText .= "{$resName} : " . formatNumber($item['amt']) . "\n";

        if ($item['res'] === "level_xp") {
          $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
          $user_info['xp_vault']['level_xp'] += $item['amt'];
        } else {
          $user_info['resources'][$item['res']] = ($user_info['resources'][$item['res']] ?? 0) + $item['amt'];
        }
      }

      saveData($data);

      $winText = "شما گزینه درست را انتخاب کردید\n\n" . trim($itemsText);
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $winText,
        'reply_markup' => null
      ]);
    } else {
      saveData($data);
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "متاسفانه این گزینه نادرست است",
        'reply_markup' => null
      ]);
    }

    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "chal_exp_ask_") === 0) {
    $chalId = substr($cbData, strlen("chal_exp_ask_"));
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "chal_exp_yes_{$chalId}"], ['text' => "خیر", 'callback_data' => "chal_exp_no_{$chalId}"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "آیا مطمن هستید که میخواهید این چالش را منقضی کنید؟",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "chal_exp_yes_") === 0) {
    $chalId = substr($cbData, strlen("chal_exp_yes_"));
    if (isset($data['challenges'][$chalId])) {
      $data['challenges'][$chalId]['status'] = "expired";
      if (!empty($data['challenges'][$chalId]['broadcast_messages'])) {
        foreach ($data['challenges'][$chalId]['broadcast_messages'] as $bMsg) {
          tgCall("editMessageText", [
            'chat_id' => $bMsg['chat_id'],
            'message_id' => $bMsg['message_id'],
            'text' => "این چالش منقضی شد",
            'reply_markup' => null
          ]);
        }
      }
      saveData($data);
    }
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات انجام شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "chal_exp_no_") === 0) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "chal_correct_") === 0) {
    $optNum = (int)substr($cbData, strlen("chal_correct_"));
    $data['temp_data'][$chatId] = $data['temp_data'][$chatId] ?? [];
    $data['temp_data'][$chatId]['challenge_correct'] = $optNum;
    $data['user_states'][$chatId] = "STATE_OWNER_CHALLENGE_ITEM_COUNT";
    saveData($data);

    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "چه تعداد ایتمی مدنظر شماست",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "chal_item_type_") === 0) {
    $resKey = substr($cbData, strlen("chal_item_type_"));
    $nameMap = ['level_xp' => "ایکس پی لول", 'coin' => "سکه", 'stone' => "سنگ", 'iron' => "آهن", 'wood' => "چوب", 'bread' => "نان"];
    $resName = $nameMap[$resKey] ?? $resKey;

    $data['temp_data'][$chatId] = $data['temp_data'][$chatId] ?? [];
    $data['temp_data'][$chatId]['selected_chal_item_res'] = $resKey;
    $data['user_states'][$chatId] = "STATE_OWNER_CHALLENGE_ITEM_AMT";
    saveData($data);

    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد {$resName} خود را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // --- سیستم کدهای هدیه و ابزارهای ادمین ---
  if (strpos($cbData, "gift_exp_ask_") === 0) {
    $code = substr($cbData, strlen("gift_exp_ask_"));
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "gift_exp_yes_{$code}"], ['text' => "خیر", 'callback_data' => "gift_exp_no_{$code}"]]
    ];
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "آیا مطمن هستید که میخواهد کد هدیه {$code} را منقظی کنید ؟",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "gift_exp_yes_") === 0) {
    $code = substr($cbData, strlen("gift_exp_yes_"));
    if (isset($data['gifts'][$code])) {
      deleteGiftHelper($data, $code);
    }
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات انجام شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "gift_exp_no_") === 0) {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "gift_item_type_") === 0) {
    $resKey = substr($cbData, strlen("gift_item_type_"));
    $nameMap = ['level_xp' => "ایکس پی لول", 'coin' => "سکه", 'stone' => "سنگ", 'iron' => "آهن", 'wood' => "چوب", 'bread' => "نان"];
    $resName = $nameMap[$resKey] ?? $resKey;

    $data['temp_data'][$chatId] = $data['temp_data'][$chatId] ?? [];
    $data['temp_data'][$chatId]['selected_item_res'] = $resKey;
    $data['user_states'][$chatId] = "STATE_OWNER_GIFT_ITEM_AMT";
    saveData($data);

    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد {$resName} خود را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "owner_inf_res_") === 0) {
    $resKey = substr($cbData, strlen("owner_inf_res_"));
    $data['user_states'][$chatId] = "STATE_OWNER_INF_TARGET_{$resKey}";
    saveData($data);

    tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا نام کاربری شخص گیرنده را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "owner_inf_confirm_no") {
    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات لغو شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if (strpos($cbData, "owner_inf_confirm_yes_") === 0) {
    $parts = explode("_", substr($cbData, strlen("owner_inf_confirm_yes_")));
    $resKey = $parts[0];
    $amount = (int)$parts[1];
    $targetUser = $parts[2];

    $targetInfo = &$data['users'][$targetUser];
    if (!$targetInfo) {
      foreach ($data['users'] as $u => &$val) {
        if (strtolower($u) === strtolower($targetUser)) { $targetInfo = &$val; break; }
      }
    }

    if ($targetInfo) {
      $targetInfo['resources'][$resKey] = ($targetInfo['resources'][$resKey] ?? 0) + $amount;
      saveData($data);

      $resName = RES_NAMES[$resKey] ?? $resKey;
      if (!empty($targetInfo['chat_id'])) {
        tgCall("sendMessage", [
          'chat_id' => $targetInfo['chat_id'],
          'text' => "حساب کاربری Owner به شما " . formatNumber($amount) . " به همراه {$resName} انتقال داد"
        ]);
      }
    }

    tgCall("editMessageText", [
      'chat_id' => $chatId,
      'message_id' => $messageId,
      'text' => "عملیات انجام شد",
      'reply_markup' => ['inline_keyboard' => []]
    ]);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "owner_bc_confirm_yes" || $cbData === "owner_bc_confirm_no") {
    $bcText = $data['temp_data'][$chatId]['broadcast_text'] ?? null;

    if ($cbData === "owner_bc_confirm_yes" && $bcText) {
      $targetChatIds = [];
      foreach ($data['users'] as $uKey => $uVal) {
        if (!empty($uVal['chat_id'])) {
          $targetChatIds[$uVal['chat_id']] = true;
        }
      }

      foreach (array_keys($targetChatIds) as $cId) {
        tgCall("sendMessage", ['chat_id' => $cId, 'text' => $bcText]);
      }

      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات انجام شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);
    } else {
      tgCall("editMessageText", [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => "عملیات لغو شد",
        'reply_markup' => ['inline_keyboard' => []]
      ]);
    }

    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "report_confirm_yes" || $cbData === "report_confirm_no") {
    $state = $data['user_states'][$chatId] ?? '';
    if ($state === "owner_reply_report_confirm") {
      $reportCode = $data['temp_data'][$chatId]['reply_report_code'] ?? null;
      $answerText = $data['temp_data'][$chatId]['reply_report_answer'] ?? null;

      if ($cbData === "report_confirm_yes" && $reportCode && $answerText) {
        $report = &$data['reports'][$reportCode];
        if ($report) {
          $report['status'] = "answered";
          $report['answer'] = $answerText;
          saveData($data);

          if (!empty($report['user_chat_id'])) {
            $userMsg = "کاربر عزیز \nادمین به گزارش شما پاسخ داد \n\nگزارش شما : {$report['text']}\n\nپاسخ ادمین : {$answerText}";
            tgCall("sendMessage", ['chat_id' => $report['user_chat_id'], 'text' => $userMsg]);
          }

          tgCall("editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "عملیات انجام شد",
            'reply_markup' => ['inline_keyboard' => []]
          ]);
        } else {
          tgCall("editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "❌ گزارش مربوطه یافت نشد.",
            'reply_markup' => ['inline_keyboard' => []]
          ]);
        }
      } else {
        tgCall("editMessageText", [
          'chat_id' => $chatId,
          'message_id' => $messageId,
          'text' => "عملیات لغو شد",
          'reply_markup' => ['inline_keyboard' => []]
        ]);
      }

      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "پنل مدیریت فعال شد.",
        'reply_markup' => getOwnerKeyboard()
      ]);
      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  if ($cbData === "delete_confirm_yes" || $cbData === "delete_confirm_no") {
    $state = $data['user_states'][$chatId] ?? '';
    if ($state === "owner_delete_user_confirm") {
      $targetUser = $data['temp_data'][$chatId]['delete_target'] ?? null;
      if ($cbData === "delete_confirm_yes" && $targetUser) {
        if (isset($data['users'][$targetUser])) {
          $channelMsgId = $data['users'][$targetUser]['channel_msg_id'] ?? null;
          if ($channelMsgId) {
            tgCall("deleteMessage", ['chat_id' => CHANNEL_ID, 'message_id' => $channelMsgId]);
          }
          unset($data['users'][$targetUser]);
          saveData($data);
          tgCall("editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "عملیات انجام شد",
            'reply_markup' => ['inline_keyboard' => []]
          ]);
        } else {
          tgCall("editMessageText", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "❌ کاربر مورد نظر یافت نشد یا پیش‌تر حذف شده است.",
            'reply_markup' => ['inline_keyboard' => []]
          ]);
        }
      } else {
        tgCall("editMessageText", [
          'chat_id' => $chatId,
          'message_id' => $messageId,
          'text' => "عملیات لغو شد",
          'reply_markup' => ['inline_keyboard' => []]
        ]);
      }
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "پنل مدیریت فعال شد.",
        'reply_markup' => getOwnerKeyboard()
      ]);
      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);
    }
    tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
    return;
  }

  // تاییدیه نهایی برای کال‌بک‌های ثبت‌نشده
  tgCall("answerCallbackQuery", ['callback_query_id' => $call['id']]);
}

function deleteGiftHelper(&$data, $code) {
  unset($data['gifts'][$code]);
  saveData($data);
}
