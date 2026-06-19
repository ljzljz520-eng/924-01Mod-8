<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/user_auth.php';

function get_category_label(string $category): string
{
    $labels = [
        'wedding' => '婚礼',
        'catering' => '餐饮开业',
        'corporate' => '企业年会',
    ];
    return $labels[$category] ?? $category;
}

function get_collections_by_user(int $user_id, ?string $category = null): array
{
    $pdo = db();
    $sql = 'SELECT c.*, u.real_name as creator_name, 
            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) as item_count,
            (SELECT COUNT(*) FROM collection_members cm WHERE cm.collection_id = c.id) as member_count
            FROM collections c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.created_by = :user_id';
    
    $params = [':user_id' => $user_id];
    
    if ($category) {
        $sql .= ' AND c.category = :category';
        $params[':category'] = $category;
    }
    
    $sql .= ' ORDER BY c.updated_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_shared_collections(int $user_id): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT c.*, u.real_name as creator_name,
            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) as item_count,
            (SELECT COUNT(*) FROM collection_members cm WHERE cm.collection_id = c.id) as member_count
            FROM collections c
            LEFT JOIN users u ON c.created_by = u.id
            INNER JOIN collection_members cm ON cm.collection_id = c.id
            WHERE cm.user_id = :user_id AND c.created_by != :user_id
            ORDER BY c.updated_at DESC');
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function get_public_collections(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT c.*, u.real_name as creator_name,
            (SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id = c.id) as item_count,
            (SELECT COUNT(*) FROM collection_members cm WHERE cm.collection_id = c.id) as member_count
            FROM collections c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.is_public = 1
            ORDER BY c.updated_at DESC');
    return $stmt->fetchAll();
}

function get_collection(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT c.*, u.real_name as creator_name, u.username as creator_username
            FROM collections c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.id = :id');
    $stmt->execute([':id' => $id]);
    $collection = $stmt->fetch();
    return $collection ?: null;
}

function get_collection_by_token(string $token): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT c.*, u.real_name as creator_name, u.username as creator_username
            FROM collections c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.share_token = :token');
    $stmt->execute([':token' => $token]);
    $collection = $stmt->fetch();
    return $collection ?: null;
}

function can_access_collection(?array $collection, ?array $user): bool
{
    if (!$collection) {
        return false;
    }
    if ($collection['is_public']) {
        return true;
    }
    if (!$user) {
        return false;
    }
    if ($collection['created_by'] == $user['id']) {
        return true;
    }
    return is_collection_member($collection['id'], $user['id']);
}

function is_collection_member(int $collection_id, int $user_id): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM collection_members WHERE collection_id = :cid AND user_id = :uid');
    $stmt->execute([':cid' => $collection_id, ':uid' => $user_id]);
    return $stmt->fetchColumn() > 0;
}

function get_collection_members(int $collection_id): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT cm.*, u.username, u.real_name, u.email
            FROM collection_members cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.collection_id = :cid
            ORDER BY cm.joined_at ASC');
    $stmt->execute([':cid' => $collection_id]);
    return $stmt->fetchAll();
}

