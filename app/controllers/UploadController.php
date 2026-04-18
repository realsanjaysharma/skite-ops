<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/UploadService.php';

class UploadController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
    }

    public function createUpload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $_POST;
        
        $roleKey = $_SESSION['role_key'] ?? '';
        
        $surface = $this->resolveSurfaceFromRole($roleKey);
        if (!$surface) {
            Response::error('Role is not allowed to create uploads', 403);
            return;
        }

        if (empty($input['parent_type']) || empty($input['parent_id']) || empty($input['upload_type'])) {
            Response::error('Missing required fields: parent_type, parent_id, upload_type', 400);
            return;
        }

        $files = $_FILES['files'] ?? [];

        try {
            $result = $this->uploadService->createUploadsForSurface($surface, $input, $files, $_SESSION['user_id']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    private function resolveSurfaceFromRole(string $roleKey): ?string 
    {
        switch ($roleKey) {
            case 'GREEN_BELT_SUPERVISOR':
                return 'SUPERVISOR';
            case 'OUTSOURCED_MAINTAINER':
                return 'OUTSOURCED';
            case 'MONITORING_TEAM':
                return 'MONITORING';
            case 'FABRICATION_LEAD':
                return 'TASK';
            default:
                return null;
        }
    }
}
