<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class Sidebar extends Component
{
    public array $menu = [];
    public $user;

    public function __construct(
        public ?string $title = null
    ) {
        $this->user = auth()->user();
        $this->generateMenuLinks();
    }

    public function render(): View
    {
        return view('core::components.layouts.app.sidebar');
    }

    protected function generateMenuLinks(): void
    {
        $this->menu = [

            [
                'label' => 'Dashboard',
                'icon'  => 'home',
                'route' => route('admin::dashboard'),
                'active'=> request()->routeIs('admin::dashboard'),
            ],

            // [
            //     'label' => 'Users',
            //     'icon'  => 'users',
            //     'route' => route('admin::users.index'),
            //     'active'=> request()->routeIs('admin::users.*'),
            // ],

            [
                'label' => 'Settings',
                'icon'  => 'cog-6-tooth',
                'route' => route('admin::settings.general'),
                'active'=> request()->routeIs('admin::settings.*'),
            ],

        ];
    }
}
