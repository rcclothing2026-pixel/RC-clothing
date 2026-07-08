<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscriber::latest();

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($w) use ($q) {
                $w->where('email', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        return view('admin.subscribers.index', [
            'subscribers' => $query->paginate(30)->withQueryString(),
            'count' => Subscriber::count(),
        ]);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('success', 'مشترک حذف شد.');
    }

    public function broadcast(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $subscribers = Subscriber::all();
        $sent = 0;

        foreach ($subscribers as $sub) {
            try {
                Mail::to($sub->email)->send(new \App\Mail\CampaignEmail($data['subject'], $data['body']));
                $sent++;
                \App\Models\EmailLog::create([
                    'type' => 'campaign',
                    'recipient_email' => $sub->email,
                    'subject' => $data['subject'],
                    'status' => 'sent',
                ]);
            } catch (\Throwable $e) {
                \App\Models\EmailLog::create([
                    'type' => 'campaign',
                    'recipient_email' => $sub->email,
                    'subject' => $data['subject'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        return back()->with('success', Money::toPersianDigits((string) $sent).' ایمیل خبرنامه ارسال شد.');
    }

    public function export(): \Illuminate\Http\Response
    {
        $emails = Subscriber::pluck('email')->implode("\n");

        return response($emails, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="subscribers.txt"',
        ]);
    }
}
