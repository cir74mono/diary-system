<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';

// タイムゾーン設定 (JST)
date_default_timezone_set('Asia/Tokyo');

$msg = '';

// マスターDB (ログイン認証・共通設定用)
$master_db = __DIR__ . '/blue/bbs.db'; 
if (!file_exists($master_db)) exit('BlueフォルダのDB(マスター)が見つかりません。');
$mpdo = new PDO('sqlite:' . $master_db);

// --- 1. ログイン処理 ---
if (!isset($_SESSION['admin_auth'])) {
    if (isset($_POST['login_pass'])) {
        $stmt = $mpdo->prepare("SELECT value FROM settings WHERE key = 'admin_pass'");
        $stmt->execute();
        $hash = $stmt->fetchColumn();
        if ($hash && password_verify($_POST['login_pass'], $hash)) {
            $_SESSION['admin_auth'] = true;
            header("Location: adminmin.php"); exit;
        } else {
            $msg = 'パスワードが違います';
        }
    }
}
if (isset($_GET['logout'])) { unset($_SESSION['admin_auth']); header("Location: adminmin.php"); exit; }

// --- 2. ログイン後の処理 ---
$current_genre = $_GET['genre'] ?? 'blue';
// 要望板へのリンクが残っていた場合のフォールバック
if ($current_genre === 'inquiry') $current_genre = 'blue';
if (!array_key_exists($current_genre, $genres)) $current_genre = 'blue';

$notice_text = '';
$search_q = $_GET['search_q'] ?? ''; 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$total_pages = 1;
$threads = [];
$requests = []; 

// フラグ: どのアクションが行われたか（これを使って該当箇所を自動で開きます）
$act_genre   = (isset($_GET['genre']) || isset($_GET['search_q']) || isset($_POST['update_notice']) || isset($_POST['update_thread_pass']) || isset($_POST['delete_thread_id']));
$act_update  = (isset($_POST['add_update_history']) || isset($_POST['delete_update_history']) || isset($_POST['edit_update_history']));
$act_inquiry = (isset($_POST['update_inquiry']) || isset($_POST['delete_inquiry']));
$act_site    = (isset($_POST['update_site_config']));
$act_pass    = (isset($_POST['change_sys_pass']));

// スクロール先のIDを決定
$scroll_target = '';
if ($act_pass)    $scroll_target = 'sec_pass';
if ($act_genre)   $scroll_target = 'sec_genre';
if ($act_update)  $scroll_target = 'sec_update';
if ($act_inquiry) $scroll_target = 'sec_inquiry';
if ($act_site)    $scroll_target = 'sec_site';


