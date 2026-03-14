<?php

namespace App\Http\Controllers;

use App\Classes\ApiResponseClass;
use App\Models\ReferralBonus;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Referral Bonuses")
 */
class ReferralBonusController extends Controller
{
    // ---------------------------------------------------------------
    // GET – list all referral bonuses (admin)
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/referral-bonus/list",
     *     summary="List all referral bonuses (admin)",
     *     tags={"Referral Bonuses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page",          in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page",       in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status",         in="query", description="Filter by status (1-4)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type",           in="query", description="Filter by type (1=listing,2=viewing,3=renting)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="referral_code",  in="query", description="Filter by referral code (partial)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id",        in="query", description="Filter by user UUID", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function list(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));
        $page    = max(1, (int) $request->get('page', 1));

        $query = ReferralBonus::query();

        if ($request->filled('status')) {
            $query->where('status', (int) $request->get('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', (int) $request->get('type'));
        }
        if ($request->filled('referral_code')) {
            $query->where('referral_code', 'ILIKE', '%' . trim($request->get('referral_code')) . '%');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $allowed   = ['id', 'created_at', 'amount', 'status', 'type'];
        $sortBy    = in_array($request->get('sort_by'), $allowed, true) ? $request->get('sort_by') : 'created_at';
        $sortDir   = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $paginator = $query->orderBy($sortBy, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponseClass::sendSuccess([
            'data'         => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    // ---------------------------------------------------------------
    // GET – list own referral bonuses (agent / any authenticated user)
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/referral-bonus/my-list",
     *     summary="List the current user's referral bonuses",
     *     tags={"Referral Bonuses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page",     in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function myList(Request $request, UserService $userService): JsonResponse
    {
        $userId = $userService->extractIdFromRequest($request);

        if (!$userId) {
            return ApiResponseClass::sendError('Unauthorized', null, 401);
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 15)));
        $page    = max(1, (int) $request->get('page', 1));

        $paginator = ReferralBonus::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponseClass::sendSuccess([
            'data'         => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ]);
    }

    // ---------------------------------------------------------------
    // GET – show single referral bonus (admin)
    // ---------------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/api/v1/referral-bonus/{id}",
     *     summary="Get a referral bonus by ID (admin)",
     *     tags={"Referral Bonuses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=400, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $bonus = ReferralBonus::find($id);

        if (!$bonus) {
            return ApiResponseClass::sendError('Referral bonus not found');
        }

        return ApiResponseClass::sendSuccess($bonus);
    }

    // ---------------------------------------------------------------
    // POST – create referral bonus (admin)
    // ---------------------------------------------------------------

    /**
     * @OA\Post(
     *     path="/api/v1/referral-bonus/create",
     *     summary="Create a referral bonus (admin)",
     *     tags={"Referral Bonuses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"referral_code","type","reference_id"},
     *             @OA\Property(property="referral_code",  type="string",  example="PROMO2026"),
     *             @OA\Property(property="type",           type="integer", example=1, description="1=listing, 2=viewing, 3=renting"),
     *             @OA\Property(property="reference_id",   type="string",  example="42"),
     *             @OA\Property(property="user_id",        type="string",  format="uuid", nullable=true),
     *             @OA\Property(property="amount",         type="integer", example=100),
     *             @OA\Property(property="status",         type="integer", example=1, description="1=waiting_approval, 2=pending, 3=completed, 4=rejected"),
     *             @OA\Property(property="public_note",    type="string",  nullable=true),
     *             @OA\Property(property="internal_note",  type="string",  nullable=true),
     *             @OA\Property(property="metadata",       type="object",  nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Created"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'referral_code' => 'required|string',
            'type'          => 'required|integer|in:1,2,3',
            'reference_id'  => 'required|string',
            'user_id'       => 'nullable|uuid',
            'amount'        => 'nullable|integer|min:0',
            'status'        => 'nullable|integer|in:1,2,3,4',
            'public_note'   => 'nullable|string',
            'internal_note' => 'nullable|string',
            'metadata'      => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), []);
        }

        $bonus = ReferralBonus::create([
            'referral_code' => $request->get('referral_code'),
            'type'          => (int) $request->get('type'),
            'reference_id'  => $request->get('reference_id'),
            'user_id'       => $request->get('user_id'),
            'amount'        => $request->get('amount', 100),
            'status'        => $request->get('status', ReferralBonus::STATUS_WAITING_APPROVAL),
            'public_note'   => $request->get('public_note'),
            'internal_note' => $request->get('internal_note'),
            'metadata'      => $request->get('metadata'),
        ]);

        return ApiResponseClass::sendSuccess($bonus);
    }

    // ---------------------------------------------------------------
    // PATCH – update referral bonus (admin)
    // ---------------------------------------------------------------

    /**
     * @OA\Patch(
     *     path="/api/v1/referral-bonus/edit",
     *     summary="Update a referral bonus (admin)",
     *     tags={"Referral Bonuses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id",             type="integer", example=1),
     *             @OA\Property(property="referral_code",  type="string",  nullable=true),
     *             @OA\Property(property="user_id",        type="string",  format="uuid", nullable=true),
     *             @OA\Property(property="amount",         type="integer", nullable=true),
     *             @OA\Property(property="status",         type="integer", nullable=true, description="1=waiting_approval, 2=pending, 3=completed, 4=rejected"),
     *             @OA\Property(property="type",           type="integer", nullable=true, description="1=listing, 2=viewing, 3=renting"),
     *             @OA\Property(property="reference_id",   type="string",  nullable=true),
     *             @OA\Property(property="public_note",    type="string",  nullable=true),
     *             @OA\Property(property="internal_note",  type="string",  nullable=true),
     *             @OA\Property(property="metadata",       type="object",  nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=400, description="Not found")
     * )
     */
    public function edit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'            => 'required|integer',
            'referral_code' => 'nullable|string',
            'user_id'       => 'nullable|uuid',
            'amount'        => 'nullable|integer|min:0',
            'status'        => 'nullable|integer|in:1,2,3,4',
            'type'          => 'nullable|integer|in:1,2,3',
            'reference_id'  => 'nullable|string',
            'public_note'   => 'nullable|string',
            'internal_note' => 'nullable|string',
            'metadata'      => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponseClass::sendInvalidFields($validator->errors()->toArray(), []);
        }

        $bonus = ReferralBonus::find($request->get('id'));

        if (!$bonus) {
            return ApiResponseClass::sendError('Referral bonus not found');
        }

        $bonus->update($request->except('id'));

        return ApiResponseClass::sendSuccess($bonus);
    }

    // ---------------------------------------------------------------
    // DELETE – delete referral bonus (admin)
    // ---------------------------------------------------------------

    /**
     * @OA\Delete(
     *     path="/api/v1/referral-bonus/delete",
     *     summary="Delete a referral bonus (admin)",
     *     tags={"Referral Bonuses"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=400, description="Not found")
     * )
     */
    public function destroy(Request $request): JsonResponse
    {
        $bonus = ReferralBonus::find($request->get('id'));

        if (!$bonus) {
            return ApiResponseClass::sendError('Referral bonus not found');
        }

        $bonus->delete();

        return ApiResponseClass::sendSuccess(['message' => 'Referral bonus deleted successfully']);
    }
}
