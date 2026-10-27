<dialog {{ $attributes->class(['app-confirm-dialog']) }} data-confirm-dialog aria-labelledby="app-confirm-title" aria-describedby="app-confirm-record app-confirm-message">
    <button type="button" class="btn-close app-confirm-close" data-confirm-close aria-label="Close confirmation"></button>
    <div class="app-confirm-body">
        <div class="app-confirm-heading">
            <span class="app-confirm-icon" aria-hidden="true"><i class="bi bi-trash3" data-confirm-icon></i></span>
            <h2 id="app-confirm-title"></h2>
        </div>
        <p id="app-confirm-record" class="app-confirm-record"></p>
        <p id="app-confirm-message" class="app-confirm-message"></p>
    </div>
    <form method="dialog" class="app-confirm-actions">
        <button type="submit" class="btn btn-outline-secondary" value="cancel" autofocus>Cancel</button>
        <button type="submit" class="btn btn-danger" value="confirm" data-confirm-accept>Delete</button>
    </form>
</dialog>
