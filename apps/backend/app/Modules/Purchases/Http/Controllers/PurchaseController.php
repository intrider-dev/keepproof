<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Households\Models\Household;
use App\Modules\Purchases\Actions\CreatePurchase;
use App\Modules\Purchases\Actions\UpdatePurchase;
use App\Modules\Purchases\Http\Requests\ListPurchasesRequest;
use App\Modules\Purchases\Http\Requests\StorePurchaseRequest;
use App\Modules\Purchases\Http\Requests\UpdatePurchaseRequest;
use App\Modules\Purchases\Http\Resources\PurchaseResource;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class PurchaseController extends Controller
{
    public function index(ListPurchasesRequest $request, Household $household): AnonymousResourceCollection
    {
        $this->authorize('view', $household);
        $query = $household->purchases()->with(['items', 'deadlines']);

        $search = $request->stringValue('search');
        if ($search !== null) {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->whereRaw('LOWER(merchant) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(receipt_number, ?)) LIKE ?', ['', $term])
                    ->orWhereRaw('LOWER(COALESCE(notes, ?)) LIKE ?', ['', $term]);
            });
        }

        $status = $request->stringValue('status');
        if ($status !== null) {
            $query->where('state', $status);
        }

        $merchant = $request->stringValue('merchant');
        if ($merchant !== null) {
            $query->whereRaw('LOWER(merchant) LIKE ?', ['%'.mb_strtolower($merchant).'%']);
        }

        $dateFrom = $request->stringValue('date_from');
        if ($dateFrom !== null) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        $dateTo = $request->stringValue('date_to');
        if ($dateTo !== null) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $deadlineBefore = $request->stringValue('deadline_before');
        if ($deadlineBefore !== null) {
            $query->whereHas('deadlines', static function ($deadlines) use ($deadlineBefore): void {
                $deadlines->where('active', true)->whereDate('due_at', '<=', $deadlineBefore);
            });
        }

        return PurchaseResource::collection(
            $query->orderByDesc('purchase_date')->orderByDesc('id')->paginate($request->perPage()),
        );
    }

    public function store(
        StorePurchaseRequest $request,
        Household $household,
        CreatePurchase $createPurchase,
    ): JsonResponse {
        $this->authorize('create', [Purchase::class, $household]);
        $purchase = $createPurchase->execute($this->authenticatedUser($request), $household, $request->purchaseData());

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }

    public function show(Household $household, Purchase $purchase): PurchaseResource
    {
        $this->authorize('view', $purchase);

        return new PurchaseResource($purchase->load(['items', 'deadlines']));
    }

    public function update(
        UpdatePurchaseRequest $request,
        Household $household,
        Purchase $purchase,
        UpdatePurchase $updatePurchase,
    ): PurchaseResource {
        $this->authorize('update', $purchase);
        $updated = $updatePurchase->execute($this->authenticatedUser($request), $purchase, $request->purchaseData());

        return new PurchaseResource($updated);
    }

    public function destroy(
        Request $request,
        Household $household,
        Purchase $purchase,
        AuditRecorder $audit,
    ): JsonResponse {
        $this->authorize('delete', $purchase);

        DB::transaction(function () use ($request, $household, $purchase, $audit): void {
            $audit->record('purchase.deleted', $this->authenticatedUser($request), $household, $purchase);
            $purchase->delete();
        });

        return response()->json(status: 204);
    }
}
