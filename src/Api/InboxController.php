<?php

declare(strict_types=1);

namespace Snaply\Api;

use DateTimeImmutable;
use Snaply\Entity\Inbox;
use Snaply\Service\InboxService;

/**
 * API controller for inbox lifecycle operations.
 *
 * Provides REST endpoints for managing temporary inboxes:
 * - Get current active inbox
 * - Rotate inbox (abandon and create new)
 * - Delete inbox immediately and create new
 */
class InboxController extends AbstractApiController
{
    private InboxService $inboxService;

    public function __construct(InboxService $inboxService)
    {
        $this->inboxService = $inboxService;
    }

    /**
     * GET /api/inbox/current
     *
     * Get or create the current active inbox for the session.
     * Automatically creates an inbox if none exists.
     * Updates last_accessed_at to extend TTL.
     */
    public function getCurrentInbox(): void
    {
        try {
            $this->validateMethod('GET');

            // Get or create session token
            $sessionToken = $this->getOrCreateSessionToken();

            // Get or create active inbox (service handles this atomically)
            $inbox = $this->inboxService->getOrCreateActiveInboxForSession($sessionToken);

            // Transform and return response
            $this->jsonSuccess($this->transformInboxToResponse($inbox));
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * POST /api/inbox/rotate
     *
     * Abandon the current inbox and create a new one with a fresh email address.
     * Previous inbox is marked as "abandoned" status.
     */
    public function rotateInbox(): void
    {
        try {
            $this->validateMethod('POST');

            // Get session token (must exist for rotation)
            $sessionToken = $this->getSessionToken();

            // Rotate inbox (abandon old, create new)
            $inbox = $this->inboxService->rotateInboxForSession($sessionToken);

            // Transform and return response
            $this->jsonSuccess(
                $this->transformInboxToResponse($inbox),
                'New inbox created successfully'
            );
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * POST /api/inbox/delete-now
     *
     * Immediately delete the current inbox (soft delete messages and inbox)
     * and create a new empty inbox with a fresh email address.
     */
    public function deleteInboxNow(): void
    {
        try {
            $this->validateMethod('POST');

            // Get session token (must exist for deletion)
            $sessionToken = $this->getSessionToken();

            // Delete inbox and create new one
            $inbox = $this->inboxService->deleteInboxNowForSession($sessionToken);

            // Transform and return response
            $this->jsonSuccess(
                $this->transformInboxToResponse($inbox),
                'Inbox deleted and new inbox created successfully'
            );
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Transform Inbox entity to API response format.
     *
     * Converts internal entity representation to frontend-friendly format:
     * - Combines email parts into full address
     * - Formats timestamps as ISO 8601
     * - Calculates expiry time based on TTL
     * - Excludes sensitive data (session_token_hash)
     *
     * @param Inbox $inbox The inbox entity
     * @return array<string, mixed> API response data
     */
    private function transformInboxToResponse(Inbox $inbox): array
    {
        $response = [
            'id' => $inbox->id,
            'email' => $inbox->getFullEmailAddress(),
            'status' => $inbox->status,
            'ttl_minutes' => $inbox->ttlMinutes,
        ];

        // Add formatted timestamps
        if ($inbox->createdAt !== null) {
            $response['created_at'] = $inbox->createdAt->format('c'); // ISO 8601
        }

        if ($inbox->lastAccessedAt !== null) {
            $response['last_accessed_at'] = $inbox->lastAccessedAt->format('c');

            // Calculate expiry time based on last_accessed_at + TTL
            $expiresAt = $inbox->lastAccessedAt->modify("+{$inbox->ttlMinutes} minutes");
            $response['expires_at'] = $expiresAt->format('c');

            // Add time remaining in seconds (useful for countdown timers)
            $now = new DateTimeImmutable();
            $remaining = $expiresAt->getTimestamp() - $now->getTimestamp();
            $response['seconds_until_expiry'] = max(0, $remaining);
        }

        if ($inbox->expiredAt !== null) {
            $response['expired_at'] = $inbox->expiredAt->format('c');
        }

        return $response;
    }
}
