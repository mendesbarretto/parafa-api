<?php

namespace App\Console\Commands;

use App\Mail\CnpjCorrectionRequested;
use App\Models\CnpjRequest;
use App\Models\CnpjSuppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ProcessCnpjRequests extends Command
{
    protected $signature = 'cnpj:process-requests';

    protected $description = 'Remove CNPJs uma hora após confirmação e envia pedidos de alteração ao atendimento';

    public function handle(): int
    {
        $failed = false;
        $due = CnpjRequest::where('action', 'removal')
            ->whereIn('status', ['scheduled_removal', 'pending_review'])
            ->whereNotNull('verified_at')->where('verified_at', '<=', now()->subHour())
            ->orderBy('verified_at')->limit(500)->pluck('id');

        foreach ($due as $id) {
            try {
                DB::connection('pgsql2')->transaction(function () use ($id): void {
                    $entry = CnpjRequest::whereKey($id)->lockForUpdate()->firstOrFail();
                    if ($entry->action !== 'removal' || ! in_array($entry->status, ['scheduled_removal', 'pending_review'], true)
                        || ! $entry->verified_at || $entry->verified_at->greaterThan(now()->subHour())) {
                        return;
                    }
                    CnpjSuppression::firstOrCreate(['cnpj' => $entry->cnpj], ['request_id' => $entry->id]);
                    $entry->update([
                        'status' => 'removed', 'reviewed_by' => 'automatic', 'reviewed_at' => now(),
                        'review_notes' => 'Remoção automática uma hora após confirmação do e-mail.',
                    ]);
                });
            } catch (Throwable $exception) {
                report($exception);
                $failed = true;
            }
        }

        $corrections = CnpjRequest::where('action', 'correction')->whereNotNull('verified_at')
            ->whereNull('correction_notified_at')->orderBy('verified_at')->limit(50)->pluck('id');
        foreach ($corrections as $id) {
            try {
                DB::connection('pgsql2')->transaction(function () use ($id): void {
                    $entry = CnpjRequest::whereKey($id)->lockForUpdate()->firstOrFail();
                    if ($entry->action !== 'correction' || ! $entry->verified_at || $entry->correction_notified_at) {
                        return;
                    }
                    if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
                        throw new RuntimeException('Configure um transporte SMTP real para enviar solicitações.');
                    }
                    Mail::to(config('cnpj.correction_email'))->send(new CnpjCorrectionRequested($entry));
                    $entry->update(['correction_notified_at' => now()]);
                });
            } catch (Throwable $exception) {
                report($exception);
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('Alguns pedidos falharam e serão tentados novamente na próxima execução.');

            return self::FAILURE;
        }
        $this->info('Pedidos processados.');

        return self::SUCCESS;
    }
}
