<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVvcCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vvc_codes', function (Blueprint $table) {
            $table->id();
            $table->string("vvc_code");
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('ibo_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('landlord_id');
            $table->string('code_for_tenant');
            $table->string('code_for_landlord');
            $table->boolean('tenant_verified')->default(0);
            $table->boolean('landlord_verified')->default(0);
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
        Schema::dropIfExists('vvc_codes');
    }
}
