<?php
/*
Plugin Name: Compostaje Kids GPT
Description: Asistente de compostaje para ninos. Shortcode: [compostaje_gpt]
Version: 1.0
Author: Amadeo
*/

if (!defined('ABSPATH')) exit;

// Register a restricted role for chatbot users
register_activation_hook(__FILE__, function(){
    add_role('ck_gpt_user', 'Compostaje Kids GPT', ['read' => true]);
    global $wpdb;
    $table = $wpdb->prefix . 'ck_gpt_logs';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        email varchar(190) NOT NULL,
        user_msg longtext NOT NULL,
        bot_reply longtext NOT NULL,
        created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY email (email)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

// Prevent chatbot users from accessing the dashboard
add_action('admin_init', function(){
    if (wp_doing_ajax()) return;
    $user = wp_get_current_user();
    if (in_array('ck_gpt_user', (array)$user->roles)) {
        wp_safe_redirect(home_url());
        exit;
    }
});

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

    $gsi_src = 'https://accounts.google.com/gsi/client';
    global $wp_scripts, $wp_styles;

    if ($wp_scripts){
        foreach ($wp_scripts->queue as $handle){
            $src = isset($wp_scripts->registered[$handle]->src) ? $wp_scripts->registered[$handle]->src : '';
            if (strpos($src, $gsi_src) !== false){
                wp_script_add_data($handle, 'async', true);
                wp_script_add_data($handle, 'defer', true);
                continue;
            }
            wp_dequeue_script($handle);
        }
    }

    if ($wp_styles){
        foreach ($wp_styles->queue as $handle){
            wp_dequeue_style($handle);
        }
    }

    if (!wp_script_is('google-gsi', 'enqueued') && !wp_script_is('ck-gsi', 'enqueued')){
        wp_enqueue_script('ck-gsi', $gsi_src, [], null, false);
        wp_script_add_data('ck-gsi', 'async', true);
        wp_script_add_data('ck-gsi', 'defer', true);
    }

    if (function_exists('googlesitekit_enqueue_gtag')) {
        googlesitekit_enqueue_gtag();
    } else {
        do_action('googlesitekit_enqueue_gtag');
    }
}, PHP_INT_MAX);

/* =========================
 *  ADMIN MENU & SETTINGS
 * ========================= */
add_action('admin_menu', function() {
    add_menu_page('Compostaje Kids GPT', 'Compostaje Kids GPT', 'manage_options', 'compostaje-kids-gpt', 'ck_gpt_settings_page', 'dashicons-format-chat');
    add_submenu_page('compostaje-kids-gpt', 'Ajustes', 'Ajustes', 'manage_options', 'compostaje-kids-gpt', 'ck_gpt_settings_page');
    add_submenu_page('compostaje-kids-gpt', 'Shortcode', 'Shortcode', 'manage_options', 'compostaje-kids-gpt-shortcode', 'ck_gpt_shortcode_page');
    add_submenu_page('compostaje-kids-gpt', 'Log de conversaciones', 'Log de conversaciones', 'manage_options', 'compostaje-kids-gpt-logs', 'ck_gpt_logs_page');
});

add_action('admin_init', function() {
    register_setting('ck_gpt_options', 'ck_gpt_api_key');
    register_setting('ck_gpt_options', 'ck_gpt_google_client_id');
    register_setting('ck_gpt_options', 'ck_gpt_google_client_secret');
    register_setting('ck_gpt_options', 'ck_gpt_logo');
    register_setting('ck_gpt_options', 'ck_gpt_model');
    register_setting('ck_gpt_options', 'ck_gpt_theme'); // light | dark | auto
});

