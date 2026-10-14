// Люди: одно окно для добавления и изменения человека.
(() => {
    const dialog = document.getElementById('person-dialog');
    const form = document.getElementById('person-form');
    const method = form.querySelector('input[name="_method"]');
    const title = form.querySelector('[data-person-title]');
    const submit = form.querySelector('[data-person-submit]');

    const setForm = (person) => {
        form.action = person ? person.action : form.dataset.storeAction;
        method.disabled = !person;
        form.elements.person_id.value = person ? person.action.split('/').pop() : '';
        form.elements.name.value = person ? person.name : '';
        const color = form.querySelector(`input[name="color"][value="${person ? person.color : 'violet'}"]`);
        if (color) color.checked = true;
        title.textContent = person ? 'Изменить' : 'Новый человек';
        submit.textContent = person ? 'Сохранить' : 'Добавить';
        form.querySelector('.alert')?.remove();
    };

    document.querySelectorAll('[data-add-person]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm(null);
            dialog.showModal();
            form.elements.name.focus();
        });
    });

    document.querySelectorAll('[data-edit-person]').forEach((button) => {
        button.addEventListener('click', () => {
            setForm(button.dataset);
            dialog.showModal();
            form.elements.name.select();
        });
    });

    if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();
})();
