<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeEmail;
use App\Models\Subscriber;
use App\Models\EmailLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SubscriberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $subscriber = Subscriber::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'] ?? null],
        );

        if ($subscriber->wasRecentlyCreated) {
            try {
                Mail::to($subscriber->email)->send(new WelcomeEmail($subscriber));
                EmailLog::create([
                    'type' => 'welcome',
                    'recipient_email' => $subscriber->email,
                    'subject' => 'به چیاکو خوش آمدید!',
                    'status' => 'sent',
                    'relatable_type' => Subscriber::class,
                    'relatable_id' => $subscriber->id,
                ]);
            } catch (\Throwable $e) {
                EmailLog::create([
                    'type' => 'welcome',
                    'recipient_email' => $subscriber->email,
                    'subject' => 'به چیاکو خوش آمدید!',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        return back()->with('success', 'ایمیل شما با موفقیت ثبت شد. به جمع چیاکویی‌ها خوش آمدید!');
    }

    public function unsubscribe(string $token): \Illuminate\Contracts\View\View|RedirectResponse
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (! $subscriber) {
            return redirect('/')->with('error', 'لینک لغو عضویت نامعتبر است.');
        }

        $subscriber->delete();

        return view('subscriber.unsubscribed');
    }
}
