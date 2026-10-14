// Статистика: одна подсказка для всех столбцов и полос — при наведении и при фокусе с клавиатуры.
(() => {
    const tip = document.querySelector('[data-chart-tip]');
    if (!tip) return;

    const show = (mark) => {
        tip.textContent = mark.dataset.tip;
        tip.hidden = false;
        const box = mark.getBoundingClientRect();
        const width = tip.offsetWidth;
        const left = Math.min(Math.max(8, box.left + box.width / 2 - width / 2), window.innerWidth - width - 8);
        tip.style.left = `${left}px`;
        tip.style.top = `${Math.max(8, box.top - tip.offsetHeight - 8)}px`;
    };

    const hide = () => {
        tip.hidden = true;
    };

    document.querySelectorAll('[data-tip]').forEach((mark) => {
        mark.addEventListener('pointerenter', () => show(mark));
        mark.addEventListener('pointerleave', hide);
        mark.addEventListener('focus', () => show(mark));
        mark.addEventListener('blur', hide);
    });

    window.addEventListener('scroll', hide, { passive: true });
})();
