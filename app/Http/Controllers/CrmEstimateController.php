<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CrmDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmEstimateController extends Controller
{
    public function __invoke(Request $request, string $deal)
    {
        $token = (string) config('services.crm_estimates.token');
        abort_unless(strlen($token) >= 32 && hash_equals($token, (string) $request->bearerToken()), 401);
        $owner = User::find(config('services.crm_estimates.owner_id') ?: 0);
        abort_unless($owner, 503);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'title' => 'required|string|max:120',
                'client' => 'required|string|max:100',
            ]);
            CrmDraft::create($owner->id, $deal, $data);
        }

        $draft = DB::table('workspace_records')->where('crm_deal_key', $deal)->where('user_id', $owner->id)->first();
        abort_unless($draft, 404);
        $accepted = DB::table('proposals')->where('crm_deal_key', $deal)
            ->where('owner', 'user:'.$owner->id)->whereNotNull('accepted_at')
            ->orderByDesc('accepted_at')->orderByDesc('id')->first();

        return response()->json([
            'draft_id' => $draft->id,
            'accepted' => $accepted ? [
                'number' => str_pad((string) $accepted->id, 4, '0', STR_PAD_LEFT),
                'total_minor' => json_decode($accepted->snapshot, true, flags: JSON_THROW_ON_ERROR)['total'],
                'token' => $accepted->share_token,
            ] : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
