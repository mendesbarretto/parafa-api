<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


## Solicitações do diretório CNPJ

As novas tabelas ficam no PostgreSQL CNPJ (`pgsql2`). A migration não remove cadastros existentes:

```sh
php artisan migrate --path=database/migrations/2026_09_23_204634_create_cnpj_privacy_tables.php --force
php artisan migrate --path=database/migrations/2026_09_24_144445_add_correction_notified_at_to_cnpj_requests_table.php --force
```

Configurar `CNPJ_SITE_URL` e um transporte SMTP real antes da publicação. O formulário envia confirmação com token aleatório, armazenado como hash; o link de confirmação vence em 24 horas e o acompanhamento fica disponível por 30 dias. Após a confirmação, remoções ficam agendadas por uma hora; alterações são encaminhadas por e-mail ao responsável definido em `CNPJ_CORRECTION_EMAIL` (padrão: `mendesbarretto@gmail.com`). A confirmação repetida não reinicia o prazo.

O Compose inclui o serviço `scheduler`, que executa `php artisan schedule:work`. O comando `cnpj:process-requests` roda a cada minuto: remove pedidos cujo prazo de uma hora venceu e envia notificações de alteração confirmadas. Em falha de SMTP, mantém o pedido para nova tentativa. Em hospedagem sem Docker, manter o scheduler do Laravel ativo. Não executar o processador manualmente na base real apenas para testar.

A remoção retira o cadastro do site e registra o bloqueio em `cnpj_suppressions`, impedindo republicação em importações futuras.

O comando abaixo lista pedidos confirmados pendentes. Informando um protocolo, mostra os dados para análise em terminal restrito:

```sh
php artisan cnpj:requests
php artisan cnpj:requests PROTOCOLO
```

O processamento automático não exige aprovação manual. Para uma intervenção administrativa, registrar a decisão e sua justificativa (uma aprovação manual pode antecipar a remoção):

```sh
php artisan cnpj:requests PROTOCOLO --decision=approve --reviewer="OPERADOR" --notes="Vínculo e pedido verificados pelo atendimento."
php artisan cnpj:requests PROTOCOLO --decision=reject --reviewer="OPERADOR" --notes="Motivo da decisão."
```

`approve` aplica apenas a pedidos de remoção: registra o CNPJ em `cnpj_suppressions`, sem apagar o cadastro de origem. Todas as consultas públicas do modelo excluem essa lista, incluindo novas importações da mesma empresa. **Não truncar a tabela de supressões nas importações**. Preservar também supressões e históricos de exclusão anteriores em qualquer recarga completa da base.

Para correções, atualizar os dados após a análise e então registrar `--decision=resolve`, com operador e justificativa. Essa decisão invalida as consultas em cache. O comando não altera automaticamente os campos enviados como texto livre pelo solicitante.

Os pedidos não têm uma tela administrativa pública. O atendimento recebe os pedidos de alteração por e-mail, pode consultar a fila pelo comando, e o solicitante acompanha o resultado pelo link recebido. `review_notes` e dados pessoais não aparecem na API pública de acompanhamento.

Os endpoints públicos usam cache interno do Laravel e respostas HTTP `private, no-store`. Não sobrepor esses cabeçalhos com cache de CDN. Ao publicar, purgar HTML e respostas da API que ficaram no cache da versão anterior.

Testes das alterações:

```sh
php artisan test --compact tests/Feature/CnpjCacheTest.php tests/Feature/CnpjPrivacyTest.php tests/Feature/CnpjSitemapTest.php
```

Os testes usam SQLite em memória e e-mails falsos; requerem a extensão PHP `pdo_sqlite`.
