<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('funding_goal')->nullable();
            $table->string('confirmation')->nullable();
            $table->string('credit_cards')->nullable();
            $table->string('credit_utilization')->nullable();
            $table->string('credit_score')->nullable();
            $table->string('business_situation')->nullable();
            $table->string('annual_income')->nullable();
            $table->text('credit_profile')->nullable();
            $table->json('answers')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_leads');
    }
};
