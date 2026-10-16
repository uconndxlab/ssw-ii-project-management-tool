<?php

namespace App\Enums;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use App\Support\CarbonDate;
use Illuminate\Database\Eloquent\Model;

enum RecentRecordType: string
{
    case Activity = 'activity';
    case Agreement = 'agreement';
    case LoggingField = 'logging-field';
    case Organization = 'organization';
    case Program = 'program';
    case Project = 'project';
    case State = 'state';
    case Team = 'team';
    case User = 'user';

    public static function fromRouteName(?string $routeName): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->showRoute() === $routeName) {
                return $type;
            }
        }

        return null;
    }

    public function showRoute(): string
    {
        return match ($this) {
            self::Activity => 'activities.show',
            self::Agreement => 'agreements.show',
            self::LoggingField => 'logging-fields.show',
            self::Organization => 'organizations.show',
            self::Program => 'programs.show',
            self::Project => 'projects.show',
            self::State => 'states.show',
            self::Team => 'teams.show',
            self::User => 'users.show',
        };
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Activity => Activity::class,
            self::Agreement => Agreement::class,
            self::LoggingField => LoggingField::class,
            self::Organization => Organization::class,
            self::Program => Program::class,
            self::Project => Project::class,
            self::State => State::class,
            self::Team => Team::class,
            self::User => User::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LoggingField => 'Logging field',
            default => $this->name,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Activity => 'bi-journal-check',
            self::Agreement => 'bi-file-earmark-text',
            self::LoggingField => 'bi-input-cursor-text',
            self::Organization => 'bi-building',
            self::Program => 'bi-diagram-3',
            self::Project => 'bi-kanban',
            self::State => 'bi-geo-alt',
            self::Team => 'bi-people',
            self::User => 'bi-person',
        };
    }

    public function titleFor(Model $record): string
    {
        if ($record instanceof Activity) {
            $record->loadMissing('activityType');

            return collect([
                $record->activityType?->name,
                CarbonDate::parse($record->engagement_date)?->format('M j, Y'),
            ])->filter()->implode(' · ') ?: 'Activity';
        }

        return (string) ($record->getAttribute('name') ?: $this->label());
    }
}
