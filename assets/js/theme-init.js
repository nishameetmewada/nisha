/* Apply the saved appearance before styles load, without sending a preference to a server. */
(() => {
  'use strict';
  let preference = null;
  try {
    const saved = localStorage.getItem('nisha:theme');
    if (saved === 'light' || saved === 'dark') preference = saved;
  } catch (_) { /* Appearance still works when browser storage is unavailable. */ }
  const dark = preference ? preference === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  document.documentElement.dataset.themePreference = preference || 'system';
})();
