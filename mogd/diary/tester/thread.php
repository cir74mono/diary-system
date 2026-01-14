<?php
require 'db.php';
require_site_login();

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

$id = $_GET['id'] ?? 0;
$sort = $_GET['sort'] ?? 'desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$id]);
$thread = $stmt->fetch();

if (!$thread) { header("Location: index.php"); exit; }


$genre_name = 'TESTER'; 
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(__DIR__); 
    if (isset($genres[$current_dir])) {
        $genre_name = $genres[$current_dir];
    }
}
$page_title = $thread['title'] . ' | ' . $genre_name . ' - DIARY';

$is_locked = !empty($thread['view_pass']);
$unlocked = false;
$auth_key = 'thread_auth_' . $id;

if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
    $is_locked = false; 
} elseif ($is_locked && isset($_SESSION[$auth_key])) {
    $unlocked = true;
}

$pass_error = '';
if ($is_locked && !$unlocked) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_pass'])) {
        if (password_verify($_POST['unlock_pass'], $thread['view_pass'])) {
            $_SESSION[$auth_key] = true;
            $unlocked = true;
            header("Location: thread.php?id=" . $id); exit;
        } else {
            $pass_error = 'パスワードが違います。';
        }
    }
}


$posts = [];
$total_pages = 1;

if (!$is_locked || $unlocked) {
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $order_sql = ($sort === 'desc') ? 'DESC' : 'ASC';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE thread_id = ?");
    $stmt->execute([$id]);
    $total_posts = $stmt->fetchColumn();
    $total_pages = ceil($total_posts / $per_page);
    if ($total_pages == 0) $total_pages = 1;

    $stmt = $pdo->prepare("SELECT * FROM posts WHERE thread_id = ? ORDER BY post_num $order_sql LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
}

function url_param($adds = []) {
    global $id, $sort, $page;
    $params = ['id' => $id, 'sort' => $sort, 'page' => $page];
    foreach ($adds as $k => $v) { $params[$k] = $v; }
    return '?' . http_build_query($params);
}

require 'header.php';
?>

<?php if ($thread['custom_css']): ?>
<style><?= $thread['custom_css'] ?></style>
<?php endif; ?>

<div class="container">

    <?php if ($is_locked && !$unlocked): ?>
        
        <?php if ($thread['lock_level'] == 1): ?>
            <div class="text-center" style="margin-bottom: 30px;">
                <div style="font-size: 0.9em; color: #ccc; margin-bottom: 15px; white-space: pre-wrap;"><?= $thread['description'] ?></div>
            </div>
            
            <div class="card" style="text-align: center; padding: 40px 20px; border-style:dashed;">
                <h3>ここから先はパスワードが必要です</h3>
                <?php if($pass_error): ?><p style="color: #ff6b6b;"><?= h($pass_error) ?></p><?php endif; ?>
                <form method="post" style="max-width: 300px; margin: 20px auto;">
                    <input type="password" name="unlock_pass" required placeholder="閲覧パスワード">
                    <button type="submit" class="btn" style="margin-top: 15px;">入室</button>
                </form>
            </div>

        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px 20px;">
                <h1 style="margin-bottom: 5px; border:none;"><?= h($thread['title']) ?></h1>
                <div style="font-size: 0.9em; color: #a0a0a0; margin-bottom: 10px;">
                    作成者: <?= h($thread['creator_name']) ?>
                </div>
                <h2>鍵付き日記</h2>
                <p>閲覧にはパスワードが必要です。</p>
                <?php if($pass_error): ?><p style="color: #ff6b6b;"><?= h($pass_error) ?></p><?php endif; ?>
                <form method="post" style="max-width: 300px; margin: 20px auto;">
                    <input type="password" name="unlock_pass" required placeholder="閲覧パスワード">
                    <button type="submit" class="btn" style="margin-top: 15px;">入室</button>
                </form>
            </div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:20px;"><a href="index.php">戻る</a></div>

    <?php else: ?>
        <div class="text-center" style="margin-bottom: 30px;">
            <div style="font-size: 0.9em; color: #ccc; margin-bottom: 15px; white-space: pre-wrap;"><?= $thread['description'] ?></div>
            
            <div style="text-align: right;">
                <a href="edit.php?type=thread&id=<?= $id ?>" style="font-size:0.8rem; color:#888;">[設定]</a>
            </div>
        </div>

        <div style="margin-bottom: 30px; font-size: 0.9rem; padding: 10px 0; text-align: center;">
            <a href="#bottom">▼底</a>
            | <a href="<?= url_param(['page' => max(1, $page - 1)]) ?>">前</a>
            | <a href="<?= url_param(['page' => min($total_pages, $page + 1)]) ?>">次</a>
            | <a href="<?= url_param(['sort' => 'asc', 'page' => 1]) ?>">初</a>
            | <a href="<?= url_param(['sort' => 'desc', 'page' => 1]) ?>">新</a>
            | <a href="search.php?id=<?= $id ?>">検</a>
            | <a href="write.php?id=<?= $id ?>" style="color:#ff6b6b; font-weight:bold;">書</a>
        </div>

        <div class="posts-area">
            <?php foreach ($posts as $post): ?>
                <div id="post-<?= $post['post_num'] ?>" style="margin-bottom: 30px; padding-bottom:15px; border-bottom:1px dashed #404040;">
                    <div style="margin-bottom: 8px; font-size: 0.95em;">
                        <a href="res.php?thread_id=<?= $id ?>&post_num=<?= $post['post_num'] ?>" target="_blank" style="font-weight: bold; color: #d4d4d4; margin-right:5px; text-decoration:none;">
                            <?= h($post['post_num']) ?> :
                        </a>
                        
                        <span style="font-weight: bold; margin-right:10px;"><?= h($post['name']) ?></span>
                        <span style="font-size: 0.8rem; color: #888;">
                            <?php 
                                $ts = strtotime($post['created_at']);
                                $time_diff = time() - $ts;
                                if (abs($time_diff) > 28800) { 
                                    echo date('Y/m/d H:i', $ts + 9*3600); 
                                } else {
                                    echo date('Y/m/d H:i', $ts);
                                }
                            ?>
                            <a href="edit.php?type=post&thread_id=<?= $id ?>&post_num=<?= $post['post_num'] ?>" style="color:#666; margin-left:5px; text-decoration:none;">[編集]</a>
                        </span>
                    </div>
                    <div style="line-height: 1.8; white-space: pre-wrap; word-wrap: break-word; letter-spacing: 0.05em;"><?php
                        $body = $post['content'];
                        $body = preg_replace('/(?<!>)>>>(\d+)/', '<a href="thread.php?id=$1">>>>$1</a>', $body);
                        $body = preg_replace('/(?<!>)>>(\d+)/', '<a href="res.php?thread_id=' . $id . '&post_num=$1" target="_blank">>>$1</a>', $body);
                        echo $body; 
                    ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 40px; margin-bottom: 40px; font-size: 0.9rem; text-align:center;">
            <a href="#top">▲頂</a>
            | <a href="<?= url_param(['page' => max(1, $page - 1)]) ?>">前</a>
            | <a href="<?= url_param(['page' => min($total_pages, $page + 1)]) ?>">次</a>
            | <a href="<?= url_param(['sort' => 'asc', 'page' => 1]) ?>">初</a>
            | <a href="<?= url_param(['sort' => 'desc', 'page' => 1]) ?>">新</a>
            | <a href="write.php?id=<?= $id ?>" style="color:#ff6b6b; font-weight:bold;">書</a>
        </div>
<center>
        <div class="text-center mt-4 mb-4" id="bottom">
            <a href="index.php" class="btn-glass" style="padding:10px 40px;">一覧に戻る</a>
        </div></center>
        <div class="footer-link"><?php require 'footer.php'; ?></div>

    <?php endif; ?>
</div>
</body>
</html>