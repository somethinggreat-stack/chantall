<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->nullable()->index();
            $table->string('arb_subscription_id')->nullable()->index();
            $table->string('event_type')->index(); // payment_failed | payment_recovered | terminated | manual_note ...
            $table->json('payload')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
