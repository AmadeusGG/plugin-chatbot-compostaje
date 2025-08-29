<?php
/*
Plugin Name: Compostaje Kids GPT
Description: Asistente de compostaje para peques. Shortcode: [compostaje_gpt]
Version: 1.0
Author: Amadeo
*/

if (!defined('ABSPATH')) exit;

// Detect pages where the shortcode is used
function ck_gpt_has_shortcode_page(){
    if (!is_singular()) return false;
    $post = get_post();
    return $post && has_shortcode($post->post_content, 'compostaje_gpt');
}

/* =========================
 *  FRONTEND ASSETS
 * ========================= */
add_action('wp_enqueue_scripts', function(){
    if (!ck_gpt_has_shortcode_page()) return;

    global $wp_scripts, $wp_styles;

    if ($wp_scripts){
        foreach ($wp_scripts->queue as $handle){
            wp_dequeue_script($handle);
        }
    }

    if ($wp_styles){
        foreach ($wp_styles->queue as $handle){
            wp_dequeue_style($handle);
        }
    }

    if (function_exists('googlesitekit_enqueue_gtag')) {
        googlesitekit_enqueue_gtag();
    } else {
        do_action('googlesitekit_enqueue_gtag');
    }
}, PHP_INT_MAX);

// Ensure a gtag() stub exists early so events can queue before the GA script loads.
add_action('wp_head', function(){
    if (!ck_gpt_has_shortcode_page()) return;
    echo "<script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};</script>";
}, 0);

/* =========================
 *  ADMIN MENU & SETTINGS
 * ========================= */
add_action('admin_menu', function() {
    add_menu_page('Compostaje Kids GPT', 'Compostaje Kids GPT', 'manage_options', 'compostaje-kids-gpt', 'ck_gpt_settings_page', 'dashicons-format-chat');
    add_submenu_page('compostaje-kids-gpt', 'Ajustes', 'Ajustes', 'manage_options', 'compostaje-kids-gpt', 'ck_gpt_settings_page');
    add_submenu_page('compostaje-kids-gpt', 'Shortcode', 'Shortcode', 'manage_options', 'compostaje-kids-gpt-shortcode', 'ck_gpt_shortcode_page');
});

add_action('admin_init', function() {
    register_setting('ck_gpt_options', 'ck_gpt_api_key');
    register_setting('ck_gpt_options', 'ck_gpt_logo');
    register_setting('ck_gpt_options', 'ck_gpt_model');
    register_setting('ck_gpt_options', 'ck_gpt_theme'); // light | dark | auto
});

