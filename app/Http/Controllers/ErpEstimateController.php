<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErpEstimateController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = (string) config('services.erp_estimates.token');
        $owner = (int) config('services.erp_estimates.owner_id');
        abort_unless($owner > 0 && strlen($token) >= 32 && hash_equals($token, (string) $request->bearerToken()), 401);
        $page = $request->validate(['page' => 'sometimes|integer|min:1|max:10000']);
        $documents = DB::table('proposals')->where('owner', 'user:'.$owner)->whereNotNull('accepted_at')
            ->orderBy('accepted_at')->orderBy('id')->simplePaginate(50, page: $page['page'] ?? 1);

        return response()->json([
            'estimates' => $documents->getCollection()->map(function (object $row) {
                $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);

                return [
                    'id' => $row->id,
                    'number' => 'КП-'.str_pad((string) $row->id, 4, '0', STR_PAD_LEFT),
                    'revision_of' => $row->revision_of,
                    'crm_deal_key' => $row->crm_deal_key,
                    'accepted_at' => $row->accepted_at,
                    'currency' => 'RUB',
                    'total_minor' => $snapshot['total'],
                    'vat_minor' => $snapshot['vat_amount'] ?? 0,
                    'lines' => array_map(fn (array $line) => [
                        'name' => $line['name'], 'quantity' => $line['quantity'],
                        'unit' => $line['unit'] ?? null, 'amount_minor' => $line['amount'],
                    ], $snapshot['lines']),
                ];
            }),
            'next_page' => $documents->hasMorePages() ? $documents->currentPage() + 1 : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
