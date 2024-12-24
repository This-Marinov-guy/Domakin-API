<?php

namespace App\Files;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary();
    }

    public function singleUpload($filePath, array $options = [])
    {
        try {
            $uploadResult = $this->cloudinary::upload($filePath, $options)->getSecurePath();
            return $uploadResult;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function multiUpload(array $filePaths, array $options = [])
    {
        $results = [];
        foreach ($filePaths as $filePath) {
            $results[] = $this->cloudinary::upload($filePath, $options)->getSecurePath();
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

    public function deleteFile($fileName)
    {
        try {
            $result = $this->cloudinary::destroy($fileName);
            if ($result['result'] === 'ok') {
                return ['success' => 'File deleted successfully.'];
            } else {
                return ['error' => 'Failed to delete file.'];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}