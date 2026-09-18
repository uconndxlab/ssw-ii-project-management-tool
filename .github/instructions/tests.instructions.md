---
applyTo: "tests/**"
---

Use Pest syntax for all new tests: test() or it() with closures. Use expect()
or Laravel response/model assertions ($this->actingAs(...)->get(...)->assertOk()).
Do not add new PHPUnit class-based tests.

Feature tests in tests/Feature/ inherit RefreshDatabase from tests/Pest.php.

Run the suite with `sail artisan test`, never bare `php artisan test` on the host.

Never run migrate:fresh, migrate:refresh, or db:wipe outside APP_ENV=testing.

New features ship with feature tests covering the behavior described in the spec.

Smoke tests should authenticate via actingAs() — GET / is behind auth and active
middleware and redirects guests to /login.

Do not create activities in dashboard smoke tests unless explicitly testing
activity-dependent behavior (adminHome references removed Activity columns).
