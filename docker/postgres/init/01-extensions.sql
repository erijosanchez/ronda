-- Extensiones exigidas por el plan (sec. 8.1).
-- Se aplican a la base central; el provisionador de tenants las repite por base.
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS unaccent;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
