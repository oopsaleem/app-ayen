<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('company_id');
        });

        $this->backfillSlugs();

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    /**
     * Assign a unique slug to every restaurant created before this column
     * existed, so the not-null constraint above never fails on real data.
     */
    private function backfillSlugs(): void
    {
        $usedSlugs = [];

        DB::table('restaurants')->orderBy('id')->get(['id', 'name_en'])
            ->each(function (object $restaurant) use (&$usedSlugs) {
                $base = Str::slug($restaurant->name_en) ?: 'restaurant';
                $slug = $base;
                $suffix = 1;

                while (in_array($slug, $usedSlugs, true)) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                $usedSlugs[] = $slug;

                DB::table('restaurants')->where('id', $restaurant->id)->update(['slug' => $slug]);
            });
    }
};
