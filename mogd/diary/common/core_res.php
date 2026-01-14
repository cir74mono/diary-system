<?php
require __DIR__ . '/db.php';
require_site_login(); 

// --- 独自リンク変換関数 (タイトル取得機能付き) ---
function convert_links_with_titles($text, $pdo) {
    // 1. 他ディレクトリへのリンク [blue>>>1]
    $text = preg_replace_callback('/\[([a-zA-Z0-9_-]+)>>>(\d+)\]/', function($m) {
        $dir = $m[1];
        $tid = $m[2];
        $label = $dir . '>>>' . $tid; // デフォルト表記
        
        $db_path = __DIR__ . '/../' . $dir . '/bbs.db';
        if (file_exists($db_path)) {
            try {
                $pdo_sub = new PDO('sqlite:' . $db_path);
                $stmt = $pdo_sub->prepare("SELECT title FROM threads WHERE id = ?");
                $stmt->execute([$tid]);
                $fetched = $stmt->fetchColumn();
                if ($fetched) {
                    $label = htmlspecialchars($fetched, ENT_QUOTES, 'UTF-8');
                }
                $pdo_sub = null;
            } catch (Exception $e) {}
        }
        return '<a href="../' . $dir . '/thread.php?id=' . $tid . '" target="_blank">' . $label . '</a>';
    }, $text);

    // 2. 同ディレクトリ内の日記リンク >>>1
    $text = preg_replace_callback('/(?<!>)>>>(\d+)/', function($m) use ($pdo) {
        $tid = $m[1];
        $label = '>>>' . $tid;

        try {
            // 現在のDB($pdo)からタイトルを取得
            $stmt = $pdo->prepare("SELECT title FROM threads WHERE id = ?");
            $stmt->execute([$tid]);
            $fetched = $stmt->fetchColumn();
            if ($fetched) {
                $label = htmlspecialchars($fetched, ENT_QUOTES, 'UTF-8');
            }
        } catch (Exception $e) {}
        
        return '<a href="thread.php?id=' . $tid . '" target="_blank">' . $label . '</a>';
    }, $text);

    return $text;
}
// ------------------------

date_default_timezone_set('Asia/Tokyo');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$t_id = filter_input(INPUT_GET, 'thread_id', FILTER_VALIDATE_INT);
$p_num = filter_input(INPUT_GET, 'post_num', FILTER_VALIDATE_INT);

$post = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT p.*, t.title as thread_title, t.custom_css, t.view_pass FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
} 
elseif ($t_id && $p_num) {
    $stmt = $pdo->prepare("SELECT p.*, t.title as thread_title, t.custom_css, t.view_pass FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.thread_id = ? AND p.post_num = ?");
    $stmt->execute([$t_id, $p_num]);
    $post = $stmt->fetch();
}

if (!$post) exit('該当する記事は削除されたか、存在しません。');

if (!empty($post['view_pass'])) {
    $site_unique_id = md5(dirname($_SERVER['SCRIPT_FILENAME']));
    $session_key = 'diary_lock_' . $site_unique_id . '_thread_' . $post['thread_id'];
    
    if (empty($_SESSION[$session_key]) || $_SESSION[$session_key] !== true) {
        require 'header.php';
        ?>
        <div class="container">
            <div class="card" style="text-align:center; padding:40px 20px;">
                <h3 style="color:#ff6b6b; border:none;">この日記は鍵がかかっています</h3>
                <p style="margin-bottom:20px;">この記事を閲覧するには、親記事でパスワードを解除してください。</p>
                <a href="thread.php?id=<?= $post['thread_id'] ?>" class="btn">親記事（日記）へ移動</a>
            </div>
        </div>
        </body></html>
        <?php
        exit;
    }
}

$genre_name = 'DIARY';
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(getcwd());
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
    <div class="text-center-mb">
        <p style="color:var(--text-sub); font-size:0.9em;">
            日記: <?= h($post['thread_title']) ?>
        </p>
    </div>

    <div style="margin-bottom: 30px; padding-bottom:15px; border-bottom:1px dashed #404040;">
        <div style="margin-bottom: 8px; font-size: 0.95em; display:flex; justify-content:space-between;">
            <span>
                <span style="font-weight: bold; color: var(--text-sub); margin-right:5px;"><?= $post['post_num'] ?> :</span>
                <span style="font-weight: bold; margin-right:10px;"><?= h($post['name']) ?></span>
            </span>
            <span style="font-size: 0.8rem; color:var(--text-sub);">
                <?php 
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

        <div class="post-content"><?php
            $content = trim($post['content']);
            // ■修正: タイトル付き変換関数を使用
            $content = convert_links_with_titles($content, $pdo);
            // ページ内リンク(>>1)のみここで変換
            $content = preg_replace('/(?<!>)>>(\d+)/', '<a href="res.php?thread_id=' . $post['thread_id'] . '&post_num=$1" target="_blank">>>$1</a>', $content);
            
            echo $content;
        ?></div>
    </div>

    <div class="text-center-box">
        <a href="thread.php?id=<?= $post['thread_id'] ?>#post-<?= $post['post_num'] ?>">
            &raquo; この日記の場所へ戻る
        </a>
        <br><br>
        <a href="javascript:window.close();" class="btn btn-sm">× 閉じる</a>
    </div>
</div>
</body>
</html>