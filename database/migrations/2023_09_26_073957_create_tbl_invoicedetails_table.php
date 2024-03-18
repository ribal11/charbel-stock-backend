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
        Schema::dropIfExists('tbl_invoicedetails');
        Schema::create('tbl_invoicedetails', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('ind_recid');
            $table->unsignedBigInteger('ind_hid');
            $table->unsignedBigInteger('ind_stkid');
            $table->decimal('ind_qty', 18, 2);
            $table->integer('ind_reserve');
            $table->dateTime('ind_dstmp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_invoicedetails');
    }
};
