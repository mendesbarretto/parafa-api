<!doctype html>
<html lang="pt-BR"><body>
<p>Recebemos uma solicitação de remoção ou correção no Parafa CNPJ.</p>
<p>Protocolo: {{ $protocol }}</p>
<p><a href="{{ $confirmationUrl }}">Conferir e confirmar solicitação</a></p>
<p>O link para confirmar o e-mail vence em 24 horas. @if ($action === 'removal') Depois da confirmação, o CNPJ será removido do site automaticamente após uma hora. @else Depois da confirmação, seu pedido de alteração será enviado à equipe do Parafa. @endif</p>
<p>Guarde este e-mail: pelo mesmo link você pode acompanhar o status por 30 dias. Se não fez o pedido, ignore esta mensagem.</p>
</body></html>
