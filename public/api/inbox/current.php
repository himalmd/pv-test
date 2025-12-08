<?php

declare(strict_types=1);

/**
 * GET /api/inbox/current
 *
 * Retrieve the current active inbox for the session.
 * Creates a new inbox if none exists.
 */

// Bootstrap application
$services = require __DIR__ . '/../../../config/bootstrap.php';

use Snaply\Api\InboxController;

// Create controller and handle request
$controller = new InboxController($services['inboxService']);
$controller->getCurrentInbox();
