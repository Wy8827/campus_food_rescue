<?php
// ============================================================
//  CONSTANTS — shared across all pages
// ============================================================

// Set PHP's timezone explicitly, matching the campus this app serves
// (APU = Asia Pacific University, Malaysia). Without this, PHP silently
// falls back to whatever php.ini's date.timezone is set to (often UTC on
// a fresh XAMPP install), which can then disagree with MySQL's own
// system timezone — causing PHP-computed timestamps (e.g. anything using
// date()/strtotime() with no explicit timezone) to drift by several
// hours from timestamps MySQL computes itself (e.g. DEFAULT
// CURRENT_TIMESTAMP, NOW()), even though both are meant to represent
// "right now". Setting this here, in a file every page already loads
// first, keeps PHP and MySQL reading from the same clock everywhere.
date_default_timezone_set('Asia/Kuala_Lumpur');

define('BASE_URL',           'http://localhost/food_rescue');
define('UPLOAD_PATH', __DIR__ . '/../uploads/food/');
define('UPLOAD_URL',  BASE_URL . '/uploads/food/');
define('CLAIM_HOLD_MINUTES', 10);
define('MAX_IMAGE_SIZE',     5 * 1024 * 1024); // 5MB
define('ALLOWED_IMG_TYPES',  ['image/jpeg', 'image/png', 'image/webp']);
?>
