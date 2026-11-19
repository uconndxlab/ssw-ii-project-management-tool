<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->actor();

        if ($user->access()->isInput()) {
            abort(403);
        }

        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return view('search.results', [
                'query' => '',
                'agreements' => collect(),
                'organizations' => collect(),
                'users' => collect(),
            ]);
        }

        $like = "%{$query}%";

        $agreements = Agreement::query()
            ->visibleTo($user)
            ->with(['organizations', 'states'])
            ->where(function ($q) use ($like) {
                $q->whereLike('name', $like)
                    ->orWhereHas('organizations', fn ($o) => $o->whereLike('name', $like))
                    ->orWhereHas('states', fn ($s) => $s->whereLike('name', $like));
            })
            ->active()
            ->orderBy('name')
            ->limit(10)
            ->get();

        $organizations = Organization::query()
            ->visibleTo($user)
            ->with('states')
            ->whereLike('name', $like)
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
                    $q->whereLike('name', $like)
                        ->orWhereLike('email', $like);
                })
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('search.results', compact('query', 'agreements', 'organizations', 'users'));
    }
}
