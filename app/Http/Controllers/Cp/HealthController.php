<?php

namespace App\Http\Controllers\Cp;

use App\Cms\ContentCheckup;
use App\Cms\SystemStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What needs looking after: content worth tidying up and, for admins, the
 * server the site runs on.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, ContentCheckup $checkup, SystemStatus $system): Response
    {
        return Inertia::render('cp/Health', [
            'checkup' => $checkup->run(),
            // Only admins can do something about it.
            'system' => $request->user()?->isAdmin() ? $system->report() : null,
        ]);
    }
}
