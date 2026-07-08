<?php

namespace App\Mail;

use App\Models\SavedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SavedCart $cart,
        public string $storeName = 'چیاکو',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'سبد خرید شما در '.$this->storeName.' منتظر است!',
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
        $name = $this->cart->user?->name ?: 'کاربر گرامی';
        $recoveryUrl = route('cart.restore', $this->cart->token);
        $itemsHtml = '';
        foreach ((array) $this->cart->items as $item) {
            $itemsHtml .= '<li style="padding: 8px 0; border-bottom: 1px solid #eee;">'
                .($item['name'] ?? 'محصول')
                .' — '.($item['quantity'] ?? 1).' عدد'
                .'</li>';
        }

        return <<<HTML
<!DOCTYPE html>
<html dir="rtl">
<head><meta charset="utf-8"></head>
<body style="font-family: Tahoma, sans-serif; background: #f5f5f5; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 30px;">
<h2 style="color: #282828; text-align: center;">{$name}، سبد خرید شما منتظر است!</h2>
<p style="color: #555; line-height: 1.8;">محصولات زیر در سبد خرید شما باقی مانده‌اند. برای تکمیل خرید روی دکمه زیر کلیک کنید:</p>
<ul style="color: #555; line-height: 1.8; padding-right: 20px;">{$itemsHtml}</ul>
<p style="text-align: center; margin: 25px 0;">
<a href="{$recoveryUrl}" style="background: #282828; color: #fff; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-size: 14px;">بازگشت به سبد خرید</a>
</p>
</div>
</body>
</html>
HTML;
    }
}
