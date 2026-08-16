<?php
// includes/chat_widget.php
// Master Public Site Enhancer: Chatwoot/Tawk.to Integration, Language Switcher, Mobile Header Login, & Public Form Feedback
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\\' || preg_match('/^[A-Z]:\\\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';

$chat_provider = get_setting($pdo, 'chat_provider', 'chatwoot');
$tawkto_raw = trim(get_setting($pdo, 'tawkto_property_id', ''));
$chatwoot_token = trim(get_setting($pdo, 'chatwoot_website_token', 'uHR3DJPM8AZ2Lpo8tDdJ5tei'));
$chatwoot_url = trim(get_setting($pdo, 'chatwoot_base_url', 'https://app.chatwoot.com'));
?>

<style>
/* ==========================================================================
   PUBLIC WEBSITE GLOBAL MOBILE HEADER & LANGUAGE SWITCHER STYLES
   ========================================================================== */
.goog-te-banner-frame.skiptranslate, 
.goog-te-banner-frame,
#goog-gt-tt,
.goog-te-balloon-frame,
.goog-tooltip,
.goog-tooltip:hover {
    display: none !important;
    visibility: hidden !important;
}
body {
    top: 0px !important;
    position: static !important;
}
#google_translate_element {
    display: none !important;
}
.skiptranslate iframe {
    display: none !important;
}
font {
    background-color: transparent !important;
    box-shadow: none !important;
}

/* Mobile Header Login Button Visibility */
@media screen and (max-width: 47.9375em) {
    .site-header__book[href*="/client/login.php"] {
        position: static !important;
        width: auto !important;
        height: auto !important;
        overflow: visible !important;
        clip: auto !important;
        white-space: nowrap !important;
        display: inline-flex !important;
        align-items: center !important;
        padding: 0.45rem 0.75rem !important;
        font-size: 0.85rem !important;
        line-height: 1.2 !important;
        margin-left: auto !important;
        margin-right: 0.5rem !important;
        border-radius: 3px !important;
        z-index: 10 !important;
    }
}

.ifw-header-lang-wrap {
    display: inline-flex;
    align-items: center;
    margin-right: 0.6rem;
    z-index: 12;
}

@keyframes ifwPopIn {
    from { opacity: 0; transform: scale(0.92); }
    to { opacity: 1; transform: scale(1); }
}
</style>

<!-- Google Translate Mount Element & Engine -->
<div id="google_translate_element" style="display:none !important;"></div>
<script type="text/javascript">
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        autoDisplay: false,
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
}

function setPublicLanguage(langCode, langName) {
    if (!langCode) return;
    try {
        localStorage.setItem('ifw_portal_lang', langCode);
        if (langName) localStorage.setItem('ifw_portal_lang_name', langName);
        
        var host = window.location.hostname;
        document.cookie = "googtrans=/en/" + langCode + "; path=/; domain=" + host;
        document.cookie = "googtrans=/en/" + langCode + "; path=/;";
        
        var select = document.querySelector('.goog-te-combo');
        if (select) {
            select.value = langCode;
            select.dispatchEvent(new Event('change'));
        } else {
            location.reload();
        }
    } catch(e) {
        location.reload();
    }
}

