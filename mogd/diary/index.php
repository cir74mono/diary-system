<?php
session_start();

if (file_exists('config.php')) {
    require_once 'config.php';
} else {

    $genres = [
        'blue' => 'OTHER',
        'red' => 'STREAMER & VTUBER',
        'white' => 'GAME',
        'black' => 'ANIME & MANGA'
    ];
}

if (!isset($_SESSION['site_auth'])) {
    header("Location: login.php");
    exit;
}

$genre_counts = [];

foreach ($genres as $dir => $name) {
    $db_path = __DIR__ . '/' . $dir . '/bbs.db';
    $count = 0;
    $max = 500; 

    if (file_exists($db_path)) {
        try {
            $pdo_sub = new PDO('sqlite:' . $db_path);
            
              $count = $pdo_sub->query("SELECT COUNT(*) FROM threads")->fetchColumn();
                
            $stmt = $pdo_sub->query("SELECT value FROM settings WHERE key = 'max_threads'");
            if ($stmt) {
                $val = $stmt->fetchColumn();
                if ($val) $max = (int)$val;
            }
        } catch (Exception $e) {

        }
    }
    $genre_counts[$dir] = ['count' => $count, 'max' => $max];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MOG DIARY</title>
    <link rel="stylesheet" href="common/css/style.css">
    <style>
        .genre-grid {
            display: grid;
            gap: 15px;
            grid-template-columns: 1fr;
        }

        @media (min-width: 600px) {
            .genre-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .count-display {
            font-size: 0.85rem; 
            color: #aaa; 
            margin-top: 5px; 
            font-weight: normal;
            font-family: monospace; 
        }

        .btn-glass:hover .count-display {
            color: #ddd;
        }

        .top-menu-box {
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 1px dashed #555;
            display: flex;    
            justify-content: center;
            flex-wrap: wrap;  
            gap: 10px;   
        }

        @media (max-width: 480px) {
            .top-menu-box {
                flex-wrap: nowrap; 
                gap: 5px;    
            }
            
            .top-menu-box .btn-glass {
                padding: 6px 2px !important; 
                font-size: 12px !important;   
                flex: 1;                     
                white-space: nowrap;       
                display: flex;
                justify-content: center;
                align-items: center;
            }
        }

    </style>
</head>
<body>
    <div class="container" style="max-width: 600px; padding-top: 50px;">
        <h1 class="text-center">MOGD.</h1>
        
        <div class="glass-container text-center">
            
            <div class="top-menu-box">
                <a href="rule.php" class="btn-glass" style="border-color: #ff6b6b;">
                    利用規約・ルール
                </a>
                <a href="tester/" class="btn-glass" style="border-color: #ff6b6b;">
                    動作サンプル
                </a>
                <a href="inquiry/" class="btn-glass" style="border-color: #ff6b6b;">
                    お問い合わせ
                </a>
            </div>

            <p style="margin-bottom: 20px;">日記ジャンルを選択してください</p>
            
            <div class="genre-grid">
                <?php foreach ($genres as $dir => $name): ?>
                    <a href="<?= h($dir) ?>/index.php" class="btn-glass" style="display:block; padding: 20px;">
                        <div style="font-size: 1.2rem; font-weight: bold;">
                            <?= h($name) ?>
                        </div>
                        
                        <div class="count-display">
                            ( <?= $genre_counts[$dir]['count'] ?> / <?= $genre_counts[$dir]['max'] ?> )
                        </div>
                        </a>
                <?php endforeach; ?>
            </div>
       </div>
        <section class="update-history-section" style="max-width:800px; margin:20px auto; padding:15px; background:rgba(0,0,0,0.2); border:1px solid #444; border-radius:4px; color:#ddd;">
    <h3 style="margin-top:0; border-bottom:1px solid #555; padding-bottom:5px; font-size:1.1em;">更新履歴</h3>
    <ul style="list-style:none; padding:0; margin:0; max-height:200px; overflow-y:auto;">
        <?php
        try {
              $db_path = __DIR__ . '/blue/bbs.db'; 
            if (file_exists($db_path)) {
                $pdo_h = new PDO('sqlite:' . $db_path);
                $res = $pdo_h->query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_updates'");
                if ($res && $res->fetch()) {
                    $updates = $pdo_h->query("SELECT * FROM site_updates ORDER BY update_date DESC, id DESC LIMIT 10")->fetchAll();
                    
                    if ($updates) {
                        foreach ($updates as $up) {
                            $date = htmlspecialchars($up['update_date'], ENT_QUOTES, 'UTF-8');
                            $text = htmlspecialchars($up['content'], ENT_QUOTES, 'UTF-8');
                            echo "<li style='padding:5px 0; border-bottom:1px dashed #444; display:flex; align-items: center;'>";
                            echo "<span style='width:100px; color:#aaa; font-family:monospace;'>{$date}</span>";
                            echo "<span style='flex:1;'>{$text}</span>";
                            echo "</li>";
                        }
                    } else {
                        echo "<li style='color:#888;'>まだ更新履歴はありません。</li>";
                    }
                }
            }
        } catch (Exception $e) {
       
        }
        ?>
    </ul>
</section>
    <div class="footer-link">(c) <?= date('Y') ?> mogd.</div>
    </div>
</body>
</html>
<?php

if (!function_exists('h')) {
    function h($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
?>