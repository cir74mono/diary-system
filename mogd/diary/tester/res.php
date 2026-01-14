<?php
require 'db.php';
require_site_login(); 

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$t_id = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT);
$p_num = filter_input(INPUT_GET, 'post_num', FILTER_VALIDATE_INT);

$post = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT p.*, t.title as thread_title, t.custom_css FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
} 
elseif ($t_id && $p_num) {
    $stmt = $pdo->prepare("SELECT p.*, t.title as thread_title, t.custom_css FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.thread_id = ? AND p.post_num = ?");
    $stmt->execute([$t_id, $p_num]);
    $post = $stmt->fetch();
}

if (!$post) exit('該当する記事は削除されたか、存在しません。');

$genre_name = 'DIARY';
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(__DIR__);
    if (isset($genres[$current_dir])) {
        $genre_name = $genres[$current_dir];
    }
}
$page_title = 'No.' . $post['post_num'] . ' : ' . $post['thread_title'] . ' | ' . $genre_name . ' - DIARY';

require 'header.php';
?>

<?php if (!empty($post['custom_css'])): ?>
<style><?= $post['custom_css'] ?></style>
<?php endif; ?>

<div class="container">
    <div class="text-center mb-4">
        <p style="color:#a0a0a0; font-size:0.9em;">
            日記: <?= h($post['thread_title']) ?>
        </p>
    </div>

    <div class="card">
        <div style="border-bottom: 1px dashed #444; padding-bottom: 5px; margin-bottom: 15px; font-size: 0.95em; display:flex; justify-content:space-between;">
            <span>
                <span style="font-weight:bold; color:#d4d4d4; margin-right:5px;"><?= $post['post_num'] ?> :</span>
                <b><?= h($post['name']) ?></b>
            </span>
            <span style="color:#888;">
                <?php 
                    // タイムゾーン補正
                    $ts = strtotime($post['created_at']);
                    $time_diff = time() - $ts;
                    if (abs($time_diff) > 28800) { 
                        echo date('Y/m/d H:i', $ts + 9*3600); 
                    } else {
                        echo date('Y/m/d H:i', $ts);
                    }
                ?>
            </span>
        </div>

        <div style="line-height: 1.7; white-space: pre-wrap; word-wrap: break-word;">
            <?php
            $content = $post['content'];
            
            // アンカーリンク変換
            $content = preg_replace('/(?<!>)>>>(\d+)/', '<a href="thread.php?id=$1">>>>$1</a>', $content);
            $content = preg_replace('/(?<!>)>>(\d+)/', '<a href="res.php?thread_id=' . $post['thread_id'] . '&post_num=$1" target="_blank">>>$1</a>', $content);
            
            echo $content;
            ?>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="thread.php?id=<?= $post['thread_id'] ?>#post-<?= $post['post_num'] ?>">
            &raquo; この日記の場所へ戻る
        </a>
        <br><br>
        <a href="javascript:window.close();" class="btn" style="padding:5px 15px; font-size:0.8em;">× 閉じる</a>
    </div>
</div>
</body>
</html>