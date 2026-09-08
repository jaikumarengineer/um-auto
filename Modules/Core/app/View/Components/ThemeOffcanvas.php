<?php

namespace Modules\Core\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ThemeOffcanvas extends Component
{
    public function __construct(
        public string $name = 'admin-theme-panel',
        public string $position = 'right',
        public bool $withTrigger = true,
    ) {
    }

    public function render(): View
    {
        return view('core::components.theme-offcanvas');
    }
}
