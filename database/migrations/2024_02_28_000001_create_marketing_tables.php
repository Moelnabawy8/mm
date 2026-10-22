<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('marketing_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('created_by')->default(0);
            $table->string('platform')->comment('facebook, instagram, twitter, linkedin, etc.');
            $table->string('account_name');
            $table->string('account_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('page_id')->nullable();
            $table->string('page_name')->nullable();
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->integer('created_by')->default(0);
            $table->foreignId('account_id')->constrained('marketing_accounts')->onDelete('cascade');
            $table->string('campaign_name');
            $table->string('campaign_id')->nullable();
            $table->string('platform');
            $table->enum('objective', ['awareness', 'traffic', 'engagement', 'leads', 'sales', 'conversions'])->default('awareness');
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->decimal('budget', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->json('targeting')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_campaign_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->onDelete('cascade');
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('engagement')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('spend', 15, 2)->default(0);
            $table->decimal('cpc', 10, 4)->default(0)->comment('Cost per click');
            $table->decimal('cpm', 10, 4)->default(0)->comment('Cost per thousand impressions');
            $table->decimal('ctr', 10, 4)->default(0)->comment('Click-through rate');
            $table->decimal('conversion_rate', 10, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['campaign_id', 'date']);
        });

        Schema::create('marketing_posts', function (Blueprint $table) {
            $table->id();
            $table->integer('created_by')->default(0);
            $table->foreignId('account_id')->constrained('marketing_accounts')->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('set null');
            $table->string('post_id')->nullable();
            $table->text('content');
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable()->comment('image, video, carousel');
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('likes')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('reach')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('marketing_accounts')->onDelete('cascade');
            $table->date('date');
            $table->integer('followers')->default(0);
            $table->integer('page_views')->default(0);
            $table->integer('profile_visits')->default(0);
            $table->integer('total_engagement')->default(0);
            $table->decimal('engagement_rate', 10, 4)->default(0);
            $table->json('demographics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['account_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketing_insights');
        Schema::dropIfExists('marketing_posts');
        Schema::dropIfExists('marketing_campaign_results');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_accounts');
    }
};
