<?php

namespace App\Support;

use App\Enums\RecentRecordType;
use App\Models\Agreement;
use App\Models\CertificateEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class RouteBreadcrumbs
{
    /**
     * @return array<int, array{label: string, url: ?string, current: bool}>
     */
    public function items(Request $request, ?string $currentLabel): array
    {
        $route = $request->route();
        $routeName = $route?->getName() ?? '';
        $resource = Str::beforeLast($routeName, '.');
        $indexRoute = $resource.'.index';
        $items = [];

        if ($routeName === 'certificate-enrollments.show') {
            $enrollment = $route->parameter('certificateEnrollment');
            $actor = $request->user();
            if ($enrollment instanceof CertificateEnrollment && $actor instanceof User) {
                $agreements = Agreement::query()->visibleTo($actor)
                    ->whereHas('certificationCandidates', fn ($query) => $query->where('certification_candidate_id', $enrollment->certification_candidate_id))
                    ->orderBy('name')->get();
                $agreement = $agreements->firstWhere('id', $request->integer('agreement_id'))
                    ?? $agreements->firstWhere('id', $enrollment->sponsoring_agreement_id)
                    ?? $agreements->first();
                if ($agreement !== null) {
                    $items[] = ['label' => 'Agreements', 'url' => route('agreements.index'), 'current' => false];
                    $items[] = ['label' => $agreement->name, 'url' => route('agreements.show', $agreement), 'current' => false];
                }
            }
        } elseif ($routeName === 'profile.edit') {
            $items[] = ['label' => 'Profile', 'url' => route('profile'), 'current' => false];
        } elseif (Route::has($indexRoute) && $routeName !== $indexRoute) {
            $label = $resource === 'contact-families'
                ? 'Activity Families'
                : Str::headline(Str::afterLast($resource, '.'));
            $items[] = ['label' => $label, 'url' => route($indexRoute), 'current' => false];
        }

        if ($route && Str::endsWith($routeName, '.edit') && Route::has($resource.'.show')) {
            $record = collect($route->parameters())->first();

            if ($record instanceof Model) {
                $items[] = [
                    'label' => RecentRecordType::fromRouteName($resource.'.show')?->titleFor($record)
                        ?? $record->getAttributes()['name']
                        ?? Str::headline(Str::singular($resource)),
                    'url' => route($resource.'.show', $record),
                    'current' => false,
                ];
            }
        }

        if (filled($currentLabel)) {
            $items[] = ['label' => $currentLabel, 'url' => null, 'current' => true];
        }

        return $items;
    }

    /**
     * Where Cancel goes: the page the user came from when it is a record or list page on this site
     * (so index filters and the edit button on a record both return them there), otherwise the
     * parent crumb.
     */
    public function cancelUrl(Request $request): ?string
    {
        return $this->previousPage($request)
            ?? Arr::last($this->items($request, null))['url'] ?? null;
    }

    private function previousPage(Request $request): ?string
    {
        $previous = $request->headers->get('referer') ?: $request->session()->previousUrl();
        $parts = parse_url((string) $previous);

        if ($previous === null || ($parts['host'] ?? null) !== $request->getHost() || ($parts['path'] ?? null) === $request->getPathInfo()) {
            return null;
        }

        try {
            $routeName = (string) app('router')->getRoutes()->match(Request::create($previous))->getName();
        } catch (Throwable) {
            return null;
        }

        $isViable = in_array($routeName, ['dashboard', 'search', 'profile'], true)
            || Str::endsWith($routeName, ['.index', '.show']);

        return $isViable ? $previous : null;
    }
}
