<?php
/**
 * Plugin Name: TransFastGlobal Shipment Tracking
 * Description: Add and manage package shipments from the WordPress dashboard, and let customers track them anywhere with the [tfg_tracking] shortcode.
 * Version: 1.0.0
 * Author: TransFastGlobal
 */

if (!defined('ABSPATH')) exit; // no direct access

/* -------------------------------------------------------------------------
 * 1. Register the "Shipment" custom post type (shows up as its own menu)
 * ---------------------------------------------------------------------- */
add_action('init', function () {
    register_post_type('tfg_shipment', array(
        'labels' => array(
            'name'          => 'Shipments',
            'singular_name' => 'Shipment',
            'add_new'       => 'Add Shipment',
            'add_new_item'  => 'Add New Shipment',
            'edit_item'     => 'Edit Shipment',
            'menu_name'     => 'Shipments',
            'search_items'  => 'Search Shipments',
        ),
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-cart',
        'supports'  => array('title'),
    ));
});

/* -------------------------------------------------------------------------
 * 2. Details form on the shipment edit screen
 * ---------------------------------------------------------------------- */
add_action('add_meta_boxes', function () {
    add_meta_box('tfg_details', 'Shipment Details', 'tfg_details_box', 'tfg_shipment', 'normal', 'high');
});

function tfg_details_box($post) {
    wp_nonce_field('tfg_save', 'tfg_nonce');
    $get = function ($k) use ($post) { return esc_attr(get_post_meta($post->ID, $k, true)); };
    $status_cur = get_post_meta($post->ID, 'tfg_status', true);
    $events     = esc_textarea(get_post_meta($post->ID, 'tfg_events', true));
    ?>
    <style>
      .tfg-field{margin:14px 0}
      .tfg-field label{display:block;font-weight:600;margin-bottom:5px}
      .tfg-field input,.tfg-field textarea,.tfg-field select{width:100%;max-width:540px}
      .tfg-hint{color:#666;font-size:12px;margin-top:4px}
    </style>
    <p class="tfg-hint">The <strong>Title</strong> at the top of this page is the <strong>tracking number</strong>
       the customer types in, e.g. <code>TFG100001</code>.</p>

    <div class="tfg-field">
      <label>Status</label>
      <select name="tfg_status">
        <?php foreach (array('Label Created','In Transit','Out for Delivery','Delivered','Exception') as $s) {
            echo '<option ' . selected($status_cur, $s, false) . '>' . esc_html($s) . '</option>';
        } ?>
      </select>
    </div>

    <div class="tfg-field"><label>Service</label>
      <input name="tfg_service" value="<?php echo $get('tfg_service'); ?>" placeholder="Ground Shipping"></div>

    <div class="tfg-field"><label>Estimated delivery (ETA)</label>
      <input name="tfg_eta" value="<?php echo $get('tfg_eta'); ?>" placeholder="Jul 28, 2026"></div>

    <div class="tfg-field"><label>Delivered on <em>(fill only when delivered)</em></label>
      <input name="tfg_delivered_on" value="<?php echo $get('tfg_delivered_on'); ?>" placeholder="Jul 22, 9:31 AM"></div>

    <div class="tfg-field"><label>From</label>
      <input name="tfg_from" value="<?php echo $get('tfg_from'); ?>" placeholder="Los Angeles, CA"></div>

    <div class="tfg-field"><label>To</label>
      <input name="tfg_to" value="<?php echo $get('tfg_to'); ?>" placeholder="Phoenix, AZ"></div>

    <div class="tfg-field">
      <label>Tracking events — newest first, one per line</label>
      <textarea name="tfg_events" rows="7" placeholder="Out for delivery | Jul 24, 7:42 AM | Phoenix, AZ
Departed sort facility | Jul 23, 6:10 AM | Ontario, CA
Picked up | Jul 22, 2:10 PM | Los Angeles, CA
Label created | Jul 22, 10:02 AM | Shipper"><?php echo $events; ?></textarea>
      <p class="tfg-hint">Format each line as: <code>Title | Time | Place</code> (Place is optional).</p>
    </div>
    <?php
}

/* -------------------------------------------------------------------------
 * 3. Save the details
 * ---------------------------------------------------------------------- */
add_action('save_post_tfg_shipment', function ($post_id) {
    if (!isset($_POST['tfg_nonce']) || !wp_verify_nonce($_POST['tfg_nonce'], 'tfg_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach (array('tfg_status','tfg_service','tfg_eta','tfg_delivered_on','tfg_from','tfg_to','tfg_events') as $k) {
        if (!isset($_POST[$k])) continue;
        $val = ($k === 'tfg_events')
            ? sanitize_textarea_field(wp_unslash($_POST[$k]))
            : sanitize_text_field(wp_unslash($_POST[$k]));
        update_post_meta($post_id, $k, $val);
    }
});

/* -------------------------------------------------------------------------
 * 4. AJAX endpoint: look up a shipment by tracking number
 * ---------------------------------------------------------------------- */
add_action('wp_ajax_tfg_track', 'tfg_track_handler');
add_action('wp_ajax_nopriv_tfg_track', 'tfg_track_handler');
function tfg_track_handler() {
    $num  = isset($_REQUEST['num']) ? sanitize_text_field(wp_unslash($_REQUEST['num'])) : '';
    $norm = strtoupper(preg_replace('/[\s-]+/', '', $num));

    $q = new WP_Query(array(
        'post_type'      => 'tfg_shipment',
        'posts_per_page' => 200,
        'post_status'    => 'publish',
    ));

    $found = null;
    foreach ($q->posts as $p) {
        if (strtoupper(preg_replace('/[\s-]+/', '', $p->post_title)) === $norm) { $found = $p; break; }
    }
    if (!$found) { wp_send_json(array('found' => false)); }

    $events = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) get_post_meta($found->ID, 'tfg_events', true)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts    = array_map('trim', explode('|', $line));
        $events[] = array(
            'title' => isset($parts[0]) ? $parts[0] : '',
            'time'  => isset($parts[1]) ? $parts[1] : '',
            'place' => isset($parts[2]) ? $parts[2] : '',
        );
    }

    wp_send_json(array(
        'found'       => true,
        'number'      => $found->post_title,
        'status'      => get_post_meta($found->ID, 'tfg_status', true),
        'service'     => get_post_meta($found->ID, 'tfg_service', true),
        'eta'         => get_post_meta($found->ID, 'tfg_eta', true),
        'deliveredOn' => get_post_meta($found->ID, 'tfg_delivered_on', true),
        'from'        => get_post_meta($found->ID, 'tfg_from', true),
        'to'          => get_post_meta($found->ID, 'tfg_to', true),
        'events'      => $events,
    ));
}

