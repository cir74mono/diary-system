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
    
    <script>
        (function() {

            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, noimageindex">
    <meta name="googlebot" content="noindex, nofollow">
    <title><?= isset($page_title) ? h($page_title) : 'Diary' ?></title>
    
    <link rel="stylesheet" href="../common/css/style.css">
</head>
<body id="top">

<div class="hamburger" id="hamburger">
    <span></span>
    <span></span>
    <span></span>
</div>

<div class="menu-overlay" id="menuOverlay">
    <nav class="menu-nav">
        <button id="themeToggle" class="theme-toggle-btn">
            <span id="themeIcon">☀</span> <span>MODE CHANGE</span>
        </button>
        <div style="margin-bottom: 20px; margin-top:10px;">
            <form action="search.php" method="get" style="display:flex; justify-content:center; gap:5px;">
                <input type="text" name="q" placeholder="キーワード検索" required style="font-size:1rem; width:200px;">
                <button type="submit" class="btn" style="padding: 10px 15px;">Go</button>
            </form>
        </div>
        
        <a href="../rule.php">RULE</a>
        <a href="create.php">CREATE</a>
        <a href="index.php">RETURN</a>
        <a href="search.php">SEARCH</a>     
        <a href="../black/">ANIME&MANGA</a>
        <a href="../white/">GAME</a>
        <a href="../red/">STREAMER&VTUBER</a>
        <a href="../blue/">OTHER</a>
        <a href="../index.php" style="margin-top: 20px; font-size: 1rem; color: #888; border-bottom:none;">INDEX</a>
    </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const btn = document.getElementById('hamburger');
    const overlay = document.getElementById('menuOverlay');
    const nav = document.querySelector('.menu-nav');
    
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (overlay.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    overlay.addEventListener('click', (e) => {
        if(e.target === overlay) {
            closeMenu();
        }
    });

    function openMenu() {
        overlay.classList.remove('closing');
        overlay.classList.add('open');
        btn.classList.add('active');
    }

    function closeMenu() {
        overlay.classList.add('closing');
        btn.classList.remove('active');
        setTimeout(() => {
            overlay.classList.remove('open');
            overlay.classList.remove('closing');
        }, 400); 
    }

    nav.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    const toggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;

    function updateIcon() {
        const isLight = html.getAttribute('data-theme') === 'light';
        themeIcon.textContent = isLight ? '🌙' : '☀'; 
    }
    updateIcon();

    toggleBtn.addEventListener('click', () => {
        if (html.getAttribute('data-theme') === 'light') {
            html.removeAttribute('data-theme');
            localStorage.setItem('theme', 'dark');
        } else {
            html.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
        }
        updateIcon();
    });
});
</script>