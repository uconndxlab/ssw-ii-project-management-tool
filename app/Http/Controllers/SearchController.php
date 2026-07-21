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

        // Agreements — non-admins only see their assigned ones
        $agreementsQuery = Agreement::with(['organizations', 'states'])
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhereHas('organizations', fn ($o) => $o->where('name', 'like', $like))
                  ->orWhereHas('states', fn ($s) => $s->where('name', 'like', $like));
            });

        if (! Auth::user()->isAdmin()) {
            $agreementsQuery->accessibleBy(Auth::user());
        }

        $agreements = $agreementsQuery->active()->orderBy('name')->limit(10)->get();

        // Organizations — visible to all
        $organizations = Organization::with('states')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(10)
            ->get();

        // Users — only admins can search/view user profiles
        $users = collect();
        if (Auth::user()->isAdmin()) {
            $users = User::where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('search.results', compact('query', 'agreements', 'organizations', 'users'));
    }
}
