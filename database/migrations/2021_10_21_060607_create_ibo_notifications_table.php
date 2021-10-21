<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIboNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ibo_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ibo_id');
            $table->string("type");
            $table->string("title");
            $table->string("content");
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string("user_role")->default('');
            $table->string("name")->default('');
            $table->string("mobile")->default('');
            $table->string("email")->default('');
            $table->string("redirect")->default('');
            $table->boolean('is_seen')->default(0);
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
        Schema::dropIfExists('ibo_notifications');
    }
}
