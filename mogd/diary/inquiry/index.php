<?php

define('DB_FILE', 'board.db');

date_default_timezone_set('Asia/Tokyo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$ga4_tag = <<<EOT
<script async src="https://www.googletagmanager.com/gtag/js?id=G-V5XY02ZGYE"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-V5XY02ZGYE');
</script>
EOT;

if (empty($_SESSION['site_auth'])) {
    $login_error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_password'])) {
        $pass = $_POST['site_password'] ?? '';
        
         $master_db = __DIR__ . '/../blue/bbs.db';
        
        if (file_exists($master_db)) {
            try {
                $pdo_auth = new PDO('sqlite:' . $master_db);
                $stmt = $pdo_auth->prepare("SELECT value FROM settings WHERE key = 'site_pass'");
                $stmt->execute();
                $hash = $stmt->fetchColumn();

                if ($hash && password_verify($pass, $hash)) {
                    $_SESSION['site_auth'] = true;
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $login_error = 'パスワードが違います。';
                }
            } catch (Exception $e) {
                $login_error = '認証データベースエラーが発生しました。';
            }
        } else {
            $login_error = '認証データベースが見つかりません。';
        }
    }

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>MOGD. LOGIN</title>
    <link rel="stylesheet" href="../common/css/style.css">
    <?= $ga4_tag ?>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>
    <div class="container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh; flex-direction:column;">
        <div class="card" style="width: 100%; max-width: 400px; padding: 40px; text-align:center;">
            <h1 style="margin-bottom: 20px; letter-spacing: 0.1em;">MOGD. LOGIN</h1>
            <p style="margin-bottom: 20px; color:var(--text-sub);">パスワードを入力ください</p>
            
            <?php if ($login_error): ?>
                <p style="color: #ff4f4f; font-weight: bold; margin-bottom: 15px;"><?= h($login_error) ?></p>
            <?php endif; ?>
            
            <form method="post">
                <div style="margin-bottom: 20px;">
                    <input type="password" name="site_password" placeholder="PASSWORD" required style="padding: 12px; width: 100%; text-align:center;">
                </div>
                <button type="submit" class="btn" style="width: 100%; padding: 12px; font-weight:bold;">ENTER</button>
            </form>
        </div>
        
        <div class="text-center" style="margin-top:30px; margin-bottom:30px;">
            <a href="javascript:history.back()" style="color: var(--text-sub); text-decoration: none;">&laquo; 戻る</a>
        </div>
        <div class="footer-link">(c) <?= date('Y') ?> mogd.</div>
    </div>
</body>
</html>
<?php
    exit;
}

