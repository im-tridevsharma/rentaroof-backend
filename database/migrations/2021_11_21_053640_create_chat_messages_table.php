<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'pdf', 'docx', 'xls']);
            $table->string('message')->nullable();
            $table->string('meta_title')->default('');
            $table->string('meta_description')->default('');
            $table->string('meta_image')->default('');
            $table->boolean('is_deleted')->default(0);
            $table->boolean('is_seen')->default(0);
            $table->date('date');
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
        Schema::dropIfExists('chat_messages');
    }
}
