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
        $admin = $user?->isUnrestrictedAdmin() ?? false;
        $items = [];

        $canDashboard = $admin || ($user?->canModule('dashboard') ?? false);
        $canPurchaseOrders = $admin || ($user?->canModule('quality.purchase.orders') ?? false);
        $canPurchaseInvoices = $admin || ($user?->canModule('quality.purchase.invoices') ?? false);
        $canPurchase = $canPurchaseOrders || $canPurchaseInvoices;
        $canParts = $admin || ($user?->canModule('quality.incoming.parts') ?? false);
        $canStandards = $admin || ($user?->canModule('quality.incoming.standards') ?? false);
        $canSampling = $admin || ($user?->canModule('quality.incoming.sampling') ?? false);
        $canReports = $admin || ($user?->canModule('quality.incoming.reports') ?? false);
        $canIncomingOps = $canParts || $canStandards || $canSampling || $canReports;
        $canVendors = $admin || ($user?->canModule('quality.incoming.vendors') ?? false);
        $canCategories = $admin || ($user?->canModule('quality.incoming.categories') ?? false);
        $canAnalysis = $admin || ($user?->canModule('quality.analysis') ?? false);
        $canAlerts = $admin || ($user?->canModule('quality.alerts') ?? false);
        $canUsers = $admin || ($user?->canModule('users') ?? false);
        $canGrouping = $admin || ($user?->canModule('grouping') ?? false);

        if ($canDashboard) {
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

        if ($canAnalysis || $canAlerts || $canPurchase || $canIncomingOps || $canVendors || $canCategories) {
            $items[] = [
                'type' => 'heading',
                'label' => __('SPQol MES'),
            ];

            if ($canAnalysis) {
                $items[] = [
                    'label' => __('Analysis'),
                    'route' => 'admin::quality.analysis',
                    'icon' => 'chart-bar',
                    'active' => request()->routeIs('admin::quality.analysis'),
                ];
            }

            if ($canPurchase) {
                $purchaseChildren = [];

                if ($canPurchaseOrders) {
                    $purchaseChildren[] = [
                        'label' => __('Purchase orders'),
                        'route' => 'admin::quality.purchase.orders',
                        'icon' => 'document-text',
                        'active' => request()->routeIs('admin::quality.purchase.orders*'),
                    ];
                }

                if ($canPurchaseInvoices) {
                    $purchaseChildren[] = [
                        'label' => __('Purchase invoices'),
                        'route' => 'admin::quality.purchase.invoices',
                        'icon' => 'receipt-percent',
                        'active' => request()->routeIs('admin::quality.purchase.invoices*'),
                    ];
                }

                $items[] = [
                    'type' => 'group',
                    'heading' => __('Purchase'),
                    'icon' => 'clipboard-document-list',
                    'expandable' => true,
                    'expanded' => request()->routeIs('admin::quality.purchase.*'),
                    'children' => $purchaseChildren,
                ];
            }

            if ($canIncomingOps || $canVendors || $canCategories) {
                $incomingChildren = [];

                if ($canVendors) {
                    $incomingChildren[] = [
                        'label' => __('Approved Vendors'),
                        'route' => 'admin::quality.incoming.vendors',
                        'icon' => 'building-storefront',
                        'active' => request()->routeIs('admin::quality.incoming.vendors'),
                    ];
                }

                if ($canCategories) {
                    $incomingChildren[] = [
                        'label' => __('List of Category'),
                        'route' => 'admin::quality.incoming.categories',
                        'icon' => 'tag',
                        'active' => request()->routeIs('admin::quality.incoming.categories'),
                    ];
                }

                if ($canParts) {
                    $incomingChildren[] = [
                        'label' => __('Incoming Quality parts'),
                        'route' => 'admin::quality.incoming.parts',
                        'icon' => 'cube',
                        'active' => request()->routeIs('admin::quality.incoming.parts*'),
                    ];
                }

                if ($canSampling) {
                    $incomingChildren[] = [
                        'label' => __('Sampling Plan Standards'),
                        'route' => 'admin::quality.incoming.sampling',
                        'icon' => 'queue-list',
                        'active' => request()->routeIs('admin::quality.incoming.sampling'),
                    ];
                }

                if ($canReports) {
                    $incomingChildren[] = [
                        'label' => __('Incoming Quality Reports'),
                        'route' => 'admin::quality.incoming.reports',
                        'icon' => 'document-magnifying-glass',
                        'active' => request()->routeIs('admin::quality.incoming.reports*'),
                    ];
                }

                $items[] = [
                    'type' => 'group',
                    'heading' => __('Incoming'),
                    'icon' => 'inbox-arrow-down',
                    'expandable' => true,
                    'expanded' => request()->routeIs('admin::quality.incoming.*'),
                    'children' => $incomingChildren,
                ];
            }

            if ($canAlerts) {
                $items[] = [
                    'label' => __('Alerts'),
                    'route' => 'admin::quality.alerts',
                    'icon' => 'bell-alert',
                    'active' => request()->routeIs('admin::quality.alerts'),
                ];
            }
        }

        if ($canUsers || $canGrouping || $admin) {
            $items[] = [
                'type' => 'heading',
                'label' => __('Management'),
            ];

            if ($canGrouping) {
                // $items[] = [
                //     'type' => 'group',
                //     'heading' => __('Grouping'),
                //     'icon' => 'globe-alt',
                //     'expandable' => true,
                //     'expanded' => request()->routeIs('admin::grouping.list'),
                //     'children' => [
                //         [
                //             'label' => __('Country List'),
                //             'route' => 'admin::grouping.list',
                //             'icon' => 'globe-alt',
                //             'active' => request()->routeIs('admin::grouping.list'),
                //         ],
                //     ],
                // ];
            }

            if ($canUsers) {
                $items[] = [
                    'label' => __('Users'),
                    'route' => 'admin::users.list',
                    'icon' => 'users',
                    'active' => request()->routeIs('admin::users.*'),
                ];
            }
        }

        if ($admin) {
            $items = array_merge($items, [
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
                        // [
                        //     'label' => __('Website'),
                        //     'route' => 'admin::settings.website',
                        //     'icon' => 'globe-alt',
                        //     'active' => request()->routeIs('admin::settings.website'),
                        // ],
                        // [
                        //     'label' => __('Mail'),
                        //     'route' => 'admin::settings.mail',
                        //     'icon' => 'envelope',
                        //     'active' => request()->routeIs('admin::settings.mail'),
                        // ],
                        [
                            'label' => __('Email alerts'),
                            'route' => 'admin::settings.email-alerts',
                            'icon' => 'bell-alert',
                            'active' => request()->routeIs('admin::settings.email-alerts'),
                        ],
                        [
                            'label' => __('Server Logs'),
                            'route' => 'admin::settings.logs',
                            'icon' => 'document-magnifying-glass',
                            'active' => request()->routeIs('admin::settings.logs'),
                        ],
                        // [
                        //     'label' => __('Server Info'),
                        //     'route' => 'admin::settings.server-info',
                        //     'icon' => 'server-stack',
                        //     'active' => request()->routeIs('admin::settings.server-info'),
                        // ],
                        // [
                        //     'type' => 'group',
                        //     'heading' => __('Scripts'),
                        //     'icon' => 'code-bracket',
                        //     'expandable' => true,
                        //     'expanded' => request()->routeIs('admin::settings.scripts.*'),
                        //     'children' => [
                        //         [
                        //             'label' => __('Third-party'),
                        //             'route' => 'admin::settings.scripts.third-party',
                        //             'icon' => 'puzzle-piece',
                        //             'active' => request()->routeIs('admin::settings.scripts.third-party'),
                        //         ],
                        //         [
                        //             'label' => __('Custom'),
                        //             'route' => 'admin::settings.scripts.custom',
                        //             'icon' => 'command-line',
                        //             'active' => request()->routeIs('admin::settings.scripts.custom'),
                        //         ],
                        //     ],
                        // ],
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
