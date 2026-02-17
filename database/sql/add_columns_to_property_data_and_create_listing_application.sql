-- ============================================================
-- 1. Add new columns to property_data table (PostgreSQL)
-- ============================================================

ALTER TABLE property_data
  ADD COLUMN IF NOT EXISTS type             INTEGER      NULL,
  ADD COLUMN IF NOT EXISTS furnished_type  INTEGER      NULL,
  ADD COLUMN IF NOT EXISTS shared_space    TEXT         NULL,
  ADD COLUMN IF NOT EXISTS bathrooms       INTEGER      NULL,
  ADD COLUMN IF NOT EXISTS toilets         INTEGER      NULL,
  ADD COLUMN IF NOT EXISTS amenities       TEXT         NULL,
  ADD COLUMN IF NOT EXISTS available_from  DATE NULL,
  ADD COLUMN IF NOT EXISTS available_to    DATE NULL;


-- ============================================================
-- 2. Create listing_application table
--    Combines personal_data + property_data into one record
-- ============================================================

CREATE TABLE IF NOT EXISTS listing_applications (
  id                BIGSERIAL    PRIMARY KEY,
  reference_id              UUID         NOT NULL DEFAULT gen_random_uuid(),

  step              INTEGER      NOT NULL DEFAULT 1,

  -- Optional relation to users table
  user_id           UUID         NULL,

  -- From personal_data
  name              VARCHAR(255) NULL,
  surname           VARCHAR(255) NULL,
  email             VARCHAR(255) NULL,
  phone             VARCHAR(255) NULL,

  -- From property_data (original columns)
  city              VARCHAR(255) NULL,
  address           VARCHAR(255) NULL,
  postcode          VARCHAR(255) NULL,
  size              VARCHAR(255) NULL,
  rent              VARCHAR(255) NULL,
  registration      VARCHAR(255) NULL,
  bills             INTEGER      NULL,
  flatmates         JSONB        NULL,
  period            JSONB        NULL,
  description       JSONB        NULL,
  images            TEXT         NULL,
  pets_allowed      BOOLEAN      NULL,
  smoking_allowed   BOOLEAN      NULL,
     available_from  DATE NULL,
   available_to       DATE NULL,

  -- From property_data (new columns)
  type              INTEGER      NULL,
  furnished_type    INTEGER      NULL,
  shared_space      TEXT         NULL,
  bathrooms         INTEGER      NULL,
  toilets           INTEGER      NULL,
  amenities         TEXT         NULL,

  created_at        TIMESTAMP(0) NULL,
  updated_at        TIMESTAMP(0) NULL
);

ALTER TABLE listing_applications
  ADD CONSTRAINT listing_applications_user_id_fkey
  FOREIGN KEY (user_id) REFERENCES public.users(id);

CREATE TABLE IF NOT EXISTS email_reminders (
  id               BIGSERIAL    PRIMARY KEY,
  status           TEXT      NOT NULL DEFAULT 'pending',
  scheduled_date             DATE         NOT NULL,
  template_id         TEXT         NOT NULL,
  email            TEXT         NOT NULL,
  metadata         JSONB        NULL,
  created_at       TIMESTAMP(0) NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP(0) NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Cast registration from VARCHAR to BOOLEAN
-- 'yes' → true, everything else (incl. 'no') → false
-- ============================================================

ALTER TABLE property_data
  ALTER COLUMN registration TYPE BOOLEAN
  USING (registration = 'yes');

ALTER TABLE listing_applications
  ALTER COLUMN registration TYPE BOOLEAN
  USING (registration = 'yes');

-- ============================================================
-- Add deposit (INT NULL) and convert size to INT with reformat
-- (extract first number from values like "25m²", "100 sqm"; empty → NULL)
-- ============================================================

-- property_data: add deposit, then convert size
ALTER TABLE property_data
  ADD COLUMN IF NOT EXISTS deposit INTEGER NULL;

ALTER TABLE property_data
  ALTER COLUMN size DROP NOT NULL;

ALTER TABLE property_data
  ALTER COLUMN size TYPE INTEGER
  USING ((regexp_match(trim(COALESCE(size, '')), '[0-9]+'))[1])::integer;

-- listing_applications: add deposit, then convert size
ALTER TABLE listing_applications
  ADD COLUMN IF NOT EXISTS deposit INTEGER NULL;

ALTER TABLE listing_applications
  ALTER COLUMN size TYPE INTEGER
  USING ((regexp_match(trim(COALESCE(size, '')), '[0-9]+'))[1])::integer;

-- ============================================================
-- Convert bills from JSON/JSONB to INTEGER NULL
-- (extract first number from content, e.g. "€150" or {"en":"€200"} → 150, 200; no number → NULL)
-- ============================================================

DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'property_data' AND column_name = 'bills'
    AND data_type IN ('jsonb', 'json')
  ) THEN
    ALTER TABLE property_data ADD COLUMN bills_new INTEGER NULL;
    UPDATE property_data SET bills_new = ((regexp_match(trim(COALESCE(bills::text, '')), '[0-9]+'))[1])::integer WHERE bills IS NOT NULL AND trim(bills::text) <> '';
    ALTER TABLE property_data DROP COLUMN bills;
    ALTER TABLE property_data RENAME COLUMN bills_new TO bills;
  END IF;
END $$;

DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'listing_applications' AND column_name = 'bills'
    AND data_type IN ('jsonb', 'json')
  ) THEN
    ALTER TABLE listing_applications ADD COLUMN bills_new INTEGER NULL;
    UPDATE listing_applications SET bills_new = ((regexp_match(trim(COALESCE(bills::text, '')), '[0-9]+'))[1])::integer WHERE bills IS NOT NULL AND trim(bills::text) <> '';
    ALTER TABLE listing_applications DROP COLUMN bills;
    ALTER TABLE listing_applications RENAME COLUMN bills_new TO bills;
  END IF;
END $$;

-- ============================================================
-- Remove independent column from both tables (if present)
-- ============================================================

ALTER TABLE property_data DROP COLUMN IF EXISTS independent;
ALTER TABLE listing_applications DROP COLUMN IF EXISTS independent;