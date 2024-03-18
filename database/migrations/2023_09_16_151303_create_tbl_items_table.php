<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('tbl_items');
        Schema::create('tbl_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('stk_recid');
            $table->string('stk_serno', 100);
            $table->string('stk_description', 500);
            $table->integer('stk_qty');
            $table->integer('stk_ordered')->default(0);
            $table->integer('stock_reservation')->default(0);
            $table->integer('three_month_sale')->default(0);
            $table->integer('six_month_sale')->default(0);
            $table->integer('one_year_sale')->default(0);
            $table->string('minimum_stock_three_month')->default('to ne filled');
            $table->string('minimum_stock_six_month')->default('to be filled');
            $table->string('minimum_stock_year')->default('to be filled');
            $table->string('purchase_Status')->default('allow');
            $table->string('moq')->nullable()->default(null);
            $table->string('allow_edit')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_items');
    }
};
