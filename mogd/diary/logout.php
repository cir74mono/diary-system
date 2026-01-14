<?php
session_start();

// すべてのセッション変数を空にする
$_SESSION = [];

// セッションクッキーも削除する
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを完全に破壊する
session_destroy();

echo '<div style="text-align:center; padding:50px;">';
echo '<h1>リセット完了</h1>';
echo '<p>管理者ログイン状態と、すべての閲覧パスワード状態を削除しました。</p>';
echo '<p>これで「一般の訪問者」と同じ状態になりました。</p>';
echo '<p><a href="index.php">トップページに戻って確認する</a></p>';
echo '</div>';
?>