<?php

namespace App\Files;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary();
    }

    /**
     * Sanitize folder name to ensure it's valid for Cloudinary
     * Removes invalid characters and replaces them with underscores
     */
    private function sanitizeFolderName(string $folderName): string
    {
        // Replace invalid characters with underscores
        // Cloudinary allows: alphanumeric, hyphens, underscores, forward slashes (for nested folders)
        $sanitized = preg_replace('/[^a-zA-Z0-9\-_\/]/', '_', $folderName);
        
        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        
        // Remove leading/trailing underscores
        $sanitized = trim($sanitized, '_');
        
        return $sanitized;
    }

    public function singleUpload($file, array $options = [])
    {
        if (isset($options['folder'])) {
            $options['folder'] = $this->sanitizeFolderName($options['folder']);
            
            if (env('APP_ENV') !== 'prod') {
            $options['folder'] = 'test/' . $options['folder'];
            }
        }

        try {
            $uploadResult = $this->cloudinary::upload($file->getRealPath(), $options);
            return $uploadResult->getSecurePath();
        } catch (\Exception $e) {
            throw new \Exception("File upload failed: " . $e->getMessage());
        }
    }

    public function multiUpload(array $files, array $options = [])
    {
        if (isset($options['folder'])) {
            $options['folder'] = $this->sanitizeFolderName($options['folder']);
            
            if (env('APP_ENV') !== 'prod') {
            $options['folder'] = 'test/' . $options['folder'];
            }
        }

        $results = [];

        foreach ($files as $file) {
            try {
                $uploadResult = $this->cloudinary::upload($file->getRealPath(), $options);
                $results[] = $uploadResult->getSecurePath();
            } catch (\Exception $e) {
                throw new \Exception("File upload failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    public function deleteFolder($folderPath)
    {
        try {
            $resources = $this->cloudinary::resources([
                'type' => 'upload',
                'prefix' => $folderPath
            ]);

            foreach ($resources['resources'] as $resource) {
                $this->cloudinary::destroy($resource['public_id']);
            }

            $this->cloudinary::deleteFolder($folderPath);

            return ['success' => 'Folder and its contents deleted successfully.'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
