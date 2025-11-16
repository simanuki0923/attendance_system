<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ▼ 認証メール（Verify Email Address）の文面を日本語にカスタマイズ
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('【勤怠管理システム】メールアドレス確認のお願い')
                ->greeting($notifiable->name . ' 様')
                ->line('この度は、勤怠管理システムへご登録いただきありがとうございます。')
                ->line('以下のボタンをクリックして、メールアドレスの確認を完了してください。')
                ->action('メールアドレスを確認する', $url)
                ->line('※このメールに心当たりがない場合は、このメールを破棄してください。');
        });
    }
}
