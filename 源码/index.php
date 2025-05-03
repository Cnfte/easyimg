<?php
require_once 'config.php';
$db = Database::getInstance();
session_start();

// [自动清理验证码和原有逻辑保持不变...]
if (mt_rand(1, 200) === 1) {
    $files = glob('code/captcha_*.png');
    $now = time();
    foreach ($files as $file) {
        if (file_exists($file) && ($now - filemtime($file)) > 600) {
            unlink($file);
        }
    }
}

// 处理登录
if (isset($_POST['login'])) {
    try {
        Auth::checkLoginAttempts();
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            throw new Exception("用户名和密码不能为空");
        }
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("用户名或密码错误");
        }
        
        if (!password_verify($password, $user['password'])) {
            throw new Exception("用户名或密码错误");
        }
        
        // 登录成功
        $_SESSION['attempts'] = 0;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['attempts']++;
        $login_error = $e->getMessage();
    }
}

// 处理注册（保持不变）
// [原有注册代码...]
// 处理注册
if (isset($_POST['register'])) {
    Auth::checkLoginAttempts();
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $captcha_input = strtoupper(trim($_POST['captcha']));
    
    // 验证验证码
    if (empty($_SESSION['captcha_code']) || $captcha_input !== $_SESSION['captcha_code']) {
        $_SESSION['attempts']++;
        $register_error = "验证码错误";
        
        // 删除验证码图片
        if (!empty($_POST['captcha_file']) && file_exists($_POST['captcha_file'])) {
            unlink($_POST['captcha_file']);
        }
    } else {
        // 验证用户名和密码
        if (strlen($username) < 4 || strlen($username) > 20) {
            $register_error = "用户名长度应在4-20个字符之间";
        } elseif (strlen($password) < 8) {
            $register_error = "密码长度至少8位";
        } else {
            // 检查IP是否在60分钟内注册过
            $stmt = $db->prepare("SELECT * FROM users WHERE ip_address = ? AND register_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
            $stmt->execute([$ip_address, REGISTER_LIMIT_TIME / 60]);
            
            if ($stmt->rowCount() > 0) {
                $register_error = "每个IP地址".(REGISTER_LIMIT_TIME / 60)."分钟内只能注册一个账户";
            } else {
                // 检查用户名是否已存在
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->rowCount() > 0) {
                    $register_error = "用户名已存在";
                } else {
                    // 创建用户
                    $hashed_pass = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $db->prepare("INSERT INTO users (username, password, ip_address, register_time) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$username, $hashed_pass, $ip_address]);
                    
                    $register_success = "注册成功，请登录";
                }
            }
        }
        
        // 无论验证是否通过都删除验证码图片
        if (!empty($_POST['captcha_file']) && file_exists($_POST['captcha_file'])) {
            unlink($_POST['captcha_file']);
        }
        unset($_SESSION['captcha_code']);
    }
}

// 处理登出
if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: index.php');
    exit;
}

// 处理文件上传
if (isset($_FILES['image']) && Auth::isLoggedIn()) {
    try {
        Image::upload($_FILES['image'], $_SESSION['user_id']);
        $upload_success = "文件上传成功!";
    } catch(Exception $e) {
        $upload_error = $e->getMessage();
    }
}

// 处理图片删除
if (isset($_GET['delete']) && Auth::isLoggedIn()) {
    try {
        Image::delete($_GET['delete'], $_SESSION['user_id']);
        header('Location: index.php');
        exit;
    } catch(Exception $e) {
        $delete_error = $e->getMessage();
    }
}

// 获取当前页码
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

// 获取用户图片
$images = [];
$totalImages = 0;
if (Auth::isLoggedIn()) {
    $images = Image::getUserImages($_SESSION['user_id'], $page, $perPage);
    $totalImages = Image::countUserImages($_SESSION['user_id']);
}

