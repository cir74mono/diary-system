<?php
// ブラウザからのアクセスを拒否 (セキュリティ対策)
// Cron(コマンドライン)からの実行のみ許可します
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access Denied');
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 設定：バックアップ対象のジャンル
// config.php と同じ内容にしてください
$genres = [
    'blue' => 'ANIME&MANGA',
    'white' => 'GAME',
    'red' => 'STREAMER&VTUBER',
    'black' => 'OTHER',
    'inquiry' => '要望板' // 要望板も追加
];

// 保存先ディレクトリ
$backup_dir = __DIR__ . '/backups';
$current_month = date('Y-m'); // 例: 2026-01

echo "バックアップ処理を開始します...\n";

try {
    // 1. バックアップフォルダがなければ作成
    if (!file_exists($backup_dir)) {
        if (mkdir($backup_dir, 0700, true)) {
            echo "フォルダ作成: $backup_dir\n";
            // 外部からダウンロードされないように .htaccess を作成
            file_put_contents($backup_dir . '/.htaccess', "Order allow,deny\nDeny from all");
        } else {
            throw new Exception("バックアップフォルダの作成に失敗しました。");
        }
    }

    // 2. 各DBをコピー
    foreach ($genres as $dir => $name) {
        // DBファイルの場所
        if ($dir === 'inquiry') {
            $source_db = __DIR__ . '/inquiry/board.db';
        } else {
            $source_db = __DIR__ . '/' . $dir . '/bbs.db';
        }

        // バックアップファイル名 (例: blue_2026-01.db)
        $backup_file = $backup_dir . '/' . $dir . '_' . $current_month . '.db';

        if (file_exists($source_db)) {
            // 今月分がまだない、または上書きしたい場合はコピー
            // ここでは「既にあったら何もしない（月1回作成）」設定にします
            if (!file_exists($backup_file)) {
                if (copy($source_db, $backup_file)) {
                    echo "[OK] 作成: $dir ($name)\n";
                } else {
                    echo "[NG] コピー失敗: $dir\n";
                }
            } else {
                echo "[SKIP] 既に存在します: $dir\n";
            }
        } else {
            echo "[SKIP] 元データなし: $dir\n";
        }
    }
    
    echo "処理完了。\n";

} catch (Exception $e) {
    echo "エラー発生: " . $e->getMessage() . "\n";
}
?>