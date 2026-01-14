<?php
require 'db.php';
require_site_login();

// --- 設定読み込み ---
$config_path = __DIR__ . '/../config.php';
$genres = [];
if (file_exists($config_path)) {
    include $config_path;
} else {
    $genres = [basename(__DIR__) => 'Current'];
}
$current_dir_name = basename(__DIR__);

// --- 入力受け取り ---
$thread_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // スレッドID
$q = $_GET['q'] ?? '';
$search_type = $_GET['type'] ?? 'keyword';
$target_genres = $_GET['genres'] ?? [$current_dir_name];

// スレッド内検索モードかどうか
$is_thread_search = ($thread_id > 0);

require 'header.php';

$results = [];
$thread_title = '';

// --- 検索実行 ---
if ($q !== '') {
    
    // A. スレッド内検索 (シンプルモード)
    if ($is_thread_search) {
        $stmt = $pdo->prepare("SELECT title FROM threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        $thread_title = $stmt->fetchColumn();
        
        if (!$thread_title) $thread_title = "不明な日記";

        // シンプルに本文のみ検索
        $sql = "SELECT * FROM posts WHERE thread_id = ? AND content LIKE ? ORDER BY post_num ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$thread_id, '%' . $q . '%']);
        $results = $stmt->fetchAll();
    } 
    // B. 全体・詳細検索 (高機能モード)
    else {
        $thread_title = "詳細検索結果";
        
        foreach ($genres as $dir => $genre_name) {
            if (!in_array($dir, $target_genres)) continue;

            $target_db_path = __DIR__ . '/../' . $dir . '/bbs.db';
            if (file_exists($target_db_path)) {
                try {
                    $pdo_search = new PDO('sqlite:' . $target_db_path);
                    // (エラー設定などは省略)

                    if ($search_type === 'id') {
                        if (is_numeric($q)) {
                            $sql = "SELECT id, title, creator_name, description, created_at, 'thread' as res_type, ? as genre_dir, ? as genre_name FROM threads WHERE id = ?";
                            $stmt = $pdo_search->prepare($sql);
                            $stmt->execute([$dir, $genre_name, $q]);
                            $results = array_merge($results, $stmt->fetchAll());
                        }
                    } elseif ($search_type === 'title') {
                        $sql = "SELECT id, title, creator_name, description, created_at, 'thread' as res_type, ? as genre_dir, ? as genre_name FROM threads WHERE title LIKE ?";
                        $stmt = $pdo_search->prepare($sql);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                    } elseif ($search_type === 'author') {
                        // 投稿者
                        $sql_post = "SELECT p.*, t.title as thread_title_name, 'post' as res_type, ? as genre_dir, ? as genre_name FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.name LIKE ? ORDER BY p.created_at DESC LIMIT 50";
                        $stmt = $pdo_search->prepare($sql_post);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                        // 作成者
                        $sql_thread = "SELECT id, title, creator_name, description, created_at, 'thread_creator' as res_type, ? as genre_dir, ? as genre_name FROM threads WHERE creator_name LIKE ? LIMIT 20";
                        $stmt = $pdo_search->prepare($sql_thread);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                    } else {
                        // キーワード
                        $sql_post = "SELECT p.*, t.title as thread_title_name, 'post' as res_type, ? as genre_dir, ? as genre_name FROM posts p JOIN threads t ON p.thread_id = t.id WHERE p.content LIKE ? ORDER BY p.created_at DESC LIMIT 50";
                        $stmt = $pdo_search->prepare($sql_post);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                        
                        $sql_thread = "SELECT id, title, creator_name, description, created_at, 'thread_desc' as res_type, ? as genre_dir, ? as genre_name FROM threads WHERE description LIKE ? LIMIT 20";
                        $stmt = $pdo_search->prepare($sql_thread);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                    }
                    $pdo_search = null;
                } catch (Exception $e) { continue; }
            }
        }
        usort($results, function ($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
    }
}
?>

<div class="container">
    <?php if ($is_thread_search): ?>
        <h1>日記内検索</h1>
        <div class="text-center mb-4">
            対象日記: <b><?= h($thread_title) ?></b>
        </div>
        <div class="card">
            <form method="get" style="display:flex; gap:10px;">
                <input type="hidden" name="id" value="<?= h($thread_id) ?>">
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="この日記内を検索..." required style="flex:1;">
                <button type="submit" class="btn">検索</button>
            </form>
        </div>

    <?php else: ?>
        <h1>詳細検索</h1>
        <div class="card">
            <form method="get">
                <div style="margin-bottom: 15px;">
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="検索語句を入力" required>
                </div>
                <div style="margin-bottom: 15px; border-bottom:1px solid #444; padding-bottom:15px;">
                    <label style="margin-right: 15px; font-weight:bold;">条件:</label>
                    <label style="margin-right: 10px;"><input type="radio" name="type" value="keyword" <?= $search_type==='keyword'?'checked':'' ?>> キーワード</label>
                    <label style="margin-right: 10px;"><input type="radio" name="type" value="author" <?= $search_type==='author'?'checked':'' ?>> 作者名</label>
                    <label style="margin-right: 10px;"><input type="radio" name="type" value="title" <?= $search_type==='title'?'checked':'' ?>> タイトル</label>
                    <label><input type="radio" name="type" value="id" <?= $search_type==='id'?'checked':'' ?>> スレ番(ID)</label>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="margin-right: 15px; font-weight:bold;">対象:</label>
                    <?php foreach ($genres as $dir => $name): ?>
                        <label style="margin-right: 15px; display:inline-block;">
                            <input type="checkbox" name="genres[]" value="<?= h($dir) ?>" <?= in_array($dir, $target_genres) ? 'checked' : '' ?>> <?= h($name) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn" style="width:100%;">検索する</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($q !== ''): ?>
        <p style="margin-bottom:20px;">ヒット: <b><?= count($results) ?></b>件</p>
        
        <?php if (count($results) === 0): ?>
            <div class="text-center" style="padding: 30px; opacity: 0.6;">見つかりませんでした。</div>
        <?php else: ?>
            <?php foreach ($results as $res): ?>
                <?php 
                    // 表示用変数の準備
                    if ($is_thread_search) {
                        // シンプルモード用
                        $link = "thread.php?id={$thread_id}#post-{$res['post_num']}";
                        $meta = "No.{$res['post_num']} : <b>" . h($res['name']) . "</b>";
                        $content = $res['content'];
                        $header_info = date('Y/m/d H:i', strtotime($res['created_at']));
                    } else {
                        // 詳細モード用
                        $g_dir = $res['genre_dir'];
                        $g_name = $res['genre_name'];
                        $res_type = $res['res_type'] ?? 'post';
                        $link_base = ($g_dir === $current_dir_name) ? "" : "../{$g_dir}/";
                        
                        if ($res_type === 'post') {
                            $tid = $res['thread_id'];
                            $link = "{$link_base}thread.php?id={$tid}#post-{$res['post_num']}";
                            $header_info = "<span style='background:#555; color:#fff; padding:2px 6px; font-size:0.7em; border-radius:4px; margin-right:5px;'>" . h($g_name) . "</span> 日記: <b>" . h($res['thread_title_name']) . "</b>";
                            $meta = "No.{$res['post_num']} : <b>" . h($res['name']) . "</b>";
                            $content = $res['content'];
                        } else {
                            $tid = $res['id'];
                            $link = "{$link_base}thread.php?id={$tid}";
                            $header_info = "<span style='background:#555; color:#fff; padding:2px 6px; font-size:0.7em; border-radius:4px; margin-right:5px;'>" . h($g_name) . "</span> <b>" . h($res['title']) . "</b>";
                            $meta = "作成者: " . h($res['creator_name']);
                            $content = $res['description'];
                            if(isset($res['res_type']) && $res['res_type'] === 'thread_desc') $content = "【概要】<br>" . $content;
                        }
                    }

                    // ハイライト
                    $safe_q = h($q);
                    $content_display = str_ireplace($safe_q, '<span style="background:#ffd700; color:#000;">'.$safe_q.'</span>', $content); // HTML許可のためh()なし
                ?>

                <div class="card">
                    <div style="border-bottom: 1px dashed #444; padding-bottom: 5px; margin-bottom: 10px; font-size: 0.9em;">
                        <div style="margin-bottom:5px;">
                            <?= $header_info ?>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span><?= $meta ?></span>
                            <span style="font-size:0.8em; color:#888;"><?= date('Y/m/d H:i', strtotime($res['created_at'])) ?></span>
                        </div>
                    </div>
                    <div style="line-height:1.6; font-size:0.95em; white-space: pre-wrap;"><?= $content_display ?></div>
                    <div style="text-align:right; margin-top:10px;">
                        <a href="<?= $link ?>" style="font-size:0.8em; color:#888;">&raquo; 見に行く</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="footer-link">
        <?php if ($is_thread_search): ?>
            <a href="thread.php?id=<?= $thread_id ?>">日記に戻る</a>
        <?php else: ?>
        <div style="text-align: center; margin-top: 40px; margin-bottom: 20px;">
        <a href="index.php" style="color: #888; text-decoration: none;">&laquo; 一覧に戻る</a>
    </div>
        <?php endif; ?>
<?php require 'footer.php'; ?>
    </div>
</div>
</body>
</html>