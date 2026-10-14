// Общее для всех страниц: окна, подтверждения, всплывающие подсказки.
(() => {
    // Общее поведение окон: кнопки закрытия и клик по фону.
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-close]').forEach((button) =>
            button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    // Подтверждение перед необратимыми действиями.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    // Списки, которые сразу применяют выбор (например, «Наложить» в планере).
    document.querySelectorAll('select[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    // Быстрое переключение светлой и тёмной темы.
    const toggle = document.querySelector('[data-theme-toggle]');
    if (toggle) {
        toggle.addEventListener('submit', (event) => {
            event.preventDefault();
            const root = document.documentElement;
            const current = root.dataset.theme
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';

            root.dataset.theme = next;
            toggle.elements.theme.value = next;
            document.dispatchEvent(new CustomEvent('theme-change', { detail: next }));

            fetch(toggle.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(toggle),
            }).catch(() => toggle.submit());
        });
    }

    // Всплывающие подсказки. С кнопкой действия («Вернуть») висят дольше.
    const hideLater = (toast, duration) => {
        setTimeout(() => toast.classList.add('is-hidden'), duration);
        setTimeout(() => toast.remove(), duration + 400);
    };

    window.showToast = (message, { actionLabel, onAction } = {}) => {
        document.querySelectorAll('[data-toast]').forEach((old) => old.remove());

        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        toast.dataset.toast = '';

        const text = document.createElement('span');
        text.textContent = message;
        toast.append(text);

        if (actionLabel) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'toast__action';
            button.textContent = actionLabel;
            button.addEventListener('click', () => {
                toast.remove();
                onAction();
            });
            toast.append(button);
        }

        document.body.append(toast);
        hideLater(toast, actionLabel ? 6000 : 2200);
    };

    const toast = document.querySelector('[data-toast]');
    if (toast) {
        hideLater(toast, toast.hasAttribute('data-toast-long') ? 6000 : 2200);
    }
})();
