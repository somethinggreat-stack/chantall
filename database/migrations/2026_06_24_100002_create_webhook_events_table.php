<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('notification_id')->unique();          // Auth.net notificationId — dedupe key
            $table->string('event_type')->index();
            $table->string('entity_id')->nullable()->index();      // payload.id (txn ID or ARB sub ID)
            $table->unsignedBigInteger('matched_enrollment_id')->nullable()->index();
            $table->string('customer_first_name')->nullable();
            $table->string('customer_last_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('description')->nullable();             // pre-computed plain English
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('arb_status')->nullable();
            $table->string('response_code')->nullable();
            $table->boolean('signature_valid')->nullable()->index(); // true / false / null
            $table->string('source_ip', 45)->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
