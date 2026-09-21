# PostgreSQL CNPJ em maquina pequena

O PostgreSQL CNPJ tem 11M+ registros e divide a maquina com MySQL, Apache e containers. O compose atual limita o banco a 512 MB e 0.5 CPU; isso pode causar recovery sob carga, especialmente sem indices.

## Compose recomendado

Use os parametros abaixo no servico `postgres` existente. Eles limitam conexoes e memoria por conexao sem mudar o volume de dados:

```yaml
services:
  postgres:
    image: postgres:16
    container_name: postgres
    restart: always
    command:
      - postgres
      - -c
      - max_connections=20
      - -c
      - shared_buffers=96MB
      - -c
      - effective_cache_size=256MB
      - -c
      - work_mem=2MB
      - -c
      - maintenance_work_mem=32MB
      - -c
      - max_parallel_workers=1
      - -c
      - max_parallel_workers_per_gather=0
      - -c
      - jit=off
    shm_size: 64mb
    deploy:
      resources:
        limits:
          cpus: "0.5"
          memory: 512M
        reservations:
          memory: 256M
```

Nao reduza o limite abaixo de 512M enquanto o banco estiver em recovery. O ganho principal vem dos indices e do limite de conexoes.

## Indices

Execute `postgres_cnpj_indexes.sql` diretamente no banco `cnpj`, fora de transacao, em horario de baixo trafego. `CREATE INDEX CONCURRENTLY` pode demorar e precisa de espaco em disco.

```bash
psql "$DATABASE_URL" -d cnpj -f database/postgres_cnpj_indexes.sql
```

## Diagnostico

```sql
SELECT state, count(*) FROM pg_stat_activity GROUP BY state;
SELECT datname, numbackends, xact_commit, blks_read, blks_hit
FROM pg_stat_database WHERE datname = 'cnpj';
```

Se aparecer `database system is in recovery mode`, pare o trafego CNPJ, verifique `docker logs postgres` e `dmesg -T | grep -iE 'oom|killed|out of memory'` antes de reiniciar repetidamente.
