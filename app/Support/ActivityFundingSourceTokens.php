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
    public static function buildEligibleTokenSets(Collection $agreements): array
    {
        $result = [];

        foreach ($agreements as $agreement) {
            $orgTokens = $agreement->organizations
                ->filter(fn ($org) => filled($org->po_number))
                ->map(fn ($org) => ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_ORGANIZATION,
                    (int) $org->id
                ))
                ->values()
                ->all();

            $userTokens = $agreement->users
                ->concat($agreement->teams->flatMap(fn ($team) => $team->users))
                ->unique('id')
                ->filter(fn ($user) => filled($user->po_number))
                ->map(fn ($user) => ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_USER,
                    (int) $user->id
                ))
                ->values()
                ->all();

            $tokens = array_values(array_unique(array_merge($orgTokens, $userTokens)));

            $result[(int) $agreement->id] = [
                ActivityAgreementFundingSource::ROLE_PAYOR => $tokens,
                ActivityAgreementFundingSource::ROLE_PAYEE => $tokens,
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
                if (!filled($organization->po_number)) {
                    continue;
                }

                $token = ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_ORGANIZATION,
                    (int) $organization->id
                );

                $agreementOptions[] = [
                    'value' => $token,
                    'label' => $organization->name,
                    'search' => strtolower(trim($organization->name.' '.$organization->po_number)),
                    'entity' => 'organization',
                    'contextLabels' => ['Organization'],
                    'meta' => $organization->po_number,
                ];
            }

            $memberUsers = $agreement->users
                ->concat($agreement->teams->flatMap(fn ($team) => $team->users))
                ->unique('id');

            foreach ($memberUsers as $user) {
                if (!filled($user->po_number)) {
                    continue;
                }

                $token = ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_USER,
                    (int) $user->id
                );

                $agreementOptions[] = [
                    'value' => $token,
                    'label' => $user->name,
                    'search' => strtolower(trim($user->name.' '.$user->po_number.' '.($user->email ?? ''))),
                    'entity' => 'user',
                    'contextLabels' => ['User'],
                    'meta' => $user->po_number,
                ];
            }

            usort($agreementOptions, fn ($a, $b) => strcasecmp($a['label'], $b['label']));

            $options[(int) $agreement->id] = $agreementOptions;
        }

        return $options;
    }
}
