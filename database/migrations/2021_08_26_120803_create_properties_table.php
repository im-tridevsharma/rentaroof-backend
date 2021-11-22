<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('property_code');
            $table->string('name');
            $table->string('short_description')->default('');
            $table->text('description')->default('');
            $table->enum('for', ['rent', 'sale', 'hostel'])->nullable();
            $table->string('type');
            $table->unsignedBigInteger('address_id');
            $table->foreign('address_id')->references('id')->on('addresses')->onDelete('cascade');
            $table->enum('posting_as', ['full_house', 'sharing_basis'])->default('full_house');
            $table->integer('bedrooms')->default(0);
            $table->integer('balconies')->default(0);
            $table->integer('floors')->default(0);
            $table->enum('furnished_status', ['furnished', 'unfurnished', 'semi-furnished', 'ongoing'])->nullable();
            $table->enum('ownership_type', ['sole', 'joint', 'ownership'])->nullable();
            $table->integer('bathrooms')->default(0);
            $table->bigInteger('carpet_area')->nullable();
            $table->string('carpet_area_unit')->default('sqft');
            $table->bigInteger('super_area')->nullable();
            $table->string('super_area_unit')->default('sqft');
            $table->timestamp('available_from')->nullable();
            $table->boolean('available_immediately')->default(0);
            $table->string('age_of_construction')->default('');
            $table->decimal('monthly_rent', 10, 2)->default(0);
            $table->decimal('security_amount', 10, 2)->default(0);
            $table->decimal('maintenence_charge', 10, 2)->default(0);
            $table->enum('maintenence_duration', ['monthly', 'quarterly', 'yearly', 'onetime', 'per-sqft-monthly'])->default('monthly');
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->decimal('offered_price', 10, 2)->nullable();
            $table->unsignedBigInteger('ibo')->nullable();
            $table->unsignedBigInteger('landlord')->nullable();
            $table->unsignedBigInteger('posted_by');
            $table->json('amenities')->nullable();
            $table->unsignedBigInteger('gallery_id')->nullable();
            $table->unsignedBigInteger('property_essential_id')->nullable();
            $table->string('front_image');
            $table->boolean('is_approved')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->string('country_name')->default('');
            $table->string('state_name')->default('');
            $table->string('city_name')->default('');
            $table->string('pincode')->default('');
            $table->boolean('is_deleted')->default(0);
            $table->string('delete_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('properties');
    }
}
