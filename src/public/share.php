<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/collection_repo.php';
require_once __DIR__ . '/../includes/template_repo.php';

$token = $_GET['token'] ?? '';
$collection = $token ? get_collection_by_token($token) : null;

if (!$collection) {
    header('HTTP/1.0 404 Not Found');
    echo '分享链接无效或已过期';
    exit;
}

$user = current_user();
$is_team_member = $user && ($collection['created_by'] == $user['id'] || is_collection_member($collection['id'], $user['id']));

if ($is_team_member) {
    header('Location: /collection_detail.php?id=' . $collection['id']);
    exit;
}

$items = get_collection_items($collection['id']);
$comments = get_comments($collection['id'], null, false);
$members = get_collection_members($collection['id']);

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
<body class="share-page">
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
        </nav>
        <div class="user-menu">
            <div class="share-notice">
                <span class="lock-icon">🔓</span>
                <span>公开分享视图</span>
                <a href="/login.php" class="btn btn-primary btn-sm">登录使用完整功能</a>
            </div>
        </div>
    </div>
</header>

<main class="main">
    <div class="public-notice">
        <div class="public-notice-content">
            <span>🌐</span>
            <div>
                <strong>这是公开分享的合集视图</strong>
                <p>团队内部备注和评论已被隐藏。<a href="/login.php">登录</a>后可使用完整协作功能。</p>
            </div>
        </div>
    </div>

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
                <span>👤 创建者: <?php echo e($collection['creator_name']); ?></span>
            </div>
        </div>
    </div>

    <div class="detail-layout">
        <div class="detail-main">
            <section class="card-section">
                <div class="section-header">
                    <h2>📦 合集素材 <span class="count"><?php echo count($items); ?></span></h2>
                </div>

                <?php if (empty($items)): ?>
                    <div class="empty-state small">
                        <p>还没有添加素材</p>
                    </div>
                <?php else: ?>
                    <div class="items-detail-list">
                        <?php foreach ($items as $item):
                            $images = format_preview_images($item['preview_images']);
                            $img = $images[0] ?? 'https://via.placeholder.com/200';
                            $votes = get_item_votes($item['item_id']);
                            $purchasers = get_purchasers($item['id']);
                            $item_comments = get_comments($collection['id'], $item['item_id'], false);
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

                                <div class="vote-display-bar">
                                    <span>👍 <?php echo $votes['up_count']; ?> 人支持</span>
                                    <span>👎 <?php echo $votes['down_count']; ?> 人反对</span>
                                    <span class="vote-score">综合得分: <?php echo $votes['total']; ?></span>
                                </div>

                                <?php if ($item['is_paid'] && !empty($purchasers)): ?>
                                <div class="purchasers-section">
                                    <strong>👥 团队成员已购买：</strong>
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

                                <?php if (!empty($votes['up'])): ?>
                                <div class="voters-section">
                                    <strong>👍 支持者：</strong>
                                    <span class="voters-list">
                                        <?php foreach ($votes['up'] as $v): ?>
                                            <span class="voter-tag"><?php echo e($v['real_name'] ?: $v['username']); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($item_comments)): ?>
                                <div class="comments-section">
                                    <div class="comments-header">
                                        <strong>💬 公开评论 <span class="count"><?php echo count($item_comments); ?></span></strong>
                                    </div>
                                    <div class="comments-list">
                                        <?php foreach ($item_comments as $comment):
                                            if ($comment['item_id'] != $item['item_id']) continue;
                                            if ($comment['is_internal']) continue;
                                        ?>
                                            <div class="comment-item">
                                                <div class="comment-header">
                                                    <span class="comment-author"><?php echo e($comment['real_name'] ?: $comment['username']); ?></span>
                                                    <span class="comment-date"><?php echo e(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></span>
                                                </div>
                                                <p class="comment-content"><?php echo e($comment['content']); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php 
            $general_comments = array_filter($comments, fn($c) => !$c['item_id'] && !$c['is_internal']);
            if (!empty($general_comments)): 
            ?>
            <section class="card-section">
                <div class="section-header">
                    <h2>💬 合集公开评论 <span class="count"><?php echo count($general_comments); ?></span></h2>
                </div>
                <div class="comments-list">
                    <?php foreach ($general_comments as $comment): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo e($comment['real_name'] ?: $comment['username']); ?></span>
                                <span class="comment-date"><?php echo e(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></span>
                            </div>
                            <p class="comment-content"><?php echo e($comment['content']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <div class="detail-sidebar">
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
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

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
                </div>
            </section>

            <section class="card-section">
                <div class="cta-box">
                    <h3>💡 想要参与协作？</h3>
                    <p>登录后可以发表评论、投票、添加内部备注</p>
                    <a href="/login.php" class="btn btn-primary btn-block">立即登录</a>
                    <p class="text-muted small" style="margin-top:10px;">还没有账号？<a href="/register.php">立即注册</a></p>
                </div>
            </section>
        </div>
    </div>
</main>

<footer class="footer">
    <p>TemplateHub · 素材合集协作平台 · 团队协作更高效</p>
</footer>
</body>
</html>
