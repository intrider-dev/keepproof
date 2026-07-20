<?php

declare(strict_types=1);

namespace App\Modules\Exports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Exports\Actions\ExportCalendarEvent;
use App\Modules\Exports\Actions\ExportClaimPackage;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class PurchaseExportController extends Controller
{
    public function calendar(
        Request $request,
        Household $household,
        Purchase $purchase,
        string $deadline,
        ExportCalendarEvent $exportCalendar,
        AuditRecorder $audit,
    ): Response {
        $this->authorize('view', $purchase);
        $record = $purchase->deadlines()->whereKey($deadline)->where('active', true)->firstOrFail();
        $ics = $exportCalendar->execute($record);
        $audit->record('deadline.calendar_exported', $this->authenticatedUser($request), $household, $record);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="keepproof-deadline.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function claimPackage(
        Request $request,
        Household $household,
        Purchase $purchase,
        ExportClaimPackage $exportClaimPackage,
        AuditRecorder $audit,
    ): BinaryFileResponse {
        $this->authorize('view', $purchase);
        $path = $exportClaimPackage->execute($purchase);
        $audit->record('purchase.claim_package_exported', $this->authenticatedUser($request), $household, $purchase);

        return response()->download(
            $path,
            'keepproof-claim-'.$purchase->id.'.zip',
            ['Cache-Control' => 'private, no-store'],
        )->deleteFileAfterSend(true);
    }
}
