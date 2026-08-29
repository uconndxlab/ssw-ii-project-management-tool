<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->access()->isInput()) {
            abort(403);
        }

        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return view('search.results', [
                'query'         => '',
                'agreements'    => collect(),
                'organizations' => collect(),
                'users'         => collect(),
            ]);
        }

        $like = "%{$query}%";

        $agreements = Agreement::query()
            ->visibleTo($user)
            ->with(['organizations', 'states'])
            ->where(function ($q) use ($like) {
                $q->whereIlike('name', $like)
                  ->orWhereHas('organizations', fn ($o) => $o->whereIlike('name', $like))
                  ->orWhereHas('states', fn ($s) => $s->whereIlike('name', $like));
            })
            ->active()
            ->orderBy('name')
            ->limit(10)
            ->get();

        $organizations = Organization::query()
            ->visibleTo($user)
            ->with('states')
            ->whereIlike('name', $like)
            ->orderBy('name')
            ->limit(10)
            ->get();

        $users = collect();
        if ($user->can('viewAny', User::class)) {
            $usersQuery = User::query()->visibleTo($user);
            if ($user->access()->isSupervisor() && ! $user->access()->hasView()) {
                $usersQuery = User::query();
                $user->access()->applySuperviseesVisibility($usersQuery);
            }

            $users = $usersQuery
                ->where(function ($q) use ($like) {
                    $q->whereIlike('name', $like)
                        ->orWhereIlike('email', $like);
                })
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('search.results', compact('query', 'agreements', 'organizations', 'users'));
    }
}
