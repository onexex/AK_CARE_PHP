<?php
// phone.php — Philippine mobile number normalization.
//
// members.contact_number holds two different shapes: 11-digit '09XXXXXXXXX'
// (726,923 rows) and 10-digit '9XXXXXXXXX' with no leading zero (~570,000).
// The login screen asks for the 11-digit form, so an exact-match lookup never
// found anyone stored the other way. Everything here reduces both sides to the
// same 10-digit core before they are compared.

/**
 * Reduce any Philippine mobile number to its 10-digit core, e.g. '9171234567'.
 *
 * Accepts 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX and +639XXXXXXXXX, with or
 * without spaces, dashes or parentheses. Returns '' when the input is not a
 * recognisable PH mobile number.
 */
function ph_mobile_core(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw);

    if (strlen($digits) === 12 && strpos($digits, '639') === 0) {
        $digits = substr($digits, 2);   // 639171234567 -> 9171234567
    } elseif (strlen($digits) === 11 && strpos($digits, '09') === 0) {
        $digits = substr($digits, 1);   // 09171234567  -> 9171234567
    }

    return (strlen($digits) === 10 && $digits[0] === '9') ? $digits : '';
}

/**
 * Every spelling the number might be stored under, for matching against
 * members.contact_number.
 *
 * This is deliberately an IN () over exact literals rather than a normalizing
 * expression on the column: members_contact_number_index only helps if the
 * column is compared raw, and the table has ~1.3M rows.
 *
 * Returns an empty array for input that isn't a valid PH mobile number.
 */
function ph_mobile_variants(string $raw): array
{
    $core = ph_mobile_core($raw);

    if ($core === '') {
        return [];
    }

    return [$core, '0' . $core, '63' . $core, '+63' . $core];
}

/** Local dialling form, e.g. '09171234567'. Used for SMS via an on-site device. */
function ph_mobile_local(string $raw): string
{
    $core = ph_mobile_core($raw);

    return $core === '' ? '' : '0' . $core;
}

/** International form with no '+', e.g. '639171234567'. Required by Brevo. */
function ph_mobile_international(string $raw): string
{
    $core = ph_mobile_core($raw);

    return $core === '' ? '' : '63' . $core;
}

/**
 * Look a member up by any spelling of their number.
 *
 * $columns is spliced into the SELECT, so it must never contain user input.
 * Returns the row, or null when the number matches no member.
 *
 * Numbers are not unique in this table — 09352427713 sits on three rows, and the
 * placeholder 0900000000 on 63 — so the tie-break is explicit: the most recently
 * registered row wins, since that is usually the person still reachable on the
 * number. MySQL sorts NULLs last under DESC, so rows with no registered_at fall
 * below any dated row, and id DESC settles the remainder. Without this the winner
 * is whatever the index happens to yield first, which is not a guarantee.
 *
 * The IN () list is tiny, so the sort costs nothing measurable.
 */
function ph_find_member(mysqli $conn, string $raw, string $columns = 'member_id'): ?array
{
    $variants = ph_mobile_variants($raw);

    if ($variants === []) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($variants), '?'));
    $stmt = $conn->prepare(
        "SELECT {$columns} FROM members
         WHERE contact_number IN ({$placeholders})
         ORDER BY registered_at DESC, id DESC
         LIMIT 1"
    );
    $stmt->bind_param(str_repeat('s', count($variants)), ...$variants);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}
