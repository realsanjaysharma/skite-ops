<?php

require_once __DIR__ . '/../repositories/RequestRepository.php';

class RequestService
{
    private RequestRepository $requestRepo;

    public function __construct()
    {
        $this->requestRepo = new RequestRepository();
    }

    /**
     * Create a new intake request.
     */
    public function createRequest(array $data, int $actorUserId, string $actorRoleKey): array
    {
        $allowedCreatorRoles = ['SALES_TEAM', 'CLIENT_SERVICING', 'MEDIA_PLANNING'];
        if (!in_array($actorRoleKey, $allowedCreatorRoles, true)) {
            throw new DomainException("Role not authorized to create requests.");
        }

        if (empty($data['description'])) {
            throw new InvalidArgumentException("Description is required.");
        }

        if (empty($data['request_type'])) {
            throw new InvalidArgumentException("Request type is required.");
        }

        // Validate operational context
        $hasContext = !empty($data['campaign_id']) || !empty($data['site_id']) || !empty($data['belt_id']);
        if (!$hasContext) {
            throw new InvalidArgumentException("At least one operational context field (campaign, site, belt) must be provided.");
        }

        $insertData = [
            'request_number' => $this->requestRepo->getNextRequestNumber(),
            'request_type' => $data['request_type'],
            'client_name' => $data['client_name'] ?? null,
            'campaign_id' => $data['campaign_id'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'belt_id' => $data['belt_id'] ?? null,
            'description' => $data['description'],
            'status' => 'PENDING',
            'requester_user_id' => $actorUserId,
        ];

        $newId = $this->requestRepo->create($insertData);
        return $this->requestRepo->findById($newId);
    }

    /**
     * Approve an existing request.
     */
    public function approveRequest(int $requestId, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can approve requests.");
        }

        $request = $this->requestRepo->findById($requestId);
        if (!$request) {
            throw new InvalidArgumentException("Request not found.");
        }

        if ($request['status'] !== 'PENDING') {
            throw new DomainException("Only PENDING requests can be approved.");
        }

        $this->requestRepo->update([
            'id' => $requestId,
            'status' => 'APPROVED',
            'reviewer_user_id' => $actorUserId
        ]);

        return $this->requestRepo->findById($requestId);
    }

    /**
     * Reject an existing request.
     */
    public function rejectRequest(int $requestId, string $rejectionReason, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can reject requests.");
        }

        if (empty($rejectionReason)) {
            throw new InvalidArgumentException("Rejection reason is required.");
        }

        $request = $this->requestRepo->findById($requestId);
        if (!$request) {
            throw new InvalidArgumentException("Request not found.");
        }

        if ($request['status'] !== 'PENDING') {
            throw new DomainException("Only PENDING requests can be rejected.");
        }

        $this->requestRepo->update([
            'id' => $requestId,
            'status' => 'REJECTED',
            'reviewer_user_id' => $actorUserId,
            'review_notes' => $rejectionReason
        ]);

        return $this->requestRepo->findById($requestId);
    }

    /**
     * List requests with role scoping.
     */
    public function listRequests(array $filters, int $actorUserId, string $actorRoleKey): array
    {
        // If not Ops, they can only see their own requests
        if ($actorRoleKey !== 'OPS_MANAGER') {
            $filters['requester_user_id'] = $actorUserId;
        }

        return $this->requestRepo->findAll($filters);
    }

    /**
     * Get a specific request.
     */
    public function getRequest(int $requestId, int $actorUserId, string $actorRoleKey): ?array
    {
        $request = $this->requestRepo->findById($requestId);
        
        if ($request && $actorRoleKey !== 'OPS_MANAGER') {
            // Scope limit check
            if ($request['requester_user_id'] != $actorUserId) {
                return null;
            }
        }

        return $request;
    }
}
