<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Outbox record for a message destined for the Chiaco Stock-Keeping SaaS.
 */
class IntegrationEvent extends Model
{
    public const TYPE_SALE_CREATED = 'sale.created';
    public const TYPE_INCOME_RECORDED = 'income.recorded';
    public const TYPE_CUSTOMER_UPSERTED = 'customer.upserted';

    protected $fillable = [
        'type', 'payload', 'status', 'attempts', 'last_error',
        'remote_id', 'available_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
