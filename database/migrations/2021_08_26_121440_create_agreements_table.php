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
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('ibo_id')->nullable();
            $table->unsignedBigInteger('landlord_id');
            $table->string('agreement_type')->default('');
            $table->string('fee_percentage')->default('');
            $table->decimal('fee_amount', 10, 2);
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'half-yearly', 'yearly']);
            $table->integer('number_of_invoices');
            $table->decimal('payment_amount', 10, 2);
            $table->string("agreement_url")->default('');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('next_due')->nullable();
            $table->timestamp('end_date')->nullable();
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
