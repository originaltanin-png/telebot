<?php
/**
 * Wielder of Power - Game Configuration & Constants
 * File: config.php
 */

// تنظیم منطقه زمانی و خطاها
date_default_timezone_set('Asia/Tehran');
error_reporting(0);
ini_set('display_errors', '0');

// توکن ربات و شناسه‌های کانال‌ها
define('BOT_TOKEN', '8469662913:AAH0niP25VhkLjEqul4F08iNBkovAnQjTB0');
define('CHANNEL_ID', -1003952306776);
define('REPORT_CHANNEL_ID', -1004404140546);
define('DATA_CHAT_CHANNEL_ID', -1004427769925);

// مسیر ذخیره‌سازی دیتابیس جیسون
define('DB_FILE', __DIR__ . '/data.json');

// وضعیت‌های عمومی نشست‌ها
define('STATE_IDLE', 'idle');
define('STATE_LOGGED_IN', 'logged_in');

// ثابت‌های زمانی بازی (بر حسب ثانیه)
define('SEASON_DURATION', 90 * 86400);        // ۹۰ روز
define('TRADE_EXPIRE_TIME', 120);              // ۲ دقیقه مهلت تایید معامله
define('PASSWORD_CHANGE_COOLDOWN', 604800);    // ۷ روز مهلت تغییر مجدد رمز
define('DAILY_GIFT_COOLDOWN', 86400);          // ۲۴ ساعت دریافت هدیه روزانه
define('ATTACK_COOLDOWN', 3600);               // ۱ ساعت کول‌داون حمله

// مشخصات کامل سازه‌ها، هزینه‌ها، ظرفیت‌ها و زمان سوددهی
const BUILDING_STATS = [
  'market' => [
    'name' => "🏪 بازار 🏪",
    'build_cost' => ['coin' => 150, 'citizen' => 1, 'wood' => 40, 'stone' => 40, 'iron' => 0],
    'build_time' => 60,
    'desc' => "🏪 شما با استفاده از بازار میتوانید منابعی مانند سنگ و باقی منابع را بفروشید و درآمد سکه ایی کسب کنید و حتی میتوانید آن منابع را خریداری کنید. 🏪"
  ],
  'iron' => [
    'name' => "⛏️ معدن آهن ⚙️",
    'resource_name' => "⚙️ آهن ⚙️",
    'build_cost' => ['coin' => 200, 'citizen' => 2, 'wood' => 60, 'stone' => 80, 'iron' => 0],
    'build_time' => 240,
    'production_interval' => 240,
    'production_amount' => 3,
    'capacity' => 20,
    'desc' => "⛏️ شما با استفاده از معدن آهن میتوانید آهن استخراج کنید تا با کمک آهن بتوانید نیرو های نظامی خود را بسازید. ⚙️"
  ],
  'stone' => [
    'name' => "⛏️ معدن سنگ 🪨",
    'resource_name' => "🪨 سنگ 🪨",
    'build_cost' => ['coin' => 100, 'citizen' => 1, 'wood' => 50, 'stone' => 0, 'iron' => 0],
    'build_time' => 60,
    'production_interval' => 180,
    'production_amount' => 5,
    'capacity' => 35,
    'desc' => "⛏️ شما با استفاده از معدن سنگ میتوانید سنگ استخراج کنید. 🪨"
  ],
  'lumber' => [
    'name' => "🪓 کارگاه چوب بری 🪵",
    'resource_name' => "🪵 چوب 🪵",
    'build_cost' => ['coin' => 80, 'citizen' => 1, 'wood' => 0, 'stone' => 30, 'iron' => 0],
    'build_time' => 40,
    'production_interval' => 120,
    'production_amount' => 5,
    'capacity' => 30,
    'desc' => "🪓 شما با استفاده از کارگاه چوب میتوانید چوب استخراج کنید. 🪵"
  ],
  'bakery' => [
    'name' => "🥖 نانوایی 🍞",
    'resource_name' => "🍞 نان 🍞",
    'build_cost' => ['coin' => 120, 'citizen' => 2, 'wood' => 50, 'stone' => 30, 'iron' => 0],
    'build_time' => 40,
    'production_interval' => 180,
    'production_amount' => 4,
    'capacity' => 25,
    'desc' => "🥖 شما با استفاده از نانوایی میتوانید نان استخراج کنید. 🍞"
  ],
  'housing' => [
    'name' => "🏠 مسکن 👥",
    'resource_name' => "👥 شهروند 👥",
    'build_cost' => ['coin' => 100, 'citizen' => 0, 'wood' => 40, 'stone' => 40, 'iron' => 0],
    'build_time' => 40,
    'production_interval' => 300,
    'production_amount' => 1,
    'capacity' => 10,
    'desc' => "🏠 شما با استفاده از مسکن میتوانید شهروند استخراج کنید. 👥"
  ],
  'barracks' => [
    'name' => "🏰 پادگان ⚔️",
    'build_cost' => ['coin' => 300, 'citizen' => 5, 'wood' => 120, 'stone' => 150, 'iron' => 0],
    'build_time' => 300,
    'desc' => "🏰 شما با استفاده از پادگان میتوانید نیرو نظامی برای دفاع کردن یا حمله کردن استفاده کنید. ⚔️"
  ]
];

