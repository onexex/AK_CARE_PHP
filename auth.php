<?php
// auth.php — who is calling.
//
// Every endpoint used to take the caller's identity from the request itself:
// `?user_id=AKM-787`. That string was simultaneously the claim of who you are
// and the authority to act as them, so changing it made you someone else. The
// ownership checks added around it were real but could only ever be as good as
// the identity underneath, and there wasn't one.
//
// A member now proves who they are once, by SMS, and gets a token. The token is
// what every later request carries; `user_id` stops being an input.
//
// Requires config.php (for $conn) to have been included first.

define('SESSION_LIFETIME_DAYS', 30);

/**
 * The bearer token on this request, or '' when there isn't one.
 *
 * Apache does not always pass Authorization through to PHP — mod_php usually
 * does, CGI/FastCGI needs CGIPassAuth or a rewrite — so X-Member-Token is
 * accepted as well. It is the same token either way; only the envelope differs.
 */
function session_token_from_request(): string
{
    $candidates = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
    ];

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $candidates[] = $value;
            }
        }
    }

    foreach ($candidates as $header) {
        if (preg_match('/^\s*Bearer\s+(.+)$/i', (string) $header, $m)) {
            return trim($m[1]);
        }
    }

    return trim((string) ($_SERVER['HTTP_X_MEMBER_TOKEN'] ?? ''));
}

/**
 * Start a session for a member and return the token to hand back to them.
 *
 * The plaintext token is returned once, here, and never stored: only its hash
 * goes in the table, so a leak of the database does not hand over live sessions.
 */
function issue_member_session(mysqli $conn, string $memberId): array
{
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $device = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $stmt = $conn->prepare(
        "INSERT INTO member_sessions (member_id, token_hash, expires_at, device)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?)"
    );
    $days = SESSION_LIFETIME_DAYS;
    $stmt->bind_param('ssis', $memberId, $hash, $days, $device);
    $stmt->execute();

    return [
        'token'      => $token,
        'expires_in' => SESSION_LIFETIME_DAYS * 86400,
    ];
}

/**
 * The member behind this request, or null when there is no usable session.
 *
 * Also slides the expiry forward, so a member who keeps using the app is not
 * asked to re-verify by SMS every month for no reason.
 */
function current_member(mysqli $conn): ?array
{
    $token = session_token_from_request();

    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);

    $stmt = $conn->prepare(
        "SELECT s.id AS session_id, m.member_id, m.contact_number, m.firstname, m.lastname
         FROM member_sessions s
         JOIN members m ON m.member_id = s.member_id
         WHERE s.token_hash = ?
           AND s.revoked_at IS NULL
           AND s.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        return null;
    }

    $touch = $conn->prepare(
        "UPDATE member_sessions
         SET last_used_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
         WHERE id = ?"
    );
    $days = SESSION_LIFETIME_DAYS;
    $touch->bind_param('ii', $days, $row['session_id']);
    $touch->execute();

    return $row;
}

/**
 * The member behind this request, or a 401 and nothing else.
 *
 * Endpoints call this instead of reading a user_id, so the caller cannot name
 * anyone but themselves. The response carries a machine-readable code so the
 * app can tell "sign in again" apart from an ordinary failure.
 */
function require_member(mysqli $conn): array
{
    $member = current_member($conn);

    if ($member === null) {
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(401);
        echo json_encode([
            'status'  => 'error',
            'code'    => 'unauthenticated',
            'message' => 'Please sign in again.',
        ]);
        exit;
    }

    return $member;
}

/** Sign out this handset. Other devices the member is signed in on are untouched. */
function revoke_current_session(mysqli $conn): void
{
    $token = session_token_from_request();

    if ($token === '') {
        return;
    }

    $hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "UPDATE member_sessions SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
}
