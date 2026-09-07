<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('cnic', 15)->unique();
            $table->string('name');
            $table->string('father_husband_name')->nullable();
            $table->text('address')->nullable();
            $table->string('front_image_path', 500)->nullable();
            $table->string('back_image_path', 500)->nullable();
            $table->string('verification_status', 32)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
