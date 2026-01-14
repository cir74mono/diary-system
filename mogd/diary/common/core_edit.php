<?php
require __DIR__ . '/db.php';
require_site_login();

date_default_timezone_set('Asia/Tokyo');

$type = $_GET['type'] ?? ''; 
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$target_thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$target_post_num  = isset($_GET['post_num']) ? (int)$_GET['post_num'] : 0;

$error = '';
$msg = ''; 
$edit_mode = false;
$data = [];

$is_manage_active = false;

if ($type === 'thread') {
    $stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
} elseif ($type === 'post') {
    if ($target_thread_id && $target_post_num) {
        $sql = "SELECT p.*, t.custom_css, t.title as thread_title, t.view_pass 
                FROM posts p 
                JOIN threads t ON p.thread_id = t.id 
                WHERE p.thread_id = ? AND p.post_num = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_thread_id, $target_post_num]);
        $data = $stmt->fetch();
    } elseif ($id) {
        $sql = "SELECT p.*, t.custom_css, t.title as thread_title, t.view_pass 
                FROM posts p 
                JOIN threads t ON p.thread_id = t.id 
                WHERE p.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $data = $stmt->fetch();
    }
}

if (!$data) exit('<div class="container"><p>データが見つかりません。<a href="index.php">戻る</a></p></div>');

$id = $data['id'];

// ロック確認
$check_view_pass = ($type === 'thread') ? ($data['view_pass'] ?? '') : ($data['view_pass'] ?? '');
$check_thread_id = ($type === 'thread') ? $data['id'] : $data['thread_id'];

