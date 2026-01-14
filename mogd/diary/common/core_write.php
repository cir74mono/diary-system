<?php
require __DIR__ . '/db.php';
require_site_login();

date_default_timezone_set('Asia/Tokyo');

$thread_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0);

$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();

if (!$thread) exit('日記が存在しません。');

// ■追加: 書き込み画面でもロック状態をチェックする
if (!empty($thread['view_pass'])) {
    $site_unique_id = md5(dirname($_SERVER['SCRIPT_FILENAME']));
    $session_key = 'diary_lock_' . $site_unique_id . '_thread_' . $thread_id;
    
    // ロック未解除ならエラー画面を表示して終了
    if (empty($_SESSION[$session_key]) || $_SESSION[$session_key] !== true) {
        
        // 管理者権限チェック (必要なら有効化)
        // if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
            
            require 'header.php';
            ?>
            <div class="container">
                <div class="card" style="text-align:center; padding:40px 20px;">
                    <h3 style="color:#ff6b6b; border:none;">この日記は鍵がかかっています</h3>
                    <p style="margin-bottom:20px;">投稿するには、まず日記ページでパスワードを解除してください。</p>
                    <a href="thread.php?id=<?= $thread_id ?>" class="btn">日記ページへ移動</a>
                </div>
            </div>
            </body></html>
            <?php
            exit;
        // }
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

if ($name === '' || $content === '' || $del_pass === '') {
        $error = '名前、本文、編集/削除用パスワードはすべて必須です。';
    } else {
        if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp|bmp)/i', $content)) {
            $error = "画像ファイルのリンクや投稿は禁止されています。";
        } elseif (preg_match('/https?:\/\/(?!([\w-]+\.)*cirmg\.com)/i', $content)) {
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
    <div class="text-center-mb">
        <p>日記: <b><?= h($thread['title']) ?></b></p>
    </div>
    
    <?php if($error): ?>
        <div class="msg-box msg-error">
            <p style="margin: 0;"><?= h($error) ?></p>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <form method="post">
            <input type="hidden" name="thread_id" value="<?= h($thread_id) ?>">
            <input type="hidden" id="thread_css" value="<?= h($thread['custom_css'] ?? '') ?>">

            <div class="form-row">
                <label>名前</label>
                <input type="text" id="input_name" name="name" value="<?= h($name) ?>" required placeholder="名前を入力してください">
            </div>
            <div class="form-row">
                <label>内容</label>
                <textarea id="input_content" name="content" rows="8" required placeholder="内容を入力してください"><?= h($content) ?></textarea>
            </div>
            
            <hr class="dashed-divider">

            <div class="preview-area">
                <label style="margin-bottom:10px; display:block;">記事プレビュー (CSS適用)</label>
                
                <button type="button" id="btn_preview" class="btn" style="width:100%; margin-bottom:10px;">▼ プレビューを表示/更新</button>
                <div style="display:flex; gap:10px; margin-bottom:15px; justify-content:center;">
                    <button type="button" id="btn_preview_light" class="btn" style="background:#f0f0f0; color:#333; border:1px solid #ccc; font-size:0.9em; flex:1;">☀ ライト</button>
                    <button type="button" id="btn_preview_dark" class="btn" style="background:#333; color:#fff; border:1px solid #000; font-size:0.9em; flex:1;">🌙 ダーク</button>
                </div>

                <div id="preview_host" class="preview-host"></div>
                <p style="font-size:0.8em; color:var(--text-sub); margin-top:5px; text-align:right;">※外部呼出し（@import）とリンクは現在プレビュー上では機能しません。(書き込み後は機能します)</p>
            </div>
            
            <div class="pass-input-area">
                <div class="pass-input-group">
                    <label>書き込みパスワード</label>
                    <input type="password" name="write_pass" required>
                </div>
                <div style="flex: 1;">
                     <label>編集/削除用パスワード</label>
                     <input type="password" name="del_pass" required>
                </div>
            </div>
            <div class="form-row">
                <label style="cursor:pointer; user-select:none;">
                    <input type="checkbox" name="sage" value="1"> sage (一覧の一番上にあげない)
                </label>
            </div>
            <div class="text-center-mb" style="margin-top: 30px;">
                <button type="submit" class="btn" style="padding: 12px 40px; font-weight: bold;">書き込む</button>
            </div>
        </form>
    </div>
    <div class="text-center-box">
        <a href="thread.php?id=<?= $thread_id ?>">
            &laquo; 日記に戻る
        </a>
        <div class="footer-link"><?php require 'footer.php'; ?></div>
    </div>
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

    // プレビュー用のリンク変換関数
    function formatPreviewText(text) {
        // [dir>>>id] -> <a href="#">dir>>>id</a>
        text = text.replace(/\[([a-zA-Z0-9_-]+)>>>(\d+)\]/g, '<a href="#">$1>>>$2</a>');
        // >>>id, >>id -> <a href="#">...</a>
        text = text.replace(/>>>(\d+)/g, '<a href="#">>>>$1</a>');
        text = text.replace(/>>(\d+)/g, '<a href="#">>>$1</a>');
        return text;
    }

    function runPreview(mode) {
        const content = document.getElementById('input_content').value;
        const css = document.getElementById('thread_css').value;
        
        // リンク変換処理を適用
        let htmlContent = formatPreviewText(content);
        
        // ラッパーDiv
        const postHtml = `<div style="line-height: 1.8; letter-spacing: 0.05em;">${htmlContent}</div>`;

        updatePreviewIframe('preview_host', postHtml, css, mode);
    }

    const btnPreview = document.getElementById('btn_preview');
    if (btnPreview) {
        btnPreview.addEventListener('click', function() {
            runPreview('current');
        });
    }

    const btnLight = document.getElementById('btn_preview_light');
    if (btnLight) {
        btnLight.addEventListener('click', function() {
            runPreview('light');
        });
    }

    const btnDark = document.getElementById('btn_preview_dark');
    if (btnDark) {
        btnDark.addEventListener('click', function() {
            runPreview('dark');
        });
    }
</script>
</body>
</html>