-- ─────────────────────────────────────────────
--  Initialisation PostgreSQL
--  Créer les bases dev + test au démarrage
-- ─────────────────────────────────────────────

-- Base de développement (créée par POSTGRES_DB dans docker-compose)
-- Base de test
CREATE DATABASE erp_test
    WITH OWNER = erp
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.utf8'
    LC_CTYPE = 'en_US.utf8';

-- Extensions utiles
\c erp_development
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c erp_test
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
