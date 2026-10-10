<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\Subscriber;
use App\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Shown once, right after a brand-new account verifies its OTP: collect the
 * customer's name + surname so the CRM has it and checkout can autofill it.
 */
class ProfileCompletionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (filled($request->user()->name)) {
            return redirect()->intended(route('account.index'));
        }

        return view('auth.complete-profile');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
        ], [], ['first_name' => 'نام', 'last_name' => 'نام خانوادگی']);

        $user = $request->user();

        // Combined name is the recipient default at checkout; the update fires the
        // UserObserver which pushes the customer to the StoqS CRM.
        $user->update([
            'name' => trim($data['first_name'].' '.$data['last_name']),
        ]);

        // Send welcome email if the user has an email address.
        if ($user->email) {
            try {
                $subscriber = Subscriber::firstOrCreate(
                    ['email' => $user->email],
                    ['name' => $user->name],
                );
                if ($subscriber->wasRecentlyCreated) {
                    Mail::to($user->email)->send(new WelcomeEmail($subscriber));
                    EmailLog::create([
                        'type' => 'welcome',
                        'recipient_email' => $user->email,
                        'subject' => 'به چیاکو خوش آمدید!',
                        'status' => 'sent',
                        'relatable_type' => Subscriber::class,
                        'relatable_id' => $subscriber->id,
                    ]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->intended(route('account.index'))->with('success', 'خوش آمدید! حساب شما تکمیل شد.');
    }
}