if (!empty($check_view_pass)) {
    $site_unique_id = md5(dirname($_SERVER['SCRIPT_FILENAME']));
    $session_key = 'diary_lock_' . $site_unique_id . '_thread_' . $check_thread_id;
    
    if (empty($_SESSION[$session_key]) || $_SESSION[$session_key] !== true) {
        require 'header.php';
        ?>
        <div class="container">
            <div class="card" style="text-align:center; padding:40px 20px;">
                <h3 style="color:#ff6b6b; border:none;">この日記は鍵がかかっています</h3>
                <p style="margin-bottom:20px;">編集するには、まず日記ページでパスワードを解除してください。</p>
                <a href="thread.php?id=<?= $check_thread_id ?>" class="btn">日記ページへ移動</a>
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

if ($type === 'thread') {
    $page_title = '設定 : ' . $data['title'] . ' | ' . $genre_name . ' - DIARY';
} else {
    $t_title = isset($data['thread_title']) ? $data['thread_title'] : 'Unknown';
    $page_title = '編集 : ' . $t_title . ' | ' . $genre_name . ' - DIARY';
}

$delete_confirm_js = "";
if ($type === 'thread') {
    $delete_confirm_js = json_encode("日記「" . $data['title'] . "」を削除します。\n本当によろしいですか？\n※この日記に含まれる全ての記事も削除されます。");
} else {
    $delete_confirm_js = json_encode("記事 No." . ($data['post_num'] ?? '?') . " (" . $data['name'] . ") を削除します。\n本当によろしいですか？");
}

$target_res = null; 
$search_res_num = $_POST['search_res_num'] ?? ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_pass = $_POST['auth_pass'] ?? $_POST['hidden_pass'] ?? '';
    if (password_verify($input_pass, $data['del_pass'])) {
        $edit_mode = true;
    } else {
        $error = 'パスワードが違います。';
    }
    
    if ($edit_mode) {
        if (isset($_POST['mode']) && $_POST['mode'] === 'search_res') {
            $is_manage_active = true; // ★管理操作フラグON
        }
        elseif (isset($_POST['mode']) && $_POST['mode'] === 'manage_res_action') {
            $is_manage_active = true; // ★管理操作フラグON
            $target_res_id = $_POST['target_res_id'];
            $action = $_POST['manage_action'];
            
            $chk = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND thread_id = ?");
            $chk->execute([$target_res_id, $id]);
            $res_data = $chk->fetch();

            if ($res_data) {
                if ($action === 'update_content') {
                    $new_name = trim($_POST['res_name']);
                    $new_body = trim($_POST['res_content']);
                    
                    if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $new_body)) {
                        $error = "画像ファイルのリンクや投稿は禁止されています。";
                    } elseif (preg_match('/https?:\/\/(?!([\w-]+\.)*cirmg\.com)/i', $new_body)) {
                        $error = '外部サイトへのリンクは禁止されています。(cirmg.com内のみ可)';
                    } else {
                        $pdo->prepare("UPDATE posts SET name = ?, content = ? WHERE id = ?")->execute([$new_name, $new_body, $target_res_id]);
                        $msg = "記事No.{$res_data['post_num']} の内容を修正しました。";
                        $search_res_num = $res_data['post_num'];
                    }
                }
                elseif ($action === 'change_pass') {
                    $new_p = $_POST['new_res_pass'] ?? '';
                    if ($new_p !== '') {
                        $hash = password_hash($new_p, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE posts SET del_pass = ? WHERE id = ?")->execute([$hash, $target_res_id]);
                        $msg = "記事No.{$res_data['post_num']} のパスワードを変更しました。";
                        $search_res_num = $res_data['post_num'];
                    } else {
                        $error = "新しいパスワードが入力されていません。";
                    }
                }
                elseif ($action === 'delete') {
                    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$target_res_id]);
                    $msg = "記事No.{$res_data['post_num']} を削除しました。";
                    $search_res_num = '';
                }
            } else {
                $error = "対象の記事が見つかりません。";
            }
        }
        elseif (isset($_POST['mode']) && $_POST['mode'] === 'delete') {
            if ($type === 'thread') {
                $pdo->prepare("DELETE FROM posts WHERE thread_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM threads WHERE id = ?")->execute([$id]);
                echo "<script>location.href='index.php';</script>"; exit;
            } elseif ($type === 'post') {
                $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
                echo "<script>location.href='thread.php?id=" . $data['thread_id'] . "';</script>"; exit;
            }
        }
        elseif (isset($_POST['mode']) && $_POST['mode'] === 'update') {
            if ($type === 'thread') {
                $check_css = $_POST['custom_css'] ?? '';
                if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $check_css)) {
                    $error = "CSS内であっても、画像ファイルの使用は禁止されています。";
                } else {
                    $lock_level = isset($_POST['lock_level']) ? (int)$_POST['lock_level'] : 0;
                    
                    $p_title = trim($_POST['title']);
                    $p_cname = trim($_POST['creator_name']);
                    $p_desc  = trim($_POST['description']);
                    $p_css   = $_POST['custom_css'];
                    
                    $sql = "UPDATE threads SET title=?, creator_name=?, description=?, custom_css=?, lock_level=? WHERE id=?";
                    $pdo->prepare($sql)->execute([$p_title, $p_cname, $p_desc, $p_css, $lock_level, $id]);

                    if (!empty($_POST['new_del_pass'])) {
                        $pdo->prepare("UPDATE threads SET del_pass=? WHERE id=?")->execute([password_hash($_POST['new_del_pass'], PASSWORD_DEFAULT), $id]);
                    }
                    if (!empty($_POST['new_write_pass'])) {
                        $pdo->prepare("UPDATE threads SET write_pass=? WHERE id=?")->execute([password_hash($_POST['new_write_pass'], PASSWORD_DEFAULT), $id]);
                    }
                    if (isset($_POST['remove_view_pass'])) {
                        $pdo->prepare("UPDATE threads SET view_pass=NULL WHERE id=?")->execute([$id]);
                    } elseif (!empty($_POST['new_view_pass'])) {
                        $pdo->prepare("UPDATE threads SET view_pass=? WHERE id=?")->execute([password_hash($_POST['new_view_pass'], PASSWORD_DEFAULT), $id]);
                    }
                    $msg = "設定を保存しました。";
                    $stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
                    $stmt->execute([$id]);
                    $data = $stmt->fetch();
                }

            } elseif ($type === 'post') {
                $check_content = $_POST['content'] ?? '';
                if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $check_content)) {
                    $error = "画像ファイルのリンクや投稿は禁止されています。";
                } elseif (preg_match('/https?:\/\/(?!([\w-]+\.)*cirmg\.com)/i', $check_content)) {
                    $error = '外部サイトへのリンクは禁止されています。(cirmg.com内のみ可)';
                } else {
                    $p_name = trim($_POST['name']);
                    $p_content = trim($_POST['content']);
                    
                    $sql = "UPDATE posts SET name=?, content=? WHERE id=?";
                    $pdo->prepare($sql)->execute([$p_name, $p_content, $id]);
                    
                    if (!empty($_POST['new_del_pass'])) {
                        $pdo->prepare("UPDATE posts SET del_pass=? WHERE id=?")->execute([password_hash($_POST['new_del_pass'], PASSWORD_DEFAULT), $id]);
                    }
                    echo "<script>location.href='thread.php?id=" . $data['thread_id'] . "';</script>"; exit;
                }
            }
        }
        
        if ($type === 'thread' && $search_res_num !== '') {
            $stmt_r = $pdo->prepare("SELECT * FROM posts WHERE thread_id = ? AND post_num = ?");
            $stmt_r->execute([$id, $search_res_num]);
            $target_res = $stmt_r->fetch();
            if (!$target_res && empty($msg)) $error = "記事No.{$search_res_num} は存在しません。";
            
            // ヒットした場合もフラグを維持
            if ($target_res) $is_manage_active = true;
        }
    }
}

