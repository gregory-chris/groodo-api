<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Media;
use App\Models\Task;
use App\Models\Project;
use App\Models\Document;
use App\Models\User;
use App\Services\ValidationService;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

class MediaController
{
    private Media $mediaModel;
    private Task $taskModel;
    private Project $projectModel;
    private Document $documentModel;
    private User $userModel;
    private ValidationService $validationService;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    private const UPLOAD_BASE_PATH = 'uploads';
    private const PREVIEW_MAX_SIZE = 500;
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo'];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'avi'];

    public function __construct(
        Media $mediaModel,
        Task $taskModel,
        Project $projectModel,
        Document $documentModel,
        User $userModel,
        ValidationService $validationService,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->mediaModel = $mediaModel;
        $this->taskModel = $taskModel;
        $this->projectModel = $projectModel;
        $this->documentModel = $documentModel;
        $this->userModel = $userModel;
        $this->validationService = $validationService;
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    /**
     * Upload media file
     */
    public function upload(Request $request, Response $response): Response
    {
        $this->logger->info('Media upload request started');

        try {
            $userId = $request->getAttribute('user_id');
            $this->logger->info('Processing upload for authenticated user', ['user_id' => $userId]);

            // Get parsed body data (form fields)
            $data = $request->getParsedBody() ?? [];
            $this->logger->debug('Received form data', [
                'has_taskId' => isset($data['taskId']),
                'has_projectId' => isset($data['projectId']),
                'has_documentId' => isset($data['documentId']),
                'has_userId' => isset($data['userId'])
            ]);

            // Step 1: Validate owner parameter (exactly one required)
            $this->logger->info('Step 1: Validating owner parameter');
            $ownerValidation = $this->validateOwnerParameter($data);
            if (!$ownerValidation['valid']) {
                $this->logger->warning('Owner parameter validation failed', [
                    'errors' => $ownerValidation['errors']
                ]);
                return $this->responseHelper->validationError($ownerValidation['errors']);
            }
            
            $ownerType = $ownerValidation['owner_type'];
            $ownerId = $ownerValidation['owner_id'];
            $this->logger->info('Owner parameter validated', [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId
            ]);

            // Step 2: Verify owner entity exists and belongs to user
            $this->logger->info('Step 2: Verifying owner entity exists and belongs to user');
            $ownerVerification = $this->verifyOwnerEntity($ownerType, $ownerId, $userId);
            if (!$ownerVerification['valid']) {
                $this->logger->warning('Owner entity verification failed', [
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'user_id' => $userId,
                    'error' => $ownerVerification['error']
                ]);
                return $this->responseHelper->error($ownerVerification['error'], 404);
            }
            $this->logger->info('Owner entity verified successfully', [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId
            ]);

            // Step 3: Get and validate uploaded file
            $this->logger->info('Step 3: Validating uploaded file');
            $uploadedFiles = $request->getUploadedFiles();
            $this->logger->debug('Uploaded files received', [
                'file_count' => count($uploadedFiles),
                'file_keys' => array_keys($uploadedFiles)
            ]);

            if (!isset($uploadedFiles['file'])) {
                $this->logger->warning('No file uploaded in request');
                return $this->responseHelper->error('No file uploaded', 400);
            }

            /** @var UploadedFileInterface $uploadedFile */
            $uploadedFile = $uploadedFiles['file'];
            
            // Check for upload errors
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                $errorMessage = $this->getUploadErrorMessage($uploadedFile->getError());
                $this->logger->error('File upload error', [
                    'error_code' => $uploadedFile->getError(),
                    'error_message' => $errorMessage
                ]);
                return $this->responseHelper->error($errorMessage, 400);
            }

            $originalFilename = $uploadedFile->getClientFilename();
            $mimeType = $uploadedFile->getClientMediaType();
            $fileSize = $uploadedFile->getSize();

            $this->logger->info('File details received', [
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'file_size' => $fileSize
            ]);

            // Validate file type
            $fileValidation = $this->validationService->validateMediaFile($mimeType, $fileSize);
            if (!$fileValidation['valid']) {
                $this->logger->warning('File validation failed', [
                    'errors' => $fileValidation['errors'],
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize
                ]);
                return $this->responseHelper->validationError($fileValidation['errors']);
            }

            $mediaType = $fileValidation['media_type'];
            $this->logger->info('File validation passed', [
                'media_type' => $mediaType
            ]);

            // Step 4: Generate unique filename and destination path
            $this->logger->info('Step 4: Generating unique filename and destination path');
            $extension = $this->getFileExtension($originalFilename);
            $uniqueFilename = $this->generateUniqueFilename($extension);
            $yearMonth = date('Ym');
            $relativePath = self::UPLOAD_BASE_PATH . '/' . $yearMonth;
            $absolutePath = $this->getPublicPath() . '/' . $relativePath;
            
            $this->logger->info('File paths generated', [
                'unique_filename' => $uniqueFilename,
                'year_month' => $yearMonth,
                'relative_path' => $relativePath,
                'absolute_path' => $absolutePath
            ]);

            // Step 5: Create directory if needed
            $this->logger->info('Step 5: Creating directory if needed');
            if (!is_dir($absolutePath)) {
                $this->logger->info('Creating upload directory', ['path' => $absolutePath]);
                if (!mkdir($absolutePath, 0755, true)) {
                    $this->logger->error('Failed to create upload directory', ['path' => $absolutePath]);
                    return $this->responseHelper->internalError('Failed to create upload directory');
                }
                $this->logger->info('Upload directory created successfully');
            } else {
                $this->logger->debug('Upload directory already exists');
            }

            // Step 6: Move uploaded file to destination
            $this->logger->info('Step 6: Moving uploaded file to destination');
            $destinationFile = $absolutePath . '/' . $uniqueFilename;
            $relativeFilePath = $relativePath . '/' . $uniqueFilename;
            
            try {
                $uploadedFile->moveTo($destinationFile);
                $this->logger->info('File moved successfully', [
                    'destination' => $destinationFile,
                    'relative_path' => $relativeFilePath
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to move uploaded file', [
                    'destination' => $destinationFile,
                    'error' => $e->getMessage()
                ]);
                return $this->responseHelper->internalError('Failed to save uploaded file');
            }

            // Step 7: Generate preview using ffmpeg
            $this->logger->info('Step 7: Generating preview using ffmpeg');
            $previewFilename = $this->generatePreviewFilename($uniqueFilename);
            $previewDestination = $absolutePath . '/' . $previewFilename;
            $relativePreviewPath = $relativePath . '/' . $previewFilename;
            
            $previewResult = $this->generatePreview($destinationFile, $previewDestination, $mediaType);
            if (!$previewResult['success']) {
                $this->logger->warning('Preview generation failed, continuing without preview', [
                    'error' => $previewResult['error']
                ]);
                $relativePreviewPath = null;
            } else {
                $this->logger->info('Preview generated successfully', [
                    'preview_path' => $relativePreviewPath
                ]);
            }

            // Step 8: Get file dimensions
            $this->logger->info('Step 8: Getting file dimensions');
            $dimensions = $this->getFileDimensions($destinationFile, $mediaType);
            $this->logger->info('File dimensions retrieved', [
                'width' => $dimensions['width'],
                'height' => $dimensions['height']
            ]);

            // Step 9: Create database record
            $this->logger->info('Step 9: Creating database record');
            $mediaData = [
                'user_id' => $userId,
                'task_id' => $ownerType === 'task' ? $ownerId : null,
                'project_id' => $ownerType === 'project' ? $ownerId : null,
                'document_id' => $ownerType === 'document' ? $ownerId : null,
                'profile_user_id' => $ownerType === 'user' ? $ownerId : null,
                'original_filename' => $originalFilename,
                'stored_filename' => $uniqueFilename,
                'file_path' => $relativeFilePath,
                'preview_path' => $relativePreviewPath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'media_type' => $mediaType,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ];

            $this->logger->debug('Media data prepared for database', [
                'original_filename' => $originalFilename,
                'stored_filename' => $uniqueFilename,
                'file_path' => $relativeFilePath,
                'preview_path' => $relativePreviewPath,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId
            ]);

            $mediaId = $this->mediaModel->createMedia($mediaData);
            $this->logger->info('Database record created', ['media_id' => $mediaId]);

            // Get created media record
            $media = $this->mediaModel->findByIdAndUserId($mediaId, $userId);
            $formattedMedia = $this->mediaModel->formatMediaForResponse($media);

            // Step 10: Return success response
            $this->logger->info('Step 10: Media upload completed successfully', [
                'media_id' => $mediaId,
                'user_id' => $userId,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'file_path' => $relativeFilePath
            ]);

            return $this->responseHelper->created($formattedMedia);

        } catch (\Exception $e) {
            $this->logger->error('Media upload failed with unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to upload media');
        }
    }

