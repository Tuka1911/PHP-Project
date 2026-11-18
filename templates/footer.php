</main>
<footer class="site-footer">
  <div class="container footer-top">
    <div><a class="logo" href="<?= e(url('home')) ?>">jinal<span class="logo-star">✳</span></a><p>Хорошие истории начинаются<br>со встречи.</p></div>
    <div class="footer-links"><a href="<?= e(url('events')) ?>">Найти событие ↗</a><a href="<?= e(url($user ? 'edit' : 'register')) ?>">Стать организатором ↗</a><a href="<?= e(url('about')) ?>">О проекте ↗</a></div>
    <p class="footer-note">Сделано, чтобы собираться.<br>Учебный проект · PHP + MySQL<br>Демонстрационные мероприятия.</p>
  </div>
  <div class="container footer-bottom"><span>© <?= date('Y') ?> Jinal</span><span>Алматы, Астана и твой следующий план <span class="green-dot"></span></span></div>
</footer>
</body>
</html>
