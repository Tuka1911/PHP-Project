<?php $categoryIds = array_column($categories, 'id', 'slug'); ?>
<section class="home-hero container">
  <div class="hero-copy">
    <div class="eyebrow"><span class="green-dot"></span> Больше, чем просто планы</div>
    <h1>Твой город.<br>Твои люди.<br><span class="underline-accent">Твои события.</span></h1>
    <p>Открой что-то новое, найди своих<br class="desktop-break"> и наполни календарь впечатлениями.</p>
    <div class="hero-actions"><a class="button button-dark" href="<?= e(url('events')) ?>">Найти своё событие <span>↗</span></a><a class="text-link" href="<?= e(url($user ? 'edit' : 'register')) ?>">Я организатор <span>→</span></a></div>
    <div class="hero-community"><div class="avatar-stack"><span>А</span><span>М</span><span>Д</span><span>+</span></div><p><strong>Встречаемся в Jinal</strong><br>Новые знакомства. Общие интересы.</p></div>
  </div>
  <div class="hero-visual"><img src="assets/hero.svg" alt="Графическая афиша: горы, музыка и люди, объединённые встречей" width="680" height="680"><div class="floating-note"><span>✳</span><div>Не откладывай жизнь.<br><strong>Давай соберёмся!</strong></div></div></div>
</section>
<div class="ticker" aria-hidden="true"><div>НАХОДИ СВОИХ <span>✳</span> ПРОБУЙ НОВОЕ <span>✳</span> БУДЬ В МОМЕНТЕ <span>✳</span> НАХОДИ СВОИХ <span>✳</span> ПРОБУЙ НОВОЕ <span>✳</span> БУДЬ В МОМЕНТЕ <span>✳</span></div></div>
<section class="section container">
  <div class="section-head"><div><div class="eyebrow">Повод выйти из дома</div><h2>Планы, которые радуют<span class="lime-text">↗</span></h2></div><a class="text-link" href="<?= e(url('events')) ?>">Вся афиша <span>→</span></a></div>
  <div class="quick-categories" aria-label="Категории мероприятий"><a class="chip selected" href="<?= e(url('events')) ?>">✳ Всё интересное</a><a class="chip" href="<?= e(url('events', ['category' => $categoryIds['music'] ?? ''])) ?>">♫ Музыка</a><a class="chip" href="<?= e(url('events', ['category' => $categoryIds['workshop'] ?? ''])) ?>">✦ Мастер-классы</a><a class="chip" href="<?= e(url('events', ['category' => $categoryIds['sport'] ?? ''])) ?>">↗ Спорт</a><a class="chip" href="<?= e(url('events', ['category' => $categoryIds['art'] ?? ''])) ?>">◉ Искусство</a><a class="chip" href="<?= e(url('events', ['category' => $categoryIds['tech'] ?? ''])) ?>">⌘ Технологии</a></div>
  <div class="event-grid"><?php foreach ($featured as $item) { require __DIR__ . '/card.php'; } ?></div>
  <?php if (empty($featured)): ?><div class="empty-state"><span>✳</span><h3>Город готов к новым событиям</h3><p>Создай первое мероприятие и собери единомышленников.</p><a class="button button-dark" href="<?= e(url('edit')) ?>">Создать событие ↗</a></div><?php endif; ?>
</section>
<section class="container organizer-banner"><div><div class="eyebrow">Твоя идея — чьи-то лучшие выходные</div><h2>Есть повод собраться?<br>Расскажи о нём.</h2><p>Создавай события, управляй местами и встречай свою аудиторию.</p><a class="button button-dark" href="<?= e(url($user ? 'edit' : 'register')) ?>">Создать событие <span>↗</span></a></div><div class="banner-art" aria-hidden="true"><span>✳</span><div class="banner-sticker">GOOD PEOPLE<br>GREAT PLANS</div></div></section>
<section class="container how-section"><div><div class="eyebrow">Без лишних сложностей</div><h2>От «что делать?»<br>до «уже иду».</h2></div><div class="how-step"><span>01 /</span><h3>Найди свой интерес</h3><p>Выбери город, категорию и событие под настроение.</p></div><div class="how-step"><span>02 /</span><h3>Забронируй место</h3><p>Одна регистрация — и все твои планы в личном кабинете.</p></div><div class="how-step"><span>03 /</span><h3>Приходи и знакомься</h3><p>Сохрани дату. Осталось только быть в моменте.</p></div></section>
