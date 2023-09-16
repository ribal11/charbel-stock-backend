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
            $table->string('stk_category', 100);
            $table->string('stk_description', 500);
            $table->integer('stk_qty');
            $table->string('stk_supplier', 200);
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
