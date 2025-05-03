<?php
session_start();

// 验证码配置
$width = 120;
$height = 40;
$length = 6; // 验证码长度
$font = __DIR__ . '/arial.ttf'; // 字体文件路径，如果没有可以使用默认字体
$charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; // 去掉了容易混淆的字符

// 创建画布
$image = imagecreatetruecolor($width, $height);

// 设置颜色
$bgColor = imagecolorallocate($image, 245, 245, 245); // 背景色
$textColor = imagecolorallocate($image, 50, 50, 50); // 文字颜色
$noiseColor = imagecolorallocate($image, 150, 150, 150); // 干扰元素颜色

// 填充背景
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// 添加干扰线
for ($i = 0; $i < 5; $i++) {
    imageline($image, mt_rand(0, $width), mt_rand(0, $height), 
            mt_rand(0, $width), mt_rand(0, $height), $noiseColor);
}

// 添加干扰点
for ($i = 0; $i < 100; $i++) {
    imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $noiseColor);
}

// 生成验证码
$code = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $charset[mt_rand(0, strlen($charset) - 1)];
}

// 存储验证码到session
$_SESSION['captcha_code'] = $code;

// 如果字体文件存在则使用，否则使用内置字体
if (file_exists($font)) {
    $angle = mt_rand(-10, 10);
    $x = 20;
    $y = 30;
    imagettftext($image, 18, $angle, $x, $y, $textColor, $font, $code);
} else {
    // 使用内置字体
    imagestring($image, 5, 30, 12, $code, $textColor);
}

// 生成唯一文件名
$filename = 'code/captcha_' . md5(uniqid()) . '.png';

// 确保code目录存在
if (!file_exists('code')) {
    mkdir('code', 0755, true);
}

// 保存图片
imagepng($image, $filename);
imagedestroy($image);

// 返回图片路径
echo $filename;