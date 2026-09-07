<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('provider');
            $table->string('status', 32);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->text('raw_text_encrypted')->nullable();
            $table->timestamp('raw_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_scans');
    }
};
