<?php
require 'db.php';
require_site_login();

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

$genre_name = 'TESTER';
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(__DIR__); 
    if (isset($genres[$current_dir])) {
        $genre_name = $genres[$current_dir];
    }
}
$page_title = $genre_name . ' - DIARY';

require 'header.php';
?>

<div class="container">
    <h1><?= h($genre_name) ?></h1>

    <div style="text-align: center; margin-bottom: 20px;">
        <a href="create.php" class="btn" style="padding: 12px 30px; font-weight: bold;">＋ 新規日記作成</a>
    </div>
    <div class="card">
        <h3>お知らせ</h3>
        <div style="line-height: 1.6;">動作サンプル用の独立した日記です。<br>テスト用としてご利用ください。テスト内容に制限は有りません。<br>※本番環境と若干のデザイン差がありますが、システムに大きな差異はありません。</div>
    </div>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding:15px; background:#333; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h2 style="margin:0; border:none; font-size:1rem;">日記一覧</h2>
            
            <form method="get" style="margin:0; display:flex; gap:5px;">
                <?php if($filter === ''): ?>
                    <button type="submit" name="filter" value="public" class="btn" style="padding:4px 10px; font-size:0.8em;">鍵無</button>
                    <button type="submit" name="filter" value="summary" class="btn" style="padding:4px 10px; font-size:0.8em;">表紙</button>
                <?php else: ?>
                    <a href="index.php" class="btn" style="padding:4px 10px; font-size:0.8em; background:#888; text-decoration:none;">全表示に戻す</a>
                    
                    <?php if($filter === 'public'): ?>
                        <button type="submit" name="filter" value="summary" class="btn" style="padding:4px 10px; font-size:0.8em;">表紙</button>
                    <?php elseif($filter === 'summary'): ?>
                        <button type="submit" name="filter" value="public" class="btn" style="padding:4px 10px; font-size:0.8em;">鍵無</button>
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
                        <span>名前: <?= h($t['creator_name']) ?></span>
                        
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
                <?php 
                   $q = $_GET; 
                   $q['page'] = $i; 
                   $link = '?' . http_build_query($q);
                ?>
                <a href="<?= h($link) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 40px; margin-bottom: 20px;">
        <a href="../index.php" style="color: #888; text-decoration: none;">&laquo; 日記ジャンル選択に戻る</a>
    </div>
    <div class="footer-link"><?php require 'footer.php'; ?></div>
</div>
</body>
</html>