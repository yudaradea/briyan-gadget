<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('line_order')->nullable()->after('product_id');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unsignedInteger('line_order')->nullable()->after('product_id');
        });

        $saleIds = DB::table('sale_items')
            ->select('sales_transaction_id')
            ->distinct()
            ->pluck('sales_transaction_id');

        foreach ($saleIds as $saleId) {
            $items = DB::table('sale_items')
                ->where('sales_transaction_id', $saleId)
                ->orderBy('created_at')
                ->orderBy('updated_at')
                ->orderBy('id')
                ->get(['id']);

            foreach ($items as $index => $item) {
                DB::table('sale_items')
                    ->where('id', $item->id)
                    ->update(['line_order' => $index + 1]);
            }
        }

        $purchaseIds = DB::table('purchase_items')
            ->select('purchase_id')
            ->distinct()
            ->pluck('purchase_id');

        foreach ($purchaseIds as $purchaseId) {
            $items = DB::table('purchase_items')
                ->where('purchase_id', $purchaseId)
                ->orderBy('created_at')
                ->orderBy('updated_at')
                ->orderBy('id')
                ->get(['id']);

            foreach ($items as $index => $item) {
                DB::table('purchase_items')
                    ->where('id', $item->id)
                    ->update(['line_order' => $index + 1]);
            }
        }

        DB::table('sale_items')
            ->whereNull('line_order')
            ->update(['line_order' => 1]);

        DB::table('purchase_items')
            ->whereNull('line_order')
            ->update(['line_order' => 1]);
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('line_order');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('line_order');
        });
    }
};
