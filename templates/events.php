<section class="container page-intro"><div class="eyebrow">Найди повод встретиться</div><h1>Что у тебя в планах<span class="lime-text">?</span></h1><p>Музыка, новые навыки или маленькое приключение — выбирай своё.</p></section>
<section class="container catalog-section">
  <form class="filter-panel" action="index.php" method="get"><input type="hidden" name="page" value="events">
    <div class="search-field"><span aria-hidden="true">⌕</span><input aria-label="Поиск по названию и описанию" name="q" maxlength="100" value="<?= e($filters['q'] ?? '') ?>" placeholder="Событие, тема или настроение"></div>
    <label class="filter-field"><span>Категория</span><select name="category"><option value="">Все категории</option><?php foreach ($categories as $category): ?><option value="<?= e($category['id']) ?>" <?= (string)($filters['category'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
    <label class="filter-field"><span>Город</span><select name="city"><option value="">Все города</option><?php foreach ($cities as $city): ?><option value="<?= e($city) ?>" <?= ($filters['city'] ?? '') === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?></select></label>
    <label class="filter-field date-filter"><span>Начиная с</span><input type="date" name="date" value="<?= e($filters['date'] ?? '') ?>"></label>
    <button class="button button-dark" type="submit">Найти <span>↗</span></button>
    <div class="filter-lower"><a href="<?= e(url('events')) ?>">Сбросить фильтры</a><label>Сортировать: <select name="sort"><option value="date" <?= ($filters['sort'] ?? '') === 'date' ? 'selected' : '' ?>>Сначала ближайшие</option><option value="price" <?= ($filters['sort'] ?? '') === 'price' ? 'selected' : '' ?>>Сначала недорогие</option><option value="popular" <?= ($filters['sort'] ?? '') === 'popular' ? 'selected' : '' ?>>По популярности</option></select></label></div>
  </form>
  <div class="results-head"><h2>Афиша событий</h2><span><?= (int)$pagination['total'] ?> событий найдено</span></div>
  <div class="event-grid"><?php foreach ($events as $item) { require __DIR__ . '/card.php'; } ?></div>
  <?php if (empty($events)): ?><div class="empty-state"><span>⌕</span><h3>Пока ничего не нашлось</h3><p>Попробуй другую тему или убери один из фильтров.</p><a class="button button-dark" href="<?= e(url('events')) ?>">Показать все события</a></div><?php endif; ?>
  <?php if ($pagination['pages'] > 1): ?><nav class="pagination" aria-label="Страницы каталога"><?php for ($n = 1; $n <= $pagination['pages']; $n++): ?><a class="<?= $n === (int)$pagination['page'] ? 'current' : '' ?>" <?= $n === (int)$pagination['page'] ? 'aria-current="page"' : '' ?> href="<?= e(url('events', array_merge($filters, ['p' => $n]))) ?>"><?= $n ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