// 生成分页
$totalPages = ceil($totalImages / $perPage);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>简洁图床系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
             :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --danger-color: #e63946;
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
            color: var(--dark-color);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 20px;
            color: var(--primary-color);
        }
        
        .logo svg {
            width: 30px;
            height: 30px;
            margin-right: 10px;
        }
        
        .auth-buttons a {
            padding: 8px 16px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .login-btn {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        .login-btn:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .register-btn {
            background-color: var(--primary-color);
            color: white;
        }
        
        .register-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
            font-weight: 500;
        }
        
        .logout-btn {
            color: var(--danger-color);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
        }
        
        .logout-btn:hover {
            background-color: rgba(230, 57, 70, 0.1);
        }
        
        .upload-area {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            border: 2px dashed #ddd;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
        }
        
        .upload-area h2 {
            margin-bottom: 20px;
            color: var(--dark-color);
        }
        
        .upload-btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            margin-top: 15px;
        }
        
        .upload-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        #file-input {
            display: none;
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .image-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .image-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .image-info {
            padding: 15px;
        }
        
        .image-name {
            font-weight: 500;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .image-meta {
            font-size: 12px;
            color: #666;
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .image-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .view-btn, .delete-btn {
            display: inline-block;
            padding: 8px 0;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 14px;
            text-align: center;
            transition: all 0.3s;
            flex: 1;
        }
        
        .view-btn {
            background-color: var(--primary-color);
            color: white;
        }
        
        .view-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .delete-btn {
            background-color: var(--danger-color);
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #c1121f;
            transform: translateY(-2px);
        }
        
        .login-form, .register-form {
            max-width: 400px;
            margin: 50px auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 40px;
        }
        
        .login-form h2, .register-form h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .submit-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .error, .upload-error {
            color: var(--danger-color);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            color: var(--success-color);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
            grid-column: 1 / -1;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.6;
        }
        
        .toggle-form {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .toggle-form:hover {
            text-decoration: underline;
        }
        
        .register-form {
            display: none;
        }
        
        .file-types {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div class="d-flex align-items-center">
                <svg viewBox="0 0 24 24" width="30" height="30" class="me-2" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 8H20M8 4V20M7.8 20H16.2C17.8802 20 18.7202 20 19.362 19.673C19.9265 19.3854 20.3854 18.9265 20.673 18.362C21 17.7202 21 16.8802 21 15.2V8.8C21 7.11984 21 6.27976 20.673 5.63803C20.3854 5.07354 19.9265 4.6146 19.362 4.32698C18.7202 4 17.8802 4 16.2 4H7.8C6.11984 4 5.27976 4 4.63803 4.32698C4.07354 4.6146 3.6146 5.07354 3.32698 5.63803C3 6.27976 3 7.11984 3 8.8V15.2C3 16.8802 3 17.7202 3.32698 18.362C3.6146 18.9265 4.07354 19.3854 4.63803 19.673C5.27976 20 6.11984 20 7.8 20Z" stroke="#4361ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="2" stroke="#4361ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="fs-5 fw-bold text-primary">简洁图床</span>
            </div>
            
            <?php if (Auth::isLoggedIn()): ?>
                <div class="d-flex align-items-center">
                    <span class="me-3">欢迎, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="?logout=1" class="btn btn-outline-danger">退出登录</a>
                </div>
            <?php else: ?>
                <div>
                    <a href="#login" class="btn btn-outline-primary me-2">登录</a>
                    <a href="#register" class="btn btn-primary">注册</a>
                </div>
            <?php endif; ?>
        </header>
        <?php if (Auth::isLoggedIn()): ?>
            <!-- 文件上传区域保持不变 -->
                     <div class="upload-area rounded-3 p-5 mb-4 text-center" id="upload-area">
                <h2 class="mb-4">拖放文件到此处或点击上传</h2>
                <form action="index.php" method="post" enctype="multipart/form-data" class="mb-3">
                    <input type="file" id="file-input" name="image" accept="<?php echo implode(',', array_map(fn($ext) => '.'.$ext, ALLOWED_EXTENSIONS)); ?>" class="d-none">
                    <label for="file-input" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-cloud-arrow-up me-2"></i>选择文件
                    </label>
                    <div class="text-muted mt-2">支持格式: <?php echo implode(', ', ALLOWED_EXTENSIONS); ?>, 最大 <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?>MB</div>
                </form>
                
                <?php if (isset($upload_success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($upload_success); ?></div>
                <?php endif; ?>
                
                <?php if (isset($upload_error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($upload_error); ?></div>
                <?php endif; ?>
                
                <?php if (isset($delete_error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($delete_error); ?></div>
                <?php endif; ?>
            </div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                <?php if (count($images) > 0): ?>
                    <?php foreach ($images as $image): ?>
                        <div class="col">
                            <div class="card image-card h-100">
                                <?php if (pathinfo($image['filename'], PATHINFO_EXTENSION) === 'mp4'): ?>
                                    <video class="card-img-top" height="200" controls>
                                        <source src="uploads/<?php echo htmlspecialchars($image['filename']); ?>" type="video/mp4">
                                        您的浏览器不支持视频播放
                                    </video>
                                <?php else: ?>
                                    <img src="uploads/<?php echo htmlspecialchars($image['filename']); ?>" 
                                         class="card-img-top" 
                                         height="200" 
                                         style="object-fit: cover;" 
                                         alt="<?php echo htmlspecialchars($image['original_name']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title text-truncate"><?php echo htmlspecialchars($image['original_name']); ?></h5>
                                    <div class="d-flex justify-content-between text-muted small mb-2">
                                        <span><?php echo round($image['file_size'] / 1024, 2); ?> KB</span>
                                        <span><?php echo date('Y-m-d', strtotime($image['upload_date'])); ?></span>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <a href="uploads/<?php echo htmlspecialchars($image['filename']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-primary">
                                           <i class="bi bi-eye me-1"></i>查看
                                        </a>
                                        <a href="?delete=<?php echo $image['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('确定要删除这个文件吗？')">
                                           <i class="bi bi-trash me-1"></i>删除
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-images file-icon"></i>
                        <h3 class="mt-3">暂无文件</h3>
                        <p class="text-muted">上传你的第一个文件开始使用</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <!-- 修改后的登录表单 -->
                    <div class="card mb-4" id="login">
                        <div class="card-body">
                            <h2 class="card-title text-center mb-4">用户登录</h2>
                            
                            <?php if (isset($login_error)): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($login_error); ?>
                                    <?php if (strpos($login_error, '过多') === false): ?>
                                        <div class="mt-2">
                                            剩余尝试次数: <?php echo Auth::getRemainingAttempts(); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">用户名</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">密码</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text text-muted">
                                        剩余尝试次数: <?php echo Auth::getRemainingAttempts(); ?>
                                    </div>
                                </div>
                                
                                <button type="submit" name="login" class="btn btn-primary w-100">登录</button>
                                <p class="text-center mt-3">
                                    没有账号？<a href="#register" class="text-decoration-none toggle-form">去注册</a>
                                    <span class="mx-2">|</span>
                                    <a href="#forgot-password" class="text-decoration-none">忘记密码？</a>
                                </p>
                            </form>
                        </div>
                    </div>
                    
                    <!-- 注册表单保持不变 -->
                                     <div class="card d-none" id="register">
                        <div class="card-body">
                            <h2 class="card-title text-center mb-4">用户注册</h2>
                            
                            <?php if (isset($register_error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($register_error); ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($register_success)): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($register_success); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="reg_username" class="form-label">用户名</label>
                                    <input type="text" class="form-control" id="reg_username" name="username" required>
                                    <div class="form-text">用户名长度4-20个字符</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reg_password" class="form-label">密码</label>
                                    <input type="password" class="form-control" id="reg_password" name="password" required>
                                    <div class="form-text">密码长度至少8位</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">验证码</label>
                                    <div class="d-flex align-items-center mb-2">
                                        <img id="captcha-image" src="" alt="CAPTCHA" style="height: 40px;" class="me-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshCaptcha()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="captcha_file" id="captcha_file">
                                    <input type="text" class="form-control" name="captcha" required placeholder="输入验证码">
                                </div>
                                
                                <button type="submit" name="register" class="btn btn-primary w-100">注册</button>
                                <p class="text-center mt-3">已有账号？<a href="#login" class="text-decoration-none toggle-form">去登录</a></p>
                            </form>
                    <!-- [原有注册表单...] -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript保持不变 -->
      <script>
        // 拖放上传功能
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        
        if (uploadArea) {
            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });
            
            ['dragover', 'dragenter'].forEach(event => {
                uploadArea.addEventListener(event, (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('bg-light');
                });
            });
            
            ['dragleave', 'dragend', 'drop'].forEach(event => {
                uploadArea.addEventListener(event, (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('bg-light');
                });
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
            
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    fileInput.form.submit();
                }
            });
        }
        
        // 表单切换功能
        document.querySelectorAll('.login-btn, .register-btn, .toggle-form').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#login' || this.classList.contains('login-btn')) {
                    document.getElementById('login').classList.remove('d-none');
                    document.getElementById('register').classList.add('d-none');
                } else if (this.getAttribute('href') === '#register' || this.classList.contains('register-btn')) {
                    document.getElementById('login').classList.add('d-none');
                    document.getElementById('register').classList.remove('d-none');
                }
                
                if (this.getAttribute('href')?.startsWith('#')) {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // 根据hash显示对应表单
        if (window.location.hash === '#register') {
            document.getElementById('login').classList.add('d-none');
            document.getElementById('register').classList.remove('d-none');
        }
        
        // 验证码功能
        function loadCaptcha() {
            fetch('code.php')
                .then(response => response.text())
                .then(filename => {
                    document.getElementById('captcha-image').src = filename;
                    document.getElementById('captcha_file').value = filename;
                });
        }
        
        function refreshCaptcha() {
            // 先删除旧验证码图片
            const oldFile = document.getElementById('captcha_file').value;
            if (oldFile) {
                fetch('delete_captcha.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'file=' + encodeURIComponent(oldFile)
                });
            }
            
            // 加载新验证码
            loadCaptcha();
        }
        
        // 页面加载时初始化验证码
        if (document.getElementById('register')) {
            document.addEventListener('DOMContentLoaded', loadCaptcha);
        }
    </script>
    <!-- [原有脚本代码...] -->
</body>
</html>
