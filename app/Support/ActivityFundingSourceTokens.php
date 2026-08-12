<?php

namespace App\Support;

use App\Models\ActivityAgreementFundingSource;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\User;
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
            $kfsNumbersByOrganization = self::kfsNumbersByOrganization($agreement);

            $payorTokens = $agreement->organizations
                ->filter(fn ($org) => (bool) ($org->pivot->payor_source ?? false))
                ->filter(fn ($org) => !empty($kfsNumbersByOrganization[(int) $org->id] ?? []))
                ->map(fn ($org) => ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_ORGANIZATION,
                    (int) $org->id
                ))
                ->values()
                ->all();

            $payeeOrgTokens = $agreement->organizations
                ->filter(fn ($org) => filled($org->po_number))
                ->map(fn ($org) => ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_ORGANIZATION,
                    (int) $org->id
                ))
                ->values()
                ->all();

            $payeeUserTokens = self::memberUsers($agreement)
                ->filter(fn ($user) => filled($user->po_number))
                ->map(fn ($user) => ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_USER,
                    (int) $user->id
                ))
                ->values()
                ->all();

            $result[(int) $agreement->id] = [
                ActivityAgreementFundingSource::ROLE_PAYOR => array_values(array_unique($payorTokens)),
                ActivityAgreementFundingSource::ROLE_PAYEE => array_values(array_unique(array_merge($payeeOrgTokens, $payeeUserTokens))),
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
            $agreementOptions = [
                ActivityAgreementFundingSource::ROLE_PAYOR => [],
                ActivityAgreementFundingSource::ROLE_PAYEE => [],
            ];
            $kfsNumbersByOrganization = self::kfsNumbersByOrganization($agreement);

            foreach ($agreement->organizations as $organization) {
                $token = ActivityAgreementFundingSource::tokenFor(ActivityAgreementFundingSource::SOURCE_ORGANIZATION, (int) $organization->id);

                if ((bool) ($organization->pivot->payor_source ?? false) && !empty($kfsNumbersByOrganization[(int) $organization->id] ?? [])) {
                    $kfsNumbers = $kfsNumbersByOrganization[(int) $organization->id];

                    $agreementOptions[ActivityAgreementFundingSource::ROLE_PAYOR][] = [
                        'value' => $token,
                        'label' => $organization->name,
                        'search' => strtolower(trim($organization->name.' '.implode(' ', $kfsNumbers))),
                        'entity' => 'organization',
                        'contextLabels' => ['Organization'],
                        'meta' => 'KFS: '.implode(', ', $kfsNumbers),
                    ];
                }

                if (self::hasValidPoNumber($organization->po_number)) {
                    $agreementOptions[ActivityAgreementFundingSource::ROLE_PAYEE][] = [
                        'value' => $token,
                        'label' => $organization->name,
                        'search' => strtolower(trim($organization->name.' '.$organization->po_number)),
                        'entity' => 'organization',
                        'contextLabels' => ['Organization'],
                        'meta' => $organization->po_number,
                    ];
                }
            }

            foreach (self::memberUsers($agreement) as $user) {
                if (!self::hasValidPoNumber($user->po_number)) {
                    continue;
                }

                $token = ActivityAgreementFundingSource::tokenFor(
                    ActivityAgreementFundingSource::SOURCE_USER,
                    (int) $user->id
                );

                $agreementOptions[ActivityAgreementFundingSource::ROLE_PAYEE][] = [
                    'value' => $token,
                    'label' => $user->name,
                    'search' => strtolower(trim($user->name.' '.$user->po_number.' '.($user->email ?? ''))),
                    'entity' => 'user',
                    'contextLabels' => ['User'],
                    'meta' => $user->po_number,
                ];
            }

            foreach ($agreementOptions as $role => $roleOptions) {
                usort($roleOptions, fn ($a, $b) => strcasecmp($a['label'], $b['label']));
                $agreementOptions[$role] = $roleOptions;
            }

            $options[(int) $agreement->id] = $agreementOptions;
        }

        return $options;
    }

    /**
     * @return array{kfs_numbers_snapshot: array<int, string>|null, po_number_snapshot: string|null}
     */
    public static function snapshotForSelection(Agreement $agreement, string $role, array $parsed): array
    {
        $snapshot = [
            'kfs_numbers_snapshot' => null,
            'po_number_snapshot' => null,
        ];

        if ($role === ActivityAgreementFundingSource::ROLE_PAYOR
            && $parsed['source_type'] === ActivityAgreementFundingSource::SOURCE_ORGANIZATION) {
            $snapshot['kfs_numbers_snapshot'] = self::kfsNumbersByOrganization($agreement)[$parsed['source_id']] ?? null;

            return $snapshot;
        }

        if ($parsed['source_type'] === ActivityAgreementFundingSource::SOURCE_ORGANIZATION) {
            $organization = $agreement->organizations->firstWhere('id', $parsed['source_id']);
            $snapshot['po_number_snapshot'] = $organization?->po_number;

            return $snapshot;
        }

        if ($parsed['source_type'] === ActivityAgreementFundingSource::SOURCE_USER) {
            $user = self::memberUsers($agreement)->firstWhere('id', $parsed['source_id']);
            $snapshot['po_number_snapshot'] = $user?->po_number;
        }

        return $snapshot;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function kfsNumbersByOrganization(Agreement $agreement): array
    {
        return $agreement->organizationKfsAccounts
            ->groupBy(fn ($account) => (int) $account->pivot->organization_id)
            ->map(fn ($accounts) => $accounts->pluck('number')->sort()->values()->all())
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    private static function memberUsers(Agreement $agreement): Collection
    {
        return $agreement->users
            ->concat($agreement->teams->flatMap(fn ($team) => $team->users))
            ->unique('id')
            ->values();
    }

    private static function hasValidPoNumber(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9]{6}$/', $value) === 1;
    }
}
