<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $fillable = ['name', 'description', 'price', 'free_over', 'cost_on_delivery', 'zones', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'free_over' => 'integer',
            'cost_on_delivery' => 'boolean',
            'zones' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Cost for a subtotal (free-over threshold), optionally per province zone.
     *  Returns 0 when the method is «پس‌کرایه» — the customer pays the courier
     *  on receipt, so the website total doesn't include the shipping fee. */
    public function costFor(int $subtotal, ?string $province = null): int
    {
        if ($this->cost_on_delivery) {
            return 0;
        }
        if ($this->free_over !== null && $subtotal >= $this->free_over) {
            return 0;
        }
        if ($province && is_array($this->zones) && isset($this->zones[$province])) {
            return (int) $this->zones[$province];
        }

        return (int) $this->price;
    }

    public function formattedPrice(): string
    {
        if ($this->cost_on_delivery) {
            return 'پس‌کرایه';
        }

        return $this->price > 0 ? Money::toman($this->price) : 'رایگان';
    }
}