    /**
     * Validate that exactly one owner parameter is provided
     */
    private function validateOwnerParameter(array $data): array
    {
        $owners = [];
        
        if (isset($data['taskId']) && $data['taskId'] !== '' && $data['taskId'] !== null) {
            $owners[] = ['type' => 'task', 'id' => (int)$data['taskId']];
        }
        if (isset($data['projectId']) && $data['projectId'] !== '' && $data['projectId'] !== null) {
            $owners[] = ['type' => 'project', 'id' => (int)$data['projectId']];
        }
        if (isset($data['documentId']) && $data['documentId'] !== '' && $data['documentId'] !== null) {
            $owners[] = ['type' => 'document', 'id' => (int)$data['documentId']];
        }
        if (isset($data['userId']) && $data['userId'] !== '' && $data['userId'] !== null) {
            $owners[] = ['type' => 'user', 'id' => (int)$data['userId']];
        }

        if (count($owners) === 0) {
            return [
                'valid' => false,
                'errors' => ['Exactly one owner parameter is required (taskId, projectId, documentId, or userId)']
            ];
        }

        if (count($owners) > 1) {
            return [
                'valid' => false,
                'errors' => ['Only one owner parameter is allowed (taskId, projectId, documentId, or userId)']
            ];
        }

        $owner = $owners[0];
        if ($owner['id'] <= 0) {
            return [
                'valid' => false,
                'errors' => ['Owner ID must be a positive integer']
            ];
        }

        return [
            'valid' => true,
            'owner_type' => $owner['type'],
            'owner_id' => $owner['id'],
            'errors' => []
        ];
    }

