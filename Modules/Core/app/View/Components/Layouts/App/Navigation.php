<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class Navigation extends Component
{
    /**
     * Navigation items for the sidebar.
     */
    public array $navigationItems = [];

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->navigationItems = $this->getNavigationItems();
    }

    /**
     * Get the navigation items.
     */
    protected function getNavigationItems(): array
    {
        return [
            [
                'label' => __('Dashboard'),
                'route' => 'dashboard',
                'icon' => 'home',
                'active' => request()->routeIs('dashboard'),
            ],
            // Add more navigation items here
        ];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('core::components.layouts.app.navigation');
    }
}
