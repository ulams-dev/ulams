<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LTI 1.3 (ADR 0012), tenant database. Platform side: we launch registered tools (`lti_tools`).
 * Tool side: registered platforms launch us (`lti_platforms`).
 */
return new class () extends Migration {
    public function up(): void
    {
        // Our signing keys (one set per tenant), rotated by ulams:lti:rotate-keys.
        Schema::create('lti_keys', function (Blueprint $table) {
            $table->id();
            $table->string('kid')->unique();
            $table->text('private_key'); // encrypted with APP_KEY
            $table->json('public_jwk');
            $table->string('status', 16)->index(); // next | active | retired
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        // Platform side: external tools we launch.
        Schema::create('lti_tools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_id')->unique();
            $table->string('deployment_id');
            $table->string('oidc_login_url');
            $table->string('launch_url');
            $table->string('deep_linking_url')->nullable();
            $table->json('redirect_uris')->nullable();
            $table->string('jwks_url')->nullable();
            $table->text('public_key')->nullable();
            $table->json('custom')->nullable();
            $table->boolean('share_name')->default(false);
            $table->boolean('share_email')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Topic type LtiLink (a resource link to a tool).
        Schema::create('topic_lti_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lti_tool_id')->constrained('lti_tools')->restrictOnDelete();
            $table->string('url')->nullable(); // target_link_uri, default: the tool's launch URL
            $table->json('custom')->nullable();
            $table->string('presentation', 16)->default('iframe'); // iframe | window
            $table->decimal('score_maximum', 10, 2)->default(100);
            $table->timestamps();
        });

        // AGS line items and scores (platform side). Scores are append-only: a new score never
        // overwrites an earlier one.
        Schema::create('lti_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lti_tool_id')->constrained('lti_tools')->cascadeOnDelete();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('topic_id')->nullable()->index();
            $table->string('label');
            $table->decimal('score_maximum', 10, 2);
            $table->string('resource_id')->nullable();
            $table->string('tag')->nullable();
            $table->timestamp('start_date_time')->nullable();
            $table->timestamp('end_date_time')->nullable();
            $table->timestamps();
        });

        Schema::create('lti_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lti_line_item_id')->constrained('lti_line_items')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('score_given', 10, 2)->nullable();
            $table->decimal('score_maximum', 10, 2)->nullable();
            $table->string('activity_progress', 32);
            $table->string('grading_progress', 32);
            $table->text('comment')->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();
        });

        // Tool side: platforms that launch us.
        Schema::create('lti_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('issuer');
            $table->string('client_id');
            $table->json('deployment_ids');
            $table->string('auth_login_url');
            $table->string('auth_token_url');
            $table->string('auth_server')->nullable();
            $table->string('jwks_url');
            $table->unsignedBigInteger('default_course_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['issuer', 'client_id']);
        });

        Schema::create('lti_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lti_platform_id')->constrained('lti_platforms')->cascadeOnDelete();
            $table->string('sub');
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->unique(['lti_platform_id', 'sub']);
        });

        // Tool side: where to send grades for a learner's launch (AGS claim of the platform).
        Schema::create('lti_grade_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lti_platform_id')->constrained('lti_platforms')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->string('sub');
            $table->string('lineitem')->nullable();
            $table->string('lineitems')->nullable();
            $table->json('scopes');
            $table->timestamp('last_sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['lti_platform_id', 'user_id', 'course_id']);
        });

        // Single-use values: OIDC state and nonce, login hints, JWT ids, one-time codes.
        Schema::create('lti_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('value', 191);
            $table->text('payload')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
            $table->unique(['type', 'value']);
        });

        // Audit of every launch in either direction.
        Schema::create('lti_launches', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 16); // platform | tool
            $table->string('message_type', 64);
            $table->unsignedBigInteger('lti_tool_id')->nullable()->index();
            $table->unsignedBigInteger('lti_platform_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('status', 16); // ok | failed
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'lti_launches', 'lti_nonces', 'lti_grade_targets', 'lti_user_links', 'lti_platforms',
            'lti_scores', 'lti_line_items', 'topic_lti_links', 'lti_tools', 'lti_keys',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
