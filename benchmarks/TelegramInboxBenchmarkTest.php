<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelegramInboxBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_response_time_with_and_without_indexes(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $index = require database_path('migrations/2026_11_27_000107_index_telegram_inbox.php');
        $index->down();

        foreach ([1000, 10000, 100000] as $size) {
            DB::table('mini_messages')->delete();
            DB::table('mini_requests')->delete();
            DB::statement("WITH RECURSIVE n(i) AS (SELECT 1 UNION ALL SELECT i+1 FROM n WHERE i < ?)
                INSERT INTO mini_requests (id, user_id, visitor, customer, items, subject, created_at, updated_at)
                SELECT i, ?, 'tg:' || (i % 100), 'Клиент', '[]', 'Вопрос', '2026-08-01',
                    datetime('2026-08-01', '+' || i || ' seconds') FROM n", [$size, $owner->id]);
            DB::statement("INSERT INTO mini_messages (mini_request_id, from_operator, body, created_at)
                SELECT id, 0, 'Сообщение', created_at FROM mini_requests");

            [$before, $original] = $this->measureInbox();
            $index->up();
            [$after, $current] = $this->measureInbox();
            $this->assertSame($original, $current);
            $this->assertCount(40, $current);
            printf("\n%d threads p50 %.3f -> %.3f ms, p95 %.3f -> %.3f ms\n",
                $size, ($before[14] + $before[15]) / 2, ($after[14] + $after[15]) / 2, $before[28], $after[28]);
            $index->down();
        }

        $index->up();
    }

    private function measureInbox(): array
    {
        $samples = [];

        for ($i = 0; $i < 35; $i++) {
            $start = hrtime(true);
            $response = $this->getJson('/api/telegram/inbox');
            $elapsed = (hrtime(true) - $start) / 1e6;
            $response->assertOk();
            if ($i >= 5) {
                $samples[] = $elapsed;
            }
        }

        sort($samples);

        return [$samples, $response->json()];
    }
}
