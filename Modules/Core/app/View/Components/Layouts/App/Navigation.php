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
     * Get the navigation items grouped by section.
     * Each group has a 'heading' and 'items' array.
     */
    protected function getNavigationItems(): array
    {
        return [
            [
                'heading' => __('Platform'),
                'items' => [
                    [
                        'label'  => __('Dashboard'),
                        'route'  => 'admin::dashboard',
                        'icon'   => 'home',
                        'active' => request()->routeIs('admin::dashboard'),
                    ],
                ],
            ],
            [
                'heading' => __('Management'),
                'items' => [
                    [
                        'label'  => __('Users'),
                        'route'  => 'admin::users.list',
                        'icon'   => 'users',
                        'active' => request()->routeIs('admin::users.*'),
                    ],
                ],
            ],
            [
                'heading' => __('Settings'),
                'items' => [
                    [
                        'label'  => __('General'),
                        'route'  => 'admin::settings.general',
                        'icon'   => 'cog-6-tooth',
                        'active' => request()->routeIs('admin::settings.general'),
                    ],
                    [
                        'label'  => __('Website'),
                        'route'  => 'admin::settings.website',
                        'icon'   => 'globe-alt',
                        'active' => request()->routeIs('admin::settings.website'),
                    ],
                    [
                        'label'  => __('Scripts'),
                        'route'  => 'admin::settings.scripts.third-party',
                        'icon'   => 'code-bracket',
                        'active' => request()->routeIs('admin::settings.scripts.*'),
                    ],
                ],
            ],
            [
                'heading' => __('Documentation'),
                'items' => [
                    [
                        'label'  => __('Help'),
                        'route'  => 'admin::documentation.help',
                        'icon'   => 'question-mark-circle',
                        'active' => request()->routeIs('admin::documentation.help'),
                    ],
                    [
                        'label'  => __('Changelog'),
                        'route'  => 'admin::documentation.changelog',
                        'icon'   => 'document-text',
                        'active' => request()->routeIs('admin::documentation.changelog'),
                    ],
                ],
            ],
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