/* -------------------------------------------------------------------------
 * 5. [tfg_tracking] shortcode — the customer-facing tracker
 * ---------------------------------------------------------------------- */
add_shortcode('tfg_tracking', function () {
    $ajax = esc_url(admin_url('admin-ajax.php'));
    ob_start(); ?>
    <div class="tfg-track" data-ajax="<?php echo $ajax; ?>">
      <div class="tfg-box">
        <h3>📦 Track your shipment</h3>
        <div class="tfg-row">
          <input type="text" class="tfg-input" placeholder="Enter tracking number (e.g. TFG100001)">
          <button type="button" class="tfg-btn">Track</button>
        </div>
        <div class="tfg-result" style="display:none"></div>
      </div>
    </div>

    <style>
      .tfg-track{--p:#4d148c;--pl:#6b1fb8;--o:#ff6600;--bg:#191925;--b:#2a2a3a;--t:#f2f2f7;--d:#a0a0b4;
        font-family:inherit;max-width:620px;margin:0 auto}
      .tfg-box{background:var(--bg);border:1px solid var(--b);border-radius:14px;padding:26px;color:var(--t)}
      .tfg-box h3{margin:0 0 14px;font-size:19px}
      .tfg-row{display:flex;gap:10px}
      .tfg-input{flex:1;background:#1c1c2a;border:1px solid var(--b);color:#fff;padding:14px 16px;border-radius:8px;font-size:15px;outline:none}
      .tfg-input:focus{border-color:var(--o)}
      .tfg-btn{border:0;background:var(--o);color:#111;font-weight:700;padding:0 22px;border-radius:8px;cursor:pointer;font-size:14px}
      .tfg-result{margin-top:20px}
      .tfg-badge{display:inline-block;background:rgba(255,102,0,.15);color:var(--o);padding:5px 12px;border-radius:20px;font-size:13px;font-weight:700;margin-bottom:16px}
      .tfg-badge.delivered{background:rgba(46,204,113,.15);color:#2ecc71}
      .tfg-badge.exception{background:rgba(231,76,60,.15);color:#e74c3c}
      .tfg-route{display:flex;flex-wrap:wrap;gap:20px;margin:0 0 20px;padding:14px 16px;background:#1c1c2a;border:1px solid var(--b);border-radius:10px}
      .tfg-route>div{display:flex;flex-direction:column;gap:3px}
      .tfg-route span{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--d)}
      .tfg-route b{font-size:14px;color:var(--t)}
      .tfg-tl{margin-left:8px}
      .tfg-tl .i{position:relative;padding:0 0 22px 26px;border-left:2px solid var(--b)}
      .tfg-tl .i:last-child{border-left-color:transparent;padding-bottom:0}
      .tfg-tl .i::before{content:"";position:absolute;left:-8px;top:2px;width:14px;height:14px;border-radius:50%;background:var(--o);box-shadow:0 0 0 4px rgba(255,102,0,.18)}
      .tfg-tl .ti{font-weight:700;font-size:15px}
      .tfg-tl .su{color:var(--d);font-size:13px}
      .tfg-none{text-align:center;color:var(--d);padding:16px 8px}
      .tfg-none strong{color:var(--t);display:block;font-size:16px;margin:6px 0}
    </style>

    <script>
    (function(){
      var root = document.currentScript.closest('.tfg-track') ||
                 document.querySelector('.tfg-track:last-of-type');
      if(!root) return;
      var ajax = root.getAttribute('data-ajax');
      var input = root.querySelector('.tfg-input');
      var btn   = root.querySelector('.tfg-btn');
      var out   = root.querySelector('.tfg-result');
      function esc(x){var d=document.createElement('div');d.textContent=(x==null?'':x);return d.innerHTML;}

      function render(raw, s){
        if(!s || !s.found){
          out.innerHTML = '<div class="tfg-none"><div style="font-size:34px">🔍</div>'+
            '<strong>No shipment found for "'+esc(raw)+'"</strong>'+
            'Double-check the number. A brand-new label can take a few hours to appear.</div>';
          return;
        }
        var st = s.status || 'In Transit';
        var delivered = /deliver/i.test(st), exception = /(exception|held|delay|failed)/i.test(st);
        var cls = delivered ? 'delivered' : (exception ? 'exception' : '');
        var eta = delivered ? ('Delivered '+(s.deliveredOn||s.eta||'')) : ('Est. delivery '+(s.eta||'—'));
        var h = '<span class="tfg-badge '+cls+'">● '+esc(st)+' — '+esc(eta)+'</span>';
        if(s.from||s.to||s.service){
          h += '<div class="tfg-route">'+
            (s.from?'<div><span>From</span><b>'+esc(s.from)+'</b></div>':'')+
            (s.to?'<div><span>To</span><b>'+esc(s.to)+'</b></div>':'')+
            (s.service?'<div><span>Service</span><b>'+esc(s.service)+'</b></div>':'')+'</div>';
        }
        h += '<div class="tfg-tl">';
        (s.events||[]).forEach(function(e){
          h += '<div class="i"><div class="ti">'+esc(e.title)+'</div>'+
               '<div class="su">'+esc(e.time)+(e.place?(' · '+esc(e.place)):'')+'</div></div>';
        });
        h += '</div>';
        out.innerHTML = h;
      }

      function go(){
        var raw = (input.value||'').trim();
        if(!raw){ input.focus(); return; }
        out.style.display = 'block';
        out.innerHTML = '<div class="tfg-none"><div style="font-size:28px">⏳</div><strong>Looking up your shipment…</strong></div>';
        fetch(ajax+'?action=tfg_track&num='+encodeURIComponent(raw))
          .then(function(r){return r.json();})
          .then(function(d){ render(raw, d); })
          .catch(function(){ render(raw, null); });
      }
      btn.addEventListener('click', go);
      input.addEventListener('keydown', function(e){ if(e.key==='Enter') go(); });
    })();
    </script>
    <?php
    return ob_get_clean();
});
