<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEarningPayoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('earning_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('role')->nullable();
            $table->decimal('payout_amount')->default(0);
            $table->enum('payout_status', ['pending', 'accepted', 'paid'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->enum('transaction_status', ['pending', 'failed', 'paid'])->default('pending');
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
        Schema::dropIfExists('earning_payouts');
    }
}
