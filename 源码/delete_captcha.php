<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'])) {
    $file = $_POST['file'];
    
    // 安全检查：确保只删除code目录下的png文件
    if (strpos($file, 'code/captcha_') === 0 && file_exists($file)) {
        unlink($file);
    }
}