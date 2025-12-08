<?php

declare(strict_types=1);

/**
 * POST /api/inbox/delete-now
 *
 * Immediately delete the current inbox and all its messages,
 * then create a new empty inbox with a fresh email address.
 */

// Bootstrap application
$services = require __DIR__ . '/../../../config/bootstrap.php';

use Snaply\Api\InboxController;

// Create controller and handle request
$controller = new InboxController($services['inboxService']);
$controller->deleteInboxNow();
