<?php
require 'db.php';
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

// --- データの取得 ---
if ($type === 'thread') {
    $stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
} elseif ($type === 'post') {
    if ($target_thread_id && $target_post_num) {
        $sql = "SELECT p.*, t.custom_css, t.title as thread_title 
                FROM posts p 
                JOIN threads t ON p.thread_id = t.id 
                WHERE p.thread_id = ? AND p.post_num = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$target_thread_id, $target_post_num]);
        $data = $stmt->fetch();
    } elseif ($id) {
        $sql = "SELECT p.*, t.custom_css, t.title as thread_title 
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

$genre_name = 'DIARY';
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(__DIR__);
    if (isset($genres[$current_dir])) {
        $genre_name = $genres[$current_dir];
    }
}

if ($type === 'thread') {
    $page_title = '設定 : ' . $data['title'] . ' | ' . $genre_name . ' - DIARY';
} else {
    // 記事編集時
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

// --- POST処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input_pass = $_POST['auth_pass'] ?? $_POST['hidden_pass'] ?? '';
    if (password_verify($input_pass, $data['del_pass'])) {
        $edit_mode = true;
    } else {
        $error = 'パスワードが違います。';
    }
    
    if ($edit_mode) {

        if (isset($_POST['mode']) && $_POST['mode'] === 'search_res') {
            // 表示のみ
        }
        // --- B. 記事管理アクション (管理者権限) ---
        elseif (isset($_POST['mode']) && $_POST['mode'] === 'manage_res_action') {
            $target_res_id = $_POST['target_res_id'];
            $action = $_POST['manage_action'];
            
            $chk = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND thread_id = ?");
            $chk->execute([$target_res_id, $id]);
            $res_data = $chk->fetch();

            if ($res_data) {
                if ($action === 'update_content') {
                    $new_name = $_POST['res_name'];
                    $new_body = $_POST['res_content'];
                    
                    // --- バリデーション ---
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
        // --- C. 通常の更新・削除 ---
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
                // --- CSSバリデーション ---
                $check_css = $_POST['custom_css'] ?? '';
                if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $check_css)) {
                    $error = "CSS内であっても、画像ファイルの使用は禁止されています。";
                } else {
                    $lock_level = isset($_POST['lock_level']) ? (int)$_POST['lock_level'] : 0;
                    $sql = "UPDATE threads SET title=?, creator_name=?, description=?, custom_css=?, lock_level=? WHERE id=?";
                    $pdo->prepare($sql)->execute([$_POST['title'], $_POST['creator_name'], $_POST['description'], $_POST['custom_css'], $lock_level, $id]);

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
                // --- 記事修正バリデーション ---
                $check_content = $_POST['content'] ?? '';
                if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $check_content)) {
                    $error = "画像ファイルのリンクや投稿は禁止されています。";
                } elseif (preg_match('/https?:\/\/(?!([\w-]+\.)*cirmg\.com)/i', $check_content)) {
                    $error = '外部サイトへのリンクは禁止されています。(cirmg.com内のみ可)';
                } else {
                    $sql = "UPDATE posts SET name=?, content=? WHERE id=?";
                    $pdo->prepare($sql)->execute([$_POST['name'], $_POST['content'], $id]);
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
        }
    }
}

require 'header.php';
?>

