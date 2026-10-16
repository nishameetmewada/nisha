'use strict';
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('.main-nav');
if (menuButton && navigation) {
  menuButton.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') !== 'true';
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    navigation.classList.toggle('is-open', open);
  });
  navigation.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    menuButton.setAttribute('aria-expanded', 'false');
    navigation.classList.remove('is-open');
  }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      menuButton.setAttribute('aria-expanded', 'false');
      navigation.classList.remove('is-open');
    }
  });
}
document.querySelectorAll('[data-exam-tab]').forEach(tab => {
  tab.addEventListener('click', () => {
    const exam = tab.dataset.examTab;
    document.querySelectorAll('[data-exam-tab]').forEach(other => {
      other.classList.toggle('is-active', other === tab);
      other.setAttribute('aria-pressed', String(other === tab));
    });
    document.querySelectorAll('[data-practice-link]').forEach(link => {
      link.href = 'practice.php?exam=' + exam + '&skill=' + link.dataset.practiceLink;
    });
    const note = document.querySelector('[data-exam-note]');
    if (note) note.textContent = exam === 'ielts' ? 'A little practice in every skill. A lot more confidence on test day.' : 'Get comfortable with PTE-style tasks, one focused session at a time.';
  });
});
