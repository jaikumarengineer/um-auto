<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class Sidebar extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $title = null
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('core::components.layouts.app.sidebar');
    }
}
