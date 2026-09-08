<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class Toast extends Component
{
    public function render(): View
    {
        return view('core::components.layouts.app.toast');
    }
}
