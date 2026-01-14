<?php
// core_thread.php と同じ階層にある db.php を読み込む
require_once __DIR__ . '/db.php';

require_site_login();

// --- 独自リンク変換関数 (タイトル取得機能付き) ---
function convert_links_with_titles($text, $pdo) {
    // 1. 他ディレクトリへのリンク [blue>>>1]
    $text = preg_replace_callback('/\[([a-zA-Z0-9_-]+)>>>(\d+)\]/', function($m) {
        $dir = $m[1];
        $tid = $m[2];
        $label = $dir . '>>>' . $tid; 
        
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sort = $_GET['sort'] ?? 'desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$id]);
$thread = $stmt->fetch();

if (!$thread) { 
    header("Location: index.php"); 
    exit; 
}

$genre_name = 'DIARY'; 

// config.php の読み込み
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$current_dir_name = basename(dirname($_SERVER['SCRIPT_FILENAME'])); 
if (isset($genres[$current_dir_name])) {
    $genre_name = $genres[$current_dir_name];
}

$page_title = $thread['title'] . ' | ' . $genre_name . ' - DIARY';

// --- 閲覧制限ロジック ---

$has_pass = !empty($thread['view_pass']);
$is_locked = $has_pass;
$unlocked = false;

$site_unique_id = md5(dirname($_SERVER['SCRIPT_FILENAME']));
$session_key = 'diary_lock_' . $site_unique_id . '_thread_' . $id;

if (isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true) {
    $is_locked = false; 
} 

if ($is_locked) {
    if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true) {
        $unlocked = true;
    }
}

$pass_error = '';

if ($is_locked && !$unlocked) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_pass'])) {
        if (password_verify($_POST['unlock_pass'], $thread['view_pass'])) {
            $_SESSION[$session_key] = true;
            session_write_close();
            $query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
            header("Location: thread.php" . $query); 
            exit;
        } else {
            $pass_error = 'パスワードが違います。';
        }
    }
}

// ------------------------------------

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

if (file_exists(__DIR__ . '/header.php')) {
    require __DIR__ . '/header.php';
} else {
    require 'header.php';
}
?>

<?php 
$should_apply_css = (!$is_locked || $unlocked || $thread['lock_level'] == 1);

if ($thread['custom_css'] && $should_apply_css): 
?>
<style><?= $thread['custom_css'] ?></style>
<?php endif; ?>

<div class="container">

    <?php if ($is_locked && !$unlocked): ?>
        
        <?php if ($thread['lock_level'] == 1): ?>
            <div class="text-center-mb">
                <div style="font-size: 0.9em; color: var(--text-sub); margin-bottom: 15px; white-space: pre-wrap;"><?php 
                    $desc = trim($thread['description']);
                    // ■修正: タイトル付き変換関数を使用
                    $desc = convert_links_with_titles($desc, $pdo);
                    // ページ内リンク(>>1)のみここで変換
                    $desc = preg_replace('/(?<!>)>>(\d+)/', '<a href="res.php?thread_id=' . $id . '&post_num=$1" target="_blank">>>$1</a>', $desc);
                    echo $desc;
                ?></div>
            </div>
            
            <div class="card" style="text-align: center; padding: 40px 20px; border-style:dashed;">
                <h3 style="color: var(--text-main); border-bottom: none;">ここから先はパスワードが必要です</h3>
                <?php if($pass_error): ?><p style="color: #ff6b6b;"><?= h($pass_error) ?></p><?php endif; ?>
                <form method="post" style="max-width: 300px; margin: 20px auto;">
                    <input type="password" name="unlock_pass" required placeholder="閲覧パスワード">
                    <button type="submit" class="btn" style="margin-top: 15px;">入室</button>
                </form>
            </div>

        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px 20px;">
                <h1 style="margin-bottom: 5px; border:none;"><?= h($thread['title']) ?></h1>
                <div style="font-size: 0.9em; color: var(--text-sub); margin-bottom: 10px;">
                    作成者: <?= h($thread['creator_name']) ?>
                </div>
                <h2 style="color: var(--text-main);">鍵付き日記</h2>
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
        <div class="text-center-mb">
            <div style="font-size: 0.9em; color: var(--text-sub); margin-bottom: 15px; white-space: pre-wrap;"><?php 
                $desc = trim($thread['description']);
                // ■修正: タイトル付き変換関数を使用
                $desc = convert_links_with_titles($desc, $pdo);
                // ページ内リンク(>>1)のみここで変換
                $desc = preg_replace('/(?<!>)>>(\d+)/', '<a href="res.php?thread_id=' . $id . '&post_num=$1" target="_blank">>>$1</a>', $desc);
                echo $desc;
            ?></div>
            
            <div style="text-align: right;">
                <a href="edit.php?type=thread&id=<?= $id ?>" style="font-size:0.8rem; color:var(--text-sub);">[設定]</a>
            </div>
        </div>

        <div class="text-center-mb" style="font-size: 0.9rem; padding: 10px 0;">
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
                <div id="post-<?= $post['post_num'] ?>" style="margin-bottom: 30px; border-bottom:1px dashed var(--border); padding-bottom:15px;">
                    <div class="post-header">
                        <a href="res.php?thread_id=<?= $id ?>&post_num=<?= $post['post_num'] ?>" target="_blank" class="post-number">
                            <?= h($post['post_num']) ?> :
                        </a>
                        <span style="font-weight: bold; margin-right:10px;"><?= h($post['name']) ?></span>
                        <span class="post-date">
                        <?php 
                           $ts = strtotime($post['created_at']);
                           echo date('Y/m/d H:i', $ts);
                        ?>
                            <a href="edit.php?type=post&thread_id=<?= $id ?>&post_num=<?= $post['post_num'] ?>" style="color:var(--text-sub); margin-left:5px; text-decoration:none;">[編集]</a>
                        </span>
                    </div>
                    <div class="post-content"><?php
                        $body = trim($post['content']);
                        // ■修正: タイトル付き変換関数を使用
                        $body = convert_links_with_titles($body, $pdo);
                        // ページ内リンク(>>1)のみここで変換
                        $body = preg_replace('/(?<!>)>>(\d+)/', '<a href="res.php?thread_id=' . $id . '&post_num=$1" target="_blank">>>$1</a>', $body);
                        echo $body; 
                    ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center-box" style="font-size: 0.9rem;" id="bottom">
            <a href="#top">▲頂</a>
            | <a href="<?= url_param(['page' => max(1, $page - 1)]) ?>">前</a>
            | <a href="<?= url_param(['page' => min($total_pages, $page + 1)]) ?>">次</a>
            | <a href="<?= url_param(['sort' => 'asc', 'page' => 1]) ?>">初</a>
            | <a href="<?= url_param(['sort' => 'desc', 'page' => 1]) ?>">新</a>
            | <a href="write.php?id=<?= $id ?>" style="color:#ff6b6b; font-weight:bold;">書</a>
        </div>
        
        <div class="text-center-box">
            <a href="index.php" style="color: var(--text-sub); text-decoration: none;">&laquo; 一覧に戻る</a>
        </div>
        <div class="footer-link">
            <?php 
            if (file_exists(__DIR__ . '/footer.php')) {
                require __DIR__ . '/footer.php';
            } else {
                require 'footer.php';
            }
            ?>
        </div>
        
    <?php endif; ?>
</div>
</body>
</html>