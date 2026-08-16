<?php

namespace App\View\Components;

use App\Models\Project;
use Illuminate\View\Component;
use Illuminate\View\View;

class DashboardLayout extends Component
{
    public function __construct(public ?string $title = null, public ?Project $project = null)
    {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.dashboard');
    }
}