require 'header.php';
?>

<style>
    details {
        margin-bottom: 20px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--card-bg);
        overflow: hidden;
    }
    summary {
        padding: 12px 15px;
        cursor: pointer;
        font-weight: bold;
        background-color: rgba(0,0,0,0.03);
        user-select: none;
        outline: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    summary:hover {
        background-color: rgba(0,0,0,0.08);
    }
    summary::after {
        content: '+';
        font-weight: bold;
        font-size: 1.2em;
    }
    details[open] summary::after {
        content: '-';
    }
    summary { list-style: none; }
    summary::-webkit-details-marker { display: none; }

    .details-content {
        padding: 20px;
        border-top: 1px solid var(--border);
    }
</style>

<div class="container">
    <h1>編集・削除</h1>
    
    <div class="text-center-mb">
        <p style="color:var(--text-sub);">
            <?= ($type === 'thread') ? '日記設定の変更: ' . h($data['title']) : '記事No.' . h($data['post_num']) . ' の編集' ?>
        </p>
    </div>

    <?php if($msg): ?>
        <div class="msg-box msg-success">
            <p style="margin: 0;"><?= h($msg) ?></p>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="msg-box msg-error">
            <p style="margin: 0;"><?= h($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$edit_mode): ?>
        <div class="card text-center-mb">
            <p style="margin-bottom:20px;">操作を行うには<b>編集/削除用パスワード</b>を入力してください。</p>
            <form method="post">
                <div style="margin-bottom:20px; max-width:300px; margin-left:auto; margin-right:auto;">
                    <input type="password" name="auth_pass" required placeholder="">
                </div>
                <button type="submit" class="btn">認証する</button>
            </form>
        </div>

    <?php else: ?>
        <div style="max-width: 800px; margin: 0 auto;">
            <form method="post" id="main_form">
                <input type="hidden" name="mode" value="update">
                <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                <input type="hidden" id="edit_thread_css" value="<?= h($data['custom_css'] ?? '') ?>">

                <?php if ($type === 'thread'): ?>
                    
                    <details open>
                        <summary>基本情報 (タイトル・概要)</summary>
                        <div class="details-content">
                            <div class="form-row">
                                <label>タイトル</label>
                                <input type="text" name="title" value="<?= h($data['title']) ?>" required>
                            </div>
                            <div class="form-row">
                                <label>作成者名</label>
                                <input type="text" name="creator_name" value="<?= h($data['creator_name']) ?>" required>
                            </div>
                            <div class="form-row">
                                <label>概要</label>
                                <textarea id="descInput" name="description" rows="5"><?= h($data['description']) ?></textarea>
                                <p style="font-size:0.8em; color:var(--text-sub); margin-top:5px; text-align:right;">※概要は中央寄せになります。</p>
                            </div>
                        </div>
                    </details>

                    <details>
                        <summary>デザイン・プレビュー (CSS)</summary>
                        <div class="details-content">
                            <div class="form-row">
                                <label>専用CSS (URL可・画像禁止)</label>
                                <textarea id="cssInput" name="custom_css" rows="6" style="font-family:monospace;" placeholder="body { background: #000; }"><?= h($data['custom_css'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-section">
                                <label style="margin-bottom:10px; display:block;">サンプルHTML (CSS確認用)</label>
                                <textarea id="sample_html" rows="5" style="font-family:monospace; margin-bottom:10px;"><div style="font-size:1.2em; font-weight:bold;">見出しのテスト</div>
<p>これは本文のテストです。</p>
<span style="color:red;">赤文字のテスト</span></textarea>

                                <button type="button" id="previewBtn" class="btn" style="font-size:0.8em; width:100%; margin-bottom:10px;">▼ CSSを反映してプレビュー</button>
                                <div style="display:flex; gap:10px; margin-bottom:10px; justify-content:center;">
                                    <button type="button" id="btn_html_light" class="btn" style="background:#f0f0f0; color:#333; border:1px solid #ccc; font-size:0.8em; flex:1;">☀ ライト</button>
                                    <button type="button" id="btn_html_dark" class="btn" style="background:#333; color:#fff; border:1px solid #000; font-size:0.8em; flex:1;">🌙 ダーク</button>
                                </div>
                                
                                <div id="html-preview-container" class="preview-host" style="margin-top:15px; display:none;"></div>
                                <p style="font-size:0.8em; color:var(--text-sub); margin-top:5px; text-align:right;">※外部呼出し（@import）とリンクは現在プレビュー上では機能しません。(書き込み後は機能します)</p>
                            </div>
                        </div>
                    </details>
                    
                    <details>
                        <summary>セキュリティ設定 (パスワード・閲覧制限)</summary>
                        <div class="details-content">
                            <div class="form-row">
                                <p style="font-weight:bold; margin-bottom:10px;">パスワード変更 (変更する場合のみ入力)</p>
                                <input type="password" name="new_del_pass" placeholder="新しい編集/削除パスワード" style="margin-bottom:10px;">
                                <input type="password" name="new_write_pass" placeholder="新しい書き込みパスワード" style="margin-bottom:10px;">
                                <input type="password" name="new_view_pass" placeholder="新しい閲覧パスワード">
                            </div>
                            
                            <hr class="dashed-divider">

                            <div class="form-section">
                                <p style="font-weight:bold; margin-bottom:5px;">閲覧制限設定</p>
                                <div style="margin-bottom:10px;">
                                    <label style="margin-right:15px;"><input type="radio" name="lock_level" value="0" <?= ($data['lock_level'] == 0) ? 'checked' : '' ?>> 全て隠す</label>
                                    <label><input type="radio" name="lock_level" value="1" <?= ($data['lock_level'] == 1) ? 'checked' : '' ?>> 概要のみ公開 (表紙表示)</label>
                                </div>
                                <label style="cursor:pointer; color:#ff6b6b; display:flex; align-items:center;">
                                    <input type="checkbox" name="remove_view_pass" value="1" style="width:auto; transform:scale(1.2); margin-right:5px;"> 閲覧パスワードを解除して公開する
                                </label>
                            </div>
                        </div>
                    </details>

                <?php else: ?>
                    <details open>
                        <summary>記事編集</summary>
                        <div class="details-content">
                            <div class="form-row">
                                <label>名前</label>
                                <input type="text" id="post_name" name="name" value="<?= h($data['name']) ?>" required>
                            </div>
                            <div class="form-row">
                                <label>内容 <span style="font-size:0.8em; color:#ff6b6b;">(外部URL/画像 禁止)</span></label>
                                <textarea id="post_content" name="content" rows="10"><?= h($data['content']) ?></textarea>
                            </div>
                        </div>
                    </details>

                    <details>
                        <summary>プレビュー</summary>
                        <div class="details-content">
                            <button type="button" id="btn_post_preview" class="btn" style="width:100%; margin-bottom:10px;">▼ プレビューを表示/更新</button>
                            <div style="display:flex; gap:10px; margin-bottom:15px; justify-content:center;">
                                <button type="button" id="btn_post_light" class="btn" style="background:#f0f0f0; color:#333; border:1px solid #ccc; font-size:0.9em; flex:1;">☀ ライト</button>
                                <button type="button" id="btn_post_dark" class="btn" style="background:#333; color:#fff; border:1px solid #000; font-size:0.9em; flex:1;">🌙 ダーク</button>
                            </div>

                            <div id="post_preview_host" class="preview-host"></div>
                            <p style="font-size:0.8em; color:var(--text-sub); margin-top:5px; text-align:right;">※外部呼出し（@import）とリンクは現在プレビュー上では機能しません。(書き込み後は機能します)</p>
                        </div>
                    </details>

                    <details>
                        <summary>パスワード変更</summary>
                        <div class="details-content">
                            <div class="form-row">
                                <label>編集パスワード変更 (変更する場合のみ)</label>
                                <input type="password" name="new_del_pass">
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </form>

            <div class="flex-end-gap" style="margin-top:30px; margin-bottom:20px; align-items: center; gap: 10px;">
                <?php if ($type === 'thread'): ?>
                    <form action="download.php" method="post" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="auth_pass" value="<?= h($input_pass) ?>">
                        <button type="submit" class="btn" 
                            style="background:transparent; border:1px solid var(--text-sub); color:var(--text-sub); font-size:0.9em; transition:0.3s;"
                            onmouseover="this.style.background='var(--text-sub)'; this.style.color='var(--bg-color)';" 
                            onmouseout="this.style.background='transparent'; this.style.color='var(--text-sub)';"
                        >📥 ログ保存(ZIP)</button>
                    </form>
                <?php endif; ?>

                <form method="post" onsubmit="return confirm(<?= h($delete_confirm_js) ?>);" style="margin:0;">
                    <input type="hidden" name="mode" value="delete">
                    <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                    <button type="submit" class="btn" 
                        style="background:transparent; border:1px solid #ff4f4f; color:#ff4f4f; font-size:0.9em; transition:0.3s;" 
                        onmouseover="this.style.background='#ff4f4f'; this.style.color='#fff';" 
                        onmouseout="this.style.background='transparent'; this.style.color='#ff4f4f';"
                    >🗑 削除</button>
                </form>
                  <button type="submit" form="main_form" class="btn" style="padding: 12px 60px; font-weight:bold; font-size:1.1em;">保存する</button>
            </div>
              
            <hr class="dashed-divider">

            <?php if ($type === 'thread'): ?>
            <details id="manage-area" <?= $is_manage_active ? 'open' : '' ?>>
                <summary>記事管理 (レス検索・削除)</summary>
                <div class="details-content" style="background: rgba(0,0,0,0.05);">
                    <form method="post" style="display:flex; gap:10px; align-items:center; margin-bottom:20px;">
                        <input type="hidden" name="mode" value="search_res">
                        <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                        <label>記事No.</label>
                        <input type="number" name="search_res_num" value="<?= h($search_res_num) ?>" style="width:80px; text-align:center;" required placeholder="No">
                        <button type="submit" class="btn" style="padding: 8px 20px;">表示</button>
                    </form>

                    <?php if ($target_res): ?>
                        <div style="background: var(--input-bg); border: 1px solid var(--border); padding: 20px; border-radius: 8px; margin-top:15px;">
                        <?php
                           $ts = strtotime($target_res['created_at']);
                            $date_str = date('Y/m/d H:i', $ts);
                        ?>
                            <div class="dashed-bottom" style="margin-bottom:15px;">
                                <b>No.<?= $target_res['post_num'] ?> (<?= $date_str ?>)</b> の編集
                            </div>

                            <form method="post">
                                <input type="hidden" name="mode" value="manage_res_action">
                                <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                                <input type="hidden" name="target_res_id" value="<?= $target_res['id'] ?>">
                                <input type="hidden" name="search_res_num" value="<?= $target_res['post_num'] ?>">

                                <div class="form-row">
                                    <label style="font-size:0.8em;">名前</label>
                                    <input type="text" id="manage_res_name" name="res_name" value="<?= h($target_res['name']) ?>" required>
                                </div>
                                <div class="form-row">
                                    <label style="font-size:0.8em;">本文 (外部URL/画像 禁止)</label>
                                    <textarea id="manage_res_content" name="res_content" rows="5" required><?= h($target_res['content']) ?></textarea>
                                </div>

                                <div class="preview-area" style="padding:10px; margin-top:15px;">
                                    <button type="button" id="btn_manage_preview" class="btn" style="width:100%; font-size:0.9em; margin-bottom:10px;">▼ 変更後のプレビュー (CSS適用)</button>
                                    <div style="display:flex; gap:10px; margin-bottom:10px; justify-content:center;">
                                        <button type="button" id="btn_manage_light" class="btn" style="background:#f0f0f0; color:#333; border:1px solid #ccc; font-size:0.9em; flex:1;">☀ ライト</button>
                                        <button type="button" id="btn_manage_dark" class="btn" style="background:#333; color:#fff; border:1px solid #000; font-size:0.9em; flex:1;">🌙 ダーク</button>
                                    </div>
                                    <div id="manage_preview_host" class="preview-host"></div>
                                <p style="font-size:0.8em; color:var(--text-sub); margin-top:5px; text-align:right;">※外部呼出し（@import）とリンクは現在プレビュー上では機能しません。(書き込み後は機能します)</p>
                                </div>

                                <div class="text-center-mb" style="margin-top:20px;">
                                    <button type="submit" name="manage_action" value="update_content" class="btn" style="background:var(--accent); color:var(--btn-hover-text); font-size:0.9em; font-weight:bold;">内容を修正保存</button>
                                </div>

                                <hr class="dashed-divider">

                                <div class="flex-end-gap" style="align-items:flex-end;">
                                    <div style="flex:1;">
                                        <label style="font-size:0.8em;">新しい編集/削除用パスワード</label>
                                        <input type="text" name="new_res_pass" placeholder="変更する場合のみ入力" style="padding: 6px; font-size: 0.85em;">
                                    </div>
                                    <button type="submit" name="manage_action" value="change_pass" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.85em;">PW変更</button>
                                    
                                    <button type="submit" name="manage_action" value="delete" class="btn" 
                                        style="padding: 6px 12px; background:transparent; border:1px solid #ff4f4f; color:#ff4f4f; font-size:0.85em; margin-left:auto; transition:0.3s;" 
                                        onmouseover="this.style.background='#ff4f4f'; this.style.color='#fff';" 
                                        onmouseout="this.style.background='transparent'; this.style.color='#ff4f4f';"
                                        onclick="return confirm('本当に削除しますか？\n取り消しはできません。');">
                                        この記事を削除
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
            <?php endif; ?>
        </div>
        
        <script>
            const themeConfig = {
                light: { bg: '#f9f9f9', text: '#333333', link: '#84b9cb', attr: 'light' },
                dark:  { bg: '#202020', text: '#e0e0e0', link: '#ffdb4f', attr: '' }
            };

            function updatePreviewIframe(hostId, contentHtml, cssVal, mode) {
                const host = document.getElementById(hostId);
                if (!host) return;

                let currentTheme = themeConfig.dark;
                
                if (mode === 'light') {
                    currentTheme = themeConfig.light;
                } else if (mode === 'current') {
                    const parentAttr = document.documentElement.getAttribute('data-theme');
                    if (parentAttr === 'light') currentTheme = themeConfig.light;
                }

                const htmlAttr = currentTheme.attr ? `data-theme="${currentTheme.attr}"` : '';

                const docContent = `
                    <!DOCTYPE html>
                    <html ${htmlAttr}>
                    <head>
                        <meta charset="UTF-8">
                        <link rel="stylesheet" href="../css/style.css">
                        <link rel="stylesheet" href="common/css/style.css">
                        <link rel="stylesheet" href="css/style.css">
                        <style>
                            body {
                                margin: 0;
                                padding: 15px;
                                word-wrap: break-word;
                                white-space: pre-wrap;
                                transition: background 0.3s, color 0.3s;
                                background-color: ${currentTheme.bg} !important;
                                color: ${currentTheme.text} !important;
                            }
                            a {
                                color: ${currentTheme.link} !important;
                                pointer-events: none; /* リンク無効化 */
                                cursor: default;
                            }
                            ${cssVal}
                        </style>
                    </head>
                    <body>
                        ${contentHtml}
                    </body>
                    </html>
                `;

                const iframe = document.createElement('iframe');
                iframe.style.width = '100%';
                iframe.style.border = 'none';
                
                host.innerHTML = '';
                host.style.display = 'block';
                host.appendChild(iframe);
                
                const iDoc = iframe.contentWindow.document;
                iDoc.open();
                iDoc.write(docContent);
                iDoc.close();

                iframe.onload = function() {
                    const h = iframe.contentWindow.document.documentElement.scrollHeight;
                    iframe.style.height = (h + 20) + 'px';
                };
            }

            // プレビュー用のリンク変換関数 (HTMLエスケープはしない)
            function formatPreviewText(text) {
                // [dir>>>id] -> <a href="#">dir>>>id</a>
                text = text.replace(/\[([a-zA-Z0-9_-]+)>>>(\d+)\]/g, '<a href="#">$1>>>$2</a>');
                // >>>id, >>id -> <a href="#">...</a>
                text = text.replace(/>>>(\d+)/g, '<a href="#">>>>$1</a>');
                text = text.replace(/>>(\d+)/g, '<a href="#">>>$1</a>');
                return text;
            }

            // CSS確認用（サンプルHTML）プレビュー
            function runHtmlPreview(mode) {
                const content = document.getElementById('sample_html').value;
                const css = document.getElementById('cssInput').value;
                
                let htmlContent = formatPreviewText(content);
                // HTMLタグは有効のまま
                updatePreviewIframe('html-preview-container', htmlContent, css, mode);
            }
            const previewBtn = document.getElementById('previewBtn');
            if (previewBtn) {
                previewBtn.addEventListener('click', () => runHtmlPreview('current'));
                document.getElementById('btn_html_light').addEventListener('click', () => runHtmlPreview('light'));
                document.getElementById('btn_html_dark').addEventListener('click', () => runHtmlPreview('dark'));
            }

            // 記事投稿用プレビュー
            function runPostPreview(mode) {
                const content = document.getElementById('post_content').value;
                const css = document.getElementById('edit_thread_css').value;
                
                let htmlContent = formatPreviewText(content);
                htmlContent = `<div style="line-height: 1.8; letter-spacing: 0.05em;">${htmlContent}</div>`;
                
                updatePreviewIframe('post_preview_host', htmlContent, css, mode);
            }
            const btnPostPreview = document.getElementById('btn_post_preview');
            if (btnPostPreview) {
                btnPostPreview.addEventListener('click', () => runPostPreview('current'));
                document.getElementById('btn_post_light').addEventListener('click', () => runPostPreview('light'));
                document.getElementById('btn_post_dark').addEventListener('click', () => runPostPreview('dark'));
            }

            // 管理用レス編集プレビュー
            function runManagePreview(mode) {
                const content = document.getElementById('manage_res_content').value;
                const css = document.getElementById('edit_thread_css').value;
                
                let htmlContent = formatPreviewText(content);
                htmlContent = `<div style="line-height: 1.8; letter-spacing: 0.05em;">${htmlContent}</div>`;
                
                updatePreviewIframe('manage_preview_host', htmlContent, css, mode);
            }
            const btnManagePreview = document.getElementById('btn_manage_preview');
            if (btnManagePreview) {
                btnManagePreview.addEventListener('click', () => runManagePreview('current'));
                document.getElementById('btn_manage_light').addEventListener('click', () => runManagePreview('light'));
                document.getElementById('btn_manage_dark').addEventListener('click', () => runManagePreview('dark'));
            }
        </script>
        
        <?php if ($is_manage_active): ?>
        <script>
            // ページ読み込み後に管理エリアへスクロール
            document.addEventListener("DOMContentLoaded", function() {
                const element = document.getElementById("manage-area");
                if (element) {
                    element.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            });
        </script>
        <?php endif; ?>
    <?php endif; ?>

    <div class="footer-link">
        <?php 
            $back = ($type === 'thread') ? "thread.php?id=$id" : "thread.php?id=" . ($data['thread_id'] ?? 0);
        ?>
        <a href="<?= $back ?>">&laquo; 戻る</a><?php require 'footer.php'; ?>
    </div>
</div>
</body>
</html>