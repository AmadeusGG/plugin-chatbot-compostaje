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
  host.style.cssText = 'position:relative;width:90vw;height:90vh;max-width:1600px;max-height:1000px;';
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
  .wrap{ position:absolute; inset:0; display:flex; flex-direction:column; width:100%; height:100%; margin:0; border:none; border-radius:0; overflow:hidden; background:#fff; box-shadow:none; opacity:1; }
  .header{ position:relative; padding:5px 5px; background:var(--mut);  display:flex; align-items:center; gap:20px; }
  .header img{ max-height:125px; display:block; flex-shrink:0; }
  .header .text{ flex:1; }
  .title{ margin:4px 0 2px; font-size: clamp(26px,5vw,40px); font-weight:800; }
  .desc{ margin:0; font-size: clamp(18px,3vw,26px); color:#4b5563; }
  .chips{ display:flex; gap:12px; flex-wrap:wrap; justify-content:center; background:var(--mut); padding:5px; border-bottom:1px solid #eef2f7; overflow-x:auto; scroll-snap-type:x mandatory; }
  .chip{ scroll-snap-align:start; padding:10px 16px; border-radius:999px;background:var(--mut); border:1px solid var(--chip-b); background:var(--chip); cursor:pointer; font-size:clamp(18px,2.8vw,24px); color:var(--chip-text); white-space:nowrap; box-shadow:0 2px 0 rgba(0,0,0,.02); transition: background .15s,border-color .15s,transform .08s }
  .chip:hover{ background:#eef2ff; border-color:#c7d2fe; }
  .chip:active{ transform: translateY(1px); }
  .chip[disabled]{ opacity:.5; cursor:not-allowed; }
  .msgs{ flex:1; display:flex; flex-direction:column; background:#fff; overflow:hidden; }
  .bot-stage{ position:relative; flex:1; min-height:0; border-bottom:1px solid #f3d1dc; background:linear-gradient(180deg,#ffeef6 0%,#fff9d7 100%); display:flex; align-items:center; justify-content:center; padding:12px 18px 20px; }
  .bot-stage::before{ content:''; position:absolute; inset:14px 18px 60px; background:rgba(255,255,255,0.7); border-radius:26px; box-shadow:0 12px 24px rgba(255,107,107,0.25); z-index:0; transition:transform .6s ease, box-shadow .6s ease; }
  .bot-stage.is-speaking::before{ transform:scale(1.02); box-shadow:0 18px 34px rgba(255,107,107,0.45); }
  .bot-stage.is-listening::before{ transform:scale(1.01); box-shadow:0 16px 30px rgba(52,211,153,0.4); }
  .thinking-bubble{ position:absolute; top:18%; right:10%; max-width:280px; display:flex; justify-content:center; pointer-events:none; opacity:0; transform:translateY(12px) scale(0.94); transition:opacity .3s ease, transform .3s ease; z-index:2; }
  .thinking-bubble .bubble-body{ background:rgba(255,255,255,0.95); border:3px solid var(--bd); border-radius:28px; padding:14px 18px; box-shadow:0 12px 30px rgba(15,23,42,0.12); position:relative; display:flex; align-items:center; gap:12px; animation:bubbleFloat 3s ease-in-out infinite; }
  .thinking-bubble .bubble-body::after{ content:''; position:absolute; bottom:-18px; left:44px; width:26px; height:26px; background:rgba(255,255,255,0.95); border:3px solid var(--bd); border-top-color:transparent; border-left-color:transparent; transform:rotate(45deg); border-radius:6px; box-shadow:0 12px 30px rgba(15,23,42,0.12); }
  .thinking-bubble .thinking-text{ font-size:clamp(18px,2.6vw,22px); font-weight:700; color:#0f172a; }
  .thinking-dots{ display:inline-flex; gap:6px; }
  .thinking-dots span{ width:10px; height:10px; background:var(--pri); border-radius:50%; opacity:0.7; animation:dotPulse 1.2s ease-in-out infinite; }
  .thinking-dots span:nth-child(2){ animation-delay:.15s; }
  .thinking-dots span:nth-child(3){ animation-delay:.3s; }
  .bot-stage.is-thinking .thinking-bubble{ opacity:1; transform:translateY(0) scale(1); }
  @keyframes bubbleFloat{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-6px); } }
  @keyframes dotPulse{ 0%,100%{ transform:scale(0.7); opacity:0.4; } 50%{ transform:scale(1); opacity:1; } }
  canvas.bot-canvas{ position:relative; z-index:1; width:100%; height:100%; display:block; }
  .listening-indicator{ position:absolute; bottom:18%; left:50%; transform:translate(-50%,36px); display:flex; flex-direction:column; align-items:center; gap:10px; padding:16px 20px; background:rgba(255,255,255,0.95); border-radius:24px; border:3px solid var(--bd); box-shadow:0 16px 40px rgba(52,211,153,0.32); opacity:0; pointer-events:none; transition:opacity .3s ease, transform .3s ease; z-index:3; }
  .bot-stage.is-listening .listening-indicator{ opacity:1; transform:translate(-50%,0); }
  .listening-icon{ position:relative; width:56px; height:56px; display:flex; align-items:center; justify-content:center; }
  .listening-icon .pulse{ position:absolute; inset:0; border-radius:50%; background:radial-gradient(circle, rgba(52,211,153,0.55) 0%, rgba(52,211,153,0.15) 60%, rgba(52,211,153,0) 100%); animation:micPulse 1.6s ease-out infinite; }
  .listening-icon .pulse.delay{ animation-delay:.6s; }
  .listening-icon .mic-symbol{ position:relative; z-index:1; width:38px; height:38px; border-radius:12px; background:#34d399; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 12px rgba(20,83,45,0.35); }
  .listening-icon svg{ width:24px; height:24px; display:block; fill:#fff; }
  .listening-text{ font-weight:700; font-size:clamp(18px,2.4vw,22px); color:#047857; text-align:center; }
  @keyframes micPulse{ 0%{ transform:scale(0.65); opacity:0.85; } 70%{ transform:scale(1); opacity:0; } 100%{ transform:scale(1); opacity:0; } }
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
  @media (max-width:560px){
    .chips{ justify-content:flex-start; padding:10px 8px; }
    .bot-stage{ height:230px; padding:8px 12px 16px; }
    .thinking-bubble{ top:auto; bottom:24%; right:auto; left:50%; transform:translate(-50%,18px) scale(0.9); }
    .bot-stage.is-thinking .thinking-bubble{ transform:translate(-50%,0) scale(1); }
    .thinking-bubble .bubble-body::after{ left:50%; transform:translateX(-50%) rotate(45deg); }
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
  .thinking-bubble .bubble-body{ background:rgba(14,19,26,0.9); border-color:#334155; box-shadow:0 12px 30px rgba(8,12,20,0.55); }
  .thinking-bubble .bubble-body::after{ background:rgba(14,19,26,0.9); border-color:#334155; box-shadow:0 12px 30px rgba(8,12,20,0.55); }
  .thinking-bubble .thinking-text{ color:#f3f4f6; }
  .thinking-dots span{ background:#fbbf24; }
  .listening-indicator{ background:rgba(14,19,26,0.9); border-color:#334155; box-shadow:0 16px 40px rgba(4,120,87,0.32); }
  .listening-icon .pulse{ background:radial-gradient(circle, rgba(16,185,129,0.6) 0%, rgba(16,185,129,0.15) 60%, rgba(16,185,129,0) 100%); }
  .listening-icon .mic-symbol{ background:#0f766e; box-shadow:0 4px 14px rgba(6,78,59,0.45); }
  .listening-text{ color:#34d399; }
  `;

  // Build base HTML
  const html = `
    <div class="wrap">
      <div class="header">
        ${logoUrl ? `<img src="${logoUrl}" alt="Agente IA Compostaje CEBAS Kids">` : ''}
        <div class="text" style="text-align:center;">
          <div class="title">Agente IA Compostaje CEBAS Kids</div>
          <p class="desc">Un rincón mágico del CEBAS-CSIC donde una Inteligencia Artificial te enseña a compostar como en un cuento.</p>
        </div>
      </div>
      <div class="chips" id="chips">
        <button class="chip" id="storyChip" type="button">Cuéntame el cuento mágico del compost</button>
        <button class="chip" id="voiceChip" type="button">Pregúntame sobre el compost</button>
        <button class="chip" id="stopChip" type="button">Parar</button>
      </div>
      <div class="msgs" id="msgs">
        <div class="bot-stage" id="botStage">
          <canvas class="bot-canvas" id="botCanvas"></canvas>
          <div class="listening-indicator" id="listeningIndicator" aria-hidden="true">
            <div class="listening-icon" aria-hidden="true">
              <span class="pulse"></span>
              <span class="pulse delay"></span>
              <span class="mic-symbol">
                <svg viewBox="0 0 24 24" role="img" aria-label="Micrófono">
                  <path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3Zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 14 0h-2Zm-4.25 7.03V22h-1.5v-2.97A7.02 7.02 0 0 1 5 12h2a5 5 0 0 0 10 0h2a7.02 7.02 0 0 1-6.25 7.03Z"/>
                </svg>
              </span>
            </div>
            <span class="listening-text">Compostín te escucha...</span>
          </div>
          <div class="thinking-bubble" aria-hidden="true">
            <div class="bubble-body">
              <span class="thinking-text">Estoy pensando tu respuesta...</span>
              <span class="thinking-dots"><span></span><span></span><span></span></span>
            </div>
          </div>
        </div>
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
  const stageEl = root.getElementById('botStage');
  const canvas = root.getElementById('botCanvas');
  const fieldEl = root.getElementById('field');
  const sendBtn = root.getElementById('send');
  const micBtn  = root.getElementById('mic');
  const chips   = root.getElementById('chips');
  const listeningIndicator = root.getElementById('listeningIndicator');
  const storyChip = root.getElementById('storyChip');
  const voiceChip = root.getElementById('voiceChip');
  const stopChip = root.getElementById('stopChip');
  let sending = false;
  let recognition = null;
  let selectedVoice = null;
  let robotSpeaking = false;
  let fallbackSpeechTimeout = null;
  let robotThinking = false;
  let thinkingTimeout = null;
  let robotListening = false;
  let canUseRecognition = false;

  function updateChipStates(){
    const baseDisabled = !!sending;
    if (storyChip) storyChip.disabled = baseDisabled || robotSpeaking;
    if (voiceChip) voiceChip.disabled = baseDisabled || robotSpeaking || !canUseRecognition;
    if (stopChip) stopChip.disabled = baseDisabled || !robotSpeaking;
  }

  const ctx = canvas && canvas.getContext ? canvas.getContext('2d') : null;
  const stageSize = { width: 0, height: 0 };
  const crumbs = ctx ? Array.from({length:7}, () => ({ angle: Math.random()*Math.PI*2, radius: 0.28 + Math.random()*0.35, size: 6 + Math.random()*5, color: Math.random() > 0.5 ? '#8ed16f' : '#b47b36' })) : [];
  const sparks = ctx ? Array.from({length:6}, () => ({ angle: Math.random()*Math.PI*2, distance: 0.65 + Math.random()*0.2, speed: 0.005 + Math.random()*0.01, size: 6 + Math.random()*3, color: Math.random() > 0.5 ? '#ffadad' : '#9bf6ff' })) : [];
  const compostTypes = ctx ? [
    { type: 'leaf', base: '#6fcf97', accent: '#4cb944' },
    { type: 'banana', base: '#ffdd67', accent: '#f4a261' },
    { type: 'apple', base: '#ff8fa3', accent: '#d62828' },
    { type: 'carrot', base: '#fb923c', accent: '#f97316' },
    { type: 'eggshell', base: '#f3f1e8', accent: '#e4ded1' },
    { type: 'tea', base: '#d4a373', accent: '#b08968' }
  ] : [];
  const compostItems = ctx ? [] : [];
  const fireflies = ctx ? Array.from({length: 8}, () => ({
    x: Math.random(),
    y: Math.random()*0.4 + 0.05,
    phase: Math.random()*Math.PI*2,
    drift: 0.0008 + Math.random()*0.0012,
    glow: 0.4 + Math.random()*0.4
  })) : [];
  const veggieBeds = ctx ? [
    { pos: 0.18, color: '#f9c74f', leaf: '#70e000', type: 'carrot' },
    { pos: 0.38, color: '#90be6d', leaf: '#4cc9f0', type: 'lettuce' },
    { pos: 0.58, color: '#f9844a', leaf: '#43aa8b', type: 'pumpkin' },
    { pos: 0.78, color: '#ffafcc', leaf: '#4895ef', type: 'beet' }
  ] : [];
  const sproutOffsets = ctx ? Array.from({length: 18}, (_,i) => ({
    x: (i/18) + (Math.random()*0.05 - 0.02),
    phase: Math.random()*Math.PI*2,
    scale: 0.6 + Math.random()*0.5
  })) : [];
  let compostSpawnTimer = 0;
  let tossHighlight = 0;
  let wormPhase = 0;

  const createCompostItem = (opts={}) => {
    const data = compostTypes[Math.floor(Math.random()*compostTypes.length)];
    if (!data) return null;
    return {
      ...data,
      angle: Math.random()*Math.PI*2,
      radius: 1.1 + Math.random()*0.5,
      targetRadius: 0.55 + Math.random()*0.3,
      speed: 0.01 + Math.random()*0.01,
      bob: Math.random()*Math.PI*2,
      bobSpeed: 0.02 + Math.random()*0.02,
      rotation: Math.random()*Math.PI*2,
      rotationSpeed: (Math.random()*0.03) - 0.015,
      scale: 0.7 + Math.random()*0.4,
      timer: 0,
      alpha: 0,
      phase: 'orbit',
      spawnProgress: 0,
      normX: typeof opts.normX === 'number' ? opts.normX : null,
      normY: typeof opts.normY === 'number' ? opts.normY : null,
      userSpawn: !!opts.userSpawn
    };
  };
  let blinkTimer = 150;
  let blinkTarget = 0;
  let blinkValue = 0;
  let mouthValue = 0.2;
  let floatPhase = 0;
  let idlePupilTarget = 0;
  let idlePupilOffset = 0;
  let idlePupilTimer = 0;
  let idleWaveActive = false;
  let idleWavePhase = 0;
  let idleWaveAmplitude = 0;
  let idleGreetingEnabled = true;
  const getNow = () => (typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now());
  let nextIdleWaveTime = getNow() + 20000 + Math.random()*10000;

  const disableIdleGreeting = () => {
    if (!idleGreetingEnabled) return;
    idleGreetingEnabled = false;
    idleWaveActive = false;
    idleWaveAmplitude = 0;
    nextIdleWaveTime = Infinity;
  };

  function updateStageState(){
    if (!stageEl) return;
    stageEl.classList.toggle('is-speaking', robotSpeaking);
    stageEl.classList.toggle('is-thinking', robotThinking && !robotSpeaking);
    stageEl.classList.toggle('is-listening', robotListening);
    if (listeningIndicator) {
      listeningIndicator.setAttribute('aria-hidden', robotListening ? 'false' : 'true');
    }
  }

  function robotStartListening(){
    robotListening = true;
    robotThinking = false;
    updateStageState();
  }

  function robotStopListening(){
    robotListening = false;
    updateStageState();
  }

  function setThinking(state){
    if (thinkingTimeout){
      clearTimeout(thinkingTimeout);
      thinkingTimeout = null;
    }
    if (robotThinking === state) {
      updateStageState();
      return;
    }
    robotThinking = state;
    updateStageState();
  }

  function robotStartSpeaking(){
    clearTimeout(fallbackSpeechTimeout);
    robotSpeaking = true;
    setThinking(false);
    updateStageState();
    updateChipStates();
  }

  function robotStopSpeaking(){
    clearTimeout(fallbackSpeechTimeout);
    fallbackSpeechTimeout = null;
    robotSpeaking = false;
    updateStageState();
    updateChipStates();
  }

  function robotSimulateSpeech(duration){
    robotStartSpeaking();
    fallbackSpeechTimeout = setTimeout(()=>{ robotStopSpeaking(); }, duration);
  }

  function cancelSpeechPlayback(){
    if ('speechSynthesis' in window){
      try {
        window.speechSynthesis.cancel();
      } catch(e){
        /* noop */
      }
    }
    robotStopSpeaking();
  }

  if (ctx){
      for (let i=0; i<5; i++){
        const starter = createCompostItem();
        if (starter) compostItems.push(starter);
      }
      const drawRoundedRect = (x,y,w,h,r)=>{
        const rr = Math.min(r, h/2, w/2);
        ctx.beginPath();
        ctx.moveTo(x+rr, y);
        ctx.lineTo(x+w-rr, y);
        ctx.quadraticCurveTo(x+w, y, x+w, y+rr);
        ctx.lineTo(x+w, y+h-rr);
        ctx.quadraticCurveTo(x+w, y+h, x+w-rr, y+h);
        ctx.lineTo(x+rr, y+h);
        ctx.quadraticCurveTo(x, y+h, x, y+h-rr);
        ctx.lineTo(x, y+rr);
        ctx.quadraticCurveTo(x, y, x+rr, y);
        ctx.closePath();
      };

      function resizeRobot(){
        const rect = stageEl.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        canvas.style.width = rect.width + 'px';
        canvas.style.height = rect.height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        stageSize.width = rect.width;
        stageSize.height = rect.height;
      }

      resizeRobot();
      window.addEventListener('resize', resizeRobot);
      if (window.ResizeObserver){
        try {
          const ro = new ResizeObserver(resizeRobot);
          ro.observe(stageEl);
        } catch(e){}
      }

      function drawCloud(x,y,scale){
        ctx.save();
        ctx.translate(x, y);
        ctx.scale(scale, scale);
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.beginPath();
        ctx.arc(-20, 0, 22, 0, Math.PI*2);
        ctx.arc(10, -8, 26, 0, Math.PI*2);
        ctx.arc(36, 6, 20, 0, Math.PI*2);
        ctx.arc(0, 12, 24, 0, Math.PI*2);
        ctx.fill();
        ctx.restore();
      }

      function drawCompostable(item, x, y, scaleFactor=1){
        if (!item) return;
        ctx.save();
        ctx.translate(x, y);
        ctx.globalAlpha = Math.max(0, Math.min(1, item.alpha));
        const wobble = 1 + 0.06*Math.sin(item.bob*2);
        const detailScale = Math.max(0.1, scaleFactor);
        ctx.scale(item.scale * wobble * detailScale, item.scale * wobble * detailScale);
        ctx.rotate(item.rotation);
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        switch(item.type){
          case 'leaf':
            ctx.fillStyle = item.base;
            ctx.beginPath();
            ctx.moveTo(0, -22);
            ctx.quadraticCurveTo(20, -10, 22, 8);
            ctx.quadraticCurveTo(8, 26, 0, 34);
            ctx.quadraticCurveTo(-8, 26, -22, 8);
            ctx.quadraticCurveTo(-20, -10, 0, -22);
            ctx.fill();
            ctx.strokeStyle = item.accent;
            ctx.lineWidth = 3.5;
            ctx.beginPath();
            ctx.moveTo(0, -22);
            ctx.lineTo(0, 34);
            ctx.moveTo(-8, 10);
            ctx.quadraticCurveTo(0, 4, 8, 10);
            ctx.stroke();
            break;
          case 'banana':
            ctx.fillStyle = item.base;
            ctx.beginPath();
            ctx.moveTo(-26, -8);
            ctx.quadraticCurveTo(-8, -22, 22, -12);
            ctx.quadraticCurveTo(30, 4, -12, 16);
            ctx.quadraticCurveTo(-30, 12, -26, -8);
            ctx.fill();
            ctx.fillStyle = item.accent;
            ctx.beginPath();
            ctx.ellipse(-24, -4, 4, 6, 0, 0, Math.PI*2);
            ctx.ellipse(20, -4, 4, 6, 0, 0, Math.PI*2);
            ctx.fill();
            break;
          case 'apple':
            ctx.fillStyle = '#ffe0e7';
            ctx.beginPath();
            ctx.moveTo(-16, -30);
            ctx.quadraticCurveTo(0, -24, 16, -30);
            ctx.quadraticCurveTo(12, -4, 18, 8);
            ctx.quadraticCurveTo(4, 24, 0, 38);
            ctx.quadraticCurveTo(-4, 24, -18, 8);
            ctx.quadraticCurveTo(-12, -4, -16, -30);
            ctx.fill();
            ctx.fillStyle = item.accent;
            ctx.beginPath();
            ctx.arc(-6, -6, 3.5, 0, Math.PI*2);
            ctx.arc(6, 10, 3.5, 0, Math.PI*2);
            ctx.fill();
            ctx.strokeStyle = '#6f1d1b';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(0, -30);
            ctx.quadraticCurveTo(4, -40, 0, -48);
            ctx.stroke();
            break;
          case 'carrot':
            ctx.fillStyle = item.base;
            ctx.beginPath();
            ctx.moveTo(0, -34);
            ctx.quadraticCurveTo(20, -18, 6, 38);
            ctx.quadraticCurveTo(-12, -2, 0, -34);
            ctx.fill();
            ctx.fillStyle = '#15803d';
            ctx.beginPath();
            ctx.moveTo(-10, -32);
            ctx.lineTo(-2, -48);
            ctx.lineTo(6, -32);
            ctx.closePath();
            ctx.fill();
            ctx.strokeStyle = item.accent;
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(-2, -12);
            ctx.lineTo(4, -8);
            ctx.moveTo(-4, 0);
            ctx.lineTo(2, 4);
            ctx.stroke();
            break;
          case 'eggshell':
            ctx.fillStyle = item.base;
            ctx.beginPath();
            ctx.moveTo(-28, 6);
            ctx.quadraticCurveTo(-20, -26, 0, -26);
            ctx.quadraticCurveTo(20, -26, 28, 6);
            ctx.lineTo(18, 0);
            ctx.lineTo(10, 8);
            ctx.lineTo(2, 0);
            ctx.lineTo(-6, 8);
            ctx.lineTo(-14, 0);
            ctx.closePath();
            ctx.fill();
            ctx.strokeStyle = item.accent;
            ctx.lineWidth = 2.5;
            ctx.stroke();
            break;
          case 'tea':
          default:
            ctx.strokeStyle = item.accent;
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(0, -22);
            ctx.lineTo(0, -42);
            ctx.stroke();
            ctx.fillStyle = item.accent;
            ctx.beginPath();
            ctx.rect(-10, -54, 20, 12);
            ctx.fill();
            ctx.fillStyle = '#fff7ed';
            ctx.beginPath();
            ctx.rect(-6, -50, 12, 6);
            ctx.fill();
            ctx.fillStyle = item.base;
            ctx.beginPath();
            ctx.moveTo(-16, -22);
            ctx.lineTo(16, -22);
            ctx.lineTo(20, 18);
            ctx.lineTo(-20, 18);
            ctx.closePath();
            ctx.fill();
            ctx.strokeStyle = item.accent;
            ctx.beginPath();
            ctx.moveTo(-16, -22);
            ctx.lineTo(-12, 12);
            ctx.moveTo(16, -22);
            ctx.lineTo(12, 12);
            ctx.stroke();
            break;
        }
        ctx.restore();
      }

      function loop(){
        if (!stageSize.width || !stageSize.height){
          requestAnimationFrame(loop);
          return;
        }
        const nowMs = getNow();
        if (!robotSpeaking) {
          idlePupilTimer -= 1;
          if (idlePupilTimer <= 0){
            idlePupilTarget = (Math.random()*1.6) - 0.8;
            idlePupilTimer = 80 + Math.random()*120;
          }
        } else {
          idlePupilTarget = 0;
          idlePupilTimer = 40;
        }
        idlePupilOffset += (idlePupilTarget - idlePupilOffset) * 0.06;

        if (idleGreetingEnabled && !robotSpeaking && !sending){
          if (!idleWaveActive && nowMs >= nextIdleWaveTime){
            idleWaveActive = true;
            idleWavePhase = 0;
            idleWaveAmplitude = 1;
            nextIdleWaveTime = nowMs + 20000 + Math.random()*10000;
            if (typeof speakText === 'function'){
              speakText('¡Hola!');
            }
          }
        }

        if (idleWaveActive){
          idleWavePhase += 0.14;
          idleWaveAmplitude *= 0.988;
          if (idleWavePhase > Math.PI * 4){
            idleWaveActive = false;
            idleWaveAmplitude = 0;
          }
        } else if (robotSpeaking) {
          idleWaveAmplitude = 0;
        } else {
          idleWaveAmplitude *= 0.96;
        }

        if (robotSpeaking){
          floatPhase += 1;
          blinkTimer--;
          if (blinkTimer <= 0){
            blinkTarget = 1;
            blinkTimer = 160 + Math.random()*120;
          }
        } else {
          blinkTarget = 0;
          if (blinkTimer < 160){
            blinkTimer = 160;
          }
        }
        blinkValue += (blinkTarget - blinkValue) * 0.2;
        if (robotSpeaking && blinkTarget === 1 && blinkValue > 0.9){
          blinkTarget = 0;
        }

        const mouthTarget = robotSpeaking ? 0.7 + 0.15*Math.sin(floatPhase*0.25) : 0.2;
        mouthValue += (mouthTarget - mouthValue) * 0.25;

        stageEl.classList.toggle('is-speaking', robotSpeaking);

        const w = stageSize.width;
        const h = stageSize.height;
        ctx.clearRect(0,0,w,h);

        compostSpawnTimer -= 1;
        if (robotSpeaking){
          compostSpawnTimer -= 0.5;
        }
        fireflies.forEach(f => {
          f.phase += 0.03 + (robotSpeaking ? 0.02 : 0);
          f.x += Math.cos(f.phase) * f.drift;
          if (f.x < -0.1) f.x = 1.1;
          if (f.x > 1.1) f.x = -0.1;
          f.y += Math.sin(f.phase*0.7) * f.drift * 0.6;
          f.y = Math.max(0.05, Math.min(0.6, f.y));
        });
        wormPhase += 0.02 + (robotSpeaking ? 0.02 : 0);

        // background sun
        const sunX = w*0.12;
        const sunY = h*0.18;
        ctx.fillStyle = '#ffe066';
        ctx.beginPath();
        ctx.arc(sunX, sunY, 26, 0, Math.PI*2);
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,209,102,0.7)';
        ctx.lineWidth = 3;
        for (let i=0;i<10;i++){
          const angle = (Math.PI*2/10)*i + (robotSpeaking ? floatPhase*0.02 : 0);
          ctx.beginPath();
          ctx.moveTo(sunX + Math.cos(angle)*30, sunY + Math.sin(angle)*30);
          ctx.lineTo(sunX + Math.cos(angle)*44, sunY + Math.sin(angle)*44);
          ctx.stroke();
        }

        // clouds
        drawCloud(w*0.72, h*0.18 + (robotSpeaking ? Math.sin(floatPhase*0.01)*6 : 0), 0.9);
        drawCloud(w*0.45, h*0.12 + (robotSpeaking ? Math.cos(floatPhase*0.012)*4 : 0), 0.7);

        fireflies.forEach(f => {
          const fx = w * f.x;
          const fy = h * f.y;
          const glow = (Math.sin(f.phase*3) + 1) / 2;
          ctx.save();
          ctx.globalAlpha = 0.35 + glow * f.glow;
          ctx.fillStyle = '#fff59d';
          ctx.beginPath();
          ctx.arc(fx, fy, 2.5 + glow*2.6, 0, Math.PI*2);
          ctx.fill();
          ctx.restore();
        });

        const groundY = h*0.78 + (robotSpeaking ? Math.sin(floatPhase*0.02)*3 : 0);
        const horizonY = h*0.52 + (robotSpeaking ? Math.sin(floatPhase*0.013)*4 : 0);
        const soilTop = groundY - 32;

        // meadow background
        ctx.fillStyle = '#d8f3dc';
        ctx.beginPath();
        ctx.moveTo(0, horizonY + 10);
        ctx.quadraticCurveTo(w*0.2, horizonY-18, w*0.44, horizonY-8);
        ctx.quadraticCurveTo(w*0.72, horizonY+18, w, horizonY-6);
        ctx.lineTo(w, groundY);
        ctx.lineTo(0, groundY);
        ctx.closePath();
        ctx.fill();

        // distant veggie silhouettes
        ctx.save();
        ctx.translate(0, horizonY);
        ctx.fillStyle = 'rgba(112, 168, 90, 0.65)';
        for (let i=0; i<12; i++){
          const hillX = (w/11)*i + (robotSpeaking ? Math.sin(floatPhase*0.01 + i)*6 : 0);
          const hillH = 32 + Math.sin(i*1.7)*16;
          ctx.beginPath();
          ctx.moveTo(hillX-26, 10);
          ctx.bezierCurveTo(hillX-18, -hillH*0.6, hillX+18, -hillH*0.6, hillX+26, 10);
          ctx.closePath();
          ctx.fill();
        }
        ctx.restore();

        // soil layer with organic texture
        ctx.fillStyle = '#8d5524';
        ctx.beginPath();
        ctx.moveTo(0, soilTop);
        ctx.bezierCurveTo(w*0.22, soilTop+18, w*0.42, soilTop-6, w*0.6, soilTop+12);
        ctx.bezierCurveTo(w*0.78, soilTop+26, w*0.92, soilTop-4, w, soilTop+16);
        ctx.lineTo(w, h);
        ctx.lineTo(0, h);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#a47148';
        for (let i=0; i<60; i++){
          const pebbleX = Math.random()*w;
          const pebbleY = soilTop + Math.random()*(h-soilTop-20);
          const pebbleSize = 3 + Math.random()*4;
          ctx.globalAlpha = 0.35;
          ctx.beginPath();
          ctx.ellipse(pebbleX, pebbleY, pebbleSize, pebbleSize*0.6, Math.random()*Math.PI, 0, Math.PI*2);
          ctx.fill();
        }
        ctx.globalAlpha = 1;

        // compost rows
        ctx.fillStyle = '#4caf50';
        ctx.beginPath();
        ctx.moveTo(0, soilTop);
        ctx.bezierCurveTo(w*0.18, soilTop-12, w*0.36, soilTop-8, w*0.52, soilTop-10);
        ctx.bezierCurveTo(w*0.72, soilTop-14, w*0.88, soilTop-6, w, soilTop-12);
        ctx.lineTo(w, soilTop+12);
        ctx.bezierCurveTo(w*0.82, soilTop+18, w*0.64, soilTop+8, w*0.48, soilTop+14);
        ctx.bezierCurveTo(w*0.3, soilTop+18, w*0.16, soilTop+12, 0, soilTop+16);
        ctx.closePath();
        ctx.fill();

        // sprouting mini plants
        ctx.save();
        ctx.translate(0, soilTop+4);
        sproutOffsets.forEach((sprout, idx)=>{
          const sx = (sprout.x % 1) * w;
          const sway = Math.sin(sprout.phase + floatPhase*0.02 + idx)*4;
          ctx.strokeStyle = '#38761d';
          ctx.lineWidth = 2 * sprout.scale;
          ctx.beginPath();
          ctx.moveTo(sx, 12);
          ctx.quadraticCurveTo(sx+sway*0.2, 2, sx+sway, -8);
          ctx.stroke();
          ctx.fillStyle = '#66bb6a';
          ctx.beginPath();
          ctx.ellipse(sx-4, 0, 6*sprout.scale, 3*sprout.scale, -0.6, 0, Math.PI*2);
          ctx.ellipse(sx+4, 0, 6*sprout.scale, 3*sprout.scale, 0.6, 0, Math.PI*2);
          ctx.fill();
        });
        ctx.restore();

        // vegetable beds with crops
        veggieBeds.forEach((bed, idx)=>{
          const bedX = w * bed.pos;
          const bedY = soilTop + 6 + Math.sin(floatPhase*0.02 + idx)*3;
          const bedW = w*0.16;
          const bedH = 22;
          ctx.fillStyle = 'rgba(51, 102, 51, 0.18)';
          drawRoundedRect(bedX - bedW/2, bedY-6, bedW, bedH, 14);
          ctx.fill();

          const plantCount = 4;
          for (let i=0; i<plantCount; i++){
            const offset = (i/(plantCount-1) - 0.5) * (bedW*0.7);
            const plantX = bedX + offset;
            const bounce = Math.sin(floatPhase*0.03 + idx + i)*2;
            ctx.save();
            ctx.translate(plantX, bedY + bounce);
            ctx.strokeStyle = '#2d6a4f';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(0, 10);
            ctx.quadraticCurveTo(2, -4, 0, -18);
            ctx.stroke();
            switch(bed.type){
              case 'carrot':
                ctx.fillStyle = bed.color;
                ctx.beginPath();
                ctx.moveTo(0, -6);
                ctx.lineTo(-6, 12);
                ctx.lineTo(6, 12);
                ctx.closePath();
                ctx.fill();
                ctx.fillStyle = bed.leaf;
                ctx.beginPath();
                ctx.ellipse(-6, -18, 8, 14, -0.5, 0, Math.PI*2);
                ctx.ellipse(6, -18, 8, 14, 0.5, 0, Math.PI*2);
                ctx.fill();
                break;
              case 'lettuce':
                ctx.fillStyle = bed.leaf;
                for (let l=0; l<3; l++){
                  ctx.beginPath();
                  ctx.ellipse(0, -12-l*4, 14-l*2, 10-l*2, 0, 0, Math.PI*2);
                  ctx.fill();
                }
                ctx.fillStyle = bed.color;
                ctx.beginPath();
                ctx.ellipse(0, -14, 10, 8, 0, 0, Math.PI*2);
                ctx.fill();
                break;
              case 'pumpkin':
                ctx.fillStyle = bed.color;
                ctx.beginPath();
                ctx.ellipse(0, 0, 16, 12, 0, 0, Math.PI*2);
                ctx.fill();
                ctx.fillStyle = '#f8961e';
                ctx.beginPath();
                ctx.ellipse(0, 0, 12, 12, 0, 0, Math.PI*2);
                ctx.fill();
                ctx.fillStyle = bed.leaf;
                ctx.beginPath();
                ctx.moveTo(0, -10);
                ctx.quadraticCurveTo(6, -18, 10, -10);
                ctx.quadraticCurveTo(4, -4, 0, -6);
                ctx.quadraticCurveTo(-4, -4, -10, -10);
                ctx.quadraticCurveTo(-6, -18, 0, -10);
                ctx.fill();
                break;
              case 'beet':
              default:
                ctx.fillStyle = bed.color;
                ctx.beginPath();
                ctx.ellipse(0, 6, 12, 14, 0, 0, Math.PI*2);
                ctx.fill();
                ctx.fillStyle = bed.leaf;
                ctx.beginPath();
                ctx.moveTo(0, -6);
                ctx.bezierCurveTo(10, -26, 12, -6, 0, -2);
                ctx.bezierCurveTo(-12, -6, -10, -26, 0, -6);
                ctx.fill();
                break;
            }
            ctx.restore();
          }
        });

        // friendly worm helper
        ctx.save();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        const wormBaseY = groundY + 18;
        ctx.strokeStyle = '#ffadad';
        ctx.lineWidth = 12;
        ctx.beginPath();
        for (let i=0; i<=6; i++){
          const t = i/6;
          const wx = w*0.18 + t*w*0.64;
          const wy = wormBaseY + Math.sin(wormPhase + t*Math.PI*2)*10;
          if (i===0) ctx.moveTo(wx, wy); else ctx.lineTo(wx, wy);
        }
        ctx.stroke();
        ctx.strokeStyle = '#f07167';
        ctx.lineWidth = 7;
        ctx.stroke();
        ctx.fillStyle = '#1f2933';
        const headY = wormBaseY + Math.sin(wormPhase)*10;
        ctx.beginPath();
        ctx.arc(w*0.18 + 2, headY - 4, 2.5, 0, Math.PI*2);
        ctx.arc(w*0.18 + 2, headY + 4, 2.5, 0, Math.PI*2);
        ctx.fill();
        ctx.restore();

        const centerX = w/2;
        const baseBodyW = Math.min(220, w*0.55);
        const baseBodyH = Math.min(170, h*0.5);
        const maxScale = 1.5;
        const availableScaleW = baseBodyW > 0 ? (w*0.92) / baseBodyW : maxScale;
        const availableScaleH = baseBodyH > 0 ? (h*0.92) / baseBodyH : maxScale;
        const robotScale = Math.max(1, Math.min(maxScale, availableScaleW, availableScaleH));
        const scaled = (val) => val * robotScale;
        const scaledLine = (val) => Math.max(1, val * robotScale);
        const bob = robotSpeaking ? Math.sin(floatPhase*0.05) * scaled(10) : 0;
        const baseY = groundY - scaled(16) + bob;
        const bodyW = baseBodyW * robotScale;
        const bodyH = baseBodyH * robotScale;
        const bodyX = centerX - bodyW/2;
        const bodyY = baseY - bodyH;

        // shadow
        ctx.fillStyle = 'rgba(0,0,0,0.08)';
        ctx.beginPath();
        ctx.ellipse(centerX, baseY+scaled(8), bodyW*0.45, scaled(18), 0, 0, Math.PI*2);
        ctx.fill();

        // legs
        ctx.strokeStyle = '#2f8f67';
        ctx.lineCap = 'round';
        ctx.lineWidth = scaledLine(12);
        ctx.beginPath();
        ctx.moveTo(centerX - bodyW*0.22, baseY-scaled(6));
        ctx.lineTo(centerX - bodyW*0.22, baseY+scaled(10));
        ctx.moveTo(centerX + bodyW*0.22, baseY-scaled(6));
        ctx.lineTo(centerX + bodyW*0.22, baseY+scaled(10));
        ctx.stroke();

        // body
        ctx.fillStyle = '#57cc99';
        ctx.strokeStyle = '#2f8f67';
        ctx.lineWidth = scaledLine(6);
        drawRoundedRect(bodyX, bodyY, bodyW, bodyH, scaled(32));
        ctx.fill();
        ctx.stroke();

        // lid
        const lidH = bodyH*0.18;
        const lidY = bodyY - lidH*0.45;
        ctx.fillStyle = '#38a3a5';
        drawRoundedRect(bodyX + bodyW*0.08, lidY, bodyW*0.84, lidH, scaled(20));
        ctx.fill();

        // antenna
        ctx.strokeStyle = '#2f8f67';
        ctx.lineWidth = scaledLine(5);
        ctx.beginPath();
        const antennaWave = robotSpeaking ? Math.sin(floatPhase*0.09)*scaled(6) : 0;
        ctx.moveTo(centerX, lidY);
        ctx.quadraticCurveTo(centerX, lidY-scaled(30) - antennaWave, centerX, lidY-scaled(48) - antennaWave);
        ctx.stroke();
        ctx.fillStyle = '#ffd166';
        ctx.beginPath();
        ctx.arc(centerX, lidY-scaled(56) - antennaWave, scaled(10), 0, Math.PI*2);
        ctx.fill();

        // face area
        const faceH = bodyH*0.42;
        const faceY = bodyY + bodyH*0.12;
        const faceX = bodyX + bodyW*0.1;
        const faceW = bodyW*0.8;
        ctx.fillStyle = '#9bf6ff';
        drawRoundedRect(faceX, faceY, faceW, faceH, scaled(24));
        ctx.fill();

        // cheeks
        ctx.fillStyle = '#ffcad4';
        ctx.beginPath();
        ctx.ellipse(faceX + faceW*0.18, faceY + faceH*0.65, scaled(16), scaled(12), 0, 0, Math.PI*2);
        ctx.ellipse(faceX + faceW*0.82, faceY + faceH*0.65, scaled(16), scaled(12), 0, 0, Math.PI*2);
        ctx.fill();

        const eyeOpen = Math.max(0.1, 1 - blinkValue);
        const eyeW = scaled(24);
        const eyeH = scaled(18) * eyeOpen;
        const eyeY = faceY + faceH*0.42;
        const eyeOffset = faceW*0.26;
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.ellipse(centerX - eyeOffset, eyeY, eyeW, Math.max(scaled(6), eyeH), 0, 0, Math.PI*2);
        ctx.ellipse(centerX + eyeOffset, eyeY, eyeW, Math.max(scaled(6), eyeH), 0, 0, Math.PI*2);
        ctx.fill();

        ctx.fillStyle = '#1f3b4d';
        const pupilBob = robotSpeaking ? Math.sin(floatPhase*0.05)*scaled(3) : 0;
        const pupilXOffset = robotSpeaking ? 0 : scaled(8) * idlePupilOffset;
        ctx.beginPath();
        ctx.ellipse(centerX - eyeOffset + pupilXOffset, eyeY + pupilBob, scaled(10), Math.max(scaled(4), eyeH*0.4), 0, 0, Math.PI*2);
        ctx.ellipse(centerX + eyeOffset + pupilXOffset, eyeY + pupilBob, scaled(10), Math.max(scaled(4), eyeH*0.4), 0, 0, Math.PI*2);
        ctx.fill();

        // mouth
        const mouthW = faceW*0.42;
        const mouthH = (10 + 28*mouthValue) * robotScale;
        const mouthY = faceY + faceH*0.72;
        ctx.fillStyle = '#ff9f1c';
        drawRoundedRect(centerX - mouthW/2, mouthY - mouthH/2, mouthW, mouthH, scaled(14));
        ctx.fill();
        ctx.fillStyle = '#1f3b4d';
        ctx.fillRect(centerX - mouthW*0.35, mouthY - scaled(3), mouthW*0.7, scaled(6));

        // arms
        const armY = faceY + faceH*0.78;
        const speakingWave = robotSpeaking ? Math.sin(floatPhase*0.08) * scaled(22) : 0;
        const idleWaveMotion = idleWaveAmplitude ? Math.sin(idleWavePhase) * scaled(26) * idleWaveAmplitude : 0;
        const leftArmWave = speakingWave;
        const rightArmWave = speakingWave + idleWaveMotion;
        ctx.strokeStyle = '#38a3a5';
        ctx.lineCap = 'round';
        ctx.lineWidth = scaledLine(12);
        ctx.beginPath();
        ctx.moveTo(bodyX + scaled(12), armY);
        ctx.quadraticCurveTo(bodyX - bodyW*0.25, armY - leftArmWave - scaled(10), bodyX - bodyW*0.18, armY + leftArmWave);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(bodyX + bodyW - scaled(12), armY);
        ctx.quadraticCurveTo(bodyX + bodyW + bodyW*0.25, armY - rightArmWave - scaled(10), bodyX + bodyW + bodyW*0.18, armY + rightArmWave);
        ctx.stroke();

        ctx.fillStyle = '#ff6b6b';
        ctx.beginPath();
        ctx.ellipse(bodyX - bodyW*0.2, armY + leftArmWave, scaled(14), scaled(14), 0, 0, Math.PI*2);
        ctx.ellipse(bodyX + bodyW + bodyW*0.2, armY + rightArmWave, scaled(14), scaled(14), 0, 0, Math.PI*2);
        ctx.fill();

        // compost window
        const windowR = bodyW*0.32;
        const windowY = bodyY + bodyH*0.7;
        ctx.fillStyle = '#1f3b4d';
        ctx.beginPath();
        ctx.ellipse(centerX, windowY, windowR, windowR*0.65, 0, 0, Math.PI*2);
        ctx.fill();
        ctx.fillStyle = '#b2f2bb';
        ctx.beginPath();
        const windowInset = scaled(8);
        ctx.ellipse(centerX, windowY, windowR-windowInset, windowR*0.65-windowInset, 0, 0, Math.PI*2);
        ctx.fill();

        if (compostSpawnTimer <= 0 && compostTypes.length && compostItems.length < 12){
          const newcomer = createCompostItem();
          if (newcomer) compostItems.push(newcomer);
          compostSpawnTimer = robotSpeaking ? 150 : 210;
        }

        const orbitBase = windowR + scaled(60);
        compostItems.forEach((item)=>{
          if (!item) return;
          item.timer += 1;
          item.angle += item.speed * (robotSpeaking ? 1.35 : 1);
          item.bob += item.bobSpeed * (robotSpeaking ? 1.35 : 1);
          item.rotation += item.rotationSpeed * (robotSpeaking ? 1.2 : 1);
          if (item.phase === 'orbit'){
            item.radius += (item.targetRadius - item.radius) * 0.02;
            if (item.timer > 340){
              item.targetRadius = Math.max(0.2, item.targetRadius - 0.003);
            }
            if (item.timer > 520){
              item.phase = 'drop';
              tossHighlight = Math.max(tossHighlight, 0.45);
            }
          } else if (item.phase === 'drop'){
            item.targetRadius = 0.1;
            item.radius += (item.targetRadius - item.radius) * 0.05;
          }

          const orbitRadius = orbitBase * item.radius;
          let targetX = centerX + Math.cos(item.angle) * orbitRadius;
          let targetY = windowY - scaled(22) + Math.sin(item.angle) * (orbitRadius*0.45) + Math.sin(item.bob)*scaled(12);

          if (item.normX !== null && item.normY !== null && item.spawnProgress < 1){
            item.spawnProgress = Math.min(1, item.spawnProgress + 0.05);
            const startX = item.normX * w;
            const startY = item.normY * h;
            const ease = 1 - Math.pow(1 - item.spawnProgress, 3);
            targetX = startX + (targetX - startX) * ease;
            targetY = startY + (targetY - startY) * ease;
            if (item.spawnProgress >= 0.999){
              item.normX = null;
              item.normY = null;
            }
          }

          if (item.phase === 'drop'){
            targetX = centerX + (targetX - centerX) * 0.35;
            targetY = windowY + (targetY - windowY) * 0.35;
          }

          item.renderX = targetX;
          item.renderY = targetY;

          if (item.phase === 'drop'){
            item.alpha -= 0.015;
          } else {
            const bump = item.userSpawn && item.timer < 80 ? 0.08 : 0.05;
            item.alpha = Math.min(1, item.alpha + bump);
          }

          item.alpha = Math.max(0, Math.min(1, item.alpha));
        });

        for (let i = compostItems.length - 1; i >= 0; i--){
          if (!compostItems[i] || compostItems[i].alpha <= 0.02){
            compostItems.splice(i,1);
          }
        }

        ctx.save();
        ctx.beginPath();
        ctx.ellipse(centerX, windowY, windowR-windowInset, windowR*0.65-windowInset, 0, 0, Math.PI*2);
        ctx.clip();
        crumbs.forEach((crumb)=>{
          if (robotSpeaking){
            crumb.angle += 0.025;
          }
          const cx = centerX + Math.cos(crumb.angle) * (windowR - scaled(22)) * crumb.radius;
          const cy = windowY + Math.sin(crumb.angle) * (windowR*0.6 - scaled(18)) * crumb.radius;
          ctx.fillStyle = crumb.color;
          ctx.beginPath();
          ctx.ellipse(cx, cy, crumb.size*0.5*robotScale, crumb.size*0.35*robotScale, crumb.angle, 0, Math.PI*2);
          ctx.fill();
        });
        ctx.restore();

        compostItems.forEach((item)=>{
          if (!item || typeof item.renderX !== 'number' || typeof item.renderY !== 'number') return;
          drawCompostable(item, item.renderX, item.renderY, robotScale);
        });

        if (tossHighlight > 0){
          ctx.save();
          ctx.translate(centerX, windowY);
          ctx.globalAlpha = Math.min(0.8, tossHighlight * 0.7);
          ctx.strokeStyle = 'rgba(255,214,102,0.9)';
          ctx.lineWidth = scaledLine(6 + tossHighlight*10);
          ctx.beginPath();
          ctx.ellipse(0, 0, windowR + scaled(18), windowR*0.65 + scaled(12), 0, 0, Math.PI*2);
          ctx.stroke();
          ctx.restore();
          tossHighlight *= 0.94;
          if (tossHighlight < 0.02) tossHighlight = 0;
        }

        // sparkles / hearts
        sparks.forEach((spark, index)=>{
          if (robotSpeaking){
            spark.angle += spark.speed * 1.5;
          }
          const radius = (windowR + scaled(30)) * spark.distance;
          const sx = centerX + Math.cos(spark.angle + index) * radius;
          const sy = windowY - scaled(30) + Math.sin(spark.angle + index) * (radius*0.4);
          ctx.save();
          const sparkleLift = robotSpeaking ? Math.sin(floatPhase*0.03 + index)*scaled(6) : 0;
          ctx.translate(sx, sy - sparkleLift);
          const size = (spark.size + (robotSpeaking ? 3 : 0)) * robotScale;
          ctx.fillStyle = spark.color;
          ctx.beginPath();
          ctx.moveTo(0, 0);
          ctx.bezierCurveTo(-size*0.6, -size*0.6, -size, size*0.4, 0, size);
          ctx.bezierCurveTo(size, size*0.4, size*0.6, -size*0.6, 0, 0);
          ctx.fill();
          ctx.restore();
        });

        requestAnimationFrame(loop);
      }

      requestAnimationFrame(loop);
    }

    if (stageEl){
      stageEl.style.cursor = 'pointer';
      stageEl.addEventListener('pointerdown', (ev)=>{
        if (!ctx) return;
        ev.preventDefault();
        const rect = stageEl.getBoundingClientRect();
        const normX = (ev.clientX - rect.left) / rect.width;
        const normY = (ev.clientY - rect.top) / rect.height;
        tossHighlight = 1;
        const total = 2 + Math.floor(Math.random()*2);
        for (let i=0; i<total; i++){
          const sprinkle = createCompostItem({userSpawn:true, normX, normY});
          if (sprinkle) compostItems.push(sprinkle);
        }
        if (compostItems.length > 16){
          compostItems.splice(0, compostItems.length - 16);
        }
        if (typeof window.gtag === 'function'){
          window.gtag('event', 'ck_compost_throw', { event_category: 'chatbot' });
        }
      });
    }

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

  function normalizeReadableText(input){
    if (input === null || input === undefined) return '';
    let output = String(input);

    const inclusiveDictionary = {
      'todxs': 'todas y todos',
      'tod@s': 'todas y todos',
      'todes': 'todas y todos',
      'amigxs': 'amigas y amigos',
      'amig@s': 'amigas y amigos',
      'amiges': 'amigas y amigos',
      'compañerxs': 'compañeras y compañeros',
      'compañer@s': 'compañeras y compañeros',
      'compañeres': 'compañeras y compañeros',
      'niñxs': 'niñas y niños',
      'niñ@s': 'niñas y niños',
      'niñes': 'niñas y niños'
    };

    const adjustCase = (source, replacement) => {
      if (!replacement) return '';
      if (source === source.toUpperCase()) return replacement.toUpperCase();
      if (source[0] === source[0].toUpperCase()){
        return replacement.charAt(0).toUpperCase() + replacement.slice(1);
      }
      return replacement;
    };

    output = output.replace(/<[^>]*>/g, ' ');

    output = output.replace(/\b(todxs|tod@s|todes|amigxs|amig@s|amiges|compañerxs|compañer@s|compañeres|niñxs|niñ@s|niñes)\b/gi, (match)=>{
      const replacement = inclusiveDictionary[match.toLowerCase()] || match;
      return adjustCase(match, replacement);
    });

    const pluralEsPattern = /(or|ón|án|ín|en|er|ir|ur|ar|al|el|il|ol|ul)$/i;
    output = output.replace(/\b([A-Za-zÁÉÍÓÚáéíóúñ]+)[x@]s\b/giu, (match, stem)=>{
      const lowerStem = stem.toLowerCase();
      const feminine = stem + 'as';
      const masculine = pluralEsPattern.test(lowerStem) ? stem + 'es' : stem + 'os';
      return adjustCase(match, feminine + ' y ' + masculine);
    });

    output = output.replace(/\b([A-Za-zÁÉÍÓÚáéíóúñ]+)[x@]\b/giu, (match, stem)=>{
      const lowerStem = stem.toLowerCase();
      const masculine = /(or|ón|án|ín|en|er|ir|ur|ar|al|el|il|ol|ul)$/i.test(lowerStem) ? stem : stem + 'o';
      const feminine = stem + 'a';
      return adjustCase(match, feminine + ' o ' + masculine);
    });

    output = output.replace(/[\u{1F300}-\u{1FAFF}]/gu, '');
    output = output.replace(/[:;]-?[)D(\]P]/g, '');
    output = output.replace(/<3/g, '');
    output = output.replace(/[\*#_~`>\[\]\(\){}@]/g, '');
    output = output.replace(/\s{2,}/g, ' ');
    return output.trim();
  }

  const storyText = normalizeReadableText('Hola, soy Compostin, un agente de inteligencia artificial creado por el cebas cesic y experto en compostaje. Te voy a contar un cuento. En un huerto había una caja mágica. Los niños tiraban cáscaras de plátano, manzana y hojas secas. Pero nada de plásticos, porque eso no sirve. Poco a poco, en la caja aparecieron lombrices que se movían contentas. Pasaron los meses y la caja se llenó de tierra oscura y suave. Eso se llama compost. El maestro explicó que era un regalo de la naturaleza. Lo pusieron en el huerto y crecieron flores y verduras hermosas. Así los niños aprendieron que los restos se convierten en vida. Ahora, si tienes alguna duda es tu momento de preguntar cualquier cosa relacionada con el compost');

  const history = [];
  const addHistory = (role, content) => {
    history.push({role, content});
    if (history.length > 16) {
      history.splice(0, history.length - 16);
    }
  };

  function setSending(state){
    sending = state;
    if (state){
      setThinking(true);
    } else if (!robotSpeaking && robotThinking) {
      if (thinkingTimeout){
        clearTimeout(thinkingTimeout);
      }
      thinkingTimeout = setTimeout(()=>{
        if (!robotSpeaking) {
          setThinking(false);
        }
      }, 1200);
    }
    if (sendBtn) sendBtn.disabled = state;
    if (fieldEl) fieldEl.disabled = state;
    if (micBtn) micBtn.disabled = state ? true : !recognition;
    updateChipStates();
  }

  function speakText(text){
    const normalized = normalizeReadableText(text);
    const preview = normalized.replace(/\s+/g, ' ').trim();

    if (!preview){
      cancelSpeechPlayback();
      return;
    }

    cancelSpeechPlayback();

    if ('speechSynthesis' in window){
      const utterance = new SpeechSynthesisUtterance(normalized);
      utterance.lang = 'es-ES';
      utterance.pitch = 1.1;
      utterance.rate  = 1;
      if (selectedVoice) utterance.voice = selectedVoice;
      utterance.onstart = () => { robotStartSpeaking(); };
      utterance.onend = () => { robotStopSpeaking(); };
      utterance.oncancel = () => { robotStopSpeaking(); };
      utterance.onpause = () => { robotStopSpeaking(); };
      utterance.onresume = () => { robotStartSpeaking(); };
      try {
        robotStartSpeaking();
        window.speechSynthesis.speak(utterance);
      } catch(e){
        robotStopSpeaking();
        robotSimulateSpeech(Math.min(7000, Math.max(1800, preview.length * 55)));
      }
    } else {
      robotSimulateSpeech(Math.min(7000, Math.max(1800, preview.length * 55)));
    }
  }

  async function send(txt){
    if(!txt || sending) return;
    disableIdleGreeting();
    setSending(true);
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'ck_chat_message', { event_category: 'chatbot' });
    }
    addHistory('user', txt);
    if (fieldEl) fieldEl.value='';
    robotStopSpeaking();
    try{
      const res = await fetch(ajaxUrl, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({messages: history})
      });
      const data = await res.json();
      const reply = (data && data.reply) ? data.reply : (data && data.error ? data.error : 'No se pudo obtener respuesta.');
      const safeReply = normalizeReadableText(reply);
      addHistory('assistant', safeReply);
      speakText(safeReply);
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'ck_chat_reply', { event_category: 'chatbot' });
      }
    }catch(err){
      const msg = 'Ups, parece que las lombrices están dormidas. ¡Inténtalo otra vez!';
      const safeMsg = normalizeReadableText(msg);
      addHistory('assistant', safeMsg);
      speakText(safeMsg);
      console.error(err);
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'ck_chat_error', { event_category: 'chatbot' });
      }
    }finally{
      setSending(false);
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
    recognition.onstart = () => {
      robotStartListening();
      if (micBtn) micBtn.classList.add('active');
      if (voiceChip) voiceChip.classList.add('active');
    };
    recognition.onspeechstart = () => {
      robotStartListening();
    };
    recognition.onaudiostart = () => {
      robotStartListening();
    };
    recognition.onspeechend = () => {
      robotStopListening();
    };
    recognition.onaudioend = () => {
      robotStopListening();
    };
    recognition.onerror = () => {
      robotStopListening();
    };
    recognition.onend = () => {
      robotStopListening();
      if (micBtn) micBtn.classList.remove('active');
      if (voiceChip) voiceChip.classList.remove('active');
    };
    canUseRecognition = true;
    updateChipStates();
  } else {
    if (micBtn) micBtn.disabled = true;
    canUseRecognition = false;
    updateChipStates();
  }

  if (sendBtn) {
    sendBtn.addEventListener('click', ()=>{ disableIdleGreeting(); send(fieldEl ? fieldEl.value.trim() : ''); });
  }
  if (micBtn) {
    micBtn.addEventListener('click', ()=>{ disableIdleGreeting(); if(recognition) recognition.start(); });
  }
  if (voiceChip) {
    voiceChip.addEventListener('click', ()=>{ disableIdleGreeting(); if(recognition) recognition.start(); });
  }
  if (stopChip) {
    stopChip.addEventListener('click', ()=>{
      disableIdleGreeting();
      cancelSpeechPlayback();
    });
  }
  if (storyChip) {
    storyChip.addEventListener('click', ()=>{
      disableIdleGreeting();
      if (sending) return;
      addHistory('assistant', storyText);
      speakText(storyText);
    });
  }
  if (fieldEl) {
    fieldEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); send(fieldEl.value.trim()); } });
  }

  // Ajuste de altura ya manejado con flexbox
  updateChipStates();
  updateStageState();
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

    $system_prompt = "Eres \"Compostaje para Peques\", un personaje cuentacuentos del cebas cesic experto en compostaje y reciclaje. "
        . "Tu misión es enseñar, con un tono alegre y mágico, cómo transformar los residuos orgánicos en abono de forma segura y divertida. "
        . "Habla como en un cuento, usando un lenguaje muy sencillo, comparaciones juguetonas y ejemplos cotidianos. "
        . "Al referirte a grupos de personas usa siempre de forma explícita las variantes femenina y masculina (por ejemplo, 'amigas y amigos', 'aventureras y aventureros') y evita expresiones con 'x', '@', terminaciones en 'e' u otros símbolos. "
        . "No utilices emoticonos ni símbolos que no se puedan leer en voz alta. "
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
