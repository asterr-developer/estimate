<?php

namespace App\Http\Controllers;

use App\Support\CrmDraft;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class CrmWorkspaceController extends Controller
{
    public function __invoke(Request $request, ?int $deal = null)
    {
        $owner = (int) config('services.crm_estimates.owner_id');
        abort_unless($owner > 0 && (int) $request->user()->id === $owner, 403);
        $base = rtrim((string) config('services.crm_estimates.url'), '/');
        $token = (string) config('services.crm_estimates.token');
        $source = (string) config('services.crm_estimates.source');
        abort_unless($base && strlen($token) >= 32 && preg_match('/^[a-z0-9-]{1,50}$/D', $source), 503, 'Подключение к CRM требует настройки.');
        $page = $request->validate(['page' => 'sometimes|integer|min:1|max:10000'])['page'] ?? 1;

        try {
            $response = Http::acceptJson()->withToken($token)->connectTimeout(1)->timeout(3)->withoutRedirecting()
                ->get($base.'/api/estimate/v1/deals'.($deal === null ? '' : '/'.$deal), $deal === null ? ['page' => $page] : []);
        } catch (ConnectionException) {
            abort(503, 'CRM временно недоступна.');
        }
        abort_if($response->notFound() && $deal !== null, 404, 'Сделка недоступна.');
        abort_unless($response->ok(), 503, 'CRM временно недоступна.');

        if ($deal === null) {
            return ['deals' => $response->json('data') ?? [], 'has_next' => $response->json('next_page_url') !== null];
        }

        $validator = Validator::make((array) $response->json(), ['title' => 'required|string|max:120', 'client' => 'required|string|max:100']);
        abort_if($validator->fails(), 502, 'CRM вернула неполные данные сделки.');
        $draft = CrmDraft::create($owner, $source.':'.$deal, $validator->validated());

        return ['draft_id' => $draft->id];
    }
}
