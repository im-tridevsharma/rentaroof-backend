<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('amount', 10,2);
            $table->decimal('paid', 10, 2);
            $table->decimal('pending', 10,2);
            $table->string('currency')->default('INR');
            $table->string('type')->default('');
            $table->string('order_number')->default('');
            $table->string('txn_number');
            $table->integer('user_id');
            $table->string('user_name');
            $table->string('method')->default('');
            $table->string('status')->default('pending');
            $table->string('gateway_used');
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
        Schema::dropIfExists('transactions');
    }
}
