<?php

namespace Database\Seeders;

use App\Models\SecurityPreset;
use Illuminate\Database\Seeder;

class PresetSeeder extends Seeder
{
    public function run(): void
    {
        SecurityPreset::updateOrCreate(
            ['name' => 'High Security'],
            [
                'description' => 'Maximum protection for premium content. All security features enabled.',
                'settings' => [
                    'ip_binding_enabled' => true,
                    'fingerprint_lock' => true,
                    'geo_restriction_enabled' => false,
                    'key_rotation_enabled' => true,
                    'max_concurrent_streams' => 1,
                    'max_total_views' => 100,
                    'token_duration' => 5,
                    'watermark_enabled' => true,
                    'watermark_type' => 'ip',
                    'watermark_opacity' => 0.25,
                    'watermark_animation' => 'move',
                ],
                'is_default' => false,
            ]
        );

        SecurityPreset::updateOrCreate(
            ['name' => 'Balanced'],
            [
                'description' => 'Good balance between security and user experience.',
                'settings' => [
                    'ip_binding_enabled' => false,
                    'fingerprint_lock' => true,
                    'geo_restriction_enabled' => false,
                    'key_rotation_enabled' => false,
                    'max_concurrent_streams' => 3,
                    'max_total_views' => 0,
                    'token_duration' => 10,
                    'watermark_enabled' => true,
                    'watermark_type' => 'ip',
                    'watermark_opacity' => 0.20,
                    'watermark_animation' => 'move',
                ],
                'is_default' => true,
            ]
        );

        SecurityPreset::updateOrCreate(
            ['name' => 'Standard'],
            [
                'description' => 'Minimal restrictions for maximum accessibility.',
                'settings' => [
                    'ip_binding_enabled' => false,
                    'fingerprint_lock' => false,
                    'geo_restriction_enabled' => false,
                    'key_rotation_enabled' => false,
                    'max_concurrent_streams' => 0,
                    'max_total_views' => 0,
                    'token_duration' => 15,
                    'watermark_enabled' => false,
                    'watermark_type' => 'ip',
                    'watermark_opacity' => 0.30,
                    'watermark_animation' => 'static',
                ],
                'is_default' => false,
            ]
        );
    }
}
