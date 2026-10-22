<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsApiController extends Controller
{
    use ApiResponser;

    public function getSettings()
    {
        $settings = Utility::settings();
        return $this->success(['settings' => $settings], 'Settings retrieved.');
    }

    public function updateSettings(Request $request)
    {
        if (Auth::user()->can('manage system settings')) {
            $settings = Utility::settings();
            $post = $request->all();

            // Filter allowed keys if necessary, or just update what's passed and valid
            foreach ($post as $key => $data) {
                if (in_array($key, array_keys($settings))) {
                    DB::insert(
                        'insert into settings (`value`, `name`,`created_by`) values (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                        [
                            $data,
                            $key,
                            Auth::user()->creatorId(),
                        ]
                    );
                }
            }
            return $this->success([], 'Settings updated successfully.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function getCompanySettings()
    {
        $settings = Utility::settings();
        // Filter or return relevant company settings
        return $this->success(['settings' => $settings], 'Company Settings retrieved.');
    }

    public function updateCompanySettings(Request $request)
    {
        if (Auth::user()->can('manage company settings')) {
            $validator = Validator::make($request->all(), [
                'company_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            $post = $request->all();
            $settings = Utility::settings();

            foreach ($post as $key => $data) {
                if (in_array($key, array_keys($settings))) {
                    DB::insert(
                        'insert into settings (`value`, `name`,`created_by`) values (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                        [
                            $data,
                            $key,
                            Auth::user()->creatorId(),
                        ]
                    );
                }
            }
            return $this->success([], 'Company Settings updated successfully.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function getEmailSettings()
    {
        $settings = Utility::settings();
        // Should verify security implication of returning SMTP credentials via API
        // For now, return relevant keys or masked values
        $emailSettings = [
            'mail_driver' => $settings['mail_driver'] ?? '',
            'mail_host' => $settings['mail_host'] ?? '',
            'mail_port' => $settings['mail_port'] ?? '',
            'mail_username' => $settings['mail_username'] ?? '',
            'mail_from_address' => $settings['mail_from_address'] ?? '',
            'mail_from_name' => $settings['mail_from_name'] ?? '',
            // 'mail_password' => '********', // Mask password
        ];
        return $this->success(['email_settings' => $emailSettings], 'Email Settings retrieved.');
    }

    public function updateEmailSettings(Request $request)
    {
        if (Auth::user()->can('manage system settings')) {
            $validator = Validator::make($request->all(), [
                'mail_driver' => 'required|string|max:255',
                'mail_host' => 'required|string|max:255',
                'mail_port' => 'required|string|max:255',
                'mail_username' => 'required|string|max:255',
                'mail_password' => 'required|string|max:255',
                'mail_encryption' => 'required|string|max:255',
                'mail_from_address' => 'required|string|max:255',
                'mail_from_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            $post = $request->all();
            $settings = Utility::settings();

            foreach ($post as $key => $data) {
                if (in_array($key, array_keys($settings))) {
                    DB::insert(
                        'insert into settings (`value`, `name`,`created_by`) values (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                        [
                            $data,
                            $key,
                            Auth::user()->creatorId(),
                        ]
                    );
                }
            }
            return $this->success([], 'Email Settings updated successfully.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function getPaymentSettings()
    {
        $settings = Utility::settings();
        $paymentSettings = [
            'currency' => $settings['currency'] ?? '',
            'currency_symbol' => $settings['currency_symbol'] ?? '',
            'is_stripe_enabled' => $settings['is_stripe_enabled'] ?? '',
            'is_paypal_enabled' => $settings['is_paypal_enabled'] ?? '',
            // Add other payment gateways status
        ];
        return $this->success(['payment_settings' => $paymentSettings], 'Payment Settings retrieved.');
    }

    public function updatePaymentSettings(Request $request)
    {
        if (Auth::user()->can('manage system settings')) {
            $post = $request->all();
            $settings = Utility::settings();

            foreach ($post as $key => $data) {
                if (in_array($key, array_keys($settings))) {
                    DB::insert(
                        'insert into settings (`value`, `name`,`created_by`) values (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ',
                        [
                            $data,
                            $key,
                            Auth::user()->creatorId(),
                        ]
                    );
                }
            }
            return $this->success([], 'Payment Settings updated successfully.');
        }
        return $this->error('Permission denied.', 403);
    }
}
