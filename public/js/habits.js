// Привычки: отметки без перезагрузки и окно добавления / изменения привычки.
(() => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const plural = (n, one, few, many) => {
        const mod10 = n % 10;
        const mod100 = n % 100;
        const word = mod10 === 1 && mod100 !== 11 ? one
            : (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? few : many);
        return `${n} ${word}`;
    };

    // Клик по клетке дня: +1 (или переключение), обновляем клетку и серию в строке.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('[data-habit-check]')) return;
        event.preventDefault();

        const cell = form.querySelector('.habit-cell');
        const row = form.closest('[data-habit]');
        cell.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then(({ count, target, done, progress }) => {
                cell.classList.toggle('is-done', done);
                cell.setAttribute('aria-pressed', String(done));
                cell.style.setProperty('--fill', `${Math.min(100, Math.round((count / target) * 100))}%`);
                const counter = cell.querySelector('.habit-cell__count');
                if (counter) counter.textContent = count || '';
                cell.setAttribute('aria-label', cell.getAttribute('aria-label').replace(
                    /: .*$/,
                    target > 1 ? `: ${count} из ${target}` : `: ${done ? 'выполнено' : 'не выполнено'}`,
                ));

                row.querySelector('[data-streak] b').textContent = plural(progress.current, 'день', 'дня', 'дней');
                row.querySelector('[data-streak] small').textContent = `лучшая ${progress.best}`;
                row.querySelector('[data-rate]').textContent = progress.rate === null ? '—' : `${progress.rate}%`;
            })
            .catch(() => window.showToast('Не получилось отметить день'))
            .finally(() => {
                cell.disabled = false;
            });
    });

    // Окно привычки: одно для добавления и изменения.
    const dialog = document.getElementById('habit-dialog');
    const form = document.getElementById('habit-form');
    const method = form.querySelector('input[name="_method"]');
    const title = form.querySelector('[data-habit-title]');
    const submit = form.querySelector('[data-habit-submit]');
    const daysError = form.querySelector('[data-days-error]');
    const dayBoxes = [...form.querySelectorAll('input[name="weekdays[]"]')];

    const setDays = (days) => {
        dayBoxes.forEach((box) => {
            box.checked = days.includes(box.value);
        });
        daysError.hidden = true;
    };

    const setForm = (habit) => {
        form.action = habit ? habit.action : form.dataset.storeAction;
        method.disabled = !habit;
        form.elements.habit_id.value = habit ? habit.action.split('/').pop() : '';
        form.elements.name.value = habit ? habit.name : '';
        form.elements.target.value = habit ? habit.target : 1;
        const color = form.querySelector(`input[name="color"][value="${habit ? habit.color : 'green'}"]`);
        if (color) color.checked = true;
        setDays(habit ? habit.weekdays.split(',') : ['1', '2', '3', '4', '5', '6', '7']);
        title.textContent = habit ? 'Изменить привычку' : 'Новая привычка';
        submit.textContent = habit ? 'Сохранить' : 'Добавить привычку';
        form.querySelector('.alert')?.remove();
    };

    document.querySelectorAll('[data-add-habit]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm(null);
            dialog.showModal();
            form.elements.name.focus();
        });
    });

    document.querySelectorAll('[data-edit-habit]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm(button.dataset);
            dialog.showModal();
            form.elements.name.select();
        });
    });

    form.querySelectorAll('[data-weekdays-preset]').forEach((button) => {
        button.addEventListener('click', () => setDays(button.dataset.weekdaysPreset.split(',')));
    });

    form.addEventListener('submit', (event) => {
        if (!dayBoxes.some((box) => box.checked)) {
            event.preventDefault();
            daysError.hidden = false;
        }
    });

    dayBoxes.forEach((box) => box.addEventListener('change', () => {
        daysError.hidden = true;
    }));

    if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();

    // На узком экране таблица прокручивается вбок: сразу показываем сегодняшний день.
    const scroller = document.querySelector('.habits-scroll');
    const todayHead = document.querySelector('.habits__day.is-today');
    if (scroller && todayHead && scroller.scrollWidth > scroller.clientWidth) {
        const nameColumn = document.querySelector('.habits__corner').offsetWidth;
        const todayLeft = todayHead.getBoundingClientRect().left - scroller.getBoundingClientRect().left + scroller.scrollLeft;
        scroller.scrollLeft = todayLeft - nameColumn - todayHead.offsetWidth;
    }
})();
