<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dashboard;

class DashboardController
{
    private array $app;

    public function __construct(array $app)
    {
        $this->app = $app;
    }

    public function index(): void
    {
        $user = requireAuth();
        $filters = [
            'from' => sanitizeText($_GET['from'] ?? '', 20),
            'to' => sanitizeText($_GET['to'] ?? '', 20),
        ];
        if ($filters['from'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) $filters['from'] = '';
        if ($filters['to'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) $filters['to'] = '';
        $summary = Dashboard::summary($filters);
        view('dashboard/index', [
            'app' => $this->app,
            'user' => $user,
            'summary' => $summary,
            'filters' => $filters,
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Resumen operativo del Centro Auxiliar Plaza Venezuela',
            'activeMenu' => 'dashboard',
        ]);
    }
}
