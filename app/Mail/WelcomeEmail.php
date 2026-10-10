<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
        public string $storeName = 'چیاکو',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'به '.$this->storeName.' خوش آمدید!',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $name = $this->subscriber->name ?: 'کاربر گرامی';
        $shopUrl = route('shop.index');
        $unsubscribeUrl = route('subscriber.unsubscribe', $this->subscriber->unsubscribe_token);

        return <<<HTML
<!DOCTYPE html>
<html dir="rtl">
<head><meta charset="utf-8"></head>
<body style="font-family: Tahoma, sans-serif; background: #f5f5f5; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 30px;">
<h2 style="color: #282828; text-align: center;">به {$this->storeName} خوش آمدید!</h2>
<p style="color: #555; line-height: 1.8;">{$name}،</p>
<p style="color: #555; line-height: 1.8;">از اینکه به جمع مشترکین خبرنامه {$this->storeName} پیوستید، بسیار خوشحالیم. از این پس از جدیدترین محصولات، تخفیف‌ها و رویدادهای ویژه ما زودتر از بقیه باخبر می‌شوید.</p>
<p style="color: #555; line-height: 1.8;">برای دیدن آخرین محصولات می‌توانید از لینک زیر دیدن کنید:</p>
<p style="text-align: center; margin: 25px 0;">
<a href="{$shopUrl}" style="background: #282828; color: #fff; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-size: 14px;">مشاهده فروشگاه</a>
</p>
<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="font-size: 12px; color: #999; text-align: center;">
اگر تمایل به لغو عضویت دارید، <a href="{$unsubscribeUrl}" style="color: #999;">اینجا کلیک کنید</a>.
</p>
</div>
</body>
</html>
HTML;
    }
}
