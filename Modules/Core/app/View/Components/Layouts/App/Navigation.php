<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class Navigation extends Component
{
    /**
     * Navigation tree for the sidebar.
     *
     * Supported node types:
     * - heading  → section label only (not clickable)
     * - item     → link (default when type is omitted)
     * - group    → expandable Flux sidebar group with children
     */
    public array $navigationItems = [];

    public function __construct()
    {
        $this->navigationItems = $this->getNavigationItems();
    }

    protected function getNavigationItems(): array
    {
        return [
            [
                'type' => 'heading',
                'label' => __('Main'),
            ],
            [
                'label' => __('Dashboard'),
                'route' => 'admin::dashboard',
                'icon' => 'home',
                'active' => request()->routeIs('admin::dashboard'),
            ],

            [
                'type' => 'heading',
                'label' => __('Management'),
            ],
            [
                'type' => 'group',
                'heading' => __('Grouping'),
                'icon' => 'globe-alt',
                'expandable' => true,
                'expanded' => request()->routeIs('admin::grouping.list'),
                'children' => [
                    [
                        'label' => __('Country List'),
                        'route' => 'admin::grouping.list',
                        'icon' => 'globe-alt',
                        'active' => request()->routeIs('admin::grouping.list'),
                    ]
                ],
            ],
            [
                'label' => __('Users'),
                'route' => 'admin::users.list',
                'icon' => 'users',
                'active' => request()->routeIs('admin::users.*'),
            ],
            [
                'type' => 'heading',
                'label' => __('Server & Settings'),
            ],
            [
                'type' => 'group',
                'heading' => __('Settings'),
                'icon' => 'cog-6-tooth',
                'expandable' => true,
                'expanded' => request()->routeIs('admin::settings.*'),
                'children' => [
                    [
                        'label' => __('General'),
                        'route' => 'admin::settings.general',
                        'icon' => 'adjustments-horizontal',
                        'active' => request()->routeIs('admin::settings.general'),
                    ],
                    [
                        'label' => __('Website'),
                        'route' => 'admin::settings.website',
                        'icon' => 'globe-alt',
                        'active' => request()->routeIs('admin::settings.website'),
                    ],
                    [
                        'label' => __('Mail'),
                        'route' => 'admin::settings.mail',
                        'icon' => 'envelope',
                        'active' => request()->routeIs('admin::settings.mail'),
                    ],
                    [
                        'label' => __('Server Logs'),
                        'route' => 'admin::settings.logs',
                        'icon' => 'document-magnifying-glass',
                        'active' => request()->routeIs('admin::settings.logs'),
                    ],
                    [
                        'label' => __('Server Info'),
                        'route' => 'admin::settings.server-info',
                        'icon' => 'server-stack',
                        'active' => request()->routeIs('admin::settings.server-info'),
                    ],
                    [
                        'type' => 'group',
                        'heading' => __('Scripts'),
                        'icon' => 'code-bracket',
                        'expandable' => true,
                        'expanded' => request()->routeIs('admin::settings.scripts.*'),
                        'children' => [
                            [
                                'label' => __('Third-party'),
                                'route' => 'admin::settings.scripts.third-party',
                                'icon' => 'puzzle-piece',
                                'active' => request()->routeIs('admin::settings.scripts.third-party'),
                            ],
                            [
                                'label' => __('Custom'),
                                'route' => 'admin::settings.scripts.custom',
                                'icon' => 'command-line',
                                'active' => request()->routeIs('admin::settings.scripts.custom'),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function render(): View
    {
        return view('core::components.layouts.app.navigation');
    }
}
