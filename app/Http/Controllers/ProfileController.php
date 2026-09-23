<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilePasswordRequest;
use App\Services\UserShowPageData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function show(): View
    {
        $data = UserShowPageData::for($this->actor());
        $data['isProfile'] = true;

        return view('admin.users.show', $data);
    }

    public function edit(): View
    {
        return view('profile.edit', [
            'user' => $this->actor(),
        ]);
    }

    public function updatePassword(UpdateProfilePasswordRequest $request): RedirectResponse
    {
        $this->actor()->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Password updated successfully.');
    }
}
