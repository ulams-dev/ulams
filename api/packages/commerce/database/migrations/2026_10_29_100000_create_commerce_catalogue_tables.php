<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A set of courses sold as one product (ADR 0130, Q6): bought once, expanded to one entitlement per course.
        Schema::create('commerce_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('commerce_bundle_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('commerce_bundles')->cascadeOnDelete();
            $table->unsignedBigInteger('course_id');
            $table->unique(['bundle_id', 'course_id']);
            $table->index('course_id');
        });
        // A course or a bundle sold for a number of days: the entitlement ends `days` after the purchase.
        Schema::create('commerce_access_passes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedSmallInteger('days');
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        // The LMS side of a price: what the tenant wants to sell a sellable for and whether it is for sale. The provider
        // is only told about it (catalogue sync), so switching providers never loses a price.
        Schema::create('commerce_sellable_prices', function (Blueprint $table) {
            $table->id();
            $table->string('sellable_type', 32);
            $table->unsignedBigInteger('sellable_id');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->boolean('for_sale')->default(true);
            $table->timestamps();
            $table->unique(['sellable_type', 'sellable_id']);
        });
        // Prices set before this table existed lived on the link the builder wrote: keep them (the newest link wins).
        foreach (DB::table('commerce_product_links')->orderBy('id')->get() as $link) {
            DB::table('commerce_sellable_prices')->updateOrInsert(
                ['sellable_type' => $link->sellable_type, 'sellable_id' => $link->sellable_id],
                ['amount_minor' => $link->amount_minor, 'currency' => $link->currency, 'for_sale' => (bool) $link->active, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        // One row per nightly check (catalogue drift) or sweep (orders): how much was out of step and what was repaired.
        Schema::create('commerce_drift_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('provider', 32);
            $table->unsignedInteger('drift')->default(0);
            $table->unsignedInteger('repaired')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kind', 'provider', 'created_at']);
        });

        // What the provider holds for each sellable and how in step it is (catalogue sync, ADR 0130).
        Schema::table('commerce_product_links', function (Blueprint $table) {
            $table->string('variant_code', 191)->nullable()->after('external_id');
            $table->string('channel_code', 64)->nullable()->after('variant_code');
            $table->timestamp('synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->index('variant_code');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_product_links', function (Blueprint $table) {
            $table->dropIndex(['variant_code']);
            $table->dropColumn(['variant_code', 'channel_code', 'synced_at', 'sync_error', 'payload_hash']);
        });
        Schema::dropIfExists('commerce_drift_reports');
        Schema::dropIfExists('commerce_sellable_prices');
        Schema::dropIfExists('commerce_access_passes');
        Schema::dropIfExists('commerce_bundle_courses');
        Schema::dropIfExists('commerce_bundles');
    }
};
