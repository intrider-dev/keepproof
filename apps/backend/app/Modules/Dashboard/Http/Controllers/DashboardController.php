<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Http\Resources\ScheduledReminderResource;
use App\Modules\Dashboard\Http\Resources\UpcomingDeadlineResource;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Http\Resources\ReceiptDocumentResource;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Models\Household;
use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Notifications\Models\Reminder;
use App\Modules\Purchases\Models\PurchaseDeadline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use KeepProof\DeadlineEngine\DeadlineKind;

final class DashboardController extends Controller
{
    public function show(Request $request, Household $household): JsonResponse
    {
        $this->authorize('view', $household);
        $user = $this->authenticatedUser($request);
        $deadlineQuery = PurchaseDeadline::query()
            ->where('household_id', $household->id)
            ->where('active', true)
            ->where('due_at', '>=', now());
        $deadlines = (clone $deadlineQuery)
            ->with(['purchase.items'])
            ->orderBy('due_at')
            ->limit(20)
            ->get();
        $reviewDocuments = ReceiptDocument::query()
            ->where('household_id', $household->id)
            ->where('state', DocumentState::NeedsReview)
            ->with(['fields', 'purchase.items', 'purchase.deadlines'])
            ->latest()
            ->limit(10)
            ->get();
        $reminders = Reminder::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->where('status', ReminderStatus::Scheduled)
            ->where('remind_at', '>=', now())
            ->with(['deadline.purchase'])
            ->orderBy('remind_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'counts' => [
                    'upcoming_returns' => (clone $deadlineQuery)->where('kind', DeadlineKind::Return)->count(),
                    'active_warranties' => (clone $deadlineQuery)->where('kind', DeadlineKind::Warranty)->count(),
                    'needs_review' => $reviewDocuments->count(),
                ],
                'upcoming_deadlines' => UpcomingDeadlineResource::collection($deadlines),
                'needs_review' => ReceiptDocumentResource::collection($reviewDocuments),
                'scheduled_reminders' => ScheduledReminderResource::collection($reminders),
            ],
        ]);
    }
}
