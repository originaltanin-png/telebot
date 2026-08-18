<?php
/**
 * Wielder of Power - Text Message & State Machine Handler
 * File: messages.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';

function handleMessage($message, &$data) {
  $chatId = $message['chat']['id'];
  $text = trim($message['text'] ?? '');
  $state = $data['user_states'][$chatId] ?? STATE_IDLE;

  $username = null;
  foreach ($data['users'] as $u => $val) {
    if ($val && ($val['chat_id'] ?? null) == $chatId) { $username = $u; break; }
  }

  // ۱. دستور امنیتی ریست کامل دیتابیس
  if ($text === "/depdel") {
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
    saveData($defaultData);

    $rmRes = tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "🔄", 'reply_markup' => ['remove_keyboard' => true]]);
    if (!empty($rmRes['ok'])) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $rmRes['result']['message_id']]);
    }

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "🚨 دیتابیس با موفقیت کاملاً پاکسازی و ریست شد. تمام اکانت‌ها حذف گردیدند."
    ]);

    $markup = [
      'inline_keyboard' => [
        [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
        [['text' => "راهنما", 'callback_data' => "btn_help"]]
      ]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
      'reply_markup' => $markup
    ]);
    return;
  }

  // ۲. دستور شروع (/start)
  if ($text === "/start") {
    if ($username) {
      if ($username === "Owner") {
        tgCall("sendMessage", [
          'chat_id' => $chatId,
          'text' => "شما در حال حاضر به عنوان مالک (Owner) وارد شده‌اید و در پنل مدیریت هستید.",
          'reply_markup' => getOwnerKeyboard()
        ]);
        return;
      }
      
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "شما در حال حاضر وارد حساب کاربری خود شده‌اید و داخل بازی هستید.",
        'reply_markup' => getUserKeyboard()
      ]);
      showUserProfile($chatId, $data);
      return;
    }

    $data['user_states'][$chatId] = STATE_IDLE;
    saveData($data);

    $rmRes = tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "🔄", 'reply_markup' => ['remove_keyboard' => true]]);
    if (!empty($rmRes['ok'])) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $rmRes['result']['message_id']]);
    }

    $markup = [
      'inline_keyboard' => [
        [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
        [['text' => "راهنما", 'callback_data' => "btn_help"]]
      ]
    ];
    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
      'reply_markup' => $markup
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
    return;
  }

  // ۳. دستور بروزرسانی (/update)
  if ($text === "/update") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب کاربری خود شوید."]);
      return;
    }
    if ($username === "Owner") {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "حساب مالک نیازی به آپدیت ندارد."]);
      return;
    }
    
    $user_info = &$data['users'][$username];
    initUserGameData($user_info);
    updateUserState($username, $data);

    foreach ($user_info['regions'] as $reg_id => &$reg_data) {
      if ($reg_data && $reg_data['type'] !== "construction" && $reg_data['type'] !== "market" && empty($reg_data['last_harvest'])) {
        $reg_data['last_harvest'] = time();
      }
    }
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "🔄 ربات و اطلاعات حساب شما با موفقیت به آخرین نسخه آپدیت شد.",
      'reply_markup' => getUserKeyboard()
    ]);
    showUserProfile($chatId, $data);
    return;
  }

  // --- دکمه‌های اختصاصی پنل مالک ---
  if ($username === "Owner" && $state === "owner_panel" && $text === "پیام همگانی") {
    $data['user_states'][$chatId] = "STATE_OWNER_BROADCAST_TEXT";
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا متن خود را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "تعداد کاربران") {
    $startedChatIds = [];
    foreach (($data['user_states'] ?? []) as $cId => $st) {
      if (is_numeric($cId)) $startedChatIds[(string)$cId] = true;
    }
    foreach (($data['users'] ?? []) as $uKey => $uVal) {
      if (!empty($uVal['chat_id'])) $startedChatIds[(string)$uVal['chat_id']] = true;
    }

    $registeredCount = 0;
    foreach (($data['users'] ?? []) as $uKey => $uVal) {
      if ($uKey !== "Owner") $registeredCount++;
    }

    $startedCount = count($startedChatIds);
    $statsMsg = "تعداد کل افرادی که ربات را استارت زده‌اند: " . formatNumber($startedCount) . "\nتعداد کاربران ثبت‌نام شده: " . formatNumber($registeredCount);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $statsMsg]);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "ساخت چالش") {
    $data['user_states'][$chatId] = "STATE_OWNER_CHALLENGE_TEXT";
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا متن چالش را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "مشاهده چالش ها") {
    showOwnerChallenges($chatId, $data);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "ساخت کد هدیه") {
    $data['user_states'][$chatId] = "STATE_OWNER_GIFT_ITEM_COUNT";
    $data['temp_data'][$chatId] = [];
    saveData($data);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا تعداد ایتمی که میخواهید هدیه بدید را وارد کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "مشاهده هدیه ها") {
    showOwnerGifts($chatId, $data);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "انتقال منابع نامحدود") {
    $inline_keyboard = [
      [['text' => "سکه", 'callback_data' => "owner_inf_res_coin"], ['text' => "آهن", 'callback_data' => "owner_inf_res_iron"]],
      [['text' => "سنگ", 'callback_data' => "owner_inf_res_stone"], ['text' => "چوب", 'callback_data' => "owner_inf_res_wood"]],
      [['text' => "نان", 'callback_data' => "owner_inf_res_bread"], ['text' => "شهروند", 'callback_data' => "owner_inf_res_citizen"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا منبع مورد نظر برای انتقال نامحدود را انتخاب کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && in_array($text, ["پاسخگویی به گزارشات", "حذف حساب کاربران", "ban time", "ban", "un ban"])) {
    if ($text === "پاسخگویی به گزارشات") {
      $data['user_states'][$chatId] = "owner_reply_report_code";
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "لطفا کد گزارش را وارد کنید :", 'reply_markup' => getCancelKeyboard()]);
    } else if ($text === "حذف حساب کاربران") {
      $data['user_states'][$chatId] = "owner_delete_user_input";
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "نام کاربری را وارد کنید:", 'reply_markup' => getCancelKeyboard()]);
    } else if ($text === "ban time") {
      $data['user_states'][$chatId] = "owner_ban_time_user";
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "نام کاربر شخص را وارد کنید:", 'reply_markup' => getCancelKeyboard()]);
    } else if ($text === "ban") {
      $data['user_states'][$chatId] = "owner_ban_user";
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "نام کاربر شخص را وارد کنید:", 'reply_markup' => getCancelKeyboard()]);
    } else if ($text === "un ban") {
      $data['user_states'][$chatId] = "owner_unban_user";
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "لطفا نام کاربر شخص بن شده رو وارد کنید:", 'reply_markup' => getCancelKeyboard()]);
    }
    return;
  }

  if ($username === "Owner" && $state === "owner_panel" && $text === "خروج از حساب") {
    $data['users']['Owner']['chat_id'] = null;
    $data['user_states'][$chatId] = STATE_IDLE;
    saveData($data);
    
    tgCall("sendMessage", [
      'chat_id' => $chatId, 
      'text' => "با موفقیت از پنل مدیریت خارج شدید.", 
      'reply_markup' => ['remove_keyboard' => true]
    ]);
    
    $markup = [
      'inline_keyboard' => [
        [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
        [['text' => "راهنما", 'callback_data' => "btn_help"]]
      ]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
      'reply_markup' => $markup
    ]);
    return;
  }

  // --- هندلرهای متنی انصراف ---
  if ($text === "انصراف" || $text === "❌ انصراف ❌") {
    if ($username === "Owner") {
      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "عملیات لغو شد. به پنل مدیریت بازگشتید.", 'reply_markup' => getOwnerKeyboard()]);
      return;
    }

    $data['user_states'][$chatId] = STATE_IDLE;
    saveData($data);
    if ($username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "عملیات لغو شد.", 'reply_markup' => getUserKeyboard()]);
      showUserProfile($chatId, $data);
    } else {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "عملیات لغو شد.", 'reply_markup' => ['remove_keyboard' => true]]);
      $markup = [
        'inline_keyboard' => [
          [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
          [['text' => "راهنما", 'callback_data' => "btn_help"]]
        ]
      ];
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
        'reply_markup' => $markup
      ]);
    }
    return;
  }

  // --- استیت‌های پنل مدیریت ---
  if ($state === "STATE_OWNER_BROADCAST_TEXT") {
    $data['temp_data'][$chatId] = ['broadcast_text' => $text];
    $data['user_states'][$chatId] = "owner_panel";
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ثبت اطلاعات...", 'reply_markup' => getOwnerKeyboard()]);
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "owner_bc_confirm_yes"], ['text' => "خیر", 'callback_data' => "owner_bc_confirm_no"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "آیا از پیام خود مطمن هستید ؟",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if ($state === "STATE_OWNER_CHALLENGE_TEXT") {
    $data['temp_data'][$chatId] = ['challenge_text' => $text];
    $data['user_states'][$chatId] = "STATE_OWNER_CHALLENGE_CORRECT_OPT";
    saveData($data);

    $inline_keyboard = [
      [['text' => "گزینه 1", 'callback_data' => "chal_correct_1"], ['text' => "گزینه 2", 'callback_data' => "chal_correct_2"]],
      [['text' => "گزینه 3", 'callback_data' => "chal_correct_3"], ['text' => "گزینه 4", 'callback_data' => "chal_correct_4"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "گزینه درست کدام است",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if ($state === "STATE_OWNER_CHALLENGE_ITEM_COUNT") {
    $count = (int)$text;
    if ($count <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $tempData = &$data['temp_data'][$chatId];
    $tempData['chal_total_items'] = $count;
    $tempData['chal_current_idx'] = 1;
    $tempData['chal_items'] = [];
    $data['user_states'][$chatId] = "STATE_OWNER_CHALLENGE_SELECT_ITEM";
    saveData($data);

    $inline_keyboard = [
      [['text' => "ایکس پی لول", 'callback_data' => "chal_item_type_level_xp"]],
      [['text' => "سکه", 'callback_data' => "chal_item_type_coin"], ['text' => "سنگ", 'callback_data' => "chal_item_type_stone"]],
      [['text' => "آهن", 'callback_data' => "chal_item_type_iron"], ['text' => "چوب", 'callback_data' => "chal_item_type_wood"]],
      [['text' => "نان", 'callback_data' => "chal_item_type_bread"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا ایتم مورد نظر خود را انتخاب کنید \nشماره ایتم : 1",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if ($state === "STATE_OWNER_CHALLENGE_ITEM_AMT") {
    $amt = (int)$text;
    if ($amt <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $tempData = &$data['temp_data'][$chatId];
    $resKey = $tempData['selected_chal_item_res'];
    $tempData['chal_items'] = $tempData['chal_items'] ?? [];
    $tempData['chal_items'][] = ['res' => $resKey, 'amt' => $amt];

    if ($tempData['chal_current_idx'] < $tempData['chal_total_items']) {
      $tempData['chal_current_idx'] += 1;
      $data['user_states'][$chatId] = "STATE_OWNER_CHALLENGE_SELECT_ITEM";
      saveData($data);

      $inline_keyboard = [
        [['text' => "ایکس پی لول", 'callback_data' => "chal_item_type_level_xp"]],
        [['text' => "سکه", 'callback_data' => "chal_item_type_coin"], ['text' => "سنگ", 'callback_data' => "chal_item_type_stone"]],
        [['text' => "آهن", 'callback_data' => "chal_item_type_iron"], ['text' => "چوب", 'callback_data' => "chal_item_type_wood"]],
        [['text' => "نان", 'callback_data' => "chal_item_type_bread"]]
      ];
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "لطفا ایتم مورد نظر خود را انتخاب کنید \nشماره ایتم : {$tempData['chal_current_idx']}",
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
      return;
    } else {
      $chalId = "chal_" . time() . "_" . mt_rand(100, 999);
      $dt = getShamsiDateTime();

      $data['challenges'][$chalId] = [
        'id' => $chalId,
        'text' => $tempData['challenge_text'],
        'correct_option' => $tempData['challenge_correct'],
        'items' => $tempData['chal_items'] ?? [],
        'created_date' => $dt['date'],
        'created_time' => $dt['time'],
        'stats' => ['opt1' => 0, 'opt2' => 0, 'opt3' => 0, 'opt4' => 0],
        'participants' => [],
        'broadcast_messages' => []
      ];

      broadcastChallenge($chalId, $data);

      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);

      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "چالش شما با موفقیت ساخته شد",
        'reply_markup' => getOwnerKeyboard()
      ]);
      return;
    }
  }

  if ($state === "STATE_OWNER_GIFT_ITEM_COUNT") {
    $count = (int)$text;
    if ($count <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $data['temp_data'][$chatId] = [
      'gift_total_items' => $count,
      'gift_current_idx' => 1,
      'gift_items' => []
    ];
    $data['user_states'][$chatId] = "STATE_OWNER_GIFT_SELECT_ITEM";
    saveData($data);

    $inline_keyboard = [
      [['text' => "ایکس پی لول", 'callback_data' => "gift_item_type_level_xp"]],
      [['text' => "سکه", 'callback_data' => "gift_item_type_coin"], ['text' => "سنگ", 'callback_data' => "gift_item_type_stone"]],
      [['text' => "آهن", 'callback_data' => "gift_item_type_iron"], ['text' => "چوب", 'callback_data' => "gift_item_type_wood"]],
      [['text' => "نان", 'callback_data' => "gift_item_type_bread"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا ایتم مورد نظر خود را انتخاب کنید \nشماره ایتم : 1",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if ($state === "STATE_OWNER_GIFT_ITEM_AMT") {
    $amt = (int)$text;
    if ($amt <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $tempData = &$data['temp_data'][$chatId];
    $resKey = $tempData['selected_item_res'];
    $tempData['gift_items'] = $tempData['gift_items'] ?? [];
    $tempData['gift_items'][] = ['res' => $resKey, 'amt' => $amt];

    if ($tempData['gift_current_idx'] < $tempData['gift_total_items']) {
      $tempData['gift_current_idx'] += 1;
      $data['user_states'][$chatId] = "STATE_OWNER_GIFT_SELECT_ITEM";
      saveData($data);

      $inline_keyboard = [
        [['text' => "ایکس پی لول", 'callback_data' => "gift_item_type_level_xp"]],
        [['text' => "سکه", 'callback_data' => "gift_item_type_coin"], ['text' => "سنگ", 'callback_data' => "gift_item_type_stone"]],
        [['text' => "آهن", 'callback_data' => "gift_item_type_iron"], ['text' => "چوب", 'callback_data' => "gift_item_type_wood"]],
        [['text' => "نان", 'callback_data' => "gift_item_type_bread"]]
      ];
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "لطفا ایتم مورد نظر خود را انتخاب کنید \nشماره ایتم : {$tempData['gift_current_idx']}",
        'reply_markup' => ['inline_keyboard' => $inline_keyboard]
      ]);
      return;
    } else {
      $data['user_states'][$chatId] = "STATE_OWNER_GIFT_CODE_INPUT";
      saveData($data);
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "لطفا کد این هدیه را وارد کنید",
        'reply_markup' => getCancelKeyboard()
      ]);
      return;
    }
  }

  if ($state === "STATE_OWNER_GIFT_CODE_INPUT") {
    $code = trim($text);
    if (!$code) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کد هدیه نامعتبر است. لطفاً کد معتبری وارد کنید:"]);
      return;
    }

    if (isset($data['gifts'][$code])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ این کد هدیه قبلاً ثبت شده است. لطفاً کد دیگری وارد کنید:"]);
      return;
    }

    $tempData = $data['temp_data'][$chatId] ?? [];
    $dt = getShamsiDateTime();

    $data['gifts'][$code] = [
      'code' => $code,
      'items' => $tempData['gift_items'] ?? [],
      'created_date' => $dt['date'],
      'created_time' => $dt['time'],
      'claimed_users' => []
    ];

    $itemsSummary = "";
    $nameMap = ['level_xp' => "ایکس پی لول", 'coin' => "سکه", 'stone' => "سنگ", 'iron' => "آهن", 'wood' => "چوب", 'bread' => "نان"];
    foreach (($tempData['gift_items'] ?? []) as $item) {
      $resName = $nameMap[$item['res']] ?? $item['res'];
      $itemsSummary .= "{$resName} : " . formatNumber($item['amt']) . "\n";
    }

    $data['user_states'][$chatId] = "owner_panel";
    $data['temp_data'][$chatId] = [];
    saveData($data);

    $responseText = "کد هدیه شما با موفقیت ساخته شد \nکد این هدیه : {$code}\nایتم های این هدیه :\n" . trim($itemsSummary);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $responseText,
      'reply_markup' => getOwnerKeyboard()
    ]);
    return;
  }

  if (strpos($state, "STATE_OWNER_INF_TARGET_") === 0) {
    $resKey = substr($state, strlen("STATE_OWNER_INF_TARGET_"));
    $targetInput = trim($text);

    $matchedTarget = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($targetInput)) { $matchedTarget = $u; break; }
    }

    if (!$matchedTarget) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری گیرنده یافت نشد. مجدداً نام کاربری صحیح را وارد کنید:"]);
      return;
    }

    if ($matchedTarget === "Owner") {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نمی‌توانید به حساب مالک منابع انتقال دهید! نام کاربری دیگری وارد کنید:"]);
      return;
    }

    $data['temp_data'][$chatId] = $data['temp_data'][$chatId] ?? [];
    $data['temp_data'][$chatId]['owner_inf_target'] = $matchedTarget;
    $data['user_states'][$chatId] = "STATE_OWNER_INF_AMT_{$resKey}";
    saveData($data);

    $resName = RES_NAMES[$resKey] ?? $resKey;
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا مقدار {$resName} را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if (strpos($state, "STATE_OWNER_INF_AMT_") === 0) {
    $resKey = substr($state, strlen("STATE_OWNER_INF_AMT_"));
    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $targetUser = $data['temp_data'][$chatId]['owner_inf_target'] ?? '';
    $data['user_states'][$chatId] = "owner_panel";
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ثبت اطلاعات...", 'reply_markup' => getOwnerKeyboard()]);

    $resName = RES_NAMES[$resKey] ?? $resKey;
    $confirmText = "مقدار " . formatNumber($amount) . " {$resName} به کاربر {$targetUser} انتقال داده شود؟ آیا مطمئن هستید؟";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "owner_inf_confirm_yes_{$resKey}_{$amount}_{$targetUser}"], ['text' => "خیر", 'callback_data' => "owner_inf_confirm_no"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $confirmText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if ($state === "owner_reply_report_code") {
    $reportCode = trim($text);
    if (!isset($data['reports'][$reportCode])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کد گزارش یافت نشد. لطفاً کد صحیح را وارد کنید:"]);
      return;
    }

    $data['temp_data'][$chatId]['reply_report_code'] = $reportCode;
    $data['user_states'][$chatId] = "owner_reply_report_text";
    saveData($data);

    $report = $data['reports'][$reportCode];
    if ($report['status'] === "answered") {
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "ℹ️ به این گزارش قبلاً پاسخ داده شده است. می‌توانید پاسخ مجدد/جدید ارسال کنید.\n\nمتن مورد نظر خود را بنویسید :"
      ]);
    } else {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "متن مورد نظر خود را بنویسید :"]);
    }
    return;
  }

  if ($state === "owner_reply_report_text") {
    $data['temp_data'][$chatId]['reply_report_answer'] = $text;
    $data['user_states'][$chatId] = "owner_reply_report_confirm";
    saveData($data);

    $confirmMarkup = [
      'inline_keyboard' => [
        [['text' => "بله", 'callback_data' => "report_confirm_yes"], ['text' => "خیر", 'callback_data' => "report_confirm_no"]]
      ]
    ];
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "آیا از ارسال متن خود مطمئن هستید ؟", 'reply_markup' => ['remove_keyboard' => true]]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "جهت تایید نهایی انتخاب کنید:",
      'reply_markup' => $confirmMarkup
    ]);
    return;
  }

  if ($state === "owner_delete_user_input") {
    if (strtolower($text) === "owner") {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما اجازه حذف حساب کاربری مالک (Owner) را ندارید. نام کاربری دیگری وارد کنید:"]);
      return;
    }
    if (!isset($data['users'][$text])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر یافت نشد. نام کاربری دیگری وارد کرده یا دکمه انصراف را بزنید:"]);
      return;
    }

    $data['temp_data'][$chatId]['delete_target'] = $text;
    $data['user_states'][$chatId] = "owner_delete_user_confirm";
    saveData($data);

    $markup = ['inline_keyboard' => [[['text' => "بله", 'callback_data' => "delete_confirm_yes"], ['text' => "خیر", 'callback_data' => "delete_confirm_no"]]]];
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "آیا مطمئن هستید میخواهید حساب کاربر {$text} را حذف کنید ؟", 'reply_markup' => $markup]);
    return;
  }

  if ($state === "owner_ban_time_user") {
    if (strtolower($text) === "owner") {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما نمی‌توانید حساب مالک را مسدود کنید. نام کاربری دیگری وارد کنید:"]);
      return;
    }
    if (!isset($data['users'][$text])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر یافت نشد. مجدداً تلاش کنید:"]);
      return;
    }
    $data['temp_data'][$chatId]['ban_target'] = $text;
    $data['user_states'][$chatId] = "owner_ban_time_seconds";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "تعداد ثانیه هایی که میخواهید کاربر بن باشد را وارد کنید:"]);
    return;
  }

  if ($state === "owner_ban_time_seconds") {
    $seconds = (int)$text;
    if ($seconds <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح معتبر و بزرگتر از صفر وارد کنید:"]);
      return;
    }
    $data['temp_data'][$chatId]['ban_seconds'] = $seconds;
    $data['user_states'][$chatId] = "owner_ban_time_reason";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "دلیل بن شدن کاربر رو وارد کنید:"]);
    return;
  }

  if ($state === "owner_ban_time_reason") {
    $target = $data['temp_data'][$chatId]['ban_target'] ?? '';
    $seconds = $data['temp_data'][$chatId]['ban_seconds'] ?? 0;
    if (isset($data['users'][$target])) {
      $expires_at = time() + $seconds;
      $data['users'][$target]['ban'] = ['type' => "temp", 'reason' => $text, 'expires_at' => $expires_at, 'ban_message_id' => null];
      
      $target_chat_id = $data['users'][$target]['chat_id'] ?? null;
      if ($target_chat_id) {
        checkUserBanAndRespond($target_chat_id, $data);
      }

      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);

      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "✅ کاربر {$target} با موفقیت به مدت {$seconds} ثانیه مسدود شد.", 'reply_markup' => getOwnerKeyboard()]);
    }
    return;
  }

  if ($state === "owner_ban_user") {
    if (strtolower($text) === "owner") {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما نمی‌توانید حساب مالک را مسدود کنید. نام کاربری دیگری وارد کنید:"]);
      return;
    }
    if (!isset($data['users'][$text])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر یافت نشد. مجدداً تلاش کنید:"]);
      return;
    }
    $data['temp_data'][$chatId]['ban_target'] = $text;
    $data['user_states'][$chatId] = "owner_ban_reason";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "دلیل بن شدن کاربر رو وارد کنید:"]);
    return;
  }

  if ($state === "owner_ban_reason") {
    $target = $data['temp_data'][$chatId]['ban_target'] ?? '';
    if (isset($data['users'][$target])) {
      $data['users'][$target]['ban'] = ['type' => "perm", 'reason' => $text, 'ban_message_id' => null];
      
      $target_chat_id = $data['users'][$target]['chat_id'] ?? null;
      if ($target_chat_id) {
        checkUserBanAndRespond($target_chat_id, $data);
      }

      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);

      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "✅ کاربر {$target} با موفقیت به صورت دائمی مسدود شد.", 'reply_markup' => getOwnerKeyboard()]);
    }
    return;
  }

  if ($state === "owner_unban_user") {
    $matchedUser = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($text)) { $matchedUser = $u; break; }
    }
    if (!$matchedUser) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر یافت نشد. مجدداً تلاش کنید:"]);
      return;
    }
    $user_info = &$data['users'][$matchedUser];
    $ban_info = $user_info['ban'] ?? null;

    if ($ban_info) {
      $target_chat_id = $user_info['chat_id'] ?? null;
      $old_ban_msg_id = $ban_info['ban_message_id'] ?? null;

      unset($user_info['ban']);
      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);

      if ($target_chat_id) {
        if ($old_ban_msg_id) {
          tgCall("deleteMessage", ['chat_id' => $target_chat_id, 'message_id' => $old_ban_msg_id]);
        }
        tgCall("sendMessage", [
          'chat_id' => $target_chat_id,
          'text' => "حساب شما باز شده است.",
          'reply_markup' => getUserKeyboard()
        ]);
      }

      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "✅ کاربر {$matchedUser} حسابش باز شد.", 'reply_markup' => getOwnerKeyboard()]);
    } else {
      $data['user_states'][$chatId] = "owner_panel";
      $data['temp_data'][$chatId] = [];
      saveData($data);
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ℹ️ کاربر {$matchedUser} از پیش فعال بود و محروم نبود.", 'reply_markup' => getOwnerKeyboard()]);
    }
    return;
  }

  // --- دکمه‌های کیبورد بازی برای کاربران عادی ---
  if ($text === "مشخصات شما" || $text === "👤 مشخصات شما 👤") {
    showUserProfile($chatId, $data);
    return;
  }

  if ($text === "راهنمای بازی" || $text === "📖 راهنمای بازی 📖") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    showGameGuideMenu($chatId, $data);
    return;
  }

  if ($text === "برترین ها" || $text === "🏆 برترین ها 🏆") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    showLeaderboard($chatId, $data);
    return;
  }

  if ($text === "کارگاه" || $text === "🛠️ کارگاه 🛠️") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $user_info = &$data['users'][$username];
    initUserGameData($user_info);

    $inline_keyboard = [];
    foreach (($user_info['workers'] ?? []) as $w_id => $worker) {
      $statusText = "بیکار";
      if ($worker['status'] === "building") {
        $statusText = "درحال ساخت " . BUILDING_STATS[$worker['building']]['name'];
      }
      $inline_keyboard[] = [['text' => "کارگر شماره {$w_id} ({$statusText})", 'callback_data' => "worker_click_{$w_id}"]];
    }
    $inline_keyboard[] = [['text' => "ساخت کارگر", 'callback_data' => "create_worker_start"]];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
    }

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا روی یکی از کارگر های زیر کلیک کنید",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
    return;
  }

  if ($text === "انتقال منابع") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $user_info = &$data['users'][$username];
    initUserGameData($user_info);

    $hasMarket = false;
    foreach (($user_info['regions'] ?? []) as $reg) {
      if ($reg && $reg['type'] === "market") {
        $hasMarket = true;
        break;
      }
    }

    if (!$hasMarket) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ برای انتقال منابع ابتدا باید سازه بازار را بسازید."]);
      return;
    }

    $inline_keyboard = [
      [['text' => "سکه", 'callback_data' => "tx_res_coin"], ['text' => "آهن", 'callback_data' => "tx_res_iron"]],
      [['text' => "سنگ", 'callback_data' => "tx_res_stone"], ['text' => "چوب", 'callback_data' => "tx_res_wood"]],
      [['text' => "نان", 'callback_data' => "tx_res_bread"]]
    ];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
    }

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا یکی از منابع زیر را انتخاب کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
    return;
  }

  if ($text === "ارسال پیام" || $text === "📩 ارسال پیام 📩") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $data['user_states'][$chatId] = "STATE_MSG_TARGET";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا نام کاربری گیرنده را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($text === "صندوق ایکس پی" || $text === "📦 صندوق ایکس پی 📦") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $user_info = &$data['users'][$username];
    initUserGameData($user_info);
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];

    $inline_keyboard = [
      [['text' => "ایکس پی لول", 'callback_data' => "vault_view_level"], ['text' => "ایکس پی رنک", 'callback_data' => "vault_view_rank"]]
    ];

    $old_msg_id = $data['last_menu_msg'][$chatId] ?? null;
    if ($old_msg_id) {
      tgCall("deleteMessage", ['chat_id' => $chatId, 'message_id' => $old_msg_id]);
    }

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "صندوق مورد نظر خود را انتخاب کنید",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
    return;
  }

  if ($text === "دریافت هدیه روزانه" || $text === "🎁 دریافت هدیه روزانه 🎁") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $user_info = &$data['users'][$username];
    initUserGameData($user_info);
    $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];

    $now = time();
    $lastGift = $user_info['last_daily_gift'] ?? 0;
    $elapsed = $now - $lastGift;

    if ($elapsed < DAILY_GIFT_COOLDOWN) {
      $remaining = (int)(DAILY_GIFT_COOLDOWN - $elapsed);
      $timeStr = formatSecondsToDHMS($remaining);
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "⚠️ شما قبلاً هدیه روزانه خود را دریافت کرده‌اید.\nزمان باقی‌مانده تا دریافت بعدی: {$timeStr}"
      ]);
      return;
    }

    $user_info['last_daily_gift'] = $now;
    $user_info['xp_vault']['level_xp'] += 5;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "۵ ایکس پی لول به صندوق شما اضافه شد"]);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "🎁 هدیه روزانه ۵ ایکس پی لول با موفقیت به صندوق شما واریز گردید!"]);
    return;
  }

  if ($text === "کد هدیه" || $text === "🎟️ کد هدیه 🎟️") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $data['user_states'][$chatId] = "STATE_USER_GIFT_CODE_INPUT";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا کد هدیه را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($text === "تغییر رمز عبور" || $text === "🔑 تغییر رمز عبور 🔑") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $user_info = &$data['users'][$username];
    $now = time();
    $lastChange = $user_info['last_pass_change'] ?? 0;

    if ($now - $lastChange < PASSWORD_CHANGE_COOLDOWN) {
      $remaining = (int)(PASSWORD_CHANGE_COOLDOWN - ($now - $lastChange));
      $timeStr = formatSecondsToDHMS($remaining);
      tgCall("sendMessage", [
        'chat_id' => $chatId,
        'text' => "⚠️ شما به محدودیت زمانی تغییر رمز عبور برخوردید.\nزمان باقی‌مانده تا امکان تغییر مجدد: {$timeStr}"
      ]);
      return;
    }

    $data['user_states'][$chatId] = "STATE_CHANGE_PASS_CURR";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا رمز فعلی خود را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($text === "گزارش به پشتیبانی" || $text === "🎧 گزارش به پشتیبانی 🎧") {
    if (!$username) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ابتدا وارد حساب خود شوید."]);
      return;
    }
    $data['user_states'][$chatId] = "STATE_SUPPORT_REPORT_TEXT";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا متنی که میخواهید به پشتیبانی بدهید را ارسال کنید",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($text === "عقب نشینی از حمله" || $text === "🛡️ عقب نشینی از حمله 🛡️") {
    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "عملیات عقب‌نشینی با موفقیت انجام شد.",
      'reply_markup' => getUserKeyboard()
    ]);
    showUserProfile($chatId, $data);
    return;
  }

  if ($text === "خروج از حساب" || $text === "🚪 خروج از حساب 🚪") {
    if ($username) {
      $data['users'][$username]['chat_id'] = null;
    }
    $data['user_states'][$chatId] = STATE_IDLE;
    saveData($data);
    
    tgCall("sendMessage", [
      'chat_id' => $chatId, 
      'text' => "شما با موفقیت از حساب خود خارج شدید.", 
      'reply_markup' => ['remove_keyboard' => true]
    ]);
    
    $markup = [
      'inline_keyboard' => [
        [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
        [['text' => "راهنما", 'callback_data' => "btn_help"]]
      ]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
      'reply_markup' => $markup
    ]);
    return;
  }

  // --- مدیریت ورودی‌های تغییر رمز عبور ---
  if ($state === "STATE_CHANGE_PASS_CURR") {
    $user_info = &$data['users'][$username];
    if ($text !== $user_info['password']) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ رمز عبور فعلی اشتباه است. مجدداً وارد کنید:"]);
      return;
    }

    $data['user_states'][$chatId] = "STATE_CHANGE_PASS_NEW";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "لطفا رمز عبور جدید خود را وارد کنید :", 'reply_markup' => getCancelKeyboard()]);
    return;
  }

  if ($state === "STATE_CHANGE_PASS_NEW") {
    if (strlen($text) < 4 || strlen($text) > 10) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ رمز عبور جدید باید بین ۴ تا ۱۰ کاراکتر باشد. رمز دیگری وارد کنید:"]);
      return;
    }

    $data['temp_data'][$chatId]['new_pass'] = $text;
    $data['user_states'][$chatId] = "STATE_CHANGE_PASS_CONFIRM";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "رمز عبور جدید خود را دوباره وارد کنید :", 'reply_markup' => getCancelKeyboard()]);
    return;
  }

  if ($state === "STATE_CHANGE_PASS_CONFIRM") {
    $newPass = $data['temp_data'][$chatId]['new_pass'] ?? '';
    if ($text !== $newPass) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ رمز عبور جدید و تکرار آن مطابقت ندارند. مجدداً تکرار آن را وارد کنید:"]);
      return;
    }

    $user_info = &$data['users'][$username];
    $user_info['password'] = $newPass;
    $user_info['last_pass_change'] = time();

    if (!empty($user_info['channel_msg_id'])) {
      $joinDate = date('Y-m-d H:i:s', ($user_info['created_timestamp'] ?? (time() * 1000)) / 1000);
      $channelText = "نام کاربری : {$username}\nرمز عبور: {$newPass}\nتاریخ ورود : {$joinDate}";
      tgCall("editMessageText", ['chat_id' => CHANNEL_ID, 'message_id' => $user_info['channel_msg_id'], 'text' => $channelText]);
    }

    unset($data['temp_data'][$chatId]['new_pass']);
    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "🎉 رمز عبور شما با موفقیت تغییر کرد.\n\nرمز عبور جدید شما:\n<code>{$newPass}</code>",
      'parse_mode' => 'HTML',
      'reply_markup' => getUserKeyboard()
    ]);
    showUserProfile($chatId, $data);
    return;
  }

  // --- دریافت کد هدیه توسط کاربر ---
  if ($state === "STATE_USER_GIFT_CODE_INPUT") {
    $code = trim($text);
    $gifts = $data['gifts'] ?? [];
    $gift = &$gifts[$code];

    if (!$gift || ($gift['status'] ?? '') === "expired" || (isset($gift['claimed_users']) && in_array($username, $gift['claimed_users']))) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "کد هدیه موجود نیست"]);
      return;
    }

    $gift['claimed_users'] = $gift['claimed_users'] ?? [];
    $gift['claimed_users'][] = $username;

    $user_info = &$data['users'][$username];
    initUserGameData($user_info);

    $levelXpGained = 0;
    $itemsText = "";
    $nameMap = ['level_xp' => "ایکس پی لول", 'coin' => "سکه", 'stone' => "سنگ", 'iron' => "آهن", 'wood' => "چوب", 'bread' => "نان", 'citizen' => "شهروند"];

    foreach (($gift['items'] ?? []) as $item) {
      $resName = $nameMap[$item['res']] ?? $item['res'];
      $itemsText .= "{$resName} : " . formatNumber($item['amt']) . "\n";

      if ($item['res'] === "level_xp") {
        $levelXpGained += $item['amt'];
        $user_info['xp_vault'] = $user_info['xp_vault'] ?? ['level_xp' => 0, 'rank_xp' => 0];
        $user_info['xp_vault']['level_xp'] += $item['amt'];
      } else {
        $user_info['resources'][$item['res']] = ($user_info['resources'][$item['res']] ?? 0) + $item['amt'];
      }
    }

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    $successMsg = "کد هدیه شما درست بود و این هدیه ها برای شما است :\n\n" . trim($itemsText);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $successMsg, 'reply_markup' => getUserKeyboard()]);

    if ($levelXpGained > 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "{$levelXpGained} ایکس پی لول به صندوق شما اضافه شد"]);
    }
    return;
  }

  // --- ارسال گزارش به پشتیبانی ---
  if ($state === "STATE_SUPPORT_REPORT_TEXT") {
    $reportCode = (string)time() . (string)mt_rand(100000, 999999);
    $dt = getShamsiDateTime();

    $channelText = "نام کاربر : {$username}\nتاریخ : {$dt['date']}\nساعت : {$dt['time']}\n\nگزارش : {$text}\nکد : <code>{$reportCode}</code>";

    $data['reports'][$reportCode] = [
      'username' => $username,
      'user_chat_id' => $chatId,
      'date' => $dt['date'],
      'time' => $dt['time'],
      'text' => $text,
      'status' => "pending",
      'answer' => null
    ];

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => REPORT_CHANNEL_ID,
      'text' => $channelText,
      'parse_mode' => 'HTML'
    ]);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "✅ گزارش شما با موفقیت ارسال شد.",
      'reply_markup' => getUserKeyboard()
    ]);
    return;
  }

  // --- سیستم چت و پیام‌رسانی ---
  if ($state === "STATE_MSG_TARGET") {
    $targetInput = trim($text);
    $matchedTarget = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($targetInput)) { $matchedTarget = $u; break; }
    }

    if (!$matchedTarget) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری گیرنده یافت نشد. مجدداً وارد کنید:"]);
      return;
    }

    if (strtolower($matchedTarget) === strtolower($username)) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما نمی‌توانید به خودتان پیام دهید!"]);
      return;
    }

    $targetInfo = $data['users'][$matchedTarget] ?? null;
    if ($targetInfo && !empty($targetInfo['ban'])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر مورد نظر مسدود است و امکان ارسال پیام به وی وجود ندارد."]);
      return;
    }

    $data['temp_data'][$chatId]['msg_target'] = $matchedTarget;
    $data['user_states'][$chatId] = "STATE_MSG_TEXT";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا پیام خود را بنویسید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if ($state === "STATE_MSG_TEXT") {
    $lines = count(explode("\n", $text));
    if ($lines > 10) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ متن پیام نمی‌تواند بیشتر از ۱۰ خط باشد. پیام کوتاه‌تری بفرستید:"]);
      return;
    }

    $data['temp_data'][$chatId]['msg_body'] = $text;
    $data['user_states'][$chatId] = ($username === "Owner") ? "owner_panel" : STATE_LOGGED_IN;
    saveData($data);

    $mainKeyboard = ($username === "Owner") ? getOwnerKeyboard() : getUserKeyboard();
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ثبت پیام...", 'reply_markup' => $mainKeyboard]);

    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "msg_send_yes"], ['text' => "خیر", 'callback_data' => "msg_send_no"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "آیا از پیام خود مطمن هستید ؟",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if (strpos($state, "STATE_MSG_REPLY_TEXT_") === 0) {
    $origMsgId = substr($state, strlen("STATE_MSG_REPLY_TEXT_"));
    $lines = count(explode("\n", $text));
    if ($lines > 10) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ متن پاسخ نمی‌تواند بیشتر از ۱۰ خط باشد. لطفاً پاسخ کوتاه‌تری ارسال کنید:"]);
      return;
    }

    $data['temp_data'][$chatId]['reply_body'] = $text;
    $data['user_states'][$chatId] = ($username === "Owner") ? "owner_panel" : STATE_LOGGED_IN;
    saveData($data);

    $mainKeyboard = ($username === "Owner") ? getOwnerKeyboard() : getUserKeyboard();
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "ثبت پاسخ...", 'reply_markup' => $mainKeyboard]);

    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "msg_reply_send_yes_{$origMsgId}"], ['text' => "خیر", 'callback_data' => "msg_reply_send_no"]]
    ];
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "آیا از پاسخ خود مطمن هستید ؟",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  // --- سیستم انتخاب حریف حمله هدفمند ---
  if (strpos($state, "STATE_ATTACK_TARGET_USER_") === 0) {
    $sourceRegId = substr($state, strlen("STATE_ATTACK_TARGET_USER_"));
    $targetInput = trim($text);

    $matchedTarget = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($targetInput)) { $matchedTarget = $u; break; }
    }

    if (!$matchedTarget) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری حریف یافت نشد. مجدداً نام کاربری صحیح را وارد کنید:"]);
      return;
    }

    if (strtolower($matchedTarget) === strtolower($username)) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما نمی‌توانید به خودتان حمله کنید!"]);
      return;
    }

    if ($matchedTarget === "Owner") {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ امکان حمله به مالک ربات وجود ندارد!"]);
      return;
    }

    $targetInfo = $data['users'][$matchedTarget] ?? null;
    if ($targetInfo && !empty($targetInfo['ban'])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر مورد نظر مسدود است و امکان حمله به وی وجود ندارد."]);
      return;
    }

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    $retreatKeyboard = [
      'keyboard' => [[['text' => "عقب نشینی از حمله"]]],
      'resize_keyboard' => true
    ];

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "حریف تایید شد. جهت لغو می‌توانید از دکمه زیر استفاده کنید:",
      'reply_markup' => $retreatKeyboard
    ]);

    $cityCount = $targetInfo['city_count'] ?? 1;
    $inline_keyboard = [];
    for ($c = 1; $c <= $cityCount; $c++) {
      $inline_keyboard[] = [['text' => "شهر {$c}", 'callback_data' => "attack_select_city_{$sourceRegId}_{$matchedTarget}_{$c}_target"]];
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
    return;
  }

  // --- سیستم معامله و انتقال مستقیم ---
  if (strpos($state, "STATE_TRADE_GIVE_AMT_") === 0) {
    $resKey = substr($state, strlen("STATE_TRADE_GIVE_AMT_"));
    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $user_info = &$data['users'][$username];
    $myAmount = $user_info['resources'][$resKey] ?? 0;
    if ($myAmount < $amount) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما این مقدار " . RES_NAMES[$resKey] . " ندارید. موجودی شما: {$myAmount}"]);
      return;
    }

    $inline_keyboard = [
      [['text' => "سکه", 'callback_data' => "trade_getres_coin_{$resKey}_{$amount}"], ['text' => "آهن", 'callback_data' => "trade_getres_iron_{$resKey}_{$amount}"]],
      [['text' => "سنگ", 'callback_data' => "trade_getres_stone_{$resKey}_{$amount}"], ['text' => "چوب", 'callback_data' => "trade_getres_wood_{$resKey}_{$amount}"]],
      [['text' => "نان", 'callback_data' => "trade_getres_bread_{$resKey}_{$amount}"]]
    ];

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "تعداد ثبت شد.", 'reply_markup' => getUserKeyboard()]);

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا منبعی که میخواهید بگیرید را وارد کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
    return;
  }

  if (strpos($state, "STATE_TRADE_GET_AMT_") === 0) {
    $parts = explode("_", $state);
    $giveResKey = $parts[4];
    $giveAmt = (int)$parts[5];
    $getResKey = $parts[6];

    $getAmt = (int)$text;
    if ($getAmt <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $data['user_states'][$chatId] = "STATE_TRADE_TARGET_USER_{$giveResKey}_{$giveAmt}_{$getResKey}_{$getAmt}";
    saveData($data);

    $confirmText = "معامله شما با موفقیت ساخته شد \nمقدار " . RES_NAMES[$giveResKey] . " که میدهید : " . formatNumber($giveAmt) . "\nمقدار " . RES_NAMES[$getResKey] . " ایی که میگیرید : " . formatNumber($getAmt) . "\nلطفا نام کاربری شخصی که میخواهید این معامله را برایش ارسال کنید را وارد کنید :";

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $confirmText,
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if (strpos($state, "STATE_TRADE_TARGET_USER_") === 0) {
    $parts = explode("_", $state);
    $giveResKey = $parts[4];
    $giveAmt = (int)$parts[5];
    $getResKey = $parts[6];
    $getAmt = (int)$parts[7];

    $targetInput = trim($text);
    $matchedTarget = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($targetInput)) { $matchedTarget = $u; break; }
    }

    if (!$matchedTarget) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری گیرنده یافت نشد. مجدداً نام کاربری صحیح را وارد کنید:"]);
      return;
    }

    if (strtolower($matchedTarget) === strtolower($username)) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما نمی‌توانید با خودتان معامله کنید!"]);
      return;
    }

    $target_chat_id = $data['users'][$matchedTarget]['chat_id'] ?? null;
    if (!$target_chat_id) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ کاربر مورد نظر در حال حاضر فعال نیست یا چت آیدی ندارد."]);
      return;
    }

    $data['user_states'][$chatId] = STATE_LOGGED_IN;

    $tradeId = "trade_" . time() . "_" . mt_rand(100, 999);
    $data['trades'][$tradeId] = [
      'sender' => $username,
      'target' => $matchedTarget,
      'give_res' => $giveResKey,
      'give_amt' => $giveAmt,
      'get_res' => $getResKey,
      'get_amt' => $getAmt,
      'timestamp' => time(),
      'expire_time' => time() + TRADE_EXPIRE_TIME,
      'status' => "pending",
      'target_msg_id' => null,
      'sender_chat_id' => $chatId
    ];

    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "معامله برای شخص ارسال شد و در انتظار تایید است.",
      'reply_markup' => getUserKeyboard()
    ]);

    $targetText = "کاربر {$username} برای شما این معامله رو فرستاد \nمقدار " . RES_NAMES[$getResKey] . " ایی که میدهید : " . formatNumber($getAmt) . "\nمقدار " . RES_NAMES[$giveResKey] . " ایی که میگیرید : " . formatNumber($giveAmt) . "\nآیا این معامله را قبول میکنید ؟";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "trade_accept_{$tradeId}"], ['text' => "خیر", 'callback_data' => "trade_reject_{$tradeId}"]]
    ];

    $targetRes = tgCall("sendMessage", [
      'chat_id' => $target_chat_id,
      'text' => $targetText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($targetRes['ok'])) {
      $data['trades'][$tradeId]['target_msg_id'] = $targetRes['result']['message_id'];
      saveData($data);
    }
    return;
  }

  if (strpos($state, "STATE_TRANSFER_AMT_") === 0) {
    $resKey = substr($state, strlen("STATE_TRANSFER_AMT_"));
    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $user_info = &$data['users'][$username];
    $myAmount = $user_info['resources'][$resKey] ?? 0;
    if ($myAmount < $amount) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما این مقدار " . RES_NAMES[$resKey] . " ندارید. موجودی شما: {$myAmount}"]);
      return;
    }

    $data['user_states'][$chatId] = "STATE_TRANSFER_TARGET_{$resKey}_{$amount}";
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا نام کاربری شخص گیرنده را وارد کنید :",
      'reply_markup' => getCancelKeyboard()
    ]);
    return;
  }

  if (strpos($state, "STATE_TRANSFER_TARGET_") === 0) {
    $parts = explode("_", $state);
    $resKey = $parts[3];
    $amount = (int)$parts[4];

    $targetInput = trim($text);
    $matchedTarget = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($targetInput)) { $matchedTarget = $u; break; }
    }

    if (!$matchedTarget) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری گیرنده یافت نشد. مجدداً وارد کنید:"]);
      return;
    }

    if (strtolower($matchedTarget) === strtolower($username)) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما نمی‌توانید به خودتان منابع انتقال دهید!"]);
      return;
    }

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "بررسی اطلاعات انتقال...", 'reply_markup' => getUserKeyboard()]);

    $confirmText = "مقدار " . RES_NAMES[$resKey] . " که میخواهید بدهید : " . formatNumber($amount) . "\nنام کاربری شخص گیرنده : {$matchedTarget}\nآیا از انتقال خود مطمن هستید؟";
    $inline_keyboard = [
      [['text' => "بله", 'callback_data' => "transfer_confirm_yes_{$resKey}_{$amount}_{$matchedTarget}"], ['text' => "خیر", 'callback_data' => "transfer_confirm_no"]]
    ];

    $res = tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => $confirmText,
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    if (!empty($res['ok'])) {
      $data['last_menu_msg'][$chatId] = $res['result']['message_id'];
      saveData($data);
    }
    return;
  }

  // --- سیستم ساخت نیرو و جابجایی نیرو ---
  if (strpos($state, "STATE_RECRUIT_AMT_") === 0) {
    $parts = explode("_", $state);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 3, count($parts) - 4));
    $tData = TROOP_STATS[$tKey];

    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $costStr = "";
    if (!empty($tData['cost']['bread'])) $costStr .= ($tData['cost']['bread'] * $amount) . " نان \n";
    if (!empty($tData['cost']['citizen'])) $costStr .= ($tData['cost']['citizen'] * $amount) . " شهروند \n";
    if (!empty($tData['cost']['wood'])) $costStr .= ($tData['cost']['wood'] * $amount) . " چوب \n";
    if (!empty($tData['cost']['stone'])) $costStr .= ($tData['cost']['stone'] * $amount) . " سنگ \n";
    if (!empty($tData['cost']['iron'])) $costStr .= ($tData['cost']['iron'] * $amount) . " آهن \n";

    $summaryText = "تعداد نیرو ها : {$amount}\nقدرت : " . ($amount * $tData['power']) . "\nاستقامت : " . ($amount * $tData['stamina']) . "\nانرژی : " . ($amount * $tData['energy']) . "\n\nهزینه : \n{$costStr}آیا از کار خود مطمئن هستید ؟";

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $summaryText, 'reply_markup' => getUserKeyboard()]);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "تایید نهایی ساخت نیرو:",
      'reply_markup' => [
        'inline_keyboard' => [
          [['text' => "بله", 'callback_data' => "recruit_confirm_yes_{$tKey}_{$amount}_{$regId}"], ['text' => "خیر", 'callback_data' => "recruit_confirm_no_{$regId}"]]
        ]
      ]
    ]);
    return;
  }

  if (strpos($state, "STATE_MOVE_TROOPS_AMT_") === 0) {
    $parts = explode("_", $state);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 4, count($parts) - 5));
    $tData = TROOP_STATS[$tKey];

    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $user_info = &$data['users'][$username];
    $reg = &$user_info['regions'][$regId];
    $currentTroops = $reg && !empty($reg['troops']) ? ($reg['troops'][$tKey] ?? 0) : 0;

    if ($currentTroops < $amount) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما این تعداد {$tData['name']} ندارید. تعداد کل شما در این منطقه {$currentTroops} است."]);
      return;
    }

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "تعداد تایید شد.", 'reply_markup' => getUserKeyboard()]);

    $cityCount = $user_info['city_count'] ?? 1;
    $inline_keyboard = [];
    for ($c = 1; $c <= $cityCount; $c++) {
      $inline_keyboard[] = [['text' => "🏛️ شهر {$c} 🏛️", 'callback_data' => "troop_move_city_{$tKey}_{$regId}_{$c}_{$amount}"]];
    }
    $inline_keyboard[] = [['text' => "بازگشت", 'callback_data' => "troops_detail_{$tKey}_{$regId}"]];

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "لطفا شهر خود را انتخاب کنید :",
      'reply_markup' => ['inline_keyboard' => $inline_keyboard]
    ]);
    return;
  }

  if (strpos($state, "STATE_DISMISS_TROOPS_AMT_") === 0) {
    $parts = explode("_", $state);
    $regId = end($parts);
    $tKey = implode("_", array_slice($parts, 4, count($parts) - 5));
    $tData = TROOP_STATS[$tKey];

    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفا یک عدد صحیح و بزرگتر از صفر وارد کنید:"]);
      return;
    }

    $user_info = &$data['users'][$username];
    $reg = &$user_info['regions'][$regId];
    $currentTroops = $reg && !empty($reg['troops']) ? ($reg['troops'][$tKey] ?? 0) : 0;

    if ($currentTroops < $amount) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما این تعداد {$tData['name']} ندارید. تعداد کل شما در این منطقه {$currentTroops} است."]);
      return;
    }

    $reg['troops'][$tKey] -= $amount;
    $user_info['resources']['citizen'] = ($user_info['resources']['citizen'] ?? 0) + $amount;
    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "✅ تعداد {$amount} {$tData['name']} با موفقیت معاف شدند و به شهروندان شما بازگشتند.",
      'reply_markup' => getUserKeyboard()
    ]);
    showUserProfile($chatId, $data);
    return;
  }

  // --- سیستم خرید و فروش بازار با مقدار دلخواه ---
  if (strpos($state, "market_buy_amt_") === 0) {
    $parts = explode("_", $state);
    $resKey = $parts[3];
    $regId = $parts[4];

    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید یا دکمه انصراف را بزنید:"]);
      return;
    }

    $rate = MARKET_RATES[$resKey];
    $totalCost = $rate['buy'] * $amount;
    $user_info = &$data['users'][$username];
    $myResource = $user_info['resources'][$resKey] ?? 0;

    $summaryText = "مقدار {$rate['name']} شما : " . formatNumber($myResource) . "\nمقدار درخواستی : " . formatNumber($amount) . "\nقیمت خرید هر یک {$rate['name']} : " . formatNumber($rate['buy']) . " سکه\nقیمت کل : " . formatNumber($totalCost) . " سکه\nموجوزی سکه شما : " . formatNumber($user_info['resources']['coin'] ?? 0) . " سکه\n\nآیا از خرید خود اطمینان دارید ؟";

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $summaryText, 'reply_markup' => getUserKeyboard()]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "تایید تراکنش:",
      'reply_markup' => [
        'inline_keyboard' => [
          [['text' => "بله", 'callback_data' => "m_buy_yes_{$resKey}_{$amount}_{$regId}"], ['text' => "خیر", 'callback_data' => "m_buy_no_{$regId}"]]
        ]
      ]
    ]);
    return;
  }

  if (strpos($state, "market_sell_amt_") === 0) {
    $parts = explode("_", $state);
    $resKey = $parts[3];
    $regId = $parts[4];

    $amount = (int)$text;
    if ($amount <= 0) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ لطفاً یک عدد صحیح و بزرگتر از صفر وارد کنید یا دکمه انصراف را بزنید:"]);
      return;
    }

    $rate = MARKET_RATES[$resKey];
    $totalEarnAmount = $rate['sell'] * $amount;
    $user_info = &$data['users'][$username];
    $myResource = $user_info['resources'][$resKey] ?? 0;

    if ($myResource < $amount) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ شما این مقدار " . MARKET_RATES[$resKey]['name'] . " را ندارید. مقدار موجودی شما " . formatNumber($myResource) . " است."]);
      return;
    }

    $summaryText = "مقدار {$rate['name']} شما : " . formatNumber($myResource) . "\nمقدار برای فروش : " . formatNumber($amount) . "\nقیمت فروش هر یک {$rate['name']} : " . formatNumber($rate['sell']) . " سکه\nقیمت کل درآمد : " . formatNumber($totalEarnAmount) . " سکه\n\nآیا از فروش خود اطمینان دارید ؟";

    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    saveData($data);

    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $summaryText, 'reply_markup' => getUserKeyboard()]);
    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "تایید تراکنش:",
      'reply_markup' => [
        'inline_keyboard' => [
          [['text' => "بله", 'callback_data' => "m_sell_yes_{$resKey}_{$amount}_{$regId}"], ['text' => "خیر", 'callback_data' => "m_sell_no_{$regId}"]]
        ]
      ]
    ]);
    return;
  }

  // --- سیستم ثبت‌نام و ورود ---
  if ($state === "reg_user") {
    if (strtolower($text) === "owner" || strlen($text) < 4 || strlen($text) > 10 || !preg_match('/^[a-zA-Z0-9_]+$/', $text)) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری باید بین ۴ تا ۱۰ کاراکتر و فقط حروف انگلیسی/اعداد باشد. مجدداً وارد کنید:"]);
      return;
    }
    if (isset($data['users'][$text])) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ این نام کاربری قبلاً ثبت شده است. نام کاربری دیگری انتخاب کنید:"]);
      return;
    }
    $data['temp_data'][$chatId]['username'] = $text;
    $data['user_states'][$chatId] = "reg_pass";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "لطفا رمز نام کاربری خود را وارد کنید :"]);
    return;
  }

  if ($state === "reg_pass") {
    $data['temp_data'][$chatId]['password'] = $text;
    $data['user_states'][$chatId] = "reg_pass_confirm";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "رمز عبور خود را دوباره وارد کنید :"]);
    return;
  }

  if ($state === "reg_pass_confirm") {
    if ($text !== ($data['temp_data'][$chatId]['password'] ?? '')) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ رمز عبور مطابقت ندارد. لطفا مجدداً تکرار رمز عبور را وارد کنید:"]);
      return;
    }
    $uName = $data['temp_data'][$chatId]['username'];
    $pwd = $data['temp_data'][$chatId]['password'];

    $dt = getShamsiDateTime();
    $joinDate = date('Y-m-d H:i:s');
    $channelText = "نام کاربری : {$uName}\nرمز عبور: {$pwd}\nتاریخ ورود : {$joinDate}";
    $channelMsgId = null;
    $chRes = tgCall("sendMessage", ['chat_id' => CHANNEL_ID, 'text' => $channelText]);
    if (!empty($chRes['ok'])) {
      $channelMsgId = $chRes['result']['message_id'];
    }

    $data['users'][$uName] = [
      'password' => $pwd,
      'chat_id' => $chatId,
      'registered_chat_id' => $chatId,
      'channel_msg_id' => $channelMsgId,
      'reg_date' => $dt['date'],
      'reg_time' => $dt['time'],
      'login_date' => $dt['date'],
      'login_time' => $dt['time'],
      'created_timestamp' => time() * 1000
    ];
    initUserGameData($data['users'][$uName]);
    
    $data['user_states'][$chatId] = STATE_LOGGED_IN;
    $data['temp_data'][$chatId] = [];
    saveData($data);

    tgCall("sendMessage", [
      'chat_id' => $chatId,
      'text' => "🎉 حساب کاربری شما با نام {$uName} و رمز {$pwd} با موفقیت ساخته شد.",
      'reply_markup' => getUserKeyboard()
    ]);
    showUserProfile($chatId, $data);
    return;
  }

  if ($state === "login_user") {
    $matchedUsername = null;
    foreach (array_keys($data['users']) as $u) {
      if (strtolower($u) === strtolower($text)) { $matchedUsername = $u; break; }
    }
    if (!$matchedUsername) {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ نام کاربری یافت نشد. مجدداً تلاش کنید یا دکمه انصراف را بزنید:"]);
      return;
    }

    $target_user_info = &$data['users'][$matchedUsername];
    if (!empty($target_user_info['ban'])) {
      $ban_info = $target_user_info['ban'];
      $ban_type = $ban_info['type'];
      $reason = $ban_info['reason'] ?? "نامشخص";

      if ($ban_type === "temp") {
        $expires_at = $ban_info['expires_at'] ?? 0;
        $now = time();
        if ($now >= $expires_at) {
          unset($target_user_info['ban']);
          saveData($data);
        } else {
          $remaining = (int)($expires_at - $now);
          $timeStr = formatSecondsToDHMS($remaining);
          $msg_text = "این حساب تا اطلاع ثانوی بسته شده است.\nدلیل: {$reason}\nزمان اتمام محرومیت: {$timeStr}";
          tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $msg_text, 'reply_markup' => ['remove_keyboard' => true]]);
          
          $data['user_states'][$chatId] = STATE_IDLE;
          $data['temp_data'][$chatId] = [];
          saveData($data);

          $markup = [
            'inline_keyboard' => [
              [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
              [['text' => "راهنما", 'callback_data' => "btn_help"]]
            ]
          ];
          tgCall("sendMessage", [
            'chat_id' => $chatId,
            'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
            'reply_markup' => $markup
          ]);
          return;
        }
      } else if ($ban_type === "perm") {
        $msg_text = "این حساب به صورت دائم بسته شده است.\nدلیل: {$reason}";
        tgCall("sendMessage", ['chat_id' => $chatId, 'text' => $msg_text, 'reply_markup' => ['remove_keyboard' => true]]);
        
        $data['user_states'][$chatId] = STATE_IDLE;
        $data['temp_data'][$chatId] = [];
        saveData($data);

        $markup = [
          'inline_keyboard' => [
            [['text' => "ثبت نام", 'callback_data' => "btn_register"], ['text' => "ورود", 'callback_data' => "btn_login"]],
            [['text' => "راهنما", 'callback_data' => "btn_help"]]
          ]
        ];
        tgCall("sendMessage", [
          'chat_id' => $chatId,
          'text' => "به ربات گیمینگ Wielder of Power خوش آمدید لطفا روی یکی از دکمه های زیر کلیک کنید :",
          'reply_markup' => $markup
        ]);
        return;
      }
    }

    $data['temp_data'][$chatId]['login_username'] = $matchedUsername;
    $data['user_states'][$chatId] = "login_pass";
    saveData($data);
    tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "رمز عبور خود را وارد کنید :"]);
    return;
  }

  if ($state === "login_pass") {
    $uName = $data['temp_data'][$chatId]['login_username'] ?? '';
    if (isset($data['users'][$uName]) && $data['users'][$uName]['password'] === $text) {
      foreach ($data['users'] as $u => &$val) {
        if (($val['chat_id'] ?? null) == $chatId) { $val['chat_id'] = null; }
      }

      $data['users'][$uName]['chat_id'] = $chatId;
      $loginDt = getShamsiDateTime();
      $data['users'][$uName]['login_date'] = $loginDt['date'];
      $data['users'][$uName]['login_time'] = $loginDt['time'];
      
      if ($uName === "Owner") {
        $data['user_states'][$chatId] = "owner_panel";
        $data['temp_data'][$chatId] = [];
        saveData($data);
        tgCall("sendMessage", [
          'chat_id' => $chatId,
          'text' => "به پنل مدیریت بازی خوش آمدید.",
          'reply_markup' => getOwnerKeyboard()
        ]);
      } else {
        $data['user_states'][$chatId] = STATE_LOGGED_IN;
        $data['temp_data'][$chatId] = [];
        saveData($data);
        tgCall("sendMessage", [
          'chat_id' => $chatId,
          'text' => "به حساب خود خوش برگشتید.",
          'reply_markup' => getUserKeyboard()
        ]);
        showUserProfile($chatId, $data);
      }
    } else {
      tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "❌ رمز عبور اشتباه است. مجدداً تلاش کنید یا دکمه انصراف را بزنید:"]);
    }
    return;
  }

  // پیام پیش‌فرض برای متون ناشناخته
  tgCall("sendMessage", ['chat_id' => $chatId, 'text' => "لطفاً برای شروع بازی از دستور /start استفاده کنید یا گزینه‌های منو را انتخاب کنید."]);
}