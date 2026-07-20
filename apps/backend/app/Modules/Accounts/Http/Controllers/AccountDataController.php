<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\DeleteAccount;
use App\Modules\Accounts\Actions\ExportAccountData;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AccountDataController extends Controller
{
    public function export(
        Request $request,
        ExportAccountData $exportAccountData,
        AuditRecorder $audit,
    ): BinaryFileResponse {
        $user = $this->authenticatedUser($request);
        $path = $exportAccountData->execute($user);
        $audit->record('account.data_exported', $user, subject: $user);

        return response()->download(
            $path,
            'keepproof-account-export.zip',
            ['Cache-Control' => 'private, no-store'],
        )->deleteFileAfterSend(true);
    }

    public function destroy(Request $request, DeleteAccount $deleteAccount): Response
    {
        $user = $this->authenticatedUser($request);
        $deleteAccount->execute($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
