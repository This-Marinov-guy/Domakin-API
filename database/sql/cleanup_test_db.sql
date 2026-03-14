-- ============================================================
-- Cleanup: keep only the 5 most recent rows in each main table
-- (ordered by created_at DESC, id DESC as tiebreaker)
-- Run against the target database before/after testing.
--
-- Notes:
--   - user_settings uses user_id (UUID) as primary key
--   - users, cache, jobs, migrations are intentionally excluded
-- ============================================================

-- listing_applications
DELETE FROM listing_applications
WHERE id NOT IN (
    SELECT id FROM listing_applications
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- properties
DELETE FROM properties
WHERE id NOT IN (
    SELECT id FROM properties
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- personal_data
DELETE FROM personal_data
WHERE id NOT IN (
    SELECT id FROM personal_data
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- property_data
DELETE FROM property_data
WHERE id NOT IN (
    SELECT id FROM property_data
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- rentings
DELETE FROM rentings
WHERE id NOT IN (
    SELECT id FROM rentings
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- viewings
DELETE FROM viewings
WHERE id NOT IN (
    SELECT id FROM viewings
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- search_rentings
DELETE FROM search_rentings
WHERE id NOT IN (
    SELECT id FROM search_rentings
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- email_reminders
DELETE FROM email_reminders
WHERE id NOT IN (
    SELECT id FROM email_reminders
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- newsletters
DELETE FROM newsletters
WHERE id NOT IN (
    SELECT id FROM newsletters
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- feedbacks
DELETE FROM feedbacks
WHERE id NOT IN (
    SELECT id FROM feedbacks
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- careers
DELETE FROM careers
WHERE id NOT IN (
    SELECT id FROM careers
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- unsubscribed_emails
DELETE FROM unsubscribed_emails
WHERE id NOT IN (
    SELECT id FROM unsubscribed_emails
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- personal_access_tokens
DELETE FROM personal_access_tokens
WHERE id NOT IN (
    SELECT id FROM personal_access_tokens
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- app_credentials
DELETE FROM app_credentials
WHERE id NOT IN (
    SELECT id FROM app_credentials
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);

-- user_settings (primary key is user_id uuid, no integer id)
DELETE FROM user_settings
WHERE user_id NOT IN (
    SELECT user_id FROM user_settings
    ORDER BY created_at DESC
    LIMIT 5
);

-- referral_bonuses
DELETE FROM referral_bonuses
WHERE id NOT IN (
    SELECT id FROM referral_bonuses
    ORDER BY created_at DESC, id DESC
    LIMIT 5
);
