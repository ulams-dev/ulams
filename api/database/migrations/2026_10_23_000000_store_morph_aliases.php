<?php

use App\Support\LegacyMorphClassNames;
use Illuminate\Database\Migrations\Migration;

/**
 * Polymorphic columns (`topicable_type`, `buyable_type`, `model_type`, ...) stored PHP class names, so renaming or
 * moving a class orphaned its rows. They now store the stable aliases of Ulams\Core\Support\MorphMap. This rewrites
 * the existing class names to aliases; `down` restores the class names. Both directions are idempotent and only touch
 * values that are exactly a known class name (or alias). See App\Support\LegacyMorphClassNames for the list.
 */
class StoreMorphAliases extends Migration
{
    public function up(): void
    {
        LegacyMorphClassNames::rewrite(true);
    }

    public function down(): void
    {
        LegacyMorphClassNames::rewrite(false);
    }
}
