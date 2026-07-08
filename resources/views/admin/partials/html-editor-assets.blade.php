{{--
    Shared assets (styles + behaviour) for the dual-view HTML editor. Split out of
    html-editor.blade.php so a host page can render them ONCE in the real DOM
    (outside any <template>), then drop as many editors as it likes — including
    ones cloned in at runtime, re-initialised via window.rteScan().
--}}
@once
    <style>
        [data-rte] { direction: rtl; }
        [data-rte-visual] { min-height: 16rem; line-height: 2; }
        [data-rte-visual]:empty:before { content: attr(data-placeholder); color: #9ca3af; }
        [data-rte-visual] h1 { font-size: 1.5rem; font-weight: 700; margin: .6em 0 .3em; }
        [data-rte-visual] h2 { font-size: 1.25rem; font-weight: 700; margin: .6em 0 .3em; }
        [data-rte-visual] h3 { font-size: 1.1rem; font-weight: 700; margin: .5em 0 .3em; }
        [data-rte-visual] ul { list-style: disc; padding-inline-start: 1.5rem; }
        [data-rte-visual] ol { list-style: decimal; padding-inline-start: 1.5rem; }
        [data-rte-visual] a { color: #2563eb; text-decoration: underline; }
        [data-rte-visual] img { max-width: 100%; height: auto; border-radius: .5rem; }
        [data-rte-visual] blockquote { border-inline-start: 3px solid #d1d5db; padding-inline-start: .75rem; color: #6b7280; margin: .5em 0; }
        [data-rte-visual] pre { background: #f3f4f6; padding: .6rem .8rem; border-radius: .5rem; overflow-x: auto; font-family: monospace; font-size: .85em; }
        [data-rte-visual] code { background: #f3f4f6; padding: .1em .3em; border-radius: .25rem; font-family: monospace; font-size: .9em; }
        [data-rte-visual] table { border-collapse: collapse; width: 100%; margin: .5em 0; }
        [data-rte-visual] table td, [data-rte-visual] table th { border: 1px solid #d1d5db; padding: .4rem .6rem; }
        [data-rte-visual] hr { border: 0; border-top: 1px solid #e5e7eb; margin: 1em 0; }
        [data-rte-btn] { width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .375rem; color: #475569; cursor: pointer; font-size: .8rem; }
        [data-rte-btn]:hover { background: #e2e8f0; }
        [data-rte-btn].is-active { background: #cbd5e1; color: #0f172a; }
        [data-rte-sep] { width: 1px; height: 1.25rem; background: #cbd5e1; margin: 0 .25rem; }
    </style>
    <script>
        (function () {
            if (window.__rteInit) return;
            window.__rteInit = true;

            const I = (p) => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
            const ICON = {
                undo: I('<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/>'),
                redo: I('<path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/>'),
                ul: I('<line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>'),
                ol: I('<line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="2" y="8" font-size="7" stroke-width="1">1</text><text x="2" y="14" font-size="7" stroke-width="1">2</text><text x="2" y="20" font-size="7" stroke-width="1">3</text>'),
                right: I('<line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/>'),
                center: I('<line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>'),
                left: I('<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/>'),
                justify: I('<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>'),
                link: I('<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>'),
                unlink: I('<path d="M16 7l3-3a5 5 0 0 1 5 5M8 17l-3 3"/><line x1="2" y1="2" x2="22" y2="22"/>'),
                image: I('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>'),
                table: I('<rect x="3" y="3" width="18" height="18" rx="1"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="12" y1="3" x2="12" y2="21"/>'),
                quote: I('<path d="M7 7h4v6H7zM13 7h4v6h-4z"/>'),
                code: I('<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>'),
                hr: I('<line x1="3" y1="12" x2="21" y2="12"/>'),
                clear: I('<path d="M4 7h16M9 7l1 13h4l1-13"/><line x1="3" y1="3" x2="21" y2="21"/>'),
                color: I('<path d="M12 3l4 9H8z"/><line x1="6" y1="20" x2="18" y2="20"/>'),
                fill: I('<path d="M3 11l8-8 9 9-8 8z"/><line x1="3" y1="21" x2="21" y2="21"/>'),
            };

            const TOOLS = [
                { cmd: 'undo', icon: ICON.undo, t: 'برگردان' },
                { cmd: 'redo', icon: ICON.redo, t: 'تکرار' },
                { sep: 1 },
                { block: 1 },
                { sep: 1 },
                { cmd: 'bold', label: 'B', cls: 'font-bold', t: 'درشت' },
                { cmd: 'italic', label: 'I', cls: 'italic', t: 'مورب' },
                { cmd: 'underline', label: 'U', cls: 'underline', t: 'زیرخط' },
                { cmd: 'strikeThrough', label: 'S', cls: 'line-through', t: 'خط‌خورده' },
                { color: 'foreColor', icon: ICON.color, t: 'رنگ متن' },
                { color: 'hiliteColor', icon: ICON.fill, t: 'رنگ زمینه' },
                { sep: 1 },
                { cmd: 'insertUnorderedList', icon: ICON.ul, t: 'فهرست نقطه‌ای' },
                { cmd: 'insertOrderedList', icon: ICON.ol, t: 'فهرست شماره‌دار' },
                { sep: 1 },
                { cmd: 'justifyRight', icon: ICON.right, t: 'راست‌چین' },
                { cmd: 'justifyCenter', icon: ICON.center, t: 'وسط‌چین' },
                { cmd: 'justifyLeft', icon: ICON.left, t: 'چپ‌چین' },
                { cmd: 'justifyFull', icon: ICON.justify, t: 'هم‌تراز' },
                { dir: 'rtl', label: 'RTL', t: 'راست به چپ' },
                { dir: 'ltr', label: 'LTR', t: 'چپ به راست' },
                { sep: 1 },
                { act: 'link', icon: ICON.link, t: 'پیوند' },
                { cmd: 'unlink', icon: ICON.unlink, t: 'حذف پیوند' },
                { act: 'image', icon: ICON.image, t: 'تصویر' },
                { act: 'table', icon: ICON.table, t: 'جدول' },
                { cmd: 'formatBlock', arg: 'blockquote', icon: ICON.quote, t: 'نقل‌قول' },
                { act: 'code', icon: ICON.code, t: 'کد' },
                { cmd: 'insertHorizontalRule', icon: ICON.hr, t: 'خط جداکننده' },
                { cmd: 'removeFormat', icon: ICON.clear, t: 'پاک‌کردن قالب' },
            ];

            const btn = (html, title) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('data-rte-btn', '');
                if (title) b.title = title;
                b.innerHTML = html;
                // Keep the editor selection when a toolbar button is pressed.
                b.addEventListener('mousedown', (e) => e.preventDefault());
                return b;
            };

            function exec(cmd, arg) {
                document.execCommand(cmd, false, arg || null);
            }

            function applyDir(visual, dir) {
                const sel = window.getSelection();
                let node = sel && sel.anchorNode ? sel.anchorNode : visual;
                if (node.nodeType === 3) node = node.parentNode;
                while (node && node !== visual && node.parentNode !== visual) node = node.parentNode;
                (node && node !== visual ? node : visual).setAttribute('dir', dir);
            }

            function insertTable() {
                const rows = parseInt(prompt('تعداد سطر؟', '2') || '0', 10);
                const cols = parseInt(prompt('تعداد ستون؟', '2') || '0', 10);
                if (rows < 1 || cols < 1) return;
                let html = '<table>';
                for (let r = 0; r < rows; r++) {
                    html += '<tr>';
                    for (let c = 0; c < cols; c++) html += '<td>&nbsp;</td>';
                    html += '</tr>';
                }
                html += '</table><p><br></p>';
                exec('insertHTML', html);
            }

            function build(wrap) {
                const visual = wrap.querySelector('[data-rte-visual]');
                const source = wrap.querySelector('[data-rte-source]');
                const toolbar = wrap.querySelector('[data-rte-toolbar]');
                const form = wrap.closest('form');

                // Load existing HTML into the visual pane.
                visual.innerHTML = source.value;
                const sync = () => { source.value = visual.innerHTML; };

                TOOLS.forEach((tool) => {
                    if (tool.sep) {
                        const s = document.createElement('span');
                        s.setAttribute('data-rte-sep', '');
                        toolbar.appendChild(s);
                        return;
                    }
                    if (tool.block) {
                        const sel = document.createElement('select');
                        sel.setAttribute('data-rte-btn', '');
                        sel.style.width = 'auto';
                        sel.title = 'سبک پاراگراف';
                        sel.innerHTML = '<option value="p">متن معمولی</option><option value="h1">تیتر بزرگ</option><option value="h2">تیتر متوسط</option><option value="h3">تیتر کوچک</option>';
                        sel.addEventListener('mousedown', (e) => e.stopPropagation());
                        sel.addEventListener('change', () => { visual.focus(); exec('formatBlock', sel.value); sync(); });
                        toolbar.appendChild(sel);
                        return;
                    }
                    const b = btn(tool.icon || ('<span class="' + (tool.cls || '') + '">' + tool.label + '</span>'), tool.t);
                    b.addEventListener('click', () => {
                        visual.focus();
                        if (tool.color) {
                            const inp = document.createElement('input');
                            inp.type = 'color';
                            inp.addEventListener('input', () => {
                                visual.focus();
                                exec('styleWithCSS', true);
                                exec(tool.color, inp.value);
                                exec('styleWithCSS', false);
                                sync();
                            });
                            inp.click();
                        } else if (tool.dir) {
                            applyDir(visual, tool.dir);
                        } else if (tool.act === 'link') {
                            const url = prompt('آدرس پیوند (https://...)', 'https://');
                            if (url) exec('createLink', url);
                        } else if (tool.act === 'image') {
                            const url = prompt('آدرس تصویر (https://...)', 'https://');
                            if (url) exec('insertImage', url);
                        } else if (tool.act === 'table') {
                            insertTable();
                        } else if (tool.act === 'code') {
                            const sel = window.getSelection();
                            const txt = sel && sel.toString();
                            exec('insertHTML', '<code>' + (txt || 'کد') + '</code>');
                        } else if (tool.cmd === 'formatBlock') {
                            exec('formatBlock', tool.arg);
                        } else {
                            exec(tool.cmd);
                        }
                        sync();
                    });
                    toolbar.appendChild(b);
                });

                // Mode toggle (visual ⇄ HTML), pushed to the far side.
                const modes = document.createElement('div');
                modes.style.marginInlineStart = 'auto';
                modes.className = 'flex items-center gap-0.5';
                const mkMode = (label, mode) => {
                    const m = document.createElement('button');
                    m.type = 'button';
                    m.textContent = label;
                    m.className = 'rounded-md px-2 py-1 text-xs font-medium';
                    m.dataset.mode = mode;
                    m.addEventListener('click', () => setMode(mode));
                    return m;
                };
                const vBtn = mkMode('بصری', 'visual');
                const hBtn = mkMode('کد HTML', 'html');
                modes.append(vBtn, hBtn);
                toolbar.appendChild(modes);

                function setMode(mode) {
                    if (mode === 'html') {
                        sync();
                        visual.classList.add('hidden');
                        source.classList.remove('hidden');
                    } else {
                        visual.innerHTML = source.value;
                        source.classList.add('hidden');
                        visual.classList.remove('hidden');
                    }
                    vBtn.classList.toggle('is-active', mode === 'visual');
                    hBtn.classList.toggle('is-active', mode === 'html');
                }

                visual.addEventListener('input', sync);
                source.addEventListener('input', () => { /* source is the field; nothing to mirror until toggle */ });
                if (form) form.addEventListener('submit', () => { if (!visual.classList.contains('hidden')) sync(); });

                setMode('visual');
            }

            function scan() {
                document.querySelectorAll('[data-rte]:not([data-rte-ready])').forEach((w) => {
                    w.setAttribute('data-rte-ready', '');
                    build(w);
                });
            }

            // Exposed so hosts that add editors at runtime (e.g. the page builder
            // cloning block templates) can initialise the new ones.
            window.rteScan = scan;

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', scan);
            } else {
                scan();
            }
        })();
    </script>
@endonce
