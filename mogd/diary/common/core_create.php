<?php
require __DIR__ . '/db.php';
require_site_login();

$genre_name = 'DIARY'; 

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$current_dir = basename(getcwd()); 
if (isset($genres[$current_dir])) {
    $genre_name = $genres[$current_dir];
}

$page_title = '新規作成 | ' . $genre_name . ' - DIARY';

require __DIR__ . '/header.php';

date_default_timezone_set('Asia/Tokyo');

$error = '';
$title = ''; $name = ''; $desc = ''; $custom_css = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $name = $_POST['name'] ?? '';
    $desc = $_POST['desc'] ?? '';
    $custom_css = $_POST['custom_css'] ?? '';
    $sys_pass = $_POST['sys_pass'] ?? '';
    $lock_level = isset($_POST['lock_level']) ? (int)$_POST['lock_level'] : 0;

    $limit_max = $pdo->query("SELECT value FROM settings WHERE key = 'max_threads'")->fetchColumn();
    $limit_max = $limit_max ? (int)$limit_max : 500;

    $stmt_count = $pdo->query("SELECT COUNT(*) FROM threads");
    $total_threads = $stmt_count->fetchColumn();
    if ($total_threads >= $limit_max) {
        $error = "このジャンルの日記作成数が上限({$limit_max}件)に達しているため、新規作成できません。";
    }

    if (!$error) {
        $limit_rate = $pdo->query("SELECT value FROM settings WHERE key = 'rate_limit_per_hour'")->fetchColumn();
        $limit_rate = $limit_rate ? (int)$limit_rate : 50;

        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $stmt_rate = $pdo->prepare("SELECT COUNT(*) FROM threads WHERE created_at > ?");
        $stmt_rate->execute([$one_hour_ago]);
        $recent_threads = $stmt_rate->fetchColumn();
        
        if ($recent_threads >= $limit_rate) {
            $error = '作成制限: 現在混雑しています。しばらく時間を空けてから作成してください。';
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'create_pass'");
        $stmt->execute();
        $saved_pass = $stmt->fetchColumn();

        if ($saved_pass && password_verify($sys_pass, $saved_pass)) {
            $w_pass = password_hash($_POST['write_pass'], PASSWORD_DEFAULT);
            $d_pass = password_hash($_POST['del_pass'], PASSWORD_DEFAULT);
            $v_pass = !empty($_POST['view_pass']) ? password_hash($_POST['view_pass'], PASSWORD_DEFAULT) : null;
            $now = date('Y-m-d H:i:s');

            try {
                $stmt = $pdo->prepare("INSERT INTO threads (title, creator_name, description, custom_css, write_pass, view_pass, del_pass, lock_level, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $name, $desc, $custom_css, $w_pass, $v_pass, $d_pass, $lock_level, $now]);
                
                header("Location: index.php");
                exit;
            } catch (Exception $e) {
                $error = 'データベースエラー: 作成に失敗しました。';
            }
        } else {
            $error = '作成用パスワードが違います。';
        }
    }
}
?>
<div class="container">
    <h1><?= h($genre_name) ?><br>新規日記作成</h1>
    <p style="font-size:0.9em; color:var(--text-sub);">
        ※こちらは <b><?= h($genre_name) ?></b> 専用の日記です。お間違えの無いようにお願いします。<br>
        ※各ジャンルの振り分け詳細は<a href="../rule.php">RULE</a>をご確認ください。
    </p>
    <?php if($error): ?>
        <div class="msg-box msg-error">
            <p style="margin: 0;"><?= h($error) ?></p>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <form method="post">
            <div class="form-row">
                <label>タイトル</label>
                <input type="text" name="title" value="<?= h($title) ?>" required>
            </div>
            <div class="form-row">
                <label>作成者名</label>
                <input type="text" name="name" value="<?= h($name) ?>" required>
            </div>
            <div class="form-row">
                <label>概要</label>
                <textarea name="desc" rows="3" required><?= h($desc) ?></textarea>
            </div>
            <div class="form-row">
                <label>専用CSS (任意)</label>
                <textarea name="custom_css" rows="4" style="font-family:monospace;"><?= h($custom_css) ?></textarea>
            </div>
            
            <hr class="dashed-divider">
            
            <div class="form-row">
                <label>作成用パスワード (必須)</label>
                <input type="password" name="sys_pass" required>
            </div>
            <div class="form-row">
                <label>書き込み用パスワード</label>
                <input type="password" name="write_pass" required>
            </div>
            <div class="form-row">
                <label>編集/削除パスワード</label>
                <input type="password" name="del_pass" required>
            </div>

            <div class="form-section">
                <label style="margin-bottom:10px; display:block;">閲覧制限設定 (任意)</label>
                <input type="password" name="view_pass" placeholder="閲覧パスワード (空欄なら公開)" style="margin-bottom:10px;">
                
                <div style="font-size:0.9em;">
                    <label style="margin-right:15px;">
                        <input type="radio" name="lock_level" value="0" checked> 全て隠す
                    </label>
                    <label>
                        <input type="radio" name="lock_level" value="1"> 概要のみ公開 (表紙を表示)
                    </label>
                </div>
            </div>
            
            <div class="text-center-mb" style="margin-top: 30px;">
                <button type="submit" class="btn" style="padding: 12px 40px; font-weight: bold;">作成する</button>
            </div>
        </form>
    </div>
    <div class="text-center-box">
        <a href="index.php" style="color: var(--text-sub); text-decoration: none;">&laquo; 一覧に戻る</a>
    </div>
    <div class="footer-link"><?php require __DIR__ . '/footer.php'; ?></div>
</div>
</body>
</html>