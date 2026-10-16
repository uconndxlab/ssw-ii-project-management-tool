<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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

        if ($routeName === 'profile.edit') {
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
                    'label' => $record->getAttributes()['name'] ?? Str::headline(Str::singular($resource)),
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
}
