<?php

namespace App\Services;

use App\Files\CloudinaryService;
use App\Models\ListingApplication;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ListingApplicationService
{
    private const LISTING_APPLICATIONS_FOLDER = 'listing_applications';

    public function __construct(
        private UserService $userService,
        private CloudinaryService $cloudinary
    ) {
    }

    /**
     * Build the final images string: existing reordered `images` (string) + uploaded `new_images` (array) appended at the end.
     */
    public function resolveImagesString(Request $request, ?string $referenceId = null): string
    {
        $existing = $request->get('images');
        if (is_array($existing)) {
            $existing = implode(', ', $existing);
        }
        $existing = trim((string) $existing);

        $newFiles = $this->getNewImageFiles($request);
        if (empty($newFiles)) {
            return $existing;
        }

        $folder = self::LISTING_APPLICATIONS_FOLDER;
        if ($referenceId) {
            $folder .= '/' . $referenceId;
        }

        $uploadedUrls = $this->cloudinary->multiUpload($newFiles, ['folder' => $folder]);
        $newPart = implode(', ', $uploadedUrls);

        return $existing !== '' ? $existing . ', ' . $newPart : $newPart;
    }

    /**
     * @return array<UploadedFile>
     */
    private function getNewImageFiles(Request $request): array
    {
        if (! $request->hasFile('new_images')) {
            return [];
        }

        $files = $request->file('new_images');
        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }

    /**
     * Normalize incoming payload keys from camelCase (API) to snake_case (DB columns).
     */
    public function mapCamelToSnakeKeys(array $data): array
    {
        $camelToSnake = [
            'petsAllowed'     => 'pets_allowed',
            'smokingAllowed'  => 'smoking_allowed',
            'furnishedType'   => 'furnished_type',
            'sharedSpace'     => 'shared_space',
            'availableFrom'   => 'available_from',
            'availableTo'     => 'available_to',
        ];

        foreach ($camelToSnake as $from => $to) {
            if (array_key_exists($from, $data)) {
                $data[$to] = $data[$from];
                unset($data[$from]);
            }
        }

        return $data;
    }

    /**
     * Save or update a listing application draft from request data.
     * Sets user_id when present in request (via JWT).
     * Request: images (string) = reordered existing; new_images (array) = new files to upload and append.
     *
     * @param  int|null  $stepOverride  When provided (e.g. from validate step), forces this step number on the saved record.
     * @return ListingApplication|null The saved/updated model, or null if update by referenceId but application not found.
     *
     * @throws \Exception On create/update failure.
     */
    public function saveDraft(Request $request, ?int $stepOverride = null): ?ListingApplication
    {
        $userId = $this->userService->extractIdFromRequest($request);

        $referenceId = $request->get('referenceId') ?? $request->get('reference_id');
        $data = array_filter(
            $request->except(['id', 'referenceId', 'reference_id', 'user_id', 'new_images']),
            fn ($v) => ! is_null($v)
        );

        // Normalize camelCase API keys to snake_case model attributes
        $data = $this->mapCamelToSnakeKeys($data);

        if ($userId !== null) {
            $data['user_id'] = $userId;
        }

        if ($stepOverride !== null) {
            $data['step'] = $stepOverride;
        }

        // images (string) = reordered existing; new_images (array) = upload and append to the back
        if ($request->has('images') || $request->hasFile('new_images')) {
            $data['images'] = $this->resolveImagesString($request, $referenceId);
        }

        if ($referenceId) {
            $query = ListingApplication::where('reference_id', $referenceId);
            // $query->where(function ($q) use ($userId) {
            //     $q->whereNull('user_id');
            //     if ($userId !== null) {
            //         $q->orWhere('user_id', $userId);
            //     }
            // });
            $application = $query->first();

            if (!$application) {
                return null;
            }

            $application->update($data);

            return $application;
        }

        $application = ListingApplication::create($data);
        $application->refresh(); // load DB-generated reference_id (and other defaults)

        return $application;
    }
}
