<?php
// 清理超过10分钟的验证码图片
$files = glob('code/captcha_*.png');
$now = time();
$expire = 600; // 10分钟

foreach ($files as $file) {
    if (file_exists($file) && ($now - filemtime($file)) > $expire) {
        unlink($file);
    }
}