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

/* Hide topbar phone numbers everywhere on header to avoid linebreaking */
.phone-headers {
    display: none !important;
}

/* Header Action Buttons & Alignment */
.site-header .l-container {
    display: flex !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
}

/* Language Switcher Container */
.ifw-header-lang-wrap {
    display: inline-flex;
    align-items: center;
    position: relative;
    z-index: 14;
    margin-left: 8px;
}

/* Language Switcher Button (matches Login Button dimensions and typography) */
.ifw-header-lang-btn {
    font-family: Antonio, sans-serif !important;
    font-size: 1.0625rem !important;
    font-weight: 700 !important;
    letter-spacing: .01875rem !important;
    line-height: 1.2941176471 !important;
    text-transform: uppercase !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: .8125rem 1.15rem .9375rem !important;
    border-radius: .1875rem !important;
    background: #1f1b1c !important;
    color: #fecc56 !important;
    border: 1px solid #fecc56 !important;
    cursor: pointer !important;
    transition: background-color .2s ease-out, color .2s ease-out, border-color .2s ease-out !important;
    box-sizing: border-box !important;
    text-decoration: none !important;
    gap: 8px !important;
    white-space: nowrap !important;
}

.ifw-header-lang-btn:hover, .ifw-header-lang-btn:focus {
    background-color: #fecc56 !important;
    color: #1f1b1c !important;
}

.ifw-header-lang-btn:hover svg, .ifw-header-lang-btn:focus svg {
    stroke: #1f1b1c !important;
}

/* Dropdown Menu */
.ifw-lang-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: #141011;
    border: 1px solid #fecc56;
    border-radius: 4px;
    padding: 6px;
    min-width: 170px;
    max-width: calc(100vw - 20px);
    max-height: 290px;
    overflow-y: auto;
    box-shadow: 0 16px 40px rgba(0,0,0,0.95);
    z-index: 9999999;
}

.ifw-lang-option {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 8px 12px !important;
    color: #ffffff !important;
    text-decoration: none !important;
    font-size: 13px !important;
    font-family: canada-type-gibson, sans-serif !important;
    font-weight: 500 !important;
    border-radius: 3px !important;
    transition: background-color 0.15s ease-out, color 0.15s ease-out !important;
}

.ifw-lang-option:hover {
    background-color: #fecc56 !important;
    color: #1f1b1c !important;
    font-weight: 700 !important;
}

/* Mobile Header Viewport Adjustments */
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
        margin-left: 4px !important;
        margin-right: 4px !important;
        border-radius: 3px !important;
        z-index: 10 !important;
    }
    
    .ifw-header-lang-wrap {
        margin-left: auto !important;
        margin-right: 4px !important;
    }
    
    .ifw-header-lang-btn {
        font-size: 0.85rem !important;
        line-height: 1.2 !important;
        padding: 0.45rem 0.65rem !important;
        border-radius: 3px !important;
        gap: 5px !important;
    }

    .ifw-lang-dropdown-menu {
        right: 0 !important;
        left: auto !important;
        top: calc(100% + 4px) !important;
        max-height: 240px !important;
    }
}

