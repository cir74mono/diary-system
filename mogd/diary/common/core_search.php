<?php
require __DIR__ . '/db.php';
require_site_login();

$config_path = __DIR__ . '/../config.php';
$genres = [];
if (file_exists($config_path)) {
    include $config_path;
} else {
    $genres = [basename(getcwd()) => 'Current'];
}
$current_dir_name = basename(getcwd());

$thread_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; 
$q = $_GET['q'] ?? '';
$search_type = $_GET['type'] ?? 'keyword';
$input_genres = $_GET['genres'] ?? [$current_dir_name];
$target_genres = array_intersect($input_genres, array_keys($genres));

$is_thread_search = ($thread_id > 0);

$search_locked_error = false;
$thread_title_for_header = '';

if ($is_thread_search) {
    $stmt = $pdo->prepare("SELECT title, view_pass FROM threads WHERE id = ?");
    $stmt->execute([$thread_id]);
    $th_data = $stmt->fetch();
    
    if ($th_data) {
        $thread_title_for_header = $th_data['title'];
        if (!empty($th_data['view_pass'])) {
            $site_unique_id = md5(dirname($_SERVER['SCRIPT_FILENAME']));
            $session_key = 'diary_lock_' . $site_unique_id . '_thread_' . $thread_id;
            
            if (empty($_SESSION[$session_key]) || $_SESSION[$session_key] !== true) {
                $search_locked_error = true;
            }
        }
    } else {
        $thread_title_for_header = "不明な日記";
    }
}

$page_title = '検索 | ' . ($is_thread_search ? $thread_title_for_header : '詳細検索') . ' - DIARY';

require __DIR__ . '/header.php';

$results = [];

