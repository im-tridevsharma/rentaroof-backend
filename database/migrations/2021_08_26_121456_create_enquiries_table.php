<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnquiriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title')->default('');
            $table->text('description')->default('');
            $table->string('type')->nullable();
            $table->string('name');
            $table->string('user_id')->nullable();
            $table->string('property_id')->nullable();
            $table->string('email')->default('');
            $table->string('mobile')->default('');
            $table->string('system_ip')->default('');
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
        Schema::dropIfExists('enquiries');
    }
}
