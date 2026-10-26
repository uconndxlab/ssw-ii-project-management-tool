<?php

namespace App\Http\Controllers;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Http\Requests\UpdateCertificationDutiesRequest;
use App\Http\Requests\UpdateProfilePasswordRequest;
use App\Models\Program;
use App\Services\UserShowPageData;
use App\Support\Authorization\CertificationDutySync;
use App\Support\ProjectProgramScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
        $user = $this->actor();
        $access = $user->access();
        $duties = $user->certificationDuties()->whereNull('revoked_at')->get();
        $dutyProgramOptions = Program::query()->when(! $access->isSystemAdmin(), fn ($query) => $query->whereIn('id', $access->adminProgramIds()))->orderBy('name')->get(['id', 'name']);

        return view('profile.edit', [
            'user' => $user,
            'actor' => $user,
            'canGrantCertificationDuties' => $access->isSystemAdmin() || $access->adminProgramIds() !== [],
            'dutyProgramOptions' => $dutyProgramOptions,
            'existingCoachSystem' => $duties->where('duty', CertificationDuty::Coach)->where('scope_type', PrivilegeScopeType::System)->isNotEmpty(),
            'existingCoachProgramIds' => $duties->where('duty', CertificationDuty::Coach)->where('scope_type', PrivilegeScopeType::Program)->whereIn('scope_id', $dutyProgramOptions->modelKeys())->pluck('scope_id')->all(),
            'existingManagerSystem' => $duties->where('duty', CertificationDuty::Manager)->where('scope_type', PrivilegeScopeType::System)->isNotEmpty(),
            'existingManagerProgramIds' => $duties->where('duty', CertificationDuty::Manager)->where('scope_type', PrivilegeScopeType::Program)->whereIn('scope_id', $dutyProgramOptions->modelKeys())->pluck('scope_id')->all(),
        ]);
    }

    public function updateCertificationDuties(UpdateCertificationDutiesRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $submitted = [];
        foreach (['coach', 'manager'] as $duty) {
            if ($request->hasAny(["duty_{$duty}_present", "duty_{$duty}_system", "duty_{$duty}_program_ids"])) {
                $submitted["{$duty}_system"] = $request->boolean("duty_{$duty}_system");
                $submitted["{$duty}_program_ids"] = ProjectProgramScope::normalizeIds($validated["duty_{$duty}_program_ids"] ?? []);
            }
        }

        DB::transaction(fn () => CertificationDutySync::apply($this->actor(), $this->actor(), $submitted));

        return redirect()->route('profile.edit')->with('success', 'Certification duties updated successfully.');
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
