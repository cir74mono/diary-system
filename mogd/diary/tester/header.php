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
    <meta name="googlebot" content="noindex, nofollow">
    <title><?= isset($page_title) ? h($page_title) : 'Diary' ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body id="top">

<div class="hamburger" id="hamburger">
    <span></span>
    <span></span>
    <span></span>
</div>

<div class="menu-overlay" id="menuOverlay">
    <nav class="menu-nav">
        <div style="margin-bottom: 20px;">
        </div>
        <a href="../rule.php">RULE</a>
        <a href="create.php">TEST CREATE</a>
        <a href="../index.php" style="margin-top: 20px; font-size: 1rem; color: #a0a0a0; border-bottom:none;">INDEX</a>
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
});
</script>
