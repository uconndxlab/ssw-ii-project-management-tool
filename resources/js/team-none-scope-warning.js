function syncTeamNoneScopeWarning(section, warning) {
    if (!section || !warning) {
        return;
    }

    const checked = section.querySelector('input[name="program_scope_mode"]:checked');
    warning.classList.toggle('d-none', !checked || checked.value !== 'none');
}

document.addEventListener('DOMContentLoaded', function () {
    const warning = document.querySelector('[data-team-none-scope-warning]');
    const section = warning?.closest('form')?.querySelector('[data-project-program-scope]');
    syncTeamNoneScopeWarning(section, warning);
    section?.addEventListener('project-program-scope:change', function () {
        syncTeamNoneScopeWarning(section, warning);
    });
});
