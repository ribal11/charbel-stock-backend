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
        Schema::dropIfExists('tbl_invoiceheader');
        Schema::create('tbl_invoiceheader', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('inh_recid');
            $table->string('inh_client', 500);
            $table->string('inh_type', 1);
            $table->dateTime('inh_date');
            $table->string('inh_remarks', 1000)->nullable();
            $table->dateTime('inh_dstmp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_invoiceheader');
    }
};
