-- teleconsult_requests: identify the member who filed the request.
--
-- The table only ever recorded phone_number, so ownership had to be decided by
-- matching numbers. members.contact_number is not unique — 09352427713 sits on
-- three member rows and the placeholder 0900000000 on 63 — which makes every
-- number-based owner check approximate: anyone sharing a number can act on the
-- request. member_id makes the link exact for rows written from now on.
--
-- The column is NULLable on purpose. Legacy rows whose number matches no member
-- (or matches several with no way to choose) keep NULL, and the endpoints fall
-- back to the phone match for those rather than locking their owners out.
--
-- Note on collation: phone_number is utf8mb4_general_ci while
-- members.contact_number is utf8mb4_unicode_ci, so any comparison between the
-- two needs an explicit COLLATE or MySQL raises "Illegal mix of collations".
-- member_id below is created as utf8mb4_unicode_ci to match members.member_id,
-- so joins on THAT column need no such dance.

ALTER TABLE teleconsult_requests
    ADD COLUMN member_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER request_id,
    ADD KEY idx_member (member_id);

-- Backfill. Mirrors ph_find_member() in phone.php exactly: match any spelling of
-- the number, and where several members share it, the most recently registered
-- row wins (NULLs sort last under DESC, id DESC settles the rest). A number that
-- matches nobody leaves member_id NULL.
UPDATE teleconsult_requests t
SET t.member_id = (
    SELECT m.member_id
    FROM members m
    WHERE m.contact_number COLLATE utf8mb4_unicode_ci IN (
              t.phone_number COLLATE utf8mb4_unicode_ci,
              CONCAT('0',   SUBSTRING(t.phone_number, -10)) COLLATE utf8mb4_unicode_ci,
              CONCAT('63',  SUBSTRING(t.phone_number, -10)) COLLATE utf8mb4_unicode_ci,
              CONCAT('+63', SUBSTRING(t.phone_number, -10)) COLLATE utf8mb4_unicode_ci,
              SUBSTRING(t.phone_number, -10) COLLATE utf8mb4_unicode_ci
          )
    ORDER BY m.registered_at DESC, m.id DESC
    LIMIT 1
)
WHERE t.member_id IS NULL;

-- Check what the backfill could not resolve:
--     SELECT request_id, phone_number FROM teleconsult_requests WHERE member_id IS NULL;
