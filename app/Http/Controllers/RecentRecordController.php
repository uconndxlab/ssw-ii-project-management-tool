<?php

namespace App\Http\Controllers;

use App\Services\RecentRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecentRecordController extends Controller
{
    public function __construct(private readonly RecentRecordService $recentRecords) {}

    public function panel(Request $request): View|RedirectResponse
    {
        if ($request->header('HX-Request') !== 'true') {
            return redirect()->route('dashboard');
        }

        return $this->listView($request);
    }

    public function clear(Request $request): View
    {
        $this->recentRecords->clear($this->actor());

        return $this->listView($request);
    }

    private function listView(Request $request): View
    {
        return view('recent.list', [
            'items' => $this->recentRecords->listFor($this->actor()),
            'currentPath' => parse_url((string) $request->header('HX-Current-URL'), PHP_URL_PATH),
        ]);
    }
}
