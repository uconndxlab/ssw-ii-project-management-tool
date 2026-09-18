Tests run against the testing environment only. Never run migrate:fresh,
migrate:refresh, or db:wipe outside APP_ENV=testing. Never point test
configuration at the development or production database.

Run tests with `sail artisan test`, never bare `php artisan test` on the host.

New features ship with feature tests covering the behavior described in the
spec, factories for any new model, and seeder updates where the feature needs
data to be visible.
