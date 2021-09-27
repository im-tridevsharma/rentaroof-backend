<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKycVerificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->enum('document_type', ['aadhar', 'pan', 'idcard', 'driving_license', 'other']);
            $table->string('document_number');
            $table->string('other_document_name')->default('');
            $table->string('other_document_number')->default('');
            $table->string('document_upload');
            $table->boolean('is_verified')->default(0);
            $table->string('verification_issues')->default('');
            $table->timestamp('verified_at')->nullable();
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
        Schema::dropIfExists('kyc_verifications');
    }
}