function create_collection(array $data, int $user_id): int
{
    $pdo = db();
    $share_token = bin2hex(random_bytes(16));
    
    $stmt = $pdo->prepare('INSERT INTO collections (title, description, category, cover_image, created_by, is_public, share_token)
            VALUES (:title, :description, :category, :cover_image, :created_by, :is_public, :share_token)');
    $stmt->execute([
        ':title' => $data['title'],
        ':description' => $data['description'] ?? '',
        ':category' => $data['category'],
        ':cover_image' => $data['cover_image'] ?? '',
        ':created_by' => $user_id,
        ':is_public' => $data['is_public'] ?? 0,
        ':share_token' => $share_token,
    ]);
    
    $collection_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare('INSERT INTO collection_members (collection_id, user_id, can_comment, can_vote, can_edit)
            VALUES (:cid, :uid, 1, 1, 1)');
    $stmt->execute([':cid' => $collection_id, ':uid' => $user_id]);
    
    return $collection_id;
}

function update_collection(int $id, array $data): void
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE collections SET title=:title, description=:description, category=:category,
            cover_image=:cover_image, is_public=:is_public WHERE id=:id');
    $stmt->execute([
        ':title' => $data['title'],
        ':description' => $data['description'] ?? '',
        ':category' => $data['category'],
        ':cover_image' => $data['cover_image'] ?? '',
        ':is_public' => $data['is_public'] ?? 0,
        ':id' => $id,
    ]);
}

function delete_collection(int $id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM collections WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function add_item_to_collection(int $collection_id, int $template_id, int $user_id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM collection_items WHERE collection_id = :cid');
    $stmt->execute([':cid' => $collection_id]);
    $result = $stmt->fetch();
    $sort_order = $result['next_order'] ?? 1;
    
    $stmt = $pdo->prepare('INSERT INTO collection_items (collection_id, template_id, sort_order, added_by)
            VALUES (:cid, :tid, :ord, :uid)');
    $stmt->execute([
        ':cid' => $collection_id,
        ':tid' => $template_id,
        ':ord' => $sort_order,
        ':uid' => $user_id,
    ]);
}

function remove_item_from_collection(int $collection_id, int $item_id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM collection_items WHERE id = :id AND collection_id = :cid');
    $stmt->execute([':id' => $item_id, ':cid' => $collection_id]);
}

function get_collection_items(int $collection_id): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ci.*, t.*, ci.id as item_id, u.real_name as added_by_name
            FROM collection_items ci
            LEFT JOIN templates t ON ci.template_id = t.id
            LEFT JOIN users u ON ci.added_by = u.id
            WHERE ci.collection_id = :cid
            ORDER BY ci.sort_order ASC, ci.added_at ASC');
    $stmt->execute([':cid' => $collection_id]);
    return $stmt->fetchAll();
}

function add_member_to_collection(int $collection_id, int $user_id, array $permissions = []): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT IGNORE INTO collection_members (collection_id, user_id, can_comment, can_vote, can_edit)
            VALUES (:cid, :uid, :can_comment, :can_vote, :can_edit)');
    $stmt->execute([
        ':cid' => $collection_id,
        ':uid' => $user_id,
        ':can_comment' => $permissions['can_comment'] ?? 1,
        ':can_vote' => $permissions['can_vote'] ?? 1,
        ':can_edit' => $permissions['can_edit'] ?? 0,
    ]);
}

function remove_member_from_collection(int $collection_id, int $user_id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM collection_members WHERE collection_id = :cid AND user_id = :uid');
    $stmt->execute([':cid' => $collection_id, ':uid' => $user_id]);
}

function add_comment(int $collection_id, ?int $item_id, int $user_id, string $content, bool $is_internal = false): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO comments (collection_id, item_id, user_id, content, is_internal)
            VALUES (:cid, :iid, :uid, :content, :is_internal)');
    $stmt->execute([
        ':cid' => $collection_id,
        ':iid' => $item_id,
        ':uid' => $user_id,
        ':content' => $content,
        ':is_internal' => $is_internal ? 1 : 0,
    ]);
}

function get_comments(int $collection_id, ?int $item_id = null, bool $include_internal = true): array
{
    $pdo = db();
    $sql = 'SELECT c.*, u.username, u.real_name
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.collection_id = :cid';
    
    $params = [':cid' => $collection_id];
    
    if ($item_id !== null) {
        $sql .= ' AND (c.item_id = :iid OR c.item_id IS NULL)';
        $params[':iid'] = $item_id;
    }
    
    if (!$include_internal) {
        $sql .= ' AND c.is_internal = 0';
    }
    
    $sql .= ' ORDER BY c.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function delete_comment(int $comment_id, int $user_id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $comment_id, ':uid' => $user_id]);
}

