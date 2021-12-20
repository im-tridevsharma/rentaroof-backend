<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIboEvaluationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ibo_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mcq_id');
            $table->unsignedBigInteger('ibo_id');
            $table->integer('answered_questions')->default(0);
            $table->integer('total_questions')->default(0);
            $table->integer('total_marks_obtained')->default(0);
            $table->integer('total_time_taken')->default(0);
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
        Schema::dropIfExists('ibo_evaluations');
    }
}
