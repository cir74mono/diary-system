<?php
ob_start();
require 'db.php';
require_site_login();

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
$desc_raw = $thread['description'];
$css_safe = $thread['custom_css'] ?? '';
$date_safe = date('Y/m/d');

// Google Fontsの読み込みURL
$font_import = "@import url('https://fonts.googleapis.com/css2?family=Dela+Gothic+One&family=DotGothic16&family=Hachi+Maru+Pop&family=Kaisei+Decol&family=Noto+Serif+JP:wght@200..900&family=Rampart+One&family=Reggae+One&family=RocknRoll+One&family=Zen+Kurenaido&family=Zen+Maru+Gothic&display=swap');\n@import url('https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap');";

$html = <<<EOT
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>{$title_safe}</title>
    <style>
        {$font_import}
        body { 
            font-family: "Zen Maru Gothic", "Noto Color Emoji", sans-serif;
            background: #202020; color: #e0e0e0; padding: 20px; line-height: 1.6; 
        }
        .container { max-width: 800px; margin: 0 auto; }
        .desc { background:#2b2b2b; padding:20px; margin-bottom:30px; border:1px solid #404040; border-radius:4px; white-space: pre-wrap; word-wrap: break-word; }
        .post { border-bottom: 1px dashed #404040; padding: 20px 0; }
        .meta { font-weight: bold; color: #d4d4d4; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px; }
        .content { white-space: pre-wrap; word-wrap: break-word; }
        a { color: #e0e0e0; }
        {$css_safe}
    </style>
</head>
<body>
    <div class="container">
        <h1>{$title_safe}</h1>
        <div class="desc">{$desc_raw}<br><br><small style="color:#888; font-size:0.8em;">保存日: {$date_safe}</small></div>
EOT;

foreach ($posts as $post) {
    $num = $post['post_num'];
    $name = h($post['name']);
    $date = date('Y/m/d H:i', strtotime($post['created_at']));
    $body = $post['content']; // HTML許可

    $html .= <<<EOT
        <div class="post">
            <div class="meta">{$num} : {$name} - {$date}</div>
            <div class="content">{$body}</div>
        </div>
EOT;
}

$html .= "</div></body></html>";

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
exit;