<?php
// Applies the .sql files in migrations/, once each.
//
// This used to be a web page. Requesting the URL — no token, no password, no
// check of any kind — ran six ALTER TABLE statements against the live database.
// It survived the authentication sweep on 12 Aug 2026 only because it took no
// user_id, so a grep for that never found it. Anyone who knew the path could
// reshape the schema.
//
// It is now a command-line script and refuses to run any other way. Schema
// changes are not something a stranger with a browser gets to trigger.
//
//     php run_migration.php              apply what has not been applied
//     php run_migration.php --baseline   record every file as applied, run none
//
// --baseline is for a database whose schema was changed by hand before this
// runner existed — which is every one of them today. Without it the first run
// tries to re-apply changes that are already there, and an ADD COLUMN is not
// something that can be repeated.
//
// The statements it used to hardcode (widening the community tables' user_id to
// VARCHAR(50)) have long since been applied; each change since then lives as a
// file in migrations/, which is what this runs.

if (PHP_SAPI !== 'cli') {
    // Nothing here is meant to be reachable over HTTP, so it does not announce
    // itself as something that exists and is merely forbidden.
    http_response_code(404);
    exit;
}

require __DIR__ . '/config.php';

$dir = __DIR__ . '/migrations';

// Which files have run. Without this the runner depends on every migration
// being safe to repeat, which is true of the ones written so far and is not a
// property to rely on for the ones that come next.
$conn->query(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        filename   VARCHAR(191) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$applied = [];
$result = $conn->query("SELECT filename FROM schema_migrations");
while ($row = $result->fetch_assoc()) {
    $applied[$row['filename']] = true;
}

$baseline = in_array('--baseline', $argv, true);

$files = glob($dir . '/*.sql') ?: [];
sort($files); // Filenames are date-prefixed, so this is chronological order.

$record = $conn->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");

$ran = 0;

foreach ($files as $path) {
    $name = basename($path);

    if (isset($applied[$name])) {
        fwrite(STDOUT, "skip  {$name} (already applied)\n");
        continue;
    }

    if ($baseline) {
        $record->bind_param('s', $name);
        $record->execute();
        fwrite(STDOUT, "mark  {$name} (recorded, not run)\n");
        $ran++;
        continue;
    }

    // Files hold several statements, so multi_query — and its results have to be
    // drained before the connection will accept anything else. mysqli throws
    // rather than returning false under the default error mode, so the failure
    // path is a catch, not a return value.
    try {
        $conn->multi_query(file_get_contents($path));

        do {
            if ($res = $conn->store_result()) {
                $res->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "FAIL  {$name}: {$e->getMessage()}\n");
        fwrite(STDERR, "Nothing after this file was applied. If this change is already\n");
        fwrite(STDERR, "in the database, record it with --baseline instead.\n");
        exit(1);
    }

    $record->bind_param('s', $name);
    $record->execute();

    fwrite(STDOUT, "ok    {$name}\n");
    $ran++;
}

if ($ran === 0) {
    fwrite(STDOUT, "Nothing to apply.\n");
} else {
    fwrite(STDOUT, $baseline ? "Recorded {$ran} migration(s).\n" : "Applied {$ran} migration(s).\n");
}
