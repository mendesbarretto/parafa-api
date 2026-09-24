<!doctype html>
<html lang="pt-BR"><body>
<h1>Solicitação de alteração de dados</h1>
<p>O solicitante confirmou o e-mail. Confira o pedido antes de alterar o cadastro.</p>
<dl>
<dt>Protocolo</dt><dd>{{ $request->id }}</dd>
<dt>CNPJ</dt><dd>{{ $request->cnpj }}</dd>
<dt>Nome</dt><dd>{{ $request->name }}</dd>
<dt>E-mail</dt><dd>{{ $request->email }}</dd>
<dt>Vínculo declarado</dt><dd>{{ $request->relationship }}</dd>
</dl>
<h2>Mensagem</h2>
<p style="white-space: pre-wrap">{{ $request->message }}</p>
<p>Você pode responder a este e-mail para falar com o solicitante.</p>
</body></html>
