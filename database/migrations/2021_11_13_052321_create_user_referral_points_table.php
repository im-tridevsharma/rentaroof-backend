<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserReferralPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_referral_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('role', ['tenant', 'ibo', 'landlord'])->default('tenant');
            $table->string('title');
            $table->decimal('point_value', 10, 2);
            $table->integer('points');
            $table->decimal('amount_earned', 10, 2);
            $table->enum("type", ["credit", "debit"]);
            $table->enum('for', ["order", "referral", "review", "payment"]);
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
        Schema::dropIfExists('user_referral_points');
    }
}
