-- E-Prescriptions and Medical Certificates: user_id must hold a member_id.
--
-- Both columns were int(11), but every caller sends members.member_id — a string
-- like 'AKM-787'. MySQL coerced that to 0, so reads matched nothing and a
-- submitted certificate request stored user_id = 0, pooling every member's
-- requests into one bucket that all of them could see.
--
-- varchar(50) utf8mb4_unicode_ci matches members.member_id and the community_*
-- tables, which were converted earlier for the same reason.
--
-- Both tables were empty when this ran (0 rows each), so no data conversion is
-- needed. If a target database has rows, the existing integers become the
-- strings '0', '1', … which will not match any member_id — check before
-- applying:
--     SELECT COUNT(*) FROM eprescriptions;
--     SELECT COUNT(*) FROM medical_cert_requests;

ALTER TABLE eprescriptions
    MODIFY user_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE medical_cert_requests
    MODIFY user_id VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
