<?php

use App\Support\NightwatchSqlMessage;

test('nightwatch sql message drops detail and connection suffixes', function () {
    $message = 'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "users_email_unique" DETAIL: Key (email)=(jane@example.com) already exists.';

    expect(NightwatchSqlMessage::trim($message))->toBe(
        'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "users_email_unique"'
    );
});

test('nightwatch sql message without a detail line is unchanged', function () {
    $message = 'SQLSTATE[23505]: Unique violation';

    expect(NightwatchSqlMessage::trim($message))->toBe($message)
        ->and(NightwatchSqlMessage::trim('not sql'))->toBe('not sql');
});
