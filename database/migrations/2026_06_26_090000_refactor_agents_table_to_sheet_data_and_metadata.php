<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE agents
                ADD COLUMN IF NOT EXISTS metadata jsonb;

            UPDATE agents
            SET metadata = COALESCE(metadata, '{}'::jsonb)
            WHERE metadata IS NULL;
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = 'agents' AND column_name = 'spreadsheet_id'
                ) THEN
                    UPDATE agents
                    SET
                        metadata = COALESCE(metadata, '{}'::jsonb)
                            || jsonb_strip_nulls(jsonb_build_object(
                                'spreadsheet_id', spreadsheet_id,
                                'sheet_gid', sheet_gid,
                                'sheet_row_number', sheet_row_number,
                                'source_row_hash', source_row_hash,
                                'synced_at', synced_at,
                                'legacy_columns_migrated_at', now()
                            ));
                END IF;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            DROP INDEX IF EXISTS agents_spreadsheet_id_index;
            DROP INDEX IF EXISTS agents_sheet_gid_index;
            DROP INDEX IF EXISTS agents_name_index;
            DROP INDEX IF EXISTS agents_phone_index;
            DROP INDEX IF EXISTS agents_email_index;

            ALTER TABLE agents
                DROP COLUMN IF EXISTS spreadsheet_id,
                DROP COLUMN IF EXISTS sheet_gid,
                DROP COLUMN IF EXISTS sheet_row_number,
                DROP COLUMN IF EXISTS name,
                DROP COLUMN IF EXISTS phone,
                DROP COLUMN IF EXISTS source_row_hash,
                DROP COLUMN IF EXISTS synced_at;
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            DECLARE
                data_key text;
                data_keys text[];
                target_column_name text;
                reserved_columns text[] := ARRAY['id', 'source_key', 'metadata', 'created_at', 'updated_at'];
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM information_schema.columns c
                    WHERE c.table_schema = 'public' AND c.table_name = 'agents' AND c.column_name = 'data'
                ) THEN
                    SELECT array_agg(DISTINCT key)
                    INTO data_keys
                    FROM (
                        SELECT jsonb_object_keys(data) AS key
                        FROM agents
                        WHERE data IS NOT NULL
                    ) keys;

                    FOREACH data_key IN ARRAY COALESCE(data_keys, ARRAY[]::text[])
                    LOOP
                        target_column_name := lower(regexp_replace(trim(data_key), '[^a-zA-Z0-9]+', '_', 'g'));
                        target_column_name := trim(both '_' from target_column_name);

                        IF target_column_name = '' THEN
                            CONTINUE;
                        END IF;

                        IF target_column_name = ANY(reserved_columns) THEN
                            target_column_name := 'sheet_' || target_column_name;
                        END IF;

                        EXECUTE format('ALTER TABLE agents ADD COLUMN IF NOT EXISTS %I text', target_column_name);
                        EXECUTE format('UPDATE agents SET %I = data ->> %L WHERE data ? %L', target_column_name, data_key, data_key);
                    END LOOP;
                END IF;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            UPDATE agents
            SET source_key = regexp_replace(source_key, '^(email|phone|row):', '')
            WHERE source_key ~ '^(email|phone|row):';

            UPDATE agents
            SET metadata = jsonb_strip_nulls(jsonb_build_object(
                'sheet_gid', metadata->>'sheet_gid',
                'synced_at', metadata->>'synced_at',
                'spreadsheet_id', metadata->>'spreadsheet_id',
                'source_row_hash', metadata->>'source_row_hash',
                'sheet_row_number',
                    CASE
                        WHEN metadata ? 'sheet_row_number' THEN to_jsonb((metadata->>'sheet_row_number')::integer)
                        ELSE NULL
                    END,
                'legacy_columns_migrated_at', metadata->>'legacy_columns_migrated_at'
            ));

            ALTER TABLE agents
                ALTER COLUMN metadata SET DEFAULT '{}'::jsonb,
                ALTER COLUMN metadata SET NOT NULL;

            ALTER TABLE agents
                DROP COLUMN IF EXISTS data;

            CREATE UNIQUE INDEX IF NOT EXISTS agents_email_unique
                ON agents (LOWER(email))
                WHERE email IS NOT NULL;
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE agents
                ADD COLUMN IF NOT EXISTS data jsonb DEFAULT '{}'::jsonb;
        SQL);
    }
};
