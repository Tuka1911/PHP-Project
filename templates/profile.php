<section class="container page-intro">
  <div class="eyebrow">Давай поближе</div><h1>Твой профиль<span class="lime-text">✳</span></h1>
  <p>Немного о человеке, который стоит за хорошими планами.</p>
</section>
<section class="container profile-layout">
  <aside class="profile-card">
    <span class="avatar avatar-large" translate="no"><?= e(mb_substr($user['name'],0,1)) ?></span>
    <h2 translate="no"><?= e($user['name']) ?></h2><p translate="no"><?= e($user['email']) ?></p>
    <span class="pill"><?= $user['role']==='admin' ? 'Администратор' : 'Участник Jinal' ?></span>
    <a class="text-link" href="<?= e(url('dashboard')) ?>">Мои события →</a>
    <form method="post" action="<?= e(url('home')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit" class="logout-button">Выйти из аккаунта ↗</button></form>
  </aside>
  <div class="profile-form-wrap"><h2>Личная информация</h2>
    <form method="post" action="<?= e(url('profile')) ?>" class="stack-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile_save">
      <label>Имя<input name="name" autocomplete="name" required minlength="2" maxlength="80" value="<?= e($old['name'] ?? $user['name']) ?>"></label>
      <label>Email<input type="email" value="<?= e($user['email']) ?>" disabled><small>Email используется для входа и не редактируется.</small></label>
      <label>О себе<textarea name="bio" maxlength="500" rows="5" placeholder="Что тебе интересно? Какие события любишь?" translate="no"><?= e($old['bio'] ?? $user['bio'] ?? '') ?></textarea><small>До 500 символов.</small></label>
      <button class="button button-dark" type="submit">Сохранить <span>↗</span></button>
    </form>
  </div>
</section>