function ck_gpt_settings_page() {
    $api     = esc_attr(get_option('ck_gpt_api_key'));
    $logo    = esc_attr(get_option('ck_gpt_logo'));
    $model   = esc_attr(get_option('ck_gpt_model', 'gpt-4o-mini'));
    $theme   = esc_attr(get_option('ck_gpt_theme', 'light')); ?>
    <div class="wrap">
        <h1>Compostaje Kids GPT — Ajustes</h1>
        <form method="post" action="options.php">
            <?php settings_fields('ck_gpt_options'); do_settings_sections('ck_gpt_options'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">API Key OpenAI</th>
                    <td><input type="password" name="ck_gpt_api_key" value="<?php echo $api; ?>" style="width:420px;" placeholder="sk-..."></td>
                </tr>
                <tr>
                    <th scope="row">Logo (URL)</th>
                    <td><input type="text" name="ck_gpt_logo" value="<?php echo $logo; ?>" style="width:420px;" placeholder="https://.../logo.png"></td>
                </tr>
                <tr>
                    <th scope="row">Modelo</th>
                    <td>
                        <input type="text" name="ck_gpt_model" value="<?php echo $model; ?>" style="width:420px;" placeholder="gpt-4o-mini">
                        <p class="description">Modelo de la API de OpenAI (ej.: <code>gpt-4o-mini</code>, <code>gpt-4.1-mini</code>). Debe existir en la API.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tema visual</th>
                    <td>
                        <select name="ck_gpt_theme">
                            <?php
                            $opts = ['light'=>'Claro (forzado)','dark'=>'Oscuro (forzado)','auto'=>'Automático (según el sistema)'];
                            $current = $theme ?: 'light';
                            foreach($opts as $val=>$label){
                                echo '<option value="'.esc_attr($val).'" '.selected($current,$val,false).'>'.esc_html($label).'</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Si tienes problemas en móvil con fondos oscuros, deja <strong>Claro (forzado)</strong>.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php }

function ck_gpt_shortcode_page() { ?>
    <div class="wrap">
        <h1>Shortcode</h1>
        <p>Inserta este shortcode en cualquier página o entrada donde quieras mostrar el chat:</p>
        <pre style="font-size:16px;padding:12px;background:#fff;border:1px solid #ccc;border-radius:6px;">[compostaje_gpt]</pre>
        <p>Recomendación: crea una página “Agente IA” y pega el shortcode en el bloque “Código corto”.</p>
    </div>
<?php }

/* =========================
 *  FRONTEND (SHORTCODE) — Shadow DOM aislado
 * ========================= */
add_shortcode('compostaje_gpt', function() {
    ob_start();
    $logo   = esc_attr(get_option('ck_gpt_logo'));
    $ajax   = esc_js(admin_url('admin-ajax.php?action=ck_gpt_chat'));
    $theme  = esc_attr(get_option('ck_gpt_theme','light')); ?>
<div id="ck-gpt-mount"
     data-logo="<?php echo $logo; ?>"
     data-ajax="<?php echo $ajax; ?>"
     data-theme="<?php echo $theme ? $theme : 'light'; ?>"
     style="display:block;contain:content;position:relative;z-index:1;"></div>

<script>
(function(){
  const mount = document.getElementById('ck-gpt-mount');
  if (!mount) return;

  const clearThirdParty = () => {
    document.querySelectorAll('script[src*="translate"],link[href*="translate"]').forEach(el => el.remove());
    document.querySelectorAll('[id*="translate"],[class*="translate"]').forEach(el => el.remove());
  };
  clearThirdParty();
  new MutationObserver(clearThirdParty).observe(document.documentElement,{childList:true,subtree:true});

  const ajaxUrl   = mount.getAttribute('data-ajax');
  const logoUrl   = mount.getAttribute('data-logo') || '';
  const themeOpt  = (mount.getAttribute('data-theme') || 'light').toLowerCase();
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:url(https://consultoriainformatica.net/wp-content/uploads/2025/08/Chatbot-en-el-Jardin-Compostero.jpg) center/cover no-repeat;display:flex;justify-content:center;align-items:center;';
  document.body.innerHTML = '';
  document.documentElement.style.height = '100%';
  document.body.style.height = '100%';
  document.body.style.margin = '0';
  document.body.appendChild(overlay);

  const host = document.createElement('div');
  host.style.cssText = 'position:relative;width:90vw;height:90vh;max-width:1400px;max-height:900px;';
  if (window.matchMedia('(min-width:600px)').matches) {
    host.style.borderRadius = '12px';
    host.style.boxShadow = '0 8px 24px rgba(0,0,0,.12)';
    host.style.overflow = 'hidden';
  }
  overlay.appendChild(host);
  const root = host.attachShadow({mode:'open'});

  const metaViewport = document.querySelector('meta[name="viewport"]');
  if (metaViewport) {
    metaViewport.setAttribute('content','width=device-width,initial-scale=1,maximum-scale=1');
  }

  const css = `
  :host{ all: initial; color-scheme: light; } /* forzar controles claros por defecto */
  *,*::before,*::after{ box-sizing: border-box; }
  :host{
    font-family: 'Comic Sans MS','Comic Sans',cursive,sans-serif;
    color:#0f172a;
    --bd:#ffd1dc; --mut:#fff0f5; --mut2:#fffbcc; --pri:#ff6b6b;
    --ai:#fff9c4; --ai-b:#ffe58f; --us:#b3e5fc; --us-b:#81d4fa;
    --chip:#e1f5fe; --chip-b:#b3e5fc; --chip-text:#0f172a;
  }
  .wrap{ position:absolute; inset:0; display:flex; flex-direction:column; width:100%; height:100%; margin:0; border:none; border-radius:0; overflow:hidden; background:#fff; box-shadow:none; opacity:0.9; }
  .header{ position:relative; text-align:center; padding:18px 20px; background:var(--mut); border-bottom:1px solid var(--bd); }
  .header img{ max-height:80px; margin:0 auto 12px; display:block; }
  .title{ margin:4px 0 2px; font-size: clamp(26px,5vw,40px); font-weight:800; }
  .desc{ margin:0; font-size: clamp(18px,3vw,26px); color:#4b5563; }
  .chips{ display:flex; gap:12px; flex-wrap:wrap; justify-content:center; padding:12px; background:var(--mut2); border-bottom:1px solid #eef2f7; overflow-x:auto; scroll-snap-type:x mandatory; }
  .chip{ scroll-snap-align:start; padding:10px 16px; border-radius:999px; border:1px solid var(--chip-b); background:var(--chip); cursor:pointer; font-size:clamp(18px,2.8vw,24px); color:var(--chip-text); white-space:nowrap; box-shadow:0 2px 0 rgba(0,0,0,.02); transition: background .15s,border-color .15s,transform .08s }
  .chip:hover{ background:#eef2ff; border-color:#c7d2fe; }
  .chip:active{ transform: translateY(1px); }
  .chip[disabled]{ opacity:.5; cursor:not-allowed; }
  .msgs{ flex:1; overflow-y:auto; padding:20px 24px; background:#fff; }
  .row{ display:flex; margin:6px 0; }
  .row.user{ justify-content:flex-end; }
  .bubble{ max-width:90%; padding:16px 18px; border-radius:20px; line-height:1.6; white-space:pre-wrap; word-wrap:break-word; font-size:clamp(18px,2.5vw,24px); }
  .row.user .bubble{ background:var(--us); border:1px solid var(--us-b); }
  .row.ai .bubble{ background:var(--ai); border:1px solid var(--ai-b); }
  .input{ display:flex; gap:12px; padding:16px 20px; border-top:1px solid var(--bd); background:#ffffff; position:sticky; bottom:0; left:0; right:0; }
  .field{ flex:1; padding:16px 20px; border:1px solid #d1d5db; border-radius:16px; font-size:20px; outline:none; background:#fff; color:#0f172a; }
  .field::placeholder{ color:#9aa3ae; }
  .field:focus{ border-color:#93c5fd; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  .send{ width:56px; min-width:56px; height:56px; display:flex; align-items:center; justify-content:center; border:none; border-radius:16px;
         background:var(--pri); color:#fff; cursor:pointer; box-shadow: 0 1px 0 rgba(0,0,0,.12), inset 0 0 0 1px rgba(255,255,255,.2); }
  .send:hover{ filter: brightness(1.08); }
  .send[disabled]{ opacity:.6; cursor:not-allowed; }
  .send svg{ width:28px; height:28px; display:block; fill:currentColor; filter: drop-shadow(0 1px 0 rgba(0,0,0,.45)); } /* visible siempre */
    .send svg path{ stroke: rgba(0,0,0,.55); stroke-width: .6px; }
    .mic{ width:56px; min-width:56px; height:56px; display:flex; align-items:center; justify-content:center; border:none; border-radius:16px; background:#34d399; color:#fff; cursor:pointer; box-shadow: 0 1px 0 rgba(0,0,0,.12), inset 0 0 0 1px rgba(255,255,255,.2); }
    .mic:hover{ filter: brightness(1.08); }
    .mic.active{ background:#dc2626; }
    .mic svg{ width:28px; height:28px; display:block; fill:currentColor; filter: drop-shadow(0 1px 0 rgba(0,0,0,.45)); }
    .mic[disabled]{ opacity:.6; cursor:not-allowed; }
    .typing{ display:inline-flex; align-items:center; gap:4px; }
  .dot{ width:6px; height:6px; border-radius:50%; background:#606770; opacity:.4; animation:blink 1.2s infinite; }
  .dot:nth-child(2){ animation-delay:.2s; } .dot:nth-child(3){ animation-delay:.4s; }
  @keyframes blink{ 0%,80%,100%{opacity:.2} 40%{opacity:1} }
  @media (max-width:560px){
    .chips{ justify-content:flex-start; padding:10px 8px; }
  }
  .input{ padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
  `;

  // Dark theme overrides only if themeOpt == 'dark' OR (themeOpt=='auto' && prefers dark)
  const darkCSS = `
  :host{
    color-scheme: dark;
    --bd:#2b2f36; --mut:#101318; --mut2:#0c0f14; --ai:#141922; --ai-b:#1f2430;
    --us:#0f2540; --us-b:#15365c; --chip:#0f1420; --chip-b:#2c3444; --chip-text:#e5e7eb;
    color:#e5e7eb;
  }
  .wrap{ background:#0b0f14; box-shadow:none; }
  .desc{ color:#b3b8c2; }
  .field{ background:#0e131a; color:#e6edf5; border-color:#293241; }
  .field::placeholder{ color:#8b93a1; }
  .input{ background:#0b0f14; }
  .send{ background:var(--pri); color:#fff; }
  `;

  // Build base HTML
  const html = `
    <div class="wrap">
      <div class="header">
        ${logoUrl ? `<img src="${logoUrl}" alt="Compostaje CEBAS para peques">` : ''}
        <div class="title">Compostaje CEBAS Kids</div>
        <p class="desc">Un rincón mágico del CEBAS-CSIC donde aprendemos a compostar como en un cuento.</p>
      </div>
      <div class="chips" id="chips">
        <button class="chip" data-q="¿Qué cositas puedo echar en mi compostera mágica?">¿Qué cositas puedo echar en mi compostera mágica?</button>
        <button class="chip" data-q="¿Por qué el compost hace feliz al huerto?">¿Por qué el compost hace feliz al huerto?</button>
        <button class="chip" data-q="¿Cuánto tarda la poción del compost?">¿Cuánto tarda la poción del compost?</button>
        <button class="chip" data-q="¿Qué bichitos ayudan en el compost?">¿Qué bichitos ayudan en el compost?</button>
      </div>
      <div class="msgs" id="msgs"></div>
      <div class="input">
        <input class="field" id="field" type="text" placeholder="Escribe aquí tu pregunta compostera..." autocomplete="off">
        <button class="mic" id="mic" aria-label="Hablar" title="Hablar">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 14a3 3 0 003-3V5a3 3 0 10-6 0v6a3 3 0 003 3zm5-3a5 5 0 01-10 0H5a7 7 0 0014 0h-2zM11 19h2v3h-2z"></path></svg>
          <span style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">Hablar</span>
        </button>
        <button class="send" id="send" aria-label="Enviar" title="Enviar">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 11.1c-.9-.4-.9-1.7 0-2.1L20.6 1.8c.9-.4 1.8.5 1.4 1.4l-7.2 18.1c-.3.8-1.5.7-1.8-.1l-2.2-5.4c-.1-.3-.4-.5-.7-.6l-7.6-3.1zM9.2 12.5l3.3 8.1 6.1-15.5-9.4 3.8 3.6 1.5c.5.2.6.9.2 1.2l-3.8 2.9z"></path></svg>
          <span style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">Enviar</span>
        </button>
      </div>
    </div>
  `;

  // Mount base CSS + optional dark
  root.innerHTML = `<style>${css}</style>${html}`;
  if (themeOpt === 'dark') {
    root.innerHTML = `<style>${css}${darkCSS}</style>${html}`;
  } else if (themeOpt === 'auto') {
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.innerHTML = `<style>${css}${prefersDark ? darkCSS : ''}</style>${html}`;
  }

  if (typeof window.gtag === 'function') {
    window.gtag('event', 'ck_chat_loaded', { event_category: 'chatbot' });
  }

  // JS logic isolated
  const msgsEl = root.getElementById('msgs');
  const fieldEl = root.getElementById('field');
    const sendBtn = root.getElementById('send');
    const micBtn  = root.getElementById('mic');
    const chips   = root.getElementById('chips');
    let sending = false;
    let recognition = null;
    let selectedVoice = null;

    if ('speechSynthesis' in window){
      const pickVoice = () => {
        const voices = window.speechSynthesis.getVoices();
        const prefer = [
          'Google español de España',
          'Google español',
          'Microsoft Helena Desktop - Spanish (Spain)'
        ];
        selectedVoice = voices.find(v => prefer.includes(v.name)) ||
                        voices.find(v => v.name && v.name.toLowerCase().includes('google') && v.lang === 'es-ES') ||
                        voices.find(v => v.lang === 'es-ES') ||
                        voices.find(v => v.lang && v.lang.startsWith('es')) || null;
      };
      pickVoice();
      window.speechSynthesis.onvoiceschanged = pickVoice;
    }

  // History
  let history = [];
  try { const saved = localStorage.getItem('ckMessages'); if(saved) history = JSON.parse(saved); } catch(e){}
  if (history.length) { history.forEach(m => render(m.role, m.content)); scroll(); }
  else {
    typingOn();
    setTimeout(function(){
      typingOff();
      const welcome = '¡Hola, peque aventurerx del compost! Soy tu amigue del CEBAS-CSIC. Juntxs haremos magia con las cáscaras y las hojas para alimentar a las plantas. ¿Qué te gustaría saber?';
      history.push({role:'assistant',content:welcome});
      render('ai', welcome, false);
      persist();
      scroll();
    },2000);
  }

  function persist(){ try{ localStorage.setItem('ckMessages', JSON.stringify(history)); } catch(e){} }
    function scroll(){ msgsEl.scrollTop = msgsEl.scrollHeight; }
    function setSending(state){ sending = state; sendBtn.disabled = state; Array.from(chips.children).forEach(b=>b.disabled=state); }
    function typingOn(){ render('ai','',true); scroll(); }
    function typingOff(){ Array.from(msgsEl.querySelectorAll('[data-typing="1"]')).forEach(n=>n.remove()); }

    function typeText(el, text){
      let i = 0;
      const speed = 27;
      (function add(){
        el.textContent += text.charAt(i);
        i++; scroll();
        if(i < text.length){ setTimeout(add, speed); }
      })();
    }

    function speakAndType(el, text){
      if (!('speechSynthesis' in window)) { typeText(el, text); return; }
      const clean = text
        .replace(/[\u{1F300}-\u{1FAFF}]/gu, '')
        .replace(/<[^>]*>/g, '');
      const words = clean.split(/\s+/);
      let spoken = 0;
      const u = new SpeechSynthesisUtterance(clean);
      u.lang = 'es-ES';
      u.pitch = 1.1;
      u.rate  = 1;
      if (selectedVoice) u.voice = selectedVoice;
      u.onboundary = (e) => {
        if(e.name === 'word'){ spoken++; el.textContent = words.slice(0, spoken).join(' '); scroll(); }
      };
      u.onend = () => { el.textContent = text; };
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(u);
    }

    function render(role, text, typing=false){
      const row = document.createElement('div');
      row.className = 'row ' + (role==='user'?'user':'ai');
      const bubble = document.createElement('div');
      bubble.className = 'bubble';
      if (typing){
        row.dataset.typing = '1';
        const t = document.createElement('div');
        t.className = 'typing';
        t.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
        bubble.appendChild(t);
      } else {
        const txt = document.createElement('div');
        bubble.appendChild(txt);
          if(role === 'ai'){
            speakAndType(txt, text);
          } else {
            txt.textContent = text;
          }
      }
      row.appendChild(bubble);
      msgsEl.appendChild(row);
    }

  async function send(txt){
    if(!txt || sending) return;
    setSending(true);
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'ck_chat_message', { event_category: 'chatbot' });
    }
    history.push({role:'user',content:txt});
    render('user', txt);
    fieldEl.value='';
    typingOn();
    try{
      const res = await fetch(ajaxUrl, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({messages: history})
      });
      const data = await res.json();
      typingOff();
      const reply = (data && data.reply) ? data.reply : (data && data.error ? data.error : 'No se pudo obtener respuesta.');
      history.push({role:'assistant',content:reply});
      render('ai', reply);
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'ck_chat_reply', { event_category: 'chatbot' });
      }
    }catch(err){
      typingOff();
      const msg = 'Ups, parece que las lombrices están dormidas. ¡Inténtalo otra vez!';
      history.push({role:'assistant',content:msg});
      render('ai', msg);
      console.error(err);
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'ck_chat_error', { event_category: 'chatbot' });
      }
    }finally{
      persist(); scroll(); setSending(false);
    }
  }

  if ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window) {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SR();
    recognition.lang = 'es-ES';
    recognition.interimResults = false;
    recognition.onresult = (e) => {
      const transcript = e.results[0][0].transcript.trim();
      if (transcript) send(transcript);
    };
    recognition.onstart = () => { micBtn.classList.add('active'); };
    recognition.onend = () => { micBtn.classList.remove('active'); };
  } else {
    micBtn.disabled = true;
  }

  sendBtn.addEventListener('click', ()=> send(fieldEl.value.trim()));
  micBtn.addEventListener('click', ()=>{ if(recognition) recognition.start(); });
  fieldEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); send(fieldEl.value.trim()); } });
  chips.addEventListener('click', (e)=>{
    const b = e.target.closest('.chip'); if(!b) return;
    const q = b.getAttribute('data-q'); if(q) send(q);
  });

  // Ajuste de altura ya manejado con flexbox
})();
</script>
<?php
    return ob_get_clean();
});

