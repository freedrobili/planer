(() => {
    const token = document.querySelector('meta[name="csrf-token"]').content;

    const send = (form) =>
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body: new FormData(form),
        }).then((res) => {
            if (!res.ok) throw new Error(res.statusText);
            return res.json();
        });

    const post = (url) =>
        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        }).then((res) => {
            if (!res.ok) throw new Error(res.statusText);
            return res.json();
        });

    // Перезагрузка после переноса: сохраняем прокрутку и показываем подсказку уже на новой странице.
    const PLACE_KEY = 'planner-place';
    const reloadKeepingPlace = (message) => {
        try {
            sessionStorage.setItem(PLACE_KEY, JSON.stringify({
                message,
                page: window.scrollY,
                schedule: document.querySelector('[data-schedule]')?.scrollTop ?? null,
                columns: [...document.querySelectorAll('.week-col__body')].map((col) => col.scrollTop),
            }));
        } catch {
            // без sessionStorage просто перезагружаем
        }
        location.reload();
    };

    let restoredPlace = null;
    try {
        restoredPlace = JSON.parse(sessionStorage.getItem(PLACE_KEY) || 'null');
        sessionStorage.removeItem(PLACE_KEY);
    } catch {
        restoredPlace = null;
    }

    const countText = (done, total) => (total ? `${done}/${total}` : '');
    const meterWidth = (done, total) => (total ? `${Math.round((done / total) * 100)}%` : '0%');

    // Режим «Неделя»: счётчик колонки дня и общий итог за неделю.
    const refreshWeek = (item) => {
        const column = item?.closest('[data-day]');
        if (column) {
            const total = column.querySelectorAll('.task').length;
            const done = column.querySelectorAll('.task.is-done').length;
            column.querySelector('[data-day-count]').textContent = countText(done, total);
            column.querySelector('[data-day-meter]').style.width = meterWidth(done, total);
        }

        const summary = document.querySelector('[data-week-progress]');
        const total = document.querySelectorAll('.week-grid .task').length;
        const done = document.querySelectorAll('.week-grid .task.is-done').length;
        const filterName = summary.dataset.filterName;
        if (total) {
            summary.textContent = `${filterName ? `«${filterName}»: готово` : 'За неделю готово'} ${done} из ${total}`;
        } else {
            summary.textContent = filterName
                ? `На этой неделе нет задач с тегом «${filterName}»`
                : 'На этой неделе пока ничего не запланировано';
        }
    };

    // Режим «День»: заголовок дня и выбранный день в полосе недели.
    const refreshDay = () => {
        const total = document.querySelectorAll('.sheet .task').length;
        const done = document.querySelectorAll('.sheet .task.is-done').length;

        const progress = document.querySelector('[data-progress]');
        const filterName = progress.dataset.filterName;
        if (filterName) {
            progress.textContent = total
                ? `«${filterName}»: готово ${done} из ${total}`
                : `Нет задач с тегом «${filterName}»`;
        } else {
            progress.textContent = total ? `Готово ${done} из ${total}` : 'Пока ничего не запланировано';
        }

        const day = document.querySelector('[data-selected-day]');
        if (day) {
            day.querySelector('.day__meter span').style.width = meterWidth(done, total);
            day.querySelector('.day__count').textContent = countText(done, total) || '\u00a0';
        }
    };

    const refreshProgress = (item) =>
        (document.querySelector('[data-week-progress]') ? refreshWeek(item) : refreshDay());

    // Отметка выполнения без перезагрузки страницы.
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (form.matches('.task__toggle')) {
            event.preventDefault();
            const item = form.closest('.task');
            const button = form.querySelector('.check');
            const done = !item.classList.contains('is-done');
            item.classList.toggle('is-done', done);
            button.setAttribute('aria-checked', String(done));
            refreshProgress(item);

            send(form).catch(() => {
                item.classList.toggle('is-done', !done);
                button.setAttribute('aria-checked', String(!done));
                refreshProgress(item);
            });
        }

        // Удаление без подтверждения: задача сразу исчезает, её можно вернуть из подсказки.
        if (form.matches('[data-delete]')) {
            event.preventDefault();
            const item = form.closest('.task');
            const column = item.closest('[data-day]');
            const list = item.parentElement;
            const next = item.nextElementSibling;
            const putBack = () => {
                list.insertBefore(item, next && next.parentElement === list ? next : null);
                refreshProgress(column || item);
            };

            item.remove();
            refreshProgress(column);

            send(form)
                .then(({ restore_url: restoreUrl }) => {
                    window.showToast('Задача удалена', {
                        actionLabel: 'Вернуть',
                        onAction: () => post(restoreUrl).then(() => {
                            putBack();
                            window.showToast('Задача возвращена');
                        }),
                    });
                })
                .catch(() => {
                    putBack();
                    window.showToast('Не получилось удалить задачу');
                });
        }

        // «На следующий день» и любой другой перенос — с перезагрузкой, чтобы задача встала на место.
        if (form.matches('[data-move-form]')) {
            event.preventDefault();
            send(form)
                .then(({ message }) => reloadKeepingPlace(message))
                .catch(() => form.submit());
        }
    });

    // Клик по названию задачи открывает её целиком.
    document.addEventListener('click', (event) => {
        const title = event.target.closest('[data-open-task]');
        title?.closest('.task')?.querySelector('[data-edit]')?.click();
    });

    // Перетаскивание: на час — сменить время, в «В течение дня» — убрать время,
    // на день недели (полоса или колонка) — сменить день.
    let dragged = null;
    let target = null;

    const dropTargetFor = (element) =>
        element.closest?.('[data-drop-hour], [data-drop-untimed], [data-drop-date], .week-col[data-day]');

    const setTarget = (next) => {
        if (target === next) return;
        target?.classList.remove('is-drop-target');
        target = next;
        target?.classList.add('is-drop-target');
    };

    document.addEventListener('dragstart', (event) => {
        const item = event.target.closest?.('.task[draggable="true"]');
        if (!item) return;
        dragged = item;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.dataset.task);
        document.body.classList.add('is-dragging');
        requestAnimationFrame(() => item.classList.add('is-dragged'));
    });

    document.addEventListener('dragend', () => {
        dragged?.classList.remove('is-dragged');
        dragged = null;
        setTarget(null);
        document.body.classList.remove('is-dragging');
    });

    document.addEventListener('dragover', (event) => {
        if (!dragged) return;
        const next = dropTargetFor(event.target);
        setTarget(next);
        if (next) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        }
    });

    document.addEventListener('drop', (event) => {
        const zone = dropTargetFor(event.target);
        if (!dragged || !zone) return;
        event.preventDefault();

        const { date, time, moveUrl } = dragged.dataset;
        const body = new FormData();
        body.append('_method', 'PATCH');
        let newDate = date;

        if (zone.dataset.dropHour !== undefined) {
            const minutes = time ? time.slice(3, 5) : '00';
            const newTime = `${String(zone.dataset.dropHour).padStart(2, '0')}:${minutes}`;
            if (newTime === time) return;
            body.append('time', newTime);
        } else if (zone.dataset.dropUntimed !== undefined) {
            if (!time) return;
            body.append('time', '');
        } else {
            newDate = zone.dataset.dropDate || zone.dataset.day;
            if (newDate === date) return;
        }

        body.append('date', newDate);
        fetch(moveUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            body,
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then(({ message }) => reloadKeepingPlace(message))
            .catch(() => window.showToast('Не получилось перенести задачу'));
    });

    // Клик по пустому часу — подставить время в форму.
    const titleInput = document.getElementById('composer-title');
    const timeInput = document.getElementById('composer-time');

    document.querySelectorAll('[data-add-at]').forEach((button) => {
        button.addEventListener('click', () => {
            timeInput.value = button.dataset.addAt;
            titleInput.focus();
            titleInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    // Редактирование задачи.
    const dialog = document.getElementById('edit-dialog');
    const editForm = document.getElementById('edit-form');

    // Чек-лист в окне задачи: пункты собираются в поля items[N][title|done] перед отправкой.
    const checklist = editForm.querySelector('[data-checklist]');
    const checklistInput = editForm.querySelector('[data-checklist-input]');
    const checklistCount = editForm.querySelector('[data-checklist-count]');
    const rowTemplate = document.getElementById('checklist-row');

    const renumberChecklist = () => {
        const rows = [...checklist.children];
        rows.forEach((row, index) => {
            row.querySelector('.checklist__title').name = `items[${index}][title]`;
            const box = row.querySelector('.checklist__done');
            box.name = `items[${index}][done]`;
            box.value = '1';
        });
        const done = rows.filter((row) => row.querySelector('.checklist__done').checked).length;
        checklistCount.textContent = rows.length ? `${done}/${rows.length}` : '';
    };

    const addChecklistRow = ({ title, done }) => {
        const row = rowTemplate.content.firstElementChild.cloneNode(true);
        row.querySelector('.checklist__title').value = title;
        row.querySelector('.checklist__done').checked = Boolean(done);
        checklist.append(row);
        renumberChecklist();
    };

    const addFromInput = () => {
        const title = checklistInput.value.trim();
        if (!title) return;
        addChecklistRow({ title, done: false });
        checklistInput.value = '';
        checklistInput.focus();
    };

    editForm.querySelector('[data-checklist-add]').addEventListener('click', addFromInput);
    checklistInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addFromInput();
        }
    });
    checklist.addEventListener('click', (event) => {
        if (event.target.closest('[data-checklist-remove]')) {
            event.target.closest('.checklist__row').remove();
            renumberChecklist();
        }
    });
    checklist.addEventListener('change', renumberChecklist);
    editForm.addEventListener('submit', () => {
        addFromInput();
        renumberChecklist();
    });

    document.querySelectorAll('[data-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const { action, title, description, date, time, endsAt, tags, items } = button.dataset;
            const selected = tags ? tags.split(',') : [];
            editForm.action = action;
            editForm.elements.title.value = title;
            editForm.elements.description.value = description || '';
            editForm.elements.date.value = date;
            editForm.elements.time.value = time || '';
            editForm.elements.ends_at.value = endsAt || '';
            editForm.querySelectorAll('input[name="tags[]"]').forEach((box) => {
                box.checked = selected.includes(box.value);
            });
            checklist.replaceChildren();
            JSON.parse(items || '[]').forEach(addChecklistRow);
            renumberChecklist();
            checklistInput.value = '';
            dialog.showModal();
            editForm.elements.title.select();
        });
    });

    // Подсказка быстрого ввода: показываем, как сервер поймёт строку.
    const composer = document.getElementById('composer');
    const preview = composer?.querySelector('[data-quick-preview]');
    let previewTimer;

    composer?.elements.title.addEventListener('input', () => {
        clearTimeout(previewTimer);
        const text = composer.elements.title.value.trim();
        if (!text) {
            preview.hidden = true;
            return;
        }
        previewTimer = setTimeout(() => {
            fetch(`${composer.dataset.parseUrl}?${new URLSearchParams({ text })}`, { headers: { Accept: 'application/json' } })
                .then((res) => res.json())
                .then(({ title, summary }) => {
                    preview.hidden = !summary;
                    if (summary) preview.textContent = `Распознано: ${summary}. Задача: «${title}»`;
                })
                .catch(() => {
                    preview.hidden = true;
                });
        }, 250);
    });

    // Управление тегами.
    const tagsDialog = document.getElementById('tags-dialog');
    const tagForm = document.getElementById('tag-form');
    const tagMethod = tagForm.querySelector('input[name="_method"]');
    const tagTitle = tagForm.querySelector('[data-tag-form-title]');
    const tagSubmit = tagForm.querySelector('[data-tag-submit]');
    const tagCancel = tagForm.querySelector('[data-cancel-edit]');

    const setTagForm = (tag) => {
        tagForm.action = tag ? tag.action : tagForm.dataset.storeAction;
        tagMethod.disabled = !tag;
        tagForm.elements.editing.value = tag ? tag.id : '';
        tagForm.elements.name.value = tag ? tag.name : '';
        const color = tagForm.querySelector(`input[name="color"][value="${tag ? tag.color : 'blue'}"]`);
        if (color) color.checked = true;
        tagTitle.textContent = tag ? 'Изменить тег' : 'Новый тег';
        tagSubmit.textContent = tag ? 'Сохранить' : 'Создать тег';
        tagCancel.hidden = !tag;
        tagForm.querySelector('.alert')?.remove();
    };

    document.querySelectorAll('[data-open-tags]').forEach((button) => {
        button.addEventListener('click', () => {
            setTagForm(null);
            tagsDialog.showModal();
            tagForm.elements.name.focus();
        });
    });

    document.querySelectorAll('[data-edit-tag]').forEach((button) => {
        button.addEventListener('click', () => {
            setTagForm(button.dataset);
            tagForm.elements.name.select();
        });
    });

    tagCancel.addEventListener('click', () => {
        setTagForm(null);
        tagForm.elements.name.focus();
    });

    if (tagsDialog.hasAttribute('data-open-on-load')) {
        // Подсказку переносим внутрь окна, иначе её закроет фон.
        const status = document.querySelector('[data-toast]');
        if (status) tagsDialog.append(status);
        tagsDialog.showModal();
        tagForm.elements.name.focus();
    }

    // Экспорт: HTML и SVG скачиваются обычной ссылкой, PNG браузер рисует из SVG сам.
    const exportDialog = document.getElementById('export-dialog');
    const exportForm = document.getElementById('export-form');
    const exportSubmit = exportForm.querySelector('[data-export-submit]');
    const exportError = exportForm.querySelector('[data-export-error]');

    document.querySelector('[data-open-export]')?.addEventListener('click', () => {
        exportError.hidden = true;
        exportDialog.showModal();
    });

    const saveFile = (blob, filename) => {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    };

    const svgToPng = (svgText) => new Promise((resolve, reject) => {
        const image = new Image();
        const source = URL.createObjectURL(new Blob([svgText], { type: 'image/svg+xml' }));
        image.onload = () => {
            const scale = 2; // чёткость на экранах с высокой плотностью
            const canvas = document.createElement('canvas');
            canvas.width = image.width * scale;
            canvas.height = image.height * scale;
            const context = canvas.getContext('2d');
            context.scale(scale, scale);
            context.drawImage(image, 0, 0);
            URL.revokeObjectURL(source);
            canvas.toBlob((png) => (png ? resolve(png) : reject(new Error('PNG'))), 'image/png');
        };
        image.onerror = reject;
        image.src = source;
    });

    exportForm.addEventListener('submit', async (event) => {
        const data = new FormData(exportForm);
        exportError.hidden = true;

        if (data.get('format') !== 'png') {
            setTimeout(() => exportDialog.close(), 0);
            return;
        }

        event.preventDefault();
        data.set('format', 'svg');
        const label = exportSubmit.textContent;
        exportSubmit.disabled = true;
        exportSubmit.textContent = 'Готовлю…';

        try {
            const response = await fetch(`${exportForm.action}?${new URLSearchParams(data)}`);
            if (!response.ok) throw new Error(response.statusText);
            const disposition = response.headers.get('Content-Disposition') || '';
            const filename = (disposition.match(/filename="?([^";]+)"?/)?.[1] || 'planner.svg').replace(/\.svg$/, '.png');
            saveFile(await svgToPng(await response.text()), filename);
            exportDialog.close();
        } catch {
            exportError.hidden = false;
        } finally {
            exportSubmit.disabled = false;
            exportSubmit.textContent = label;
        }
    });

    // Линия текущего времени.
    const timeline = document.querySelector('.timeline[data-today]');
    const nowLine = document.querySelector('[data-now]');

    const placeNow = () => {
        if (!timeline || !nowLine) return;
        const now = new Date();
        const row = timeline.querySelector(`.hour[data-hour="${now.getHours()}"]`);
        if (!row) {
            nowLine.hidden = true;
            return;
        }
        nowLine.hidden = false;
        nowLine.style.top = `${row.offsetTop + (row.offsetHeight * now.getMinutes()) / 60}px`;
        nowLine.querySelector('span').textContent =
            `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

        timeline.querySelectorAll('.hour').forEach((hour) => {
            hour.classList.toggle('is-past', Number(hour.dataset.hour) < now.getHours());
        });
    };

    placeNow();

    // На широком экране расписание прокручивается внутри: сразу показываем нужные часы.
    const schedule = document.querySelector('[data-schedule]');
    if (restoredPlace) {
        window.scrollTo(0, restoredPlace.page);
        if (schedule && restoredPlace.schedule !== null) schedule.scrollTop = restoredPlace.schedule;
        document.querySelectorAll('.week-col__body').forEach((col, index) => {
            col.scrollTop = restoredPlace.columns?.[index] ?? 0;
        });
        if (restoredPlace.message) window.showToast(restoredPlace.message);
    } else if (schedule && schedule.scrollHeight > schedule.clientHeight) {
        const target = timeline
            ? timeline.querySelector(`.hour[data-hour="${new Date().getHours() - 1}"]`)
            : schedule.querySelector('.hour .tasks')?.closest('.hour');
        if (target) schedule.scrollTop = target.offsetTop - 8;
    }
    setInterval(placeNow, 30_000);
    window.addEventListener('resize', placeNow);

    // Если есть ошибка ввода — сразу вернуть фокус в форму.
    if (titleInput && document.querySelector('.sheet .alert')) titleInput.focus();
})();
