document.querySelector('.menu-toggle')?.addEventListener('click', function () {
  const open = this.getAttribute('aria-expanded') !== 'true';
  this.setAttribute('aria-expanded', String(open));
  document.querySelector('.site-header')?.classList.toggle('menu-open', open);
});
document.querySelectorAll('[data-toggle-password]').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.togglePassword);
    if (!input) return;
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-label', visible ? 'Скрыть пароль' : 'Показать пароль');
    button.setAttribute('aria-pressed', String(visible));
  });
});
document.querySelectorAll('[data-confirm]').forEach(button => {
  button.addEventListener('click', event => {
    if (!window.confirm(button.dataset.confirm)) event.preventDefault();
  });
});
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', () => {
    if (form.method.toLowerCase() !== 'post') return;
    const button = form.querySelector('button[type="submit"]');
    if (button) { button.setAttribute('aria-busy', 'true'); }
  });
});
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const reveals = document.querySelectorAll('.event-card, .how-step, .organizer-banner, .stat-card, .requirements-list article');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-revealed');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08, rootMargin: '0px 0px 20px 0px' });
  reveals.forEach((element, index) => {
    element.classList.add('reveal-ready', `reveal-delay-${index % 3}`);
    observer.observe(element);
  });
}
