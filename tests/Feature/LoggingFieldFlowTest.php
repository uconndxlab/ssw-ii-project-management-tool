<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\AgreementLoggingField;
use App\Models\ContactFamily;
use App\Models\ContactFamilyLoggingField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoggingFieldFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_home_page_loads_with_active_agreements(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Agreement::create([
            'name' => 'Current Agreement',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        Agreement::create([
            'name' => 'Expired Agreement',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Agreement Logging Fields');
        $response->assertSee('Contact Family Logging Fields');
        $response->assertViewHas('stats', function (array $stats) {
            return ($stats['active_agreements'] ?? null) === 1;
        });
    }

    public function test_activity_create_page_renders_split_logging_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agreement = Agreement::create(['name' => 'Agreement Alpha']);
        $agreementField = AgreementLoggingField::create([
            'name' => 'Travel Miles',
            'field_type' => 'decimal',
            'is_active' => true,
        ]);
        $agreement->agreementLoggingFields()->sync([
            $agreementField->id => ['is_required' => true],
        ]);

        $contactFamily = ContactFamily::create([
            'name' => 'Training',
            'active' => true,
            'sort_order' => 1,
        ]);
        $contactField = ContactFamilyLoggingField::create([
            'name' => 'Session Format',
            'field_type' => 'select',
            'options_json' => ['In Person', 'Virtual'],
            'is_active' => true,
        ]);
        $contactFamily->contactFamilyLoggingFields()->sync([
            $contactField->id => ['is_required' => false],
        ]);

        $response = $this->actingAs($admin)->get(route('activities.create'));

        $response->assertOk();
        $response->assertSee('Agreement Logging Fields');
        $response->assertSee('Travel Miles');
        $response->assertSee('Session Format');
    }

    public function test_contact_family_edit_page_lists_available_logging_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contactFamily = ContactFamily::create([
            'name' => 'Coaching',
            'active' => true,
            'sort_order' => 1,
        ]);
        $field = ContactFamilyLoggingField::create([
            'name' => 'Classification Notes',
            'field_type' => 'textarea',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('contact-families.edit', $contactFamily));

        $response->assertOk();
        $response->assertSee('Classification Logging Fields');
        $response->assertSee('Classification Notes');
    }
}