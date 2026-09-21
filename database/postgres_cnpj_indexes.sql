-- Execute no banco cnpj, fora de uma transacao, em horario de baixo trafego.
-- Estes indices evitam varreduras completas nas rotas CNPJ.

CREATE INDEX CONCURRENTLY IF NOT EXISTS companies_cnpj_idx
    ON companies (cnpj);

CREATE INDEX CONCURRENTLY IF NOT EXISTS companies_city_id_id_idx
    ON companies (city_id, id);

CREATE INDEX CONCURRENTLY IF NOT EXISTS companies_city_id_id_desc_idx
    ON companies (city_id, id DESC);

CREATE INDEX CONCURRENTLY IF NOT EXISTS cities_url_state_idx
    ON cities (url, state);

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX CONCURRENTLY IF NOT EXISTS companies_name_trgm_idx
    ON companies USING gin (name gin_trgm_ops);

CREATE INDEX CONCURRENTLY IF NOT EXISTS companies_fantasy_trgm_idx
    ON companies USING gin (fantasy gin_trgm_ops);