<?php

namespace App\Observers;

use App\Models\User;
use App\Services\StockKeeping\StockKeepingReporter;
use Throwable;

/**
 * Keeps the stock-keeping CRM in sync with website accounts: a customer is
 * pushed on signup and whenever their identity fields change.
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->sync($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged(['name', 'phone', 'email'])) {
            $this->sync($user);
        }
    }

    private function sync(User $user): void
    {
        try {
            app(StockKeepingReporter::class)->syncCustomer($user);
        } catch (Throwable $e) {
            report($e); // CRM sync must never break signup/profile edits
        }
    }
}
