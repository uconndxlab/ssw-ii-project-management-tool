<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilePasswordRequest;
use App\Services\UserShowPageData;

class ProfileController extends Controller
{
    public function show()
    {
        $data = UserShowPageData::for($this->actor());
        $data['isProfile'] = true;

        return view('admin.users.show', $data);
    }

    public function edit()
    {
        return view('profile.edit', [
            'user' => $this->actor(),
        ]);
    }

    public function updatePassword(UpdateProfilePasswordRequest $request)
    {
        $this->actor()->update([
            'password' => $request->validated('password'),
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Password updated successfully.');
    }
}
