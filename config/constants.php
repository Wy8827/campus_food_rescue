<?php
// ============================================================
//  CONSTANTS — shared across all pages
// ============================================================
define('BASE_URL',           'http://localhost/food_rescue');
define('UPLOAD_PATH',        __DIR__ . '/../assets/images/uploads/');
define('UPLOAD_URL',         BASE_URL . '/assets/images/uploads/');
define('CLAIM_HOLD_MINUTES', 10);
define('MAX_IMAGE_SIZE',     5 * 1024 * 1024); // 5MB
define('ALLOWED_IMG_TYPES',  ['image/jpeg', 'image/png', 'image/webp']);
?>
