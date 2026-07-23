<?php

namespace App\Support;

use App\Models\ActivityAgreementFundingSource;
use App\Models\Agreement;
use Illuminate\Support\Collection;

class ActivityFundingSourceTokens
{
    /**
     * @return array{source_type: string, source_id: int}|null
     */
    public static function parseToken(mixed $token): ?array
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        if (!preg_match('/^(user|organization):(\d+)$/', $token, $matches)) {
            return null;
        }

        $sourceType = $matches[1] === 'user'
            ? ActivityAgreementFundingSource::SOURCE_USER
            : ActivityAgreementFundingSource::SOURCE_ORGANIZATION;

        return [
            'source_type' => $sourceType,
            'source_id' => (int) $matches[2],
        ];
    }

    /**
     * @param  Collection<int, Agreement>  $agreements
     * @return array<int, array<string, array<int, string>>>
     */
    public static function buildEligibleTokenSets(
        Collection $agreements,
        array $organizationIds,
        array $participantUserIds,
    ): array {
        $organizationIdSet = collect($organizationIds)->map(fn ($id) => (int) $id)->flip();
        $participantIdSet = collect($participantUserIds)->map(fn ($id) => (int) $id)->flip();

        $result = [];

        foreach ($agreements as $agreement) {
            $orgTokens = $agreement->organizations
                ->filter(fn ($org) => filled($org->kfs_number) && $organizationIdSet->has((int) $org->id))
                ->map(fn ($org) => ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_ORGANIZATION,
                    (int) $org->id
                ))
                ->values()
                ->all();

            $memberUserIds = $agreement->users->pluck('id')
                ->concat($agreement->teams->flatMap(fn ($team) => $team->users->pluck('id')))
                ->map(fn ($id) => (int) $id)
                ->unique();

            $userTokens = $memberUserIds
                ->filter(fn ($userId) => $participantIdSet->has($userId))
                ->map(function ($userId) use ($agreement) {
                    $user = $agreement->users->firstWhere('id', $userId)
                        ?? $agreement->teams->flatMap(fn ($team) => $team->users)->firstWhere('id', $userId);

                    if (!$user || !filled($user->kfs_number)) {
                        return null;
                    }

                    return ActivityAgreementFundingSource::tokenFor(
                        ActivityAgreementFundingSource::SOURCE_USER,
                        (int) $userId
                    );
                })
                ->filter()
                ->values()
                ->all();

            $result[(int) $agreement->id] = [
                ActivityAgreementFundingSource::ROLE_PAYOR => array_values(array_unique(array_merge($orgTokens, $userTokens))),
                ActivityAgreementFundingSource::ROLE_PAYEE => array_values(array_unique(array_merge($orgTokens, $userTokens))),
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, Agreement>  $agreements
     * @return array<int, array<string, list<string>>>
     */
    public static function fundingSourceOptionsByAgreement(Collection $agreements): array
    {
        $options = [];

        foreach ($agreements as $agreement) {
            $agreementOptions = [];

            foreach ($agreement->organizations as $organization) {
                if (!filled($organization->kfs_number)) {
                    continue;
                }

                $token = ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_ORGANIZATION,
                    (int) $organization->id
                );

                $agreementOptions[] = [
                    'value' => $token,
                    'label' => $organization->name,
                    'search' => strtolower(trim($organization->name.' '.$organization->kfs_number)),
                    'contextLabels' => ['Organization'],
                    'contextBadgeClass' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                    'selectedBadgeClass' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                    'kfs_number' => $organization->kfs_number,
                ];
            }

            $memberUsers = $agreement->users
                ->concat($agreement->teams->flatMap(fn ($team) => $team->users))
                ->unique('id');

            foreach ($memberUsers as $user) {
                if (!filled($user->kfs_number)) {
                    continue;
                }

                $token = ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_USER,
                    (int) $user->id
                );

                $agreementOptions[] = [
                    'value' => $token,
                    'label' => $user->name,
                    'search' => strtolower(trim($user->name.' '.$user->kfs_number.' '.($user->email ?? ''))),
                    'contextLabels' => ['User'],
                    'contextBadgeClass' => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
                    'selectedBadgeClass' => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
                    'kfs_number' => $user->kfs_number,
                ];
            }

            usort($agreementOptions, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

            $options[(int) $agreement->id] = $agreementOptions;
        }

        return $options;
    }
}