function cast_vote(int $collection_id, int $item_id, int $user_id, string $vote_type): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO votes (collection_id, item_id, user_id, vote_type)
            VALUES (:cid, :iid, :uid, :vtype)
            ON DUPLICATE KEY UPDATE vote_type = :vtype2');
    $stmt->execute([
        ':cid' => $collection_id,
        ':iid' => $item_id,
        ':uid' => $user_id,
        ':vtype' => $vote_type,
        ':vtype2' => $vote_type,
    ]);
}

function remove_vote(int $collection_id, int $item_id, int $user_id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM votes WHERE collection_id = :cid AND item_id = :iid AND user_id = :uid');
    $stmt->execute([':cid' => $collection_id, ':iid' => $item_id, ':uid' => $user_id]);
}

function get_item_votes(int $item_id): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT v.*, u.real_name, u.username
            FROM votes v
            LEFT JOIN users u ON v.user_id = u.id
            WHERE v.item_id = :iid
            ORDER BY v.created_at DESC');
    $stmt->execute([':iid' => $item_id]);
    $rows = $stmt->fetchAll();
    
    $up_votes = [];
    $down_votes = [];
    foreach ($rows as $row) {
        if ($row['vote_type'] === 'up') {
            $up_votes[] = $row;
        } else {
            $down_votes[] = $row;
        }
    }
    
    return [
        'up' => $up_votes,
        'down' => $down_votes,
        'up_count' => count($up_votes),
        'down_count' => count($down_votes),
        'total' => count($up_votes) - count($down_votes),
    ];
}

function get_user_vote(int $item_id, int $user_id): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT vote_type FROM votes WHERE item_id = :iid AND user_id = :uid');
    $stmt->execute([':iid' => $item_id, ':uid' => $user_id]);
    $row = $stmt->fetch();
    return $row ? $row['vote_type'] : null;
}

function mark_purchased(int $template_id, int $user_id, float $amount = 0): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO purchases (template_id, user_id, amount)
            VALUES (:tid, :uid, :amount)
            ON DUPLICATE KEY UPDATE purchase_date = CURRENT_TIMESTAMP');
    $stmt->execute([
        ':tid' => $template_id,
        ':uid' => $user_id,
        ':amount' => $amount,
    ]);
}

function get_purchasers(int $template_id): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT p.*, u.real_name, u.username
            FROM purchases p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.template_id = :tid
            ORDER BY p.purchase_date DESC');
    $stmt->execute([':tid' => $template_id]);
    return $stmt->fetchAll();
}

function has_purchased(int $template_id, int $user_id): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM purchases WHERE template_id = :tid AND user_id = :uid');
    $stmt->execute([':tid' => $template_id, ':uid' => $user_id]);
    return $stmt->fetchColumn() > 0;
}

function add_internal_note(int $collection_id, ?int $item_id, int $user_id, string $content): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO internal_notes (collection_id, item_id, user_id, content)
            VALUES (:cid, :iid, :uid, :content)');
    $stmt->execute([
        ':cid' => $collection_id,
        ':iid' => $item_id,
        ':uid' => $user_id,
        ':content' => $content,
    ]);
}

function get_internal_notes(int $collection_id, ?int $item_id = null): array
{
    $pdo = db();
    $sql = 'SELECT n.*, u.username, u.real_name
            FROM internal_notes n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE n.collection_id = :cid';
    
    $params = [':cid' => $collection_id];
    
    if ($item_id !== null) {
        $sql .= ' AND n.item_id = :iid';
        $params[':iid'] = $item_id;
    }
    
    $sql .= ' ORDER BY n.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function delete_internal_note(int $note_id, int $user_id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM internal_notes WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $note_id, ':uid' => $user_id]);
}

function regenerate_share_token(int $collection_id): string
{
    $pdo = db();
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare('UPDATE collections SET share_token = :token WHERE id = :id');
    $stmt->execute([':token' => $token, ':id' => $collection_id]);
    return $token;
}
