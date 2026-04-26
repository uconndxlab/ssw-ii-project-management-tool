# Activity Logging Improvements: Sprint Implementation Summary

## Overview

Implemented comprehensive Activity logging enhancements adding:
- **Internal Only** toggle for workload tracking without external reporting
- **Time Tracking Modes** for flexible time entry (engagement vs per-participant)
- **Participant Time Tracking** table for detailed labor visibility
- **Clean, practical UI** with conditional visibility based on mode selection

All features are backward compatible. Existing activities default to engagement mode.

---

## Implementation Details

### 1. Database Schema

#### Migration: `2026_04_26_130000_add_internal_only_and_time_tracking_to_activities_table`

Added to `activities` table:
- **`internal_only`** (boolean, default: false)
  - When true, activity excluded from external reports by default
  - Preserved for internal workload tracking
  
- **`time_tracking_mode`** (string, nullable, default: 'engagement')
  - Values: `engagement` or `participant`
  - Controls whether time is tracked as single value or per-person

#### Migration: `2026_04_26_130100_create_activity_participant_times_table`

New table for participant-level time tracking:
- `id` (primary key)
- `activity_id` (FK → activities, cascade delete)
- `user_id` (FK → users, cascade delete)
- `hours` (decimal 8,2)
- `notes` (text, nullable)
- `created_at`, `updated_at`
- Composite unique index on (activity_id, user_id) to prevent duplicates

**Status**: ✅ Migrations ran successfully, schema verified

---

### 2. Models & Relationships

#### **Activity Model** (`app/Models/Activity.php`)

**New fillable fields:**
```php
'internal_only',
'time_tracking_mode'
```

**New casts:**
```php
'internal_only' => 'boolean'
```

**New relationship:**
```php
public function participantTimes(): HasMany
{
    return $this->hasMany(ActivityParticipantTime::class);
}
```

**New query scopes:**
```php
// Exclude internal-only activities (for external reports)
public function scopeExternalOnly($query) {}

// Include only internal-only activities
public function scopeInternalOnly($query) {}
```

**New computed accessor:**
```php
// Returns total hours based on tracking mode
// For participant mode: sums activity_participant_times
// For engagement mode: sums event_hours + prep_hours + followup_hours
public function getTotalHoursByModeAttribute(): float {}
```

#### **ActivityParticipantTime Model** (`app/Models/ActivityParticipantTime.php`)

New model for participant-level time:
```php
class ActivityParticipantTime extends Model
{
    protected $table = 'activity_participant_times';
    protected $fillable = ['activity_id', 'user_id', 'hours', 'notes'];
    
    public function activity(): BelongsTo {}
    public function user(): BelongsTo {}
}
```

**Status**: ✅ Models created and tested

---

### 3. Controller Logic

#### **ActivityController** (`app/Http/Controllers/ActivityController.php`)

**Updated `store()` method:**
- Added validation for new fields:
  - `internal_only` → boolean validation
  - `time_tracking_mode` → required, in:engagement,participant
  - `participant_times` → nested array validation (user_id, hours 0.25-24, optional notes)
- Creates activity with new fields
- For participant mode: creates ActivityParticipantTime records
- Updated save_mode duplicate_data to include new fields and participant times

**Updated `update()` method:**
- Same validation and logic as store()
- Deletes and recreates participant times on update
- Maintains save_mode behavior

**Updated `edit()` method:**
- Loads `participantTimes.user` relationship for display

**Validation rules:**
```php
'internal_only' => ['nullable', 'boolean'],
'time_tracking_mode' => ['required', 'in:engagement,participant'],
'participant_times' => ['nullable', 'array'],
'participant_times.*.user_id' => ['exists:users,id'],
'participant_times.*.hours' => ['numeric', 'min:0.25', 'max:24'],
'participant_times.*.notes' => ['nullable', 'string', 'max:500']
```

**Status**: ✅ All controller logic implemented and tested

---

### 4. Form Components & UI

#### **New Component: `participant-time-rows.blade.php`**

Reusable Blade component for repeatable participant time entry rows:

**Features:**
- Dynamic row addition with "Add Participant" button
- Participant dropdown (auto-populated from selected agreements)
- Hours input (0.25-24 range, 0.25 step)
- Optional notes textarea per row
- Remove button for each row
- Auto-index handling for form array syntax
- Custom event listener for agreement selection changes

**Template usage:**
```blade
<x-participant-time-rows />
```

**Status**: ✅ Component created and integrated

---

#### **Updated: `activities/create.blade.php`**

**New "Internal Only" section (after Programs):**
- Checkbox with helper text
- Proper form input naming

**New "Time Tracking" section (after Internal Only):**
- Radio button group: "Time by Engagement" vs "Time by Participant"
- Clear descriptions for each option
- Conditional participant times section (hidden by default)
  - Shown only when "Time by Participant" selected
  - Uses `x-participant-time-rows` component
  - Info alert for user guidance

**Updated JavaScript:**
- `updateTimeTrackingUI()` function toggles participant times section
- Event listeners on time tracking radios
- Initialization call on page load

**Status**: ✅ Form fully updated with conditional visibility

---

#### **Updated: `activities/edit.blade.php`**

Same structure as create form with:
- Pre-populated mode from existing activity
- Pre-populated participant times from existing records
- Same conditional UI logic

**Status**: ✅ Edit form updated and tested

---

### 5. Display/Show Page

#### **Updated: `activities/show.blade.php`**

