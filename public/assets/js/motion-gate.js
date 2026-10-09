/*
 * Loaded synchronously in <head> on public pages only. Adds `motion-ok` so
 * reveal animations can start from hidden without a flash of content. If the
 * visitor prefers reduced motion nothing is hidden, and a failsafe reveals
 * everything if the animation library does not start within 2.5 seconds.
 */
(function () {
  var root = document.documentElement;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }
  root.classList.add('motion-ok');
  window.setTimeout(function () {
    if (!root.classList.contains('motion-started')) {
      root.classList.remove('motion-ok');
    }
  }, 2500);
})();
