<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInspectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('ibo_id');
            $table->string('address')->nullable()->default('not-verified');
            $table->string('super_area')->nullable()->default('not-verified');
            $table->string('carpet_area')->nullable()->default('not-verified');
            $table->string('bedrooms')->nullable()->default('not-verified');
            $table->string('bathrooms')->nullable()->default('not-verified');
            $table->string('balconies')->nullable()->default('not-verified');
            $table->string('floors')->nullable()->default('not-verified');
            $table->string('renting_amount')->nullable()->default('not-verified');
            $table->string('images')->nullable()->default('not-verified');
            $table->string('amenities')->nullable()->default('not-verified');
            $table->string('preferences')->nullable()->default('not-verified');
            $table->string('essentials')->nullable()->default('not-verified');
            $table->string('inspection_note')->nullable();
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
        Schema::dropIfExists('inspections');
    }
}
