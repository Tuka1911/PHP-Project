<!doctype html>
<html lang="<?= e(active_lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#d5f55b">
  <meta name="description" content="Jinal — находи события, встречай своих людей и создавай поводы собраться.">
  <title><?= e($title ?? 'Jinal — встречаемся в городе') ?> · Jinal</title>
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<header class="site-header">
  <div class="container nav-wrap">
    <a class="logo" href="<?= e(url('home')) ?>" aria-label="Jinal — главная">jinal<span class="logo-star">✳</span></a>
    <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="navigation">☰</button>
    <nav id="navigation" class="navigation" aria-label="Основная навигация">
      <a class="<?= $page === 'events' ? 'active' : '' ?>" href="<?= e(url('events')) ?>">Афиша</a>
      <a class="<?= $page === 'about' ? 'active' : '' ?>" href="<?= e(url('about')) ?>">О проекте</a>
      <?php if ($user): ?>
      <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard')) ?>">Мои события</a>
      <?php endif; ?>
    </nav>
    <div class="nav-actions">
      <?php $languageParams = array_filter($_GET, 'is_scalar'); ?>
      <nav class="language-switch" aria-label="Язык интерфейса" translate="no"><?php foreach (['ru' => 'RU', 'kk' => 'ҚАЗ', 'en' => 'EN'] as $languageCode => $languageLabel): ?><a lang="<?= e($languageCode) ?>" hreflang="<?= e($languageCode) ?>" class="<?= active_lang() === $languageCode ? 'current' : '' ?>" <?= active_lang() === $languageCode ? 'aria-current="true"' : '' ?> href="<?= e(url($page, array_merge($languageParams, ['lang' => $languageCode]))) ?>"><?= e($languageLabel) ?></a><?php endforeach; ?></nav>
      <?php if ($user): ?>
      <a class="profile-link" href="<?= e(url('profile')) ?>"><span class="avatar small"><?= e(mb_substr($user['name'], 0, 1)) ?></span><span><?= e(explode(' ', $user['name'])[0]) ?></span></a>
      <a class="button button-dark button-small" href="<?= e(url('edit')) ?>">Создать событие <span>↗</span></a>
      <?php else: ?>
      <a class="login-link" href="<?= e(url('login')) ?>">Войти</a>
      <a class="button button-dark button-small" href="<?= e(url('register')) ?>">Присоединиться <span>↗</span></a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main id="main">
<?php if (!empty($flash)): ?>
<div class="container"><div class="notice <?= ($flash['type'] ?? '') === 'error' ? 'notice-error' : 'notice-success' ?>" role="status"><?= e($flash['message']) ?></div></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="container"><div class="notice notice-error" role="alert"><strong>Проверь данные</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>
