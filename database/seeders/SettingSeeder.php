<?php

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SettingService $settings */
        $settings = app(SettingService::class);

        $settings->setMany([
            'site_name' => config('app.name', 'Starter Kit'),
            'site_description' => 'Your workspace is ready.',
            'site_title' => config('app.name', 'Starter Kit'),
            'meta_title' => config('app.name', 'Starter Kit'),
            'meta_description' => '',
            'meta_tags' => [],
            'meta_os_image' => null,
            'site_logo' => null,
            'site_logo_2' => null,
            'site_mobile_logo' => null,
            'site_favicon' => null,
            'admin_logo' => null,
            'footer_logo' => null,
            'company_name' => config('app.name', 'Starter Kit'),
            'company_email' => config('mail.from.address', 'hello@example.com'),
            'company_phone' => '',
            'company_about' => '',
            'company_address' => '',
            'fb_link' => '',
            'twitter_link' => '',
            'instagram_link' => '',
            'pinterest_link' => '',
            'linked_in_link' => '',
            'youtube_link' => '',
            'google_plus_link' => '',
            'footer_description' => '',
            'editor_layout' => 'ck-editor-4',
            'app_base_url' => config('app.url', 'http://localhost'),
            'app_email' => config('mail.from.address', 'hello@example.com'),
            'timezone' => config('app.timezone', 'UTC'),
            'locale' => config('app.locale', 'en'),
            'captcha' => 1,
            'maintenance_mode' => 0,
            // Legacy aliases used by older pages
            'app_name' => config('app.name', 'Starter Kit'),
            'app_url' => config('app.url', 'http://localhost'),
        ], 'general');

        $settings->setMany([
            'site_title' => config('app.name', 'Starter Kit'),
            'site_tagline' => 'Your workspace is ready.',
            'meta_description' => '',
            'logo_path' => null,
            'favicon_path' => null,
        ], 'website');

        $settings->setMany([
            'head_scripts' => '',
            'body_scripts' => '',
        ], 'scripts_third_party');

        $settings->setMany([
            'custom_css' => '',
            'custom_js' => '',
        ], 'scripts_custom');

        $settings->setMany([
            'mailer' => config('mail.default', 'smtp'),
            'host' => config('mail.mailers.smtp.host', ''),
            'port' => (string) config('mail.mailers.smtp.port', '587'),
            'username' => config('mail.mailers.smtp.username', ''),
            'password' => '',
            'encryption' => config('mail.mailers.smtp.encryption', 'tls') ?: 'tls',
            'from_address' => config('mail.from.address', ''),
            'from_name' => config('mail.from.name', ''),
            'support_phone' => '',
        ], 'mail');
    }
}