// نرخ‌های خرید و فروش در بازار
const MARKET_RATES = [
  'iron'  => ['name' => "⚙️ آهن ⚙️", 'buy' => 15, 'sell' => 10],
  'stone' => ['name' => "🪨 سنگ 🪨", 'buy' => 8,  'sell' => 5],
  'wood'  => ['name' => "🪵 چوب 🪵", 'buy' => 6,  'sell' => 4],
  'bread' => ['name' => "🍞 نان 🍞", 'buy' => 5,  'sell' => 3]
];

// نام‌های فارسی و نمایشی منابع
const RES_NAMES = [
  'coin'    => "🪙 سکه 🪙",
  'iron'    => "⚙️ آهن ⚙️",
  'stone'   => "🪨 سنگ 🪨",
  'wood'    => "🪵 چوب 🪵",
  'bread'   => "🍞 نان 🍞",
  'citizen' => "👥 شهروند 👥"
];

// مشخصات کامل نیروهای نظامی، قدرت، استقامت، انرژی و هزینه‌ها
const TROOP_STATS = [
  'soldier' => [
    'name' => "🗡️ سرباز 🗡️",
    'reqLevel' => 1,
    'power' => 5,
    'stamina' => 5,
    'energy' => 5,
    'cost' => ['bread' => 3, 'citizen' => 1, 'iron' => 5]
  ],
  'archer' => [
    'name' => "🏹 کماندار 🏹",
    'reqLevel' => 2,
    'power' => 10,
    'stamina' => 1,
    'energy' => 1,
    'cost' => ['bread' => 3, 'citizen' => 1, 'wood' => 1, 'iron' => 1]
  ],
  'spearman' => [
    'name' => "🔱 نیزه دار 🗡️",
    'reqLevel' => 3,
    'power' => 10,
    'stamina' => 5,
    'energy' => 5,
    'cost' => ['bread' => 3, 'citizen' => 1, 'wood' => 1, 'iron' => 5]
  ],
  'horse_soldier' => [
    'name' => "🐎 سرباز اسب سوار 🐎",
    'reqLevel' => 4,
    'power' => 20,
    'stamina' => 20,
    'energy' => 20,
    'cost' => ['bread' => 12, 'citizen' => 1, 'iron' => 20]
  ],
  'horse_archer' => [
    'name' => "🏇 کماندار اسب سوار 🏹",
    'reqLevel' => 5,
    'power' => 40,
    'stamina' => 4,
    'energy' => 4,
    'cost' => ['bread' => 16, 'citizen' => 1, 'iron' => 4]
  ],
  'horse_spearman' => [
    'name' => "🐎 نیزه دار اسب سوار 🔱",
    'reqLevel' => 6,
    'power' => 40,
    'stamina' => 20,
    'energy' => 20,
    'cost' => ['bread' => 12, 'citizen' => 1, 'wood' => 4, 'iron' => 20]
  ],
  'catapult' => [
    'name' => "💥 منجنیق 💥",
    'reqLevel' => 7,
    'power' => 100,
    'stamina' => 20,
    'energy' => 10,
    'cost' => ['bread' => 10, 'citizen' => 4, 'wood' => 20, 'iron' => 5]
  ],
  'wood_giant' => [
    'name' => "🪵 غول چوبی 🪵",
    'reqLevel' => 8,
    'power' => 100,
    'stamina' => 100,
    'energy' => 100,
    'cost' => ['citizen' => 1, 'wood' => 100]
  ],
  'stone_giant' => [
    'name' => "🗿 غول سنگی 🪨",
    'reqLevel' => 9,
    'power' => 1000,
    'stamina' => 1000,
    'energy' => 1000,
    'cost' => ['citizen' => 1, 'stone' => 1000]
  ],
  'iron_giant' => [
    'name' => "🤖 غول آهنی ⚙️",
    'reqLevel' => 10,
    'power' => 10000,
    'stamina' => 10000,
    'energy' => 10000,
    'cost' => ['citizen' => 1, 'iron' => 50]
  ]
];

