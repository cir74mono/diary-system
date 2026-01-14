<?php
ob_start();
require __DIR__ . '/db.php';
require_site_login();

// --- タイトル付きリンク変換関数 ---
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
            $stmt = $pdo->prepare("SELECT title FROM threads WHERE id = ?");
            $stmt->execute([$tid]);
            $fetched = $stmt->fetchColumn();
            if ($fetched) {
                $label = htmlspecialchars($fetched, ENT_QUOTES, 'UTF-8');
            }
        } catch (Exception $e) {}
        
        return '<a href="../thread.php?id=' . $tid . '" target="_blank">' . $label . '</a>';
    }, $text);

    return $text;
}
// ------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('不正なリクエストです。');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$pass = $_POST['auth_pass'] ?? '';

if (!$id || !$pass) exit('IDまたはパスワードが不足しています。');

$stmt = $pdo->prepare("SELECT * FROM threads WHERE id = ?");
$stmt->execute([$id]);
$thread = $stmt->fetch();

if (!$thread || !password_verify($pass, $thread['del_pass'])) exit('パスワードが違います。');

$stmt = $pdo->prepare("SELECT * FROM posts WHERE thread_id = ? ORDER BY post_num ASC");
$stmt->execute([$id]);
$posts = $stmt->fetchAll();

$title_safe = h($thread['title']);

// 概要の変換処理
$desc_raw = $thread['description'];
$desc_raw = convert_links_with_titles($desc_raw, $pdo);
$desc_raw = preg_replace('/(?<!>)>>(\d+)/', '<a href="#post-$1">>>$1</a>', $desc_raw);

$css_safe = $thread['custom_css'] ?? '';
$date_safe = date('Y/m/d');

$style_css_content = '';
$possible_paths = [
    __DIR__ . '/../common/css/style.css', 
    __DIR__ . '/../css/style.css',        
    __DIR__ . '/style.css'                
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $style_css_content = file_get_contents($path);
        $style_css_content = str_replace('@charset "UTF-8";', '', $style_css_content);
        break;
    }
}

// デフォルトCSS（style.cssが読み込めなかった場合の保険）
if (empty($style_css_content)) {
    $style_css_content = "
        @import url('https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic&display=swap');
        body { font-family: 'Zen Maru Gothic', sans-serif; }
    ";
}

// ----------------------------------------------------
// HTML生成
// ----------------------------------------------------
$html = <<<EOT
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{$title_safe}</title>
    <style>
        {$style_css_content}

        :root {
            --bg-color: #202020;
            --text-main: #e0e0e0;
            --link-color: #ffdb4f; 
            --border-color: #404040;
        }
        :root[data-theme='light'] {
            --bg-color: #f9f9f9;
            --text-main: #333333;
            --link-color: #0056b3; 
            --border-color: #cccccc;
        }

        body { 
            padding: 30px 20px; 
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
            transition: background-color 0.3s, color 0.3s;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
        }
        
        a { 
            color: var(--link-color); 
            text-decoration: none; 
        }
        .theme-btns {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
        .theme-btns button {
            padding: 5px 12px;
            cursor: pointer;
            border-radius: 4px;
            border: 1px solid #888;
            background: #444;
            color: #fff;
            font-size: 0.9em;
        }
        :root[data-theme="light"] .theme-btns button {
            background: #fff;
            color: #333;
            border-color: #ccc;
        }

        {$css_safe}
    </style>
</head>
<body>
    <div class="container">
        <div class="theme-btns">
            <button onclick="setTheme('light')">☀ Light</button>
            <button onclick="setTheme('dark')">🌙 Dark</button>
        </div>

        <h1>{$title_safe}</h1>
        
        <div style="margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
            <div style="line-height: 1.8; white-space: pre-wrap; word-wrap: break-word;">{$desc_raw}</div>
            <div style="text-align: right; font-size: 0.8em; color: #888; margin-top: 15px;">
                保存日: {$date_safe}
            </div>
        </div>
EOT;

foreach ($posts as $post) {
    $num = $post['post_num'];
    $name = h($post['name']);
    $date = date('Y/m/d H:i', strtotime($post['created_at']));
    
    $body = trim($post['content']);
    $body = convert_links_with_titles($body, $pdo);
    $body = preg_replace('/(?<!>)>>(\d+)/', '<a href="#post-$1">>>$1</a>', $body);
    
    $html .= <<<EOT
        <div id="post-{$num}" style="margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px dashed var(--border-color);">
            <div style="margin-bottom: 8px; font-size: 0.95em; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <span style="font-weight: bold; color: #a0a0a0; margin-right: 5px;">{$num} :</span>
                    <b style="color: inherit;">{$name}</b>
                </div>
                <span style="font-size: 0.8em; color: #a0a0a0;">{$date}</span>
            </div>
            <div class="post-content" style="line-height: 1.8; white-space: pre-wrap; word-wrap: break-word; letter-spacing: 0.05em;">{$body}</div>
        </div>
EOT;
}

$html .= <<<EOT
    </div>
    <script>
        function setTheme(mode) {
            if (mode === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
        }
    </script>
</body>
</html>
EOT;

// ----------------------------------------------------
// ZIP圧縮と出力
// ----------------------------------------------------
$zip = new ZipArchive();
$safe_filename = preg_replace('/[\\/\\:*?"<>|]/', '_', $thread['title']);
$zip_download_name = $safe_filename . '.zip';
$temp_file = tempnam(sys_get_temp_dir(), 'zip');

if ($zip->open($temp_file, ZipArchive::CREATE) === TRUE) {
    $zip->addFromString($safe_filename . '.html', $html);
    $zip->close();
    ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . urlencode($zip_download_name) . '"');
    header('Content-Length: ' . filesize($temp_file));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($temp_file);
    unlink($temp_file);
}