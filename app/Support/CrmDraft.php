<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CrmDraft
{
    public static function create(int $owner, string $deal, array $data): object
    {
        $data['lines'] = [['name' => '', 'unit' => 'усл.', 'price' => '', 'quantity' => 1]];
        $data['discount'] = 0;
        DB::table('workspace_records')->insertOrIgnore([
            'user_id' => $owner,
            'kind' => 'drafts',
            'crm_deal_key' => $deal,
            'payload' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return DB::table('workspace_records')->where('crm_deal_key', $deal)
            ->where('user_id', $owner)->firstOrFail();
    }
}
