<?php
class Auth {
    public static function checkLoginAttempts() {
        if (!isset($_SESSION['attempts'])) {
            $_SESSION['attempts'] = 0;
            $_SESSION['last_attempt'] = time();
        }
        
        // 30分钟后重置尝试次数
        if (time() - $_SESSION['last_attempt'] > 1800) {
            $_SESSION['attempts'] = 0;
        }
        
        if ($_SESSION['attempts'] >= LOGIN_ATTEMPTS_LIMIT) {
            $_SESSION['block_time'] = time() + LOGIN_BLOCK_TIME;
            $remaining = LOGIN_BLOCK_TIME - (time() - $_SESSION['block_time']);
            die("尝试次数过多，请 ".gmdate("i", $remaining)." 分钟后再试");
        }
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function logout() {
        $_SESSION = array();
        session_destroy();
    }
    
    public static function getRemainingAttempts() {
        return max(0, LOGIN_ATTEMPTS_LIMIT - ($_SESSION['attempts'] ?? 0));
    }
}
?>