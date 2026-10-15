// Theme behaviour: rail toggle on narrow containers. Everything else is CSS or core blocks.
const rail = document.getElementById('joo-rail');
const button = document.querySelector('.joo-rail-btn');
if (rail && button) {
  button.hidden = false;
  const set = (open) => {
    rail.dataset.open = open ? 'true' : 'false';
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  set(false);
  button.addEventListener('click', () => set(rail.dataset.open !== 'true'));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && rail.dataset.open === 'true') {
      set(false);
      button.focus();
    }
  });
}
