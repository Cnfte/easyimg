<?php
if(file_exists('config.php')) {
    header('Location: index.php');
    exit;
}

// [原有安装处理逻辑保持不变，只修改生成的config.php内容]
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $db_host = trim($_POST['db_host']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $db_name = trim($_POST['db_name']);
    $admin_user = trim($_POST['admin_user']);
    $admin_pass = trim($_POST['admin_pass']);
    $admin_pass_confirm = trim($_POST['admin_pass_confirm']);
    
    // 验证输入
    $errors = [];
    
    if(empty($db_host)) $errors[] = "数据库地址不能为空";
    if(empty($db_user)) $errors[] = "数据库用户名不能为空";
    if(empty($db_name)) $errors[] = "数据库名不能为空";
    if(empty($admin_user)) $errors[] = "管理员用户名不能为空";
    if(empty($admin_pass)) $errors[] = "管理员密码不能为空";
    if($admin_pass !== $admin_pass_confirm) $errors[] = "两次输入的密码不一致";
    if(strlen($admin_pass) < 8) $errors[] = "密码长度至少8位";
    
    if(empty($errors)) {
        // 测试数据库连接
        try {
            $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 创建表结构
            $sql = "
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `username` varchar(50) NOT NULL,
                `password` varchar(255) NOT NULL,
                `ip_address` varchar(45) NOT NULL,
                `register_time` datetime NOT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `is_admin` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            CREATE TABLE IF NOT EXISTS `images` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `filename` varchar(255) NOT NULL,
                `original_name` varchar(255) NOT NULL,
                `file_size` int(11) NOT NULL,
                `file_type` varchar(50) NOT NULL,
                `upload_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `images_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            $db->exec($sql);
            
            // 创建管理员账户
            $hashed_pass = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("INSERT INTO `users` (`username`, `password`, `ip_address`, `register_time`, `is_admin`) VALUES (?, ?, ?, NOW(), 1)");
            $stmt->execute([$admin_user, $hashed_pass, $_SERVER['REMOTE_ADDR']]);

// 修改后的config.php生成内容
$config_content = "<?php
// 数据库配置
define('DB_HOST', '".addslashes($db_host)."');
define('DB_USER', '".addslashes($db_user)."');
define('DB_PASS', '".addslashes($db_pass)."');
define('DB_NAME', '".addslashes($db_name)."');

// 文件上传配置
define('MAX_FILE_SIZE', 20 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['mp4', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'heif']);
define('UPLOAD_DIR', 'uploads/');

// 安全设置（放宽限制）
define('LOGIN_ATTEMPTS_LIMIT', 15);      // 允许15次尝试
define('LOGIN_BLOCK_TIME', 1800);        // 30分钟封锁
define('REGISTER_LIMIT_TIME', 1800);     // 30分钟内限注册1次

// [其余配置保持不变...]
// 自动加载类
spl_autoload_register(function (\$class) {
    require_once 'classes/' . \$class . '.php';
});

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 会话配置
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => \$_SERVER['HTTP_HOST'],
    'secure' => isset(\$_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
";
            
            // 创建必要目录
            if(!is_dir('uploads')) mkdir('uploads', 0755);
            if(!is_dir('classes')) mkdir('classes', 0755);
            if(!is_dir('logs')) mkdir('logs', 0755);
            if(!is_dir('code')) mkdir('code', 0755);
            
            file_put_contents('config.php', $config_content);
            
            // 重定向到主页
            header('Location: index.php');
            exit;
            
        } catch(PDOException $e) {
            $errors[] = "数据库连接失败: " . $e->getMessage();
        }
    }
}

// [其余安装流程保持不变...]
?>

<!-- 前端表单保持不变 -->
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图床系统安装向导</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
     :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7ff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .install-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .install-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        h1 {
            color: var(--dark-color);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 14px 20px;
            width: 100%;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .error {
            color: #e63946;
            margin-bottom: 20px;
            text-align: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo svg {
            width: 80px;
            height: 80px;
        }
        /* ... */
        
        .password-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="install-container bg-white">
            <div class="logo">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 8H20M8 4V20M7.8 20H16.2C17.8802 20 18.7202 20 19.362 19.673C19.9265 19.3854 20.3854 18.9265 20.673 18.362C21 17.7202 21 16.8802 21 15.2V8.8C21 7.11984 21 6.27976 20.673 5.63803C20.3854 5.07354 19.9265 4.6146 19.362 4.32698C18.7202 4 17.8802 4 16.2 4H7.8C6.11984 4 5.27976 4 4.63803 4.32698C4.07354 4.6146 3.6146 5.07354 3.32698 5.63803C3 6.27976 3 7.11984 3 8.8V15.2C3 16.8802 3 17.7202 3.32698 18.362C3.6146 18.9265 4.07354 19.3854 4.63803 19.673C5.27976 20 6.11984 20 7.8 20Z" stroke="#4361ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="2" stroke="#4361ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h1 class="mt-3">图床系统安装向导</h1>
            </div>
            
            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="install.php">
                <div class="mb-3">
                    <label for="db_host" class="form-label">数据库地址</label>
                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="mb-3">
                    <label for="db_user" class="form-label">数据库用户名</label>
                    <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                </div>
                
                <div class="mb-3">
                    <label for="db_pass" class="form-label">数据库密码</label>
                    <input type="password" class="form-control" id="db_pass" name="db_pass">
                </div>
                
                <div class="mb-3">
                    <label for="db_name" class="form-label">数据库名</label>
                    <input type="text" class="form-control" id="db_name" name="db_name" required>
                    <div class="form-text">请提前创建好数据库</div>
                </div>
                
                <div class="mb-3">
                    <label for="admin_user" class="form-label">管理员用户名</label>
                    <input type="text" class="form-control" id="admin_user" name="admin_user" required>
                </div>
                
                <div class="mb-3">
                    <label for="admin_pass" class="form-label">管理员密码</label>
                    <input type="password" class="form-control" id="admin_pass" name="admin_pass" required>
                    <div class="form-text">密码长度至少8位</div>
                </div>
                
                <div class="mb-3">
                    <label for="admin_pass_confirm" class="form-label">确认密码</label>
                    <input type="password" class="form-control" id="admin_pass_confirm" name="admin_pass_confirm" required>
                </div>
                
                <button type="submit" name="install" class="btn btn-primary w-100">开始安装</button>
            </form>
        </div>
    </div>
</body>
</html>
<!-- [原有HTML表单代码...] -->