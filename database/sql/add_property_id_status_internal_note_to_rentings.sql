-- Add property_id, status and internal_note to rentings table (PostgreSQL)
-- Run this if you prefer raw SQL instead of Laravel migrations

-- Add columns (PostgreSQL)
ALTER TABLE rentings
  ADD COLUMN IF NOT EXISTS property_id BIGINT NULL,
  ADD COLUMN IF NOT EXISTS status INT DEFAULT 1 NULL,
  ADD COLUMN IF NOT EXISTS internal_note TEXT NULL,
  ADD COLUMN IF NOT EXISTS internal_updated_at TIMESTAMP(0) NULL,
  ADD COLUMN IF NOT EXISTS internal_updated_by UUID NULL;

-- Foreign key: property_id (run only if the constraint does not exist)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'rentings_property_id_foreign'
  ) THEN
    ALTER TABLE rentings
      ADD CONSTRAINT rentings_property_id_foreign
      FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE SET NULL;
  END IF;
END $$;

-- Foreign key: internal_updated_by (run only if the constraint does not exist)
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'rentings_internal_updated_by_foreign'
  ) THEN
    ALTER TABLE rentings
      ADD CONSTRAINT rentings_internal_updated_by_foreign
      FOREIGN KEY (internal_updated_by) REFERENCES users (id) ON DELETE SET NULL;
  END IF;
END $$;
