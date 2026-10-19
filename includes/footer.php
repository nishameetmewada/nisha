<footer class="site-footer">
    <div class="container footer-main">
        <div class="footer-brand-block">
            <a href="index.php" class="brand"><svg viewBox="0 0 36 36" width="31" height="31" fill="none" aria-hidden="true"><path d="M18 30V16M18 21C7 21 5 14 6 6c9 0 14 5 12 15ZM18 16c0-7 5-11 12-10 1 7-4 13-12 13" fill="currentColor"/></svg><span>NISHA.</span></a>
            <p>A little practice.<br>A world of possibilities.</p>
        </div>
        <div class="footer-links"><span>YOUR NEXT CHAPTER</span><a href="exams.php">Full mock exams</a><a href="practice.php?exam=ielts">IELTS practice</a><a href="practice.php?exam=pte">PTE practice</a><a href="dashboard.php">Your progress</a></div>
        <div class="footer-links"><span>FIND YOUR WAY</span><a href="index.php#why-nisha">Why NISHA</a><a href="index.php#how-it-works">How it works</a><a href="index.php#faq">Common questions</a></div>
        <div class="footer-note"><?= icon('heart', '', 24) ?><p>Made for big dreams<br>and everyday progress.</p><a href="register.php">Start your story <?= icon('arrow', '', 16) ?></a></div>
    </div>
    <div class="container footer-bottom"><p>© <?= date('Y') ?> NISHA. Keep moving forward.</p><p>Independent practice platform. Not affiliated with IELTS or Pearson PTE.</p></div>
</footer>
<?php foreach ($extraScripts ?? [] as $script): ?>
<script src="<?= e(nisha_asset_url($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
