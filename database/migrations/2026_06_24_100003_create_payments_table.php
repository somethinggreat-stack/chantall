<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->nullable()->index(); // the customer this belongs to
            $table->string('transaction_id')->index();                        // Auth.net transId
            $table->string('invoice_number')->nullable()->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('type')->index();        // initial | recurring | refund | void | auth_only
            $table->string('status')->index();      // captured | refunded | voided | authorized | failed
            $table->string('event_type_raw')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_last4', 8)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->timestamp('charged_at')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'charged_at']);
            $table->unique(['transaction_id', 'type']); // idempotent against duplicate webhooks
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
