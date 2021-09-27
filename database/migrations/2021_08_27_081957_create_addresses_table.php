<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('user_id');
            $table->string('landmark')->default('');
            $table->string('house_number')->default('');
            $table->enum('address_type', ['property', 'normal'])->default('normal');
            $table->string('full_address');
            $table->string('lat')->default('');
            $table->string('long')->default('');
            $table->unsignedBigInteger('country')->nullable();
            $table->foreign('country')->references('id')->on("countries");
            $table->unsignedBigInteger('state')->nullable();
            $table->foreign('state')->references('id')->on("states");
            $table->unsignedBigInteger('city')->nullable();
            $table->foreign('city')->references('id')->on("cities");
            $table->string('pincode');
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
        Schema::dropIfExists('addresses');
    }
}
