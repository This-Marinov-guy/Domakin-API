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

    public function singleUpload($file, array $options = [])
    {
        try {
            $uploadResult = $this->cloudinary::upload($file->getRealPath(), $options);
            return $uploadResult->getSecurePath();
        } catch (\Exception $e) {
            throw new \Exception("File upload failed: " . $e->getMessage());
        }
    }

    public function multiUpload(array $files, array $options = [])
    {
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
