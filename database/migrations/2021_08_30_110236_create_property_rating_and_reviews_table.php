<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyRatingAndReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_rating_and_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('full_name');
            $table->string('email')->default('');
            $table->string('mobile')->default('');
            $table->integer('rating')->default(0);
            $table->integer('neighbourhood_rating')->default(0);
            $table->integer('roads_rating')->default(0);
            $table->integer('safety_rating')->default(0);
            $table->integer('cleanliness_rating')->default(0);
            $table->integer('public_transport_rating')->default(0);
            $table->integer('parking_rating')->default(0);
            $table->integer('connectivity_rating')->default(0);
            $table->integer('traffic_rating')->default(0);
            $table->integer('schools_rating')->default(0);
            $table->integer('hospitals_rating')->default(0);
            $table->integer('restaurents_rating')->default(0);
            $table->integer('markets_rating')->default(0);
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
        Schema::dropIfExists('property_rating_and_reviews');
    }
}