// لیست ۲۵ رنک بازی و مقدار ایکس‌پی مورد نیاز هر رنک
const RANK_TIERS = [
  ['name' => "\u{200E}⚪️I", 'req' => 20],
  ['name' => "\u{200E}⚪️II", 'req' => 21],
  ['name' => "\u{200E}⚪️III", 'req' => 22],
  ['name' => "\u{200E}⚪️IV", 'req' => 23],
  ['name' => "\u{200E}⚪️V", 'req' => 24],
  ['name' => "\u{200E}🟢I", 'req' => 25],
  ['name' => "\u{200E}🟢II", 'req' => 26],
  ['name' => "\u{200E}🟢III", 'req' => 27],
  ['name' => "\u{200E}🟢IV", 'req' => 28],
  ['name' => "\u{200E}🟢V", 'req' => 29],
  ['name' => "\u{200E}🔵I", 'req' => 30],
  ['name' => "\u{200E}🔵II", 'req' => 31],
  ['name' => "\u{200E}🔵III", 'req' => 32],
  ['name' => "\u{200E}🔵IV", 'req' => 33],
  ['name' => "\u{200E}🔵V", 'req' => 34],
  ['name' => "\u{200E}🟣I", 'req' => 35],
  ['name' => "\u{200E}🟣II", 'req' => 36],
  ['name' => "\u{200E}🟣III", 'req' => 37],
  ['name' => "\u{200E}🟣IV", 'req' => 38],
  ['name' => "\u{200E}🟣V", 'req' => 39],
  ['name' => "\u{200E}🟠I", 'req' => 40],
  ['name' => "\u{200E}🟠II", 'req' => 41],
  ['name' => "\u{200E}🟠III", 'req' => 42],
  ['name' => "\u{200E}🟠IV", 'req' => 43],
  ['name' => "\u{200E}🟠V", 'req' => 44]
];

// لیست ساده رنک‌ها برای مرتب‌سازی و حریف‌یابی
const RANK_TIERS_LIST = [
  "\u{200E}⚪️I", "\u{200E}⚪️II", "\u{200E}⚪️III", "\u{200E}⚪️IV", "\u{200E}⚪️V",
  "\u{200E}🟢I", "\u{200E}🟢II", "\u{200E}🟢III", "\u{200E}🟢IV", "\u{200E}🟢V",
  "\u{200E}🔵I", "\u{200E}🔵II", "\u{200E}🔵III", "\u{200E}🔵IV", "\u{200E}🔵V",
  "\u{200E}🟣I", "\u{200E}🟣II", "\u{200E}🟣III", "\u{200E}🟣IV", "\u{200E}🟣V",
  "\u{200E}🟠I", "\u{200E}🟠II", "\u{200E}🟠III", "\u{200E}🟠IV", "\u{200E}🟠V"
];