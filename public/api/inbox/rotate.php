<?php

declare(strict_types=1);

/**
 * POST /api/inbox/rotate
 *
 * Abandon the current inbox and create a new one with a fresh email address.
 */

// Bootstrap application
$services = require __DIR__ . '/../../../config/bootstrap.php';

use Snaply\Api\InboxController;

// Create controller and handle request
$controller = new InboxController($services['inboxService']);
$controller->rotateInbox();