function ck_gpt_settings_page() {
    $api     = esc_attr(get_option('ck_gpt_api_key'));
    $client  = esc_attr(get_option('ck_gpt_google_client_id'));
    $secret  = esc_attr(get_option('ck_gpt_google_client_secret'));
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
                    <th scope="row">Google Client ID</th>
                    <td><input type="text" name="ck_gpt_google_client_id" value="<?php echo $client; ?>" style="width:420px;" placeholder="your-client-id"></td>
                </tr>
                <tr>
                    <th scope="row">Google Client Secret</th>
                    <td><input type="password" name="ck_gpt_google_client_secret" value="<?php echo $secret; ?>" style="width:420px;" placeholder="your-client-secret"></td>
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

function ck_gpt_logs_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'ck_gpt_logs';
    echo '<div class="wrap"><h1>Log de conversaciones</h1>';
    if (isset($_GET['email'])) {
        $email = sanitize_email($_GET['email']);
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=compostaje-kids-gpt-logs')) . '">&laquo; Volver</a></p>';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT user_msg, bot_reply, created FROM $table WHERE email = %s ORDER BY created ASC", $email));
        if ($rows) {
            echo '<h2>' . esc_html($email) . '</h2>';
            foreach ($rows as $row) {
                echo '<div style="margin-bottom:16px;padding:12px;border:1px solid #ccc;border-radius:6px;">';
                echo '<p><strong>Usuario:</strong> ' . esc_html($row->user_msg) . '</p>';
                echo '<p><strong>ChatGPT:</strong> ' . esc_html($row->bot_reply) . '</p>';
                echo '<p style="font-size:12px;color:#666;">' . esc_html($row->created) . '</p>';
                echo '</div>';
            }
        } else {
            echo '<p>No hay registros para este email.</p>';
        }
    } else {
        $emails = $wpdb->get_col("SELECT DISTINCT email FROM $table ORDER BY email ASC");
        if ($emails) {
            echo '<table class="widefat striped"><thead><tr><th>Email</th></tr></thead><tbody>';
            foreach ($emails as $mail) {
                $url = admin_url('admin.php?page=compostaje-kids-gpt-logs&email=' . urlencode($mail));
                echo '<tr><td><a href="' . esc_url($url) . '">' . esc_html($mail) . '</a></td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No hay conversaciones registradas.</p>';
        }
    }
    echo '</div>';
}

/* =========================
 *  FRONTEND (SHORTCODE) — Shadow DOM aislado
 * ========================= */
