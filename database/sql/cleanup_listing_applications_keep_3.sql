-- ============================================================
-- Delete all listing_applications except the 3 most recent
-- (ordered by created_at DESC, then id DESC as tiebreaker)
-- ============================================================

DELETE FROM listing_applications
WHERE id NOT IN (
    SELECT id
    FROM listing_applications
    ORDER BY created_at DESC, id DESC
    LIMIT 3
);
