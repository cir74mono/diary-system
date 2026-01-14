<?php
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', 0);

$db_file = getcwd() . '/bbs.db';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL;');

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS threads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        creator_name TEXT,
        description TEXT,
        custom_css TEXT, 
        write_pass TEXT,
        view_pass TEXT,
        del_pass TEXT,
        lock_level INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        thread_id INTEGER,
        post_num INTEGER DEFAULT 0,
        name TEXT,
        content TEXT,
        del_pass TEXT,
        is_sage INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $cols = $pdo->query("PRAGMA table_info(threads)")->fetchAll();
    $has_lock = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'lock_level') { $has_lock = true; break; }
    }
    if (!$has_lock) {
        $pdo->exec("ALTER TABLE threads ADD COLUMN lock_level INTEGER DEFAULT 0");
    }

    $days_limit = 60; 
    $stmt_set = $pdo->query("SELECT value FROM settings WHERE key = 'auto_delete_days'");
    if ($stmt_set && $val = $stmt_set->fetchColumn()) {
        $days_limit = (int)$val;
    }

    if ($days_limit > 0) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_limit} days"));
        $sql_delete_threads = "DELETE FROM threads 
                               WHERE id IN (
                                   SELECT thread_id 
                                   FROM posts 
                                   GROUP BY thread_id 
                                   HAVING MAX(created_at) < :cutoff
                               )";
        $stmt_del = $pdo->prepare($sql_delete_threads);
        $stmt_del->execute([':cutoff' => $cutoff_date]);
        $pdo->exec("DELETE FROM posts WHERE thread_id NOT IN (SELECT id FROM threads)");
    }

} catch (PDOException $e) {
    exit('Database Error');
}

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function require_site_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['site_auth'])) {
        header("Location: ../login.php");
        exit;
    }
}