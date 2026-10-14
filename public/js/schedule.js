// Страница расписания пар: одно окно для добавления и изменения пары.
(() => {
    const dialog = document.getElementById('lesson-dialog');
    const form = document.getElementById('lesson-form');
    const method = form.querySelector('input[name="_method"]');
    const title = form.querySelector('[data-lesson-title]');
    const submit = form.querySelector('[data-lesson-submit]');
    const fields = ['starts_at', 'ends_at', 'subject', 'kind', 'room', 'group_name'];
    const createDays = form.querySelector('[data-mode="create"]');
    const editDay = form.querySelector('[data-mode="edit"]');
    const daysError = form.querySelector('[data-days-error]');

    const texts = {
        create: ['Новая пара', 'Добавить пару'],
        edit: ['Изменить пару', 'Сохранить'],
        duplicate: ['Копия пары', 'Добавить копию'],
    };

    /**
     * mode: create — пустая форма с отмеченным днём; edit — изменение одной пары;
     * duplicate — новая пара с данными существующей, дни выбираются заново.
     */
    const setForm = (mode, lesson = null, weekday = null) => {
        const isEdit = mode === 'edit';
        form.action = isEdit ? lesson.action : form.dataset.storeAction;
        method.disabled = !isEdit;
        form.elements.lesson_id.value = isEdit ? lesson.id : '';

        createDays.hidden = isEdit;
        createDays.disabled = isEdit;
        editDay.hidden = !isEdit;
        form.elements.weekday.disabled = !isEdit;

        fields.forEach((name) => {
            const key = name.replace(/_(\w)/g, (_, c) => c.toUpperCase()); // starts_at → startsAt
            form.elements[name].value = lesson ? lesson[key] || '' : '';
        });

        if (isEdit) {
            form.elements.weekday.value = lesson.weekday;
        }
        form.querySelectorAll('input[name="weekdays[]"]').forEach((box) => {
            box.checked = mode === 'create' && box.value === String(weekday);
        });

        const weeks = form.querySelector(`input[name="weeks"][value="${lesson ? lesson.weeks : 'all'}"]`);
        if (weeks) weeks.checked = true;

        [title.textContent, submit.textContent] = texts[mode];
        daysError.hidden = true;
        form.querySelector('.alert')?.remove();
    };

    document.querySelectorAll('[data-add-lesson]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm('create', null, button.dataset.weekday);
            dialog.showModal();
            form.elements.starts_at.focus();
        });
    });

    document.querySelectorAll('[data-edit-lesson]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm('edit', button.dataset);
            dialog.showModal();
            form.elements.subject.select();
        });
    });

    document.querySelectorAll('[data-duplicate-lesson]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm('duplicate', button.dataset);
            dialog.showModal();
            form.querySelector('input[name="weekdays[]"]').focus();
        });
    });

    // Новая пара без единого дня не отправляется — подсказываем сразу.
    form.addEventListener('submit', (event) => {
        if (createDays.disabled) return;
        if (!form.querySelector('input[name="weekdays[]"]:checked')) {
            event.preventDefault();
            daysError.hidden = false;
        }
    });

    createDays.addEventListener('change', () => {
        daysError.hidden = true;
    });

    // Быстрый выбор уже использованного времени пары.
    form.querySelectorAll('[data-slot]').forEach((button) => {
        button.addEventListener('click', () => {
            const [start, end] = button.dataset.slot.split('–');
            form.elements.starts_at.value = start;
            form.elements.ends_at.value = end;
            form.elements.subject.focus();
        });
    });

    if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();
})();
