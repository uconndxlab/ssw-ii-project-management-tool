// Tool/Training builder: the type picks the Dimensions or Sections card; the scoring switch sets new sections' Pass/Fail.
const typeGroup = document.querySelector('[data-instrument-type]');

if (typeGroup) {
    const form = typeGroup.closest('form');
    const scoringDefault = form.querySelector('[data-section-scoring-default]');
    const sectionSwitches = () => [...form.querySelectorAll('[data-instrument-for="training"] [data-section-scored]:not(:disabled)')];
    const showType = () => {
        const type = form.querySelector('input[name="type"]:checked')?.value ?? form.querySelector('input[name="type"][type="hidden"]')?.value;
        form.querySelectorAll('[data-instrument-for]').forEach((card) => { card.hidden = card.dataset.instrumentFor !== type; });
    };
    typeGroup.addEventListener('change', showType);
    form.addEventListener('repeater:row-added', (event) => {
        const scored = event.target.querySelector?.('[data-section-scored]');
        if (scored && scoringDefault) scored.checked = scoringDefault.checked;
    });
    form.querySelector('[data-section-apply-all]')?.addEventListener('click', () => {
        const switches = sectionSwitches();
        switches.forEach((input) => { input.checked = scoringDefault.checked; });
        form.dispatchEvent(new Event('input', { bubbles: true }));
        form.querySelector('[data-section-status]').textContent = `Pass/Fail scoring ${scoringDefault.checked ? 'on' : 'off'} for ${switches.length} ${switches.length === 1 ? 'section' : 'sections'}.`;
    });
    showType();
}