// 1. Auto-inject header & mobile menu Language Switcher and Mobile Login Buttons
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // A. Inject Language Selector into public header
        var headerContainer = document.querySelector('.site-header > .l-container') || document.querySelector('.site-header');
        if (headerContainer && !document.getElementById('ifw-header-lang-switcher')) {
            var langWrap = document.createElement('div');
            langWrap.id = 'ifw-header-lang-switcher';
            langWrap.className = 'ifw-header-lang-wrap';
            langWrap.innerHTML = `
                <div class="ifw-lang-dropdown" style="position:relative; display:inline-block;">
                    <button type="button" id="ifwLangBtn" style="background:#272223; color:#fecc56; border:1px solid rgba(254,204,86,0.6); padding:5px 9px; border-radius:20px; font-size:11px; font-weight:bold; cursor:pointer; display:flex; align-items:center; gap:4px; font-family:sans-serif;">
                        <span>🌐</span> <span id="ifwLangCurrent">EN</span> <span style="font-size:8px;">▼</span>
                    </button>
                    <div id="ifwLangMenu" style="display:none; position:absolute; right:0; top:calc(100% + 5px); background:#181415; border:1px solid #fecc56; border-radius:8px; padding:6px; min-width:160px; max-height:320px; overflow-y:auto; box-shadow:0 10px 25px rgba(0,0,0,0.8); z-index:999999;">
                        <div style="font-size:10px; color:#fecc56; text-transform:uppercase; font-weight:bold; padding:4px 8px; border-bottom:1px solid #333; margin-bottom:4px;">Select Language</div>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('en', 'English')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇺🇸 English</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('es', 'Español')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇪🇸 Español</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('fr', 'Français')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇫🇷 Français</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('de', 'Deutsch')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇩🇪 Deutsch</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('it', 'Italiano')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇮🇹 Italiano</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('pt', 'Português')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇵🇹 Português</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('ar', 'العربية')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇸🇦 العربية</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('zh-CN', '中文')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇨🇳 中文</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('ru', 'Русский')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇷🇺 Русский</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('nl', 'Nederlands')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇳🇱 Nederlands</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('ja', '日本語')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇯🇵 日本語</a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('tr', 'Türkçe')" style="display:flex; align-items:center; gap:8px; padding:6px 8px; color:#fff; text-decoration:none; font-size:12px; border-radius:4px;">🇹🇷 Türkçe</a>
                    </div>
                </div>
            `;
            
            var toggleBtn = headerContainer.querySelector('.site-header__toggle');
            if (toggleBtn) {
                headerContainer.insertBefore(langWrap, toggleBtn);
            } else {
                headerContainer.appendChild(langWrap);
            }
            
            var btn = document.getElementById('ifwLangBtn');
            var menu = document.getElementById('ifwLangMenu');
            if (btn && menu) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
                });
                document.addEventListener('click', function() {
                    menu.style.display = 'none';
                });
            }
        }
        
        // Sync language indicator
        var current = localStorage.getItem('ifw_portal_lang') || 'en';
        var curSpan = document.getElementById('ifwLangCurrent');
        if (curSpan) curSpan.textContent = current.toUpperCase().slice(0,2);

        // B. Ensure mobile offcanvas menu drawer has top prominent Login button & Language picker
        var offcanvasMenu = document.querySelector('.offcanvas-menu__list') || document.querySelector('#menu-main-menu-1') || document.querySelector('.site-offcanvas nav') || document.querySelector('.offcanvas-menu');
        if (offcanvasMenu && !document.getElementById('ifw-mobile-drawer-actions')) {
            var drawerActions = document.createElement('div');
            drawerActions.id = 'ifw-mobile-drawer-actions';
            drawerActions.innerHTML = `
                <div style="padding:16px 14px; background:#141011; border-bottom:2px solid #fecc56; margin-bottom:15px; border-radius:4px;">
                    <a href="/client/login.php" style="display:flex; align-items:center; justify-content:center; background:#fecc56; color:#1f1b1c; font-weight:bold; font-family:Antonio, sans-serif; font-size:15px; padding:12px; border-radius:4px; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px; box-shadow:0 4px 12px rgba(254,204,86,0.3); margin-bottom:10px;">
                        <span style="margin-right:8px;">🔒</span> Client Portal Login
                    </a>
                    <a href="/contact/" style="display:flex; align-items:center; justify-content:center; background:transparent; color:#fecc56; border:1px solid #fecc56; font-weight:bold; font-family:Antonio, sans-serif; font-size:13px; padding:10px; border-radius:4px; text-decoration:none; text-transform:uppercase; margin-bottom:12px;">
                        <span style="margin-right:8px;">📝</span> Submit An Enquiry
                    </a>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding-top:10px; border-top:1px solid rgba(255,255,255,0.15);">
                        <span style="color:#cbd5e1; font-size:12px;">🌐 Language:</span>
                        <select onchange="setPublicLanguage(this.value)" style="background:#272223; color:#fecc56; border:1px solid #fecc56; border-radius:4px; padding:4px 8px; font-size:12px; font-weight:bold;">
                            <option value="en">🇺🇸 English</option>
                            <option value="es">🇪🇸 Español</option>
                            <option value="fr">🇫🇷 Français</option>
                            <option value="de">🇩🇪 Deutsch</option>
                            <option value="it">🇮🇹 Italiano</option>
                            <option value="pt">🇵🇹 Português</option>
                            <option value="ar">🇸🇦 العربية</option>
                            <option value="zh-CN">🇨🇳 中文</option>
                            <option value="ru">🇷🇺 Русский</option>
                            <option value="nl">🇳🇱 Nederlands</option>
                            <option value="ja">🇯🇵 日本語</option>
                            <option value="tr">🇹🇷 Türkçe</option>
                        </select>
                    </div>
                </div>
            `;
            offcanvasMenu.parentNode.insertBefore(drawerActions, offcanvasMenu);
        }
    });
})();

