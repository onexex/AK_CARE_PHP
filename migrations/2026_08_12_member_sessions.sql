-- Sessions for the member app.
--
-- Until now no endpoint had any idea who was calling it: identity came from a
-- `user_id` in the query string or post body, which meant authentication,
-- authorisation and lookup were all the same untrusted string. Anyone could
-- read or act as anyone by typing a different member_id. Every ownership check
-- added on 12 Aug 2026 was guarding a door in a wall that wasn't there.
--
-- A row here is one signed-in handset. The token itself is never stored — only
-- its SHA-256 — so a dump of this table cannot be used to impersonate anyone.
--
-- Sliding 30-day expiry: every authenticated request pushes expires_at out
-- again, so an active member stays signed in and an abandoned handset stops
-- working within a month. revoked_at is set on sign-out and is what makes
-- "sign me out everywhere" possible later.

CREATE TABLE IF NOT EXISTS member_sessions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id    VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    -- DATETIME rather than TIMESTAMP: a NOT NULL TIMESTAMP with no default is
    -- rejected outright under this server's sql_mode, and these are absolute
    -- moments that should not shift with the session time zone anyway.
    issued_at    DATETIME NOT NULL DEFAULT current_timestamp(),
    last_used_at DATETIME NULL DEFAULT NULL,
    expires_at   DATETIME NOT NULL,
    revoked_at   DATETIME NULL DEFAULT NULL,
    device       VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY member_sessions_token_hash_unique (token_hash),
    KEY member_sessions_member_id_index (member_id),
    KEY member_sessions_expires_at_index (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
