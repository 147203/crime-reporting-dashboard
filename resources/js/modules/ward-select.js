// Limits the ward list to the chosen sub-county. Server-side validation (V4) still enforces the rule.
export function initWardSelects() {
    document.querySelectorAll('select[data-ward-select]').forEach((wardSelect) => {
        const subCountySelect = document.getElementById(wardSelect.dataset.wardSelect);
        if (!subCountySelect) {
            return;
        }

        const sync = () => {
            const subCounty = subCountySelect.value;
            wardSelect.querySelectorAll('option[data-sub-county]').forEach((option) => {
                const visible = subCounty === '' || option.dataset.subCounty === subCounty;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            const selected = wardSelect.selectedOptions[0];
            if (selected && selected.disabled) {
                wardSelect.value = '';
            }
        };

        subCountySelect.addEventListener('change', sync);
        sync();
    });
}
