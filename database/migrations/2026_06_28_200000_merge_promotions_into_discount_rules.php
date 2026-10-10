<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Port every Promotion row into a DiscountRule (so live discounts keep
 * applying after the merge), then drop the promotions table entirely.
 * The PromotionEngine is being deleted in the same commit — DiscountRule's
 * engine handles every case Promotion did, with more conditions on top.
 *
 * Mapping:
 *   cart_percent      → action: cart_discount_percent
 *   category_percent  → action: item_discount_percent + condition: item_categories
 *   buy_x_get_y       → no direct equivalent in the rule engine; ported as
 *                       INACTIVE with a description note so the admin can
 *                       recreate it as a real rule manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotions')) {
            foreach (DB::table('promotions')->get() as $promo) {
                [$conditions, $actions, $description] = $this->convert($promo);

                DB::table('discount_rules')->insert([
                    'name'        => $promo->name,
                    'description' => $description,
                    'priority'    => 0,
                    'conditions'  => json_encode($conditions),
                    'actions'     => json_encode($actions),
                    'apply_mode'  => 'all',
                    'stack_mode'  => 'best',
                    'starts_at'   => $promo->starts_at,
                    'expires_at'  => $promo->expires_at,
                    'used_count'  => 0,
                    // buy_x_get_y can't be expressed natively — keep inactive
                    // so it doesn't apply mysteriously after the merge.
                    'is_active'   => $promo->type === 'buy_x_get_y' ? false : (bool) $promo->is_active,
                    'created_at'  => $promo->created_at,
                    'updated_at'  => $promo->updated_at,
                ]);
            }

            Schema::drop('promotions');
        }
    }

    public function down(): void
    {
        // One-way merge — promotions are gone; the discount_rules rows
        // representing them stay. Re-creating the table without their
        // original rows would be misleading.
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->unsignedInteger('value')->default(0);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('buy_qty')->nullable();
            $table->unsignedInteger('get_qty')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /** @return array{0: array, 1: array, 2: ?string} */
    private function convert(object $promo): array
    {
        $value = (int) $promo->value;

        return match ($promo->type) {
            'cart_percent' => [
                [],
                [['type' => 'cart_discount_percent', 'params' => ['value' => $value]]],
                'پورت‌شده از پروموشن «درصد روی کل سبد».',
            ],
            'category_percent' => [
                [['type' => 'item_categories', 'params' => ['category_ids' => [(int) $promo->category_id]]]],
                [['type' => 'item_discount_percent', 'params' => ['value' => $value]]],
                'پورت‌شده از پروموشن «درصد روی یک دسته».',
            ],
            'buy_x_get_y' => [
                [],
                [],
                'پورت‌شده از پروموشن «بخر X بگیر Y» — قابل اجرا در موتور قوانین نیست؛ غیرفعال شد. در صورت نیاز، یک قانون جدید بسازید.',
            ],
            default => [[], [], 'پورت‌شده از پروموشن نامشخص.'],
        };
    }
};
