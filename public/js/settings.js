// Страница «Оформление»: живой предпросмотр и отметка несохранённых изменений.
(() => {
    const form = document.getElementById('settings-form');
    const style = document.getElementById('appearance-style');
    const fontsLink = document.getElementById('fonts-link');
    const dirty = form.querySelector('[data-dirty]');
    const discard = form.querySelector('[data-discard]');
    const token = form.elements._token.value;

    const snapshot = () => {
        const data = new FormData(form);
        data.delete('_token');
        data.delete('_method');
        return new URLSearchParams(data).toString();
    };

    let saved = snapshot();
    let timer;

    const preview = () => {
        const body = new FormData(form);
        body.delete('_method');

        fetch(form.dataset.previewUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body,
        })
            .then((res) => res.json())
            .then(({ css, fontsUrl, theme }) => {
                style.textContent = css;
                if (fontsUrl) fontsLink.href = fontsUrl;
                else fontsLink.removeAttribute('href');
                if (theme) document.documentElement.dataset.theme = theme;
                else delete document.documentElement.dataset.theme;
            })
            .catch(() => {});
    };

    const changed = () => {
        const isDirty = snapshot() !== saved;
        dirty.hidden = !isDirty;
        discard.hidden = !isDirty;
        clearTimeout(timer);
        timer = setTimeout(preview, 120);
    };

    // Свой цвет: палитра браузера управляет скрытой радиокнопкой рядом.
    form.querySelectorAll('[data-custom-color]').forEach((picker) => {
        const swatch = picker.closest('.swatch');
        const radio = swatch.querySelector('[data-custom-radio]');
        picker.addEventListener('input', () => {
            radio.value = picker.value;
            radio.checked = true;
            swatch.style.setProperty('--tag', picker.value);
            changed();
        });
    });

    form.addEventListener('change', changed);

    // Переключатель темы в шапке уже сохранил выбор — синхронизируем форму.
    document.addEventListener('theme-change', ({ detail }) => {
        const wasDirty = snapshot() !== saved;
        const radio = form.querySelector(`input[name="theme"][value="${detail}"]`);
        if (radio) radio.checked = true;
        if (!wasDirty) saved = snapshot();
    });

    form.addEventListener('submit', () => {
        saved = snapshot();
    });

    window.addEventListener('beforeunload', (event) => {
        if (snapshot() !== saved) event.preventDefault();
    });
})();