if (isset($_SESSION['admin_auth'])) {
    try {
        // ==================================================
        // 0. 更新履歴管理 (Update History)
        // ==================================================
        
        // テーブル作成
        $mpdo->exec("CREATE TABLE IF NOT EXISTS site_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            update_date TEXT,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 追加
        if (isset($_POST['add_update_history'])) {
            $u_date = $_POST['update_date'];
            $u_content = $_POST['update_content'];
            if ($u_date && $u_content) {
                $stmt = $mpdo->prepare("INSERT INTO site_updates (update_date, content) VALUES (?, ?)");
                $stmt->execute([$u_date, $u_content]);
                $msg = "更新履歴を追加しました。";
            }
        }
        // 削除
        if (isset($_POST['delete_update_history'])) {
            $del_id = $_POST['del_id'];
            $mpdo->prepare("DELETE FROM site_updates WHERE id = ?")->execute([$del_id]);
            $msg = "更新履歴を削除しました。";
        }
        // 編集 (※SELECTの前に配置！)
        if (isset($_POST['edit_update_history'])) {
            $id = $_POST['edit_id'];
            $u_date = $_POST['edit_date'];
            $u_content = $_POST['edit_content'];
            if ($id && $u_date && $u_content) {
                $stmt = $mpdo->prepare("UPDATE site_updates SET update_date = ?, content = ? WHERE id = ?");
                $stmt->execute([$u_date, $u_content, $id]);
                $msg = "更新履歴(ID:$id)を修正しました。";
            }
        }

        // データ取得
        $site_updates = $mpdo->query("SELECT * FROM site_updates ORDER BY update_date DESC, id DESC LIMIT 50")->fetchAll();


        // ==================================================
        // 1. サイト全体設定 / パスワード変更
        // ==================================================
        if (isset($_POST['update_site_config'])) {
            $conf_max_threads = (int)$_POST['conf_max_threads'];
            $conf_del_days    = (int)$_POST['conf_del_days'];
            $conf_rate_limit  = (int)$_POST['conf_rate_limit'];
            
            foreach ($genres as $dir => $name) {
                $target_db = __DIR__ . '/' . $dir . '/bbs.db';
                if (file_exists($target_db)) {
                    $tmp_pdo = new PDO('sqlite:' . $target_db);
                    $tmp_pdo->prepare("REPLACE INTO settings (key, value) VALUES ('max_threads', ?)")->execute([$conf_max_threads]);
                    $tmp_pdo->prepare("REPLACE INTO settings (key, value) VALUES ('auto_delete_days', ?)")->execute([$conf_del_days]);
                    $tmp_pdo->prepare("REPLACE INTO settings (key, value) VALUES ('rate_limit_per_hour', ?)")->execute([$conf_rate_limit]);
                }
            }
            $msg = "サイト全体の運用設定を更新しました。";
        }

        if (isset($_POST['change_sys_pass'])) {
            if (!empty($_POST['new_pass'])) {
                $new_hash = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
                $key = $_POST['change_type'];
                foreach ($genres as $dir => $name) {
                    $target_db = __DIR__ . '/' . $dir . '/bbs.db';
                    if (file_exists($target_db)) {
                        $tmp_pdo = new PDO('sqlite:' . $target_db);
                        $tmp_pdo->prepare("REPLACE INTO settings (key, value) VALUES (?, ?)")->execute([$key, $new_hash]);
                    }
                }
                $pass_names = ['site_pass'=>'サイト閲覧PW', 'create_pass'=>'作成PW', 'admin_pass'=>'管理PW'];
                $msg = "【全板更新】" . ($pass_names[$key]??$key) . "を変更しました。";
            }
        }


        // ==================================================
        // 2. 要望・報告板 (Inquiry) - 独立処理
        // ==================================================
        $req_db_path = __DIR__ . '/inquiry/board.db';
        if (file_exists($req_db_path)) {
            $qpdo = new PDO('sqlite:' . $req_db_path);
            $qpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 更新
            if (isset($_POST['update_inquiry'])) {
                $id = $_POST['req_id'];
                $status = $_POST['admin_status'];
                $reply = $_POST['admin_reply'];
                $qpdo->prepare("UPDATE requests SET admin_status = ?, admin_reply = ? WHERE id = ?")->execute([$status, $reply, $id]);
                $msg = "要望(ID:$id)を更新しました。";
            }
            // 削除
            if (isset($_POST['delete_inquiry'])) {
                $id = $_POST['req_id'];
                $qpdo->prepare("DELETE FROM requests WHERE id = ?")->execute([$id]);
                $msg = "要望(ID:$id)を削除しました。";
            }
            // データ取得 (最新50件固定)
            $requests = $qpdo->query("SELECT * FROM requests ORDER BY created_at DESC LIMIT 50")->fetchAll();
        }


        // ==================================================
        // 3. 通常日記ジャンル (Genre)
        // ==================================================
        $db_path = __DIR__ . '/' . $current_genre . '/bbs.db';
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // お知らせ更新
        if (isset($_POST['update_notice'])) {
            $text = $_POST['notice_text'];
            if (isset($_POST['update_all'])) {
                foreach ($genres as $dir => $n) {
                    $tmp = new PDO('sqlite:' . __DIR__ . '/' . $dir . '/bbs.db');
                    $tmp->prepare("REPLACE INTO settings (key, value) VALUES ('notice_text', ?)")->execute([$text]);
                }
                $msg = "全ジャンルのお知らせを一括更新しました";
            } else {
                $pdo->prepare("REPLACE INTO settings (key, value) VALUES ('notice_text', ?)")->execute([$text]);
                $msg = "{$genres[$current_genre]} のお知らせを更新しました";
            }
        }
        
        // 削除
        if (isset($_POST['delete_thread_id'])) {
            $tid = $_POST['delete_thread_id'];
            $pdo->prepare("DELETE FROM posts WHERE thread_id = ?")->execute([$tid]);
            $pdo->prepare("DELETE FROM threads WHERE id = ?")->execute([$tid]);
            $msg = "スレッド(ID:{$tid})を削除しました";
        }

        // パスワード変更
        if (isset($_POST['update_thread_pass'])) {
            $tid = $_POST['target_id'];
            $pass_type = $_POST['pass_type'];
            $new_val = $_POST['new_thread_pass'];
            
            if ($new_val === '') {
                if ($pass_type === 'view_pass') {
                    $pdo->prepare("UPDATE threads SET view_pass = NULL WHERE id = ?")->execute([$tid]);
                    $msg = "日記(ID:$tid)の閲覧パスワードを解除しました（公開状態）";
                } else {
                    $msg = "エラー: 編集/削除パスワードは空にできません。";
                }
            } else {
                $hash = password_hash($new_val, PASSWORD_DEFAULT);
                if ($pass_type === 'view_pass' || $pass_type === 'del_pass') {
                    $sql = "UPDATE threads SET {$pass_type} = ? WHERE id = ?";
                    $pdo->prepare($sql)->execute([$hash, $tid]);
                    $label = ($pass_type === 'view_pass') ? '閲覧' : '編集/削除';
                    $msg = "日記(ID:$tid)の{$label}パスワードを変更しました";
                }
            }
        }

        // データ取得
        $notice_text = $pdo->query("SELECT value FROM settings WHERE key = 'notice_text'")->fetchColumn();
        
        $where_sql = "";
        $params = [];
        if ($search_q !== '') {
            $where_sql = " WHERE title LIKE ? OR id = ?";
            $params[] = '%' . $search_q . '%';
            $params[] = $search_q;
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM threads" . $where_sql);
        $count_stmt->execute($params);
        $total_threads = $count_stmt->fetchColumn();
        $total_pages = ceil($total_threads / $limit) ?: 1;

        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM threads" . $where_sql . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $threads = $stmt->fetchAll();

        // 共通設定値 (表示用)
        $conf_max_threads = $mpdo->query("SELECT value FROM settings WHERE key = 'max_threads'")->fetchColumn() ?: 500;
        $conf_del_days    = $mpdo->query("SELECT value FROM settings WHERE key = 'auto_delete_days'")->fetchColumn() ?: 60;
        $conf_rate_limit  = $mpdo->query("SELECT value FROM settings WHERE key = 'rate_limit_per_hour'")->fetchColumn() ?: 100;

    } catch (Exception $e) { $msg = "Error: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Admin</title>
    <link rel="stylesheet" href="common/css/style.css">
    <style>
        .tab-nav { display:flex; gap:5px; border-bottom:1px solid #404040; margin-bottom:20px; flex-wrap: wrap; }
        .tab-item { 
            padding:10px 20px; 
            background: #202020; 
            color: #888; 
            text-decoration:none; 
            font-weight:bold; 
            border-radius:5px 5px 0 0; 
            border: 1px solid #404040;
            border-bottom: none;
            transition: 0.3s;
        }
        .tab-item:hover { background: #333; color: #ccc; }
        .tab-item.active { background: #404040; color: #fff; border-color: #404040; }
        
        .btn-danger { background-color: #8B0000; color: #ffcccc; border: 1px solid #ff4f4f; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .btn-danger:hover { background-color: #a00000; }

        .btn-primary { background-color: #2c3e50; color: #fff; border: 1px solid #3498db; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .btn-primary:hover { background-color: #34495e; }
        
        details { 
            margin-top: 10px; 
            border: 1px solid #404040; 
            border-radius: 4px; 
            padding: 10px; 
            background-color: var(--card-bg, #2b2b2b);
            color: var(--text-main, #e0e0e0);
        }
        
        summary { 
            cursor: pointer; 
            font-weight: bold; 
            color: #ffffff;
            outline: none; 
            transition: color 0.3s;
        }
        summary:hover { 
            color: #ffdb4f;
        }
        
        .config-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; border-bottom:1px dashed #404040; padding-bottom:5px; }
        .config-row label { font-weight:bold; font-size:0.9rem; color: #ccc; }
        .config-row input { width:80px; text-align:center; background: #333; color: #fff; border: 1px solid #555; border-radius: 3px; }

        .looker-studio-container {
            width: 100%;
            aspect-ratio: 16 / 9;
            max-height: 500px;
            overflow: hidden;
            background: #333;
            border: 1px solid #404040;
            border-radius: 4px;
            margin: 0 auto;
        }
        .looker-studio-container iframe { width: 100%; height: 100%; border: 0; }

        .admin-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        .admin-pagination a {
            padding: 5px 10px;
            background: #404040;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
        }
        .admin-pagination span { color: #ccc; font-size: 0.9em; }
        
        .req-card {
            margin-bottom: 15px; 
            padding: 0;
            border: 1px solid #555; 
            border-radius: 4px; 
            background: #2b2b2b;
            overflow: hidden;
        }
        .req-header {
            padding: 10px 15px;
            background: #333;
            border-bottom: 1px solid #444;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            font-size: 0.9em;
            color: #ccc;
        }
        .req-body { padding: 15px; }
        .req-footer {
            padding: 10px 15px;
            background: #303030;
            border-top: 1px dashed #444;
        }
        
        .status-badge {
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 3px;
            text-shadow: 1px 1px 0px rgba(0,0,0,0.5);
        }
        .st-done { color: #4caf50; border: 1px solid #4caf50; background: rgba(76, 175, 80, 0.1); }
        .st-wont { color: #b0bec5; border: 1px solid #b0bec5; background: rgba(176, 190, 197, 0.1); }
        .st-yet  { color: #ff5252; border: 1px solid #ff5252; background: rgba(255, 82, 82, 0.1); }

        .badge-genre { color: #fff; background: #555; padding: 1px 5px; border-radius: 3px; font-size: 0.8em; }
        .badge-private { color: #ff6b6b; border: 1px solid #ff6b6b; padding: 0 4px; border-radius: 3px; font-size: 0.8em; margin-left: 5px; }
        
        /* 共通テキストエリア (マルチライン入力用) */
        .admin-textarea {
            width: 100%;
            background: #222;
            color: #fff;
            border: 1px solid #555;
            padding: 8px;
            border-radius: 3px;
            font-size: 0.95rem;
            resize: vertical; /* 縦方向にリサイズ可能 */
        }
        .admin-textarea:focus { border-color: #888; outline: none; }
        
        .radio-group label {
            margin-right: 15px;
            cursor: pointer;
            color: #ddd;
            font-size: 0.9em;
        }
        .radio-group input { vertical-align: middle; margin-right: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1>総合管理画面</h1>
    
    <?php if (!isset($_SESSION['admin_auth'])): ?>
        <div class="card text-center" style="max-width:400px; margin:auto;">
            <?php if ($msg): ?><p style="color:red;"><?= h($msg) ?></p><?php endif; ?>
            <form method="post">
                <input type="password" name="login_pass" class="form-control-glass mb-4" placeholder="管理パスワード"><br><br>
                <button class="btn-glass">ログイン</button>
            </form><br>
            <div class="mt-4"><a href="index.php">戻る</a></div>
        </div>

    <?php else: ?>
        <div class="actions">
            <a href="index.php" class="btn-glass">サイトトップ</a>
            <a href="?logout=1" class="btn-glass" style="color:#ff6b6b; border-color:#ff6b6b;">ログアウト</a>
        </div>

        <?php if($msg): ?>
            <div class="card" style="color:#a5d6a7; text-align:center; background:rgba(0,100,0,0.3); border-color:#2e7d32;">
                <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <details class="card" id="sec_pass" <?= $act_pass ? 'open' : '' ?>>
            <summary>🔑 システムパスワード変更 (全ジャンル一括適用)</summary>
            <form method="post" style="padding:15px; display:flex; flex-wrap:wrap; gap:10px; align-items:end;">
                <div style="flex:1; min-width:200px;">
                    <label style="font-size:0.8em; display:block; color:#aaa;">変更対象</label>
                    <select name="change_type" class="form-control-glass" style="width:100%;">
                        <option value="create_pass">日記作成パスワード</option>
                        <option value="site_pass">サイト閲覧パスワード (Login)</option>
                        <option value="admin_pass">管理ログインパスワード</option>
                    </select>
                </div>
                <div style="flex:1; min-width:200px;">
                    <label style="font-size:0.8em; display:block; color:#aaa;">新しいパスワード</label>
                    <input type="text" name="new_pass" class="form-control-glass" placeholder="New Password" required>
                </div>
                <button type="submit" name="change_sys_pass" class="btn-glass" style="font-weight:bold;">変更実行</button>
            </form>
        </details>

        <details class="card" id="sec_genre" <?= $act_genre ? 'open' : '' ?>>
            <summary>📁 ジャンル別板管理</summary>
            <div style="padding:15px;">
                <div class="tab-nav">
                    <?php foreach($genres as $d => $n): ?>
                        <a href="?genre=<?= $d ?>" class="tab-item <?= $current_genre==$d?'active':'' ?>"><?= h($n) ?></a>
                    <?php endforeach; ?>
                </div>

                <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:4px; border:1px solid #444; margin-bottom:20px;">
                    <h4 style="margin-top:0; border-bottom:1px solid #555; padding-bottom:5px; margin-bottom:10px;">📢 お知らせ設定 (対象: <?= h($genres[$current_genre]) ?>)</h4>
                    <form method="post">
                        <textarea name="notice_text" class="form-control-glass mb-2" rows="3" style="width:100%;"><?= h($notice_text) ?></textarea>
                        <label style="display:block; margin-bottom:10px; cursor:pointer;">
                            <input type="checkbox" name="update_all" value="1"> <span style="color:#ccc; font-size:0.9em;">全ジャンルのお知らせを一括上書き</span>
                        </label>
                        <div style="text-align:right;">
                            <button name="update_notice" class="btn-glass btn-sm">更新</button>
                        </div>
                    </form>
                </div>

                <div>
                    <h4 style="margin-top:0; border-bottom:1px solid #555; padding-bottom:5px; margin-bottom:15px;">📔 日記一覧 (<?= h($genres[$current_genre]) ?>)</h4>
                    
                    <form method="get" style="margin-bottom:20px; display:flex; gap:5px; align-items:center;">
                        <input type="hidden" name="genre" value="<?= h($current_genre) ?>">
                        <input type="text" name="search_q" value="<?= h($search_q) ?>" class="form-control-glass" placeholder="スレッド名 または ID で検索" style="max-width:300px;">
                        <button class="btn-glass">検索</button>
                        <?php if($search_q !== ''): ?>
                            <a href="?genre=<?= h($current_genre) ?>" class="btn-glass" style="color:#aaa; border-color:#666; text-decoration:none;">リセット</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($threads)): ?>
                        <p>日記がありません。</p>
                    <?php else: ?>
                        <?php foreach($threads as $t): ?>
                            <div class="card-glass" style="margin-bottom: 10px; padding: 15px; border:1px solid #555;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <span style="font-size:0.8em; color:#666;">ID:<?= $t['id'] ?></span>
                                        <b><?= h($t['title']) ?></b>
                                        <span style="font-size:0.8em;">(作成者: <?= h($t['creator_name']) ?>)</span>
                                        <?php if($t['view_pass']): ?>
                                            <span style="color:#ff6b6b; font-size:0.8em; border:1px solid #ff6b6b; padding:1px 4px; border-radius:3px;">🔒鍵あり</span>
                                        <?php else: ?>
                                            <span style="color:#4fc3f7; font-size:0.8em; border:1px solid #4fc3f7; padding:1px 4px; border-radius:3px;">公開中</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="min-width:120px; text-align:right;">
                                        <a href="<?= $current_genre ?>/thread.php?id=<?= $t['id'] ?>" target="_blank" class="btn-glass btn-sm">表示</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('【確認】\n日記「<?= h($t['title']) ?>」を本当に削除しますか？\n\n※この操作は取り消せません。');">
                                            <input type="hidden" name="delete_thread_id" value="<?= $t['id'] ?>">
                                            <button class="btn-danger">削除</button>
                                        </form>
                                    </div>
                                </div>
                                <details style="background:rgba(0,0,0,0.1); border-color:#555;">
                                    <summary style="font-size:0.9em;">パスワード変更・詳細設定</summary>
                                    <div style="margin-top:10px;">
                                        <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                            <input type="hidden" name="target_id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="update_thread_pass" value="1">
                                            <div style="flex:1;">
                                                <label style="font-size:0.8em; color:#aaa;">変更対象</label><br>
                                                <select name="pass_type" class="form-control-glass" style="width:100%;">
                                                    <option value="view_pass">閲覧パスワード</option>
                                                    <option value="del_pass">編集/削除パスワード</option>
                                                </select>
                                            </div>
                                            <div style="flex:2;">
                                                <label style="font-size:0.8em; color:#aaa;">新しいパスワード</label><br>
                                                <input type="text" name="new_thread_pass" class="form-control-glass" placeholder="空欄で送信するとロック解除(閲覧のみ)" style="width:100%;">
                                            </div>
                                            <div style="align-self: flex-end;">
                                                <button class="btn-primary">変更保存</button>
                                            </div>
                                        </form>
                                        <p style="font-size:0.8em; color:#aaa; margin-top:5px; margin-bottom:0;">
                                            ※「閲覧パスワード」を選択し、空欄で保存するとパスワードが解除され<b>公開状態</b>になります。
                                        </p>
                                    </div>
                                </details>
                            </div>
                        <?php endforeach; ?>
                        <?php if($total_pages > 1): ?>
                            <div class="admin-pagination">
                                <?php if($page > 1): ?><a href="?genre=<?=h($current_genre)?>&search_q=<?=h($search_q)?>&page=<?= $page-1 ?>">« 前</a><?php endif; ?>
                                <span><?= $page ?> / <?= $total_pages ?></span>
                                <?php if($page < $total_pages): ?><a href="?genre=<?=h($current_genre)?>&search_q=<?=h($search_q)?>&page=<?= $page+1 ?>">次 »</a><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </details>

        <details class="card" id="sec_update" <?= $act_update ? 'open' : '' ?>>
            <summary>📝 サイト更新履歴の管理</summary>
            <div style="padding:15px;">
                <form method="post" style="margin-bottom:20px; border-bottom:1px dashed #555; padding-bottom:15px;">
                    <h5 style="margin-top:0; color:#ccc;">新規追加</h5>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:start;">
                        <div>
                            <label style="font-size:0.8em; color:#aaa; display:block;">日付</label>
                            <input type="date" name="update_date" value="<?= date('Y-m-d') ?>" class="form-control-glass">
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <label style="font-size:0.8em; color:#aaa; display:block;">更新内容 (複数行可)</label>
                            <textarea name="update_content" class="form-control-glass" rows="2" style="width:100%;" placeholder="例：機能を追加しました" required></textarea>
                        </div>
                        <button type="submit" name="add_update_history" class="btn-primary" style="margin-top:20px;">追加</button>
                    </div>
                </form>

                <h5 style="margin-top:0; color:#ccc;">履歴一覧 (最新50件)</h5>
                <div style="max-height:400px; overflow-y:auto;">
                    <table style="width:100%; border-collapse:collapse; color:#ddd; font-size:0.9em;">
                        <?php if(empty($site_updates)): ?>
                            <tr><td colspan="3" style="text-align:center; padding:10px;">履歴はありません</td></tr>
                        <?php else: ?>
                            <?php foreach($site_updates as $upd): ?>
                                <tr style="border-bottom:1px solid #444;">
                                    <td style="padding:8px; width:120px; vertical-align:top;"><?= h($upd['update_date']) ?></td>
                                    <td style="padding:8px; vertical-align:top;">
                                        <div style="margin-bottom:4px; line-height:1.5;"><?= nl2br(h($upd['content'])) ?></div>
                                        <details style="font-size:0.85em; color:#aaa;">
                                            <summary>修正する</summary>
                                            <form method="post" style="margin-top:5px; display:flex; gap:10px; align-items:start; flex-wrap:wrap;">
                                                <input type="hidden" name="edit_id" value="<?= $upd['id'] ?>">
                                                <input type="date" name="edit_date" value="<?= h($upd['update_date']) ?>" class="form-control-glass" style="padding:2px; font-size:1em; width:110px;">
                                                <textarea name="edit_content" class="form-control-glass" rows="3" style="flex:1; min-width:200px;"><?= h($upd['content']) ?></textarea>
                                                <button type="submit" name="edit_update_history" class="btn-primary" style="padding:4px 8px; font-size:0.9em;">保存</button>
                                            </form>
                                        </details>
                                    </td>
                                    <td style="padding:8px; text-align:right; width:60px; vertical-align:top;">
                                        <form method="post" onsubmit="return confirm('削除しますか？');">
                                            <input type="hidden" name="del_id" value="<?= $upd['id'] ?>">
                                            <button type="submit" name="delete_update_history" class="btn-danger" style="padding:2px 6px; font-size:0.8em;">削除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </details>

        <details class="card" id="sec_inquiry" <?= $act_inquiry ? 'open' : '' ?>>
            <summary>❔ 問い合わせ・要望板管理</summary>
            <div style="padding:15px;">
                <?php if (empty($requests)): ?>
                    <p>現在、投稿はありません。</p>
                <?php else: ?>
                    <?php foreach($requests as $req): ?>
                        <?php 
                            $st_label = h($req['admin_status']);
                            $st_css = 'st-yet';
                            if ($req['admin_status'] === '対応しました') { $st_label = '対応済'; $st_css = 'st-done'; }
                            if ($req['admin_status'] === '対応しません') { $st_label = '非対応'; $st_css = 'st-wont'; }
                            if ($req['admin_status'] === '未対応')       { $st_label = '未対応'; $st_css = 'st-yet'; }
                            
                            $created_ts = strtotime($req['created_at'] . ' UTC');
                            $created_str = date('Y/m/d H:i', $created_ts);
                        ?>
                        <div class="req-card">
                            <div class="req-header">
                                <div>
                                    <span style="color:#aaa;">ID:<?= $req['id'] ?></span>
                                    <span style="margin:0 10px; color:#555;">|</span>
                                    <?= $created_str ?>
                                </div>
                                <div class="status-badge <?= $st_css ?>"><?= $st_label ?></div>
                            </div>
                            
                            <div class="req-body">
                                <div style="margin-bottom:10px;">
                                    <span style="color:var(--accent); font-weight:bold;">【<?= h($req['user_type']) ?>】</span> 
                                    <span class="badge-genre"><?= h($req['genre']) ?></span>
                                    <?php if($req['is_private']): ?><span class="badge-private">🔒秘匿</span><?php endif; ?>
                                </div>
                                <div style="white-space:pre-wrap; line-height:1.6; color:#fff;"><?= h($req['content']) ?></div>
                            </div>

                            <div class="req-footer">
                                <form method="post">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    
                                    <div class="radio-group" style="margin-bottom:10px;">
                                        <span style="color:#aaa; font-size:0.8em; margin-right:10px;">ステータス変更:</span>
                                        <label><input type="radio" name="admin_status" value="対応しました" <?= $req['admin_status']=='対応しました'?'checked':'' ?>> 対応済</label>
                                        <label><input type="radio" name="admin_status" value="対応しません" <?= $req['admin_status']=='対応しません'?'checked':'' ?>> 非対応</label>
                                        <label><input type="radio" name="admin_status" value="未対応" <?= $req['admin_status']=='未対応'?'checked':'' ?>> 未対応</label>
                                    </div>
                                    
                                    <div style="display:flex; gap:10px; align-items:flex-start;">
                                        <div style="flex:1;">
                                            <textarea name="admin_reply" class="admin-textarea" rows="2" placeholder="返信コメントを入力..."><?= h($req['admin_reply']) ?></textarea>
                                        </div>
                                        <div style="display:flex; flex-direction:column; gap:5px;">
                                            <button type="submit" name="update_inquiry" class="btn-primary" style="width:60px;">更新</button>
                                            <button type="submit" name="delete_inquiry" class="btn-danger" style="width:60px;" onclick="return confirm('本当に削除しますか？');">削除</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </details>

        <details class="card" id="sec_site" <?= $act_site ? 'open' : '' ?>>
            <summary>🛠 サイト全体設定 (上限数・削除期間など)</summary>
            <form method="post" style="padding:15px;">
                <p style="font-size:0.8em; color:#aaa; margin-bottom:15px;">※ここでの変更は、すべてのジャンル(Blue, Red, etc)に一括適用されます。</p>
                <div class="config-row">
                    <label>最大スレッド数 (1ジャンルあたり)</label>
                    <div><input type="number" name="conf_max_threads" value="<?= h($conf_max_threads) ?>" required> 件</div>
                </div>
                <div class="config-row">
                    <label>自動削除までの日数 (最終書き込みから)</label>
                    <div><input type="number" name="conf_del_days" value="<?= h($conf_del_days) ?>" required> 日</div>
                </div>
                <div class="config-row">
                    <label>作成速度制限 (1時間あたりの最大作成数)</label>
                    <div><input type="number" name="conf_rate_limit" value="<?= h($conf_rate_limit) ?>" required> 件</div>
                </div>
                <div style="text-align:right; margin-top:15px;">
                    <button type="submit" name="update_site_config" class="btn-glass">設定を保存</button>
                </div>
            </form>
        </details>

        <details class="card">
            <summary>📊 アクセス解析 (Google Analytics / Looker Studio)</summary>
            <div style="padding:15px;">
                <p style="font-size:0.8em; color:#aaa; margin-bottom:10px;">
                    ※ブラウザでログイン中のGoogleアカウントが権限を持っている場合のみ表示されます。
                </p>
                <div class="looker-studio-container">
                    <iframe width="600" height="443" src="https://lookerstudio.google.com/embed/reporting/9a213a86-1052-447e-8656-0e4a03a1e2fd/page/0VbkF" frameborder="0" style="border:0" allowfullscreen sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
                </div>
            </div>
        </details>

    <?php endif; ?>
    
    <div class="footer-link">(c) mogd.</div>
</div>

<script>
    // PHPから渡されたターゲットIDがあればスクロール
    const scrollTarget = "<?= $scroll_target ?>";
    if (scrollTarget) {
        const element = document.getElementById(scrollTarget);
        if (element) {
            // 少し遅延させるとdetailsの展開アニメーションと衝突しにくい
            setTimeout(() => {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
</script>

</body>
</html>