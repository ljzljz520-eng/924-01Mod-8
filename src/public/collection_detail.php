<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/collection_repo.php';
require_once __DIR__ . '/../includes/template_repo.php';

$user = current_user();
$collection_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$collection = $collection_id ? get_collection($collection_id) : null;

if (!$collection || !can_access_collection($collection, $user)) {
    header('Location: /collections.php');
    exit;
}

$is_team_member = $user && ($collection['created_by'] == $user['id'] || is_collection_member($collection_id, $user['id']));
$is_owner = $user && $collection['created_by'] == $user['id'];
$is_public_view = !$is_team_member;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_team_member) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_comment') {
        $content = trim($_POST['content'] ?? '');
        $item_id = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : null;
        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
        
        if (!empty($content)) {
            add_comment($collection_id, $item_id, $user['id'], $content, $is_internal);
            $success = '评论已发布';
        }
    }
    
    if ($action === 'delete_comment') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        if ($comment_id > 0) {
            delete_comment($comment_id, $user['id']);
            $success = '评论已删除';
        }
    }
    
    if ($action === 'vote') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $vote_type = $_POST['vote_type'] ?? '';
        
        if ($item_id > 0 && in_array($vote_type, ['up', 'down'])) {
            $current_vote = get_user_vote($item_id, $user['id']);
            if ($current_vote === $vote_type) {
                remove_vote($collection_id, $item_id, $user['id']);
            } else {
                cast_vote($collection_id, $item_id, $user['id'], $vote_type);
            }
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    if ($action === 'mark_purchased') {
        $template_id = (int)($_POST['template_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        if ($template_id > 0) {
            mark_purchased($template_id, $user['id'], $amount);
            $success = '已标记为已购买';
        }
    }
    
    if ($action === 'add_note') {
        $content = trim($_POST['note_content'] ?? '');
        $item_id = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : null;
        
        if (!empty($content)) {
            add_internal_note($collection_id, $item_id, $user['id'], $content);
            $success = '备注已添加';
        }
    }
    
    if ($action === 'delete_note') {
        $note_id = (int)($_POST['note_id'] ?? 0);
        if ($note_id > 0) {
            delete_internal_note($note_id, $user['id']);
            $success = '备注已删除';
        }
    }
    
    if ($action === 'regenerate_token' && $is_owner) {
        regenerate_share_token($collection_id);
        $collection = get_collection($collection_id);
        $success = '分享链接已重新生成';
    }
    
    if ($action === 'toggle_public' && $is_owner) {
        $data = [
            'title' => $collection['title'],
            'description' => $collection['description'],
            'category' => $collection['category'],
            'cover_image' => $collection['cover_image'],
            'is_public' => $collection['is_public'] ? 0 : 1,
        ];
        update_collection($collection_id, $data);
        $collection = get_collection($collection_id);
        $success = $collection['is_public'] ? '合集已设为公开' : '合集已设为私有';
    }
}

$items = get_collection_items($collection_id);
$members = get_collection_members($collection_id);
$comments = get_comments($collection_id, null, $is_team_member);
$internal_notes = $is_team_member ? get_internal_notes($collection_id) : [];

$share_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/share.php?token=' . $collection['share_token'];

$category_icons = [
    'wedding' => '💒',
    'catering' => '🍽️',
    'corporate' => '🏢',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($collection['title']); ?> | 素材合集</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="detail-page">
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
            <?php if ($user): ?>
                <span class="user-info">👤 <?php echo e($user['real_name']); ?></span>
                <a href="/logout.php" class="btn btn-ghost">退出</a>
            <?php else: ?>
                <a href="/login.php" class="btn btn-primary">登录</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="main">
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <div class="detail-header">
        <div class="detail-title-section">
            <div class="category-badge big">
                <?php echo $category_icons[$collection['category']] ?? '📁'; ?>
                <?php echo e(get_category_label($collection['category'])); ?>
            </div>
            <h1><?php echo e($collection['title']); ?></h1>
            <p class="collection-desc"><?php echo e($collection['description']); ?></p>
            <div class="collection-stats">
                <span>📦 <?php echo count($items); ?> 个素材</span>
                <span>👥 <?php echo count($members); ?> 位成员</span>
                <span>👤 创建者: <?php echo e($collection['creator_name']); ?></span>
                <?php if ($collection['is_public']): ?>
                    <span class="public-indicator">🌐 公开可见</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="detail-actions">
            <?php if ($is_owner): ?>
                <a href="/collection_edit.php?id=<?php echo e($collection_id); ?>" class="btn btn-primary">✏️ 编辑合集</a>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_public">
                    <button type="submit" class="btn btn-ghost">
                        <?php echo $collection['is_public'] ? '🔒 设为私有' : '🌐 设为公开'; ?>
                    </button>
                </form>
            <?php endif; ?>
            <a href="/collections.php" class="btn btn-ghost">← 返回列表</a>
        </div>
    </div>

    <?php if ($is_owner): ?>
    <section class="card-section share-section">
        <div class="section-header">
            <h2>🔗 分享链接</h2>
        </div>
        <div class="share-box">
            <input type="text" value="<?php echo e($share_url); ?>" readonly class="share-url">
            <button onclick="copyShareUrl()" class="btn btn-primary">📋 复制链接</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="regenerate_token">
                <button type="submit" class="btn btn-ghost" onclick="return confirm('重新生成后原链接将失效，确定吗？')">🔄 重新生成</button>
            </form>
        </div>
        <p class="text-muted small">公开分享时，团队内部备注将被隐藏，仅成员可见。</p>
    </section>
    <?php endif; ?>

    <div class="detail-layout">
        <div class="detail-main">
            <section class="card-section">
                <div class="section-header">
                    <h2>📦 合集素材 <span class="count"><?php echo count($items); ?></span></h2>
                </div>

                <?php if (empty($items)): ?>
                    <div class="empty-state small">
                        <p>还没有添加素材</p>
                        <?php if ($is_owner): ?>
                            <a href="/collection_edit.php?id=<?php echo e($collection_id); ?>" class="btn btn-primary btn-sm">去添加素材</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="items-detail-list">
                        <?php foreach ($items as $item):
                            $images = format_preview_images($item['preview_images']);
                            $img = $images[0] ?? 'https://via.placeholder.com/200';
                            $votes = get_item_votes($item['item_id']);
                            $user_vote = $user ? get_user_vote($item['item_id'], $user['id']) : null;
                            $purchasers = get_purchasers($item['id']);
                            $has_purchased = $user ? has_purchased($item['id'], $user['id']) : false;
                            $item_comments = get_comments($collection_id, $item['item_id'], $is_team_member);
                            $item_notes = $is_team_member ? get_internal_notes($collection_id, $item['item_id']) : [];
                        ?>
                            <div class="item-detail-card" id="item-<?php echo e($item['item_id']); ?>">
                                <div class="item-detail-header">
                                    <img src="<?php echo e($img); ?>" alt="" class="item-detail-thumb">
                                    <div class="item-detail-info">
                                        <div class="item-title-row">
                                            <h3><?php echo e($item['title']); ?></h3>
                                            <?php if ($item['is_paid']): ?>
                                                <span class="paid-badge large">💰 付费 ¥<?php echo e($item['price']); ?></span>
                                            <?php else: ?>
                                                <span class="free-badge large">🆓 免费</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="item-desc"><?php echo e($item['description']); ?></p>
                                        <div class="item-tags">
                                            <?php foreach (array_filter(array_map('trim', explode(',', $item['tags'] ?? ''))) as $tag): ?>
                                                <span class="tag"><?php echo e($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="item-meta-row">
                                            <span>添加者: <?php echo e($item['added_by_name']); ?></span>
                                            <a href="<?php echo e($item['download_url']); ?>" target="_blank" class="btn btn-sm btn-primary">📥 下载</a>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($is_team_member): ?>
                                <div class="item-action-bar">
                                    <form method="POST" class="vote-form">
                                        <input type="hidden" name="action" value="vote">
                                        <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
                                        <input type="hidden" name="vote_type" value="up">
                                        <button type="submit" class="btn btn-vote <?php echo $user_vote === 'up' ? 'active' : ''; ?>">
                                            👍 <?php echo $votes['up_count']; ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="vote-form">
                                        <input type="hidden" name="action" value="vote">
                                        <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
                                        <input type="hidden" name="vote_type" value="down">
                                        <button type="submit" class="btn btn-vote <?php echo $user_vote === 'down' ? 'active' : ''; ?>">
                                            👎 <?php echo $votes['down_count']; ?>
                                        </button>
                                    </form>
                                    <span class="vote-score">得分: <?php echo $votes['total']; ?></span>
                                    
                                    <?php if ($item['is_paid']): ?>
                                        <?php if (!$has_purchased): ?>
                                            <form method="POST" class="purchase-form">
                                                <input type="hidden" name="action" value="mark_purchased">
                                                <input type="hidden" name="template_id" value="<?php echo e($item['id']); ?>">
                                                <input type="hidden" name="amount" value="<?php echo e($item['price']); ?>">
                                                <button type="submit" class="btn btn-success btn-sm">✅ 标记已购买</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="purchased-badge">✅ 你已购买</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($item['is_paid'] && !empty($purchasers)): ?>
                                <div class="purchasers-section">
                                    <strong>👥 已购买成员：</strong>
                                    <span class="purchasers-list">
                                        <?php foreach ($purchasers as $p): ?>
                                            <span class="purchaser-tag">
                                                <?php echo e($p['real_name'] ?: $p['username']); ?>
                                                <small>(<?php echo e(date('m-d', strtotime($p['purchase_date']))); ?>)</small>
                                            </span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <?php if ($is_team_member && !empty($votes['up'])): ?>
                                <div class="voters-section">
                                    <strong>👍 支持者：</strong>
                                    <span class="voters-list">
                                        <?php foreach ($votes['up'] as $v): ?>
                                            <span class="voter-tag"><?php echo e($v['real_name'] ?: $v['username']); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <?php if ($is_team_member && !empty($item_notes)): ?>
                                <div class="notes-section">
                                    <div class="notes-header">
                                        <strong>📝 内部备注 <span class="badge internal-badge">仅团队可见</span></strong>
                                    </div>
                                    <div class="notes-list">
                                        <?php foreach ($item_notes as $note): ?>
                                            <div class="note-item">
                                                <div class="note-header">
                                                    <span class="note-author"><?php echo e($note['real_name'] ?: $note['username']); ?></span>
                                                    <span class="note-date"><?php echo e(date('Y-m-d H:i', strtotime($note['created_at']))); ?></span>
                                                    <?php if ($note['user_id'] == $user['id']): ?>
                                                        <form method="POST" class="note-delete-form">
                                                            <input type="hidden" name="action" value="delete_note">
                                                            <input type="hidden" name="note_id" value="<?php echo e($note['id']); ?>">
                                                            <button type="submit" class="btn btn-link" onclick="return confirm('确定删除这条备注吗？')">删除</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="note-content"><?php echo e($note['content']); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <form method="POST" class="note-form">
                                        <input type="hidden" name="action" value="add_note">
                                        <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
                                        <div class="form-row">
                                            <textarea name="note_content" rows="2" placeholder="添加内部备注..." required class="flex-grow"></textarea>
                                            <button type="submit" class="btn btn-primary">添加</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>

                                <?php if ($is_team_member): ?>
                                <div class="comments-section">
                                    <div class="comments-header">
                                        <strong>💬 评论 <span class="count"><?php echo count($item_comments); ?></span></strong>
                                    </div>
                                    <?php if (!empty($item_comments)): ?>
                                        <div class="comments-list">
                                            <?php foreach ($item_comments as $comment):
                                                if ($comment['item_id'] != $item['item_id']) continue;
                                            ?>
                                                <div class="comment-item <?php echo $comment['is_internal'] ? 'internal-comment' : ''; ?>">
                                                    <div class="comment-header">
                                                        <span class="comment-author"><?php echo e($comment['real_name'] ?: $comment['username']); ?></span>
                                                        <span class="comment-date"><?php echo e(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></span>
                                                        <?php if ($comment['is_internal']): ?>
                                                            <span class="badge internal-badge">内部</span>
                                                        <?php endif; ?>
                                                        <?php if ($comment['user_id'] == $user['id']): ?>
                                                            <form method="POST" class="comment-delete-form">
                                                                <input type="hidden" name="action" value="delete_comment">
                                                                <input type="hidden" name="comment_id" value="<?php echo e($comment['id']); ?>">
                                                                <button type="submit" class="btn btn-link" onclick="return confirm('确定删除这条评论吗？')">删除</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="comment-content"><?php echo e($comment['content']); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <form method="POST" class="comment-form">
                                        <input type="hidden" name="action" value="add_comment">
                                        <input type="hidden" name="item_id" value="<?php echo e($item['item_id']); ?>">
                                        <textarea name="content" rows="2" placeholder="发表评论..." required></textarea>
                                        <div class="form-row">
                                            <label class="checkbox-inline">
                                                <input type="checkbox" name="is_internal">
                                                <span>设为内部评论（公开分享时隐藏）</span>
                                            </label>
                                            <button type="submit" class="btn btn-primary">发表评论</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($is_team_member): ?>
            <section class="card-section">
                <div class="section-header">
                    <h2>💬 合集整体评论 <span class="count"><?php echo count(array_filter($comments, fn($c) => !$c['item_id'])); ?></span></h2>
                </div>
                
                <?php 
                $general_comments = array_filter($comments, fn($c) => !$c['item_id']);
                if (!empty($general_comments)): 
                ?>
                    <div class="comments-list">
                        <?php foreach ($general_comments as $comment): ?>
                            <div class="comment-item <?php echo $comment['is_internal'] ? 'internal-comment' : ''; ?>">
                                <div class="comment-header">
                                    <span class="comment-author"><?php echo e($comment['real_name'] ?: $comment['username']); ?></span>
                                    <span class="comment-date"><?php echo e(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></span>
                                    <?php if ($comment['is_internal']): ?>
                                        <span class="badge internal-badge">内部</span>
                                    <?php endif; ?>
                                    <?php if ($comment['user_id'] == $user['id']): ?>
                                        <form method="POST" class="comment-delete-form">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?php echo e($comment['id']); ?>">
                                            <button type="submit" class="btn btn-link" onclick="return confirm('确定删除这条评论吗？')">删除</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <p class="comment-content"><?php echo e($comment['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">还没有评论，来发表第一条吧。</p>
                <?php endif; ?>

                <form method="POST" class="comment-form">
                    <input type="hidden" name="action" value="add_comment">
                    <textarea name="content" rows="3" placeholder="对整个合集发表评论..." required></textarea>
                    <div class="form-row">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_internal">
                            <span>设为内部评论（公开分享时隐藏）</span>
                        </label>
                        <button type="submit" class="btn btn-primary">发表评论</button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <?php if ($is_team_member && !empty($internal_notes)): ?>
            <section class="card-section internal-notes-section">
                <div class="section-header">
                    <h2>📝 合集内部备注 <span class="badge internal-badge">仅团队可见</span> <span class="count"><?php echo count($internal_notes); ?></span></h2>
                </div>
                <div class="notes-list">
                    <?php foreach ($internal_notes as $note):
                        if ($note['item_id']) continue;
                    ?>
                        <div class="note-item">
                            <div class="note-header">
                                <span class="note-author"><?php echo e($note['real_name'] ?: $note['username']); ?></span>
                                <span class="note-date"><?php echo e(date('Y-m-d H:i', strtotime($note['created_at']))); ?></span>
                                <?php if ($note['user_id'] == $user['id']): ?>
                                    <form method="POST" class="note-delete-form">
                                        <input type="hidden" name="action" value="delete_note">
                                        <input type="hidden" name="note_id" value="<?php echo e($note['id']); ?>">
                                        <button type="submit" class="btn btn-link" onclick="return confirm('确定删除这条备注吗？')">删除</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <p class="note-content"><?php echo e($note['content']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form method="POST" class="note-form">
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="note_content" rows="2" placeholder="添加合集内部备注..." required></textarea>
                    <button type="submit" class="btn btn-primary">添加备注</button>
                </form>
            </section>
            <?php endif; ?>
        </div>

        <div class="detail-sidebar">
            <?php if ($is_team_member): ?>
            <section class="card-section">
                <div class="section-header">
                    <h2>👥 团队成员</h2>
                </div>
                <div class="members-list compact">
                    <?php foreach ($members as $member): ?>
                        <div class="member-row compact">
                            <div class="member-avatar">
                                <?php echo e(mb_substr($member['real_name'] ?: $member['username'], 0, 1)); ?>
                            </div>
                            <div class="member-info">
                                <strong><?php echo e($member['real_name'] ?: $member['username']); ?></strong>
                                <div class="member-perms small">
                                    <?php if ($member['user_id'] == $collection['created_by']): ?>
                                        <span class="owner-tag">创建者</span>
                                    <?php endif; ?>
                                    <?php if ($member['can_edit']): ?>
                                        <span class="perm-tag">可编辑</span>
                                    <?php endif; ?>
                                    <?php if ($member['can_comment']): ?>
                                        <span class="perm-tag">可评论</span>
                                    <?php endif; ?>
                                    <?php if ($member['can_vote']): ?>
                                        <span class="perm-tag">可投票</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_owner): ?>
                    <a href="/collection_edit.php?id=<?php echo e($collection_id); ?>#members" class="btn btn-ghost btn-block">管理成员</a>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="card-section">
                <div class="section-header">
                    <h2>ℹ️ 合集信息</h2>
                </div>
                <div class="info-list">
                    <div class="info-row">
                        <span class="info-label">创建时间</span>
                        <span class="info-value"><?php echo e(date('Y-m-d', strtotime($collection['created_at']))); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">更新时间</span>
                        <span class="info-value"><?php echo e(date('Y-m-d', strtotime($collection['updated_at']))); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">创建者</span>
                        <span class="info-value"><?php echo e($collection['creator_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">可见性</span>
                        <span class="info-value"><?php echo $collection['is_public'] ? '🌐 公开' : '🔒 私有'; ?></span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<footer class="footer">
    <p>TemplateHub · 素材合集协作平台 · 团队协作更高效</p>
</footer>

<script>
function copyShareUrl() {
    const urlInput = document.querySelector('.share-url');
    urlInput.select();
    document.execCommand('copy');
    alert('分享链接已复制到剪贴板！');
}
</script>
</body>
</html>
