<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/collection_repo.php';
require_once __DIR__ . '/../includes/template_repo.php';

require_user_login();

$user = current_user();
$collection_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$collection = $collection_id ? get_collection($collection_id) : null;

$error = '';
$success = '';

if ($collection && $collection['created_by'] != $user['id']) {
    header('Location: /collections.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_collection') {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category' => $_POST['category'] ?? 'wedding',
            'cover_image' => trim($_POST['cover_image'] ?? ''),
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
        ];
        
        if (empty($data['title'])) {
            $error = '请输入合集标题';
        } else {
            if ($collection) {
                update_collection($collection_id, $data);
                $success = '合集已更新';
            } else {
                    $collection_id = create_collection($data, $user['id']);
                    $collection = get_collection($collection_id);
                $success = '合集创建成功';
            }
        }
    }
    
    if ($action === 'add_item' && $collection_id) {
        $template_id = (int)($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            add_item_to_collection($collection_id, $template_id, $user['id']);
            $success = '素材已添加';
        }
    }
    
    if ($action === 'remove_item' && $collection_id) {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            remove_item_from_collection($collection_id, $item_id);
            $success = '素材已移除';
        }
    }
    
    if ($action === 'add_member' && $collection_id) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $permissions = [
                'can_comment' => isset($_POST['can_comment']) ? 1 : 0,
                'can_vote' => isset($_POST['can_vote']) ? 1 : 0,
                'can_edit' => isset($_POST['can_edit']) ? 1 : 0,
            ];
            add_member_to_collection($collection_id, $user_id, $permissions);
            $success = '成员已添加';
        }
    }
    
    if ($action === 'remove_member' && $collection_id) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0 && $user_id != $collection['created_by']) {
            remove_member_from_collection($collection_id, $user_id);
            $success = '成员已移除';
        }
    }
    
    if ($action === 'delete' && $collection_id) {
        delete_collection($collection_id);
        header('Location: /collections.php');
        exit;
    }
}

$all_templates = fetch_templates();
$all_users = get_all_users();
$items = $collection ? get_collection_items($collection_id) : [];
$members = $collection ? get_collection_members($collection_id) : [];
$member_ids = array_column($members, 'user_id');

