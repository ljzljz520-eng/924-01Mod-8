<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (user_login($username, $password)) {
        header('Location: /collections.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}

if (isset($_GET['registered'])) {
    $success = '注册成功，请登录';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 | 素材合集协作平台</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>🎨 素材合集协作平台</h1>
            <p class="auth-subtitle">团队素材收藏 · 协作评论 · 高效决策</p>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo e($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label>用户名 / 邮箱</label>
                    <input type="text" name="username" required placeholder="请输入用户名或邮箱" value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" required placeholder="请输入密码">
                </div>
                <button type="submit" class="btn btn-primary btn-block">登录</button>
            </form>

            <div class="auth-footer">
                <p>还没有账号？<a href="/register.php">立即注册</a></p>
                <p class="demo-hint">演示账号：zhangwei / password</p>
            </div>
        </div>
    </div>
</body>
</html>