// 2. Universal Public Form Feedback Modal & Whitelist Card Interceptor
(function() {
    function renderFeedbackCard(data, form, respDiv) {
        var refId = data.ref_id || 'IFW-' + Math.random().toString(36).substr(2, 6).toUpperCase();
        var clientEmail = data.client_email || 'your email';
        var contactPrimary = data.contact_email_primary || 'notifications@ifwglobalrecovery.site';
        var contactSecondary = data.contact_email_secondary || 'investigations@ifwglobalrecovery.site';
        
        var cardHtml = `
            <div class="ifw-submission-feedback-card" style="background:#11151c; border:2px solid #fecc56; border-radius:12px; padding:28px 20px; text-align:center; color:#fff; box-shadow:0 12px 36px rgba(0,0,0,0.6); margin-top:20px; font-family:canada-type-gibson, sans-serif;">
                <div style="width:60px; height:60px; border-radius:50%; background:rgba(34,197,94,0.15); border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h3 style="color:#fecc56; font-size:22px; font-weight:700; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; font-family:Antonio, sans-serif;">Enquiry Dispatched Successfully</h3>
                <p style="color:#cbd5e1; font-size:14px; line-height:1.6; max-width:540px; margin:0 auto 16px;">
                    Thank you for reaching out to <strong>IFW Global Private Intelligence &amp; Asset Recovery</strong>. Your confidential file has been assigned to our Senior Forensics &amp; Legal Liaison Desk for immediate conflict check and case evaluation.
                </p>
                <div style="background:#1a202c; border:1px solid #334155; border-radius:8px; padding:16px; margin-bottom:16px; text-align:left;">
                    <div style="display:flex; align-items:flex-start; margin-bottom:10px;">
                        <span style="font-size:16px; margin-right:10px; line-height:1;">⚡</span>
                        <div style="font-size:13px; color:#f8fafc;">
                            <strong>Expected Response:</strong> A Senior Case Officer will contact you via email at <strong style="color:#fecc56;">${clientEmail}</strong> within <strong>1 to 4 business hours</strong>.
                        </div>
                    </div>
                    <div style="display:flex; align-items:flex-start;">
                        <span style="font-size:16px; margin-right:10px; line-height:1;">🛡️</span>
                        <div style="font-size:13px; color:#94a3b8; line-height:1.5;">
                            <strong>Important Next Step:</strong> Please whitelist &amp; add <strong style="color:#fecc56;">${contactPrimary}</strong> and <strong style="color:#fecc56;">${contactSecondary}</strong> to your email contacts/safe senders list to ensure secure reports and case updates are not delayed or routed to Spam/Junk.
                        </div>
                    </div>
                </div>
                <div style="font-size:12px; color:#64748b; border-top:1px solid #2d3748; padding-top:10px;">
                    Official Case Tracking Code: <span style="color:#fecc56; font-family:monospace; font-weight:bold; font-size:13px;">${refId}</span> &bull; 256-Bit TLS Encrypted
                </div>
            </div>
        `;
        
        if (respDiv) {
            respDiv.innerHTML = cardHtml;
            respDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        if (form) {
            form.reset();
            form.style.display = 'none';
        }
        
        showFeedbackModal(refId, clientEmail, contactPrimary, contactSecondary);
    }
    
    function showFeedbackModal(refId, clientEmail, contactPrimary, contactSecondary) {
        var existing = document.getElementById('ifw-submission-modal-overlay');
        if (existing) existing.remove();
        
        var modal = document.createElement('div');
        modal.id = 'ifw-submission-modal-overlay';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; display:flex; align-items:center; justify-content:center; padding:15px;';
        modal.innerHTML = `
            <div style="background:#11151c; border:2px solid #fecc56; border-radius:14px; max-width:520px; width:100%; padding:28px 22px; text-align:center; color:#fff; box-shadow:0 20px 50px rgba(0,0,0,0.8); position:relative; animation:ifwPopIn 0.3s ease-out; font-family:canada-type-gibson, sans-serif;">
                <button type="button" onclick="document.getElementById('ifw-submission-modal-overlay').remove()" style="position:absolute; top:12px; right:15px; background:transparent; border:0; color:#94a3b8; font-size:22px; cursor:pointer; font-weight:bold;">&times;</button>
                <div style="width:64px; height:64px; border-radius:50%; background:rgba(34,197,94,0.15); border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h3 style="color:#fecc56; font-size:22px; font-weight:700; margin-bottom:8px; text-transform:uppercase; font-family:Antonio, sans-serif; letter-spacing:0.5px;">Enquiry Received</h3>
                <p style="color:#cbd5e1; font-size:14px; line-height:1.6; margin-bottom:16px;">
                    Your case details have been securely transmitted to our Senior Forensics Team.
                </p>
                <div style="background:#1a202c; border:1px solid #334155; border-radius:8px; padding:14px; text-align:left; margin-bottom:18px;">
                    <div style="color:#f8fafc; font-size:13px; margin-bottom:8px;">
                        <strong>📧 Target Email:</strong> <span style="color:#fecc56;">${clientEmail}</span>
                    </div>
                    <div style="color:#94a3b8; font-size:12px; line-height:1.5;">
                        🛡️ <strong>Action Required:</strong> Please add <strong style="color:#fecc56;">${contactPrimary}</strong> and <strong style="color:#fecc56;">${contactSecondary}</strong> to your email contacts to ensure case updates reach your inbox safely.
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('ifw-submission-modal-overlay').remove()" style="background:#fecc56; color:#1f1b1c; border:0; padding:12px 24px; font-weight:bold; font-family:Antonio, sans-serif; font-size:15px; text-transform:uppercase; border-radius:4px; cursor:pointer; width:100%;">
                    I Understand &bull; Return to Site
                </button>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            var action = form.getAttribute('action') || '';
            if (action.indexOf('process_form.php') !== -1 || form.closest('.gform_wrapper') || form.querySelector('input[name="enquiry_type"]') || form.querySelector('input[name="first_name"]')) {
                form.addEventListener('submit', function(e) {
                    var respDiv = form.parentNode.querySelector('.formResponse') || form.querySelector('.formResponse');
                    if (!respDiv) {
                        respDiv = document.createElement('div');
                        respDiv.className = 'formResponse';
                        form.parentNode.insertBefore(respDiv, form.nextSibling);
                    }
                    
                    var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Transmitting Case Data...';
                    }
                    
                    e.preventDefault();
                    var formData = new FormData(form);
                    
                    fetch('/process_form.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.status === 'success') {
                            renderFeedbackCard(data, form, respDiv);
                        } else {
                            var errs = data.errors ? data.errors.join('<br>') : (data.message || 'An error occurred.');
                            respDiv.innerHTML = '<div style="background:#7f1d1d; color:#fff; padding:12px; border-radius:6px; margin-top:15px;">' + errs + '</div>';
                        }
                    })
                    .catch(function() {
                        respDiv.innerHTML = '<div style="background:#7f1d1d; color:#fff; padding:12px; border-radius:6px; margin-top:15px;">Network transmission failed. Please try again or call our hotline.</div>';
                    })
                    .finally(function() {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Submit Details';
                        }
                    });
                }, true);
            }
        });
    });
})();
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<?php
// Tawk.to Injection (when selected as provider)
if ($chat_provider === 'tawkto' && !empty($tawkto_raw)):
    $clean_id = preg_replace('/.*embed\.tawk\.to\//', '', trim($tawkto_raw, " \t\n\r;'\"/"));
    $placeholder_ids = ['6a742dd38875351d455643d1/default', 'YOUR_TAWKTO_PROPERTY_ID', 'your_property_id/default', 'placeholder/default'];
    if (preg_match('/^[a-zA-Z0-9_\/\-]{10,}$/', $clean_id) && !in_array(strtolower($clean_id), array_map('strtolower', $placeholder_ids), true)):
?>
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/<?php echo htmlspecialchars($clean_id, ENT_QUOTES); ?>';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
})();
</script>
<?php
    endif;
// Chatwoot Bubble Injection (when selected as provider)
elseif ($chat_provider === 'chatwoot' && !empty($chatwoot_token) && $chatwoot_token !== 'YOUR_CHATWOOT_WEBSITE_TOKEN'):
?>
<script>
(function(d,t) {
    var BASE_URL = "<?= htmlspecialchars($chatwoot_url) ?>";
    var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
    g.src = BASE_URL + "/packs/js/sdk.js";
    g.async = true;
    s.parentNode.insertBefore(g, s);
    g.onload = function() {
        window.chatwootSDK.run({
            websiteToken: '<?= htmlspecialchars($chatwoot_token) ?>',
            baseUrl: BASE_URL
        });
    }
})(document, "script");
</script>
<?php endif; ?>
