(() => {
  'use strict';
  const root = document.documentElement;
  const toggle = document.querySelector('.theme-toggle');
  if (!toggle) return;
  const system = window.matchMedia('(prefers-color-scheme: dark)');
  const apply = (theme, preference) => {
    const dark = theme === 'dark';
    root.dataset.theme = dark ? 'dark' : 'light';
    root.dataset.themePreference = preference;
    toggle.setAttribute('aria-checked', String(dark));
    toggle.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
    toggle.querySelector('.theme-toggle-label').textContent = dark ? 'Dark' : 'Light';
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#151f1a' : '#f8f7f2');
  };
  apply(root.dataset.theme || (system.matches ? 'dark' : 'light'), root.dataset.themePreference || 'system');
  toggle.hidden = false;
  toggle.addEventListener('click', () => {
    const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('nisha:theme', theme); } catch (_) { /* Keep the selection for this page. */ }
    apply(theme, theme);
  });
  system.addEventListener('change', event => {
    if (root.dataset.themePreference === 'system') apply(event.matches ? 'dark' : 'light', 'system');
  });
  window.addEventListener('storage', event => {
    if (event.key !== 'nisha:theme' && event.key !== null) return;
    const saved = event.newValue;
    const preference = saved === 'light' || saved === 'dark' ? saved : 'system';
    apply(preference === 'system' ? (system.matches ? 'dark' : 'light') : preference, preference);
  });
})();
