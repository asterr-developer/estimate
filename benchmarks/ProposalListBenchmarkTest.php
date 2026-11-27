<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProposalListBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_page_at_three_archive_sizes(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $snapshot = json_encode(['title' => 'Разработка', 'client' => 'Клиент', 'total' => 10000,
            'lines' => array_fill(0, 8, ['name' => 'Работа', 'quantity' => 1, 'price' => 1250])]);

        foreach ([1000, 10000, 100000] as $size) {
            DB::table('proposals')->delete();
            DB::statement("WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i+1 FROM n WHERE i < ?)
                INSERT INTO proposals (id, owner, share_token, snapshot, created_at)
                SELECT i, ?, printf('%064x', i), ?, '2026-08-01' FROM n", [$size, 'user:'.$owner->id, $snapshot]);

            $before = $this->measure('/api/proposals');
            $after = $this->measure('/api/proposal-list');
            printf("\n%d rows: p95 %.2f -> %.2f ms; bytes %d -> %d\n", $size, $before[0], $after[0], $before[1], $after[1]);
            $query = '/api/proposal-list?'.http_build_query(['q' => 'РАЗРАБОТКА', 'status' => 'pending', 'page' => 2]);
            $this->getJson($query)->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('items.0.id', $size - 25);
        }
    }

    private function measure(string $path): array
    {
        $samples = [];
        for ($i = 0; $i < 12; $i++) {
            $start = hrtime(true);
            $response = $this->getJson($path);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $response->assertOk();
            $bytes = strlen($response->getContent());
            unset($response);
            if ($i >= 2) {
                $samples[] = $elapsed;
            }
        }
        sort($samples);

        return [$samples[9], $bytes];
    }
}