    /**
     * Verify that the owner entity exists and belongs to the user
     */
    private function verifyOwnerEntity(string $ownerType, int $ownerId, int $userId): array
    {
        switch ($ownerType) {
            case 'task':
                $entity = $this->taskModel->findByIdAndUserId($ownerId, $userId);
                if ($entity === null) {
                    return ['valid' => false, 'error' => 'Task not found or access denied'];
                }
                break;
                
            case 'project':
                $entity = $this->projectModel->findByIdAndUserId($ownerId, $userId);
                if ($entity === null) {
                    return ['valid' => false, 'error' => 'Project not found or access denied'];
                }
                break;
                
            case 'document':
                $entity = $this->documentModel->findByIdAndUserId($ownerId, $userId);
                if ($entity === null) {
                    return ['valid' => false, 'error' => 'Document not found or access denied'];
                }
                break;
                
            case 'user':
                // For user profile images, the owner must be the authenticated user
                if ($ownerId !== $userId) {
                    return ['valid' => false, 'error' => 'Cannot upload media for another user'];
                }
                break;
                
            default:
                return ['valid' => false, 'error' => 'Invalid owner type'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Get file extension from filename
     */
    private function getFileExtension(string $filename): string
    {
        $parts = explode('.', $filename);
        return strtolower(end($parts));
    }

    /**
     * Generate a unique filename using UUID
     */
    private function generateUniqueFilename(string $extension): string
    {
        // Generate UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        
        return $uuid . '.' . $extension;
    }

    /**
     * Generate preview filename by adding .preview before extension
     */
    private function generatePreviewFilename(string $filename): string
    {
        $lastDotPos = strrpos($filename, '.');
        if ($lastDotPos === false) {
            return $filename . '.preview';
        }
        
        $name = substr($filename, 0, $lastDotPos);
        $extension = substr($filename, $lastDotPos + 1);
        
        // For videos, preview is always a jpg image
        if (in_array(strtolower($extension), self::VIDEO_EXTENSIONS)) {
            return $name . '.preview.jpg';
        }
        
        return $name . '.preview.' . $extension;
    }

    /**
     * Get the public directory path
     */
    private function getPublicPath(): string
    {
        return dirname(__DIR__, 2) . '/public';
    }

    /**
     * Generate preview image using ffmpeg
     */
    private function generatePreview(string $sourcePath, string $destinationPath, string $mediaType): array
    {
        $maxSize = self::PREVIEW_MAX_SIZE;
        
        // Escape paths for shell command
        $escapedSource = escapeshellarg($sourcePath);
        $escapedDestination = escapeshellarg($destinationPath);
        
        if ($mediaType === 'image') {
            // For images: scale to max 500x500 preserving aspect ratio
            $command = "ffmpeg -i {$escapedSource} -vf \"scale='min({$maxSize},iw)':'min({$maxSize},ih)':force_original_aspect_ratio=decrease\" -y {$escapedDestination} 2>&1";
        } else {
            // For videos: extract first frame as preview image
            $command = "ffmpeg -i {$escapedSource} -vf \"scale='min({$maxSize},iw)':'min({$maxSize},ih)':force_original_aspect_ratio=decrease\" -vframes 1 -y {$escapedDestination} 2>&1";
        }
        
        $this->logger->debug('Executing ffmpeg command', ['command' => $command]);
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->logger->warning('ffmpeg command failed', [
                'return_code' => $returnCode,
                'output' => implode("\n", $output)
            ]);
            return [
                'success' => false,
                'error' => 'ffmpeg command failed with code ' . $returnCode
            ];
        }
        
        // Verify preview file was created
        if (!file_exists($destinationPath)) {
            return [
                'success' => false,
                'error' => 'Preview file was not created'
            ];
        }
        
        return ['success' => true, 'error' => null];
    }

    /**
     * Get file dimensions (width and height)
     */
    private function getFileDimensions(string $filePath, string $mediaType): array
    {
        $width = null;
        $height = null;
        
        if ($mediaType === 'image') {
            // For images, use getimagesize
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        } else {
            // For videos, use ffprobe
            $escapedPath = escapeshellarg($filePath);
            $command = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 {$escapedPath} 2>&1";
            
            $this->logger->debug('Executing ffprobe command for dimensions', ['command' => $command]);
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output[0])) {
                $dimensions = explode('x', $output[0]);
                if (count($dimensions) === 2) {
                    $width = (int)$dimensions[0];
                    $height = (int)$dimensions[1];
                }
            }
        }
        
        return ['width' => $width, 'height' => $height];
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }
}
