# Implementation Checklist

## Files Modified
- ✅ `Services/GoogleDriveService.php` - Added streaming methods
- ✅ `Services/VideoStreamingService.php` - Optimized getSegment() with caching
- ✅ `Http/Controllers/VideoStreamController.php` - Improved segment response headers
- ✅ `Config/config.php` - Added cache configuration

## Files Created
- ✅ `Services/SegmentCacheService.php` - Multi-layer caching system
- ✅ `Http/Middleware/SegmentRateLimiter.php` - Rate limiting for segments
- ✅ `PERFORMANCE_OPTIMIZATION.md` - Complete documentation

## Required Actions

### 1. Service Provider Registration
**File:** `Modules/VideoStreaming/Providers/VideoStreamingServiceProvider.php`

Add to the `register()` method:
```php
public function register()
{
    // ... existing code ...
    
    $this->app->singleton(\Modules\VideoStreaming\Services\SegmentCacheService::class);
}
```

### 2. Middleware Registration
**File:** `app/Http/Kernel.php`

Add to `$routeMiddleware` array:
```php
protected $routeMiddleware = [
    // ... existing middleware ...
    'segment.rate.limit' => \Modules\VideoStreaming\Http\Middleware\SegmentRateLimiter::class,
];
```

### 3. Route Update
**File:** `Modules/VideoStreaming/Routes/web.php`

Update segment route:
```php
Route::middleware(['segment.rate.limit'])->group(function () {
    Route::get('/{uuid}/{quality}/segment/{segment}.ts', 
        [VideoStreamController::class, 'segment']
    )->name('video.stream.segment');
});
```

### 4. Environment Configuration
**File:** `.env`

Add these variables:
```env
# Cache Settings
VIDEO_CACHE_DRIVER=file
VIDEO_CACHE_TTL=86400
VIDEO_CACHE_MAX_SIZE=5000
VIDEO_CACHE_PRELOAD=true
VIDEO_CACHE_PRELOAD_COUNT=3

# Optional: Reduce segment duration for faster start
HLS_SEGMENT_DURATION=6
```

### 5. Create Cache Directory
```bash
mkdir -p storage/app/cache/segments
chmod -R 775 storage/app/cache
```

### 6. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 7. Optional: Install Redis (Recommended)
```bash
# Ubuntu/Debian
sudo apt-get install redis-server
sudo systemctl start redis
sudo systemctl enable redis

# Update .env
VIDEO_CACHE_DRIVER=redis
```

## Testing

### Test Segment Delivery
```bash
# Get a video token first
TOKEN="your-access-token"
UUID="video-uuid"

# Test segment request
curl -I "http://localhost/video/stream/${UUID}/720p/segment/0.ts?token=${TOKEN}"

# Should return:
# HTTP/1.1 200 OK
# Content-Type: video/mp2t
# Cache-Control: public, max-age=31536000, immutable
# ETag: ...
```

### Monitor Cache Performance
```bash
# Watch logs for cache hits/misses
tail -f storage/logs/laravel.log | grep "Segment cache"

# Output examples:
# Segment cache HIT (Disk): /path/to/segment.ts
# Segment cache MISS: segment:uuid:720p:5
# Preloaded segment: uuid 720p 6
```

### Check Cache Size
```bash
du -sh storage/app/cache/segments
```

## Performance Validation

### Before Optimization Metrics:
- API calls per 60-segment video: **120+**
- Segment latency: **500-2000ms**
- Buffering: **Frequent**

### After Optimization Targets:
- API calls per 60-segment video: **1-5** (95%+ reduction)
- Segment latency (cached): **5-50ms**
- Segment latency (uncached): **200-500ms**
- Buffering: **Eliminated**

### Monitoring Commands
```bash
# Check Redis cache (if using Redis)
redis-cli
> KEYS segment:*
> GET segment:uuid:720p:0

# Check disk cache
ls -lh storage/app/cache/segments/

# Monitor API calls (add to GoogleDriveService)
tail -f storage/logs/laravel.log | grep "Google Drive"
```

## Troubleshooting

### Issue: Cache not working
**Check:**
1. Directory permissions: `chmod -R 775 storage/app/cache`
2. Config cached: `php artisan config:clear`
3. Service registered: Check ServiceProvider

### Issue: Still slow
**Solutions:**
1. Enable Redis: `VIDEO_CACHE_DRIVER=redis`
2. Increase preload: `VIDEO_CACHE_PRELOAD_COUNT=5`
3. Reduce segment duration: `HLS_SEGMENT_DURATION=6`

### Issue: Rate limiting too strict
**Adjust in:** `Http/Middleware/SegmentRateLimiter.php`
```php
protected int $maxRequestsPerMinute = 240; // Increase from 120
protected int $maxConcurrentRequests = 20; // Increase from 10
```

### Issue: High disk usage
**Solutions:**
1. Reduce max size: `VIDEO_CACHE_MAX_SIZE=2000` (2GB)
2. Reduce TTL: `VIDEO_CACHE_TTL=43200` (12 hours)
3. Manual cleanup: Delete `storage/app/cache/segments/*`

## Rollback Plan

If issues occur, revert these files:
```bash
git checkout HEAD -- Modules/VideoStreaming/Services/GoogleDriveService.php
git checkout HEAD -- Modules/VideoStreaming/Services/VideoStreamingService.php
git checkout HEAD -- Modules/VideoStreaming/Http/Controllers/VideoStreamController.php
git checkout HEAD -- Modules/VideoStreaming/Config/config.php

# Remove new files
rm Modules/VideoStreaming/Services/SegmentCacheService.php
rm Modules/VideoStreaming/Http/Middleware/SegmentRateLimiter.php

php artisan config:clear
php artisan cache:clear
```

## Success Criteria

✅ **Segment delivery < 100ms** (cached)  
✅ **No buffering during playback**  
✅ **95%+ reduction in API calls**  
✅ **Cache hit rate > 90%** (after warmup)  
✅ **No security regressions**  

## Next Steps (Optional Enhancements)

1. **CDN Integration** - Offload popular segments to CDN
2. **Adaptive Preloading** - Adjust preload count based on bandwidth
3. **Segment Compression** - Enable gzip in web server
4. **Analytics Dashboard** - Monitor cache performance
5. **Batch File ID Caching** - Cache all segment file IDs on first request