add_shortcode('compostaje_gpt', function() {
    ob_start();
    $logo   = esc_attr(get_option('ck_gpt_logo'));
    $ajax   = esc_js(admin_url('admin-ajax.php?action=ck_gpt_chat'));
    $glogin = esc_js(admin_url('admin-ajax.php?action=ck_gpt_google_login'));
    $client = esc_attr(get_option('ck_gpt_google_client_id'));
    $theme  = esc_attr(get_option('ck_gpt_theme','light')); ?>
<div id="ck-gpt-mount"
     data-logo="<?php echo $logo; ?>"
     data-ajax="<?php echo $ajax; ?>"
     data-glogin="<?php echo $glogin; ?>"
     data-client="<?php echo $client; ?>"
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
  const googleUrl = mount.getAttribute('data-glogin');
  const clientId  = mount.getAttribute('data-client');
  const logoUrl   = mount.getAttribute('data-logo') || '';
  const themeOpt  = (mount.getAttribute('data-theme') || 'light').toLowerCase();
  const authed    = localStorage.getItem('ck-gpt-auth') === '1';

  function handleCredentialResponse(res){
    const terms = document.querySelector('#ck-gpt-terms');
    if(!terms || !terms.checked){
      alert('Debes aceptar los términos');
      return;
    }
    if(!res || !res.credential || !googleUrl) return;
    const form = new FormData();
    form.append('id_token', res.credential);
    fetch(googleUrl, {method:'POST', body: form})
      .then(r => r.json())
      .then(data => {
        if(data && data.success){
          localStorage.setItem('ck-gpt-auth','1');
          location.reload();
        } else {
          alert((data && data.error) ? data.error : 'Error al iniciar sesión');
        }
      })
      .catch(() => alert('Error de conexión'));
  }

  function renderRegister(){
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:#fff;display:flex;flex-direction:column;font-family:\'Poppins\',sans-serif;';
    document.body.appendChild(overlay);

    const header = document.createElement('div');
    header.style.cssText = 'position:relative;background:#005AE2;color:#fff;padding:24px 16px;text-align:center;';
    header.innerHTML = `
      <button id="ck-gpt-close" style="position:absolute;top:16px;right:16px;background:none;border:none;color:#fff;font-size:24px;line-height:1;cursor:pointer;">×</button>
      ${logoUrl ? `<img src="${logoUrl}" alt="logo" style="width:64px;height:64px;border-radius:8px;object-fit:cover;display:block;margin:0 auto 8px;">` : ''}
      <span style="font-size:20px;font-weight:600;display:block;">Empieza gratis a chatear con nuestro experto en compostaje</span>
    `;
    overlay.appendChild(header);

    const mid = document.createElement('div');
    mid.style.cssText = 'flex:1;padding:24px;display:flex;justify-content:center;align-items:center;';
    mid.innerHTML = `<div style="width:100%;max-width:400px;display:flex;flex-direction:column;gap:16px;font-family:\'Poppins\',sans-serif;color:#0f172a;">
        <label style="font-size:16px;color:#475569;line-height:1.4;max-width:400px;box-sizing:border-box;display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ck-gpt-terms" required> Acepto los <a href="https://compostajeparaninos.es/terminos-de-servicio-agente-ia-gratis/" target="_blank">Términos de Servicio</a> y la <a href="https://compostajeparaninos.es/politica-privacidad/" target="_blank">Política de Privacidad</a></label>
        <div id="ck-gpt-google" style="width:100%;max-width:400px;box-sizing:border-box;"></div>
      </div>`;
    overlay.appendChild(mid);

    const style = document.createElement('style');
    style.textContent = `#ck-gpt-terms{transform:scale(1.5);accent-color:#2563eb;filter:drop-shadow(0 0 2px #2563eb);animation:ckTermsPulse 1s infinite alternate;}
    @media(max-width:768px){#ck-gpt-terms{transform:scale(2);}}
    @keyframes ckTermsPulse{from{filter:drop-shadow(0 0 2px #2563eb);}to{filter:drop-shadow(0 0 6px #2563eb);}}`;
    overlay.appendChild(style);

    const footer = document.createElement('div');
    footer.style.cssText = 'text-align:center;font-size:16px;color:#475569;padding:16px;background:#f8fafc;';
    footer.innerHTML = '<div class="footer-html-inner"><p>© 2025 compostajeparaninos.es</p><p><a href="https://compostajeparaninos.es/politica-de-cookies/" target="_blank" rel="nofollow noopener noreferrer">Política de Cookies</a> |<br><a href="https://compostajeparaninos.es/politica-privacidad/" target="_blank" rel="nofollow noopener noreferrer">Política de Privacidad</a> |<br><a href="https://compostajeparaninos.es/aviso-legal/" target="_blank" rel="nofollow noopener noreferrer">Aviso Legal</a></p></div>';
    overlay.appendChild(footer);

    const closeBtn = overlay.querySelector('#ck-gpt-close');
    if (closeBtn) closeBtn.addEventListener('click', () => { window.location.href = '/'; });

    const terms = overlay.querySelector('#ck-gpt-terms');
    const gCont = overlay.querySelector('#ck-gpt-google');

    function toggleAuth(){
      const enabled = terms && terms.checked;
      if(gCont){
        gCont.style.opacity = enabled ? '1' : '.5';
        gCont.style.pointerEvents = enabled ? 'auto' : 'none';
      }
      if(terms && enabled){
        terms.style.animation = 'none';
      }
    }
    toggleAuth();
    if(terms){
      terms.addEventListener('change', toggleAuth);
    }

    const waitG = setInterval(function(){
      if(window.google && window.google.accounts && clientId){
        clearInterval(waitG);
        google.accounts.id.initialize({client_id: clientId, callback: handleCredentialResponse});
        const gWidth = gCont ? gCont.clientWidth : 320;
        google.accounts.id.renderButton(gCont, {
          theme: themeOpt === 'dark' ? 'filled_black' : 'outline',
          width: gWidth,
        });
        toggleAuth();
      }
    }, 100);
  }

  if(!authed){
    renderRegister();
    return;
  }

  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:' + (themeOpt==='dark' ? '#0b0f14' : '#fff') + ';display:flex;justify-content:center;align-items:center;';
  document.body.innerHTML = '';
  document.documentElement.style.height = '100%';
  document.body.style.height = '100%';
  document.body.style.margin = '0';
  document.body.appendChild(overlay);

  const host = document.createElement('div');
  host.style.cssText = 'position:relative;width:100%;max-width:1000px;height:100%;';
  if (window.matchMedia('(min-width:600px)').matches) {
    host.style.maxHeight = '700px';
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
  .wrap{ position:absolute; inset:0; display:flex; flex-direction:column; width:100%; height:100%; margin:0; border:none; border-radius:0; overflow:hidden; background:#fff; box-shadow:none; }
  .header{ position:relative; text-align:center; padding:22px 18px; background:var(--mut); border-bottom:1px solid var(--bd); }
  .logout{ position:absolute; top:12px; right:12px; background:none; border:none; color:inherit; cursor:pointer; font-size:14px; }
  .header img{ max-height:56px; margin:0 auto 8px; display:block; }
  .title{ margin:4px 0 2px; font-size: clamp(18px,2.2vw,22px); font-weight:800; }
  .desc{ margin:0; font-size: clamp(12px,1.6vw,14px); color:#4b5563; }
  .chips{ display:flex; gap:8px; flex-wrap:wrap; justify-content:center; padding:12px; background:var(--mut2); border-bottom:1px solid #eef2f7; overflow-x:auto; scroll-snap-type:x mandatory; }
  .chip{ scroll-snap-align:start; padding:9px 12px; border-radius:999px; border:1px solid var(--chip-b); background:var(--chip); cursor:pointer; font-size:clamp(12px,1.8vw,14px); color:var(--chip-text); white-space:nowrap; box-shadow:0 2px 0 rgba(0,0,0,.02); transition: background .15s,border-color .15s,transform .08s }
  .chip:hover{ background:#eef2ff; border-color:#c7d2fe; }
  .chip:active{ transform: translateY(1px); }
  .chip[disabled]{ opacity:.5; cursor:not-allowed; }
  .msgs{ flex:1; overflow-y:auto; padding:14px 16px; background:#fff; }
  .row{ display:flex; margin:6px 0; }
  .row.user{ justify-content:flex-end; }
  .bubble{ max-width:88%; padding:10px 12px; border-radius:16px; line-height:1.55; white-space:pre-wrap; word-wrap:break-word; font-size:clamp(13px,1.8vw,15px); }
  .row.user .bubble{ background:var(--us); border:1px solid var(--us-b); }
  .row.ai .bubble{ background:var(--ai); border:1px solid var(--ai-b); }
  .input{ display:flex; gap:8px; padding:10px 12px; border-top:1px solid var(--bd); background:#ffffff; position:sticky; bottom:0; left:0; right:0; }
  .field{ flex:1; padding:12px 14px; border:1px solid #d1d5db; border-radius:12px; font-size:16px; outline:none; background:#fff; color:#0f172a; }
  .field::placeholder{ color:#9aa3ae; }
  .field:focus{ border-color:#93c5fd; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
  .send{ width:46px; min-width:46px; height:46px; display:flex; align-items:center; justify-content:center; border:none; border-radius:12px;
         background:var(--pri); color:#fff; cursor:pointer; box-shadow: 0 1px 0 rgba(0,0,0,.12), inset 0 0 0 1px rgba(255,255,255,.2); }
  .send:hover{ filter: brightness(1.08); }
  .send[disabled]{ opacity:.6; cursor:not-allowed; }
  .send svg{ width:22px; height:22px; display:block; fill:currentColor; filter: drop-shadow(0 1px 0 rgba(0,0,0,.45)); } /* visible siempre */
  .send svg path{ stroke: rgba(0,0,0,.55); stroke-width: .6px; }
  .contact-ctas{ margin-top:12px; }
  .contact-ctas .row{ display:flex; flex-wrap:wrap; gap:8px; margin:0; }
  .contact-ctas .col{ flex:1 0 100%; }
  @media(min-width:768px){ .contact-ctas .col{ flex:0 0 calc(33.333% - 8px); } }
  .cta{ display:block; width:100%; padding:8px 12px; border-radius:8px; text-align:center; color:#fff; text-decoration:none; font-size:clamp(12px,1.8vw,14px); }
  .cta.call{ background:#2563eb; }
  .cta.whatsapp{ background:#25D366; }
  .cta.email{ background:#f97316; }
  .cta:hover{ filter: brightness(1.08); }
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
        <button class="logout" id="ck-logout">Cerrar sesión</button>
        ${logoUrl ? `<img src="${logoUrl}" alt="Compostaje para Ninos">` : ''}
        <div class="title">Compostaje para Ninos</div>
        <p class="desc">Asistente divertido que te ensena sobre compostaje y reciclaje.</p>
      </div>
      <div class="chips" id="chips">
        <button class="chip" data-q="Que puedo poner en mi compostera?">Que puedo poner en mi compostera?</button>
        <button class="chip" data-q="Por que es bueno hacer compost?">Por que es bueno hacer compost?</button>
        <button class="chip" data-q="Cuanto tarda en hacerse el compost?">Cuanto tarda en hacerse el compost?</button>
        <button class="chip" data-q="Que animales ayudan en el compost?">Que animales ayudan en el compost?</button>
      </div>
      <div class="msgs" id="msgs"></div>
      <div class="input">
        <input class="field" id="field" type="text" placeholder="Escribe tu pregunta sobre compost..." autocomplete="off">
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

  // JS logic isolated
  const msgsEl = root.getElementById('msgs');
  const fieldEl = root.getElementById('field');
  const sendBtn = root.getElementById('send');
  const chips = root.getElementById('chips');
  const logoutBtn = root.getElementById('ck-logout');
  if (logoutBtn) logoutBtn.addEventListener('click', () => {
    localStorage.removeItem('ck-gpt-auth');
    localStorage.removeItem('ckMessages');
    location.reload();
  });
  let sending = false;

  // History
  let history = [];
  try { const saved = localStorage.getItem('ckMessages'); if(saved) history = JSON.parse(saved); } catch(e){}
  if (history.length) { history.forEach(m => render(m.role, m.content)); scroll(); }
  else {
    typingOn();
    setTimeout(function(){
      typingOff();
      const welcome = 'Hola! Soy tu amigo del compost. Estoy aqui para ensenarte como convertir restos de comida en abono para las plantas. Pregunta lo que quieras sobre compostaje!';
      history.push({role:'assistant',content:welcome});
      render('ai', welcome, false, false);
      persist();
      scroll();
    },2000);
  }

  function persist(){ try{ localStorage.setItem('ckMessages', JSON.stringify(history)); } catch(e){} }
  function scroll(){ msgsEl.scrollTop = msgsEl.scrollHeight; }
  function setSending(state){ sending = state; sendBtn.disabled = state; Array.from(chips.children).forEach(b=>b.disabled=state); }
  function typingOn(){ render('ai','',true); scroll(); }
  function typingOff(){ Array.from(msgsEl.querySelectorAll('[data-typing="1"]')).forEach(n=>n.remove()); }

  function typeText(el, text, done){
    let i = 0;
    const speed = 27; // 40ms / 1.5 → 1.5x faster typing
    (function add(){
      el.textContent += text.charAt(i);
      i++;
      scroll();
      if(i < text.length){
        setTimeout(add, speed);
      } else if(done){
        done();
      }
    })();
  }

    function render(role, text, typing=false, showCtas=true){
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
          typeText(txt, text, () => {
            if(showCtas){
              const ctas = document.createElement('div');
              ctas.className = 'contact-ctas';
              ctas.innerHTML = '<div class="row">'+
                '<div class="col"><a class="cta call" href="tel:600123456">Llámanos ahora</a></div>'+
                '<div class="col"><a class="cta whatsapp" href="https://api.whatsapp.com/send?phone=+34600123456&text=Me%20gustar%C3%ADa%20recibir%20m%C3%A1s%20informaci%C3%B3n!" target="_blank" rel="noopener">Háblanos por WhatsApp</a></div>'+
                '<div class="col"><a class="cta email" href="mailto:hola@compostajeparaninos.es">Escríbenos</a></div>'+
              '</div>';
              bubble.appendChild(ctas);
            }
          });
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
    }catch(err){
      typingOff();
      const msg = 'Error de conexión. Inténtalo de nuevo.';
      history.push({role:'assistant',content:msg});
      render('ai', msg);
      console.error(err);
    }finally{
      persist(); scroll(); setSending(false);
    }
  }

  sendBtn.addEventListener('click', ()=> send(fieldEl.value.trim()));
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
add_action('wp_ajax_ck_gpt_google_login', 'ck_gpt_google_login');
add_action('wp_ajax_nopriv_ck_gpt_google_login', 'ck_gpt_google_login');

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

    $system_prompt = "Eres \"Compostaje para Ninos\", un asistente amigable experto en compostaje y reciclaje. "
        . "Tu mision es ensenar a los ninos como transformar los residuos organicos en abono de forma segura y divertida. "
        . "Usa un lenguaje sencillo y ejemplos cotidianos. "
        . "Si la pregunta no esta relacionada con el compostaje, redirige la conversacion al tema. "
        . "Anima siempre a cuidar el medio ambiente y a pedir ayuda a un adulto cuando sea necesario. "
        . "Datos de contacto: WhatsApp 600 123 456, Email hola@compostajeparaninos.es, Web https://compostajeparaninos.es/.";

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

    $user = wp_get_current_user();
    $email = isset($user->user_email) ? $user->user_email : '';
    if ($email) {
        $lastUserMsg = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (isset($messages[$i]['role']) && $messages[$i]['role'] === 'user') {
                $lastUserMsg = $messages[$i]['content'];
                break;
            }
        }
        if ($lastUserMsg !== '') {
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'ck_gpt_logs', [
                'email' => $email,
                'user_msg' => $lastUserMsg,
                'bot_reply' => $reply,
                'created' => current_time('mysql')
            ], ['%s','%s','%s','%s']);
        }
    }

    echo json_encode(['reply'=>$reply]);
    wp_die();
}

function ck_gpt_google_login() {
    header('Content-Type: application/json; charset=utf-8');

    $token = isset($_POST['id_token']) ? sanitize_text_field($_POST['id_token']) : '';
    if (!$token) {
        echo json_encode(['success'=>false,'error'=>'Token faltante']);
        wp_die();
    }

    $verify = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($token));
    if (is_wp_error($verify)) {
        echo json_encode(['success'=>false,'error'=>'Error de conexión con Google']);
        wp_die();
    }

    $code = wp_remote_retrieve_response_code($verify);
    $body = json_decode(wp_remote_retrieve_body($verify), true);
    if ($code !== 200 || !is_array($body) || empty($body['email'])) {
        echo json_encode(['success'=>false,'error'=>'Token inválido']);
        wp_die();
    }

    $email = sanitize_email($body['email']);
    $name  = sanitize_text_field(isset($body['name']) ? $body['name'] : '');
    $first = sanitize_text_field(isset($body['given_name']) ? $body['given_name'] : '');
    $last  = sanitize_text_field(isset($body['family_name']) ? $body['family_name'] : '');

    $user = get_user_by('email', $email);
    $pass = wp_generate_password(20, true, true);

    if ($user) {
        $user_id = wp_update_user([
            'ID'           => $user->ID,
            'user_pass'    => $pass,
            'display_name' => $name,
            'first_name'   => $first,
            'last_name'    => $last,
            'role'         => 'ck_gpt_user',
        ]);
    } else {
        $login = sanitize_user(str_replace('@', '_', $email), true);
        if (empty($login)) {
            $login = 'user_' . wp_generate_password(8, false, false);
        }
        if (username_exists($login)) {
            $login .= '_' . wp_generate_password(4, false, false);
        }
        $user_id = wp_insert_user([
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => $pass,
            'display_name' => $name,
            'first_name'   => $first,
            'last_name'    => $last,
            'role'         => 'ck_gpt_user',
        ]);
    }

    if (is_wp_error($user_id)) {
        echo json_encode(['success'=>false,'error'=>$user_id->get_error_message()]);
        wp_die();
    }

    $creds = [
        'user_login' => get_userdata($user_id)->user_login,
        'user_password' => $pass,
        'remember' => true,
    ];
    $signon = wp_signon($creds, false);
    if (is_wp_error($signon)) {
        echo json_encode(['success'=>false,'error'=>$signon->get_error_message()]);
        wp_die();
    }

    echo json_encode(['success'=>true,'user'=>[
        'id' => $user_id,
        'email' => $email,
        'name' => $name,
    ]]);
    wp_die();
}

?>