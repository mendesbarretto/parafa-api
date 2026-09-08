# Segurança da API Parafa - Backend Real 

## Situação Atual ✅

O **frontend já funciona perfeitamente** com o backend! As rotas coincidem:

- Frontend chama: `http://172.17.0.1:8000/api/empresas`
- Backend responde: `GET /api/empresas` ✅

## O que foi implementado

### 🔒 **Validação de Origem Inteligente**

O middleware `CorsMiddleware` agora valida:

1. **Ambiente de desenvolvimento**: Aceita tudo (`local`, `dev`)
2. **Produção**: Validação rigorosa com 3 métodos:
   - ✅ API Key (`X-API-Key` header)
   - ✅ Origin autorizada (domínios específicos)
   - ✅ IP autorizado (para requisições server-to-server)

### 🌐 **Origens Permitidas**
```php
$allowedOrigins = [
    'https://parafa.com.br',      // Produção
    'https://www.parafa.com.br',  // Produção com www
    'http://localhost:3000',      // Dev local
    'http://127.0.0.1:3000',     // Dev local
    'http://172.17.0.1:3000',    // Docker frontend
];
```

### 🔑 **IPs Autorizados**
```php
$allowedIps = [
    '127.0.0.1',          // Localhost
    '172.17.0.1',         // Docker frontend
    '172.18.0.1',         // Docker networks
    '147.182.248.223',    // Servidor
];
```

### 🔐 **API Key**
```bash
# No .env
INTERNAL_API_KEY=parafa_api_2024_secure_key_xyz789
```

## Como funciona

### 🚀 **Frontend (funcionando)**
```javascript
// Requisições automáticas do Next.js funcionam
const response = await fetch('http://172.17.0.1:8000/api/empresas');
```

**Por que funciona:**
- Ambiente é `local` → Validação desabilitada ✅
- Em produção: IP `172.17.0.1` está na whitelist ✅

### 🛡️ **Produção (protegida)**
```javascript
// Método 1: Com API Key (recomendado)
fetch('https://api.parafa.com.br/api/empresas', {
    headers: {
        'X-API-Key': 'parafa_api_2024_secure_key_xyz789'
    }
});

// Método 2: Do frontend autorizado (automático)
// Funciona se a requisição vier de parafa.com.br
```

### ❌ **Bloqueado**
```bash
# Requisições de origens não autorizadas
curl https://api.parafa.com.br/api/empresas
# Retorna: 403 - "Acesso não autorizado"
```

## Testando

### ✅ **Desenvolvimento (atual)**
```bash
# Funciona - ambiente local
curl http://localhost:8000/api/empresas
```

### ✅ **Com API Key**
```bash
# Funciona - API key válida
curl -H "X-API-Key: parafa_api_2024_secure_key_xyz789" \
     https://api.parafa.com.br/api/empresas
```

### ❌ **Sem autorização**
```bash
# Bloqueado - origem não autorizada
curl https://api.parafa.com.br/api/empresas
# Response: {"error": "Acesso não autorizado"}
```

## Status

### ✅ **O que está funcionando:**
- Frontend → Backend (desenvolvimento) ✅
- Validação de segurança implementada ✅
- API Key configurada ✅
- IPs Docker autorizados ✅

### 🔄 **Para produção:**
1. Mudar `APP_ENV=production`
2. Configurar domínio real da API
3. Atualizar frontend para usar domínio de produção

## Resumo

**Seu frontend já está funcionando normalmente!** A validação só é ativada em produção, mantendo o desenvolvimento fluido. Em produção, apenas requisições autorizadas (API key, domínios ou IPs específicos) conseguem acessar a API.

A implementação é inteligente: **não quebra nada em desenvolvimento**, mas **protege tudo em produção**.
