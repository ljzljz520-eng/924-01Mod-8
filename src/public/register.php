<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $real_name = trim($_POST['real_name'] ?? '');

    if (strlen($username) < 3) {
        $error = '用户名至少3个字符';
    } elseif (strlen($password) < 6) {
        $error = '密码至少6个字符';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } else {
        $result = user_register($username, $email, $password, $real_name);
        if ($result['success']) {
            header('Location: /login.php?registered=1');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 | 素材合集协作平台</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <h1>🎨 注册新账号</h1>
            <p class="auth-subtitle">加入团队，开始素材协作</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label>用户名 *</label>
                    <input type="text" name="username" required placeholder="至少3个字符" value="<?php echo e($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>真实姓名</label>
                    <input type="text" name="real_name" placeholder="显示在评论和投票中" value="<?php echo e($_POST['real_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>邮箱 *</label>
                    <input type="email" name="email" required placeholder="your@email.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>密码 *</label>
                    <input type="password" name="password" required placeholder="至少6个字符">
                </div>
                <div class="form-group">
                    <label>确认密码 *</label>
                    <input type="password" name="confirm_password" required placeholder="再次输入密码">
                </div>
                <button type="submit" class="btn btn-primary btn-block">注册</button>
            </form>

            <div class="auth-footer">
                <p>已有账号？<a href="/login.php">立即登录</a></p>
            </div>
        </div>
    </div>
</body>
</html>
