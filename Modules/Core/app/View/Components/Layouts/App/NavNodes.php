<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class NavNodes extends Component
{
    public function __construct(
        public array $nodes = [],
        public int $depth = 0,
    ) {
    }

    public function render(): View
    {
        return view('core::components.layouts.app.nav-nodes');
    }
}
