// Client-side narrowing of long lists (input[data-list-filter="<table id>"]); purely a convenience, nothing is hidden
// from the server-rendered page.
export function initListFilters() {
    document.querySelectorAll('input[data-list-filter]').forEach((input) => {
        const table = document.getElementById(input.dataset.listFilter);
        if (!table) {
            return;
        }
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const status = document.getElementById(`${input.dataset.listFilter}-count`);

        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach((row) => {
                const text = Array.from(row.querySelectorAll('input[name="name"], select option:checked, td'))
                    .map((el) => (el.value ?? el.textContent ?? '').toLowerCase())
                    .join(' ');
                const match = term === '' || text.includes(term);
                row.hidden = !match;
                shown += match ? 1 : 0;
            });
            if (status) {
                status.textContent = `${shown} shown`;
            }
        });
    });
}