try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_type TEXT,
        genre TEXT,
        user_id_text TEXT,
        content TEXT,
        is_private INTEGER,
        post_pass TEXT, 
        del_pass TEXT, 
        admin_status TEXT DEFAULT '未対応',
        admin_reply TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $cols = $pdo->query("PRAGMA table_info(requests)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('post_pass', $cols)) $pdo->exec("ALTER TABLE requests ADD COLUMN post_pass TEXT");

} catch (PDOException $e) {
    exit('データベース接続エラー: ' . htmlspecialchars($e->getMessage()));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_post_id'])) {
    $unlock_id = $_POST['unlock_post_id'];
    $unlock_pass = $_POST['unlock_pass'];
    
    $stmt = $pdo->prepare("SELECT post_pass FROM requests WHERE id = ?");
    $stmt->execute([$unlock_id]);
    $stored_pass = $stmt->fetchColumn();

    if ($stored_pass && password_verify($unlock_pass, $stored_pass)) {
        $_SESSION['unlocked_posts'][$unlock_id] = true;
    } else {
        $error = 'パスワードが違います。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    $del_id = $_POST['delete_post_id'];
    $del_pass_input = $_POST['delete_pass'] ?? '';

    $stmt = $pdo->prepare("SELECT post_pass FROM requests WHERE id = ?");
    $stmt->execute([$del_id]);
    $stored_post_pass = $stmt->fetchColumn();

    if ($stored_post_pass && password_verify($del_pass_input, $stored_post_pass)) {
        $pdo->prepare("DELETE FROM requests WHERE id = ?")->execute([$del_id]);
        $message = '記事を削除しました。';
    } else {
        $error = 'パスワードが違います。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $content = trim($_POST['content']);
    $user_type = $_POST['user_type'] ?? '利用者';
    $genre = $_POST['genre'] ?? 'その他';
    $user_id = trim($_POST['user_id_text']);
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $raw_pass = $_POST['post_pass'] ?? ''; 

    if (mb_strlen($content) > 1000 || mb_strlen($content) === 0) {
        $error = '内容は1文字以上1000文字以内で入力してください。';
    } elseif (preg_match('/(https?:\/\/|www\.|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|<img|<a)/i', $content)) {
        $error = 'URL、メールアドレス、画像の貼り付けは禁止されています。';
    } elseif (empty($raw_pass)) {
        $error = 'パスワードを設定してください。';
    } else {
        $pass_hash = password_hash($raw_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO requests (user_type, genre, user_id_text, content, is_private, post_pass) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_type, $genre, $user_id, $content, $is_private, $pass_hash]);


        $to_email = 'mgmgb77@gmail.com'; 
        
        mb_language("Japanese");
        mb_internal_encoding("UTF-8");
        
        $mail_subject = "【MOGD】お問い合わせがありました";
        
        $mail_body = "以下の内容で新しい投稿がありました。\n\n";
        $mail_body .= "--------------------------------------------------\n";
        $mail_body .= "【種別】 " . $user_type . "\n";
        $mail_body .= "【ジャンル】 " . $genre . "\n";
        $mail_body .= "【ID】 " . ($user_id ?: 'なし') . "\n";
        if ($is_private) {
            $mail_body .= "※非公開（秘匿）設定で投稿されています\n";
        }
        $mail_body .= "--------------------------------------------------\n\n";
        $mail_body .= $content . "\n\n";
        $mail_body .= "--------------------------------------------------\n";
        $mail_body .= "投稿日時: " . date('Y/m/d H:i:s');
        
        $from_email = 'noreply@' . $_SERVER['SERVER_NAME'];
        $headers = "From: " . $from_email . "\r\n";

        mb_send_mail($to_email, $mail_subject, $mail_body, $headers);


        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$posts = $pdo->query("SELECT * FROM requests ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>要望・トラブル報告 - DIARY</title>
    <link rel="stylesheet" href="../common/css/style.css">
    <?= $ga4_tag ?>
    <style>
        .input-compact {
            padding: 4px 8px !important;
            font-size: 0.9em !important;
            width: 120px !important;
            height: auto !important;
            display: inline-block;
            vertical-align: middle;
        }
        .label-text {
            font-size: 0.85em;
            color: var(--text-sub);
            margin-right: 5px;
            vertical-align: middle;
        }
        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 30px var(--card-bg) inset !important;
            -webkit-text-fill-color: var(--text-main) !important;
        }
        .form-row-inline {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>

<div class="container">
    <h1>要望・トラブル報告</h1>

   <?php if ($message): ?>
        <div class="msg-box msg-success"><p style="margin:0;"><?= h($message) ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg-box msg-error"><p style="margin:0;"><?= h($error) ?></p></div>
    <?php endif; ?>

    <div class="card" style="margin-top: 30px;">
        <div style="padding: 20px;">
            <form method="post">
                <div class="form-row-inline">
                    <div>
                        <label><input type="radio" name="user_type" value="利用者" checked> 利用者</label>
                        <label style="margin-left:10px;"><input type="radio" name="user_type" value="訪問者"> 訪問者</label>
                    </div>
                    <div>
                        <select name="genre" style="padding:4px; font-size:0.9em;">
                            <option value="バグ報告">バグ報告</option>
                            <option value="違反報告">違反報告</option>
                            <option value="改善・要望" selected>改善・要望</option>
                            <option value="その他">その他</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="user_id_text" placeholder="日記名(任意)" class="input-compact" style="width:100px !important;">
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <textarea name="content" rows="4" maxlength="1000" placeholder="内容を入力してください" style="width:100%;"></textarea>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                        <div>
                            <span class="label-text">削除パス</span>
                            <input type="password" name="post_pass" required class="input-compact">
                        </div>
                        <label style="cursor:pointer; display:flex; align-items:center; font-size:0.9em;">
                            <input type="checkbox" name="is_private" value="1"> 
                            <span style="margin-left:5px;">内容を秘匿する</span>
                        </label>
                    </div>
                    <button type="submit" name="submit_post" class="btn" style="padding: 6px 20px;">送信</button>
                </div>
            </form>
        </div>
    </div>

    <div class="dashed-divider"></div>

    <?php if (empty($posts)): ?>
        <p style="text-align:center; color:var(--text-sub);">現在投稿はありません。</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php 
                $showContent = true;
                $isUnlocked = isset($_SESSION['unlocked_posts'][$post['id']]);

                if ($post['is_private'] == 1) {
                    if (!$isUnlocked) {
                        $showContent = false;
                    }
                }

                $statusClass = 'req-status-yet';
                if ($post['admin_status'] === '対応しました') $statusClass = 'req-status-done';
                if ($post['admin_status'] === '対応しません') $statusClass = 'req-status-wont';

                $ts = strtotime($post['created_at'] . ' UTC');
                $date_disp = date('Y/m/d H:i', $ts);
            ?>

            <div class="card" style="padding:15px;">
                <div class="req-meta" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;">
                    <div>
                        <span style="font-weight:bold; color:var(--accent);">【<?= h($post['user_type']) ?>】</span>
                        <?= h($post['genre']) ?> / ID:<?= h($post['user_id_text'] ?: 'なし') ?>
                        <?php if ($post['is_private']): ?>
                            <span class="req-private-mark">🔒 非公開</span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <div style="font-size:0.85em;"><?= $date_disp ?></div>
                        <button class="btn btn-sm" onclick="document.getElementById('del_form_<?= $post['id'] ?>').style.display = 'inline-flex';" style="background:transparent; border:1px solid var(--border); color:var(--text-sub); padding:2px 8px; font-size:0.8em;">削除</button>
                    </div>
                </div>

                <div id="del_form_<?= $post['id'] ?>" style="display:none; margin-bottom:10px; justify-content:flex-end; gap:5px;">
                    <form method="post" style="display:flex; align-items:center; gap:5px;">
                        <input type="hidden" name="delete_post_id" value="<?= $post['id'] ?>">
                        <span class="label-text">Pass:</span>
                        <input type="password" name="delete_pass" class="input-compact" style="width:80px !important;">
                        <button type="submit" class="btn btn-sm" style="background:#ff4f4f; color:#fff; padding:3px 8px;" onclick="return confirm('削除しますか？');">実行</button>
                        <button type="button" class="btn btn-sm" style="padding:3px 8px;" onclick="document.getElementById('del_form_<?= $post['id'] ?>').style.display = 'none';">×</button>
                    </form>
                </div>

                <div style="margin-top:10px; margin-bottom:15px;">
                    <?php if ($showContent): ?>
                        <div style="white-space: pre-wrap; line-height:1.6; font-size:0.95em;"><?= nl2br(h($post['content'])) ?></div>
                    <?php else: ?>
                        <div style="color:var(--text-sub); font-style:italic; padding:10px; text-align:center;">
                            <p style="margin:0 0 5px 0; font-size:0.9em;">[この投稿は秘匿されています]</p>
                            <form method="post" style="display:inline-flex; gap:5px; align-items:center;">
                                <input type="hidden" name="unlock_post_id" value="<?= $post['id'] ?>">
                                <span class="label-text">Pass:</span>
                                <input type="password" name="unlock_pass" class="input-compact" style="width:80px !important;">
                                <button type="submit" class="btn btn-sm" style="padding:3px 8px;">閲覧</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($post['admin_status'] !== '未対応' || $post['admin_reply']): ?>
                    <div class="req-admin-area">
                        <div class="req-admin-label">
                            管理人
                            <span class="req-status <?= $statusClass ?>"><?= h($post['admin_status']) ?></span>
                        </div>
                        <?php if ($post['admin_reply']): ?>
                            <div style="color:var(--text-main); line-height:1.6;"><?= nl2br(h($post['admin_reply'])) ?></div>
                        <?php else: ?>
                            <span style="color:var(--text-sub); font-size:0.9em;">(コメントなし)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center-box">
        <a href="../index.php" style="color: var(--text-sub); text-decoration: none;">&laquo; 一覧に戻る</a>
    </div>
    <div class="footer-link">(c) <?= date('Y') ?> mogd.</div>
</div>

<script>
    const toggleBtn = document.getElementById('themeToggle');
    const html = document.documentElement;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (html.getAttribute('data-theme') === 'light') {
                html.removeAttribute('data-theme');
                localStorage.setItem('theme', 'dark');
            } else {
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });
    }
</script>

</body>
</html>