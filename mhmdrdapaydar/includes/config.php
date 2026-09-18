<?php
/**
 * انیمه موزیک — پیکربندی پنل مدیریت
 * نکته: اطلاعات ورود را حتماً پیش از استفاده تغییر دهید.
 * میزان تلاش برای ورود محدود شده تا از حدس رمز جلوگیری شود.
 */

// اطلاعات ورود ادمین — حتماً تغییر دهید
define('AM_ADMIN_USER', 'Mhmdrdapaydar');

// رمز جدید (هش bcrypt برای رمز پیش‌فرض «mohamadrza»)
// برای ساخت رمز جدید: مقدار AM_ADMIN_HASH را برابر password_hash('رمزجدید', PASSWORD_BCRYPT) بگذارید.
define('AM_ADMIN_HASH', '$2b$10$v/ofCoa97dX0px5MlrulrOt8k5ph3GacLLzhrquavN8vTyVPsShye');
