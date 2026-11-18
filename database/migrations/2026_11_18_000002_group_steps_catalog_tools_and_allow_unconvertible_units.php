<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->string('section')->nullable();
        });

        $this->turnStepTitlesIntoSections();

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->boolean('is_owned')->default(true);
        });

        Schema::table('product_conversions', function (Blueprint $table) {
            $table->decimal('amount_in_base', 12, 3)->nullable()->change();
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->json('extra_amounts')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('extra_amounts');
        });

        DB::table('product_conversions')->whereNull('amount_in_base')->delete();

        Schema::table('product_conversions', function (Blueprint $table) {
            $table->decimal('amount_in_base', 12, 3)->nullable(false)->change();
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('is_owned');
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->string('title')->nullable();
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }

    /**
     * A title used to mark the first step of a component. Give that name
     * to every step up to the next title, so the steps form a group.
     */
    private function turnStepTitlesIntoSections(): void
    {
        $section = null;
        $recipeId = null;

        foreach (DB::table('recipe_steps')->orderBy('recipe_id')->orderBy('position')->get() as $step) {
            if ($step->recipe_id !== $recipeId) {
                $recipeId = $step->recipe_id;
                $section = null;
            }

            $section = $step->title ?: $section;

            DB::table('recipe_steps')->where('id', $step->id)->update(['section' => $section]);
        }
    }
};
