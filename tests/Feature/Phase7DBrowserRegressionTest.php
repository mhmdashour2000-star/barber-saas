<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7DBrowserRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Browser regressions require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-07 22:30:00', 'UTC'));
        $manager = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->company = Company::create([
            'name' => 'Browser Regression Salon', 'code' => 'QA-BROWSER', 'phone' => '+905551117700',
            'manager_id' => $manager->id, 'status' => Company::STATUS_ACTIVE,
        ]);
        $this->actingAs($manager);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unchecked_closure_form_submits_explicit_false_and_saves_custom_hours(): void
    {
        $response = $this->get(route('company.availability.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        // Unchecked checkboxes are omitted by browsers; the hidden field must supply false.
        $hidden = $xpath->query('//input[@name="is_closed" and @type="hidden"]');
        $this->assertCount(1, $hidden);
        $this->post(route('company.availability.exceptions.store'), [
            'type' => 'company', 'date' => '2030-01-08',
            'is_closed' => $hidden->item(0)->getAttribute('value'),
            'windows' => [['start_time' => '11:00', 'end_time' => '16:00']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $exception = $this->company->availabilityExceptions()->firstOrFail();
        $this->assertFalse($exception->is_closed);
        $this->assertSame('11:00', substr($exception->windows()->firstOrFail()->start_time, 0, 5));
    }

    public function test_availability_form_uses_istanbul_day_for_default_and_minimum(): void
    {
        $this->get(route('company.availability.index'))->assertOk()
            ->assertSee('min="2030-01-08"', false)
            ->assertSee('value="2030-01-08"', false);
    }

    public function test_custom_hours_and_unchecked_state_survive_validation_failure(): void
    {
        $data = ['type' => 'company', 'date' => '2030-01-08', 'is_closed' => '0',
            'reason' => 'Retain my input', 'windows' => [['start_time' => '20:00', 'end_time' => '10:00']]];
        $this->from(route('company.availability.index'))->post(route('company.availability.exceptions.store'), $data)
            ->assertRedirect()->assertSessionHasErrors('windows.0')->assertSessionHasInput('windows.0.start_time', '20:00');
        $response = $this->withSession(['_old_input' => $data])->get(route('company.availability.index'))->assertOk();
        $response->assertSee('value="20:00"', false)->assertSee('value="10:00"', false)->assertSee('Retain my input');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertCount(0, $xpath->query('//input[@id="is_closed" and @checked]'));
        $this->assertCount(0, $xpath->query('//div[@id="custom-windows-group" and contains(@class,"hidden")]'));
    }
}
