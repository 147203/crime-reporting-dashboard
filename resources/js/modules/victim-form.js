// The incident form's victim section: person details apply only to an individual victim, and an exact age sets the
// age band. The server applies the same rules (IncidentRules::normaliseVictim), so this only keeps the form honest.
const BANDS = [[17, 'under_18'], [24, '18_24'], [34, '25_34'], [44, '35_44'], [54, '45_54'], [64, '55_64']];

export function initVictimForm() {
    const card = document.querySelector('[data-victim-form]');
    if (!card) {
        return;
    }
    const type = card.querySelector('#victim_type');
    const fields = card.querySelector('[data-person-fields]');
    const note = card.querySelector('[data-person-note]');
    const age = card.querySelector('#victim_age');
    const band = card.querySelector('#victim_age_band');

    const syncType = () => {
        const notPerson = ['business', 'public_property'].includes(type.value);
        fields.disabled = notPerson;
        note.hidden = !notPerson;
    };
    const syncBand = () => {
        const years = Number.parseInt(age.value, 10);
        const fromAge = !Number.isNaN(years) && years >= 0;
        band.disabled = fromAge;
        if (fromAge) {
            band.value = (BANDS.find(([max]) => years <= max) ?? [0, '65_plus'])[1];
        }
    };

    type.addEventListener('change', syncType);
    age.addEventListener('input', syncBand);
    syncType();
    syncBand();
}
