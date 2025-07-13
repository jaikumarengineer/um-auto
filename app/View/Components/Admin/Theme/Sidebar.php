<?php

namespace App\View\Components\Admin\Theme;

use Closure;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Sidebar extends Component
{
    public ?User $authUser;
    public $segment1;
    public $segment2;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->authUser = Auth::user();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.admin.theme.sidebar', [
            'menu' => $this->generateMenu(),
            'outlineMenu' => $this->getOutlineMenu(),
        ]);
    }

    public function generateMenu()
    {
        return [
            'dashboard-group' => [
                'title' => 'Dashboard & Platform',
                'menu' => Arr::collapse([
                    $this->dashboardRoutes(),
                    $this->settingsRoutes()
                ]),
            ],
            // 'settings-group' => [
            //     'title' => 'Server & Settings',
            //     'menu' => $this->settingsRoutes(),
            // ],
        ];
    }

    public function getOutlineMenu()
    {
        return [
            [
                'type' => 'link',
                'name' => __('Settings'),
                'icon' => 'cog-6-tooth',
                'url' => 'https://github.com/laravel/livewire-starter-kit',
                'target' => '_blank',
            ],
            [
                'type' => 'link',
                'name' => __('Help'),
                'icon' => 'question-mark-circle',
                'url' => 'https://laravel.com/docs/starter-kits#livewire',
                'target' => '_blank',
            ],
        ];
    }

    public function dashboardRoutes()
    {
        return [
            [
                'type' => 'link',
                'name' => 'Dashboard',
                'icon' => 'home',
                'active' => request()->routeIs('admin::dashboard'),
                'url' => route('admin::dashboard'),
                'submenu' => [],
            ]
        ];
    }

    public function settingsRoutes()
    {
        return [
            [
                'type' => 'dropdown',
                'name' => 'Settings',
                'icon' => 'cog-6-tooth',
                'active' => request()->routeIs('admin::settings.*'),
                'url' => '#',
                'submenu' => [
                    [
                        'type' => 'link',
                        'name' => 'General',
                        'icon' => 'adjustments-horizontal',
                        'active' => request()->routeIs('admin::settings.general'),
                        'url' => route('admin::settings.general'),
                        'submenu' => [],
                    ],
                    [
                        'type' => 'link',
                        'name' => 'Website',
                        'icon' => 'globe-alt',
                        'active' => request()->routeIs('admin::settings.website'),
                        'url' => route('admin::settings.website'),
                        'submenu' => [],
                    ],
                    [
                        'type' => 'link',
                        'name' => 'Third Party Scripts',
                        'icon' => 'arrow-top-right-on-square',
                        'active' => request()->routeIs('admin::settings.scripts.third-party'),
                        'url' => route('admin::settings.scripts.third-party'),
                        'submenu' => [],
                    ],
                    [
                        'type' => 'link',
                        'name' => 'Custom Scripts',
                        'icon' => 'pencil-square',
                        'active' => request()->routeIs('admin::settings.scripts.custom'),
                        'url' => route('admin::settings.scripts.custom'),
                        'submenu' => [],
                    ],
                ]
            ]
        ];
    }
}