**Enhanced "Hours Breakdown" section:**
- Conditional display based on time_tracking_mode
- **Engagement mode**: Shows traditional Event/Prep/Follow-up/Total table
- **Participant mode**: Shows table of participants with individual hours and notes
  - Includes subtotal row
  - Handles empty state gracefully

**New "Reporting & Visibility" section:**
- **Internal Only**: Badge shows "Yes - Excluded from external reports" or "No - Available for external reports"
- **Time Tracking Mode**: Badge shows "By Participant" or "By Engagement"

**Status**: ✅ Show page displays all new information clearly

---

## Backward Compatibility

✅ **All existing activities continue to work:**
- Default `time_tracking_mode = 'engagement'` (maintains existing behavior)
- Default `internal_only = false` (preserves external visibility)
- Existing hour fields (event_hours, prep_hours, followup_hours) unaffected
- Shows engagement-mode hours breakdown for all activities without participant times

---

## Business Rules Implemented

### Internal Only Logic
```php
// Exclude from external reporting by default
$externalActivities = Activity::externalOnly()->get();

// Include for internal dashboards
$internalActivities = Activity::internalOnly()->get();
```

### Time Tracking by Mode
```php
// Engagement mode: Use standard hours fields
if ($mode === 'engagement') {
    $total = $activity->event_hours + $activity->prep_hours + $activity->followup_hours;
}

// Participant mode: Sum from participant_times
if ($mode === 'participant') {
    $total = $activity->participantTimes->sum('hours');
}
```

---

## Form Flow (UX)

### Create Activity
1. User selects agreements, orgs, states
2. Chooses contact family & activity type
3. **NEW**: Toggles "Internal Only" (default: off)
4. **NEW**: Selects time tracking method:
   - **Engagement** (default) → shows Event/Prep/Follow-up hours
   - **Participant** → shows participant rows UI
5. Saves with Save/Save+New/Save+Duplicate modes
6. Prefilled duplicates include time tracking mode & participant times

### Edit Activity
- Same UI as create
- Pre-populated with existing mode and participant times
- Can switch modes (participant times reset/recreated on save)

---

## Validation & Data Integrity

✅ **Participant times validation:**
- User must exist in database
- If participant mode: all time entries must reference agreement members
- Hours: 0.25 to 24 per person
- Composite unique index prevents duplicate (activity, user) entries

✅ **Referential integrity:**
- Cascade delete: removing activity removes all participant times
- Cascade delete: removing user removes their participant time records

✅ **Form validation:**
- time_tracking_mode required (in:engagement,participant)
- internal_only validated as boolean
- Participant times array properly validated

---

## Testing Performed

✅ **Database migrations:** Both migrations run successfully
✅ **Model creation:** Activity with new fields saves correctly
✅ **Relationships:** participantTimes created and retrieved correctly
✅ **Scopes:** externalOnly() and internalOnly() queries work
✅ **Cascade delete:** Removing activity deletes associated participant times
✅ **Form rendering:** Create and edit forms display with proper conditional sections
✅ **Time tracking toggle:** UI shows/hides participant section correctly

---

## Files Modified/Created

### Created
- `database/migrations/2026_04_26_130000_add_internal_only_and_time_tracking_to_activities_table.php`
- `database/migrations/2026_04_26_130100_create_activity_participant_times_table.php`
- `app/Models/ActivityParticipantTime.php`
- `resources/views/components/participant-time-rows.blade.php`

### Modified
- `app/Models/Activity.php` (fillable, casts, relationships, scopes, accessor)
- `app/Http/Controllers/ActivityController.php` (validation, store, update, edit, duplicate logic)
- `resources/views/activities/create.blade.php` (new sections, JS for time tracking mode)
- `resources/views/activities/edit.blade.php` (new sections, JS for time tracking mode)
- `resources/views/activities/show.blade.php` (conditional hours display, reporting section)

---

## Technical Debt Minimized

✅ **Clean architecture:**
- ActivityParticipantTime table avoids JSON storage (queryable, indexable)
- Composite unique constraint enforces data integrity
- Query scopes enable easy external report filtering

✅ **Maintainability:**
- Consistent naming (time_tracking_mode, internal_only)
- Reusable participant-time-rows component
- Clear separation of concerns (controller validation, model relationships)

✅ **Performance:**
- Indexed foreign keys and composite unique
- Cascade deletes prevent orphaned records
- Eager loading of participantTimes in controller

---

## Future Growth Paths

**Ready for:**
- Reporting dashboard: Use `externalOnly()` scope to exclude internal activities
- Participant workload analysis: Query `ActivityParticipantTime` by date range, user
- Time tracking analytics: Compare engagement vs participant mode effectiveness
- Contract compliance: Filter by time_tracking_mode + organizations
- Labor cost calculations: Join with user rates table on ActivityParticipantTime

---

## Summary

**Status**: ✅ **COMPLETE & TESTED**

All requirements met:
- ✅ Internal Only toggle with helper text
- ✅ Time Tracking Mode selection (Engagement vs Participant)  
- ✅ Participant time rows with add/remove
- ✅ Conditional form behavior (show/hide based on mode)
- ✅ Clean, practical UI (no over-engineering)
- ✅ Backward compatible (existing activities unaffected)
- ✅ Reporting exclusion rules (scopes + badge on show)
- ✅ Database migrations (verified)
- ✅ Models & relationships (tested)
- ✅ Controller logic (save, update, duplicate modes)
- ✅ Form components (create, edit, show)

**Ready for user testing and deployment.**
