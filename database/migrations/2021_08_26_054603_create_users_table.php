<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string("system_userid")->unique()->nullable();
            $table->string('first');
            $table->string('last');
            $table->string('username')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('mobile')->unique()->nullable();
            $table->enum('role', ['tenant', 'ibo', 'landlord', 'admin', 'superadmin']);
            $table->enum('past_role', ['tenant', 'ibo', 'landlord', 'admin', 'superadmin'])->nullable();
            $table->string('password');
            $table->timestamp('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->boolean('remember_me')->default(0);
            $table->boolean('is_logged_in')->default(0);
            $table->timestamp('last_logged_in')->nullable();
            $table->enum('ibo_duty_mode', ['offline', 'online'])->nullable();
            $table->string('operating_since')->default(0);
            $table->enum("experience", ["beginner", "intermediate", "advanced"])->nullable();
            $table->string('profile_pic')->default('');
            $table->string('system_ip')->default('');
            $table->unsignedBigInteger('address_id')->nullable();
            $table->foreign('address_id')->references('id')->on('addresses')->onDelete('cascade');
            $table->unsignedBigInteger('kyc_id')->nullable();
            $table->foreign('kyc_id')->references('id')->on('kyc_verifications')->onDelete('cascade');
            $table->string("account_status")->default('activated');
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
        Schema::dropIfExists('users');
    }
}
