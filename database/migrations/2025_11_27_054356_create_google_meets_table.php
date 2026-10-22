<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('google_meets', function (Blueprint $table) {
            $table->id();
            $table->string('meet_name');
            $table->integer('created_by'); // Foreign key to users.id
            $table->json('meet_members'); // Store as JSON array of user IDs
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->text('meet_link')->nullable();
            $table->string('status')->default('active'); // 'active' or 'finished'
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('google_meets');
    }
};