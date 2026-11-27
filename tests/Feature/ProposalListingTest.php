<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProposalListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_filters_archive_and_preserves_access_to_version_details(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $snapshot = json_encode(['title' => 'Сайт', 'client' => 'ООО Север', 'total' => 10000,
            'lines' => [['name' => 'Вёрстка', 'quantity' => 1, 'price' => 10000]]]);
        $token = str_repeat('a', 64);

        DB::table('proposals')->insert([
            'owner' => 'user:'.$owner->id, 'share_token' => $token, 'snapshot' => $snapshot,
            'created_at' => now(), 'revision_requested_at' => now(),
        ]);

        $this->actingAs($other)->getJson('/api/proposal-list')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson('/api/proposal-list/'.$token)->assertNotFound();

        $this->actingAs($owner)->getJson('/api/proposal-list?status=changes&q='.urlencode('СЕВЕР'))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.payment_summary.remaining', 10000)
            ->assertJsonMissingPath('items.0.snapshot.lines');
        $this->getJson('/api/proposal-list?status=pending')->assertJsonCount(0, 'items');
        $this->getJson('/api/proposal-list/'.$token)->assertOk()->assertJsonPath('item.snapshot.lines.0.name', 'Вёрстка');
        $this->postJson('/api/proposals/'.$token.'/archive', ['archived' => true, 'compact' => true])->assertNoContent();
        $this->getJson('/api/proposal-list')->assertJsonCount(0, 'items');
        $this->getJson('/api/proposal-list?archived=1')->assertJsonCount(1, 'items');
    }
}
