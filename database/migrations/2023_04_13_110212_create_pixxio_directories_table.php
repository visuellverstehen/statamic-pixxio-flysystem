<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up()
    {
        Schema::create('pixxio_directories', function (Blueprint $table) {
            $table->string('relative_path')->primary();
            $table->timestamps(); // do we need timestamps?
        });
    }

    public function down()
    {
        Schema::dropIfExists('pixxio_directories');
    }
};
