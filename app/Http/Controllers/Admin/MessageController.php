<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Inbox: one row per conversation. Conversations group by telegram_chat_id
     * (preferred), falling back to phone, then email. Rows are ordered by the
     * most recent message in the thread — newest at the top, like a chat inbox.
     */
    public function index(): View
    {
        $latest = ContactMessage::latest()->limit(200)->get();

        $threads = $latest
            ->groupBy(fn (ContactMessage $m) => $m->telegram_chat_id
                ?: ($m->phone ?: ($m->email ?: 'm:'.$m->id)))
            ->map(fn ($group) => [
                'head' => $group->first(),
                'count' => $group->count(),
                'unread' => $group->where('direction', ContactMessage::DIR_IN)->where('is_read', false)->count(),
            ])
            ->values();

        return view('admin.messages.index', compact('threads'));
    }

    public function show(ContactMessage $message): View
    {
        $thread = $message->threadMessages();
        // Mark every inbound message in this thread as read.
        ContactMessage::whereIn('id', $thread->where('direction', ContactMessage::DIR_IN)->pluck('id'))
            ->update(['is_read' => true]);

        return view('admin.messages.show', compact('message', 'thread'));
    }

    public function reply(Request $request, ContactMessage $message, TelegramNotifier $tg): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'max:4000']]);

        if (! $message->telegram_chat_id) {
            return back()->with('error', 'این مکالمه از تلگرام نیست — برای پاسخ از تلفن/ایمیل/واتساپ استفاده کنید.');
        }

        $ok = $tg->sendSupportReply($message, $data['reply'], $request->user()?->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'پاسخ شما برای مشتری در تلگرام ارسال شد.' : 'ارسال پاسخ ناموفق بود — اتصال تلگرام را بررسی کنید.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'پیام حذف شد.');
    }
}
