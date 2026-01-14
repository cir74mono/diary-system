<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    

    $master_db = __DIR__ . '/blue/bbs.db';
    
    if (file_exists($master_db)) {
        try {
            $pdo = new PDO('sqlite:' . $master_db);
 
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'site_pass'");
            $stmt->execute();
            $hash = $stmt->fetchColumn();


            if ($hash && password_verify($pass, $hash)) {
                $_SESSION['site_auth'] = true;
                header("Location: index.php"); 
                exit;
            } else {
                $error = 'パスワードが違います。';
            }
        } catch (Exception $e) {
            $error = 'データベースエラーが発生しました。';
        }
    } else {
        $error = 'データベースが見つかりません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="common/css/style.css">
</head>
<body>
    <div class="container" style="display: flex; justify-content: center; align-items: center; min-height: 80vh; flex-direction:column;">
        <div class="card-glass text-center" style="width: 100%; max-width: 400px; padding: 40px;">
            <h1 class="mb-4">MOGD. LOGIN</h1>
            <center><p>パスワードを入力ください</p></center>
            
            <?php if ($error): ?>
                <p style="color: #ff4f4f; font-weight: bold;"><?= h($error) ?></p>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-4">
                    <input type="password" name="password" class="form-control-glass" placeholder="PASSWORD" required>
                </div><br>
                <button type="submit" class="btn-glass" style="width: 100%; padding: 12px;">ENTER</button>
            </form>
        </div>
    <div class="text-center" style="margin-top:50px; margin-bottom:30px; text-align: center;">
        <a href="javascript:history.back()" style="color: #888; text-decoration: none;">&laquo; 戻る</a>
    </div>
    <div class="footer-link">(c) <?= date('Y') ?> mogd.</div>
    </div>
</body>
</html>
<?php
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>