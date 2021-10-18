<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeetingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('description')->default('');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('user_role', ['tenant', 'ibo', 'landlord'])->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('name');
            $table->string('contact');
            $table->string('email');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time_expected')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->boolean('cancelled')->default(0);
            $table->string('cancel_reason')->default('');
            $table->boolean('rescheduled')->default(0);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->default('');
            $table->enum('created_by_role', ['tenant', 'ibo', 'landlord', 'guest'])->default('guest');
            $table->enum('meeting_status', ['pending', 'cancelled', 'approved'])->default('pending');
            $table->json('meeting_history');
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
        Schema::dropIfExists('meetings');
    }
}
