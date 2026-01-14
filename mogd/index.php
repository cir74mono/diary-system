<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-V5XY02ZGYE"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-V5XY02ZGYE');
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, noimageindex">
    <title>MOGD. - index</title>
    
    <link rel="stylesheet" href="diary/common/css/style.css">
    
    <style>
        .accordion-item {
            border: 1px solid #404040;
            margin-bottom: 10px;
            border-radius: 4px;
            background: rgba(43, 43, 43, 0.8);
            overflow: hidden;
        }
        .accordion-header {
            width: 100%;
            text-align: left;
            padding: 15px 20px;
            background: rgba(0,0,0,0.2);
            color: #e0e0e0;
            border: none;
            outline: none;
            cursor: pointer;
            font-size: 1.1rem;
            font-family: inherit;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        .accordion-header:hover {
            background: rgba(255,255,255,0.05);
        }
        .accordion-header::after {
            content: '+';
            font-size: 1.2rem;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .accordion-header.active::after {
            transform: rotate(45deg);
        }
        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: transparent;
        }
        .accordion-inner {
            padding: 20px;
            border-top: 1px dashed #404040;
            line-height: 1.8;
            font-size: 0.95rem;
        }
        .rule-list {
            padding-left: 20px;
            margin: 0;
        }
        .rule-list li {
            margin-bottom: 15px;
        }
        .highlight {
            color: #ff6b6b;
            font-weight: bold;
        }
        .command-box {
            background: rgba(0,0,0,0.3);
            padding: 2px 6px;
            border: 1px solid #555;
            border-radius: 4px;
            font-family: monospace;
        }
        .genre-title {
            color: #ffdb4f; 
            font-weight: bold; 
            display:inline-block; 
            border-bottom:1px solid #666; 
            margin-bottom:5px;
        }
        .icon-book {
            font-style: normal;
        }
    </style>
</head>
<body id="top">

<div class="container">
    <h1>MOGD.</h1>
    
    <div class="card" style="text-align:center; margin-bottom:30px;">
        <p>
            当サイトは、PBW（なりきり）専用の日記支援サイトです。<br>
            ご利用の前に、以下の利用規約と禁止事項を必ずご一読ください。<br>
            <span class="highlight">当サイトを利用された時点で、本規約のすべてに同意したものとみなします。</span>
        </p>
    </div>

    <div class="accordion-item">
        <button class="accordion-header">各板（ジャンル）の取り扱い</button>
        <div class="accordion-content">
            <div class="accordion-inner">
                <ul class="rule-list" style="list-style: none; padding-left: 10px;">
                    <li>
                        <span class="genre-title">ANIME & MANGA</span><br>
                        アニメ、漫画、小説のキャラクターでの利用が可能です。
                    </li>
                    <li>
                        <span class="genre-title">GAME</span><br>
                        ゲームのキャラクターでの利用が可能です。
                    </li>
                    <li>
                        <span class="genre-title">STREAMER & VTUBER</span><br>
                        ストリーマー、Vtuber、配信者での利用が可能です。
                    </li>
                    <li>
                        <span class="genre-title">OTHER</span><br>
                        上記に該当しないキャラクター、オリジナルキャラクター、または実際の芸能人などでの利用が可能です。<br>
                        <span class="highlight">※ただし実際の芸能人での利用の場合は、閲覧パスワードが必須です。</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <button class="accordion-header">基本ルール</button>
        <div class="accordion-content">
            <div class="accordion-inner">
                <ul class="rule-list">
                    <li><strong>利用資格</strong><br>
                    20歳以上であり、自身の投稿に責任を持てる方のみご利用いただけます。</li>
                    
                    <li><strong>名前の記載</strong><br>
                    作成者名（ジャンル名）は、省略せず<span class="highlight">正式名称</span>で徹底してください。(※日記内の名前は自由です)</li>
                    
                    <li><strong>ネタバレへの配慮</strong><br>
                    過度なネタバレを含む内容は、概要（表紙）にて注意書きを行うか、閲覧パスワードを設定するなど、未プレイ・未読の方への配慮をお願いします。</li>
                    
                    <li><strong>本体交流・恋愛・透過について</strong><br>
                    本体交流、本体恋愛を含む可能性のある日記は、<span class="highlight">必ず概要にその旨を記載し、閲覧パスワードを設定</span>してください。<br>
                    ※この場合、「鍵付き・表紙のみ公開（概要は誰でも読める状態）」し、鍵を配布するなどは可能です。<br>
                                        ※当サイトでの閲覧パスワード必須本体交流は、本体での邂逅、同棲など、本体で実際に会う等の範囲とし、遠隔でゲームや同時視聴等は含まないものとします。(※ただし公開時は配慮などお願いします)</li>
                    
                    <li><strong>ジャンル間の交流</strong><br>
                    ジャンルを跨いでの交流は可能です。ただし、交流不可の場合は概要にその旨を明記してください。</li>
                    
                    <li><strong>所持冊数制限</strong><br>
                    原則、<span class="highlight">1ジャンルにつき、お一人様1冊まで</span>です。<br>
                    ただし、交換日記や複数人で作成する場合に限り、1ジャンルにつき2冊まで参加可能です。<br>
                    例）個人1冊、複数2冊で、合計3冊所持</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <button class="accordion-header">機能・仕様について</button>
        <div class="accordion-content">
            <div class="accordion-inner">
                <ul class="rule-list">
                    <li><strong>作成上限</strong><br>
                    1ジャンルにつき最大500冊まで作成可能です。1冊の日記内の記事数（記事数）に上限はありません。<br>
                    <span style="font-size:0.9em; color:#aaa;">※サーバー負荷対策のため、日記作成は全体で1時間に50冊までの制限があります。</span></li>
                    
                    <li><strong>自動削除</strong><br>
                    最終書き込み（または作成日）から<span class="highlight">60日以上経過した日記は自動的に削除</span>されます。</li>

                    <li><strong>CSS・HTMLの利用</strong><br>
                    記事内および設定にてCSS、HTMLタグが利用可能です。<br>
                    <span class="highlight">※ただし、外部サイトへのリンク（cirmg.comを除く）および画像ファイルの直接表示はシステムで制限されています。</span>（概要のCSSは外部リンクを許可しています）</li>
                    
                    <li><strong>アンカー機能</strong><br>
                    記事内で以下の記述をすると自動でリンクになります。<br>
                    <span class="command-box">&gt;&gt;数字</span> ：その日記内の指定き記事へリンク<br>
                    <span class="command-box">&gt;&gt;&gt;数字</span> ：同ジャンル内の日記（日記ID）へリンク(日記名でリンクされます)<br>
                    <span class="command-box">[color&gt;&gt;&gt;数字]</span> ：別ジャンルの日記（日記ID）へリンク(日記名でリンクされます)<br>
                    対応：[]を含む<br>
                    ANIME&MANGA：[black&gt;&gt;&gt;数字]<br>GAME：[white&gt;&gt;&gt;数字]<br>STREAMER&VTUBER：[red&gt;&gt;&gt;数字]<br>OTHER：[blue&gt;&gt;&gt;数字]</li>
                    
                    <li><strong>表紙（概要）公開機能</strong><br>
                    パスワードを設定した日記でも、設定により「表紙（概要）」だけを一覧に公開することができます。<br>
                    一覧ページでは、表紙のみ閲覧可能な日記に <span class="icon-book">📖</span> マークがつきます。<br>
                    （鍵の配布や、注意書きの掲示にご利用ください）</li>
                    
                    <li><strong>その他の機能</strong><br>
                    ・一覧画面での絞り込み（鍵無しのみ／表紙公開のみ）<br>
                    ・日記管理画面（CSS編集、記事の修正・削除、パスワード変更、ログの一括ダウンロード）<br>
                    ・全板横断検索機能<br>
                    ・sage機能、プレビュー機能<br>
                    ・現在の作成パスワードは make です　※全ジャンル共通</li>
                    
                    <li><strong>検索避けについて</strong><br>
                    全ページに対して検索エンジン回避（noindex）設定済みです。ご自身での検索避け対策は不要です。</li>
                </ul>
            </div>
        </div>
    </div>


    <div class="accordion-item">
        <button class="accordion-header" style="color:#ff6b6b;">禁止事項</button>
        <div class="accordion-content">
            <div class="accordion-inner">
                <ul class="rule-list">
                    <li>誹謗中傷、荒らし行為、他者を不快にさせる行為。</li>
                    <li>日本国法令または公序良俗に反するおそれのある投稿。</li>
                    <li><strong>画像、外部URLの貼り付け</strong><br>
                    記事本文において、画像ファイル（jpg, png等）の直リンクおよび表示は禁止です。<br>
                    外部URLは「cirmg.com」のみ許可されています。<br>
                    <span style="font-size:0.9em; color:#aaa;">※日記設定のCSS欄においては、Webフォントやcssなどの外部URL指定が可能です（ただし画像の記述は禁止）。</span></li>
                    <li>個人情報の記載（特定可能な情報の書き込み）。</li>
                    <li>ジャンル違いの日記作成（適切な板をご利用ください）。</li>
                    <li><strong>著作権侵害</strong><br>
                    歌詞などの完全転載は禁止です（一節程度の引用なら可）。</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <button class="accordion-header">運営・免責事項</button>
        <div class="accordion-content">
            <div class="accordion-inner">
                <ul class="rule-list">
                    <li><strong>投稿の削除</strong><br>
                    管理者は、本ルールに反していると認められる投稿については、利用者の同意を得ることなしに削除することができるものとします。</li>
                    <li><strong>運営について</strong><br>
                    管理者個人で運営している為、ご利用は自己責任にてお願いいたします。<br>
                    基本的に管理者が利用者へサイト名を冠し個人的に連絡を取ることは有りません。<br>
                    利用者に予告なく機能の追加及び削除を行うことがあります。</li>
                    <li><strong>免責事項</strong><br>
                    利用者が当該日記の利用により第三者の権利を侵害し、または第三者に対して損害を与えたことに関連して生じた全ての苦情や請求について、管理者は損害賠償その他の責任を負いません。<br>
                    管理者は、利用者による日記への投稿内容などを削除し、または保存しなかったこと（システムトラブルによるデータ消失含む）について一切責任を負わず、その理由説明義務を負いません。</li>
                    <li><strong>入室パスワード</strong>：yes</li>
                </ul>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px; padding: 0 10px; font-size: 0.9em; color: #aaa; text-align: right; line-height: 1.6;">
        <p style="margin-bottom: 5px;">
            本規約は当サイト「MOGD.」（mogd.cirmg.com）内でのみ効力を有するものとします。<br>
            また、管理者は利用者に予告なく本規約の改定を行えるものとします。<br>
            MOGD.
        </p>
        <p style="margin-bottom: 0;">
            制定日：2026.1.7<br>
            本体交流の範囲追記  改定日：2026.1.12
        </p>
    </div>

    <center><div class="text-center" style="margin-top:30px; margin-bottom:30px;">
        <a href="diary/" class="btn" style="padding:15px 40px;">同意してログイン画面へ</a>
    </div></center>
<center><a href="https://r.alicex.jp/HAPINARI/in.php?eid=mogd&guid=ON">はぴなりランキング</a></center>
   <div class="footer-link">(c) <?= date('Y') ?> mogd.</div>
</div>

<script>
    document.querySelectorAll('.accordion-header').forEach(button => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            button.classList.toggle('active');
            if (button.classList.contains('active')) {
                content.style.maxHeight = content.scrollHeight + "px";
            } else {
                content.style.maxHeight = "0";
            }
        });
    });
</script>

</body>
</html>