$category_tags = [
    'wedding' => '婚礼',
    'catering' => '餐饮开业',
    'corporate' => '企业年会',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $collection ? '编辑合集' : '创建合集'; ?> | 素材合集协作平台</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="edit-page">
<header class="site-header">
    <div class="navbar">
        <div class="brand">
            <a href="/" style="color:inherit;text-decoration:none;">
                <span class="badge">Free</span>
                <span>TemplateHub · 素材合集</span>
            </a>
        </div>
        <nav class="nav-links">
            <a href="/">模板库</a>
            <a href="/collections.php" class="active">素材合集</a>
        </nav>
        <div class="user-menu">
            <span class="user-info">👤 <?php echo e($user['real_name']); ?></span>
            <a href="/logout.php" class="btn btn-ghost">退出</a>
        </div>
    </div>
</header>

<main class="main">
    <div class="page-header">
        <div class="page-title">
            <h1><?php echo $collection ? '✏️ 编辑合集' : '➕ 创建新合集'; ?></h1>
            <p><?php echo $collection ? '修改合集信息和管理素材' : '创建一个新的素材合集'; ?></p>
        </div>
        <?php if ($collection): ?>
            <div class="header-actions">
                <a href="/collection_detail.php?id=<?php echo e($collection_id); ?>" class="btn btn-ghost">查看合集</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <div class="edit-layout">
        <div class="edit-main">
            <section class="card-section">
                <h2>📝 基本信息</h2>
                <form method="POST" class="form">
                    <input type="hidden" name="action" value="save_collection">
                    <div class="form-group">
                        <label>合集标题 *</label>
                        <input type="text" name="title" required value="<?php echo e($collection['title'] ?? ''); ?>" placeholder="例如：2024春季婚礼素材合集">
                    </div>
                    <div class="form-group">
                        <label>分类 *</label>
                        <select name="category" required>
                            <?php foreach ($category_tags as $key => $label): ?>
                                <option value="<?php echo e($key); ?>" <?php echo (($collection['category'] ?? '') === $key) ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>描述</label>
                        <textarea name="description" rows="3" placeholder="简要描述这个合集的用途和内容"><?php echo e($collection['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>封面图片 URL</label>
                        <input type="text" name="cover_image" value="<?php echo e($collection['cover_image'] ?? ''); ?>" placeholder="输入图片URL（可选）">
                    </div>
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="is_public" <?php echo ($collection['is_public'] ?? 0) ? 'checked' : ''; ?>>
                            设为公开合集（所有人可见）
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 保存合集</button>
                </form>
            </section>

            <?php if ($collection): ?>
            <section class="card-section">
                <div class="section-header">
                    <h2>📦 合集素材 <span class="count"><?php echo count($items); ?></span></h2>
                </div>

                <?php if (empty($items)): ?>
                    <p class="text-muted">还没有添加素材，从下方选择添加。</p>
                <?php else: ?>
                    <div class="items-list">
                        <?php foreach ($items as $item):
                            $images = format_preview_images($item['preview_images']);
                            $img = $images[0] ?? 'https://via.placeholder.com/100';
                        ?>
                            <div class="item-row">
                                <img src="<?php echo e($img); ?>" alt="" class="item-thumb">
                                <div class="item-info">
                                    <strong><?php echo e($item['title']); ?></strong>
                                    <div class="item-meta">
                                        <?php if ($item['is_paid']): ?>
                                            <span class="paid-badge">💰 付费 ¥<?php echo e($item['price']); ?></span>
                                        <?php else: ?>
                                            <span class="free-badge">🆓 免费</span>
                                        <?php endif; ?>
                                        <span class="text-muted">添加者: <?php echo e($item['added_by_name']); ?></span>
                                    </div>
                                </div>
                                <form method="POST" class="item-actions">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('确定要移除这个素材吗？')">移除</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card-section">
                <div class="section-header">
                    <h2>➕ 添加素材</h2>
                </div>
                <form method="POST" class="add-item-form">
                    <input type="hidden" name="action" value="add_item">
                    <div class="form-row">
                        <select name="template_id" required class="flex-grow">
                            <option value="">选择要添加的素材...</option>
                            <?php foreach ($all_templates as $tpl):
                                $already_added = in_array($tpl['id'], array_column($items, 'id'));
                                if ($already_added) continue;
                            ?>
                                <option value="<?php echo e($tpl['id']); ?>">
                                    <?php echo e($tpl['title']); ?>
                                    <?php echo $tpl['is_paid'] ? '(付费 ¥' . $tpl['price'] . ')' : '(免费)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </div>
                </form>
            </section>
            <?php endif; ?>
        </div>

        <?php if ($collection): ?>
        <div class="edit-sidebar">
            <section class="card-section">
                <div class="section-header">
                    <h2>👥 团队成员 <span class="count"><?php echo count($members); ?></span></h2>
                </div>

                <div class="members-list">
                    <?php foreach ($members as $member): ?>
                        <div class="member-row">
                            <div class="member-info">
                                <strong><?php echo e($member['real_name'] ?: $member['username']); ?></strong>
                                <div class="member-perms">
                                    <?php if ($member['can_edit']): ?>
                                        <span class="perm-tag">可编辑</span>
                                    <?php endif; ?>
                                    <?php if ($member['can_comment']): ?>
                                        <span class="perm-tag">可评论</span>
                                    <?php endif; ?>
                                    <?php if ($member['can_vote']): ?>
                                        <span class="perm-tag">可投票</span>
                                    <?php endif; ?>
                                    <?php if ($member['user_id'] == $collection['created_by']): ?>
                                        <span class="owner-tag">创建者</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($member['user_id'] != $collection['created_by']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="user_id" value="<?php echo e($member['user_id']); ?>">
                                    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('确定要移除该成员吗？')">移除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="add-member-form">
                    <input type="hidden" name="action" value="add_member">
                    <div class="form-group">
                        <label>邀请成员</label>
                        <select name="user_id" required>
                            <option value="">选择团队成员...</option>
                            <?php foreach ($all_users as $u):
                                if (in_array($u['id'], $member_ids)) continue;
                            ?>
                                <option value="<?php echo e($u['id']); ?>">
                                    <?php echo e($u['real_name'] ?: $u['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="can_comment" checked>
                            允许评论
                        </label>
                        <label>
                            <input type="checkbox" name="can_vote" checked>
                            允许投票
                        </label>
                        <label>
                            <input type="checkbox" name="can_edit">
                            允许编辑
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">➕ 添加成员</button>
                </form>
            </section>

            <?php if ($collection['created_by'] == $user['id']): ?>
            <section class="card-section danger-zone">
                <h2>⚠️ 危险操作</h2>
                <form method="POST" onsubmit="return confirm('确定要删除这个合集吗？此操作不可撤销。');">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger btn-block">删除合集</button>
                </form>
            </section>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <p>TemplateHub · 素材合集协作平台 · 团队协作更高效</p>
</footer>
</body>
</html>