@keyframes ifwPopIn {
    from { opacity: 0; transform: scale(0.94); }
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

var ifwLangMeta = {
    'en': { name: 'English', flag: '🇺🇸' },
    'es': { name: 'Español', flag: '🇪🇸' },
    'fr': { name: 'Français', flag: '🇫🇷' },
    'de': { name: 'Deutsch', flag: '🇩🇪' },
    'it': { name: 'Italiano', flag: '🇮🇹' },
    'pt': { name: 'Português', flag: '🇵🇹' },
    'ar': { name: 'العربية', flag: '🇸🇦' },
    'zh-CN': { name: '中文', flag: '🇨🇳' },
    'ru': { name: 'Русский', flag: '🇷🇺' },
    'nl': { name: 'Nederlands', flag: '🇳🇱' },
    'ja': { name: '日本語', flag: '🇯🇵' },
    'tr': { name: 'Türkçe', flag: '🇹🇷' }
};

function setPublicLanguage(langCode, langName, langFlag) {
    if (!langCode) return;
    try {
        localStorage.setItem('ifw_portal_lang', langCode);
        if (langName) localStorage.setItem('ifw_portal_lang_name', langName);
        if (langFlag) localStorage.setItem('ifw_portal_lang_flag', langFlag);
        
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
        var currentLang = localStorage.getItem('ifw_portal_lang') || 'en';
        var meta = ifwLangMeta[currentLang] || { name: 'English', flag: '🇺🇸' };

        // A. Inject Language Selector into public header
        var headerContainer = document.querySelector('.site-header > .l-container') || document.querySelector('.site-header');
        if (headerContainer && !document.getElementById('ifw-header-lang-switcher')) {
            var langWrap = document.createElement('div');
            langWrap.id = 'ifw-header-lang-switcher';
            langWrap.className = 'ifw-header-lang-wrap';
            langWrap.innerHTML = `
                <div style="position:relative; display:inline-block;">
                    <button type="button" id="ifwLangBtn" class="ifw-header-lang-btn" aria-label="Select Language">
                        <span id="ifwLangFlag">${meta.flag}</span>
                        <span id="ifwLangName">${meta.name}</span>
                        <svg style="width:11px; height:11px; margin-left:3px; flex-shrink:0; stroke:#fecc56; fill:none; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; transition:stroke .2s;" viewBox="0 0 24 24">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div id="ifwLangMenu" class="ifw-lang-dropdown-menu">
                        <div style="font-size:10px; color:#fecc56; text-transform:uppercase; font-weight:700; padding:6px 8px; border-bottom:1px solid #333; margin-bottom:4px; letter-spacing:0.5px; font-family:Antonio, sans-serif;">Select Language</div>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('en', 'English', '🇺🇸')" class="ifw-lang-option"><span>🇺🇸 English</span> <small style="color:#94a3b8;">EN</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('es', 'Español', '🇪🇸')" class="ifw-lang-option"><span>🇪🇸 Español</span> <small style="color:#94a3b8;">ES</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('fr', 'Français', '🇫🇷')" class="ifw-lang-option"><span>🇫🇷 Français</span> <small style="color:#94a3b8;">FR</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('de', 'Deutsch', '🇩🇪')" class="ifw-lang-option"><span>🇩🇪 Deutsch</span> <small style="color:#94a3b8;">DE</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('it', 'Italiano', '🇮🇹')" class="ifw-lang-option"><span>🇮🇹 Italiano</span> <small style="color:#94a3b8;">IT</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('pt', 'Português', '🇵🇹')" class="ifw-lang-option"><span>🇵🇹 Português</span> <small style="color:#94a3b8;">PT</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('ar', 'العربية', '🇸🇦')" class="ifw-lang-option"><span>🇸🇦 العربية</span> <small style="color:#94a3b8;">AR</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('zh-CN', '中文', '🇨🇳')" class="ifw-lang-option"><span>🇨🇳 中文</span> <small style="color:#94a3b8;">ZH</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('ru', 'Русский', '🇷🇺')" class="ifw-lang-option"><span>🇷🇺 Русский</span> <small style="color:#94a3b8;">RU</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('nl', 'Nederlands', '🇳🇱')" class="ifw-lang-option"><span>🇳🇱 Nederlands</span> <small style="color:#94a3b8;">NL</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('ja', '日本語', '🇯🇵')" class="ifw-lang-option"><span>🇯🇵 日本語</span> <small style="color:#94a3b8;">JA</small></a>
                        <a href="javascript:void(0)" onclick="setPublicLanguage('tr', 'Türkçe', '🇹🇷')" class="ifw-lang-option"><span>🇹🇷 Türkçe</span> <small style="color:#94a3b8;">TR</small></a>
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

        // B. Ensure mobile offcanvas menu drawer has top clean LOGIN & SUBMIT AN ENQUIRY buttons (NO EMOJIS)
        var offcanvasMenu = document.querySelector('.offcanvas-menu__list') || document.querySelector('#menu-main-menu-1') || document.querySelector('.site-offcanvas nav') || document.querySelector('.offcanvas-menu');
        if (offcanvasMenu && !document.getElementById('ifw-mobile-drawer-actions')) {
            var drawerActions = document.createElement('div');
            drawerActions.id = 'ifw-mobile-drawer-actions';
            drawerActions.innerHTML = `
                <div style="padding:16px 14px; background:#141011; border-bottom:2px solid #fecc56; margin-bottom:15px; border-radius:4px;">
                    <a href="/client/login.php" style="display:block; width:100%; text-align:center; background:#fecc56; color:#1f1b1c; font-weight:700; font-family:Antonio, sans-serif; font-size:1.0625rem; padding:.8125rem 1rem; border-radius:.1875rem; text-decoration:none; text-transform:uppercase; letter-spacing:.01875rem; margin-bottom:10px; border:none; box-sizing:border-box;">
                        LOGIN
                    </a>
                    <a href="/contact/" style="display:block; width:100%; text-align:center; background:transparent; color:#fecc56; border:1px solid #fecc56; font-weight:700; font-family:Antonio, sans-serif; font-size:1.0625rem; padding:.8125rem 1rem; border-radius:.1875rem; text-decoration:none; text-transform:uppercase; letter-spacing:.01875rem; margin-bottom:12px; box-sizing:border-box;">
                        SUBMIT AN ENQUIRY
                    </a>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding-top:12px; border-top:1px solid rgba(255,255,255,0.15);">
                        <span style="color:#cbd5e1; font-size:13px; font-family:Antonio, sans-serif; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">LANGUAGE:</span>
                        <select onchange="setPublicLanguage(this.value)" style="background:#1f1b1c; color:#fecc56; border:1px solid #fecc56; border-radius:3px; padding:6px 10px; font-size:13px; font-weight:bold; font-family:canada-type-gibson, sans-serif;">
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
            <div class="ifw-submission-feedback-card" style="background:#11151c; border:2px solid #fecc56; border-radius:8px; padding:28px 22px; text-align:center; color:#fff; box-shadow:0 14px 40px rgba(0,0,0,0.7); margin-top:20px; font-family:canada-type-gibson, sans-serif;">
                <div style="width:56px; height:56px; border-radius:50%; background:rgba(34,197,94,0.12); border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h3 style="color:#fecc56; font-size:20px; font-weight:700; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; font-family:Antonio, sans-serif;">CONFIDENTIAL ENQUIRY RECEIVED</h3>
                <p style="color:#cbd5e1; font-size:13.5px; line-height:1.6; max-width:540px; margin:0 auto 16px;">
                    Your submission has been securely encrypted and routed to our Forensics &amp; Legal Investigations Desk for case evaluation.
                </p>
                <div style="background:#181d27; border:1px solid #2e384d; border-radius:6px; padding:16px 18px; margin-bottom:16px; text-align:left; font-size:13px; line-height:1.6;">
                    <div style="margin-bottom:10px; color:#f8fafc; border-bottom:1px solid #252e3e; padding-bottom:8px;">
                        <strong style="color:#94a3b8; text-transform:uppercase; font-size:11px; letter-spacing:0.5px; display:block;">Contact Email:</strong>
                        <span style="color:#fecc56; font-weight:600; font-size:14px;">${clientEmail}</span>
                    </div>
                    <div style="margin-bottom:10px; color:#f8fafc; border-bottom:1px solid #252e3e; padding-bottom:8px;">
                        <strong style="color:#94a3b8; text-transform:uppercase; font-size:11px; letter-spacing:0.5px; display:block;">Case Tracking ID:</strong>
                        <span style="color:#ffffff; font-family:monospace; font-weight:bold; font-size:13px;">${refId}</span>
                    </div>
                    <div style="color:#cbd5e1;">
                        <strong style="color:#94a3b8; text-transform:uppercase; font-size:11px; letter-spacing:0.5px; display:block; margin-bottom:4px;">Next Steps &amp; Communications Advisory:</strong>
                        <p style="margin:0 0 8px 0; color:#e2e8f0; font-size:12.5px;">
                            A Case Officer will evaluate your submission and contact you directly at <strong style="color:#fecc56;">${clientEmail}</strong> within <strong>1 to 4 business hours</strong>.
                        </p>
                        <p style="margin:0; color:#94a3b8; font-size:12px;">
                            To ensure confidential case updates and reports are delivered directly to your primary inbox without delay, please whitelist <strong style="color:#fecc56;">${contactPrimary}</strong> in your email client.
                        </p>
                    </div>
                </div>
                <div style="font-size:11px; color:#64748b;">
                    256-Bit TLS Encrypted Transmission &bull; Confidential &amp; Privileged
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
            <div style="background:#11151c; border:2px solid #fecc56; border-radius:8px; max-width:500px; width:100%; padding:28px 24px; text-align:center; color:#fff; box-shadow:0 24px 60px rgba(0,0,0,0.85); position:relative; animation:ifwPopIn 0.3s ease-out; font-family:canada-type-gibson, sans-serif;">
                <button type="button" onclick="document.getElementById('ifw-submission-modal-overlay').remove()" style="position:absolute; top:12px; right:15px; background:transparent; border:0; color:#94a3b8; font-size:20px; cursor:pointer; font-weight:bold;">&times;</button>
                <div style="width:56px; height:56px; border-radius:50%; background:rgba(34,197,94,0.12); border:2px solid #22c55e; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <h3 style="color:#fecc56; font-size:20px; font-weight:700; margin-bottom:8px; text-transform:uppercase; font-family:Antonio, sans-serif; letter-spacing:0.5px;">ENQUIRY RECEIVED</h3>
                <p style="color:#cbd5e1; font-size:13.5px; line-height:1.5; margin-bottom:16px;">
                    Your case details have been securely transmitted to our Forensics Team.
                </p>
                <div style="background:#181d27; border:1px solid #2e384d; border-radius:6px; padding:14px 16px; text-align:left; margin-bottom:18px; font-size:12.5px; line-height:1.5;">
                    <div style="color:#f8fafc; margin-bottom:8px;">
                        <span style="color:#94a3b8; text-transform:uppercase; font-size:11px; display:block;">Registered Email:</span>
                        <strong style="color:#fecc56; font-size:13.5px;">${clientEmail}</strong>
                    </div>
                    <div style="color:#cbd5e1; font-size:12px; border-top:1px solid #252e3e; padding-top:8px;">
                        <strong style="color:#ffffff;">Communications Notice:</strong> Please whitelist <strong style="color:#fecc56;">${contactPrimary}</strong> in your email contacts so responses reach your primary inbox without delay.
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('ifw-submission-modal-overlay').remove()" style="background:#fecc56; color:#1f1b1c; border:0; padding:12px 24px; font-weight:bold; font-family:Antonio, sans-serif; font-size:14px; text-transform:uppercase; letter-spacing:0.5px; border-radius:4px; cursor:pointer; width:100%;">
                    RETURN TO SITE
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
