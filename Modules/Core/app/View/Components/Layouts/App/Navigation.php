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
        $user = auth()->user();
        $master = $user?->isMaster() ?? false;
        $purchase = $master || ($user?->isPurchase() ?? false);
        $incoming = $master || ($user?->isIncoming() ?? false);
        $items = [];

        if ($master) {
            $items = array_merge($items, [
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
            ]);
        }

        if ($purchase || $incoming) {
            $items[] = [
                'type' => 'heading',
                'label' => __('Quality demo'),
            ];
            $items[] = [
                'label' => __('Analysis'),
                'route' => 'admin::quality.analysis',
                'icon' => 'chart-bar',
                'active' => request()->routeIs('admin::quality.analysis'),
            ];

            if ($purchase) {
                $items[] = [
                    'type' => 'group',
                    'heading' => __('Purchase'),
                    'icon' => 'clipboard-document-list',
                    'expandable' => true,
                    'expanded' => request()->routeIs('admin::quality.purchase.*'),
                    'children' => [
                        [
                            'label' => __('Purchase orders'),
                            'route' => 'admin::quality.purchase.orders',
                            'icon' => 'document-text',
                            'active' => request()->routeIs('admin::quality.purchase.orders*'),
                        ],
                        [
                            'label' => __('Purchase invoices'),
                            'route' => 'admin::quality.purchase.invoices',
                            'icon' => 'receipt-percent',
                            'active' => request()->routeIs('admin::quality.purchase.invoices*'),
                        ],
                    ],
                ];
            }

            if ($incoming) {
                $items[] = [
                    'type' => 'group',
                    'heading' => __('Incoming'),
                    'icon' => 'inbox-arrow-down',
                    'expandable' => true,
                    'expanded' => request()->routeIs('admin::quality.incoming.*'),
                    'children' => [
                        [
                            'label' => __('Approved vendors'),
                            'route' => 'admin::quality.incoming.vendors',
                            'icon' => 'building-storefront',
                            'active' => request()->routeIs('admin::quality.incoming.vendors'),
                        ],
                        [
                            'label' => __('Parts'),
                            'route' => 'admin::quality.incoming.parts',
                            'icon' => 'cube',
                            'active' => request()->routeIs('admin::quality.incoming.parts'),
                        ],
                        [
                            'label' => __('Categories'),
                            'route' => 'admin::quality.incoming.categories',
                            'icon' => 'tag',
                            'active' => request()->routeIs('admin::quality.incoming.categories'),
                        ],
                        [
                            'label' => __('Inspection standards'),
                            'route' => 'admin::quality.incoming.standards',
                            'icon' => 'clipboard-document-check',
                            'active' => request()->routeIs('admin::quality.incoming.standards*'),
                        ],
                        [
                            'label' => __('Inspection reports'),
                            'route' => 'admin::quality.incoming.reports',
                            'icon' => 'document-magnifying-glass',
                            'active' => request()->routeIs('admin::quality.incoming.reports*'),
                        ],
                        [
                            'label' => __('Sampling plans'),
                            'route' => 'admin::quality.incoming.sampling',
                            'icon' => 'queue-list',
                            'active' => request()->routeIs('admin::quality.incoming.sampling'),
                        ],
                    ],
                ];
            }

            $items[] = [
                'label' => __('Alerts'),
                'route' => 'admin::quality.alerts',
                'icon' => 'bell-alert',
                'active' => request()->routeIs('admin::quality.alerts'),
            ];
        }

        if ($master) {
            $items = array_merge($items, [
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
                        ],
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
            ]);
        }

        return $items;
    }

    public function render(): View
    {
        return view('core::components.layouts.app.navigation');
    }
}
