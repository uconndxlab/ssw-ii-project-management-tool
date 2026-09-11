<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use RuntimeException;

class NightwatchDiagnosticsController extends Controller
{
    /**
     * Fire a batch of synthetic log/exception/mail events so Nightwatch redaction can be
     * verified by hand. Never wired up unless nightwatch.diagnostics_enabled is true.
     */
    public function __invoke(Request $request): string
    {
        abort_unless(
            config('nightwatch.diagnostics_enabled') && $request->user()?->isSystemAdmin(),
            404
        );

        // Bypass the low request sampling rate so this probe is always captured.
        Nightwatch::sample(1);

        $checks = [];

        $this->probeLogs();
        $checks[] = 'Logs: info/warning/error sent with synthetic PII in message + context.';

        Nightwatch::report(new RuntimeException('Nightwatch probe: handled exception'), handled: true);
        $checks[] = 'Handled exception reported via Nightwatch::report().';

        $this->probeQueryException($request);
        $checks[] = 'Duplicate-key QueryException triggered and rolled back.';

        $this->probeMail();
        $checks[] = 'Mail sent with a client-name subject via the log mailer.';

        return "Nightwatch diagnostics fired:\n- ".implode("\n- ", $checks)
            ."\n\nCheck the Nightwatch UI for each event above.";
    }

    private function probeLogs(): void
    {
        $context = [
            'name' => 'Jane Q. Testerson',
            'email' => 'jane.doe@example.invalid',
            'phone' => '555-0100',
            'notes' => 'Synthetic Nightwatch diagnostics probe, not a real client.',
        ];

        Log::info('Nightwatch probe: info level', $context);
        Log::warning('Nightwatch probe: warning level', $context);
        Log::error('Nightwatch probe: error level', $context);
    }

    private function probeQueryException(Request $request): void
    {
        try {
            DB::transaction(function () use ($request) {
                DB::table('users')->insert([
                    'name' => 'Nightwatch Probe Duplicate',
                    'email' => $request->userStr::il,
                    'password' => Hash::make(str()->random(32)),
                ]);
            });
        } catch (QueryException $e) {
            Nightwatch::report($e, handled: true);
        }
    }

    private function probeMail(): void
    {
        Mail::mailer('log')->raw(
            'Synthetic Nightwatch diagnostics probe, not a real client.',
            function ($message) {
                $message->to('probe@example.invalid')
                    ->subject('Case update for Jane Q. Testerson');
            }
        );
    }
}
