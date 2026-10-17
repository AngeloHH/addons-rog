<?php

/**
 * Addons ROG - Queued notification delivery tests
 *
 * - Process queued notifications through the disposable mail transport and require processed work.
 */

$mail = (new Rog\Services\NotificationService(test_app()))->work(100);
check($mail > 0, 'Durable mail worker');
