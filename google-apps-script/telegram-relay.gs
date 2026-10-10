/**
 * Chiiaco Telegram relay — runs on Google's servers (reachable from Iran AND
 * reachable BY Telegram), bridging both directions around the national filter:
 *
 *   OUTBOUND  PHP server → (this relay) → api.telegram.org      (sendMessage…)
 *   INBOUND   Telegram   → (this relay) → chiiaco.com webhook   (/start, buttons)
 *
 * Telegram cannot connect to the Parspack/cPanel host directly (Iran is filtered
 * on Telegram's side → "Connection timed out"), so the bot's webhook is pointed
 * at THIS script instead, and we forward each update to the real PHP endpoint.
 *
 * SETUP
 *   1. script.google.com → your existing project → replace with this file.
 *   2. Set SECRET (your existing relay secret), DEFAULT_BOT_TOKEN (BotFather
 *      token), and FORWARD_URL (the site webhook, incl. its path secret).
 *   3. Deploy → Manage deployments → edit the existing Web app deployment →
 *      Version: New version → Deploy. (Keeps the same /exec URL.)
 *        Execute as: Me   |   Who has access: Anyone
 *   4. Point the Telegram webhook at this script's plain /exec URL (VPN on), once:
 *        https://api.telegram.org/bot<TOKEN>/setWebhook?url=<EXEC_URL>&allowed_updates=%5B%22message%22%2C%22callback_query%22%5D
 *   5. getWebhookInfo should now show url=<EXEC_URL> and NO "Connection timed
 *      out" — Telegram reaches Google fine, Google reaches chiiaco fine.
 *
 * The site admin (پنل تلگرام) keeps the same آدرس رله / کلید مشترک رله for OUTBOUND.
 */

var SECRET = 'CHANGE_ME';                  // must equal TELEGRAM_RELAY_SECRET
var DEFAULT_BOT_TOKEN = 'CHANGE_ME';       // your BotFather token (fallback)
// Real site webhook, including the path secret = first 24 chars of sha256(token):
var FORWARD_URL = 'https://chiiaco.com/webhooks/telegram/CHANGE_ME';

function doGet(e) {
  return json({ ok: true, service: 'chiiaco telegram relay' });
}

function doPost(e) {
  try {
    var raw = (e && e.postData && e.postData.contents) || '{}';
    var body = JSON.parse(raw);

    // INBOUND: a Telegram Update (always carries update_id, never our "secret")
    // posted by the bot webhook → forward verbatim to the PHP webhook, which
    // validates its own path secret. The unguessable /exec URL is the shared
    // secret here, so the webhook URL stays plain (nothing to encode).
    if (body.update_id !== undefined && body.secret === undefined) {
      UrlFetchApp.fetch(FORWARD_URL, {
        method: 'post',
        contentType: 'application/json',
        payload: raw,
        muteHttpExceptions: true,
      });
      return json({ ok: true });
    }

    // OUTBOUND: the PHP server relaying a Bot API call.
    if (String(body.secret) !== String(SECRET)) {
      return json({ ok: false, error: 'forbidden' });
    }

    var token = body.bot_token || DEFAULT_BOT_TOKEN;

    // Two request shapes are accepted:
    //   1) generic: { method: 'sendMessage', params: {...} }
    //   2) simple:  { chat_id, text }  → treated as sendMessage
    var method = body.method || 'sendMessage';
    var params = body.params || {};
    if (!body.method && body.chat_id) {
      params = { chat_id: body.chat_id, text: body.text || '', parse_mode: 'HTML' };
    }

    // reply_markup may arrive as a JSON string (form style) — form-encoding it
    // back to Telegram is exactly what the Bot API expects, so we pass params
    // through as a form payload (UrlFetchApp url-encodes the object for us).
    var resp = UrlFetchApp.fetch('https://api.telegram.org/bot' + token + '/' + method, {
      method: 'post',
      payload: params,
      muteHttpExceptions: true,
    });

    var parsed;
    try { parsed = JSON.parse(resp.getContentText() || '{"ok":false}'); }
    catch (err) { parsed = { ok: false, error: 'bad telegram response' }; }
    return json(parsed);
  } catch (err) {
    return json({ ok: false, error: String(err) });
  }
}

function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
