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
  bills             JSONB        NULL,
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