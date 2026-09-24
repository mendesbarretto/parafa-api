<?php

namespace Tests\Feature;

use App\Mail\CnpjRequestConfirmation;
use App\Models\CnpjRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesCnpjData;
use Tests\TestCase;

class CnpjPrivacyTest extends TestCase
{
    use CreatesCnpjData;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.pgsql2' => config('database.connections.sqlite')]);
        DB::purge('pgsql2');
    }

    private function submitRequest(string $action = 'removal'): array
    {
        Mail::fake();
        $response = $this->postJson('/api/cnpj/requests', [
            'cnpj' => '16410532000137', 'name' => 'Responsável', 'email' => 'responsavel@example.test',
            'relationship' => 'responsavel', 'action' => $action, 'message' => 'Solicito revisão dos dados publicados.',
            'acknowledged' => true, 'status' => 'removed',
        ])->assertCreated();
        $mail = Mail::sent(CnpjRequestConfirmation::class)->first();
        Mail::assertSent(CnpjRequestConfirmation::class, fn ($sent): bool => $sent->hasTo('responsavel@example.test'));
        parse_str(parse_url($mail->confirmationUrl, PHP_URL_FRAGMENT), $credentials);
        $this->assertSame($response->json('protocol'), $credentials['id']);

        return $credentials;
    }

    public function test_creates_request_and_confirmation_only_queues_review(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest();
        $entry = CnpjRequest::findOrFail($credentials['id']);
        $this->assertSame('pending_email', $entry->status);
        $this->assertNotSame($credentials['token'], $entry->token_hash);
        $this->getJson('/api/cnpj/requests/'.$entry->id.'/confirm')->assertMethodNotAllowed();
        $this->assertSame('pending_email', $entry->fresh()->status);

        $this->postJson('/api/cnpj/requests/'.$entry->id.'/confirm', ['token' => $credentials['token']])
            ->assertOk()->assertExactJson(['protocol' => $entry->id, 'status' => 'pending_review']);
        $this->assertNotNull($entry->fresh()->verified_at);
        $this->getJson('/api/cnpj/companies/16410532000137')->assertOk();
        $this->assertDatabaseCount('cnpj_suppressions', 0, 'pgsql2');
    }

    public function test_rejects_invalid_and_expired_confirmation_without_changing_request(): void
    {
        $this->freezeTime();
        $this->createCompanyData();
        $credentials = $this->submitRequest();
        $url = '/api/cnpj/requests/'.$credentials['id'].'/confirm';
        $this->postJson($url, ['token' => str_repeat('x', 64)])->assertNotFound();
        $this->travel(25)->hours();
        $this->postJson($url, ['token' => $credentials['token']])->assertGone();
        $this->assertSame('pending_email', CnpjRequest::findOrFail($credentials['id'])->status);
    }

    public function test_removal_hides_company_from_warm_caches_and_future_imports(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest();
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/confirm', ['token' => $credentials['token']])->assertOk();
        foreach (['/api/cnpj/companies/16410532000137', '/api/cnpj/companies/11111111000191', '/api/cnpj/companies?search=16410532000137', '/api/cnpj/cities/salvador-ba', '/api/cnpj/sitemaps/1'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->artisan('cnpj:requests', ['id' => $credentials['id'], '--decision' => 'approve', '--reviewer' => 'Equipe', '--notes' => 'Vínculo conferido pelo atendimento.'])->assertSuccessful();
        $this->assertDatabaseHas('cnpj_suppressions', ['cnpj' => '16410532000137'], 'pgsql2');
        DB::connection('pgsql2')->table('companies')->where('cnpj', '16410532000137')->update(['name' => 'Nome reimportado']);

        $this->getJson('/api/cnpj/companies/16410532000137')->assertNotFound();
        $this->getJson('/api/cnpj/companies?search=16410532000137')->assertJsonCount(0, 'data')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/cnpj/companies/11111111000191')->assertJsonCount(0, 'related');
        $this->getJson('/api/cnpj/cities/salvador-ba')->assertJsonCount(1, 'data')->assertJsonPath('data.0.cnpj', '11111111000191');
        $this->getJson('/api/cnpj/sitemaps/1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.cnpj', '11111111000191');
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/status', ['token' => $credentials['token']])
            ->assertExactJson(['protocol' => $credentials['id'], 'status' => 'removed']);
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/confirm', ['token' => $credentials['token']])->assertJsonPath('status', 'removed');
    }

    public function test_cannot_approve_unverified_request(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest();
        $this->artisan('cnpj:requests', ['id' => $credentials['id'], '--decision' => 'approve', '--reviewer' => 'Equipe', '--notes' => 'Teste'])->assertFailed();
        $this->assertDatabaseCount('cnpj_suppressions', 0, 'pgsql2');
    }

    public function test_requires_review_notes_and_rejects_wrong_action(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest('correction');
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/confirm', ['token' => $credentials['token']])->assertOk();
        $this->artisan('cnpj:requests', ['id' => $credentials['id'], '--decision' => 'resolve'])->assertFailed();
        $this->artisan('cnpj:requests', ['id' => $credentials['id'], '--decision' => 'approve', '--reviewer' => 'Equipe', '--notes' => 'Teste'])->assertFailed();
        $this->assertDatabaseCount('cnpj_suppressions', 0, 'pgsql2');
    }

    public function test_resolved_correction_refreshes_cached_company_data(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest('correction');
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/confirm', ['token' => $credentials['token']])->assertOk();
        $this->getJson('/api/cnpj/companies/16410532000137')->assertJsonPath('data.name', 'Empresa de teste');
        DB::connection('pgsql2')->table('companies')->where('id', 1)->update(['name' => 'Empresa corrigida']);
        $this->artisan('cnpj:requests', ['id' => $credentials['id'], '--decision' => 'resolve', '--reviewer' => 'Equipe', '--notes' => 'Cadastro corrigido na base.'])->assertSuccessful();
        $this->getJson('/api/cnpj/companies/16410532000137')->assertJsonPath('data.name', 'Empresa corrigida');
    }

    public function test_rejecting_request_keeps_company_published(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest();
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/confirm', ['token' => $credentials['token']])->assertOk();
        $this->artisan('cnpj:requests', ['id' => $credentials['id'], '--decision' => 'reject', '--reviewer' => 'Equipe', '--notes' => 'Vínculo não comprovado.'])->assertSuccessful();
        $this->getJson('/api/cnpj/companies/16410532000137')->assertOk();
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/status', ['token' => $credentials['token']])->assertJsonPath('status', 'rejected')->assertJsonMissingPath('review_notes');
    }

    public function test_rejects_missing_fields_and_honeypot_without_sending_email(): void
    {
        $this->createCompanyData();
        Mail::fake();
        $this->postJson('/api/cnpj/requests', [])->assertUnprocessable()->assertJsonValidationErrors(['cnpj', 'name', 'email', 'relationship', 'action', 'message', 'acknowledged']);
        $this->postJson('/api/cnpj/requests', ['website' => 'https://spam.example'])->assertJsonValidationErrors(['website']);
        $this->assertDatabaseCount('cnpj_requests', 0, 'pgsql2');
        Mail::assertNothingSent();
    }

    public function test_unknown_token_cannot_read_request_details(): void
    {
        $this->createCompanyData();
        $credentials = $this->submitRequest();
        $this->postJson('/api/cnpj/requests/'.$credentials['id'].'/status', ['token' => str_repeat('z', 64)])->assertNotFound();
    }

    public function test_limits_repeated_emails(): void
    {
        $this->createCompanyData();
        for ($i = 0; $i < 3; $i++) {
            $this->submitRequest();
        }
        $this->postJson('/api/cnpj/requests', [
            'cnpj' => '16410532000137', 'name' => 'Responsável', 'email' => 'responsavel@example.test',
            'relationship' => 'responsavel', 'action' => 'removal', 'message' => 'Solicito revisão dos dados publicados.', 'acknowledged' => true,
        ])->assertTooManyRequests()->assertJsonPath('message', 'Aguarde antes de enviar outra solicitação.');
    }

    public function test_mail_failure_returns_503_without_leaving_an_unusable_request(): void
    {
        $this->createCompanyData();
        Mail::shouldReceive('to')->once()->with('responsavel@example.test')->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->postJson('/api/cnpj/requests', [
            'cnpj' => '16410532000137', 'name' => 'Responsável', 'email' => 'responsavel@example.test',
            'relationship' => 'responsavel', 'action' => 'removal', 'message' => 'Solicito revisão dos dados publicados.', 'acknowledged' => true,
        ])->assertServiceUnavailable()->assertJsonPath('message', 'Não foi possível enviar a confirmação. Tente novamente mais tarde.');
        $this->assertDatabaseCount('cnpj_requests', 0, 'pgsql2');
    }

    public function test_mail_template_escapes_url_and_protocol(): void
    {
        $mail = new CnpjRequestConfirmation('https://example.test/"><script>alert(1)</script>', '<script>bad</script>');
        $html = $mail->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
