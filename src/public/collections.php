<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../includes/template_repo.php';
require_once __DIR__ . '/../includes/collection_repo.php';

require_user_login();

$user = current_user();
$category = $_GET['category'] ?? null;
$tab = $_GET['tab'] ?? 'my';

$my_collections = get_collections_by_user($user['id'], $category);
$shared_collections = get_shared_collections($user['id']);
$public_collections = get_public_collections();

$category_icons = [
    'wedding' => '💒',
    'catering' => '🍽️',
    'corporate' => '🏢',
];

$category_colors = [
    'wedding' => 'category-wedding',
    'catering' => 'category-catering',
    'corporate' => 'category-corporate',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的合集 | 素材合集协作平台</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="collections-page">
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
            <h1>📚 素材合集</h1>
            <p>整理素材，团队协作，高效决策</p>
        </div>
        <a href="/collection_edit.php" class="btn btn-primary">+ 创建合集</a>
    </div>

    <div class="category-filter">
        <a href="/collections.php?tab=<?php echo e($tab); ?>" class="chip <?php echo !$category ? 'active' : ''; ?>">全部</a>
        <a href="/collections.php?tab=<?php echo e($tab); ?>&category=wedding" class="chip <?php echo $category === 'wedding' ? 'active' : ''; ?>">💒 婚礼</a>
        <a href="/collections.php?tab=<?php echo e($tab); ?>&category=catering" class="chip <?php echo $category === 'catering' ? 'active' : ''; ?>">🍽️ 餐饮开业</a>
        <a href="/collections.php?tab=<?php echo e($tab); ?>&category=corporate" class="chip <?php echo $category === 'corporate' ? 'active' : ''; ?>">🏢 企业年会</a>
    </div>

    <div class="tabs">
        <a href="/collections.php?tab=my<?php echo $category ? '&category=' . e($category) : ''; ?>" class="tab <?php echo $tab === 'my' ? 'active' : ''; ?>">
            我的合集 <span class="count"><?php echo count($my_collections); ?></span>
        </a>
        <a href="/collections.php?tab=shared<?php echo $category ? '&category=' . e($category) : ''; ?>" class="tab <?php echo $tab === 'shared' ? 'active' : ''; ?>">
            共享给我 <span class="count"><?php echo count($shared_collections); ?></span>
        </a>
        <a href="/collections.php?tab=public<?php echo $category ? '&category=' . e($category) : ''; ?>" class="tab <?php echo $tab === 'public' ? 'active' : ''; ?>">
            公开合集 <span class="count"><?php echo count($public_collections); ?></span>
        </a>
    </div>

    <div class="collections-grid">
        <?php
        $display_collections = match($tab) {
            'shared' => $shared_collections,
            'public' => $public_collections,
            default => $my_collections,
        };
        
        if (empty($display_collections)):
        ?>
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <h3>暂无合集</h3>
                <p>点击右上角"创建合集"开始整理你的素材</p>
                <?php if ($tab === 'my'): ?>
                    <a href="/collection_edit.php" class="btn btn-primary">+ 创建第一个合集</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($display_collections as $col): 
                $images = format_preview_images($col['cover_image'] ?? '');
                $cover = $images[0] ?? 'https://images.unsplash.com/photo-1513542789411-b6a5d4f31634?auto=format&fit=crop&w=800&q=80';
                $col_category = $col['category'];
            ?>
                <article class="collection-card <?php echo $category_colors[$col_category] ?? ''; ?>">
                    <div class="collection-cover">
                        <img src="<?php echo e($cover); ?>" alt="<?php echo e($col['title']); ?>">
                        <div class="collection-category-badge">
                            <?php echo $category_icons[$col_category] ?? '📁'; ?>
                            <?php echo e(get_category_label($col_category)); ?>
                        </div>
                        <?php if ($col['is_public']): ?>
                            <div class="public-badge">🌐 公开</div>
                        <?php endif; ?>
                    </div>
                    <div class="collection-body">
                        <h3><?php echo e($col['title']); ?></h3>
                        <p class="collection-desc"><?php echo e(mb_substr($col['description'] ?? '', 0, 60)); ?>...</p>
                        <div class="collection-meta">
                            <span>📦 <?php echo $col['item_count']; ?> 个素材</span>
                            <span>👥 <?php echo $col['member_count']; ?> 位成员</span>
                        </div>
                        <div class="collection-footer">
                            <span class="creator">创建者: <?php echo e($col['creator_name']); ?></span>
                            <a href="/collection_detail.php?id=<?php echo e($col['id']); ?>" class="btn btn-primary btn-sm">查看详情</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <p>TemplateHub · 素材合集协作平台 · 团队协作更高效</p>
</footer>
</body>
</html>
