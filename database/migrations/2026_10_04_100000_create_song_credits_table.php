<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('song_credits', static function (Blueprint $table): void {
            $table->id();
            $table->string('song_id', 36)->index();
            $table->string('role', 64);
            $table->string('name');
            $table->string('instrument')->nullable();
            $table->string('artist_mbid', 36)->nullable();

            $table->index(['role', 'name']);
            $table->foreign('song_id')->references('id')->on('songs')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }
};
