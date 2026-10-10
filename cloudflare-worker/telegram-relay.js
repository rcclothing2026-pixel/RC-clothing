/**
 * Chiiaco Telegram relay — Cloudflare Worker version.
 *
 * Use this when Google Apps Script won't serve the web app publicly (Workspace
 * accounts block anonymous web apps → Telegram/cURL get a Drive "page not found"
 * even with access = Anyone). A Worker has no such restriction and returns a
 * clean HTTP 200 (no 302), so Telegram is happy and there are no retries.
 *
 * Bridges both directions around the Iran filter:
 *   INBOUND   Telegram   → (this worker) → chiiaco.com webhook   (/start, buttons)
 *   OUTBOUND  PHP server → (this worker) → api.telegram.org      (sendMessage…)
 *
 * SETUP (free, ~2 min, no domain needed)
 *   1. cloudflare.com → sign up / log in.
 *   2. Workers & Pages → Create → Create Worker → name it (e.g. chiiaco-relay) → Deploy.
 *   3. Edit code → paste this file → Deploy.
 *   4. Copy the worker URL: https://chiiaco-relay.<your-subdomain>.workers.dev
 *   5. INBOUND: point the Telegram webhook at it (VPN on):
 *        https://api.telegram.org/bot<TOKEN>/setWebhook?url=<WORKER_URL>&allowed_updates=%5B%22message%22%2C%22callback_query%22%5D
 *   6. OUTBOUND (only if push/notifications stopped working): set the site admin
 *        پنل تلگرام «آدرس رله» = <WORKER_URL>, keep «کلید مشترک رله» = SECRET below.
 */

const SECRET = 'b61628a5811c67580965732c0225a6e6db71a71849488337';
const DEFAULT_BOT_TOKEN = '8946503213:AAHQmGhfnFI8rqmHM50UQx_D9rUEDBxyM20';
const FORWARD_URL = 'https://chiiaco.com/webhooks/telegram/deb436a239d095bd058b7724';

export default {
  async fetch(request) {
    if (request.method !== 'POST') {
      return json({ ok: true, service: 'chiiaco telegram relay' });
    }

    const raw = await request.text();
    let body = {};
    try { body = JSON.parse(raw); } catch (e) {}

    // INBOUND: a Telegram Update (has update_id, never our "secret") → forward
    // verbatim to the PHP webhook, then return a clean 200 to Telegram.
    if (body.update_id !== undefined && body.secret === undefined) {
      await fetch(FORWARD_URL, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: raw,
      });
      return new Response('ok', { status: 200 });
    }

    // OUTBOUND: the PHP server relaying a Bot API call.
    if (String(body.secret) !== String(SECRET)) {
      return json({ ok: false, error: 'forbidden' });
    }
    const token = body.bot_token || DEFAULT_BOT_TOKEN;
    const method = body.method || 'sendMessage';
    let params = body.params || {};
    if (!body.method && body.chat_id) {
      params = { chat_id: body.chat_id, text: body.text || '', parse_mode: 'HTML' };
    }
    // Form-encode so reply_markup (a JSON string) reaches Telegram as the Bot API
    // expects — same as the Apps Script version.
    const form = new URLSearchParams();
    for (const k in params) {
      const v = params[k];
      form.append(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
    }
    const resp = await fetch(`https://api.telegram.org/bot${token}/${method}`, { method: 'POST', body: form });
    return new Response(await resp.text(), { headers: { 'content-type': 'application/json' } });
  },
};

function json(obj) {
  return new Response(JSON.stringify(obj), { headers: { 'content-type': 'application/json' } });
}
