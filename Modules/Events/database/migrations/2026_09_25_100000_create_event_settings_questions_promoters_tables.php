<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_settings')) {
            Schema::create('event_settings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete()->unique();

                // Virtual & Streaming
                $table->string('stream_provider')->nullable();
                $table->text('stream_instructions')->nullable();

                // Policies & Guidelines
                $table->text('refund_policy')->nullable();
                $table->string('refund_option')->nullable();
                $table->string('age_restriction')->nullable();
                $table->string('dress_code')->nullable();
                $table->text('event_rules')->nullable();

                // Amenities
                $table->json('amenities')->nullable();

                // Notifications & Status Messages
                $table->text('confirmation_email_msg')->nullable();
                $table->text('status_message')->nullable();
                $table->boolean('is_status_msg_enabled')->default(false);
                $table->string('status_message_type')->nullable();
                $table->text('pre_sale_message')->nullable();
                $table->text('post_sale_message')->nullable();

                // Theme & Appearance
                $table->string('theme_color')->nullable();
                $table->string('banner_style')->nullable();

                // Media & FAQs (phased out from events.meta)
                $table->json('faqs')->nullable();
                $table->json('gallery_urls')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_custom_questions')) {
            Schema::create('event_custom_questions', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->string('question');
                $table->string('type')->default('Text');
                $table->text('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['event_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('event_promoters')) {
            Schema::create('event_promoters', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->string('code')->index();
                $table->decimal('discount', 10, 2)->default(0);
                $table->string('discount_type')->default('percentage');
                $table->unsignedInteger('uses')->default(0);
                $table->decimal('sales', 14, 2)->default(0);
                $table->unsignedInteger('max_uses')->nullable();
                $table->string('status')->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['event_id', 'sort_order']);
            });
        }

        // Migrate any existing events.meta data to event_settings
        if (Schema::hasTable('events') && Schema::hasTable('event_settings')) {
            $events = DB::table('events')->whereNotNull('meta')->get();
            foreach ($events as $event) {
                $meta = json_decode($event->meta ?? '[]', true) ?: [];
                if (empty($meta)) {
                    continue;
                }

                $faqs = $meta['faqs'] ?? null;
                $galleryUrls = $meta['galleryUrls'] ?? $meta['gallery'] ?? null;

                DB::table('event_settings')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'event_id' => $event->id,
                    'stream_provider' => $meta['streamProvider'] ?? null,
                    'stream_instructions' => $meta['streamInstructions'] ?? null,
                    'refund_policy' => $meta['refundPolicy'] ?? null,
                    'refund_option' => $meta['refundOption'] ?? null,
                    'age_restriction' => $meta['ageRestriction'] ?? null,
                    'dress_code' => $meta['dressCode'] ?? null,
                    'event_rules' => $meta['eventRules'] ?? null,
                    'amenities' => isset($meta['amenities']) ? json_encode($meta['amenities']) : null,
                    'confirmation_email_msg' => $meta['confirmationEmailMsg'] ?? null,
                    'status_message' => $meta['statusMessage'] ?? null,
                    'is_status_msg_enabled' => (bool) ($meta['isStatusMsgEnabled'] ?? false),
                    'status_message_type' => $meta['statusMessageType'] ?? null,
                    'pre_sale_message' => $meta['preSaleMessage'] ?? null,
                    'post_sale_message' => $meta['postSaleMessage'] ?? null,
                    'theme_color' => $meta['themeColor'] ?? null,
                    'banner_style' => $meta['bannerStyle'] ?? null,
                    'faqs' => $faqs ? json_encode($faqs) : null,
                    'gallery_urls' => $galleryUrls ? json_encode($galleryUrls) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_promoters');
        Schema::dropIfExists('event_custom_questions');
        Schema::dropIfExists('event_settings');
    }
};
