<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmEstimateTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_reuses_the_draft_and_reads_only_the_accepted_estimate(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = str_repeat('x', 40);
        config(['services.crm_estimates.token' => $token, 'services.crm_estimates.owner_id' => $owner->id]);
        $url = '/api/crm/v1/estimates/portfolio:42';
        $headers = ['Authorization' => 'Bearer '.$token];
        $incoming = ['title' => 'Групповая заявка', 'client' => 'Тестовая компания'];

        $this->postJson($url, $incoming)->assertUnauthorized();
        $this->getJson($url, $headers)->assertNotFound();
        $id = $this->postJson($url, $incoming, $headers)->assertOk()->json('draft_id');
        $this->postJson($url, $incoming, $headers)->assertOk()->assertJsonPath('draft_id', $id);
        $this->assertDatabaseCount('workspace_records', 1);

        $payload = $incoming + [
            'draft_id' => $id, 'sender' => 'Подрядчик', 'discount' => 0,
            'lines' => [['name' => 'Работа', 'quantity' => 2, 'price' => '125.50']],
        ];
        $this->actingAs($other)->postJson('/api/proposals', $payload)->assertNotFound();
        $proposal = $this->actingAs($owner)->postJson('/api/proposals', $payload)->assertCreated()->json();
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('accepted', null);
        $this->postJson('/api/proposals/'.$proposal['token'].'/accept', ['name' => 'Клиент'])->assertOk();

        unset($payload['draft_id']);
        $payload['lines'][0]['price'] = '200.00';
        $revision = $this->postJson('/api/proposals', $payload + ['revision_of' => $proposal['id']])->assertCreated()->json();
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('accepted.total_minor', 25100);
        $this->postJson('/api/proposals/'.$revision['token'].'/accept', ['name' => 'Клиент'])->assertOk();
        $this->getJson($url, $headers)->assertOk()
            ->assertJsonPath('accepted.total_minor', 40000)
            ->assertJsonPath('accepted.token', $revision['token']);
        config(['services.erp_estimates.token' => $token, 'services.erp_estimates.owner_id' => $owner->id]);
        $this->getJson('/api/erp/v1/estimates')->assertUnauthorized();
        $this->getJson('/api/erp/v1/estimates', $headers)->assertOk()
            ->assertJsonCount(2, 'estimates')->assertJsonPath('estimates.1.total_minor', 40000)
            ->assertJsonPath('estimates.1.lines.0.amount_minor', 40000)
            ->assertDontSee($revision['token']);
        config(['services.crm_estimates.url' => 'https://crm.example.test', 'services.crm_estimates.source' => 'portfolio']);
        \Illuminate\Support\Facades\Http::fake([
            'https://crm.example.test/api/estimate/v1/deals/42' => \Illuminate\Support\Facades\Http::response($incoming + ['id' => 42]),
            'https://crm.example.test/api/estimate/v1/deals?page=1' => \Illuminate\Support\Facades\Http::response(['data' => [$incoming + ['id' => 42]]]),
        ]);
        $this->actingAs($other)->getJson('/api/workspace/crm-deals')->assertForbidden();
        $this->actingAs($owner)->getJson('/api/workspace/crm-deals')->assertOk()->assertJsonPath('deals.0.id', 42);
        $this->postJson('/api/workspace/crm-deals/42')->assertOk()->assertJsonPath('draft_id', $id);
        $this->assertDatabaseCount('workspace_records', 1);
        $this->deleteJson('/api/workspace/drafts/'.$id)->assertConflict();
    }
}
