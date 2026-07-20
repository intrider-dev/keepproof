<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InAppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $notifications = $user->notifications()->latest()->paginate($perPage);

        return response()->json($notifications);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return response()->json(['data' => ['id' => $record->id, 'read_at' => $record->read_at?->toAtomString()]]);
    }
}
