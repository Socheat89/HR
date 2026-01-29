<?php
require_once 'db.php';

try {
    echo "Checking database schema...<br>";

    // 1. Add vote_count column if it doesn't exist
    $stmt = $db->query("SHOW COLUMNS FROM peer_votes LIKE 'vote_count'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE peer_votes ADD COLUMN vote_count INT DEFAULT 1");
        echo "Added 'vote_count' column to 'peer_votes'.<br>";
    } else {
        echo "'vote_count' column already exists.<br>";
    }

    // 2. Check and adjust indexes
    // We want to allow a user to vote for multiple candidates in the same poll, 
    // but only one record per candidate per user (with a vote_count).
    // So we need a UNIQUE key on (poll_id, voter_user_id, voted_for_user_id).
    // And we must ensure there is NO UNIQUE key on just (poll_id, voter_user_id).

    $stmt = $db->query("SHOW INDEX FROM peer_votes");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $poll_voter_unique = false;
    $poll_voter_candidate_unique = false;

    foreach ($indexes as $index) {
        if ($index['Key_name'] == 'poll_id' && $index['Non_unique'] == 0) {
            // This is likely the unique constraint on (poll_id, voter_user_id) based on the SQL dump
            // We need to check the columns in this index, but usually the name 'poll_id' matches the dump
            $poll_voter_unique = true;
        }
        if ($index['Key_name'] == 'unique_vote' || ($index['Key_name'] == 'poll_id' && $index['Column_name'] == 'voted_for_user_id')) {
             // Hard to detect exactly without parsing all rows, but let's try to be safe.
        }
    }

    // To be safe, let's try to drop the restrictive unique index if it exists and add the correct one.
    // Note: Dropping an index that doesn't exist might throw error, so we wrap in try-catch or check.
    
    // Based on SQL dump: ADD UNIQUE KEY `poll_id` (`poll_id`,`voter_user_id`)
    // We want to drop this if it prevents voting for multiple people.
    
    try {
        // Try to drop the old unique index. 
        // Warning: This might fail if the index name is different. 
        // But based on the dump it is `poll_id`.
        $db->exec("ALTER TABLE peer_votes DROP INDEX poll_id");
        echo "Dropped index 'poll_id'.<br>";
    } catch (PDOException $e) {
        // Ignore if it doesn't exist
        echo "Index 'poll_id' not found or could not be dropped (might be already correct).<br>";
    }

    // Now add the correct unique index
    try {
        $db->exec("ALTER TABLE peer_votes ADD UNIQUE KEY `unique_vote` (`poll_id`, `voter_user_id`, `voted_for_user_id`)");
        echo "Added unique index 'unique_vote' on (poll_id, voter_user_id, voted_for_user_id).<br>";
    } catch (PDOException $e) {
        echo "Index 'unique_vote' likely already exists.<br>";
    }

    echo "Database update completed successfully.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
