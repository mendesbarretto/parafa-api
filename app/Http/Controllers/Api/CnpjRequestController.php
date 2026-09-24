<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CnpjRequestConfirmation;
use App\Models\CnpjRequest;
use App\Models\CompanyPg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CnpjRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cnpj' => ['required', 'string', 'regex:/^[A-Z0-9]{12}[0-9]{2}$/'],
            'name' => 'required|string|min:2|max:150',
            'email' => 'required|email|max:254',
            'relationship' => 'required|in:responsavel,representante,titular,outro',
            'action' => 'required|in:removal,correction',
            'message' => 'required|string|min:10|max:3000',
            'acknowledged' => 'required|accepted',
            'website' => 'nullable|string|max:0',
        ]);
        CompanyPg::where('cnpj', $data['cnpj'])->firstOrFail();
        $data['email'] = mb_strtolower($data['email']);
        $rateKey = 'cnpj:request:'.hash('sha256', $data['email']);
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return response()->json(['message' => 'Aguarde antes de enviar outra solicitação.'], 429);
        }
        RateLimiter::hit($rateKey, 3600);

        $companyRateKey = 'cnpj:request:company:'.$data['cnpj'];
        if (RateLimiter::tooManyAttempts($companyRateKey, 5)) {
            return response()->json(['message' => 'Aguarde antes de enviar outra solicitação para este CNPJ.'], 429);
        }
        RateLimiter::hit($companyRateKey, 3600);
        if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
            return response()->json(['message' => 'O envio de e-mail está temporariamente indisponível.'], 503);
        }

        $token = Str::random(64);
        $entry = new CnpjRequest;
        $entry->id = (string) Str::uuid();
        $entry->fill(collect($data)->only(['cnpj', 'name', 'email', 'relationship', 'action', 'message'])->all());
        $entry->token_hash = hash('sha256', $token);
        $entry->expires_at = now()->addDay();
        $entry->status = 'pending_email';
        $entry->save();

        $url = rtrim(config('cnpj.site_url'), '/').'/remocao#id='.$entry->id.'&token='.$token;
        try {
            Mail::to($entry->email)->send(new CnpjRequestConfirmation($url, $entry->id, $entry->action));
        } catch (\Throwable $exception) {
            $entry->delete();
            report($exception);

            return response()->json(['message' => 'Não foi possível enviar a confirmação. Tente novamente mais tarde.'], 503);
        }

        return response()->json(['protocol' => $entry->id, 'message' => 'Confira seu e-mail para confirmar a solicitação.'], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function confirm(string $id, Request $request): JsonResponse
    {
        $token = $request->validate(['token' => 'required|string|size:64'])['token'];
        $entry = DB::connection('pgsql2')->transaction(function () use ($id, $token): CnpjRequest {
            $entry = CnpjRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($entry->token_hash, hash('sha256', $token)), 404);
            abort_if($entry->created_at->addDays(30)->isPast(), 410, 'Link expirado. Entre em contato com o Parafa.');
            if ($entry->status === 'pending_email') {
                abort_if($entry->expires_at->isPast(), 410, 'Link expirado. Envie uma nova solicitação.');
                $entry->update(['verified_at' => now(), 'status' => $entry->action === 'removal' ? 'scheduled_removal' : 'pending_review']);
            }

            return $entry;
        });

        return $this->statusResponse($entry);
    }

    public function status(string $id, Request $request): JsonResponse
    {
        $token = $request->validate(['token' => 'required|string|size:64'])['token'];
        $entry = CnpjRequest::findOrFail($id);
        abort_unless(hash_equals($entry->token_hash, hash('sha256', $token)), 404);
        abort_if($entry->created_at->addDays(30)->isPast(), 410, 'Link expirado. Entre em contato com o Parafa.');

        return $this->statusResponse($entry);
    }

    private function statusResponse(CnpjRequest $entry): JsonResponse
    {
        return response()->json(['protocol' => $entry->id, 'status' => $entry->status])
            ->header('Cache-Control', 'private, no-store');
    }
}