if ($q !== '' && !$search_locked_error) {
    if ($is_thread_search) {
        // 日記内検索
        $sql = "SELECT * FROM posts WHERE thread_id = ? AND content LIKE ? ORDER BY post_num ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$thread_id, '%' . $q . '%']);
        $results = $stmt->fetchAll();
    } else {
        // 詳細検索
        foreach ($genres as $dir => $genre_name) {
            if (!in_array($dir, $target_genres)) continue;
            if (strpos($dir, '.') !== false || strpos($dir, '/') !== false) continue;

            $target_db_path = __DIR__ . '/../' . $dir . '/bbs.db';
            
            if (file_exists($target_db_path)) {
                try {
                    $pdo_search = new PDO('sqlite:' . $target_db_path);
                    
                    if ($search_type === 'id') {
                        if (is_numeric($q)) {
                            $sql = "SELECT id, title, creator_name, description, view_pass, lock_level, created_at, 'thread' as res_type, ? as genre_dir, ? as genre_name 
                                    FROM threads 
                                    WHERE id = ?";
                            $stmt = $pdo_search->prepare($sql);
                            $stmt->execute([$dir, $genre_name, $q]);
                            $results = array_merge($results, $stmt->fetchAll());
                        }
                    } elseif ($search_type === 'title') {
                        $sql = "SELECT id, title, creator_name, description, view_pass, lock_level, created_at, 'thread' as res_type, ? as genre_dir, ? as genre_name 
                                FROM threads 
                                WHERE title LIKE ?";
                        $stmt = $pdo_search->prepare($sql);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                    } elseif ($search_type === 'author') {
                        // 作者検索：記事（鍵なしのみ）
                        $sql_post = "SELECT p.*, t.title as thread_title_name, t.view_pass, t.lock_level, 'post' as res_type, ? as genre_dir, ? as genre_name 
                                     FROM posts p 
                                     JOIN threads t ON p.thread_id = t.id 
                                     WHERE p.name LIKE ? 
                                     AND (t.view_pass IS NULL OR t.view_pass = '') 
                                     ORDER BY p.created_at DESC LIMIT 50";
                        $stmt = $pdo_search->prepare($sql_post);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                        
                        // 作者検索：スレッド（鍵ありもヒット）
                        $sql_thread = "SELECT id, title, creator_name, description, view_pass, lock_level, created_at, 'thread_creator' as res_type, ? as genre_dir, ? as genre_name 
                                       FROM threads 
                                       WHERE creator_name LIKE ? 
                                       LIMIT 20";
                        $stmt = $pdo_search->prepare($sql_thread);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());

                    } else {
                        // キーワード検索：記事（鍵なしのみ）
                        $sql_post = "SELECT p.*, t.title as thread_title_name, t.view_pass, t.lock_level, 'post' as res_type, ? as genre_dir, ? as genre_name 
                                     FROM posts p 
                                     JOIN threads t ON p.thread_id = t.id 
                                     WHERE p.content LIKE ? 
                                     AND (t.view_pass IS NULL OR t.view_pass = '') 
                                     ORDER BY p.created_at DESC LIMIT 50";
                        $stmt = $pdo_search->prepare($sql_post);
                        $stmt->execute([$dir, $genre_name, '%' . $q . '%']);
                        $results = array_merge($results, $stmt->fetchAll());
                        
                        // キーワード検索：スレッド概要（鍵なしのみ）
                        $sql_thread = "SELECT id, title, creator_name, description, view_pass, lock_level, created_at, 'thread_desc' as res_type, ? as genre_dir, ? as genre_name 
                                       FROM threads 
                                       WHERE description LIKE ? 
                                       AND (view_pass IS NULL OR view_pass = '') 
                                       LIMIT 20";
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
        <div class="text-center-mb">
            対象日記: <b><?= h($thread_title_for_header) ?></b>
        </div>

        <?php if ($search_locked_error): ?>
            <div class="msg-box msg-error">
                この日記は鍵がかかっています。<br>検索するには先に日記に入室（パスワード解除）してください。
            </div>
            <div class="text-center-box">
                <a href="thread.php?id=<?= $thread_id ?>">日記へ移動して解除する</a>
            </div>
        <?php else: ?>
            <div class="card">
                <form method="get" style="display:flex; gap:10px;">
                    <input type="hidden" name="id" value="<?= h($thread_id) ?>">
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="この日記内を検索..." required style="flex:1;">
                    <button type="submit" class="btn">検索</button>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <h1>詳細検索</h1>
        <div class="card">
            <form method="get">
                <div class="form-row">
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="検索語句を入力" required>
                </div>
                <div class="dashed-bottom">
                    <label style="margin-right: 15px; font-weight:bold;">条件:</label>
                    <div style="display:inline-block;">
                        <label style="margin-right: 10px;"><input type="radio" name="type" value="keyword" <?= $search_type==='keyword'?'checked':'' ?>> キーワード(本文)</label>
                        <label style="margin-right: 10px;"><input type="radio" name="type" value="author" <?= $search_type==='author'?'checked':'' ?>> 作成者名</label>
                        <label style="margin-right: 10px;"><input type="radio" name="type" value="title" <?= $search_type==='title'?'checked':'' ?>> タイトル</label>
                        <label><input type="radio" name="type" value="id" <?= $search_type==='id'?'checked':'' ?>> スレ番(ID)</label>
                    </div>
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
                <p style="font-size:0.8em; color:var(--text-sub); margin-top:10px;">※キーワード検索の場合、鍵付き日記の中身は検索対象外になります。</p>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($q !== '' && !$search_locked_error): ?>
        <p style="margin-bottom:20px;">ヒット: <b><?= count($results) ?></b>件</p>
        
        <?php if (count($results) === 0): ?>
            <div class="text-center-mb" style="padding: 30px; opacity: 0.6;">見つかりませんでした。</div>
        <?php else: ?>
            <?php foreach ($results as $res): ?>
                <?php 
                    $header_info = '';
                    $meta = '';
                    $content_raw = '';
                    $link = '#';
                    
                    $view_pass = $res['view_pass'] ?? null;
                    $lock_level = $res['lock_level'] ?? 0;
                    $res_type = $res['res_type'] ?? 'post';
                    
                    $is_locked = !empty($view_pass);
                    $lock_icon = '';
                    
                    // --- 表示判定ロジック ---
                    $show_content = false;

                    // 日記内検索なら常に表示
                    if ($is_thread_search) {
                        $show_content = true;
                    } 
                    // 詳細検索の場合
                    else {
                        // 鍵がかかっている場合
                        if ($is_locked) {
                            // アイコン設定
                            if ($lock_level == 1) {
                                $lock_icon = '🔒📖 '; 
                            } else {
                                $lock_icon = '🔒 '; 
                            }
                            // 鍵付きは中身（本文・概要）を一切表示しない
                            $show_content = false;
                        } 
                        // 鍵がない場合
                        else {
                            // キーワード検索のみ本文を表示
                            if ($search_type === 'keyword') {
                                $show_content = true;
                            } else {
                                // それ以外(ID,タイトル,作者)はシンプル表示
                                $show_content = false;
                            }
                        }
                    }

                    if ($is_thread_search) {
                        $link = "thread.php?id={$thread_id}#post-{$res['post_num']}";
                        $header_info = "No.{$res['post_num']} : <b>" . h($res['name']) . "</b>";
                        $meta = date('Y/m/d H:i', strtotime($res['created_at']));
                        $content_raw = $res['content'];
                    } else {
                        $g_dir = $res['genre_dir'];
                        $g_name = $res['genre_name'];
                        $link_base = ($g_dir === $current_dir_name) ? "" : "../{$g_dir}/";
                        
                        if ($res_type === 'post') {
                            $tid = $res['thread_id'];
                            $link = "{$link_base}thread.php?id={$tid}#post-{$res['post_num']}";
                            $header_info = "<span class='label-genre'>" . h($g_name) . "</span> 日記: {$lock_icon}<b>" . h($res['thread_title_name']) . "</b>";
                            $meta = "No.{$res['post_num']} : " . h($res['name']);
                            $content_raw = $res['content'];
                        } else {
                            $tid = $res['id'];
                            $link = "{$link_base}thread.php?id={$tid}";
                            $header_info = "<span class='label-genre'>" . h($g_name) . "</span> {$lock_icon}<b>" . h($res['title']) . "</b>";
                            $meta = "作成者: " . h($res['creator_name']);
                            $content_raw = $res['description'];
                            if(isset($res['res_type']) && $res['res_type'] === 'thread_desc') {
                                $content_raw = "【概要】 " . $content_raw;
                            }
                        }
                    }

                    $content_display = '';
                    if ($show_content) {
                        // HTMLタグを除去（プレーンテキスト化）
                        $plain = strip_tags($content_raw);
                        // 短く丸める場合はここで調整可能 (例: mb_substr)
                        // 今回は全文表示しつつハイライト
                        $safe_q = h($q);
                        // strip_tags済みなので h() は不要だが、念のためエスケープしてからハイライト
                        $content_display = h($plain);
                        $content_display = str_ireplace($safe_q, '<span class="highlight-match">'.$safe_q.'</span>', $content_display); 
                    }
                ?>

                <div class="card">
                    <div class="dashed-bottom" style="font-size: 0.9em;">
                        <div style="margin-bottom:5px;">
                            <?= $header_info ?>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span><?= $meta ?></span>
                            <span style="font-size:0.8em; color:var(--text-sub);"><?= date('Y/m/d H:i', strtotime($res['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php if ($show_content): ?>
                        <div style="line-height:1.6; font-size:0.9em; margin-top:10px; color:var(--text-main); word-wrap:break-word;"><?= $content_display ?></div>
                    <?php endif; ?>
                    <div style="text-align:right; margin-top:5px;">
                        <a href="<?= $link ?>" style="font-size:0.8em; color:var(--text-sub);">&raquo; 見に行く</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="footer-link">
        <?php if ($is_thread_search): ?>
            <a href="thread.php?id=<?= $thread_id ?>">日記に戻る</a>
        <?php else: ?>
        <div class="text-center-box">
            <a href="index.php" style="color: var(--text-sub); text-decoration: none;">&laquo; 一覧に戻る</a>
        </div>
        <?php endif; ?>
        <?php require __DIR__ . '/footer.php'; ?>
    </div>
</div>
</body>
</html>