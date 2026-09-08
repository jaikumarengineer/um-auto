<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class Navbar extends Component
{
    public $user;

    public function __construct()
    {
        $this->user = Auth::user();
    }

    public function render(): View
    {
        return view('core::components.layouts.app.navbar');
    }
}
