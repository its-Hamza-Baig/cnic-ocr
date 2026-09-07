<?php

namespace App\Http\Controllers;

use App\Enums\OcrScanStatus;
use App\Enums\VerificationStatus;
use App\Models\OcrScan;
use App\Models\Person;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', Person::class);

        return view('dashboard.index', [
            'personCount' => Person::query()->count(),
            'pendingCount' => Person::query()->where('verification_status', VerificationStatus::Pending)->count(),
            'verifiedCount' => Person::query()->where('verification_status', VerificationStatus::Verified)->count(),
            'failedOcrCount' => OcrScan::query()->where('status', OcrScanStatus::Failed)->count(),
            'recentPersons' => Person::query()->latest()->limit(5)->get(),
        ]);
    }
}
