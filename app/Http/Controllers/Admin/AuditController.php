<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\ActivityLog;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;

class AuditController extends Controller
{
    /**
     * AuditController constructor.
     */
    public function __construct(private ViewFactory $view)
    {
    }

    /**
     * Show the admin audit trail. Every mutating request to the admin area
     * is recorded by the AuditAdminActions middleware under an admin:*
     * event; this page is the human-readable view over that trail.
     */
    public function index(): View
    {
        $entries = ActivityLog::query()
            ->where('event', 'like', 'admin:%')
            ->with('actor')
            ->orderByDesc('timestamp')
            ->paginate(50);

        return $this->view->make('admin.audit.index', ['entries' => $entries]);
    }
}
