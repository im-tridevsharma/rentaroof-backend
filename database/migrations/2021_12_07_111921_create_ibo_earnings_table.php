<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIboEarningsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ibo_earnings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ibo_id')->default(0);
            $table->bigInteger('deal_id')->default(0);
            $table->bigInteger('property_id')->default(0);
            $table->bigInteger('agreement_id')->default(0);
            $table->bigInteger('amount_percentage')->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ibo_earnings');
    }
}
