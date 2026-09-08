<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class Navigation extends Component
{
    /**
     * Navigation tree for the sidebar (items + expandable groups).
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
                'type' => 'item',
                'label' => __('Dashboard'),
                'route' => 'admin::dashboard',
                'icon' => 'home',
                'active' => request()->routeIs('admin::dashboard'),
            ],
            [
                'type' => 'item',
                'label' => __('Users'),
                'route' => 'admin::users.list',
                'icon' => 'users',
                'active' => request()->routeIs('admin::users.*'),
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
            [
                'type' => 'group',
                'heading' => __('Documentation'),
                'icon' => 'book-open',
                'expandable' => true,
                'expanded' => request()->routeIs('admin::documentation.*') || request()->routeIs('admin::components.*'),
                'children' => [
                    [
                        'label' => __('Help'),
                        'route' => 'admin::documentation.help',
                        'icon' => 'question-mark-circle',
                        'active' => request()->routeIs('admin::documentation.help'),
                    ],
                    [
                        'label' => __('Changelog'),
                        'route' => 'admin::documentation.changelog',
                        'icon' => 'document-text',
                        'active' => request()->routeIs('admin::documentation.changelog'),
                    ],
                    [
                        'label' => __('Form components'),
                        'route' => 'admin::components.forms',
                        'icon' => 'squares-2x2',
                        'active' => request()->routeIs('admin::components.forms'),
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
