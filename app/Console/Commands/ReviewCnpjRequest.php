<?php

namespace App\Console\Commands;

use App\Models\CnpjRequest;
use App\Models\CnpjSuppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReviewCnpjRequest extends Command
{
    protected $signature = 'cnpj:requests {id?} {--decision= : approve, reject ou resolve} {--reviewer=} {--notes=}';

    protected $description = 'Lista solicitações confirmadas e registra a análise de remoção/correção';

    public function handle(): int
    {
        if (! $this->argument('id')) {
            $this->table(['Protocolo', 'CNPJ', 'Ação', 'Confirmado em'], CnpjRequest::where('status', 'pending_review')
                ->oldest()->limit(100)->get(['id', 'cnpj', 'action', 'verified_at'])->toArray());

            return self::SUCCESS;
        }
        $entry = CnpjRequest::find($this->argument('id'));
        if (! $entry) {
            $this->error('Protocolo não encontrado.');

            return self::FAILURE;
        }
        if (! $this->option('decision')) {
            $this->line(json_encode($entry->only(['id', 'cnpj', 'name', 'email', 'relationship', 'action', 'message', 'status', 'review_notes']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }
        $decision = $this->option('decision');
        if (! in_array($decision, ['approve', 'reject', 'resolve'], true) || ! trim((string) $this->option('reviewer')) || ! trim((string) $this->option('notes'))) {
            $this->error('Informe --decision=approve|reject|resolve, --reviewer e --notes com a justificativa da análise.');

            return self::FAILURE;
        }
        if (($decision === 'approve' && $entry->action !== 'removal') || ($decision === 'resolve' && $entry->action !== 'correction')) {
            $this->error('approve aplica remoção; resolve registra uma correção já realizada na base.');

            return self::FAILURE;
        }

        return DB::connection('pgsql2')->transaction(function () use ($entry, $decision): int {
            $entry = CnpjRequest::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'pending_review' || ! $entry->verified_at) {
                $this->error('Somente pedidos confirmados e pendentes podem ser analisados.');

                return self::FAILURE;
            }
            if ($decision === 'approve') {
                CnpjSuppression::firstOrCreate(['cnpj' => $entry->cnpj], ['request_id' => $entry->id]);
            }
            $entry->update([
                'status' => match ($decision) {
                    'approve' => 'removed', 'reject' => 'rejected', 'resolve' => 'resolved'
                },
                'reviewed_by' => $this->option('reviewer'),
                'review_notes' => $this->option('notes'),
                'reviewed_at' => now(),
            ]);
            $this->info('Análise registrada. Status: '.$entry->status);

            return self::SUCCESS;
        });
    }
}
