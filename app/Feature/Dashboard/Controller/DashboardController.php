<?php

declare(strict_types=1);

namespace App\Feature\Dashboard\Controller;

use Illuminate\View\View;

class DashboardController
{
    public function index(): View
    {
        return view('workbench.index');
    }
}
