<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgreementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description')->default('');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->foreign('property_id')->references('id')->on('properties');
            $table->unsignedBigInteger('tenant')->nullable();
            $table->foreign('tenant')->references('id')->on('users');
            $table->unsignedBigInteger('ibo')->nullable();
            $table->foreign('ibo')->references('id')->on('users');
            $table->string('agreement_type')->default('');
            $table->string('fee_percentage')->default('');
            $table->decimal('fee_amount', 10,2);
            $table->enum('payment_frequency', ['monthly','quarterly','half-yearly','yearly']);
            $table->integer('number_of_invoices');
            $table->decimal('payment_amount',10,2);
            $table->json('urls');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions');
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
        Schema::dropIfExists('agreements');
    }
}
