<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller implements HasMiddleware
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public static function middleware(): array
    {
        return [
            'auth:api',
            new Middleware('permission:view_user', only: ['index', 'show']),
            new Middleware('permission:create_user', only: ['store']),
            new Middleware('permission:update_user', only: ['update', 'changePassword']),
            new Middleware('permission:delete_user', only: ['destroy']),
        ];
    }

    public function index()
    {
        return UserResource::collection($this->userService->getAll());
    }

    public function show($id)
    {
        $user = $this->userService->getById($id);
        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    public function store(Request $request)
    {
        try {
            $user = $this->userService->create($request->all());
            return response()->json([
                'message' => 'User berhasil dibuat',
                'data' => new UserResource($user)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $this->userService->update($id, $request->all());
            return response()->json([
                'message' => 'Profil berhasil diperbarui',
                'data' => new UserResource($user)
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $this->userService->changePassword(Auth::id(), $request->all());
            return response()->json(['message' => 'Password berhasil diperbarui'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy($id)
    {
        try {
            $this->userService->delete($id);
            return response()->json(['message' => 'Pengguna berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
