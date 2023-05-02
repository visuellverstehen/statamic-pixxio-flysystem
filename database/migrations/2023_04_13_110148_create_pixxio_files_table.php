<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up()
    {
        Schema::create('pixxio_files', function (Blueprint $table) {
            $table->integer('pixxio_id')->unique();
            $table->string('relative_path')->primary();
            $table->string('absolute_path');
            $table->text('alternative_text')->nullable();
            $table->text('copyright')->nullable();
            $table->integer('filesize')->nullable();
            $table->string('visibility')->default('public'); // Can it be null?
            $table->timestamp('last_modified')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pixxio_files');
    }
};
