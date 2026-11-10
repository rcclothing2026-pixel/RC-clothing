<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_variants.stoqs_size_price: the size price StoqS last set on this size
 * (CatalogImporter::applyVariantPrices). While the size price still equals it, StoqS
 * owns it and may change or clear it; any other size price was set in the shop and
 * an import leaves it alone. Existing rows stay null, i.e. shop-set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $t) {
            $t->unsignedBigInteger('stoqs_size_price')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $t) {
            $t->dropColumn('stoqs_size_price');
        });
    }
};
