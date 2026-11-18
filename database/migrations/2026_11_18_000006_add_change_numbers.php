<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose records clients keep a copy of.
     */
    private const array ENTITIES = [
        'recipes', 'products', 'tools', 'tags', 'units', 'store_sections',
        'meal_plan_entries', 'pantry_items', 'shopping_lists',
    ];

    /**
     * Tables whose rows are part of a record of another table, with the
     * table and the column that name it.
     */
    private const array PARTS = [
        'recipe_ingredients' => ['recipes', 'recipe_id'],
        'recipe_steps' => ['recipes', 'recipe_id'],
        'recipe_tag' => ['recipes', 'recipe_id'],
        'recipe_tool' => ['recipes', 'recipe_id'],
        'recipe_marks' => ['recipes', 'recipe_id'],
        'product_aliases' => ['products', 'product_id'],
        'product_conversions' => ['products', 'product_id'],
        'product_packages' => ['products', 'product_id'],
        'shopping_list_items' => ['shopping_lists', 'shopping_list_id'],
    ];

    /**
     * Run the migrations.
     *
     * Every change to a record gives it the next number of one sequence,
     * so a client can ask for everything after the last number it saw.
     * The lock makes transactions commit in the order of their numbers;
     * without it a client could read past a number that appears later.
     * Sequence and functions outlive dropped tables, so they are created
     * in a way that survives a rebuilt schema.
     */
    public function up(): void
    {
        Schema::create('sync_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('sync_version')->index();
        });

        DB::unprepared(<<<'SQL'
            CREATE SEQUENCE IF NOT EXISTS sync_versions;

            CREATE OR REPLACE FUNCTION sync_stamp() RETURNS trigger AS $$
            BEGIN
                PERFORM pg_advisory_xact_lock(7316001);
                NEW.sync_version := nextval('sync_versions');
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION sync_forget() RETURNS trigger AS $$
            BEGIN
                PERFORM pg_advisory_xact_lock(7316001);
                INSERT INTO sync_deletions (entity, entity_id, sync_version)
                VALUES (TG_TABLE_NAME, OLD.id, nextval('sync_versions'));
                RETURN OLD;
            END
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION sync_touch_owner() RETURNS trigger AS $$
            DECLARE
                part jsonb;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    part := to_jsonb(OLD);
                ELSE
                    part := to_jsonb(NEW);
                END IF;

                EXECUTE format('UPDATE %I SET sync_version = 0 WHERE id = $1', TG_ARGV[0])
                USING (part ->> TG_ARGV[1])::bigint;

                RETURN NULL;
            END
            $$ LANGUAGE plpgsql;
            SQL);

        foreach (self::ENTITIES as $entity) {
            Schema::table($entity, function (Blueprint $table) {
                $table->unsignedBigInteger('sync_version')->default(0)->index();
            });

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$entity}_sync_stamp BEFORE INSERT OR UPDATE ON {$entity}
                FOR EACH ROW EXECUTE FUNCTION sync_stamp();
                CREATE TRIGGER {$entity}_sync_forget AFTER DELETE ON {$entity}
                FOR EACH ROW EXECUTE FUNCTION sync_forget();
                UPDATE {$entity} SET sync_version = 0;
                SQL);
        }

        foreach (self::PARTS as $part => [$owner, $column]) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$part}_sync_touch AFTER INSERT OR UPDATE OR DELETE ON {$part}
                FOR EACH ROW EXECUTE FUNCTION sync_touch_owner('{$owner}', '{$column}');
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys(self::PARTS) as $part) {
            DB::unprepared("DROP TRIGGER {$part}_sync_touch ON {$part}");
        }

        foreach (self::ENTITIES as $entity) {
            DB::unprepared("DROP TRIGGER {$entity}_sync_stamp ON {$entity}; DROP TRIGGER {$entity}_sync_forget ON {$entity};");

            Schema::table($entity, function (Blueprint $table) {
                $table->dropColumn('sync_version');
            });
        }

        DB::unprepared('DROP FUNCTION sync_touch_owner; DROP FUNCTION sync_forget; DROP FUNCTION sync_stamp; DROP SEQUENCE sync_versions;');
        Schema::dropIfExists('sync_deletions');
    }
};
