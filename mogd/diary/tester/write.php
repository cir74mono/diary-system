<?php
require 'db.php';
require_site_login();

date_default_timezone_set('Asia/Tokyo');

$thread_id = $_GET['id'] ?? $_POST['thread_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();

if (!$thread) exit('日記が存在しません。');

$genre_name = 'DIARY';
if (file_exists('../config.php')) {
    require_once '../config.php';
    $current_dir = basename(__DIR__);
    if (isset($genres[$current_dir])) {
        $genre_name = $genres[$current_dir];
    }
}
$page_title = '投稿 : ' . $thread['title'] . ' | ' . $genre_name . ' - DIARY';


$error = '';
$name = isset($_COOKIE['my_name']) ? $_COOKIE['my_name'] : '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $write_pass = $_POST['write_pass'] ?? '';
    $del_pass = $_POST['del_pass'] ?? '';
    $is_sage = isset($_POST['sage']) ? 1 : 0;

    if ($name === '' || $content === '') {
        $error = '名前と本文は必須です。';
    } else {
        // 1. 画像拡張子チェック (いかなる場合も禁止)
        if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $content)) {
            $error = "画像ファイルのリンクや投稿は禁止されています。";
        }
        // 2. URLチェック (cirmg.com 以外は禁止)
        elseif (preg_match('/https?:\/\/(?!([\w-]+\.)*cirmg\.com)/i', $content)) {
            $error = '外部サイトへのリンクは禁止されています。(cirmg.com内のみ可)';
        }
    }

    if (!$error) {
        if (password_verify($write_pass, $thread['write_pass'])) {
            try {
                $pdo->beginTransaction();
                
                $stmt_max = $pdo->prepare("SELECT MAX(post_num) FROM posts WHERE thread_id = ?");
                $stmt_max->execute([$thread_id]);
                $next_num = ($stmt_max->fetchColumn() ?: 0) + 1;

                $d_pass_hash = $del_pass ? password_hash($del_pass, PASSWORD_DEFAULT) : null;
                $now = date('Y-m-d H:i:s');
                
                $stmt = $pdo->prepare("INSERT INTO posts (thread_id, post_num, name, content, del_pass, is_sage, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$thread_id, $next_num, $name, $content, $d_pass_hash, $is_sage, $now]);

                $pdo->commit();

                setcookie('my_name', $name, time() + 60*60*24*30, '/');
                header("Location: thread.php?id=" . $thread_id . "#post-" . $next_num);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "書き込みエラーが発生しました。";
            }
        } else {
            $error = "書き込みパスワードが違います。";
        }
    }
}

require 'header.php';
?>
<div class="container">
    <h1>記事を投稿する</h1>
    <div class="text-center mb-4">
        <p>日記: <b><?= h($thread['title']) ?></b></p>
    </div>
    <?php if($error): ?>
        <div class="card" style="border-color: #ff6b6b; background: rgba(255,107,107,0.1); text-align:center;">
            <p style="color: #ff6b6b; margin: 0; font-weight: bold;"><?= h($error) ?></p>
        </div>
    <?php endif; ?>
    <div class="card">
        <form method="post">
            <input type="hidden" name="thread_id" value="<?= h($thread_id) ?>">
            <input type="hidden" id="thread_css" value="<?= h($thread['custom_css'] ?? '') ?>">

            <div style="margin-bottom: 20px;">
                <label>名前</label>
                <input type="text" id="input_name" name="name" value="<?= h($name) ?>" required placeholder="名前を入力してください">
            </div>
            <div style="margin-bottom: 20px;">
                <label>内容</label>
                <textarea id="input_content" name="content" rows="8" required placeholder="内容を入力してください"><?= h($content) ?></textarea>
            </div>
            
            <hr style="border: 0; border-top: 1px solid #404040; margin: 25px 0;">

            <div style="margin-bottom: 25px; border:1px solid #555; padding:15px; border-radius:4px; background:rgba(0,0,0,0.3);">
                <label style="margin-bottom:10px; display:block;">記事プレビュー (CSS適用)</label>
                <button type="button" id="btn_preview" class="btn" style="background:#555; width:100%; margin-bottom:15px;">▼ プレビューを表示/更新</button>
                <div id="preview_host" style="border:1px dashed #666; min-height:50px; background:#fff; color:#000;"></div>
                <p style="font-size:0.8em; color:#aaa; margin-top:5px; text-align:right;">※背景色などがCSSで設定されている場合、枠内の見た目が変化します。</p>
            </div>
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label>書き込みパスワード</label>
                    <input type="password" name="write_pass" required>
                </div>
<div style="flex: 1;">
    <label>編集/削除用パスワード</label>
    <input type="password" name="del_pass">
</div>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="cursor:pointer; user-select:none;">
                    <input type="checkbox" name="sage" value="1"> sage (一覧の一番上にあげない)
                </label>
            </div>
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn" style="padding: 12px 40px; font-weight: bold;">書き込む</button>
            </div>
        </form>
    </div>
    <div class="text-center mt-4">
        <a href="thread.php?id=<?= $thread_id ?>">
            &laquo; 日記に戻る
        </a>
        <div class="footer-link"><?php require 'footer.php'; ?></div>
    </div>
</div>

<script>
document.getElementById('btn_preview').addEventListener('click', function() {
    const name = document.getElementById('input_name').value;
    const content = document.getElementById('input_content').value;
    const css = document.getElementById('thread_css').value;
    const host = document.getElementById('preview_host');

    let shadow = host.shadowRoot;
    if (!shadow) {
        shadow = host.attachShadow({mode: 'open'});
    }

    const html = `
        <style>
            body { font-family: sans-serif; margin:0; padding:10px; color:#333; background:#fff; }
            ${css}
        </style>
        <div class="container">
            <div style="margin-bottom: 30px; padding-bottom:15px; border-bottom:1px dashed #404040;">
                <div style="margin-bottom: 8px; font-size: 0.95em;">
                    <span style="font-weight: bold; color: #d4d4d4; margin-right:5px;">No.Preview :</span>
                    <span style="font-weight: bold; margin-right:10px;">${name ? name : '（名前）'}</span>
                    <span style="font-size: 0.8rem; color: #888;">
                        20XX/XX/XX XX:XX [編集]
                    </span>
                </div>
                <div style="line-height: 1.8; white-space: pre-wrap; word-wrap: break-word; letter-spacing: 0.05em;">${content}</div>
            </div>
        </div>
    `;
    shadow.innerHTML = html;
});
</script>
</body>
</html>