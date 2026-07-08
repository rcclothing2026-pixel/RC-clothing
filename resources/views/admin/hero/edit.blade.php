<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>بنر هیرو — مدیریت</title>
@vite(['resources/css/app.css'])
<style>
  body{margin:0;font-family:'Vazirmatn',system-ui,sans-serif;background:#f4eff1;color:#282828;}
  .ci{width:100%;border:1px solid #e3dadd;border-radius:10px;padding:9px 12px;font:400 14px 'Vazirmatn',sans-serif;color:#282828;background:#fff;outline:none;transition:border .15s ease;}
  .ci:focus{border-color:#CC3333;}
  input[type=color].ci{padding:3px 6px;height:40px;cursor:pointer;}
  .cl{display:block;font-size:11px;letter-spacing:.04em;color:#9b9094;margin:0 0 5px;font-weight:500;}
  .btn{border:none;border-radius:10px;padding:10px 16px;font:500 14px 'Vazirmatn',sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
  .btn-red{background:#CC3333;color:#fff;}
  .btn-ghost{background:#fff;color:#282828;border:1px solid #e3dadd;}
  .iconbtn{border:none;background:#f4eff1;border-radius:8px;min-width:30px;height:30px;cursor:pointer;color:#5a5256;display:flex;align-items:center;justify-content:center;font-size:14px;padding:0 8px;}
  .iconbtn:hover{background:#e8dee1;}
  .iconbtn.danger{background:#fbeaea;color:#CC3333;}
  .swatch{width:30px;height:30px;border-radius:8px;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 1px #e3dadd;padding:0;}
  .swatch.on{box-shadow:0 0 0 2px #fff,0 0 0 4px #282828;}
  .sect{font-weight:600;font-size:13px;letter-spacing:.06em;color:#CC3333;}
  .card{background:#fff;border:1px solid #e3dadd;border-radius:14px;padding:14px;margin-bottom:12px;}
  .tab{border:none;cursor:pointer;border-radius:9px;padding:8px 14px;font:500 14px 'Vazirmatn',sans-serif;background:#fff;color:#5a5256;border:1px solid #e3dadd;}
  .tab.on{background:#282828;color:#fff;border-color:#282828;}
  .psearch-wrap{position:relative;}
  .psearch-list{position:absolute;z-index:30;top:100%;right:0;left:0;margin-top:4px;max-height:220px;overflow:auto;background:#fff;border:1px solid #e3dadd;border-radius:10px;box-shadow:0 12px 28px -12px rgba(0,0,0,.25);display:none;}
  .psearch-list.show{display:block;}
  .psearch-opt{display:flex;align-items:center;gap:10px;padding:8px 10px;cursor:pointer;border:none;background:none;width:100%;text-align:right;font:400 13px 'Vazirmatn';}
  .psearch-opt:hover{background:#faf6f7;}
  .psearch-opt img{width:30px;height:30px;border-radius:7px;object-fit:cover;background:#f0eaec;}
</style>
</head>
<body>
<div style="min-height:100vh;display:flex;flex-direction:column;">
  <header style="background:#282828;color:#fff;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:14px;">
      <svg width="30" viewBox="0 0 56 44" fill="none" stroke="#CC3333" stroke-width="2.8" stroke-linejoin="miter"><path d="M5 39 V31 H13 V23 H21 V13 H27 M51 39 V31 H43 V23 H35 V13 H29 M27 13 H29 M24 39 V30 H32 V39"></path></svg>
      <div>
        <div style="font-weight:600;font-size:17px;">چیاکو</div>
        <div style="font-size:12px;color:#b6abae;margin-top:-2px;">مدیریت بنر هیرو</div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <span id="saved" style="font-size:12px;color:#9aa;display:flex;align-items:center;gap:6px;"><span style="width:7px;height:7px;border-radius:50%;background:#3fbf73;"></span>ذخیره شد</span>
      <button id="btn-reset" class="btn btn-ghost" style="color:#fff;background:transparent;border-color:#4a4446;">بازنشانی به پیش‌فرض</button>
      <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}" style="color:#fff;background:transparent;border-color:#4a4446;">→ پنل</a>
      <a class="btn btn-red" href="{{ url('/') }}" target="_blank">مشاهده زنده ↗</a>
    </div>
  </header>

  <div style="flex:1;display:flex;align-items:stretch;min-height:0;flex-wrap:wrap;">
    <div id="editor" style="width:46%;min-width:340px;flex:1 1 460px;overflow-y:auto;padding:22px 24px 60px;border-left:1px solid #e3dadd;background:#f9f5f6;"></div>

    <div style="flex:1 1 420px;padding:20px;display:flex;flex-direction:column;gap:12px;background:#ece3e6;min-width:320px;">
      <div style="font-size:12px;letter-spacing:.06em;color:#9b9094;font-weight:500;">پیش‌نمایش زنده — با هر تغییر به‌روزرسانی می‌شود</div>
      <div style="background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 20px 50px -24px rgba(40,20,30,.4);flex:1;position:relative;min-height:420px;">
        <iframe id="preview" src="{{ route('admin.hero.preview') }}" title="پیش‌نمایش" style="position:absolute;inset:0;width:100%;height:100%;border:none;"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const ROUTES = {
    save:     @json(route('admin.hero.save')),
    upload:   @json(route('admin.hero.upload')),
    reset:    @json(route('admin.hero.reset')),
    products: @json(route('admin.hero.products')),
    productImages: @json(route('admin.hero.product-images')),
  };
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const imgCache = {}; // product slug → current catalog image (preview only)
  function slugOf(link){ const m = String(link||'').match(/\/product\/([^\/?#]+)/); return m ? m[1] : ''; }
  const ACCENTS = [['red','قرمز'],['dark','تیره'],['pink','صورتی']];
  const GRAD = {
    red: 'radial-gradient(circle at 38% 30%, #ef6a5e 0%, #CC3333 52%, #8f2222 100%)',
    dark:'radial-gradient(circle at 38% 30%, #5a5a5a 0%, #282828 55%, #050505 100%)',
    pink:'radial-gradient(circle at 38% 30%, #f3ecef 0%, #E0D5D9 55%, #c2afb6 100%)'
  };
  let cfg = @json($config);
  let slideIdx = 0;
  const editor  = document.getElementById('editor');
  const savedEl = document.getElementById('saved');
  const preview = document.getElementById('preview');
  let saveTimer, previewTimer;

  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function status(t){ savedEl.lastChild.textContent = t; }
  function validHex(v){ return /^#[0-9a-fA-F]{6}$/.test(String(v||'')); }
  function floaterBg(f){
    if (f.color_hex && validHex(f.color_hex)) return f.color_hex;
    return GRAD[f.color] || GRAD.red;
  }

  function persist(){
    status('در حال ذخیره…');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      fetch(ROUTES.save, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({ config: cfg }) })
        .then(() => { status('ذخیره شد'); reloadPreview(); })
        .catch(() => status('خطا در ذخیره'));
    }, 350);
  }
  function reloadPreview(){ clearTimeout(previewTimer); previewTimer = setTimeout(() => { preview.contentWindow.location.reload(); }, 150); }

  function slide(){ return cfg.slides[Math.min(slideIdx, cfg.slides.length-1)] || {products:[],floaters:[]}; }

  function swatches(current, kind, idx){
    return ACCENTS.map(([n,label]) =>
      `<button class="swatch${current===n?' on':''}" style="background:${GRAD[n]}" title="${label}" data-swatch="${kind}" data-color="${n}" data-idx="${idx==null?'':idx}"></button>`
    ).join('');
  }

  function imageField(label, val, kind, idx){
    return `<div>
      <label class="cl">${label}</label>
      <div class="img-field" data-imgkind="${kind}" data-idx="${idx==null?'':idx}" style="display:flex;align-items:center;gap:10px;">
        <div class="img-prev" style="width:42px;height:42px;border-radius:9px;flex-shrink:0;background:${val?`url('${esc(val)}') center/cover`:'#f0eaec'};box-shadow:0 0 0 1px #e3dadd;"></div>
        <label class="iconbtn" style="width:auto;padding:0 12px;cursor:pointer;">بارگذاری<input type="file" accept="image/*" class="img-file" hidden></label>
        ${val?`<button class="iconbtn danger" data-imgclear="${kind}" data-idx="${idx==null?'':idx}">حذف</button>`:''}
      </div>
    </div>`;
  }

  function render(){
    const s = slide();
    const tabs = cfg.slides.map((sl,i) => `<button class="tab${i===slideIdx?' on':''}" data-act="selSlide" data-idx="${i}">اسلاید ${i+1}</button>`).join('');

    const products = (s.products||[]).map((p,pi) => `
      <div class="card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
          <div style="width:36px;height:36px;border-radius:9px;flex-shrink:0;background:${(p.img||imgCache[slugOf(p.link)])?`url('${esc(p.img||imgCache[slugOf(p.link)])}') center/cover`:GRAD[p.color]||'#ccc'};box-shadow:0 0 0 1px #e3dadd;"></div>
          <input class="ci" value="${esc(p.name)}" data-p="name" data-idx="${pi}" placeholder="نام محصول" style="font-weight:500;">
          <div style="display:flex;gap:4px;">
            <button class="iconbtn" data-act="pUp" data-idx="${pi}">↑</button>
            <button class="iconbtn" data-act="pDown" data-idx="${pi}">↓</button>
            <button class="iconbtn danger" data-act="pRemove" data-idx="${pi}">✕</button>
          </div>
        </div>
        <div class="psearch-wrap" style="margin-bottom:10px;">
          <label class="cl">جستجوی محصول فروشگاه</label>
          <input class="ci psearch" data-idx="${pi}" placeholder="نام محصول را تایپ کنید…" autocomplete="off">
          <div class="psearch-list" data-idx="${pi}"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
          <div><label class="cl">ابعاد</label><input class="ci" value="${esc(p.dims)}" data-p="dims" data-idx="${pi}"></div>
          <div><label class="cl">کد</label><input class="ci" value="${esc(p.no)}" data-p="no" data-idx="${pi}"></div>
          <div><label class="cl">لینک</label><input class="ci" value="${esc(p.link)}" data-p="link" data-idx="${pi}" dir="ltr"></div>
        </div>
        <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;">
          ${imageField('تصویر محصول', p.img, 'product', pi)}
          <div><label class="cl">رنگ</label><div style="display:flex;gap:8px;">${swatches(p.color,'product',pi)}</div></div>
        </div>
      </div>`).join('');

    const floaters = (s.floaters||[]).map((f,fi) => `
      <div class="card" style="margin-bottom:10px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
          <div style="width:34px;height:34px;border-radius:50%;flex-shrink:0;background:${floaterBg(f)};box-shadow:0 0 0 1px #e3dadd;"></div>
          <span style="font-weight:600;font-size:13px;">شناور ${fi+1}</span>
          <button class="iconbtn danger" data-act="fRemove" data-idx="${fi}" style="margin-right:auto;">✕</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label class="cl">رنگ پالت برند</label>
            <div style="display:flex;gap:8px;">${swatches(f.color,'floater',fi)}</div>
          </div>
          <div>
            <label class="cl">رنگ آزاد (هگز) — اگر تنظیم شود پالت را نادیده می‌گیرد</label>
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="color" class="ci" style="width:54px;height:38px;" value="${validHex(f.color_hex)?f.color_hex:'#CC3333'}" data-f="color_hex" data-idx="${fi}">
              <input class="ci" style="flex:1;" value="${esc(f.color_hex||'')}" placeholder="مثال: #7C4DFF" data-f="color_hex" data-idx="${fi}" dir="ltr">
              ${f.color_hex?`<button class="iconbtn danger" data-act="fClearHex" data-idx="${fi}" title="پاک کردن رنگ آزاد">✕</button>`:''}
            </div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:10px;">
          <div><label class="cl">بالا (%)</label><input class="ci" type="number" min="0" max="95" step="1" value="${+(f.top??10)}" data-f-num="top" data-idx="${fi}"></div>
          <div><label class="cl">چپ (%)</label><input class="ci" type="number" min="0" max="95" step="1" value="${+(f.left??20)}" data-f-num="left" data-idx="${fi}"></div>
          <div><label class="cl">اندازه (px)</label><input class="ci" type="number" min="10" max="300" step="5" value="${+(f.size??70)}" data-f-num="size" data-idx="${fi}"></div>
          <div><label class="cl">بلور (px)</label><input class="ci" type="number" min="0" max="20" step="0.5" value="${+(f.blur??1)}" data-f-num="blur" data-idx="${fi}"></div>
        </div>
        <div><label class="cl">لینک</label><input class="ci" value="${esc(f.link)}" data-f="link" data-idx="${fi}" dir="ltr"></div>
      </div>`).join('');

    const bgColorVal = validHex(s.bg_color) ? s.bg_color : (s.accent==='dark'?'#282828':s.accent==='pink'?'#E0D5D9':'#CC3333');

    editor.innerHTML = `
      <div class="sect" style="margin-bottom:14px;">تنظیمات کلی</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div><label class="cl">خط برند</label><input class="ci" value="${esc(cfg.brandLine)}" data-g="brandLine"></div>
        <div><label class="cl">نام فروشگاه</label><input class="ci" value="${esc(cfg.storeTitle)}" data-g="storeTitle"></div>
        <div><label class="cl">لینک لوگو</label><input class="ci" value="${esc(cfg.logoLink)}" data-g="logoLink" dir="ltr"></div>
        <div><label class="cl">نقش‌مایه پس‌زمینه</label>
          <select class="ci" data-g="bgPattern">
            <option value="none" ${(cfg.bgPattern||'none')==='none'?'selected':''}>بدون نقش‌مایه</option>
            <option value="gate" ${cfg.bgPattern==='gate'?'selected':''}>نشان چیاکو (Gate)</option>
            <option value="quatrefoil" ${cfg.bgPattern==='quatrefoil'?'selected':''}>چهارپَر</option>
          </select>
        </div>
      </div>

      <div class="sect" style="margin-bottom:14px;">نوار پایین (لینک هر دکمه)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div><label class="cl">📏 اندازه‌ها</label><input class="ci" value="${esc((cfg.toolbar||{}).sizes||'')}" data-g="toolbar.sizes" dir="ltr" placeholder="#"></div>
        <div><label class="cl">✏️ ویرایش</label><input class="ci" value="${esc((cfg.toolbar||{}).edit||'')}" data-g="toolbar.edit" dir="ltr" placeholder="#"></div>
        <div><label class="cl">👤 حساب</label><input class="ci" value="${esc((cfg.toolbar||{}).account||'')}" data-g="toolbar.account" dir="ltr" placeholder="/account"></div>
        <div><label class="cl">➡️ دکمه فلش (CTA)</label><input class="ci" value="${esc((cfg.toolbar||{}).cta||'')}" data-g="toolbar.cta" dir="ltr" placeholder="خالی = CTA اسلاید اول"></div>
      </div>
      <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap;margin-bottom:8px;">
        <div style="flex:1;min-width:200px;">
          <label class="cl">زمان هر اسلاید — ${cfg.rotationSeconds} ثانیه</label>
          <input type="range" min="2" max="15" step="0.5" value="${cfg.rotationSeconds}" data-g-range="rotationSeconds" style="width:100%;accent-color:#CC3333;">
        </div>
        <div style="flex:1;min-width:200px;">
          <label class="cl">سرعت انیمیشن — ${cfg.animationSpeedMs??800} میلی‌ثانیه</label>
          <input type="range" min="200" max="2000" step="50" value="${cfg.animationSpeedMs??800}" data-g-range="animationSpeedMs" style="width:100%;accent-color:#CC3333;">
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;"><input type="checkbox" ${cfg.autoplay!==false?'checked':''} data-g-bool="autoplay" style="accent-color:#CC3333;width:17px;height:17px;">پخش خودکار</label>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;"><input type="checkbox" ${cfg.pauseOnHover!==false?'checked':''} data-g-bool="pauseOnHover" style="accent-color:#CC3333;width:17px;height:17px;">توقف با نشانگر</label>
      </div>

      <div style="height:1px;background:#e3dadd;margin:22px 0;"></div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div class="sect">اسلایدها</div>
        <button class="btn btn-ghost" data-act="addSlide" style="padding:7px 12px;">+ افزودن اسلاید</button>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">${tabs}</div>

      <div class="card" style="padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
          <span style="font-weight:600;font-size:15px;">اسلاید ${slideIdx+1} از ${cfg.slides.length}</span>
          <button class="iconbtn danger" data-act="removeSlide" style="width:auto;padding:0 10px;">حذف اسلاید</button>
        </div>
        <div style="margin-bottom:14px;"><label class="cl">عنوان (هر خط = یک سطر)</label><textarea class="ci" rows="2" data-s="headline" style="resize:vertical;font-weight:500;">${esc(s.headline)}</textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
          <div><label class="cl">لینک دکمه (CTA)</label><input class="ci" value="${esc(s.ctaLink)}" data-s="ctaLink" dir="ltr"></div>
          ${imageField('تصویر هیرو (اختیاری)', s.heroImg, 'hero', null)}
        </div>
        <div style="background:#f4eff1;border-radius:10px;padding:14px;margin-bottom:14px;">
          <div style="font-weight:600;font-size:12px;color:#CC3333;margin-bottom:12px;letter-spacing:.06em;">موقعیت و اندازه تصویر هیرو</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div>
              <label class="cl">از بالا — ${+(s.hero_top??5)}%</label>
              <input type="range" min="0" max="80" step="1" value="${+(s.hero_top??5)}" data-s-num="hero_top" style="width:100%;accent-color:#CC3333;">
            </div>
            <div>
              <label class="cl">از چپ — ${+(s.hero_left??24)}%</label>
              <input type="range" min="0" max="70" step="1" value="${+(s.hero_left??24)}" data-s-num="hero_left" style="width:100%;accent-color:#CC3333;">
            </div>
            <div>
              <label class="cl">عرض تصویر — ${+(s.hero_width??400)}px</label>
              <input type="range" min="80" max="700" step="10" value="${+(s.hero_width??400)}" data-s-num="hero_width" style="width:100%;accent-color:#CC3333;">
            </div>
          </div>
        </div>
        <div style="margin-bottom:14px;">
          <label class="cl">رنگ پس‌زمینه اسلاید</label>
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="display:flex;gap:8px;">${swatches(s.accent,'accent',null)}</div>
            <span style="font-size:11px;color:#bdb3b7;">یا رنگ آزاد (هگز):</span>
            <input type="color" class="ci" style="width:54px;height:38px;" value="${bgColorVal}" data-s="bg_color">
            <input class="ci" style="width:110px;" value="${esc(s.bg_color||'')}" placeholder="#CC3333" data-s="bg_color" dir="ltr">
            ${s.bg_color?`<button class="iconbtn danger" data-act="clearBgColor" title="پاک کردن رنگ آزاد">✕</button>`:''}
          </div>
          <div style="font-size:11px;color:#9b9094;margin-top:5px;">رنگ آزاد اولویت دارد. پالت برند فقط وقتی رنگ آزاد خالی باشد اعمال می‌شود.</div>
        </div>
        <div><label class="cl">حالت موشن (انیمیشن جابه‌جایی اسلاید)</label>
          <select class="ci" data-s="motion">
            <option value="drift"${(s.motion||'drift')==='drift'?' selected':''}>نرم — مثل ویدیو</option>
            <option value="slide"${s.motion==='slide'?' selected':''}>کشویی</option>
            <option value="fade"${s.motion==='fade'?' selected':''}>محو</option>
            <option value="zoom"${s.motion==='zoom'?' selected':''}>زوم</option>
          </select>
        </div>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin:24px 0 12px;">
        <div class="sect">محصولات</div>
        <button class="btn btn-ghost" data-act="addProduct" style="padding:7px 12px;">+ افزودن محصول</button>
      </div>
      ${products}

      <div style="display:flex;align-items:center;justify-content:space-between;margin:24px 0 12px;">
        <div class="sect">اشکال شناور (تا ۴ شناور)</div>
        <button class="btn btn-ghost" data-act="addFloater" style="padding:7px 12px;">+ افزودن شناور</button>
      </div>
      <div style="font-size:11px;color:#9b9094;margin-bottom:12px;">موقعیت (بالا/چپ) بر حسب درصد از گوشه بالا-چپ پنل رنگی. اندازه و بلور به پیکسل.</div>
      ${floaters}
    `;
    resolveImages();
  }

  // Fetch current catalog photos for linked products without a stored image,
  // so the editor thumbnails match the live hero. Each slug is fetched once.
  function resolveImages(){
    const need = [];
    (cfg.slides||[]).forEach(sl => (sl.products||[]).forEach(p => {
      const sg = slugOf(p.link);
      if (sg && !p.img && !(sg in imgCache)) { imgCache[sg] = ''; need.push(sg); }
    }));
    if (!need.length) return;
    fetch(ROUTES.productImages + '?slugs=' + encodeURIComponent([...new Set(need)].join(',')))
      .then(r => r.json()).then(j => { Object.assign(imgCache, j.images || {}); render(); })
      .catch(() => {});
  }

  // Set a config key, supporting one level of nesting via "parent.child".
  function setG(key, val){
    if (key.indexOf('.') > -1){ const p = key.split('.'); cfg[p[0]] = cfg[p[0]] || {}; cfg[p[0]][p[1]] = val; }
    else cfg[key] = val;
  }

  // ---- input (text/select) ----
  editor.addEventListener('input', (e) => {
    const t = e.target;
    if (t.dataset.g)       { setG(t.dataset.g, t.value); return persist(); }
    if (t.dataset.gRange)  { cfg[t.dataset.gRange] = parseFloat(t.value); render(); return persist(); }
    if (t.dataset.s)       { slide()[t.dataset.s] = t.value; return persist(); }
    if (t.dataset.sNum)    { slide()[t.dataset.sNum] = parseFloat(t.value); render(); return persist(); }
    if (t.dataset.p)       { slide().products[+t.dataset.idx][t.dataset.p] = t.value; return persist(); }
    if (t.dataset.f)       { slide().floaters[+t.dataset.idx][t.dataset.f] = t.value; render(); return persist(); }
    if (t.dataset.fNum)    { slide().floaters[+t.dataset.idx][t.dataset.fNum] = parseFloat(t.value); return persist(); }
    if (t.classList.contains('psearch')){ return searchProducts(t); }
  });
  editor.addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset.g)     { setG(t.dataset.g, t.value); return persist(); }
    if (t.dataset.gBool) { cfg[t.dataset.gBool] = t.checked; return persist(); }
    if (t.dataset.s)     { slide()[t.dataset.s] = t.value; return persist(); }
    if (t.dataset.f)     { slide().floaters[+t.dataset.idx][t.dataset.f] = t.value; render(); return persist(); }
    if (t.classList.contains('img-file')){ return uploadImage(t); }
  });

  // ---- clicks ----
  editor.addEventListener('click', (e) => {
    const sw = e.target.closest('[data-swatch]');
    if (sw){
      const kind = sw.dataset.swatch, idx = sw.dataset.idx, color = sw.dataset.color;
      if (kind === 'accent') slide().accent = color;
      if (kind === 'product') slide().products[+idx].color = color;
      if (kind === 'floater') slide().floaters[+idx].color = color;
      render(); return persist();
    }
    const clr = e.target.closest('[data-imgclear]');
    if (clr){
      const kind = clr.dataset.imgclear, idx = clr.dataset.idx;
      if (kind === 'hero') slide().heroImg = '';
      if (kind === 'product') slide().products[+idx].img = '';
      render(); return persist();
    }
    const opt = e.target.closest('.psearch-opt');
    if (opt){
      const pi = +opt.dataset.idx, p = slide().products[pi];
      p.name = opt.dataset.name; p.link = opt.dataset.link; if (opt.dataset.img) p.img = opt.dataset.img;
      render(); return persist();
    }
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const act = b.dataset.act, idx = +b.dataset.idx;
    if (act === 'selSlide')    { slideIdx = idx; return render(); }
    if (act === 'addSlide')    { cfg.slides.push(newSlide()); slideIdx = cfg.slides.length-1; render(); return persist(); }
    if (act === 'removeSlide') { if (cfg.slides.length>1){ cfg.slides.splice(slideIdx,1); slideIdx=0; render(); persist(); } return; }
    if (act === 'addProduct')  { slide().products.push(newProduct(slide().products.length)); render(); return persist(); }
    if (act === 'pRemove')     { slide().products.splice(idx,1); render(); return persist(); }
    if (act === 'pUp')         { const a=slide().products; if(idx>0){ [a[idx-1],a[idx]]=[a[idx],a[idx-1]]; render(); persist(); } return; }
    if (act === 'pDown')       { const a=slide().products; if(idx<a.length-1){ [a[idx+1],a[idx]]=[a[idx],a[idx+1]]; render(); persist(); } return; }
    if (act === 'addFloater')  { if((slide().floaters||[]).length<4){ slide().floaters.push(newFloater()); render(); persist(); } return; }
    if (act === 'fRemove')     { slide().floaters.splice(idx,1); render(); return persist(); }
    if (act === 'fClearHex')   { slide().floaters[idx].color_hex = ''; render(); return persist(); }
    if (act === 'clearBgColor'){ slide().bg_color = ''; render(); return persist(); }
  });

  document.getElementById('btn-reset').addEventListener('click', () => {
    if (!confirm('بنر به حالت پیش‌فرض بازگردد؟')) return;
    fetch(ROUTES.reset, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF} })
      .then(r => r.json()).then(j => { cfg = j.config; slideIdx = 0; render(); reloadPreview(); status('بازنشانی شد'); });
  });

  function newProduct(n){ return { name:'محصول جدید', dims:'۱۱۰ × ۱۱۰', no:'0'+(n+1), color:'red', img:'', link:'#' }; }
  function newFloater(){
    const colors = ['red','dark','pink'];
    const n = (slide().floaters||[]).length;
    const tops  = [6,44,12,55], lefts = [30,16,8,40], sizes = [70,96,34,56];
    return { color: colors[n%3], color_hex:'', link:'#', top: tops[n]||10, left: lefts[n]||20, size: sizes[n]||60, blur: 1.0 };
  }
  function newSlide(){
    return { headline:'اسلاید\nجدید', accent:'red', bg_color:'', motion:'drift', ctaLink:'#', heroImg:'', hero_top:5, hero_left:24, hero_width:400,
      floaters:[
        {color:'dark', color_hex:'', link:'#', top:6,  left:30, size:70, blur:1.2},
        {color:'pink', color_hex:'', link:'#', top:44, left:16, size:96, blur:1.0},
        {color:'red',  color_hex:'', link:'#', top:12, left:8,  size:34, blur:0.6},
      ],
      products:[newProduct(0)] };
  }

  function uploadImage(input){
    const file = input.files[0]; if (!file) return;
    const field = input.closest('.img-field'); const kind = field.dataset.imgkind; const idx = field.dataset.idx;
    const fd = new FormData(); fd.append('file', file);
    field.querySelector('.img-prev').style.opacity = '.5';
    fetch(ROUTES.upload, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF}, body:fd })
      .then(r => r.json()).then(j => {
        const url = j.url || '';
        if (kind === 'hero') slide().heroImg = url;
        if (kind === 'product') slide().products[+idx].img = url;
        render(); persist();
      }).catch(() => { alert('بارگذاری ناموفق بود.'); field.querySelector('.img-prev').style.opacity = '1'; });
  }

  let searchTimer;
  function searchProducts(input){
    const pi = input.dataset.idx;
    const list = editor.querySelector(`.psearch-list[data-idx="${pi}"]`);
    const q = input.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      fetch(ROUTES.products + '?q=' + encodeURIComponent(q))
        .then(r => r.json()).then(j => {
          list.innerHTML = (j.results||[]).map(r =>
            `<button type="button" class="psearch-opt" data-idx="${pi}" data-name="${esc(r.name)}" data-link="${esc(r.link)}" data-img="${esc(r.img)}">
               <img src="${r.img||''}" onerror="this.style.visibility='hidden'"><span>${esc(r.name)}</span></button>`
          ).join('') || '<div style="padding:10px;color:#9b9094;font-size:13px;">موردی یافت نشد</div>';
          list.classList.add('show');
        });
    }, 200);
  }
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.psearch-wrap')) editor.querySelectorAll('.psearch-list').forEach(l => l.classList.remove('show'));
  });

  render();
})();
</script>
</body>
</html>
