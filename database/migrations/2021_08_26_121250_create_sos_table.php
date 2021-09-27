<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('fired_by_id');
            $table->string('sos_content');
            $table->string('name')->default('');
            $table->string('email')->default('');
            $table->string('user_type');
            $table->boolean('notified_mobile')->default(0);
            $table->boolean('notified_email')->default(0);
            $table->boolean('notified_admin')->default(0);
            $table->enum('resolve_status', ['pending', 'reviewed', 'working', 'resolved'])->default('pending');
            $table->string('resolve_message')->default('');
            $table->json('status_history');
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
        Schema::dropIfExists('sos');
    }
}
