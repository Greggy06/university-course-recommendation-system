(() => {
    const list = document.querySelector('#results-list');
    const addButton = document.querySelector('#add-result');

    if (!list || !addButton) {
        return;
    }

    let nextIndex = list.querySelectorAll('.result-row').length;

    addButton.addEventListener('click', () => {
        if (list.querySelectorAll('.result-row').length >= 20) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'result-row';
        row.innerHTML = `
            <label>
                Subject
                <input name="results[${nextIndex}][subject]" maxlength="100" autocomplete="off">
            </label>
            <label>
                Grade or result
                <input name="results[${nextIndex}][result]" maxlength="30" autocomplete="off">
            </label>
            <button type="button" class="remove-result" aria-label="Remove this result">Remove</button>
        `;
        list.appendChild(row);
        nextIndex += 1;
    });

    list.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('.remove-result')) {
            const rows = list.querySelectorAll('.result-row');
            if (rows.length > 1) {
                event.target.closest('.result-row').remove();
            }
        }
    });
})();
