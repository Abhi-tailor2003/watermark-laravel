<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->string('uploaded_by_user_id', 100);
            $table->json('watermark_ids');
            $table->timestamps();
            $table->index('original_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};