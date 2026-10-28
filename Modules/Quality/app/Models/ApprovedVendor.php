<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovedVendor extends Model
{
    protected $table = 'approved_vendors';

    protected $fillable = [
        'code',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'gstin',
        'status',
        'approved_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'approved_on' => 'date',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(IncomingPart::class, 'vendor_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'vendor_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function optionLabel(): string
    {
        return $this->name.' · '.$this->code;
    }
}
