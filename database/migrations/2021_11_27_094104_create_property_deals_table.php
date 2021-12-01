<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyDealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('property_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->default(0);
            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('offer_for')->default(0);
            $table->decimal('offer_price', 10, 2)->default(0.00);
            $table->timestamp('offer_expires_time')->nullable();
            $table->enum('status', ['accepted', 'rejected', 'pending'])->default('pending');
            $table->boolean('is_closed')->default(0);
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
        Schema::dropIfExists('property_deals');
    }
}
