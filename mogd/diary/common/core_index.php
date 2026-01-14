<?php
require __DIR__ . '/db.php';
require_site_login();

// 自動削除処理
$del_days = $pdo->query("SELECT value FROM settings WHERE key = 'auto_delete_days'")->fetchColumn();
if (!$del_days) $del_days = 60; 
$limit_date = date('Y-m-d H:i:s', strtotime("-{$del_days} days"));
$sql_del = "SELECT id FROM threads t WHERE COALESCE((SELECT MAX(created_at) FROM posts WHERE thread_id = t.id), t.created_at) < ?";
$stmt_del = $pdo->prepare($sql_del);
$stmt_del->execute([$limit_date]);
$del_ids = $stmt_del->fetchAll(PDO::FETCH_COLUMN);
if (!empty($del_ids)) {
    foreach ($del_ids as $did) {
        $pdo->prepare("DELETE FROM posts WHERE thread_id = ?")->execute([$did]);
        $pdo->prepare("DELETE FROM threads WHERE id = ?")->execute([$did]);
    }
}

// フィルタ・ページネーション
$filter = $_GET['filter'] ?? ''; 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "1=1";
if ($filter === 'public') {
    $where .= " AND (view_pass IS NULL OR view_pass = '')";
} elseif ($filter === 'summary') {
    $where .= " AND (view_pass IS NOT NULL AND view_pass != '') AND lock_level = 1";
}

$total_stmt = $pdo->query("SELECT COUNT(*) FROM threads WHERE $where");
$total_items = $total_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

$sql = "SELECT t.*, 
        (SELECT MAX(created_at) FROM posts WHERE thread_id = t.id AND is_sage = 0) as last_post_time
        FROM threads t 
        WHERE $where
        ORDER BY COALESCE(
            (SELECT MAX(created_at) FROM posts WHERE thread_id = t.id AND is_sage = 0), 
            t.created_at
        ) DESC
        LIMIT $limit OFFSET $offset";
$threads = $pdo->query($sql)->fetchAll();
$now_ts = time();

$notice = $pdo->query("SELECT value FROM settings WHERE key = 'notice_text'")->fetchColumn();

$genre_name = 'DIARY';
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(getcwd()); 
    if (isset($genres[$current_dir])) {
        $genre_name = $genres[$current_dir];
    }
}
$page_title = $genre_name . ' - DIARY';

require __DIR__ . '/header.php';
?>

<div class="container">
    <h1><?= h($genre_name) ?></h1>
<div class="text-center-mb" style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <a href="create.php" class="btn" style="padding: 12px 30px; font-weight: bold;">＋ 新規日記作成</a>
        <a href="../rule.php" class="btn" style="padding: 12px 30px; font-weight: bold;">利用規約・ルール</a>
    </div>
    <?php if ($notice): ?>
    <div class="card">
        <h3>お知らせ</h3>
        <div style="line-height: 1.6;"><?= nl2br(h($notice)) ?></div>
    </div>
    <?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="filter-bar">
            <h2 class="filter-title">日記一覧</h2>
            <form method="get" style="margin:0; display:flex; gap:5px;">
                <?php if($filter === ''): ?>
                    <button type="submit" name="filter" value="public" class="btn btn-sm">鍵無</button>
                    <button type="submit" name="filter" value="summary" class="btn btn-sm">表紙</button>
                <?php else: ?>
                    <a href="index.php" class="btn btn-sm btn-secondary">全表示に戻す</a>
                    <?php if($filter === 'public'): ?>
                        <button type="submit" name="filter" value="summary" class="btn btn-sm">表紙</button>
                    <?php elseif($filter === 'summary'): ?>
                        <button type="submit" name="filter" value="public" class="btn btn-sm">鍵無</button>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($threads)): ?>
            <div style="padding: 20px; text-align: center;">まだ日記がありません。</div>
        <?php else: ?>
            <?php foreach ($threads as $t): ?>
                <?php
                    $upd = !empty($t['last_post_time']) ? $t['last_post_time'] : $t['created_at'];
                    $is_new = ($now_ts - strtotime($upd)) < 86400; 
                ?>
                <a href="thread.php?id=<?= h($t['id']) ?>" class="list-item">
                    <div style="font-weight: bold; font-size: 1.1rem; display:flex; align-items:center; gap:8px;">
                        <?php if ($is_new): ?><span class="new-mark">NEW</span><?php endif; ?><?= h($t['title']) ?>
                    
                    </div>
                    <div class="meta-info">
                        <span>No: <?= h($t['id']) ?></span>
                        <span>作成者名: <?= h($t['creator_name']) ?></span>
                        <?php if (!empty($t['view_pass'])): ?>
                            <span title="鍵付き">🔒</span>
                            <?php if (isset($t['lock_level']) && $t['lock_level'] == 1): ?>
                                <span title="表紙公開(概要閲覧可)">📖</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <?php $q = $_GET; $q['page'] = $i; $link = '?' . http_build_query($q); ?>
                <a href="<?= h($link) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div class="text-center-box">
        <a href="../index.php" style="color: var(--text-sub); text-decoration: none;">&laquo; 日記ジャンル選択に戻る</a>
    </div>
    <div class="footer-link"><?php require __DIR__ . '/footer.php'; ?></div>
</div>
</body>
</html>