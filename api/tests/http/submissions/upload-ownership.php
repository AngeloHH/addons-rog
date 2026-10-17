<?php

/**
 * Addons ROG - Submission upload ownership tests
 *
 * - Reject attaching another account's uploaded ZIP to a new submission.
 */

$sibling = $other->call('POST', '/api/submissions', ['uploadId' => $upload['id']], 422);
