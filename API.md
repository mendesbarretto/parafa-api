# Parafa API - Documentação

API REST do Laravel para integração com o frontend Next.js.

## Base URL
```
http://localhost:8000/api
```

## Endpoints

### Empresas (Customers)

#### Listar Empresas
```
GET /api/empresas
```

**Parâmetros de Query:**
- `per_page`: número de itens por página (default: 5, max: 20)
- `category_id`: filtra por categoria
- `city_id`: filtra por cidade
- `state`: filtra por estado (UF)
- `search`: busca por nome ou descrição

**Exemplo:**
```
GET /api/empresas?per_page=10&state=PR&search=restaurante
```

**Resposta:**
```json
{
  "data": [
    {
      "id": "999999",
      "name": "Kilo Grill Comércio de Alimentos Ltda ME",
      "description": "",
      "neighborhood": "Jd Social",
      "city": "Curitiba",
      "state": "PR",
      "category_id": 7527,
      "city_id": "41000",
      "url": "/curitiba-pr/bares-e-restaurantes/restaurantes/999999/kilo-grill-comercio-de-alimentos-ltda-me"
    }
  ],
  "meta": {
    "per_page": "5",
    "total": 2
  }
}
```

#### Detalhe da Empresa
```
GET /api/empresas/{id}
```

**Resposta:**
```json
{
  "id": "999999",
  "name": "Kilo Grill Comércio de Alimentos Ltda ME",
  "slogan": "",
  "description": "",
  "address": "Fagundes Varela",
  "number": 1954,
  "complement": "tr",
  "neighborhood": "Jd Social",
  "zipcode": "82520-040",
  "city": "Curitiba",
  "state": "PR",
  "site": "",
  "email": "",
  "category_id": 7527,
  "city_id": "41000",
  "url": "/curitiba-pr/bares-e-restaurantes/restaurantes/999999/kilo-grill-comercio-de-alimentos-ltda-me",
  "status": "1"
}
```

#### Criar Empresa
```
POST /api/empresas
```

**Body:**
```json
{
  "name": "Empresa Exemplo",
  "category_id": 6650,
  "city_id": "41000",
  "description": "Descrição da empresa",
  "address": "Rua Exemplo",
  "number": 123,
  "neighborhood": "Centro",
  "zipcode": "80000-000",
  "email": "contato@empresa.com",
  "site": "https://empresa.com"
}
```

#### Atualizar Empresa
```
PUT /api/empresas/{id}
```

#### Deletar Empresa
```
DELETE /api/empresas/{id}
```

### Categorias

#### Listar Categorias
```
GET /api/categorias
```

**Resposta:**
```json
{
  "data": [
    {
      "id": 6650,
      "name": "Perfumarias",
      "url": "perfumarias",
      "department_id": 25,
      "customers_count": 3461
    }
  ],
  "total": 3970
}
```

#### Detalhe da Categoria
```
GET /api/categorias/{id}
```

### Cidades e Estados

#### Listar Cidades
```
GET /api/cidades
```

**Parâmetros:**
- `state`: filtra por estado (UF)

**Exemplo:**
```
GET /api/cidades?state=PR
```

#### Detalhe da Cidade
```
GET /api/cidades/{id}
```

#### Listar Estados
```
GET /api/estados
```

**Resposta:**
```json
["AC", "AL", "AM", "AP", "BA", "BR", "CE", "DF", "ES", "EX", "GO", "MA", "MG", "MS", "MT", "PA", "PB", "PE", "PI", "PR", "RJ", "RN", "RO", "RR", "RS", "SC", "SE", "SP", "TO"]
```

## Configuração do Banco de Dados

**PostgreSQL:**
- Host: 147.182.248.223
- Port: 5432
- Database: parafa
- Username: admin
- Password: senha123

## CORS

A API está configurada para aceitar requisições de qualquer origem para desenvolvimento. Em produção, recomenda-se restringir para domínios específicos.

## Performance

- Consultas otimizadas com seleção de campos específicos
- Paginação com limites máximos para evitar sobrecarga
- Índices no banco de dados para filtros comuns

## Erros

A API retorna códigos de status HTTP padrão:
- 200: Sucesso
- 201: Criado com sucesso
- 404: Não encontrado
- 500: Erro interno do servidor

## Notas

- A tabela `customers` contém 1.01 GB de dados com mais de 1 milhão de registros
- Filtros e paginação são essenciais para performance
- Queries podem ser demoradas sem filtros específicos