<div class="container">
    <h1>編集・削除</h1>
    
    <div class="text-center mb-4">
        <p style="color:#a0a0a0;">
            <?= ($type === 'thread') ? '日記設定の変更: ' . h($data['title']) : '記事No.' . h($data['post_num']) . ' の編集' ?>
        </p>
    </div>

    <?php if($msg): ?>
        <div class="card" style="border-color: #28a745; background: rgba(40,167,69,0.1); text-align:center;">
            <p style="color: #28a745; margin: 0; font-weight: bold;"><?= h($msg) ?></p>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="card" style="border-color: #ff6b6b; background: rgba(255,107,107,0.1); text-align:center;">
            <p style="color: #ff6b6b; margin: 0; font-weight: bold;"><?= h($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$edit_mode): ?>
        <div class="card text-center">
            <p style="margin-bottom:20px;">操作を行うには<b>編集/削除用パスワード</b>を入力してください。</p>
            <form method="post">
                <div style="margin-bottom:20px; max-width:300px; margin-left:auto; margin-right:auto;">
                    <input type="password" name="auth_pass" required placeholder="">
                </div>
                <button type="submit" class="btn">認証する</button>
            </form>
        </div>

    <?php else: ?>
        <div class="card">
            <form method="post">
                <input type="hidden" name="mode" value="update">
                <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                <input type="hidden" id="edit_thread_css" value="<?= h($data['custom_css'] ?? '') ?>">

                <?php if ($type === 'thread'): ?>
                    <h3 style="text-align:center;">日記設定</h3>
                    <div style="margin-bottom:15px;">
                        <label>タイトル</label>
                        <input type="text" name="title" value="<?= h($data['title']) ?>" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label>作成者名</label>
                        <input type="text" name="creator_name" value="<?= h($data['creator_name']) ?>" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label>概要</label>
                        <textarea name="description" rows="5"><?= h($data['description']) ?></textarea>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label>専用CSS (URL可・画像禁止)</label>
                        <textarea id="cssInput" name="custom_css" rows="6" style="font-family:monospace;" placeholder="body { background: #000; }"><?= h($data['custom_css'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="margin-bottom:15px; border:1px solid #444; padding:15px; background:rgba(0,0,0,0.2); border-radius:4px;">
                        <label style="margin-bottom:5px; display:block;">サンプルHTML (CSS確認用)</label>
                        <textarea id="sampleHtmlInput" rows="5" style="font-family:monospace; margin-bottom:10px;">
<div style="font-size:1.2em; font-weight:bold;">見出しのテスト</div>
<p>これは本文のテストです。<a href="#">リンクのテスト</a></p>
<span style="color:red;">赤文字のテスト</span></textarea>
                        <button type="button" id="previewBtn" class="btn" style="background:#666; font-size:0.8em; width:100%;">CSSを反映してプレビュー</button>
                        <div id="html-preview-container" class="card" style="margin-top:15px; min-height:100px; display:none; border:1px dashed #888; padding:0; overflow:hidden;"></div>
                    </div>
                    
                    <hr style="border:0; border-top:1px dashed #444; margin:30px 0;">
                    <div style="margin-bottom:15px;">
                        <p style="font-weight:bold; margin-bottom:10px;">パスワード変更 (変更する場合のみ入力)</p>
                        <input type="password" name="new_del_pass" placeholder="新しい編集/削除パスワード" style="margin-bottom:10px;">
                        <input type="password" name="new_write_pass" placeholder="新しい書き込みパスワード" style="margin-bottom:10px;">
                        <input type="password" name="new_view_pass" placeholder="新しい閲覧パスワード">
                    </div>
                    <div style="margin-bottom:15px; background:rgba(0,0,0,0.2); padding:10px; border-radius:4px;">
                        <p style="font-weight:bold; margin-bottom:5px;">閲覧制限設定</p>
                        <div style="margin-bottom:10px;">
                            <label style="margin-right:15px;"><input type="radio" name="lock_level" value="0" <?= ($data['lock_level'] == 0) ? 'checked' : '' ?>> 全て隠す</label>
                            <label><input type="radio" name="lock_level" value="1" <?= ($data['lock_level'] == 1) ? 'checked' : '' ?>> 概要のみ公開 (表紙表示)</label>
                        </div>
                        <label style="cursor:pointer; color:#ff6b6b; display:flex; align-items:center;">
                            <input type="checkbox" name="remove_view_pass" value="1" style="width:auto; transform:scale(1.2); margin-right:5px;"> 閲覧パスワードを解除して公開する
                        </label>
                    </div>

                <?php else: ?>
                    <h3 style="text-align:center;">記事編集</h3>
                    <div style="margin-bottom:15px;">
                        <label>名前</label>
                        <input type="text" id="post_name" name="name" value="<?= h($data['name']) ?>" required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label>内容 <span style="font-size:0.8em; color:#ff6b6b;">(外部URL/画像 禁止)</span></label>
                        <textarea id="post_content" name="content" rows="10"><?= h($data['content']) ?></textarea>
                    </div>

                    <div style="margin-bottom: 25px; border:1px solid #555; padding:15px; border-radius:4px; background:rgba(0,0,0,0.3);">
                        <label style="margin-bottom:10px; display:block;">記事プレビュー (CSS適用)</label>
                        <button type="button" id="btn_post_preview" class="btn" style="background:#555; width:100%; margin-bottom:15px;">▼ プレビューを表示/更新</button>
                        <div id="post_preview_host" style="border:1px dashed #666; min-height:50px; background:#fff; color:#000; padding:0; overflow:hidden;"></div>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label>編集パスワード変更 (変更する場合のみ)</label>
                        <input type="password" name="new_del_pass">
                    </div>
                <?php endif; ?>

                <div style="text-align:center; margin-top:30px;">
                    <button type="submit" class="btn" style="padding: 10px 40px;">保存する</button>
                </div>
            </form>

            <hr style="border:0; border-top:1px dashed #444; margin:40px 0 20px;">

            <?php if ($type === 'thread'): ?>
            <div style="margin-bottom:40px; padding:15px; border:1px solid #555; background:rgba(255,255,255,0.05); border-radius:4px;">
                <h3 style="margin-top:0;">記事管理 (管理者用)</h3>
                
                <form method="post" style="display:flex; gap:10px; align-items:center; margin-bottom:20px;">
                    <input type="hidden" name="mode" value="search_res">
                    <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                    <label>記事No.</label>
                    <input type="number" name="search_res_num" value="<?= h($search_res_num) ?>" style="width:80px; text-align:center;" required placeholder="No">
                    <button type="submit" class="btn" style="padding: 8px 20px;">表示</button>
                </form>

                <?php if ($target_res): ?>
                    <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:4px; border:1px dashed #666;">
                        <?php
                            $ts = strtotime($target_res['created_at']);
                            $time_diff = time() - $ts;
                            if (abs($time_diff) > 28800) { $ts += 9*3600; }
                            $date_str = date('Y/m/d H:i', $ts);
                        ?>
                        <p style="margin-top:0; border-bottom:1px solid #444; padding-bottom:5px;">
                            <b>No.<?= $target_res['post_num'] ?> (<?= $date_str ?>)</b> の編集
                        </p>

                        <form method="post">
                            <input type="hidden" name="mode" value="manage_res_action">
                            <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                            <input type="hidden" name="target_res_id" value="<?= $target_res['id'] ?>">
                            <input type="hidden" name="search_res_num" value="<?= $target_res['post_num'] ?>">

                            <div style="margin-bottom:10px;">
                                <label style="font-size:0.8em;">名前</label>
                                <input type="text" id="manage_res_name" name="res_name" value="<?= h($target_res['name']) ?>" required>
                            </div>
                            <div style="margin-bottom:10px;">
                                <label style="font-size:0.8em;">本文 (外部URL/画像 禁止)</label>
                                <textarea id="manage_res_content" name="res_content" rows="5" required><?= h($target_res['content']) ?></textarea>
                            </div>

                            <div style="margin-bottom: 25px; border:1px solid #555; padding:10px; border-radius:4px;">
                                <button type="button" id="btn_manage_preview" class="btn" style="background:#555; width:100%; font-size:0.9em;">▼ 変更後のプレビュー (CSS適用)</button>
                                <div id="manage_preview_host" style="border:1px dashed #666; min-height:50px; background:#fff; margin-top:10px; color:#000; padding:0; overflow:hidden;"></div>
                            </div>

                            <div style="text-align:right; margin-bottom:20px;">
                                <button type="submit" name="manage_action" value="update_content" class="btn" style="background:#3498db; font-size:0.9em;">内容を修正保存</button>
                            </div>

                            <hr style="border:0; border-top:1px dashed #444; margin:15px 0;">

                            <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                                <div style="flex:1;">
                                    <label style="font-size:0.8em;">新しい削除パスワード</label>
                                    <input type="text" name="new_res_pass" placeholder="変更する場合のみ入力">
                                </div>
                                <button type="submit" name="manage_action" value="change_pass" class="btn" style="background:#555; font-size:0.9em;">PW変更</button>
                                <button type="submit" name="manage_action" value="delete" class="btn" style="background:#ff4f4f; font-size:0.9em; margin-left:auto;" onclick="return confirm('本当に削除しますか？\n取り消しはできません。');">この記事を削除</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
                <?php if ($type === 'thread'): ?>
                    <form action="download.php" method="post" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="auth_pass" value="<?= h($input_pass) ?>">
                        <button type="submit" class="btn" style="background:#555; font-size:0.9em;">📥 ログ保存(ZIP)</button>
                    </form>
                <?php endif; ?>

                <form method="post" onsubmit="return confirm(<?= h($delete_confirm_js) ?>);" style="margin:0;">
                    <input type="hidden" name="mode" value="delete">
                    <input type="hidden" name="hidden_pass" value="<?= h($input_pass) ?>">
                    <button type="submit" class="btn" style="background:#ff4f4f; font-size:0.9em;">🗑 削除する</button>
                </form>
            </div>
        </div>
        
<script>
    function updatePreviewIframe(hostId, contentHtml, cssVal) {
        const host = document.getElementById(hostId);
        if (!host) return;

        const docContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <link rel="stylesheet" href="../css/style.css">
                <style>
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
        
        iframe.contentWindow.document.open();
        iframe.contentWindow.document.write(docContent);
        iframe.contentWindow.document.close();

        iframe.onload = function() {
            const h = iframe.contentWindow.document.documentElement.scrollHeight;
            iframe.style.height = (h + 20) + 'px';
        };
    }

    const previewBtn = document.getElementById('previewBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', function() {
            const cssText = document.getElementById('cssInput').value;
            const htmlContent = document.getElementById('sampleHtmlInput').value;
            updatePreviewIframe('html-preview-container', htmlContent, cssText);
        });
    }

    const btnPostPreview = document.getElementById('btn_post_preview');
    if (btnPostPreview) {
        btnPostPreview.addEventListener('click', function() {
            const content = document.getElementById('post_content').value;
            const css = document.getElementById('edit_thread_css').value;
            
            const postHtml = `
                <div style="line-height: 1.8; letter-spacing: 0.05em;">${content}</div>
            `;
            updatePreviewIframe('post_preview_host', postHtml, css);
        });
    }

    const btnManagePreview = document.getElementById('btn_manage_preview');
    if (btnManagePreview) {
        btnManagePreview.addEventListener('click', function() {
            const content = document.getElementById('manage_res_content').value;
            const css = document.getElementById('edit_thread_css').value;
            
            const postHtml = `
                <div style="line-height: 1.8; letter-spacing: 0.05em;">${content}</div>
            `;
            updatePreviewIframe('manage_preview_host', postHtml, css);
        });
    }
</script>
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