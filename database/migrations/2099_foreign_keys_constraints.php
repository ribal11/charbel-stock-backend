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
        //
        Schema::table('tbl_invoicedetails', function (Blueprint $table) {
            $table->foreign('ind_hid')->references('inh_recid')->on('tbl_invoiceheader');
            $table->foreign('ind_stkid')->references('stk_recid')->on('tbl_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