/* =========================
 *  AJAX: SERVER SIDE
 * ========================= */
add_action('wp_ajax_ck_gpt_chat', 'ck_gpt_chat');
add_action('wp_ajax_nopriv_ck_gpt_chat', 'ck_gpt_chat');

function ck_gpt_chat() {
    header('Content-Type: application/json; charset=utf-8');

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $messages = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : [];

    $api_key = trim((string) get_option('ck_gpt_api_key'));
    $model   = trim((string) get_option('ck_gpt_model', 'gpt-4o-mini'));
    if (!$api_key) {
        echo json_encode(['reply'=>null,'error'=>'Falta configurar la API Key en Ajustes > Compostaje Kids GPT.']);
        wp_die();
    }
    if (!$model) $model = 'gpt-4o-mini';

    if (count($messages) > 16) { $messages = array_slice($messages, -16); }

    foreach ($messages as &$m) {
        if (!isset($m['role']) || !isset($m['content'])) continue;
        $m['role'] = ($m['role']==='assistant'?'assistant':($m['role']==='system'?'system':'user'));
        $m['content'] = wp_strip_all_tags((string) $m['content']);
    } unset($m);

    $system_prompt = "Eres \"Compostaje para Peques\", un personaje cuentacuentos del CEBAS-CSIC experto en compostaje y reciclaje. "
        . "Tu misión es enseñar, con un tono alegre y mágico, cómo transformar los residuos orgánicos en abono de forma segura y divertida. "
        . "Habla como en un cuento, usando un lenguaje muy sencillo, comparaciones juguetonas y ejemplos cotidianos, con lenguaje inclusivo dirigido a niñas, niños y niñes. "
        . "Si la pregunta no está relacionada con el compostaje, guía la conversación de vuelta al compost. "
        . "Anima siempre a cuidar el medio ambiente y a pedir ayuda a una persona adulta cuando sea necesario. "
        . "Basate en la información divulgativa del CEBAS (https://www.cebas.csic.es/general_spain/presentacion.html) y no proporciones enlaces ni datos de contacto.";

    array_unshift($messages, ['role'=>'system','content'=>$system_prompt]);

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
        ],
        'timeout' => 30,
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 700
        ])
    ]);

    if (is_wp_error($response)) {
        echo json_encode(['reply'=>null, 'error'=>'Error de conexión con OpenAI: ' . $response->get_error_message()]);
        wp_die();
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200) {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : ('Código HTTP ' . $code);
        echo json_encode(['reply'=>null, 'error'=>'OpenAI: ' . $msg]);
        wp_die();
    }

    $reply = isset($body['choices'][0]['message']['content']) ? $body['choices'][0]['message']['content'] : null;
    if (!$reply) {
        echo json_encode(['reply'=>null, 'error'=>'Respuesta vacía de OpenAI.']);
        wp_die();
    }

    echo json_encode(['reply'=>$reply]);
    wp_die();
}
?>
