<?php

namespace Modules\Core\View\Components\Layouts\App;

use Illuminate\View\Component;
use Illuminate\View\View;

class MobileHeader extends Component
{
    /**
     * The authenticated user.
     */
    public $user;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->user = auth()->user();
    }

    /**
     * Get user initials.
     */
    public function getUserInitials(): string
    {
        return $this->user?->initials() ?? '';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('core::components.layouts.app.mobile-header');
    }
}
