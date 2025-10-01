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
  .header{ position:relative; padding:18px 20px; background:var(--mut); border-bottom:1px solid var(--bd); display:flex; align-items:center; gap:20px; }
  .header img{ max-height:125px; display:block; flex-shrink:0; }
  .header .text{ flex:1; }
  .title{ margin:4px 0 2px; font-size: clamp(26px,5vw,40px); font-weight:800; }
  .desc{ margin:0; font-size: clamp(18px,3vw,26px); color:#4b5563; }
  .chips{ display:flex; gap:12px; flex-wrap:wrap; justify-content:center; padding:12px; background:var(--mut2); border-bottom:1px solid #eef2f7; overflow-x:auto; scroll-snap-type:x mandatory; }
  .chip{ scroll-snap-align:start; padding:10px 16px; border-radius:999px; border:1px solid var(--chip-b); background:var(--chip); cursor:pointer; font-size:clamp(18px,2.8vw,24px); color:var(--chip-text); white-space:nowrap; box-shadow:0 2px 0 rgba(0,0,0,.02); transition: background .15s,border-color .15s,transform .08s }
  .chip:hover{ background:#eef2ff; border-color:#c7d2fe; }
  .chip:active{ transform: translateY(1px); }
  .chip[disabled]{ opacity:.5; cursor:not-allowed; }
  .msgs{ flex:1; display:flex; flex-direction:column; background:#fff; overflow:hidden; }
  .bot-stage{ position:relative; flex:1; min-height:0; border-bottom:1px solid #f3d1dc; background:linear-gradient(180deg,#ffeef6 0%,#fff9d7 100%); display:flex; align-items:center; justify-content:center; padding:12px 18px 20px; }
  .bot-stage::before{ content:''; position:absolute; inset:14px 18px 60px; background:rgba(255,255,255,0.7); border-radius:26px; box-shadow:0 12px 24px rgba(255,107,107,0.25); z-index:0; transition:transform .6s ease, box-shadow .6s ease; }
  .bot-stage.is-speaking::before{ transform:scale(1.02); box-shadow:0 18px 34px rgba(255,107,107,0.45); }
  canvas.bot-canvas{ position:relative; z-index:1; width:100%; height:100%; display:block; }
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
        ${logoUrl ? `<img src="${logoUrl}" alt="Agente IA Compostaje CEBAS Kids">` : ''}
        <div class="text">
          <div class="title">Agente IA Compostaje CEBAS Kids</div>
          <p class="desc">Un rincón mágico del CEBAS-CSIC donde una Inteligencia Artificial te enseña a compostar como en un cuento.</p>
        </div>
      </div>
      <div class="chips" id="chips">
        <button class="chip" data-q="¿Qué cositas puedo echar en mi compostera mágica?">¿Qué cositas puedo echar en mi compostera mágica?</button>
        <button class="chip" data-q="¿Por qué el compost hace feliz al huerto?">¿Por qué el compost hace feliz al huerto?</button>
        <button class="chip" data-q="¿Cuánto tarda la poción del compost?">¿Cuánto tarda la poción del compost?</button>
        <button class="chip" data-q="¿Qué bichitos ayudan en el compost?">¿Qué bichitos ayudan en el compost?</button>
      </div>
      <div class="msgs" id="msgs">
        <div class="bot-stage" id="botStage">
          <canvas class="bot-canvas" id="botCanvas"></canvas>
        </div>
      </div>
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
  const stageEl = root.getElementById('botStage');
  const canvas = root.getElementById('botCanvas');
  const fieldEl = root.getElementById('field');
  const sendBtn = root.getElementById('send');
  const micBtn  = root.getElementById('mic');
  const chips   = root.getElementById('chips');
  let sending = false;
  let recognition = null;
  let selectedVoice = null;
  let robotSpeaking = false;
  let fallbackSpeechTimeout = null;

  const ctx = canvas && canvas.getContext ? canvas.getContext('2d') : null;
  const stageSize = { width: 0, height: 0 };
  const crumbs = ctx ? Array.from({length:7}, () => ({ angle: Math.random()*Math.PI*2, radius: 0.28 + Math.random()*0.35, size: 6 + Math.random()*5, color: Math.random() > 0.5 ? '#8ed16f' : '#b47b36' })) : [];
  const sparks = ctx ? Array.from({length:6}, () => ({ angle: Math.random()*Math.PI*2, distance: 0.65 + Math.random()*0.2, speed: 0.005 + Math.random()*0.01, size: 6 + Math.random()*3, color: Math.random() > 0.5 ? '#ffadad' : '#9bf6ff' })) : [];
  let blinkTimer = 150;
  let blinkTarget = 0;
  let blinkValue = 0;
  let mouthValue = 0.2;
  let floatPhase = 0;

  function updateStageState(){
    if (!stageEl) return;
    stageEl.classList.toggle('is-speaking', robotSpeaking);
  }

  function robotStartSpeaking(){
    clearTimeout(fallbackSpeechTimeout);
    robotSpeaking = true;
    updateStageState();
  }

  function robotStopSpeaking(){
    clearTimeout(fallbackSpeechTimeout);
    robotSpeaking = false;
    updateStageState();
  }

  function robotSimulateSpeech(duration){
    robotStartSpeaking();
    fallbackSpeechTimeout = setTimeout(()=>{ robotStopSpeaking(); }, duration);
  }

  if (ctx){
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

      function loop(){
        if (!stageSize.width || !stageSize.height){
          requestAnimationFrame(loop);
          return;
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

        const groundY = h*0.78 + (robotSpeaking ? Math.sin(floatPhase*0.02)*3 : 0);
        ctx.fillStyle = '#b8f5a3';
        ctx.beginPath();
        ctx.moveTo(0, groundY);
        ctx.quadraticCurveTo(w*0.25, groundY-24, w*0.5, groundY-10);
        ctx.quadraticCurveTo(w*0.8, groundY+8, w, groundY-18);
        ctx.lineTo(w, h);
        ctx.lineTo(0, h);
        ctx.closePath();
        ctx.fill();

        ctx.fillStyle = '#7bd7f0';
        ctx.beginPath();
        ctx.moveTo(0, groundY);
        ctx.bezierCurveTo(w*0.2, groundY-18, w*0.35, groundY-6, w*0.5, groundY-12);
        ctx.bezierCurveTo(w*0.7, groundY-24, w*0.85, groundY-6, w, groundY-20);
        ctx.lineTo(w, groundY);
        ctx.closePath();
        ctx.fill();

        const bob = robotSpeaking ? Math.sin(floatPhase*0.05) * 10 : 0;
        const centerX = w/2;
        const baseY = groundY - 16 + bob;
        const bodyW = Math.min(220, w*0.55);
        const bodyH = Math.min(170, h*0.5);
        const bodyX = centerX - bodyW/2;
        const bodyY = baseY - bodyH;

        // shadow
        ctx.fillStyle = 'rgba(0,0,0,0.08)';
        ctx.beginPath();
        ctx.ellipse(centerX, baseY+8, bodyW*0.45, 18, 0, 0, Math.PI*2);
        ctx.fill();

        // legs
        ctx.strokeStyle = '#2f8f67';
        ctx.lineCap = 'round';
        ctx.lineWidth = 12;
        ctx.beginPath();
        ctx.moveTo(centerX - bodyW*0.22, baseY-6);
        ctx.lineTo(centerX - bodyW*0.22, baseY+10);
        ctx.moveTo(centerX + bodyW*0.22, baseY-6);
        ctx.lineTo(centerX + bodyW*0.22, baseY+10);
        ctx.stroke();

        // body
        ctx.fillStyle = '#57cc99';
        ctx.strokeStyle = '#2f8f67';
        ctx.lineWidth = 6;
        drawRoundedRect(bodyX, bodyY, bodyW, bodyH, 32);
        ctx.fill();
        ctx.stroke();

        // lid
        const lidH = bodyH*0.18;
        const lidY = bodyY - lidH*0.45;
        ctx.fillStyle = '#38a3a5';
        drawRoundedRect(bodyX + bodyW*0.08, lidY, bodyW*0.84, lidH, 20);
        ctx.fill();

        // antenna
        ctx.strokeStyle = '#2f8f67';
        ctx.lineWidth = 5;
        ctx.beginPath();
        const antennaWave = robotSpeaking ? Math.sin(floatPhase*0.09)*6 : 0;
        ctx.moveTo(centerX, lidY);
        ctx.quadraticCurveTo(centerX, lidY-30 - antennaWave, centerX, lidY-48 - antennaWave);
        ctx.stroke();
        ctx.fillStyle = '#ffd166';
        ctx.beginPath();
        ctx.arc(centerX, lidY-56 - antennaWave, 10, 0, Math.PI*2);
        ctx.fill();

        // face area
        const faceH = bodyH*0.42;
        const faceY = bodyY + bodyH*0.12;
        const faceX = bodyX + bodyW*0.1;
        const faceW = bodyW*0.8;
        ctx.fillStyle = '#9bf6ff';
        drawRoundedRect(faceX, faceY, faceW, faceH, 24);
        ctx.fill();

        // cheeks
        ctx.fillStyle = '#ffcad4';
        ctx.beginPath();
        ctx.ellipse(faceX + faceW*0.18, faceY + faceH*0.65, 16, 12, 0, 0, Math.PI*2);
        ctx.ellipse(faceX + faceW*0.82, faceY + faceH*0.65, 16, 12, 0, 0, Math.PI*2);
        ctx.fill();

        const eyeOpen = Math.max(0.1, 1 - blinkValue);
        const eyeW = 24;
        const eyeH = 18 * eyeOpen;
        const eyeY = faceY + faceH*0.42;
        const eyeOffset = faceW*0.26;
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.ellipse(centerX - eyeOffset, eyeY, eyeW, Math.max(6, eyeH), 0, 0, Math.PI*2);
        ctx.ellipse(centerX + eyeOffset, eyeY, eyeW, Math.max(6, eyeH), 0, 0, Math.PI*2);
        ctx.fill();

        ctx.fillStyle = '#1f3b4d';
        const pupilBob = robotSpeaking ? Math.sin(floatPhase*0.05)*3 : 0;
        ctx.beginPath();
        ctx.ellipse(centerX - eyeOffset, eyeY + pupilBob, 10, Math.max(4, eyeH*0.4), 0, 0, Math.PI*2);
        ctx.ellipse(centerX + eyeOffset, eyeY + pupilBob, 10, Math.max(4, eyeH*0.4), 0, 0, Math.PI*2);
        ctx.fill();

        // mouth
        const mouthW = faceW*0.42;
        const mouthH = 10 + 28*mouthValue;
        const mouthY = faceY + faceH*0.72;
        ctx.fillStyle = '#ff9f1c';
        drawRoundedRect(centerX - mouthW/2, mouthY - mouthH/2, mouthW, mouthH, 14);
        ctx.fill();
        ctx.fillStyle = '#1f3b4d';
        ctx.fillRect(centerX - mouthW*0.35, mouthY - 3, mouthW*0.7, 6);

        // arms
        const armY = faceY + faceH*0.78;
        const wave = robotSpeaking ? Math.sin(floatPhase*0.08) * 22 : 0;
        ctx.strokeStyle = '#38a3a5';
        ctx.lineCap = 'round';
        ctx.lineWidth = 12;
        ctx.beginPath();
        ctx.moveTo(bodyX + 12, armY);
        ctx.quadraticCurveTo(bodyX - bodyW*0.25, armY - wave - 10, bodyX - bodyW*0.18, armY + wave);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(bodyX + bodyW - 12, armY);
        ctx.quadraticCurveTo(bodyX + bodyW + bodyW*0.25, armY - wave - 10, bodyX + bodyW + bodyW*0.18, armY + wave);
        ctx.stroke();

        ctx.fillStyle = '#ff6b6b';
        ctx.beginPath();
        ctx.ellipse(bodyX - bodyW*0.2, armY + wave, 14, 14, 0, 0, Math.PI*2);
        ctx.ellipse(bodyX + bodyW + bodyW*0.2, armY + wave, 14, 14, 0, 0, Math.PI*2);
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
        ctx.ellipse(centerX, windowY, windowR-8, windowR*0.65-8, 0, 0, Math.PI*2);
        ctx.fill();

        ctx.save();
        ctx.beginPath();
        ctx.ellipse(centerX, windowY, windowR-8, windowR*0.65-8, 0, 0, Math.PI*2);
        ctx.clip();
        crumbs.forEach((crumb)=>{
          if (robotSpeaking){
            crumb.angle += 0.025;
          }
          const cx = centerX + Math.cos(crumb.angle) * (windowR-22) * crumb.radius;
          const cy = windowY + Math.sin(crumb.angle) * (windowR*0.6-18) * crumb.radius;
          ctx.fillStyle = crumb.color;
          ctx.beginPath();
          ctx.ellipse(cx, cy, crumb.size*0.5, crumb.size*0.35, crumb.angle, 0, Math.PI*2);
          ctx.fill();
        });
        ctx.restore();

        // sparkles / hearts
        sparks.forEach((spark, index)=>{
          if (robotSpeaking){
            spark.angle += spark.speed * 1.5;
          }
          const radius = (windowR + 30) * spark.distance;
          const sx = centerX + Math.cos(spark.angle + index) * radius;
          const sy = windowY - 30 + Math.sin(spark.angle + index) * (radius*0.4);
          ctx.save();
          const sparkleLift = robotSpeaking ? Math.sin(floatPhase*0.03 + index)*6 : 0;
          ctx.translate(sx, sy - sparkleLift);
          const size = spark.size + (robotSpeaking ? 3 : 0);
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

  const history = [];
  const addHistory = (role, content) => {
    history.push({role, content});
    if (history.length > 16) {
      history.splice(0, history.length - 16);
    }
  };

  function setSending(state){
    sending = state;
    sendBtn.disabled = state;
    fieldEl.disabled = state;
    micBtn.disabled = state ? true : !recognition;
    Array.from(chips.children).forEach(b=>b.disabled = state);
  }

  function speakText(text){
    const clean = text
      .replace(/[\u{1F300}-\u{1FAFF}]/gu, '')
      .replace(/[\*#_~`>\[\]\(\){}]/g, '')
      .replace(/<[^>]*>/g, '');
    const preview = clean.replace(/\s+/g, ' ').trim();

    if ('speechSynthesis' in window){
      robotStopSpeaking();
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance((clean || text || '').trim());
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
        window.speechSynthesis.speak(utterance);
      } catch(e){
        if (preview){
          robotStopSpeaking();
          robotSimulateSpeech(Math.min(7000, Math.max(1800, preview.length * 55)));
        }
      }
    } else if (preview){
      robotStopSpeaking();
      robotSimulateSpeech(Math.min(7000, Math.max(1800, preview.length * 55)));
    }
  }

  const welcomeMessage = '¡Hola, peque aventurerx del compost! Soy tu amigue del CEBAS-CSIC. Juntxs haremos magia con las cáscaras y las hojas para alimentar a las plantas. ¿Qué te gustaría saber?';
  setTimeout(()=>{
    addHistory('assistant', welcomeMessage);
    speakText(welcomeMessage);
  }, 2000);

  async function send(txt){
    if(!txt || sending) return;
    setSending(true);
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'ck_chat_message', { event_category: 'chatbot' });
    }
    addHistory('user', txt);
    fieldEl.value='';
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
      addHistory('assistant', reply);
      speakText(reply);
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'ck_chat_reply', { event_category: 'chatbot' });
      }
    }catch(err){
      const msg = 'Ups, parece que las lombrices están dormidas. ¡Inténtalo otra vez!';
      addHistory('assistant', msg);
      speakText(msg);
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
