<article class="event-card">
  <a class="card-art" href="<?= e(url('event', ['id' => $item['id']])) ?>" aria-label="<?= e($item['title']) ?>">
    <img src="<?= e(poster($item)) ?>" alt="" loading="lazy" width="720" height="520">
    <span class="card-category"><?= e($item['category_name']) ?></span>
    <span class="card-arrow" aria-hidden="true">↗</span>
  </a>
  <div class="card-body">
    <div class="card-meta"><span><?= e(date_label($item['starts_at'])) ?></span><span><?= e($item['city']) ?></span></div>
    <h3 translate="no"><a href="<?= e(url('event', ['id' => $item['id']])) ?>"><?= e($item['title']) ?></a></h3>
    <div class="card-bottom"><span class="card-venue" translate="no"><?= e($item['venue']) ?></span><strong><?= e(money($item['price'])) ?></strong></div>
  </div>
</article>
