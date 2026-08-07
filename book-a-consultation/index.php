<?php
$dir = __DIR__;
while (!file_exists($dir . '/config.php')) {
    $dir = dirname($dir);
    if ($dir === '/' || $dir === '\' || preg_match('/^[A-Z]:\\$/i', $dir)) break;
}
require_once $dir . '/config.php';
require_once $dir . '/includes/functions.php';
?>
require_once '../config.php';
require_once '../includes/functions.php';

// Fetch Global Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM IFW_site_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Fetch Form Fields
$fields = $pdo->query("SELECT * FROM IFW_form_fields ORDER BY display_order ASC")->fetchAll();
?>
<!doctype html>


<html lang="en-AU" prefix="og: https://ogp.me/ns#" class="no-js ">
<head>
	

	<meta charset="UTF-8">


	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta name="theme-color" content="#fecc56">

  <link rel="apple-touch-icon" sizes="180x181" href="/wp-content/themes/rb-council/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/wp-content/themes/rb-council/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/wp-content/themes/rb-council/favicons/favicon-16x16.png">
  <link rel="manifest" href="/wp-content/themes/rb-council/favicons/site.webmanifest">
  <link rel="mask-icon" href="/wp-content/themes/rb-council/favicons/safari-pinned-tab.svg" color="#fecc56">
  <meta name="msapplication-TileColor" content="#fecc56">

	<style>
#wpadminbar #wp-admin-bar-wccp_free_top_button .ab-icon:before {
	content: "\f160";
	color: #02CA02;
	top: 3px;
}
#wpadminbar #wp-admin-bar-wccp_free_top_button .ab-icon {
	transform: rotate(45deg);
}
</style>

<!-- Google Tag Manager for WordPress by gtm4wp.com -->

<!-- End Google Tag Manager for WordPress by gtm4wp.com -->
<!-- Search Engine Optimisation by Rank Math PRO - https://rankmath.com/ -->
<title>Submit an Enquiry: IFW Global</title>
<meta name="description" content="Submit an enquiry with Ken Gamble and IFW Global. International specialists at cybercrime investigations"/>
<meta name="robots" content="follow, index, max-snippet:-1, max-video-preview:-1, max-image-preview:large"/>
<link rel="canonical" href="/contact/" />
<meta property="og:locale" content="en_US" />
<meta property="og:type" content="article" />
<meta property="og:title" content="Submit an Enquiry: IFW Global" />
<meta property="og:description" content="Submit an enquiry with Ken Gamble and IFW Global. International specialists at cybercrime investigations" />
<meta property="og:url" content="/book-a-consultation/" />
<meta property="og:updated_time" content="2026-07-29T23:19:02+10:00" />
<meta property="article:published_time" content="2022-03-03T14:18:54+11:00" />
<meta property="article:modified_time" content="2026-07-29T23:19:02+10:00" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Submit an Enquiry: IFW Global" />
<meta name="twitter:description" content="Submit an enquiry with Ken Gamble and IFW Global. International specialists at cybercrime investigations" />
<meta name="twitter:label1" content="Time to read" />
<meta name="twitter:data1" content="Less than a minute" />

<!-- /Rank Math WordPress SEO plugin -->

<link rel='dns-prefetch' href='//use.typekit.net' />
<style>html{-webkit-text-size-adjust:100%;-moz-text-size-adjust:100%;text-size-adjust:100%}body{margin:0;line-height:1}form{margin:0}fieldset{border:0;margin:0;padding:0}button,input,select,textarea{font-size:100%;font-family:inherit;margin:0;padding:0;vertical-align:baseline}button,input{line-height:normal;overflow:visible}textarea{overflow:auto;padding:0;vertical-align:top}input[type=search]{-webkit-appearance:textfield;box-sizing:content-box}input[type=search]::-webkit-search-decoration{-webkit-appearance:none}:focus{outline:0}input[type=checkbox],input[type=radio]{box-sizing:border-box;padding:0}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}figure{margin:0}img{-ms-interpolation-mode:bicubic;display:block}ol,ul{margin:0;padding:0}dd,dl{margin:0}li{display:block;list-style:none;margin:0;padding:0}h1,h2,h3,h4,h5,h6{font-weight:inherit;line-height:inherit;font-size:inherit;margin:0}p{margin:0}blockquote{margin:0}pre{white-space:pre;white-space:pre-wrap;word-wrap:break-word;margin:0;font-family:inherit;font-size:inherit}cite{font-style:normal}ins{text-decoration:none}dfn{font-style:inherit}del{text-decoration:none}mark{background:0 0;color:inherit}address{font-style:normal}code,kbd,samp,tt{font-family:inherit;font-size:inherit}small{font-size:100%}q{quotes:none}q:after,q:before{content:"";content:none}a{font-weight:inherit;color:inherit;text-decoration:none}a:active,a:hover{outline:0}a:focus{outline:0}a img{border:none}sub,sup{font-size:75%;line-height:0;position:relative}sup{top:-.5em}sub{bottom:-.25em}article,aside,details,figcaption,figure,footer,header,hgroup,menu,nav,section{display:block}table{border-collapse:collapse;border-spacing:0}th{font-weight:inherit}td{vertical-align:top}@font-face{font-family:Antonio;font-style:normal;font-weight:700;src:local(""),url(/wp-content/themes/rb-council/fonts/antonio-v8-latin-700.woff2) format("woff2"),url(/wp-content/themes/rb-council/fonts/antonio-v8-latin-700.woff) format("woff")}*,:after,:before{box-sizing:border-box;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}body,html{min-width:20rem;margin:0;color:#231f20;font-family:canada-type-gibson,sans-serif;font-size:1rem;font-weight:400;line-height:1.5}html{background-color:#231f20;scroll-behavior:smooth}@media (prefers-reduced-motion){html{scroll-behavior:auto}}@media screen and (max-width:37.5625em){html.logged-in{margin-top:0!important;padding-top:2.875rem}}body{position:relative;background:#fff}.grey-page body{background-color:#f5f5f5}body.no-scroll{overflow:hidden}img{max-width:100%;height:auto}@media screen and (max-width:37.5625em){#wpadminbar{position:fixed}}::-moz-selection{background:#231f20;color:#fff;text-shadow:none}::selection{background:#231f20;color:#fff;text-shadow:none}strong{font-weight:700}button,input[type=submit]{cursor:pointer}p:empty{display:none}.js-focus-visible :focus:not(.focus-visible){outline:0;box-shadow:none!important}.l-container{max-width:87.5rem;margin:0 auto;padding:0 1.25rem}@media (min-width:64em){.l-container{padding:0 1.875rem}}@media (min-width:80em){.l-container{padding:0 3.75rem}}.l-container--sm{max-width:50rem}.l-container--md{max-width:64.25rem}.l-container--lg{max-width:95rem}.l-grid{display:flex;flex-wrap:wrap;margin:0 -.625rem}@media (min-width:80em){.l-grid{margin:0 -1.25rem}}.l-grid--with-margin{margin-top:-1.875rem}@media screen and (min-width:80em){.l-grid--with-margin{margin-top:-3.75rem}}.l-grid--column{flex-direction:column}.l-grid--between{justify-content:space-between}.l-grid--stretch{align-items:stretch}.l-grid__cell{width:100%;padding:0 .625rem}@media (min-width:80em){.l-grid__cell{padding:0 1.25rem}}.l-grid--with-margin .l-grid__cell{margin-top:1.875rem}@media (min-width:80em){.l-grid--with-margin .l-grid__cell{margin-top:3.75rem}}.l-grid__cell--50{width:50%}@media (min-width:28.75em){.l-grid__cell--50-at-sm{width:50%}}@media (min-width:35em){.l-grid__cell--50-at-smd{width:50%}}@media (min-width:48em){.l-grid__cell--33-at-md{width:33.33%}.l-grid__cell--50-at-md{width:50%}.l-grid__cell--66-at-md{width:66.66%}}@media (min-width:64em){.l-grid__cell--8-at-lg{width:8.33333333%}.l-grid__cell--25-at-lg{width:25%}.l-grid__cell--30-at-lg{width:30%}.l-grid__cell--33-at-lg{width:33.33%}.l-grid__cell--41-at-lg{width:41%}.l-grid__cell--50-at-lg{width:50%}.l-grid__cell--64-at-lg{width:64.5%}.l-grid__cell--66-at-lg{width:66.66%}.l-grid__cell--70-at-lg{width:70%}.l-grid__cell--75-at-lg{width:75%}.l-grid__cell--100-at-lg{width:100%}}@media (min-width:80em){.l-grid__cell--20-at-ds{width:20%}.l-grid__cell--25-at-ds{width:25%}.l-grid__cell--33-at-ds{width:33.33%}.l-grid__cell--40-at-ds{width:40%}.l-grid__cell--42-at-ds{width:41.66666667%}.l-grid__cell--45-at-ds{width:45%}.l-grid__cell--50-at-ds{width:50%}.l-grid__cell--55-at-ds{width:55%}.l-grid__cell--66-at-ds{width:66.66%}.l-grid__cell--75-at-ds{width:75%}.l-grid__cell--80-at-ds{width:80%}.l-grid__cell--100-at-ds{width:100%}}@media (min-width:87.5em){.l-grid__cell--50-at-xl{width:50%}}.l-section{margin-bottom:3.125rem}.l-section--bordered{padding-bottom:3.125rem;border-bottom:1px solid rgba(0,0,0,.2)}.l-section--none{margin-bottom:0}.l-section--large{margin-bottom:6.25rem}.l-section--large.l-section--bordered{padding-bottom:6.25rem}.l-section--background_dark{background-color:#1f1b1c;color:#fff}.btn,.gform_button{font-family:Antonio,sans-serif;font-size:1.0625rem;font-weight:700;letter-spacing:.01875rem;line-height:1.2941176471;text-transform:uppercase;display:inline-block;padding:.8125rem 1.25rem .9375rem;transition:background-color .2s ease-out,border-color .2s ease-out;border:0;border-radius:.1875rem;background-color:#fecc56;color:#231f20;text-align:center;cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none}.btn:hover,.gform_button:hover{background-color:#eda701}.btn:focus,.gform_button:focus{outline:.125rem solid #000}.btn--dark{background-color:#000;color:#fff}.btn--clear{border:.0625rem solid rgba(0,0,0,.2);background-color:transparent;color:#000}.btn--clear:hover{border-color:#eda701}.meta-links{display:flex;align-items:center}@media screen and (min-width:64em){.meta-links{flex-shrink:0;width:9.375rem}}.meta-links--vertical{flex-direction:column;align-items:flex-start}.meta-links__item{position:relative}.meta-links__item+.meta-links__item{margin-left:1.5rem}.meta-links--vertical .meta-links__item+.meta-links__item{margin-left:0}.meta-links__link{font-size:.875rem;line-height:1.4285714286;word-wrap:break-word;-ms-word-break:break-all;word-break:break-all;word-break:break-word;overflow-wrap:break-word;display:flex;align-items:center;border:none;background:0 0;color:#000;text-decoration:underline;cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none}@media screen and (min-width:48em){.meta-links__link{font-size:1rem;line-height:1.5625}}.meta-links__link:hover{color:#fecc56;text-decoration:none}.meta-links__link:focus{outline:.125rem solid #fecc56;outline-offset:.25rem}.meta-links__icon{margin-right:.75rem}.meta-links__share{position:absolute;z-index:2;top:calc(100% + .625rem);left:0;width:16.875rem;padding:.625rem .625rem .375rem;border:.125rem solid #231f20;background-color:#eaebeb}@media screen and (min-width:64em){.meta-links__share{top:calc(100% + 1.9375rem);right:-5.5rem;left:auto}}.meta-links__share--hidden{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px);white-space:nowrap}.meta-links__share .at-share-tbx-element{display:flex!important}.meta-links__share .at-share-tbx-element .addthis_counter:last-of-type{margin-right:0;margin-left:auto}.banner{position:relative}@media screen and (min-width:64em){.banner{background-color:#1f1b1c}.banner .l-container{display:flex;position:absolute;z-index:3;flex-direction:column;justify-content:space-between;padding-top:2.125rem;padding-bottom:2.125rem;top:0;right:0;bottom:0;left:0}.is-ie .banner .l-container{width:100%}}@media screen and (min-width:80em){.banner .l-container{padding-bottom:3.625rem}}@media screen and (min-width:64em){.banner--with-numbers .l-container{padding-top:12.5rem}.admin-bar .banner--with-numbers .l-container{padding-top:14.5rem}}@media screen and (min-width:80em){.banner--with-numbers .l-container{padding-top:13.75rem}.admin-bar .banner--with-numbers .l-container{padding-top:15.75rem}}.banner__overlay{position:relative;height:18.75rem;overflow:hidden}@media screen and (min-width:48em){.banner__overlay{height:31.25rem}}@media screen and (min-width:80em){.banner__overlay{height:37.5rem}}.banner__overlay::before{content:"";position:absolute;z-index:2;background-color:rgba(31,27,28,.6);top:0;right:0;bottom:0;left:0}@media print{.banner__overlay{height:auto}}.banner--with-numbers .banner__overlay{background-color:#1f1b1c}.banner__image{position:absolute;z-index:1;width:100%;height:100%;margin:0 auto;margin:0 auto;font-family:"object-fit: cover;";top:0;right:0;bottom:0;left:0;-o-object-fit:cover;object-fit:cover}.banner__image--default{background-color:#fecc56}@media screen and (min-width:120.0625em){.banner__image--default{font-family:"object-fit: none;";-o-object-fit:none;object-fit:none}}.banner__bg{position:absolute;z-index:1;width:100%;height:100%;transform:scale(1.05);font-family:"object-fit: cover;";-o-object-fit:cover;object-fit:cover;top:0;right:0;bottom:0;left:0}.banner__breadcrumbs{position:absolute;z-index:3;top:.75rem}@media screen and (min-width:64em){.banner__breadcrumbs{position:static}}.banner__breadcrumbs.banner__breadcrumbs .breadcrumb_last,.banner__breadcrumbs.banner__breadcrumbs .breadcrumbs__link,.banner__breadcrumbs.banner__breadcrumbs a,.banner__breadcrumbs.banner__breadcrumbs span{color:#fff}.banner--with-numbers .banner__breadcrumbs{margin-top:8.125rem}@media screen and (min-width:48em){.banner--with-numbers .banner__breadcrumbs{margin-top:12.25rem}.admin-bar .banner--with-numbers .banner__breadcrumbs{margin-top:15.375rem}}@media screen and (min-width:48.9375em){.admin-bar .banner--with-numbers .banner__breadcrumbs{margin-top:14.25rem}}@media screen and (min-width:64em){.banner--with-numbers .banner__breadcrumbs{margin-top:0}.admin-bar .banner--with-numbers .banner__breadcrumbs{margin-top:0}.banner__bottom{display:flex;align-items:flex-end;justify-content:space-between}}.banner__content{margin-bottom:1.5rem;padding:1rem 0 0}@media screen and (min-width:64em){.banner__content{margin-bottom:0}}.banner__datetime{font-size:1.125rem;line-height:1.3888888889;display:block;margin-bottom:.375rem}@media screen and (min-width:48em){.banner__datetime{font-size:1.25rem;line-height:1.35}}@media screen and (min-width:64em){.banner__datetime{color:#fff}}@media screen and (min-width:80em){.banner__datetime{margin-bottom:1.25rem}}.banner__title{font-size:2.0625rem;font-weight:500;line-height:1.0909090909;max-width:52.5rem}@media screen and (min-width:48em){.banner__title{font-size:2.75rem;line-height:1.1818181818}}@media screen and (min-width:80em){.banner__title{font-size:3.125rem;line-height:1.2}}@media screen and (min-width:64em){.banner__title{color:#fff}.banner__links{flex-shrink:0;width:auto;margin-left:1.5rem}.banner__links .meta-links__link{color:#fff}}@media print{.banner__links{display:none}}.breadcrumbs{width:calc(100% - 2.5rem)}@media screen and (min-width:48em){.breadcrumbs{margin-left:0}}.breadcrumbs__list{display:flex;align-items:flex-start;overflow-x:scroll;-ms-overflow-style:none;scrollbar-width:none}.breadcrumbs__list::-webkit-scrollbar{display:none}.breadcrumbs__item{display:inline-flex;align-items:center;margin:0 0 0 .75rem!important;white-space:nowrap}@media screen and (max-width:47.9375em){.breadcrumbs__item:nth-last-child(n+3){display:none}.breadcrumbs__item:nth-last-child(2){margin-left:0}.breadcrumbs__item:nth-last-child(2)::before{display:none}}.breadcrumbs__item:first-child{margin-left:0!important}.breadcrumbs__item:first-child::before{display:none}.breadcrumbs__item:first-child .breadcrumbs__link{flex-shrink:0;width:1.125rem;height:1.25rem;overflow:hidden;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg width='18' height='20' viewBox='0 0 18 20' fill='%23fff' xmlns='http://www.w3.org/2000/svg'%3E %3Cpath fill-rule='evenodd' clip-rule='evenodd' d='M7.5 17.9967C7.5 19.1031 6.60457 20 5.5 20H3C1.34315 20 0 18.6547 0 16.9951V8.54215C0 7.70022 0.35263 6.89691 0.972046 6.32778L6.9985 0.790549C8.11214 -0.232685 9.81178 -0.266475 10.9651 0.711686L16.9386 5.7781C17.6118 6.34905 18 7.1878 18 8.07133V16.9951C18 18.6547 16.6568 20 15 20H12.5C11.3954 20 10.5 19.1031 10.5 17.9967V13.0252H7.5V17.9967ZM9.67266 2.24051C9.28823 1.91446 8.72168 1.92572 8.35047 2.2668L2.32402 7.80402C2.11754 7.99373 2 8.26151 2 8.54215V16.9951C2 17.5483 2.44772 17.9967 3 17.9967H5.5V12.0236C5.5 11.4704 5.94772 11.0219 6.5 11.0219H11.5C12.0523 11.0219 12.5 11.4704 12.5 12.0236V17.9967H15C15.5523 17.9967 16 17.5483 16 16.9951V8.07133C16 7.77682 15.8706 7.49724 15.6462 7.30692L9.67266 2.24051Z'/%3E %3C/svg%3E");background-repeat:no-repeat;background-size:1.125rem 1.25rem;text-indent:-62.4375rem;white-space:nowrap}.breadcrumbs__item:last-child .breadcrumbs__link{font-size:.875rem;line-height:1.4285714286}@media screen and (min-width:48em){.breadcrumbs__item:last-child .breadcrumbs__link{font-size:1rem;line-height:1.5625}}.breadcrumbs__item::before{content:"";display:inline-block;position:relative;top:.0625rem;flex-shrink:0;width:.4375rem;height:.9375rem;margin-right:.75rem;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg width='9' height='14' viewBox='0 0 9 14' fill='%23fff' xmlns='http://www.w3.org/2000/svg'%3E %3Cpath d='M8.34961 7.32129C8.61328 7.05762 8.61328 6.61816 8.34961 6.35449L2.66602 0.641602C2.37305 0.37793 1.93359 0.37793 1.66992 0.641602L0.996094 1.31543C0.732422 1.5791 0.732422 2.01855 0.996094 2.31152L5.50781 6.82324L0.996094 11.3643C0.732422 11.6572 0.732422 12.0967 0.996094 12.3604L1.66992 13.0342C1.93359 13.2979 2.37305 13.2979 2.66602 13.0342L8.34961 7.32129Z'/%3E %3C/svg%3E");background-size:.4375rem .9375rem}.breadcrumbs__link{font-size:.875rem;line-height:1.4285714286;color:#fff}@media screen and (min-width:48em){.breadcrumbs__link{font-size:1rem;line-height:1.5625}}.breadcrumbs__link:hover{text-decoration:underline}.breadcrumbs__link:focus{outline:.125rem solid #fecc56;outline-offset:.25rem}.hero{position:relative;padding-top:7.875rem;padding-bottom:1.5rem}@media screen and (min-width:48em){.hero{padding-top:12rem}}@media screen and (min-width:80em){.hero{padding-top:13.375rem}}.hero::before{content:"";position:absolute;z-index:1;background-image:linear-gradient(74.42deg,#171516 48.46%,rgba(35,31,32,.75) 88.07%);top:0;right:0;bottom:0;left:0}.hero::after{content:"";position:absolute;z-index:1;top:calc(100% - 380px);right:0;bottom:0;left:0;background-image:linear-gradient(180deg,rgba(31,27,28,0) 0,#1f1b1c 66.67%)}.hero__image,.hero__video{position:absolute;width:100%;height:100%;font-family:"object-fit: cover;";top:0;right:0;bottom:0;left:0;-o-object-fit:cover;object-fit:cover}@media screen and (min-width:64em){.hero__image--small,.hero__video--small{display:none}}.hero__image--big,.hero__video--big{display:none}@media screen and (min-width:64em){.hero__image--big,.hero__video--big{display:block}}.is-ie .hero__video{display:none}.hero__content{position:relative;z-index:2;padding-top:1.125rem;padding-bottom:3.125rem}@media screen and (min-width:48em){.hero__content{padding-top:5rem;padding-bottom:4.375rem;overflow:hidden}}@media screen and (min-width:80em){.hero__content{padding-top:3.25rem;padding-bottom:9.375rem}}@media screen and (min-width:48em){.hero__content .l-container{display:flex;align-items:center}}.hero__title{font-family:Antonio,sans-serif;font-size:2.625rem;font-weight:700;letter-spacing:-.025rem;line-height:1.1052631579;text-transform:uppercase;position:relative;z-index:3;max-width:20rem;margin:1.25rem auto 1.875rem;color:#fff;text-align:center}@media screen and (min-width:48em){.hero__title{font-size:4rem;letter-spacing:-.125rem;line-height:1.09375}}@media screen and (min-width:80em){.hero__title{font-size:5.625rem;line-height:1.0444444444}}@media screen and (min-width:35em){.hero__title{max-width:none;margin-top:0}}@media screen and (min-width:48em){.hero__title{width:27.0625rem;margin:0;text-align:left}}@media screen and (min-width:80em){.hero__title{width:33.875rem}}.hero__pre-title{font-family:canada-type-gibson,sans-serif;font-size:.75rem;font-weight:400;letter-spacing:.1875rem;line-height:1.6666666667;text-transform:uppercase;display:block;margin-bottom:.75rem}@media screen and (min-width:48em){.hero__pre-title{font-size:.875rem;letter-spacing:.3125rem;line-height:2.8571428571}}@media screen and (min-width:80em){.hero__pre-title{font-size:.9375rem;letter-spacing:.4375rem;line-height:2.6666666667}}.hero__phones.hero__phones{border-color:rgba(255,255,255,.45);margin:1.25rem auto 0;display:flex;width:-moz-fit-content;width:fit-content}@media screen and (min-width:48em){.hero__phones.hero__phones{margin-inline:0}}.hero__play{position:relative;z-index:2;width:5rem;height:5rem;margin:2.5rem auto 6.375rem}@media screen and (min-width:48em){.hero__play{top:3.625rem;width:7.5rem;height:7.5rem}}.hero__play::after,.hero__play::before{content:"";pointer-events:none;position:absolute;z-index:1;border:.125rem solid rgba(167,170,170,.2);border-right:0;border-bottom:0;border-top-left-radius:50%;border-top-right-radius:50%;border-bottom-left-radius:50%;border-bottom-right-radius:0}.hero__play::before{width:15rem;height:15rem;animation:rotateCircle 10s linear infinite}.hero__play::after{width:22.5rem;height:22.5rem;animation:rotateCircle 10s linear reverse infinite}.hero__links{position:relative;z-index:2}.no-js .hero__links .l-grid__cell:first-of-type{display:none}.no-js .hero__links .l-grid__cell:last-of-type{margin-right:auto;margin-left:auto}.hero__experts{margin-bottom:2.5rem}.hero__tagline{font-size:.75rem;line-height:1.5;margin-bottom:1.25rem;color:#fff;text-align:center}@media screen and (min-width:48em){.hero__tagline{font-size:.875rem;line-height:1.5714285714;text-align:left}}.hero__select{font-family:canada-type-gibson,sans-serif;font-size:1.0625rem;font-weight:400;letter-spacing:normal;line-height:1.5294117647;text-transform:none;width:100%;padding:.75rem 2rem .75rem 1rem;overflow:hidden;border-radius:.1875rem;border-color:rgba(255,255,255,.45);background-color:transparent;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg id='Layer_1' data-name='Layer 1' xmlns='http://www.w3.org/2000/svg' width='8.71' height='5.48' viewBox='0 0 8.71 5.48' fill='%23fff'%3E%3Cpath d='M4.81,5.27a.63.63,0,0,1-.88.05l-.05-.05L.19,1.58a.67.67,0,0,1,0-.93L.65.19a.67.67,0,0,1,.93,0L4.34,3,7.13.19a.67.67,0,0,1,.93,0l.47.46a.69.69,0,0,1,0,.93Z' transform='translate(0 0)'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:top 50% right 1rem;background-size:.625rem 1rem;color:#fff;text-overflow:ellipsis;white-space:nowrap;-webkit-appearance:none;-moz-appearance:none;appearance:none}@media screen and (min-width:48em){.hero__select{font-size:1.1875rem;line-height:1.4210526316}}.hero__select option{color:#231f20}.hero__featured{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;margin-left:-.625rem}@media screen and (min-width:48em){.hero__featured{justify-content:space-between}}@media screen and (min-width:80em){.hero__featured{justify-content:flex-start;margin-left:-.75rem}}.hero__featured-item{padding:.625rem .3125rem}@media screen and (min-width:80em){.hero__featured-item{padding:.25rem .75rem}}@media screen and (min-width:87.5em){.hero__featured-item{padding:.125rem .75rem}}@media screen and (min-width:93.75em){.hero__featured-item{padding:0 .75rem}}.hero__logo{width:auto;height:1.875rem}@media screen and (min-width:48em){.hero__logo{height:3.4375rem}}@media screen and (min-width:80em){.hero__logo{height:2.5rem}}@media screen and (min-width:87.5em){.hero__logo{height:3.125rem}}@media screen and (min-width:93.75em){.hero__logo{height:3.25rem}}.intro{position:relative;padding:1.25rem 0 2.5rem;background-color:#1f1b1c}@media screen and (min-width:80em){.intro{padding:1.75rem 0 6.25rem}}.intro .l-container{position:relative;z-index:3}@media screen and (min-width:64em){.intro .l-container{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;min-height:17.5rem}.intro--with-image .l-container{min-height:30rem}}.intro--with-numbers{padding-top:8.125rem}@media screen and (min-width:48em){.intro--with-numbers{padding-top:13.5rem}.admin-bar .intro--with-numbers{padding-top:16.625rem}}@media screen and (min-width:48.9375em){.admin-bar .intro--with-numbers{padding-top:14.25rem}}@media screen and (min-width:64em){.intro--with-numbers{padding-top:12.5rem}.admin-bar .intro--with-numbers{padding-top:14.5rem}}@media screen and (min-width:80em){.intro--with-numbers{padding-top:13.75rem}.admin-bar .intro--with-numbers{padding-top:15.75rem}}.intro__image-container::before{content:"";position:absolute;z-index:2;background-color:rgba(31,27,28,.6);top:0;right:0;bottom:0;left:0}.intro__image{position:absolute;z-index:1;width:100%;height:100%;font-family:"object-fit: cover;";top:0;right:0;bottom:0;left:0;-o-object-fit:cover;object-fit:cover}.intro__numbers{position:absolute;z-index:1;top:0;bottom:0;left:55%;overflow:hidden}.intro__numbers::after{content:"";position:absolute;z-index:2;background-image:linear-gradient(270deg,rgba(31,27,28,0) 0,#1f1b1c 100%);top:0;right:0;bottom:0;left:0}.intro__breadcrumbs{align-self:flex-start;width:100%;margin-bottom:2.25rem}@media screen and (min-width:80em){.intro__breadcrumbs{margin-bottom:3.625rem}}.intro--with-numbers .intro__breadcrumbs{position:relative;z-index:3}@media screen and (min-width:64em){.intro__content{max-width:60.625rem}}.intro--with-numbers .intro__content{position:relative;z-index:3}.intro__title{font-family:Antonio,sans-serif;font-size:2.625rem;font-weight:700;letter-spacing:-.025rem;line-height:1.1052631579;text-transform:uppercase;color:#fff}@media screen and (min-width:48em){.intro__title{font-size:4rem;letter-spacing:-.125rem;line-height:1.09375}}@media screen and (min-width:80em){.intro__title{font-size:5.625rem;line-height:1.0444444444}}.intro__copy{font-size:1.125rem;line-height:1.3888888889;max-width:52.5rem;margin-top:1.5rem;color:#fff}@media screen and (min-width:48em){.intro__copy{font-size:1.3125rem;line-height:1.4285714286}}.intro__copy a{font-weight:600;text-decoration:underline}.intro__copy a:focus,.intro__copy a:hover{text-decoration:none}.intro__copy a:focus{outline:.125rem solid #fecc56;outline-offset:.125rem}.intro__links{display:flex;align-items:center;margin-top:1rem}@media screen and (min-width:64em){.intro__links{flex-shrink:0;width:9.375rem;margin-top:0}}.intro--with-numbers .intro__links{position:relative;z-index:3}.intro__links-item+.intro__links-item{margin-left:1.5rem}.intro__link{font-size:.875rem;line-height:1.4285714286;display:flex;align-items:center;color:#000;text-decoration:underline}@media screen and (min-width:48em){.intro__link{font-size:1rem;line-height:1.5625}}.intro__link:hover{color:#fecc56;text-decoration:none}.intro__link:focus{outline:.125rem solid #fecc56}.intro__icon{margin-right:.75rem}.primary-menu__list{display:flex}.primary-menu__item{position:relative}.primary-menu__link{font-size:.875rem;line-height:1.125;display:block;position:relative;padding:1rem .375rem;color:#fff}@media screen and (min-width:87.5em){.primary-menu__link{font-size:1rem;padding:1rem .75rem}}.primary-menu__link::after{content:"";display:block;position:absolute;right:.375rem;bottom:.5rem;left:.375rem;height:.125rem;transform:scaleX(0);transform-origin:top right;transition:transform .2s ease-out;background-color:#fecc56}.has-children .primary-menu__link::after{right:1.5rem}.primary-menu__link--active::after,.primary-menu__link:focus::after,.primary-menu__link:hover::after{transform:scaleX(100%);transform-origin:top left}.primary-menu__link--active .primary-menu__arrow,.primary-menu__link:focus .primary-menu__arrow,.primary-menu__link:hover .primary-menu__arrow{color:#fecc56}.primary-menu__arrow{width:.625rem;height:.3125rem;margin-left:.625rem;transform:rotate(180deg);transition:color .2s ease-out;color:#fff}.primary-menu__dropdown{visibility:hidden;position:absolute;z-index:100;top:calc(100% - .625rem);left:0;width:auto;min-width:18.75rem;transition:.2s ease-out;transition-property:visibility,opacity;opacity:0}.primary-menu__dropdown::before{content:"";position:absolute;top:.5rem;left:.9375rem;width:0;height:0;border-width:0 .5rem .5rem .5rem;border-style:solid;border-color:transparent transparent #000 transparent}.primary-menu__item:hover .primary-menu__dropdown{visibility:visible;opacity:1}.primary-menu-submenu--level-1{width:100%;margin-top:.9375rem;padding-top:.625rem;padding-bottom:.625rem;overflow:hidden;transition:width .2s ease-out;border-radius:.25rem;background:#000;box-shadow:.375rem 1.625rem 5.6875rem -1.5rem rgba(0,0,0,.45)}.primary-menu-submenu--level-1.is-active{width:200%}.primary-menu-submenu--level-1.is-active--two-columns{width:300%}.primary-menu-submenu--level-2{visibility:hidden;position:absolute;top:0;left:100%;min-width:18.75rem;min-height:100%;transition:.2s ease-out;transition-property:visibility,opacity;opacity:0}.primary-menu-submenu__item:hover .primary-menu-submenu--level-2{visibility:visible;opacity:1}.primary-menu-submenu__list{position:relative;width:100%;max-width:18.75rem;min-height:0;transition:.2s ease-out;transition-property:min-height,height}.is-active>.primary-menu-submenu__list{border-right:.0625rem solid #fecc56}.primary-menu-submenu__item--two-columns .primary-menu-submenu__list{width:200%;max-width:31.25rem;-moz-columns:2;columns:2;-moz-column-break-inside:avoid;break-inside:avoid-column}.primary-menu-submenu__item{min-width:18.75rem;padding:0 .625rem}.primary-menu-submenu__item--two-columns .primary-menu-submenu__item{-moz-column-break-inside:avoid;break-inside:avoid-column}.primary-menu-submenu__link{font-size:.875rem;line-height:1.125;display:flex;align-items:baseline;justify-content:space-between;padding:.75rem 1.375rem .75rem 1.125rem;transition:.2s ease-out;transition-property:background-color,color;border-radius:.25rem;color:#fff}@media screen and (min-width:87.5em){.primary-menu-submenu__link{font-size:1rem}}.primary-menu-submenu__link:hover{background-color:#fecc56;color:#231f20}.primary-menu-submenu__label{display:block}.primary-menu-submenu__item--indented .primary-menu-submenu__label{position:relative;padding-left:17px}.primary-menu-submenu__item--indented .primary-menu-submenu__label::before{content:"";position:absolute;top:11px;left:0;width:10px;height:1px;background-color:#58585a}.primary-menu-submenu__arrow{width:.5rem;height:.75rem;margin-left:.625rem;color:#fff}.primary-menu-submenu__item:hover .primary-menu-submenu__arrow{color:#231f20}.search__toggle{display:block;position:relative;z-index:31;width:1.25rem;height:1.25rem;overflow:hidden;border:0;background-color:transparent;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg' stroke='%23fff'%3E %3Cpath d='M29 29L20.11 20.11M23.4 12.2C23.4 18.3856 18.3856 23.4 12.2 23.4C6.01441 23.4 1 18.3856 1 12.2C1 6.01441 6.01441 1 12.2 1C18.3856 1 23.4 6.01441 23.4 12.2Z' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E %3C/svg%3E");background-repeat:no-repeat;background-size:1.25rem 1.25rem;text-indent:-62.4375rem;cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none}.search__toggle:hover{background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg width='30' height='30' viewBox='0 0 30 30' fill='none' xmlns='http://www.w3.org/2000/svg' stroke='%23fecc56'%3E %3Cpath d='M29 29L20.11 20.11M23.4 12.2C23.4 18.3856 18.3856 23.4 12.2 23.4C6.01441 23.4 1 18.3856 1 12.2C1 6.01441 6.01441 1 12.2 1C18.3856 1 23.4 6.01441 23.4 12.2Z' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E %3C/svg%3E")}.search__toggle:focus{outline:.125rem solid #fecc56;outline-offset:.25rem}.search__toggle--active{position:absolute;right:1.25rem;background-color:transparent;background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg width='18' height='18' viewBox='0 0 18 18' fill='%23fff' xmlns='http://www.w3.org/2000/svg'%3E %3Cpath fill-rule='evenodd' clip-rule='evenodd' d='M17.7071 1.70711C18.0976 1.31658 18.0976 0.683417 17.7071 0.292893C17.3166 -0.0976311 16.6834 -0.0976311 16.2929 0.292893L9 7.58579L1.70711 0.292893C1.31658 -0.0976311 0.683418 -0.0976311 0.292894 0.292893C-0.0976295 0.683417 -0.0976295 1.31658 0.292894 1.70711L7.58579 9L0.292893 16.2929C-0.0976311 16.6834 -0.0976311 17.3166 0.292893 17.7071C0.683418 18.0976 1.31658 18.0976 1.70711 17.7071L9 10.4142L16.2929 17.7071C16.6834 18.0976 17.3166 18.0976 17.7071 17.7071C18.0976 17.3166 18.0976 16.6834 17.7071 16.2929L10.4142 9L17.7071 1.70711Z'/%3E %3C/svg%3E")}@media screen and (min-width:48em){.search__toggle--active{top:2.625rem;right:0;width:6.25rem;height:6.25rem;margin-top:-1.9375rem;background-position:center right 1.375rem}}@media screen and (min-width:64em){.search__toggle--active{background-position:center right 2rem}}@media screen and (min-width:80em){.search__toggle--active{right:3.75rem;width:6.875rem;height:6.875rem;top:2.75rem;background-position:center right}.search-open .search__toggle--active{top:2rem}}@media screen and (min-width:88.75em){.search__toggle--active{right:calc((100% - 80rem)/ 2)}}.search__toggle--active:hover{background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg width='18' height='18' viewBox='0 0 18 18' fill='%23fecc56' xmlns='http://www.w3.org/2000/svg'%3E %3Cpath fill-rule='evenodd' clip-rule='evenodd' d='M17.7071 1.70711C18.0976 1.31658 18.0976 0.683417 17.7071 0.292893C17.3166 -0.0976311 16.6834 -0.0976311 16.2929 0.292893L9 7.58579L1.70711 0.292893C1.31658 -0.0976311 0.683418 -0.0976311 0.292894 0.292893C-0.0976295 0.683417 -0.0976295 1.31658 0.292894 1.70711L7.58579 9L0.292893 16.2929C-0.0976311 16.6834 -0.0976311 17.3166 0.292893 17.7071C0.683418 18.0976 1.31658 18.0976 1.70711 17.7071L9 10.4142L16.2929 17.7071C16.6834 18.0976 17.3166 18.0976 17.7071 17.7071C18.0976 17.3166 18.0976 16.6834 17.7071 16.2929L10.4142 9L17.7071 1.70711Z'/%3E %3C/svg%3E")}.search__form{display:none;position:absolute;z-index:5;top:0;right:0;left:0;width:100%;height:auto;margin:0 auto}@media screen and (min-width:80em){.search__form{padding:0 3.75rem}.is-ie .search__form{max-width:none}}.search__form--active{display:block}.search__form--very-active{position:fixed;padding-top:3.25rem;height:100vh;background:rgba(0,0,0,.98)}.skip{font-family:Antonio,sans-serif;font-size:1.0625rem;font-weight:700;letter-spacing:.01875rem;line-height:1.2941176471;text-transform:uppercase;display:flex;position:fixed;z-index:12;top:.625rem;right:0;left:0;align-items:center;justify-content:center;width:15rem;height:3.8125rem;margin:0 auto;transform:translateY(-4.4375rem);border-radius:.25rem;border-bottom-left-radius:.25rem;border-bottom-right-radius:.25rem;background:#fecc56;color:#000;text-decoration:none}.skip:focus{transform:translateY(0);outline:.125rem solid #000;text-decoration:underline}@media print{.skip{display:none}}.site-header{position:relative;padding:1.5rem 0;background-color:#1f1b1c}@media screen and (min-width:48em){.site-header{padding:2rem 0}}.site-header>.l-container{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between}@media screen and (min-width:64em){.site-header>.l-container{align-items:flex-start}}.site-header>.l-container::before{content:"";display:none;position:absolute;bottom:0;width:13.75rem;height:.5rem;background-color:#fecc56}@media screen and (min-width:48em){.site-header>.l-container::before{display:block}}@media screen and (min-width:80em){.site-header>.l-container::before{width:32.75rem}}@media print{.site-header{display:none}}.numbers-at-top .site-header{position:absolute;z-index:4;top:51px;right:0;left:0;background:0 0}@media screen and (min-width:48em){.numbers-at-top .site-header{top:3.5625rem}}@media screen and (min-width:80em){.numbers-at-top .site-header{top:3.8125rem}}.numbers-at-top.offcanvas-active .site-header,.offcanvas-active .site-header{z-index:15}.site-header__brand{display:block;width:3.875rem;transform-origin:center left}@media screen and (min-width:48em){.site-header__brand{width:8.375rem}}@media screen and (min-width:64em) and (max-width:79.9375em){.site-header__brand{width:6.25rem}}.site-header__brand:focus{outline:.125rem solid #fecc56;outline-offset:.25rem}.site-header__primary-menu{display:none}@media screen and (min-width:64em){.site-header__primary-menu{display:block;position:relative;top:.125rem;margin-left:.75rem}}@media screen and (min-width:87.5em){.site-header__primary-menu{top:0;margin-left:2.25rem}}.site-header__search{width:1.25rem;height:1.25rem;margin-right:1.5rem;margin-left:auto}@media screen and (min-width:48em){.site-header__search{margin-right:0}}@media screen and (min-width:64em){.site-header__search{margin-top:.875rem}}.site-header__book{margin-right:0;margin-left:auto}@media screen and (min-width:48em){.site-header__book{margin-right:0;margin-left:2.125rem}}@media screen and (max-width:47.9375em){.site-header__book{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px);white-space:nowrap}}@media screen and (min-width:64em) and (max-width:79.9375em){.site-header__book-more{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px);white-space:nowrap}}.site-header__toggle{font-size:1.125rem;font-weight:600;line-height:1.3888888889;display:flex;position:relative;align-items:center;width:1.25rem;height:1.25rem;border:0;background:0 0;color:#fff;cursor:pointer;-webkit-appearance:none;-moz-appearance:none;appearance:none}@media screen and (min-width:48em){.site-header__toggle{font-size:1.25rem;line-height:1.35;margin-left:2.125rem}}@media screen and (min-width:64em){.site-header__toggle{display:none}}.site-header__toggle:focus{outline:.125rem solid #fecc56}@media screen and (max-width:63.9375em){.offcanvas-active .site-header__toggle{position:relative;z-index:15}}.site-header__lines{display:block;position:relative;width:1.25rem;height:.125rem;transition:background .2s ease-out;background-color:#fff}.site-header__toggle--active .site-header__lines{background-color:transparent}.site-header__lines::after,.site-header__lines::before{content:"";position:absolute;right:0;height:.125rem;transition:transform .2s ease-out;background-color:#fff}.site-header__lines::before{width:1.25rem;transform:translateY(-.4375rem)}.site-header__toggle--active .site-header__lines::before{top:0;transform:rotate(-45deg)}.site-header__lines::after{width:1.25rem;transform:translateY(.4375rem)}.site-header__toggle--active .site-header__lines::after{top:0;transform:rotate(45deg)}.site-header__menu-label{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(1px,1px,1px,1px);white-space:nowrap}</style><link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="/contact/" />
<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="/contact/" />
<style id="wp-img-auto-sizes-contain-inline-css">
img:is([sizes=auto i],[sizes^="auto," i]){contain-intrinsic-size:3000px 1500px}
/*# sourceURL=wp-img-auto-sizes-contain-inline-css */
</style>
<style id="wp-emoji-styles-inline-css">

	img.wp-smiley, img.emoji {
		display: inline !important;
		border: none !important;
		box-shadow: none !important;
		height: 1em !important;
		width: 1em !important;
		margin: 0 0.07em !important;
		vertical-align: -0.1em !important;
		background: none !important;
		padding: 0 !important;
	}
/*# sourceURL=wp-emoji-styles-inline-css */
</style>
<style id="classic-theme-styles-inline-css">
/*! This file is auto-generated */
.wp-block-button__link{color:#fff;background-color:#32373c;border-radius:9999px;box-shadow:none;text-decoration:none;padding:calc(.667em + 2px) calc(1.333em + 2px);font-size:1.125em}.wp-block-file__button{background:#32373c;color:#fff;text-decoration:none}
/*# sourceURL=/wp-includes/css/classic-themes.min.css */
</style>

<style id="site-designer-shared-pattern-classes-inline-css">
<style id="book-consultation-fix">
/* Dark cover/section overlay + on-dark text. */
body .wp-site-blocks .is-style-overlay-dark .wp-block-cover__background { background-color: var(--wp--preset--color--base-3); color: var(--wp--preset--color--contrast-3); }
.wp-block-cover.is-style-overlay-dark .wp-block-cover__background.has-background-dim { opacity: 0.8 !important; }
:is(.wp-block-designsetgo-section, .wp-block-designsetgo-scroll-slides).is-style-overlay-dark { --dsgo-overlay-color: var(--wp--preset--color--base-3); --dsgo-overlay-opacity: 0.8; color: var(--wp--preset--color--contrast-3); }
.wp-block-group.has-background.is-style-overlay-dark { box-shadow: inset 0 0 0 9999px color-mix(in srgb, var(--wp--preset--color--base-3) 80%, transparent); }
body .wp-site-blocks .is-style-overlay-dark, body .wp-site-blocks .is-style-on-dark { --dsgo-text-color: var(--wp--preset--color--contrast-3); color: var(--wp--preset--color--contrast-3) !important; }
body .wp-site-blocks .is-style-overlay-dark :is(h1,h2,h3,h4,h5,h6,p,li,blockquote,cite), body .wp-site-blocks .is-style-on-dark :is(h1,h2,h3,h4,h5,h6,p,li,blockquote,cite) { color: var(--wp--preset--color--contrast-3) !important; }

/* Solid dark section background + light text. */
.is-style-bg-dark { background-color: var(--wp--preset--color--contrast); color: var(--wp--preset--color--base); }
.is-style-bg-dark a { color: var(--wp--preset--color--base); }
/*# sourceURL=site-designer-shared-pattern-classes-inline-css */
</style>
<style id="global-styles-inline-css">
:root{--wp--preset--aspect-ratio--square: 1;--wp--preset--aspect-ratio--4-3: 4/3;--wp--preset--aspect-ratio--3-4: 3/4;--wp--preset--aspect-ratio--3-2: 3/2;--wp--preset--aspect-ratio--2-3: 2/3;--wp--preset--aspect-ratio--16-9: 16/9;--wp--preset--aspect-ratio--9-16: 9/16;--wp--preset--color--black: #000000;--wp--preset--color--cyan-bluish-gray: #abb8c3;--wp--preset--color--white: #ffffff;--wp--preset--color--pale-pink: #f78da7;--wp--preset--color--vivid-red: #cf2e2e;--wp--preset--color--luminous-vivid-orange: #ff6900;--wp--preset--color--luminous-vivid-amber: #fcb900;--wp--preset--color--light-green-cyan: #7bdcb5;--wp--preset--color--vivid-green-cyan: #00d084;--wp--preset--color--pale-cyan-blue: #8ed1fc;--wp--preset--color--vivid-cyan-blue: #0693e3;--wp--preset--color--vivid-purple: #9b51e0;--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple: linear-gradient(135deg,rgb(6,147,227) 0%,rgb(155,81,224) 100%);--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan: linear-gradient(135deg,rgb(122,220,180) 0%,rgb(0,208,130) 100%);--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange: linear-gradient(135deg,rgb(252,185,0) 0%,rgb(255,105,0) 100%);--wp--preset--gradient--luminous-vivid-orange-to-vivid-red: linear-gradient(135deg,rgb(255,105,0) 0%,rgb(207,46,46) 100%);--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray: linear-gradient(135deg,rgb(238,238,238) 0%,rgb(169,184,195) 100%);--wp--preset--gradient--cool-to-warm-spectrum: linear-gradient(135deg,rgb(74,234,220) 0%,rgb(151,120,209) 20%,rgb(207,42,186) 40%,rgb(238,44,130) 60%,rgb(251,105,98) 80%,rgb(254,248,76) 100%);--wp--preset--gradient--blush-light-purple: linear-gradient(135deg,rgb(255,206,236) 0%,rgb(152,150,240) 100%);--wp--preset--gradient--blush-bordeaux: linear-gradient(135deg,rgb(254,205,165) 0%,rgb(254,45,45) 50%,rgb(107,0,62) 100%);--wp--preset--gradient--luminous-dusk: linear-gradient(135deg,rgb(255,203,112) 0%,rgb(199,81,192) 50%,rgb(65,88,208) 100%);--wp--preset--gradient--pale-ocean: linear-gradient(135deg,rgb(255,245,203) 0%,rgb(182,227,212) 50%,rgb(51,167,181) 100%);--wp--preset--gradient--electric-grass: linear-gradient(135deg,rgb(202,248,128) 0%,rgb(113,206,126) 100%);--wp--preset--gradient--midnight: linear-gradient(135deg,rgb(2,3,129) 0%,rgb(40,116,252) 100%);--wp--preset--font-size--small: 13px;--wp--preset--font-size--medium: 20px;--wp--preset--font-size--large: 36px;--wp--preset--font-size--x-large: 42px;--wp--preset--spacing--20: 0.44rem;--wp--preset--spacing--30: 0.67rem;--wp--preset--spacing--40: 1rem;--wp--preset--spacing--50: 1.5rem;--wp--preset--spacing--60: 2.25rem;--wp--preset--spacing--70: 3.38rem;--wp--preset--spacing--80: 5.06rem;--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2);--wp--preset--shadow--deep: 12px 12px 50px rgba(0, 0, 0, 0.4);--wp--preset--shadow--sharp: 6px 6px 0px rgba(0, 0, 0, 0.2);--wp--preset--shadow--outlined: 6px 6px 0px -3px rgb(255, 255, 255), 6px 6px rgb(0, 0, 0);--wp--preset--shadow--crisp: 6px 6px 0px rgb(0, 0, 0);}:where(body) { margin: 0; }:where(.is-layout-flex){gap: 0.5em;}:where(.is-layout-grid){gap: 0.5em;}body .is-layout-flex{display: flex;}.is-layout-flex{flex-wrap: wrap;align-items: center;}.is-layout-flex > :is(*, div){margin: 0;}body .is-layout-grid{display: grid;}.is-layout-grid > :is(*, div){margin: 0;}body{padding-top: 0px;padding-right: 0px;padding-bottom: 0px;padding-left: 0px;}:root :where(.wp-element-button, .wp-block-button__link){background-color: #32373c;border-width: 0;color: #fff;font-family: inherit;font-size: inherit;font-style: inherit;font-weight: inherit;letter-spacing: inherit;line-height: inherit;padding-top: calc(0.667em + 2px);padding-right: calc(1.333em + 2px);padding-bottom: calc(0.667em + 2px);padding-left: calc(1.333em + 2px);text-decoration: none;text-transform: inherit;}.has-black-color{color: var(--wp--preset--color--black) !important;}.has-cyan-bluish-gray-color{color: var(--wp--preset--color--cyan-bluish-gray) !important;}.has-white-color{color: var(--wp--preset--color--white) !important;}.has-pale-pink-color{color: var(--wp--preset--color--pale-pink) !important;}.has-vivid-red-color{color: var(--wp--preset--color--vivid-red) !important;}.has-luminous-vivid-orange-color{color: var(--wp--preset--color--luminous-vivid-orange) !important;}.has-luminous-vivid-amber-color{color: var(--wp--preset--color--luminous-vivid-amber) !important;}.has-light-green-cyan-color{color: var(--wp--preset--color--light-green-cyan) !important;}.has-vivid-green-cyan-color{color: var(--wp--preset--color--vivid-green-cyan) !important;}.has-pale-cyan-blue-color{color: var(--wp--preset--color--pale-cyan-blue) !important;}.has-vivid-cyan-blue-color{color: var(--wp--preset--color--vivid-cyan-blue) !important;}.has-vivid-purple-color{color: var(--wp--preset--color--vivid-purple) !important;}.has-black-background-color{background-color: var(--wp--preset--color--black) !important;}.has-cyan-bluish-gray-background-color{background-color: var(--wp--preset--color--cyan-bluish-gray) !important;}.has-white-background-color{background-color: var(--wp--preset--color--white) !important;}.has-pale-pink-background-color{background-color: var(--wp--preset--color--pale-pink) !important;}.has-vivid-red-background-color{background-color: var(--wp--preset--color--vivid-red) !important;}.has-luminous-vivid-orange-background-color{background-color: var(--wp--preset--color--luminous-vivid-orange) !important;}.has-luminous-vivid-amber-background-color{background-color: var(--wp--preset--color--luminous-vivid-amber) !important;}.has-light-green-cyan-background-color{background-color: var(--wp--preset--color--light-green-cyan) !important;}.has-vivid-green-cyan-background-color{background-color: var(--wp--preset--color--vivid-green-cyan) !important;}.has-pale-cyan-blue-background-color{background-color: var(--wp--preset--color--pale-cyan-blue) !important;}.has-vivid-cyan-blue-background-color{background-color: var(--wp--preset--color--vivid-cyan-blue) !important;}.has-vivid-purple-background-color{background-color: var(--wp--preset--color--vivid-purple) !important;}.has-black-border-color{border-color: var(--wp--preset--color--black) !important;}.has-cyan-bluish-gray-border-color{border-color: var(--wp--preset--color--cyan-bluish-gray) !important;}.has-white-border-color{border-color: var(--wp--preset--color--white) !important;}.has-pale-pink-border-color{border-color: var(--wp--preset--color--pale-pink) !important;}.has-vivid-red-border-color{border-color: var(--wp--preset--color--vivid-red) !important;}.has-luminous-vivid-orange-border-color{border-color: var(--wp--preset--color--luminous-vivid-orange) !important;}.has-luminous-vivid-amber-border-color{border-color: var(--wp--preset--color--luminous-vivid-amber) !important;}.has-light-green-cyan-border-color{border-color: var(--wp--preset--color--light-green-cyan) !important;}.has-vivid-green-cyan-border-color{border-color: var(--wp--preset--color--vivid-green-cyan) !important;}.has-pale-cyan-blue-border-color{border-color: var(--wp--preset--color--pale-cyan-blue) !important;}.has-vivid-cyan-blue-border-color{border-color: var(--wp--preset--color--vivid-cyan-blue) !important;}.has-vivid-purple-border-color{border-color: var(--wp--preset--color--vivid-purple) !important;}.has-vivid-cyan-blue-to-vivid-purple-gradient-background{background: var(--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple) !important;}.has-light-green-cyan-to-vivid-green-cyan-gradient-background{background: var(--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan) !important;}.has-luminous-vivid-amber-to-luminous-vivid-orange-gradient-background{background: var(--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange) !important;}.has-luminous-vivid-orange-to-vivid-red-gradient-background{background: var(--wp--preset--gradient--luminous-vivid-orange-to-vivid-red) !important;}.has-very-light-gray-to-cyan-bluish-gray-gradient-background{background: var(--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray) !important;}.has-cool-to-warm-spectrum-gradient-background{background: var(--wp--preset--gradient--cool-to-warm-spectrum) !important;}.has-blush-light-purple-gradient-background{background: var(--wp--preset--gradient--blush-light-purple) !important;}.has-blush-bordeaux-gradient-background{background: var(--wp--preset--gradient--blush-bordeaux) !important;}.has-luminous-dusk-gradient-background{background: var(--wp--preset--gradient--luminous-dusk) !important;}.has-pale-ocean-gradient-background{background: var(--wp--preset--gradient--pale-ocean) !important;}.has-electric-grass-gradient-background{background: var(--wp--preset--gradient--electric-grass) !important;}.has-midnight-gradient-background{background: var(--wp--preset--gradient--midnight) !important;}.has-small-font-size{font-size: var(--wp--preset--font-size--small) !important;}.has-medium-font-size{font-size: var(--wp--preset--font-size--medium) !important;}.has-large-font-size{font-size: var(--wp--preset--font-size--large) !important;}.has-x-large-font-size{font-size: var(--wp--preset--font-size--x-large) !important;}
/*# sourceURL=global-styles-inline-css */
</style>

<link rel='stylesheet' id='wp-components-css' href='/wp-includes/css/dist/components/style.min.css?ver=7.0.3' media='all' />
<link rel='stylesheet' id='godaddy-styles-css' href='/wp-content/mu-plugins/vendor/wpex/godaddy-launch/includes/Dependencies/GoDaddy/Styles/build/latest.css?ver=2.0.2' media='all' />
<link rel='stylesheet' id='style-css' href='/wp-content/themes/rb-council/css/style.min.css?ver=1.0.5' media='all' />
<link rel='stylesheet' id='fonts-css' href='https://use.typekit.net/akm4rro.css?ver=1.0.2' media='all' />


<link rel="https://api.w.org/" href="/wp-json/" /><link rel="alternate" title="JSON" type="application/json" href="/wp-json/wp/v2/pages/119" /><!-- HFCM by 99 Robots - Snippet # 1: FP CSS -->
<style>
    #popular-searches, #hide-links, #read-more-container, #hide-less {display:none;}
</style>
<!-- /end HFCM by 99 Robots -->
<!-- HFCM by 99 Robots - Snippet # 12: Sticky header -->
<style>
#top.scrolled,
.sticky-header.scrolled {
	position: fixed;
	top: 0 !important;
	left: 0;
	width: 100%;
	background: black;
	z-index: 1000;
}
</style>


<!-- /end HFCM by 99 Robots -->


<meta name="ti-site-data" content="eyJyIjoiMTowITc6MCEzMDowIiwibyI6Imh0dHBzOlwvXC9pZndnbG9iYWwuY29tP3RpLW9ubGluZS11c2Vycy1nb29nbGU9MSZhbXA7cD0lMkZib29rLWEtY29uc3VsdGF0aW9uJTJGJmFtcDtfd3Bub25jZT1kOTBiNTM1YmJiIn0=" /><meta name="description" content="Submit an enquiry with Ken Gamble and IFW Global. International specialists at cybercrime investigations">

<!-- Google Tag Manager for WordPress by gtm4wp.com -->
<!-- GTM Container placement set to footer -->


<!-- End Google Tag Manager for WordPress by gtm4wp.com --><style id="wp-site-designer-contrast-fallback"> .wp-block-group:not([data-dsgo-inline-bg]),.wp-block-column:not([data-dsgo-inline-bg]),.wp-block-columns:not([data-dsgo-inline-bg]){--dsgo-text-color:initial;} :is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover:not(.has-text-color){color:var(--wp--preset--color--contrast-3) !important;--dsgo-text-color:var(--wp--preset--color--contrast-3);} body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) h1:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) h2:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) h3:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) h4:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) h5:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) h6:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) p:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(:is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column).has-background:not([class*="-background-color"]):not(.has-text-color):not(.is-style-footer-section):not(.is-style-header-section),.wp-block-cover) .wp-element-caption:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)){color:var(--dsgo-text-color,inherit) !important;} body .wp-site-blocks :is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column)[data-dsgo-inline-bg] .wp-block-button:is([class*="is-style-outline"],[class*="is-style-secondary"]) .wp-element-button{color:var(--dsgo-text-color,inherit) !important;border-color:currentColor !important;}body .wp-site-blocks :is(.wp-block-designsetgo-section,.wp-block-group,.wp-block-column)[data-dsgo-inline-bg] .wp-block-button:is([class*="is-style-outline"],[class*="is-style-secondary"]) .wp-element-button:hover{opacity:0.75;} body .wp-site-blocks [data-dsgo-inline-bg] h1:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] h2:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] h3:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] h4:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] h5:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] h6:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] p:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks [data-dsgo-inline-bg] .wp-element-caption:not(.has-text-color):where(:not(.elementor *):not([data-elementor-type] *)){color:var(--dsgo-text-color,inherit) !important;} .wp-block-designsetgo-section.has-background:not([data-dsgo-inline-bg]):not(.has-text-color){color:var(--wp--preset--color--contrast);--dsgo-text-color:var(--wp--preset--color--contrast);} :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)),.has-background:is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)),.has-text-color:is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)){background-color:var(--wp--preset--color--accent-5);color:var(--wp--preset--color--contrast);} body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) h1:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) h2:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) h3:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) h4:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) h5:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) h6:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) p:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-1:not(:is(.has-white-background-color,.has-black-background-color)) .wp-element-caption:where(:not(.elementor *):not([data-elementor-type] *)){color:var(--wp--preset--color--contrast);} :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)),.has-background:is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)),.has-text-color:is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)){background-color:var(--wp--preset--color--accent-2);color:var(--wp--preset--color--contrast);} body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) h1:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) h2:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) h3:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) h4:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) h5:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) h6:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) p:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-2:not(:is(.has-white-background-color,.has-black-background-color)) .wp-element-caption:where(:not(.elementor *):not([data-elementor-type] *)){color:var(--wp--preset--color--contrast);} :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)),.has-background:is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)),.has-text-color:is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)){background-color:var(--wp--preset--color--accent-1);color:var(--wp--preset--color--contrast);} body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) h1:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) h2:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) h3:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) h4:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) h5:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) h6:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) p:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-3:not(:is(.has-white-background-color,.has-black-background-color)) .wp-element-caption:where(:not(.elementor *):not([data-elementor-type] *)){color:var(--wp--preset--color--contrast);} :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)),.has-background:is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)),.has-text-color:is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)){background-color:var(--wp--preset--color--accent-3);color:var(--wp--preset--color--accent-2);} body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) h1:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) h2:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) h3:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) h4:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) h5:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) h6:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) p:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-4:not(:is(.has-white-background-color,.has-black-background-color)) .wp-element-caption:where(:not(.elementor *):not([data-elementor-type] *)){color:var(--wp--preset--color--accent-2);} :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)),.has-background:is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)),.has-text-color:is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)){background-color:var(--wp--preset--color--contrast);color:var(--wp--preset--color--base);} body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) h1:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) h2:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) h3:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) h4:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) h5:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) h6:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) p:where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) a:where(:not(.wp-element-button):not(.wp-block-social-link-anchor)):where(:not(.elementor *):not([data-elementor-type] *)),body .wp-site-blocks :is(.wp-block-group,.wp-block-column).is-style-section-5:not(:is(.has-white-background-color,.has-black-background-color)) .wp-element-caption:where(:not(.elementor *):not([data-elementor-type] *)){color:var(--wp--preset--color--base);}   body .wp-site-blocks input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="image"]):not([type="hidden"]),body .wp-site-blocks textarea,body .wp-site-blocks select{background-color:var(--wp--preset--color--base);color:var(--wp--preset--color--contrast);border:1px solid color-mix(in srgb, var(--wp--preset--color--contrast) 30%, transparent);} .wp-site-blocks input[type="submit"]:not(.wp-element-button),.wp-site-blocks button[type="submit"]:not(.wp-element-button){background-color:var(--wp--preset--color--accent-2);color:var(--wp--preset--color--base);border-width:0;padding:12px 30px;font-family:inherit;font-size:var(--wp--preset--font-size--medium, 1rem);line-height:inherit;cursor:pointer;}</style>
<style id="wp-custom-css">
.fp-home-btn{
	margin: 30px 0;
}

@media(max-width: 575px){
	.fp-home-btn{
		display: none;
	}
}

.gf-hidden-fields{
	display:none;
}
@media (max-width: 1024px) {
    .featured-icon {
        height: 26px!important;
        width: 26px!important; 
    }
}

.hero__featured {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 1.25rem 1.75rem;
  margin: 1rem 0 0;
  padding: 0;
  list-style: none;
}

.hero__featured-item {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 1 calc(50% - 1.75rem);
}

.hero__logo {
  display: block;
  width: auto;
  height: auto;
  max-width: 7.5rem;
  max-height: 2.25rem;
  object-fit: contain;
}

@media screen and (min-width: 48em) {
  .hero__featured {
    justify-content: center;
    gap: 1.5rem 2rem;
  }

  .hero__featured-item {
    flex: 0 1 calc(33.333% - 2rem);
  }

  .hero__logo {
    max-width: 8.75rem;
    max-height: 2.75rem;
  }
}

@media screen and (min-width: 80em) {
  .hero__featured-item {
    flex: 0 1 calc(25% - 2rem);
  }
}
</style>
		<style id="site-designer-logo-constraints">
			.wp-block-site-logo.is-default-size img {
				width: auto;
				height: auto;
				max-width: 180px;
				max-height: 80px;
			}
			.wp-block-site-logo img {
				height: auto;
			}
		</style>
		<link rel='stylesheet' id='spf_intlTelInput-css' href='/wp-content/plugins/smart-phone-field-for-gravity-forms/assets/css/intlTelInput.min.css?ver=2.2.1' media='all' />


<style id='asp-basic'>@keyframes aspAnFadeIn{0%{opacity:0}50%{opacity:0.6}100%{opacity:1}}@-webkit-keyframes aspAnFadeIn{0%{opacity:0}50%{opacity:0.6}100%{opacity:1}}@keyframes aspAnFadeOut{0%{opacity:1}50%{opacity:0.6}100%{opacity:0}}@-webkit-keyframes aspAnFadeOut{0%{opacity:1}50%{opacity:0.6}100%{opacity:0}}@keyframes aspAnFadeInDrop{0%{opacity:0;transform:translate(0,-50px)}100%{opacity:1;transform:translate(0,0)}}@-webkit-keyframes aspAnFadeInDrop{0%{opacity:0;transform:translate(0,-50px);-webkit-transform:translate(0,-50px)}100%{opacity:1;transform:translate(0,0);-webkit-transform:translate(0,0)}}@keyframes aspAnFadeOutDrop{0%{opacity:1;transform:translate(0,0);-webkit-transform:translate(0,0)}100%{opacity:0;transform:translate(0,-50px);-webkit-transform:translate(0,-50px)}}@-webkit-keyframes aspAnFadeOutDrop{0%{opacity:1;transform:translate(0,0);-webkit-transform:translate(0,0)}100%{opacity:0;transform:translate(0,-50px);-webkit-transform:translate(0,-50px)}}div.ajaxsearchpro.asp_an_fadeIn,div.ajaxsearchpro.asp_an_fadeOut,div.ajaxsearchpro.asp_an_fadeInDrop,div.ajaxsearchpro.asp_an_fadeOutDrop{-webkit-animation-duration:100ms;animation-duration:100ms;-webkit-animation-fill-mode:forwards;animation-fill-mode:forwards}.asp_an_fadeIn,div.ajaxsearchpro.asp_an_fadeIn{animation-name:aspAnFadeIn;-webkit-animation-name:aspAnFadeIn}.asp_an_fadeOut,div.ajaxsearchpro.asp_an_fadeOut{animation-name:aspAnFadeOut;-webkit-animation-name:aspAnFadeOut}div.ajaxsearchpro.asp_an_fadeInDrop{animation-name:aspAnFadeInDrop;-webkit-animation-name:aspAnFadeInDrop}div.ajaxsearchpro.asp_an_fadeOutDrop{animation-name:aspAnFadeOutDrop;-webkit-animation-name:aspAnFadeOutDrop}div.ajaxsearchpro.asp_main_container{transition:width 130ms linear;-webkit-transition:width 130ms linear}asp_w_container,div.asp_w.ajaxsearchpro,div.asp_w.asp_r,div.asp_w.asp_s,div.asp_w.asp_sb,div.asp_w.asp_sb *{-webkit-box-sizing:content-box;-moz-box-sizing:content-box;-ms-box-sizing:content-box;-o-box-sizing:content-box;box-sizing:content-box;padding:0;margin:0;border:0;border-radius:0;text-transform:none;text-shadow:none;box-shadow:none;text-decoration:none;text-align:left;text-indent:initial;letter-spacing:normal;font-display:swap}div.asp_w_container div[id*=__original__]{display:none !important}div.asp_w.ajaxsearchpro{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;-ms-box-sizing:border-box;-o-box-sizing:border-box;box-sizing:border-box}div.asp_w.asp_r,div.asp_w.asp_r *{-webkit-touch-callout:none;-webkit-user-select:none;-khtml-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}div.asp_w.ajaxsearchpro input[type=text]::-ms-clear{display:none;width :0;height:0}div.asp_w.ajaxsearchpro input[type=text]::-ms-reveal{display:none;width :0;height:0}div.asp_w.ajaxsearchpro input[type="search"]::-webkit-search-decoration,div.asp_w.ajaxsearchpro input[type="search"]::-webkit-search-cancel-button,div.asp_w.ajaxsearchpro input[type="search"]::-webkit-search-results-button,div.asp_w.ajaxsearchpro input[type="search"]::-webkit-search-results-decoration{display:none}div.asp_w.ajaxsearchpro input[type="search"]{appearance:auto !important;-webkit-appearance:none !important}.clear{clear:both}.asp_clear{display:block !important;clear:both !important;margin:0 !important;padding:0 !important;width:auto !important;height:0 !important}.hiddend{display:none !important}div.asp_w.ajaxsearchpro{width:100%;height:auto;border-radius:0;background:rgba(255,255,255,0);overflow:hidden}div.asp_w.ajaxsearchpro.asp_non_compact{min-width:200px}#asp_absolute_overlay{width:0;height:0;position:fixed;background:rgba(255,255,255,0.5);top:0;left:0;display:block;z-index:0;opacity:0;transition:opacity 200ms linear;-webkit-transition:opacity 200ms linear}div.asp_m.ajaxsearchpro .proinput input:before,div.asp_m.ajaxsearchpro .proinput input:after,div.asp_m.ajaxsearchpro .proinput form:before,div.asp_m.ajaxsearchpro .proinput form:after{display:none}div.asp_w.ajaxsearchpro textarea:focus,div.asp_w.ajaxsearchpro input:focus{outline:none}div.asp_m.ajaxsearchpro .probox .proinput input::-ms-clear{display:none}div.asp_m.ajaxsearchpro .probox{width:auto;border-radius:5px;background:#FFF;overflow:hidden;border:1px solid #FFF;box-shadow:1px 0 3px #CCC inset;display:-webkit-flex;display:flex;-webkit-flex-direction:row;flex-direction:row;direction:ltr;align-items:stretch;isolation:isolate}div.asp_m.ajaxsearchpro .probox .proinput{width:1px;height:100%;float:left;box-shadow:none;position:relative;flex:1 1 auto;-webkit-flex:1 1 auto;z-index:0}div.asp_m.ajaxsearchpro .probox .proinput form{height:100%;margin:0 !important;padding:0 !important;display:block !important;max-width:unset !important}div.asp_m.ajaxsearchpro .probox .proinput input{height:100%;width:100%;border:0;background:transparent;box-shadow:none;padding:0;left:0;padding-top:2px;min-width:120px;min-height:unset;max-height:unset}div.asp_m.ajaxsearchpro .probox .proinput input.autocomplete{border:0;background:transparent;width:100%;box-shadow:none;margin:0;padding:0;left:0}div.asp_m.ajaxsearchpro .probox .proinput.iepaddingfix{padding-top:0}div.asp_m.ajaxsearchpro .probox .proloading,div.asp_m.ajaxsearchpro .probox .proclose,div.asp_m.ajaxsearchpro .probox .promagnifier,div.asp_m.ajaxsearchpro .probox .prosettings{width:20px;height:20px;min-width:unset;min-height:unset;background:none;background-size:20px 20px;float:right;box-shadow:none;margin:0;padding:0;z-index:1}div.asp_m.ajaxsearchpro button.promagnifier:focus-visible{box-shadow:inset 0 0 0 2px rgba(0,0,0,0.4)}div.asp_m.ajaxsearchpro .probox .proloading,div.asp_m.ajaxsearchpro .probox .proclose{background-position:center center;display:none;background-size:auto;background-repeat:no-repeat;background-color:transparent}div.asp_m.ajaxsearchpro .probox .proloading{padding:2px;box-sizing:border-box}div.asp_m.ajaxsearchpro .probox .proclose{position:relative;cursor:pointer;z-index:2}div.asp_m.ajaxsearchpro .probox .promagnifier .innericon,div.asp_m.ajaxsearchpro .probox .prosettings .innericon,div.asp_m.ajaxsearchpro .probox .proclose .innericon{background-size:20px 20px;background-position:center center;background-repeat:no-repeat;background-color:transparent;width:100%;height:100%;line-height:initial;text-align:center;overflow:hidden}div.asp_m.ajaxsearchpro .probox .promagnifier .innericon svg,div.asp_m.ajaxsearchpro .probox .prosettings .innericon svg,div.asp_m.ajaxsearchpro .probox .proloading svg{height:100%;width:22px;vertical-align:baseline;display:inline-block}div.asp_m.ajaxsearchpro .probox .proclose svg{background:#333;position:absolute;top:50%;width:20px;height:20px;left:50%;fill:#fefefe;box-sizing:border-box;box-shadow:0 0 0 2px rgba(255,255,255,0.9)}.opacityOne{opacity:1}.opacityZero{opacity:0}div.asp_w.asp_s [disabled].noUi-connect,div.asp_w.asp_s [disabled] .noUi-connect{background:#B8B8B8}div.asp_w.asp_s [disabled] .noUi-handle{cursor:not-allowed}div.asp_w.asp_r p.showmore{display:none;margin:0}div.asp_w.asp_r.asp_more_res_loading p.showmore a,div.asp_w.asp_r.asp_more_res_loading p.showmore a span{color:transparent !important}@-webkit-keyframes shm-rot-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg);opacity:1}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg);opacity:0.85}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg);opacity:1}}@keyframes shm-rot-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg);opacity:1}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg);opacity:0.85}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg);opacity:1}}div.asp_w.asp_r div.asp_showmore_container{position:relative}div.asp_w.asp_r. div.asp_moreres_loader{display:none;position:absolute;width:100%;height:100%;top:0;left:0;background:rgba(255,255,255,0.2)}div.asp_w.asp_r.asp_more_res_loading div.asp_moreres_loader{display:block !important}div.asp_w.asp_r div.asp_moreres_loader-inner{height:24px;width:24px;animation:shm-rot-simple 0.8s infinite linear;-webkit-animation:shm-rot-simple 0.8s infinite linear;border:4px solid #353535;border-right-color:transparent;border-radius:50%;box-sizing:border-box;position:absolute;top:50%;margin:-12px auto auto -12px;left:50%}div.asp_hidden_data,div.asp_hidden_data *{display:none}div.asp_w.asp_r{display:none}div.asp_w.asp_r *{text-decoration:none;text-shadow:none}div.asp_w.asp_r .results{overflow:hidden;width:auto;height:0;margin:0;padding:0}div.asp_w.asp_r .asp_nores{display:flex;flex-wrap:wrap;gap:8px;overflow:hidden;width:auto;height:auto;position:relative;z-index:2}div.asp_w.asp_r .results .item{overflow:hidden;width:auto;margin:0;padding:3px;position:relative;background:#f4f4f4;border-left:1px solid rgba(255,255,255,0.6);border-right:1px solid rgba(255,255,255,0.4)}div.asp_w.asp_r .results .item,div.asp_w.asp_r .results .asp_group_header{animation-delay:0s;animation-duration:0.5s;animation-fill-mode:both;animation-timing-function:ease;backface-visibility:hidden;-webkit-animation-delay:0s;-webkit-animation-duration:0.5s;-webkit-animation-fill-mode:both;-webkit-animation-timing-function:ease;-webkit-backface-visibility:hidden}div.asp_w.asp_r .results .item .asp_image{overflow:hidden;background:transparent;padding:0;float:left;background-position:center;background-size:cover;position:relative}div.asp_w.asp_r .results .asp_image canvas{display:none}div.asp_w.asp_r .results .asp_image .asp_item_canvas{position:absolute;top:0;left:0;right:0;bottom:0;margin:0;width:100%;height:100%;z-index:1;display:block;opacity:1;background-position:inherit;background-size:inherit;transition:opacity 0.5s}div.asp_w.asp_r .results .item:hover .asp_image .asp_item_canvas,div.asp_w.asp_r .results figure:hover .asp_image .asp_item_canvas{opacity:0}div.asp_w.asp_r a.asp_res_image_url,div.asp_w.asp_r a.asp_res_image_url:hover,div.asp_w.asp_r a.asp_res_image_url:focus,div.asp_w.asp_r a.asp_res_image_url:active{box-shadow:none !important;border:none !important;margin:0 !important;padding:0 !important;display:inline !important}div.asp_w.asp_r .results .item .asp_image_auto{width:auto !important;height:auto !important}div.asp_w.asp_r .results .item .asp_image img{width:100%;height:100%}div.asp_w.asp_r .results a span.overlap{position:absolute;width:100%;height:100%;top:0;left:0;z-index:1}div.asp_w.asp_r .resdrg{height:auto}div.asp_w.ajaxsearchpro .asp_results_group{margin:10px 0 0 0}div.asp_w.ajaxsearchpro .asp_results_group:first-of-type{margin:0 !important}div.asp_w.asp_r.vertical .results .item:first-child{border-radius:0}div.asp_w.asp_r.vertical .results .item:last-child{border-radius:0;margin-bottom:0}div.asp_w.asp_r.vertical .results .item:last-child:after{height:0;margin:0;width:0}div.asp_w.asp_s.searchsettings{width:auto;height:auto;position:absolute;display:none;z-index:11001;border-radius:0 0 3px 3px;visibility:hidden;opacity:0;overflow:visible}div.asp_w.asp_sb.searchsettings{display:none;visibility:hidden;direction:ltr;overflow:visible;position:relative;z-index:1}div.asp_w.asp_sb.searchsettings .asp_sett_scroll::-webkit-scrollbar-thumb{background:transparent;border-radius:5px;border:none}div.asp_w.asp_s.searchsettings form,div.asp_w.asp_sb.searchsettings form{display:flex;flex-wrap:wrap;margin:0 0 12px 0 !important;padding:0 !important}div.asp_w.asp_s.searchsettings .asp_option_inner,div.asp_w.asp_sb.searchsettings .asp_option_inner,div.asp_w.asp_sb.searchsettings input[type='text']{margin:2px 10px 0 10px;*padding-bottom:10px}div.asp_w.asp_s.searchsettings input[type='text']:not(.asp_select2-search__field),div.asp_w.asp_sb.searchsettings input[type='text']:not(.asp_select2-search__field){width:86% !important;padding:8px 6px !important;margin:0 0 0 10px !important;background-color:#FAFAFA !important;font-size:13px;border:none !important;line-height:17px;height:20px}div.asp_w.asp_s.searchsettings.ie78 .asp_option_inner,div.asp_w.asp_sb.searchsettings.ie78 .asp_option_inner{margin-bottom:0 !important;padding-bottom:0 !important}div.asp_w.asp_s.searchsettings div.asp_option_label,div.asp_w.asp_sb.searchsettings div.asp_option_label{font-size:14px;line-height:20px !important;margin:0;width:150px;text-shadow:none;padding:0;min-height:20px;border:none;background:transparent;float:none;-webkit-touch-callout:none;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}div.asp_w.asp_s.searchsettings .asp_label,div.asp_w.asp_sb.searchsettings .asp_label{line-height:24px !important;vertical-align:middle;display:inline-block;cursor:pointer}div.asp_w.asp_s.searchsettings input[type=radio],div.asp_w.asp_sb.searchsettings input[type=radio]{vertical-align:middle;margin:0 6px 0 17px;display:inline-block;appearance:normal;-moz-appearance:normal;-webkit-appearance:radio}div.asp_w.asp_s.searchsettings .asp_option_inner input[type=checkbox],div.asp_w.asp_sb.searchsettings .asp_option_inner input[type=checkbox]{display:none !important}div.asp_w.asp_s.searchsettings.ie78 .asp_option_inner input[type=checkbox],div.asp_w.asp_sb.searchsettings.ie78 .asp_option_inner input[type=checkbox]{display:block}div.asp_w.asp_s.searchsettings.ie78 div.asp_option_label,div.asp_w.asp_sb.searchsettings.ie78 div.asp_option_label{float:right !important}div.asp_w.asp_s.searchsettings .asp_option_inner,div.asp_w.asp_sb.searchsettings .asp_option_inner{width:17px;height:17px;position:relative;flex-grow:0;-webkit-flex-grow:0;flex-shrink:0;-webkit-flex-shrink:0}div.asp_w.asp_sb.searchsettings .asp_option_inner{border-radius:3px;background:rgb(66,66,66);box-shadow:none}div.asp_w.asp_s.searchsettings .asp_option_inner .asp_option_checkbox,div.asp_w.asp_sb.searchsettings .asp_option_inner .asp_option_checkbox{cursor:pointer;position:absolute;width:17px;height:17px;top:0;padding:0;border-radius:2px;box-shadow:none;font-size:0 !important;color:rgba(0,0,0,0)}div.asp_w.asp_s.searchsettings.ie78 .asp_option_inner .asp_option_checkbox,div.asp_w.asp_sb.searchsettings.ie78 .asp_option_inner .asp_option_checkbox{display:none}div.asp_w.asp_s.searchsettings .asp_option_inner .asp_option_checkbox:before,div.asp_w.asp_sb.searchsettings .asp_option_inner .asp_option_checkbox:before{display:none !important}div.asp_w.asp_s.searchsettings .asp_option_inner .asp_option_checkbox:after,div.asp_w.asp_sb.searchsettings .asp_option_inner .asp_option_checkbox:after{opacity:0;font-family:'asppsicons2';content:"\e800";background:transparent;border-top:none;border-right:none;box-sizing:content-box;height:100%;width:100%;padding:0 !important;position:absolute;top:0;left:0}div.asp_w.asp_s.searchsettings.ie78 .asp_option_inner .asp_option_checkbox:after,div.asp_w.asp_sb.searchsettings.ie78 .asp_option_inner .asp_option_checkbox:after{display:none}div.asp_w.asp_s.searchsettings .asp_option_inner .asp_option_checkbox:hover::after,div.asp_w.asp_sb.searchsettings .asp_option_inner .asp_option_checkbox:hover::after{opacity:0.3}div.asp_w.asp_s.searchsettings .asp_option_inner input[type=checkbox]:checked ~ div:after,div.asp_w.asp_sb.searchsettings .asp_option_inner input[type=checkbox]:checked ~ div:after{opacity:1}div.asp_w.asp_sb.searchsettings span.checked ~ div:after,div.asp_w.asp_s.searchsettings span.checked ~ div:after{opacity:1 !important}div.asp_w.asp_s.searchsettings fieldset,div.asp_w.asp_sb.searchsettings fieldset{position:relative;float:left}div.asp_w.asp_s.searchsettings fieldset,div.asp_w.asp_sb.searchsettings fieldset{background:transparent;font-size:.9em;margin:12px 0 0 !important;padding:0 !important;width:200px;min-width:200px}div.asp_w.asp_sb.searchsettings fieldset:last-child{margin:5px 0 0 !important}div.asp_w.asp_sb.searchsettings fieldset{margin:10px 0 0}div.asp_w.asp_sb.searchsettings fieldset legend{padding:0 0 0 10px;margin:0;font-weight:normal;font-size:13px}div.asp_w.asp_sb.searchsettings .asp_option,div.asp_w.asp_s.searchsettings .asp_option{display:flex;flex-direction:row;-webkit-flex-direction:row;align-items:flex-start;margin:0 0 10px 0;cursor:pointer}div.asp_w.asp_sb.searchsettings .asp_option:focus,div.asp_w.asp_s.searchsettings .asp_option:focus{outline:none}div.asp_w.asp_sb.searchsettings .asp_option:focus-visible,div.asp_w.asp_s.searchsettings .asp_option:focus-visible{outline-style:auto}div.asp_w.asp_s.searchsettings .asp_option.asp-o-last,div.asp_w.asp_s.searchsettings .asp_option:last-child{margin-bottom:0}div.asp_w.asp_s.searchsettings fieldset .asp_option,div.asp_w.asp_s.searchsettings fieldset .asp_option_cat,div.asp_w.asp_sb.searchsettings fieldset .asp_option,div.asp_w.asp_sb.searchsettings fieldset .asp_option_cat{width:auto;max-width:none}div.asp_w.asp_s.searchsettings fieldset .asp_option_cat_level-1,div.asp_w.asp_sb.searchsettings fieldset .asp_option_cat_level-1{margin-left:12px}div.asp_w.asp_s.searchsettings fieldset .asp_option_cat_level-2,div.asp_w.asp_sb.searchsettings fieldset .asp_option_cat_level-2{margin-left:24px}div.asp_w.asp_s.searchsettings fieldset .asp_option_cat_level-3,div.asp_w.asp_sb.searchsettings fieldset .asp_option_cat_level-3{margin-left:36px}div.asp_w.asp_s.searchsettings fieldset div.asp_option_label,div.asp_w.asp_sb.searchsettings fieldset div.asp_option_label{width:70%;display:block}div.asp_w.asp_s.searchsettings fieldset div.asp_option_label{width:auto;display:block;box-sizing:border-box}div.asp_w.asp_s.searchsettings fieldset .asp_option_cat_level-2 div.asp_option_label{padding-right:12px}div.asp_w.asp_s.searchsettings fieldset .asp_option_cat_level-3 div.asp_option_label{padding-right:24px}div.asp_w.asp_s select,div.asp_w.asp_sb select{width:100%;max-width:100%;border-radius:0;padding:5px !important;background:#f9f9f9;background-clip:padding-box;-webkit-box-shadow:none;box-shadow:none;margin:0;border:none;color:#111;margin-bottom:0 !important;box-sizing:border-box;line-height:initial;outline:none !important;font-family:Roboto,Helvetica;font-size:14px;height:34px;min-height:unset !important}div.asp_w.asp_s select[multiple],div.asp_w.asp_sb select[multiple]{background:#fff}div.asp_w.asp_s select:not([multiple]),div.asp_w.asp_sb select:not([multiple]){overflow:hidden !important}div.asp_w.asp_s .asp-nr-container,div.asp_w.asp_sb .asp-nr-container{display:flex;gap:8px;justify-content:space-between}div.ajaxsearchpro.searchsettings fieldset.asp_custom_f{margin-top:9px}div.ajaxsearchpro.searchsettings fieldset legend{margin-bottom:8px !important;-webkit-touch-callout:none;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}div.ajaxsearchpro.searchsettings fieldset legend + div.asp_option_inner{margin-top:0 !important}div.ajaxsearchpro.searchsettings .asp_sett_scroll>.asp_option_cat:first-child>.asp_option_inner{margin-top:0 !important}div.ajaxsearchpro.searchsettings fieldset .asp_select_single,div.ajaxsearchpro.searchsettings fieldset .asp_select_multiple{padding:0 10px}.asp_arrow_box{position:absolute;background:#444;padding:12px;color:white;border-radius:4px;font-size:14px;max-width:240px;display:none;z-index:99999999999999999}.asp_arrow_box:after{top:100%;left:50%;border:solid transparent;content:" ";height:0;width:0;position:absolute;pointer-events:none;border-color:transparent;border-top-color:#444;border-width:6px;margin-left:-6px}.asp_arrow_box.asp_arrow_box_bottom:after{bottom:100%;top:unset;border-bottom-color:#444;border-top-color:transparent}.asp_two_column{margin:8px 0 12px 0}.asp_two_column .asp_two_column_first,.asp_two_column .asp_two_column_last{width:48%;padding:1% 2% 1% 0;float:left;box-sizing:content-box}.asp_two_column .asp_two_column_last{padding:1% 0 1% 2%}.asp_shortcodes_container{display:-webkit-flex;display:flex;-webkit-flex-wrap:wrap;flex-wrap:wrap;margin:-10px 0 12px -10px;box-sizing:border-box}.asp_shortcodes_container .asp_shortcode_column{-webkit-flex-grow:0;flex-grow:0;-webkit-flex-shrink:1;flex-shrink:1;min-width:120px;padding:10px 0 0 10px;flex-basis:33%;-webkit-flex-basis:33%;box-sizing:border-box}p.asp-try{color:#555;font-size:14px;margin-top:5px;line-height:28px;font-weight:300;visibility:hidden}p.asp-try a{color:#FFB556;margin-left:10px;cursor:pointer;display:inline-block}.asp_ac_autocomplete,.asp_ac_autocomplete div,.asp_ac_autocomplete span{}.asp_ac_autocomplete{display:inline;position:relative;word-spacing:normal;text-transform:none;text-indent:0;text-shadow:none;text-align:start}.asp_ac_autocomplete .asp_ac_autocomplete_dropdown{position:absolute;border:1px solid #ccc;border-top-color:#d9d9d9;box-shadow:0 2px 4px rgba(0,0,0,0.2);-webkit-box-shadow:0 2px 4px rgba(0,0,0,0.2);cursor:default;display:none;z-index:1001;margin-top:-1px;background-color:#fff;min-width:100%;overflow:auto}.asp_ac_autocomplete .asp_ac_autocomplete_hint{position:absolute;z-index:1;color:#ccc !important;-webkit-text-fill-color:#ccc !important;text-fill-color:#ccc !important;overflow:hidden !important;white-space:pre !important}.asp_ac_autocomplete .asp_ac_autocomplete_hint span{color:transparent;opacity:0.0}.asp_ac_autocomplete .asp_ac_autocomplete_dropdown>div{background:#fff;white-space:nowrap;cursor:pointer;line-height:1.5em;padding:2px 0 2px 0}.asp_ac_autocomplete .asp_ac_autocomplete_dropdown>div.active{background:#0097CF;color:#FFF}.rtl .asp_content,.rtl .asp_nores,.rtl .asp_content *,.rtl .asp_nores *,.rtl .searchsettings form{text-align:right !important;direction:rtl !important}.rtl .asp_nores>*{display:inline-block}.rtl .searchsettings .asp_option{flex-direction:row-reverse !important;-webkit-flex-direction:row-reverse !important}.rtl .asp_option{direction:ltr}.rtl .asp_label,.rtl .asp_option div.asp_option_label{text-align:right !important}.rtl .asp_label{max-width:1000px !important;width:100%;direction:rtl !important}.rtl .asp_label input[type=radio]{margin:0 0 0 6px !important}.rtl .asp_option_cat_level-0 div.asp_option_label{font-weight:bold !important}.rtl fieldset .asp_option_cat_level-1{margin-right:12px !important;margin-left:0}.rtl fieldset .asp_option_cat_level-2{margin-right:24px !important;margin-left:0}.rtl fieldset .asp_option_cat_level-3{margin-right:36px !important;margin-left:0}.rtl .searchsettings legend{text-align:right !important;display:block;width:100%}.rtl .searchsettings input[type=text],.rtl .searchsettings select{direction:rtl !important;text-align:right !important}.rtl div.asp_w.asp_s.searchsettings form,.rtl div.asp_w.asp_sb.searchsettings form{flex-direction:row-reverse !important}.rtl div.horizontal.asp_r div.item{float:right !important}.rtl p.asp-try{direction:rtl;text-align:right;margin-right:10px;width:auto !important}.asp_elementor_nores{text-align:center}.elementor-sticky__spacer .asp_w,.elementor-sticky__spacer .asp-try{visibility:hidden !important;opacity:0 !important;z-index:-1 !important}</style><style id='asp-instance-1'>div[id*='ajaxsearchpro1_'] div.asp_loader,div[id*='ajaxsearchpro1_'] div.asp_loader *{box-sizing:border-box !important;margin:0;padding:0;box-shadow:none}div[id*='ajaxsearchpro1_'] div.asp_loader{box-sizing:border-box;display:flex;flex:0 1 auto;flex-direction:column;flex-grow:0;flex-shrink:0;flex-basis:28px;max-width:100%;max-height:100%;align-items:center;justify-content:center}div[id*='ajaxsearchpro1_'] div.asp_loader-inner{width:100%;margin:0 auto;text-align:center;height:100%}@-webkit-keyframes rotate-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}@keyframes rotate-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}div[id*='ajaxsearchpro1_'] div.asp_simple-circle{margin:0;height:100%;width:100%;animation:rotate-simple 0.8s infinite linear;-webkit-animation:rotate-simple 0.8s infinite linear;border:4px solid rgb(255,255,255);border-right-color:transparent;border-radius:50%;box-sizing:border-box}div[id*='ajaxsearchprores1_'] .asp_res_loader div.asp_loader,div[id*='ajaxsearchprores1_'] .asp_res_loader div.asp_loader *{box-sizing:border-box !important;margin:0;padding:0;box-shadow:none}div[id*='ajaxsearchprores1_'] .asp_res_loader div.asp_loader{box-sizing:border-box;display:flex;flex:0 1 auto;flex-direction:column;flex-grow:0;flex-shrink:0;flex-basis:28px;max-width:100%;max-height:100%;align-items:center;justify-content:center}div[id*='ajaxsearchprores1_'] .asp_res_loader div.asp_loader-inner{width:100%;margin:0 auto;text-align:center;height:100%}@-webkit-keyframes rotate-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}@keyframes rotate-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}div[id*='ajaxsearchprores1_'] .asp_res_loader div.asp_simple-circle{margin:0;height:100%;width:100%;animation:rotate-simple 0.8s infinite linear;-webkit-animation:rotate-simple 0.8s infinite linear;border:4px solid rgb(255,255,255);border-right-color:transparent;border-radius:50%;box-sizing:border-box}#ajaxsearchpro1_1 div.asp_loader,#ajaxsearchpro1_2 div.asp_loader,#ajaxsearchpro1_1 div.asp_loader *,#ajaxsearchpro1_2 div.asp_loader *{box-sizing:border-box !important;margin:0;padding:0;box-shadow:none}#ajaxsearchpro1_1 div.asp_loader,#ajaxsearchpro1_2 div.asp_loader{box-sizing:border-box;display:flex;flex:0 1 auto;flex-direction:column;flex-grow:0;flex-shrink:0;flex-basis:28px;max-width:100%;max-height:100%;align-items:center;justify-content:center}#ajaxsearchpro1_1 div.asp_loader-inner,#ajaxsearchpro1_2 div.asp_loader-inner{width:100%;margin:0 auto;text-align:center;height:100%}@-webkit-keyframes rotate-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}@keyframes rotate-simple{0%{-webkit-transform:rotate(0deg);transform:rotate(0deg)}50%{-webkit-transform:rotate(180deg);transform:rotate(180deg)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}#ajaxsearchpro1_1 div.asp_simple-circle,#ajaxsearchpro1_2 div.asp_simple-circle{margin:0;height:100%;width:100%;animation:rotate-simple 0.8s infinite linear;-webkit-animation:rotate-simple 0.8s infinite linear;border:4px solid rgb(255,255,255);border-right-color:transparent;border-radius:50%;box-sizing:border-box}@-webkit-keyframes asp_an_fadeInDown{0%{opacity:0;-webkit-transform:translateY(-20px)}100%{opacity:1;-webkit-transform:translateY(0)}}@keyframes asp_an_fadeInDown{0%{opacity:0;transform:translateY(-20px)}100%{opacity:1;transform:translateY(0)}}.asp_an_fadeInDown{-webkit-animation-name:asp_an_fadeInDown;animation-name:asp_an_fadeInDown}div.asp_r.asp_r_1,div.asp_r.asp_r_1 *,div.asp_m.asp_m_1,div.asp_m.asp_m_1 *,div.asp_s.asp_s_1,div.asp_s.asp_s_1 *{-webkit-box-sizing:content-box;-moz-box-sizing:content-box;-ms-box-sizing:content-box;-o-box-sizing:content-box;box-sizing:content-box;border:0;border-radius:0;text-transform:none;text-shadow:none;box-shadow:none;text-decoration:none;text-align:left;letter-spacing:normal}div.asp_r.asp_r_1,div.asp_m.asp_m_1,div.asp_s.asp_s_1{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;-ms-box-sizing:border-box;-o-box-sizing:border-box;box-sizing:border-box}div.asp_r.asp_r_1,div.asp_r.asp_r_1 *,div.asp_m.asp_m_1,div.asp_m.asp_m_1 *,div.asp_s.asp_s_1,div.asp_s.asp_s_1 *{padding:0;margin:0}.wpdreams_clear{clear:both}.asp_w_container_1{width:100%}#ajaxsearchpro1_1,#ajaxsearchpro1_2,div.asp_m.asp_m_1{width:100%;height:auto;max-height:none;border-radius:5px;background:#d1eaff;margin-top:0;margin-bottom:0;background-image:-moz-radial-gradient(center,ellipse cover,rgb(225,99,92),rgb(225,99,92));background-image:-webkit-gradient(radial,center center,0px,center center,100%,rgb(225,99,92),rgb(225,99,92));background-image:-webkit-radial-gradient(center,ellipse cover,rgb(225,99,92),rgb(225,99,92));background-image:-o-radial-gradient(center,ellipse cover,rgb(225,99,92),rgb(225,99,92));background-image:-ms-radial-gradient(center,ellipse cover,rgb(225,99,92),rgb(225,99,92));background-image:radial-gradient(ellipse at center,rgb(225,99,92),rgb(225,99,92));overflow:hidden;border:0 none rgb(141,213,239);border-radius:0;box-shadow:none}#ajaxsearchpro1_1 .probox,#ajaxsearchpro1_2 .probox,div.asp_m.asp_m_1 .probox{margin:0;height:34px;background:transparent;border:0 solid rgb(104,174,199);border-radius:0;box-shadow:none}p[id*=asp-try-1]{color:rgb(85,85,85) !important;display:block}div.asp_main_container+[id*=asp-try-1]{width:100%}p[id*=asp-try-1] a{color:rgb(255,181,86) !important}p[id*=asp-try-1] a:after{color:rgb(85,85,85) !important;display:inline;content:','}p[id*=asp-try-1] a:last-child:after{display:none}#ajaxsearchpro1_1 .probox .proinput,#ajaxsearchpro1_2 .probox .proinput,div.asp_m.asp_m_1 .probox .proinput{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;line-height:15px;text-shadow:none;line-height:normal;flex-grow:1;order:5;margin:0 0 0 10px;padding:0 5px}#ajaxsearchpro1_1 .probox .proinput input.orig,#ajaxsearchpro1_2 .probox .proinput input.orig,div.asp_m.asp_m_1 .probox .proinput input.orig{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;line-height:15px;text-shadow:none;line-height:normal;border:0;box-shadow:none;height:34px;position:relative;z-index:2;padding:0 !important;padding-top:2px !important;margin:-1px 0 0 -4px !important;width:100%;background:transparent !important}#ajaxsearchpro1_1 .probox .proinput input.autocomplete,#ajaxsearchpro1_2 .probox .proinput input.autocomplete,div.asp_m.asp_m_1 .probox .proinput input.autocomplete{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;line-height:15px;text-shadow:none;line-height:normal;opacity:0.25;height:34px;display:block;position:relative;z-index:1;padding:0 !important;margin:-1px 0 0 -4px !important;margin-top:-34px !important;width:100%;background:transparent !important}.rtl #ajaxsearchpro1_1 .probox .proinput input.orig,.rtl #ajaxsearchpro1_2 .probox .proinput input.orig,.rtl #ajaxsearchpro1_1 .probox .proinput input.autocomplete,.rtl #ajaxsearchpro1_2 .probox .proinput input.autocomplete,.rtl div.asp_m.asp_m_1 .probox .proinput input.orig,.rtl div.asp_m.asp_m_1 .probox .proinput input.autocomplete{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;line-height:15px;text-shadow:none;line-height:normal;direction:rtl;text-align:right}.rtl #ajaxsearchpro1_1 .probox .proinput,.rtl #ajaxsearchpro1_2 .probox .proinput,.rtl div.asp_m.asp_m_1 .probox .proinput{margin-right:2px}.rtl #ajaxsearchpro1_1 .probox .proloading,.rtl #ajaxsearchpro1_1 .probox .proclose,.rtl #ajaxsearchpro1_2 .probox .proloading,.rtl #ajaxsearchpro1_2 .probox .proclose,.rtl div.asp_m.asp_m_1 .probox .proloading,.rtl div.asp_m.asp_m_1 .probox .proclose{order:3}div.asp_m.asp_m_1 .probox .proinput input.orig::-webkit-input-placeholder{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;text-shadow:none;opacity:0.85}div.asp_m.asp_m_1 .probox .proinput input.orig::-moz-placeholder{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;text-shadow:none;opacity:0.85}div.asp_m.asp_m_1 .probox .proinput input.orig:-ms-input-placeholder{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;text-shadow:none;opacity:0.85}div.asp_m.asp_m_1 .probox .proinput input.orig:-moz-placeholder{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;text-shadow:none;opacity:0.85;line-height:normal !important}#ajaxsearchpro1_1 .probox .proinput input.autocomplete,#ajaxsearchpro1_2 .probox .proinput input.autocomplete,div.asp_m.asp_m_1 .probox .proinput input.autocomplete{font-weight:normal;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;line-height:15px;text-shadow:none;line-height:normal;border:0;box-shadow:none}#ajaxsearchpro1_1 .probox .proloading,#ajaxsearchpro1_1 .probox .proclose,#ajaxsearchpro1_1 .probox .promagnifier,#ajaxsearchpro1_1 .probox .prosettings,#ajaxsearchpro1_2 .probox .proloading,#ajaxsearchpro1_2 .probox .proclose,#ajaxsearchpro1_2 .probox .promagnifier,#ajaxsearchpro1_2 .probox .prosettings,div.asp_m.asp_m_1 .probox .proloading,div.asp_m.asp_m_1 .probox .proclose,div.asp_m.asp_m_1 .probox .promagnifier,div.asp_m.asp_m_1 .probox .prosettings{width:34px;height:34px;flex:0 0 34px;flex-grow:0;order:7;text-align:center}#ajaxsearchpro1_1 .probox .proclose svg,#ajaxsearchpro1_2 .probox .proclose svg,div.asp_m.asp_m_1 .probox .proclose svg{fill:rgb(254,254,254);background:rgb(51,51,51);box-shadow:0 0 0 2px rgba(255,255,255,0.9);border-radius:50%;box-sizing:border-box;margin-left:-10px;margin-top:-10px;padding:4px}#ajaxsearchpro1_1 .probox .proloading,#ajaxsearchpro1_2 .probox .proloading,div.asp_m.asp_m_1 .probox .proloading{width:34px;height:34px;min-width:34px;min-height:34px;max-width:34px;max-height:34px}#ajaxsearchpro1_1 .probox .proloading .asp_loader,#ajaxsearchpro1_2 .probox .proloading .asp_loader,div.asp_m.asp_m_1 .probox .proloading .asp_loader{width:30px;height:30px;min-width:30px;min-height:30px;max-width:30px;max-height:30px}#ajaxsearchpro1_1 .probox .promagnifier,#ajaxsearchpro1_2 .probox .promagnifier,div.asp_m.asp_m_1 .probox .promagnifier{width:auto;height:34px;flex:0 0 auto;order:7;-webkit-flex:0 0 auto;-webkit-order:7}div.asp_m.asp_m_1 .probox .promagnifier:focus-visible{outline:black outset}#ajaxsearchpro1_1 .probox .proloading .innericon,#ajaxsearchpro1_2 .probox .proloading .innericon,#ajaxsearchpro1_1 .probox .proclose .innericon,#ajaxsearchpro1_2 .probox .proclose .innericon,#ajaxsearchpro1_1 .probox .promagnifier .innericon,#ajaxsearchpro1_2 .probox .promagnifier .innericon,#ajaxsearchpro1_1 .probox .prosettings .innericon,#ajaxsearchpro1_2 .probox .prosettings .innericon,div.asp_m.asp_m_1 .probox .proloading .innericon,div.asp_m.asp_m_1 .probox .proclose .innericon,div.asp_m.asp_m_1 .probox .promagnifier .innericon,div.asp_m.asp_m_1 .probox .prosettings .innericon{text-align:center}#ajaxsearchpro1_1 .probox .promagnifier .innericon,#ajaxsearchpro1_2 .probox .promagnifier .innericon,div.asp_m.asp_m_1 .probox .promagnifier .innericon{display:block;width:34px;height:34px;float:right}#ajaxsearchpro1_1 .probox .promagnifier .asp_text_button,#ajaxsearchpro1_2 .probox .promagnifier .asp_text_button,div.asp_m.asp_m_1 .probox .promagnifier .asp_text_button{display:block;width:auto;height:34px;float:right;margin:0;padding:0 10px 0 2px;font-weight:normal;font-family:"Open Sans";color:rgba(51,51,51,1);font-size:15px;line-height:normal;text-shadow:none;line-height:34px}#ajaxsearchpro1_1 .probox .promagnifier .innericon svg,#ajaxsearchpro1_2 .probox .promagnifier .innericon svg,div.asp_m.asp_m_1 .probox .promagnifier .innericon svg{fill:rgb(255,255,255)}#ajaxsearchpro1_1 .probox .prosettings .innericon svg,#ajaxsearchpro1_2 .probox .prosettings .innericon svg,div.asp_m.asp_m_1 .probox .prosettings .innericon svg{fill:rgb(255,255,255)}#ajaxsearchpro1_1 .probox .promagnifier,#ajaxsearchpro1_2 .probox .promagnifier,div.asp_m.asp_m_1 .probox .promagnifier{width:34px;height:34px;background-image:-webkit-linear-gradient(180deg,rgb(190,76,70),rgb(190,76,70));background-image:-moz-linear-gradient(180deg,rgb(190,76,70),rgb(190,76,70));background-image:-o-linear-gradient(180deg,rgb(190,76,70),rgb(190,76,70));background-image:-ms-linear-gradient(180deg,rgb(190,76,70) 0,rgb(190,76,70) 100%);background-image:linear-gradient(180deg,rgb(190,76,70),rgb(190,76,70));background-position:center center;background-repeat:no-repeat;order:11;-webkit-order:11;float:right;border:0 solid rgb(0,0,0);border-radius:0;box-shadow:0 0 0 0 rgba(255,255,255,0.61);cursor:pointer;background-size:100% 100%;background-position:center center;background-repeat:no-repeat;cursor:pointer}#ajaxsearchpro1_1 .probox .prosettings,#ajaxsearchpro1_2 .probox .prosettings,div.asp_m.asp_m_1 .probox .prosettings{width:34px;height:34px;background-image:-webkit-linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-image:-moz-linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-image:-o-linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-image:-ms-linear-gradient(185deg,rgb(190,76,70) 0,rgb(190,76,70) 100%);background-image:linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-position:center center;background-repeat:no-repeat;order:10;-webkit-order:10;float:right;border:0 solid rgb(104,174,199);border-radius:0;box-shadow:0 0 0 0 rgba(255,255,255,0.63);cursor:pointer;background-size:100% 100%;align-self:flex-end}#ajaxsearchprores1_1,#ajaxsearchprores1_2,div.asp_r.asp_r_1{position:absolute;z-index:11000;width:auto;margin:12px 0 0 0}#ajaxsearchprores1_1 .asp_nores,#ajaxsearchprores1_2 .asp_nores,div.asp_r.asp_r_1 .asp_nores{border:0 solid rgb(0,0,0);border-radius:0;box-shadow:0 5px 5px -5px #dfdfdf;padding:6px 12px 6px 12px;margin:0;font-weight:normal;font-family:inherit;color:rgba(74,74,74,1);font-size:1rem;line-height:1.2rem;text-shadow:none;font-weight:normal;background:rgb(255,255,255)}#ajaxsearchprores1_1 .asp_nores .asp_nores_kw_suggestions,#ajaxsearchprores1_2 .asp_nores .asp_nores_kw_suggestions,div.asp_r.asp_r_1 .asp_nores .asp_nores_kw_suggestions{color:rgba(234,67,53,1);font-weight:normal}#ajaxsearchprores1_1 .asp_nores .asp_keyword,#ajaxsearchprores1_2 .asp_nores .asp_keyword,div.asp_r.asp_r_1 .asp_nores .asp_keyword{padding:0 8px 0 0;cursor:pointer;color:rgba(20,84,169,1);font-weight:bold}#ajaxsearchprores1_1 .asp_results_top,#ajaxsearchprores1_2 .asp_results_top,div.asp_r.asp_r_1 .asp_results_top{background:rgb(255,255,255);border:1px none rgb(81,81,81);border-radius:0;padding:6px 12px 6px 12px;margin:0 0 4px 0;text-align:center;font-weight:normal;font-family:"Open Sans";color:rgb(74,74,74);font-size:13px;line-height:16px;text-shadow:none}#ajaxsearchprores1_1 .results .item,#ajaxsearchprores1_2 .results .item,div.asp_r.asp_r_1 .results .item{height:auto;background:rgb(255,255,255)}#ajaxsearchprores1_1 .results .item.hovered,#ajaxsearchprores1_2 .results .item.hovered,div.asp_r.asp_r_1 .results .item.hovered{background-image:-moz-radial-gradient(center,ellipse cover,rgb(245,245,245),rgb(245,245,245));background-image:-webkit-gradient(radial,center center,0px,center center,100%,rgb(245,245,245),rgb(245,245,245));background-image:-webkit-radial-gradient(center,ellipse cover,rgb(245,245,245),rgb(245,245,245));background-image:-o-radial-gradient(center,ellipse cover,rgb(245,245,245),rgb(245,245,245));background-image:-ms-radial-gradient(center,ellipse cover,rgb(245,245,245),rgb(245,245,245));background-image:radial-gradient(ellipse at center,rgb(245,245,245),rgb(245,245,245))}#ajaxsearchprores1_1 .results .item .asp_image,#ajaxsearchprores1_2 .results .item .asp_image,div.asp_r.asp_r_1 .results .item .asp_image{background-size:cover;background-repeat:no-repeat}#ajaxsearchprores1_1 .results .item .asp_item_overlay_img,#ajaxsearchprores1_2 .results .item .asp_item_overlay_img,div.asp_r.asp_r_1 .results .item .asp_item_overlay_img{background-size:cover;background-repeat:no-repeat}#ajaxsearchprores1_1 .results .item .asp_content,#ajaxsearchprores1_2 .results .item .asp_content,div.asp_r.asp_r_1 .results .item .asp_content{overflow:hidden;background:transparent;margin:0;padding:0 10px}#ajaxsearchprores1_1 .results .item .asp_content h3,#ajaxsearchprores1_2 .results .item .asp_content h3,div.asp_r.asp_r_1 .results .item .asp_content h3{margin:0;padding:0;display:inline-block;line-height:inherit;font-weight:bold;font-family:"Open Sans";color:rgba(20,84,169,1);font-size:14px;line-height:20px;text-shadow:none}#ajaxsearchprores1_1 .results .item .asp_content h3 a,#ajaxsearchprores1_2 .results .item .asp_content h3 a,div.asp_r.asp_r_1 .results .item .asp_content h3 a{margin:0;padding:0;line-height:inherit;display:block;font-weight:bold;font-family:"Open Sans";color:rgba(20,84,169,1);font-size:14px;line-height:20px;text-shadow:none}#ajaxsearchprores1_1 .results .item .asp_content h3 a:hover,#ajaxsearchprores1_2 .results .item .asp_content h3 a:hover,div.asp_r.asp_r_1 .results .item .asp_content h3 a:hover{font-weight:bold;font-family:"Open Sans";color:rgba(20,84,169,1);font-size:14px;line-height:20px;text-shadow:none}#ajaxsearchprores1_1 .results .item div.etc,#ajaxsearchprores1_2 .results .item div.etc,div.asp_r.asp_r_1 .results .item div.etc{padding:0;font-size:13px;line-height:1.3em;margin-bottom:6px}#ajaxsearchprores1_1 .results .item .etc .asp_author,#ajaxsearchprores1_2 .results .item .etc .asp_author,div.asp_r.asp_r_1 .results .item .etc .asp_author{padding:0;font-weight:bold;font-family:"Open Sans";color:rgba(161,161,161,1);font-size:12px;line-height:13px;text-shadow:none}#ajaxsearchprores1_1 .results .item .etc .asp_date,#ajaxsearchprores1_2 .results .item .etc .asp_date,div.asp_r.asp_r_1 .results .item .etc .asp_date{margin:0 0 0 10px;padding:0;font-weight:normal;font-family:"Open Sans";color:rgba(173,173,173,1);font-size:12px;line-height:15px;text-shadow:none}#ajaxsearchprores1_1 .results .item div.asp_content,#ajaxsearchprores1_2 .results .item div.asp_content,div.asp_r.asp_r_1 .results .item div.asp_content{margin:0;padding:0;font-weight:normal;font-family:"Open Sans";color:rgba(74,74,74,1);font-size:13px;line-height:13px;text-shadow:none}#ajaxsearchprores1_1 span.highlighted,#ajaxsearchprores1_2 span.highlighted,div.asp_r.asp_r_1 span.highlighted{font-weight:bold;color:rgba(217,49,43,1);background-color:rgba(238,238,238,1)}#ajaxsearchprores1_1 p.showmore,#ajaxsearchprores1_2 p.showmore,div.asp_r.asp_r_1 p.showmore{text-align:center;font-weight:normal;font-family:"Open Sans";color:rgba(5,94,148,1);font-size:12px;line-height:15px;text-shadow:none}#ajaxsearchprores1_1 p.showmore a,#ajaxsearchprores1_2 p.showmore a,div.asp_r.asp_r_1 p.showmore a{font-weight:normal;font-family:"Open Sans";color:rgba(5,94,148,1);font-size:12px;line-height:15px;text-shadow:none;padding:10px 5px;margin:0 auto;background:rgba(255,255,255,1);display:block;text-align:center}#ajaxsearchprores1_1 .asp_res_loader,#ajaxsearchprores1_2 .asp_res_loader,div.asp_r.asp_r_1 .asp_res_loader{background:rgb(255,255,255);height:200px;padding:10px}#ajaxsearchprores1_1.isotopic .asp_res_loader,#ajaxsearchprores1_2.isotopic .asp_res_loader,div.asp_r.asp_r_1.isotopic .asp_res_loader{background:rgba(255,255,255,0);}#ajaxsearchprores1_1 .asp_res_loader .asp_loader,#ajaxsearchprores1_2 .asp_res_loader .asp_loader,div.asp_r.asp_r_1 .asp_res_loader .asp_loader{height:200px;width:200px;margin:0 auto}div.asp_s.asp_s_1.searchsettings,div.asp_s.asp_s_1.searchsettings,div.asp_s.asp_s_1.searchsettings{direction:ltr;padding:0;background-image:-webkit-linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-image:-moz-linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-image:-o-linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));background-image:-ms-linear-gradient(185deg,rgb(190,76,70) 0,rgb(190,76,70) 100%);background-image:linear-gradient(185deg,rgb(190,76,70),rgb(190,76,70));box-shadow:none;;max-width:208px;z-index:2}div.asp_s.asp_s_1.searchsettings.asp_s,div.asp_s.asp_s_1.searchsettings.asp_s,div.asp_s.asp_s_1.searchsettings.asp_s{z-index:11001}#ajaxsearchprobsettings1_1.searchsettings,#ajaxsearchprobsettings1_2.searchsettings,div.asp_sb.asp_sb_1.searchsettings{max-width:none}div.asp_s.asp_s_1.searchsettings form,div.asp_s.asp_s_1.searchsettings form,div.asp_s.asp_s_1.searchsettings form{display:flex}div.asp_sb.asp_sb_1.searchsettings form,div.asp_sb.asp_sb_1.searchsettings form,div.asp_sb.asp_sb_1.searchsettings form{display:flex}#ajaxsearchprosettings1_1.searchsettings div.asp_option_label,#ajaxsearchprosettings1_2.searchsettings div.asp_option_label,#ajaxsearchprosettings1_1.searchsettings .asp_label,#ajaxsearchprosettings1_2.searchsettings .asp_label,div.asp_s.asp_s_1.searchsettings div.asp_option_label,div.asp_s.asp_s_1.searchsettings .asp_label{font-weight:bold;font-family:"Open Sans";color:rgb(255,255,255);font-size:12px;line-height:15px;text-shadow:none}#ajaxsearchprosettings1_1.searchsettings .asp_option_inner .asp_option_checkbox,#ajaxsearchprosettings1_2.searchsettings .asp_option_inner .asp_option_checkbox,div.asp_sb.asp_sb_1.searchsettings .asp_option_inner .asp_option_checkbox,div.asp_s.asp_s_1.searchsettings .asp_option_inner .asp_option_checkbox{background-image:-webkit-linear-gradient(180deg,rgb(34,34,34),rgb(69,72,77));background-image:-moz-linear-gradient(180deg,rgb(34,34,34),rgb(69,72,77));background-image:-o-linear-gradient(180deg,rgb(34,34,34),rgb(69,72,77));background-image:-ms-linear-gradient(180deg,rgb(34,34,34) 0,rgb(69,72,77) 100%);background-image:linear-gradient(180deg,rgb(34,34,34),rgb(69,72,77))}#ajaxsearchprosettings1_1.searchsettings .asp_option_inner .asp_option_checkbox:after,#ajaxsearchprosettings1_2.searchsettings .asp_option_inner .asp_option_checkbox:after,#ajaxsearchprobsettings1_1.searchsettings .asp_option_inner .asp_option_checkbox:after,#ajaxsearchprobsettings1_2.searchsettings .asp_option_inner .asp_option_checkbox:after,div.asp_sb.asp_sb_1.searchsettings .asp_option_inner .asp_option_checkbox:after,div.asp_s.asp_s_1.searchsettings .asp_option_inner .asp_option_checkbox:after{font-family:'asppsicons2';border:none;content:"\e800";display:block;position:absolute;top:0;left:0;font-size:11px;color:rgb(255,255,255);margin:1px 0 0 0 !important;line-height:17px;text-align:center;text-decoration:none;text-shadow:none}div.asp_sb.asp_sb_1.searchsettings .asp_sett_scroll,div.asp_s.asp_s_1.searchsettings .asp_sett_scroll{scrollbar-width:thin;scrollbar-color:rgba(0,0,0,0.5) transparent}div.asp_sb.asp_sb_1.searchsettings .asp_sett_scroll::-webkit-scrollbar,div.asp_s.asp_s_1.searchsettings .asp_sett_scroll::-webkit-scrollbar{width:7px}div.asp_sb.asp_sb_1.searchsettings .asp_sett_scroll::-webkit-scrollbar-track,div.asp_s.asp_s_1.searchsettings .asp_sett_scroll::-webkit-scrollbar-track{background:transparent}div.asp_sb.asp_sb_1.searchsettings .asp_sett_scroll::-webkit-scrollbar-thumb,div.asp_s.asp_s_1.searchsettings .asp_sett_scroll::-webkit-scrollbar-thumb{background:rgba(0,0,0,0.5);border-radius:5px;border:none}#ajaxsearchprosettings1_1.searchsettings .asp_sett_scroll,#ajaxsearchprosettings1_2.searchsettings .asp_sett_scroll,div.asp_s.asp_s_1.searchsettings .asp_sett_scroll{max-height:220px;overflow:auto}#ajaxsearchprobsettings1_1.searchsettings .asp_sett_scroll,#ajaxsearchprobsettings1_2.searchsettings .asp_sett_scroll,div.asp_sb.asp_sb_1.searchsettings .asp_sett_scroll{max-height:220px;overflow:auto}#ajaxsearchprosettings1_1.searchsettings fieldset,#ajaxsearchprosettings1_2.searchsettings fieldset,div.asp_s.asp_s_1.searchsettings fieldset{width:200px;min-width:200px;max-width:10000px}#ajaxsearchprobsettings1_1.searchsettings fieldset,#ajaxsearchprobsettings1_2.searchsettings fieldset,div.asp_sb.asp_sb_1.searchsettings fieldset{width:200px;min-width:200px;max-width:10000px}#ajaxsearchprosettings1_1.searchsettings fieldset legend,#ajaxsearchprosettings1_2.searchsettings fieldset legend,div.asp_s.asp_s_1.searchsettings fieldset legend{padding:0 0 0 10px;margin:0;background:transparent;font-weight:normal;font-family:"Open Sans";color:rgb(31,31,31);font-size:13px;line-height:15px;text-shadow:none}#ajaxsearchprores1_1.vertical,#ajaxsearchprores1_2.vertical,div.asp_r.asp_r_1.vertical{padding:4px;background:rgb(225,99,92);border-radius:3px;border:0 none rgba(0,0,0,1);border-radius:0;box-shadow:none;visibility:hidden;display:none}#ajaxsearchprores1_1.vertical .results,#ajaxsearchprores1_2.vertical .results,div.asp_r.asp_r_1.vertical .results{max-height:none;overflow-x:hidden;overflow-y:auto}#ajaxsearchprores1_1.vertical .item,#ajaxsearchprores1_2.vertical .item,div.asp_r.asp_r_1.vertical .item{position:relative;box-sizing:border-box}#ajaxsearchprores1_1.vertical .item .asp_content h3,#ajaxsearchprores1_2.vertical .item .asp_content h3,div.asp_r.asp_r_1.vertical .item .asp_content h3{display:inline}#ajaxsearchprores1_1.vertical .results .item .asp_content,#ajaxsearchprores1_2.vertical .results .item .asp_content,div.asp_r.asp_r_1.vertical .results .item .asp_content{overflow:hidden;width:auto;height:auto;background:transparent;margin:0;padding:8px}#ajaxsearchprores1_1.vertical .results .item .asp_image,#ajaxsearchprores1_2.vertical .results .item .asp_image,div.asp_r.asp_r_1.vertical .results .item .asp_image{width:70px;height:70px;margin:2px 8px 0 0}#ajaxsearchprores1_1.vertical .asp_simplebar-scrollbar::before,#ajaxsearchprores1_2.vertical .asp_simplebar-scrollbar::before,div.asp_r.asp_r_1.vertical .asp_simplebar-scrollbar::before{background:transparent;background-image:-moz-radial-gradient(center,ellipse cover,rgba(0,0,0,0.5),rgba(0,0,0,0.5));background-image:-webkit-gradient(radial,center center,0px,center center,100%,rgba(0,0,0,0.5),rgba(0,0,0,0.5));background-image:-webkit-radial-gradient(center,ellipse cover,rgba(0,0,0,0.5),rgba(0,0,0,0.5));background-image:-o-radial-gradient(center,ellipse cover,rgba(0,0,0,0.5),rgba(0,0,0,0.5));background-image:-ms-radial-gradient(center,ellipse cover,rgba(0,0,0,0.5),rgba(0,0,0,0.5));background-image:radial-gradient(ellipse at center,rgba(0,0,0,0.5),rgba(0,0,0,0.5))}#ajaxsearchprores1_1.vertical .results .item::after,#ajaxsearchprores1_2.vertical .results .item::after,div.asp_r.asp_r_1.vertical .results .item::after{display:block;position:absolute;bottom:0;content:"";height:1px;width:100%;background:rgba(204,204,204,1)}#ajaxsearchprores1_1.vertical .results .item.asp_last_item::after,#ajaxsearchprores1_2.vertical .results .item.asp_last_item::after,div.asp_r.asp_r_1.vertical .results .item.asp_last_item::after{display:none}.asp_spacer{display:none !important;}.asp_v_spacer{width:100%;height:0}#ajaxsearchprores1_1 .asp_group_header,#ajaxsearchprores1_2 .asp_group_header,div.asp_r.asp_r_1 .asp_group_header{background:#DDD;background:rgb(246,246,246);border-radius:3px 3px 0 0;border-top:1px solid rgb(248,248,248);border-left:1px solid rgb(248,248,248);border-right:1px solid rgb(248,248,248);margin:0 0 -3px;padding:7px 0 7px 10px;position:relative;z-index:1000;min-width:90%;flex-grow:1;font-weight:bold;font-family:"Open Sans";color:rgba(5,94,148,1);font-size:11px;line-height:13px;text-shadow:none}#ajaxsearchprores1_1.vertical .results,#ajaxsearchprores1_2.vertical .results,div.asp_r.asp_r_1.vertical .results{scrollbar-width:thin;scrollbar-color:rgba(0,0,0,0.5) rgb(255,255,255)}#ajaxsearchprores1_1.vertical .results::-webkit-scrollbar,#ajaxsearchprores1_2.vertical .results::-webkit-scrollbar,div.asp_r.asp_r_1.vertical .results::-webkit-scrollbar{width:10px}#ajaxsearchprores1_1.vertical .results::-webkit-scrollbar-track,#ajaxsearchprores1_2.vertical .results::-webkit-scrollbar-track,div.asp_r.asp_r_1.vertical .results::-webkit-scrollbar-track{background:rgb(255,255,255);box-shadow:inset 0 0 12px 12px transparent;border:none}#ajaxsearchprores1_1.vertical .results::-webkit-scrollbar-thumb,#ajaxsearchprores1_2.vertical .results::-webkit-scrollbar-thumb,div.asp_r.asp_r_1.vertical .results::-webkit-scrollbar-thumb{background:transparent;box-shadow:inset 0 0 12px 12px rgba(0,0,0,0);border:solid 2px transparent;border-radius:12px}#ajaxsearchprores1_1.vertical:hover .results::-webkit-scrollbar-thumb,#ajaxsearchprores1_2.vertical:hover .results::-webkit-scrollbar-thumb,div.asp_r.asp_r_1.vertical:hover .results::-webkit-scrollbar-thumb{box-shadow:inset 0 0 12px 12px rgba(0,0,0,0.5)}@media(hover:none),(max-width:500px){#ajaxsearchprores1_1.vertical .results::-webkit-scrollbar-thumb,#ajaxsearchprores1_2.vertical .results::-webkit-scrollbar-thumb,div.asp_r.asp_r_1.vertical .results::-webkit-scrollbar-thumb{box-shadow:inset 0 0 12px 12px rgba(0,0,0,0.5)}}</style>
				<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
				<style>
					@font-face {
  font-family: 'Open Sans';
  font-style: normal;
  font-weight: 300;
  font-stretch: normal;
  font-display: swap;
  src: url(https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsiH0B4gaVc.ttf) format('truetype');
}
@font-face {
  font-family: 'Open Sans';
  font-style: normal;
  font-weight: 400;
  font-stretch: normal;
  font-display: swap;
  src: url(https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVc.ttf) format('truetype');
}
@font-face {
  font-family: 'Open Sans';
  font-style: normal;
  font-weight: 700;
  font-stretch: normal;
  font-display: swap;
  src: url(https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsg-1x4gaVc.ttf) format('truetype');
}

				</style></head>

<body class="wp-singular page-template-default page page-id-119 wp-theme-rb-council  numbers-at-top unselectable">
<?php if(get_setting($pdo, 'announcement_bar_active') == '1'): ?>
<div style="background-color: #fecc56; color: #000; text-align: center; padding: 12px; font-weight: bold; z-index: 9999; position: relative; border-bottom: 2px solid #e5b340;">
    <?= htmlspecialchars(get_setting($pdo, 'announcement_bar_text')) ?>
</div>
<?php endif; ?>

	<a class="skip" href="#main">Skip to main content</a>
	<?php require_once '../includes/announcement.php'; ?>


	<header class="site-header  js-header" role="banner" id="top">
		<!--
		<div style="background-color:#fecc56;margin-top: -35px;padding:10px;margin-bottom: 17px;text-align:center;font-size:10pt;">
    <div class="l-container">Our office will be closed from 5:00 PM, Friday, 20 December 2024, and will reopen at 9:00 AM, Monday, 6 January 2025. Wishing you a Merry Christmas and a Happy 2025!</div>
</div> -->
		
		<div class="l-container">
			<a href="/" class="site-header__brand brand">
				<img
					src="/wp-content/themes/rb-council/images/logo.svg"
					alt="IFW Global"
					class="brand__image  js-header-brand"
				>
			</a>

      <nav class="site-header__primary-menu primary-menu" role="navigation">
        <ul id="menu-main-menu" class="primary-menu__list"><li id="menu-item-185" class="primary-menu__item primary-menu__item--185 has-children"><a href="/about-us/" class="primary-menu__link"><span class='primary-menu__label'>About Us</span><svg class="primary-menu__arrow" role="img" focusable="false"><use xlink:href="#svg-chevron--up"></use></svg></a><div class='primary-menu__dropdown  js-primary-menu-dropdown'><div class='primary-menu-submenu primary-menu-submenu--level-1 js-primary-menu-submenu'><ul class='primary-menu-submenu__list  js-primary-menu-list'><li id="menu-item-1556" class="primary-menu-submenu__item primary-menu-submenu__item--1556 is-child"><a href="/about-us/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>About Us</span></a></li>
<li id="menu-item-1555" class="primary-menu-submenu__item primary-menu-submenu__item--1555 is-child"><a href="/contact/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Contact Us</span></a></li>
</ul></div></div></li>
<li id="menu-item-1940" class="primary-menu__item primary-menu__item--1940 has-children"><a href="#" class="primary-menu__link"><span class='primary-menu__label'>Services</span><svg class="primary-menu__arrow" role="img" focusable="false"><use xlink:href="#svg-chevron--up"></use></svg></a><div class='primary-menu__dropdown  js-primary-menu-dropdown'><div class='primary-menu-submenu primary-menu-submenu--level-1 js-primary-menu-submenu'><ul class='primary-menu-submenu__list  js-primary-menu-list'><li id="menu-item-195" class="primary-menu-submenu__item primary-menu-submenu__item--195 is-child"><a href="/scam/serious-and-organised-fraud/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Investment Scam Investigations</span></a></li>
<li id="menu-item-203" class="primary-menu-submenu__item primary-menu-submenu__item--203 is-child"><a href="/investigation/due-diligence/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Background Investigations</span></a></li>
<li id="menu-item-205" class="primary-menu-submenu__item primary-menu-submenu__item--205 is-child"><a href="/investigation/covert-surveillance-investigators/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Covert Surveillance</span></a></li>
<li id="menu-item-1725" class="primary-menu-submenu__item primary-menu-submenu__item--1725 is-child"><a href="/asset-recovery/asset-recovery-scams/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Asset Recovery</span></a></li>
<li id="menu-item-220" class="primary-menu-submenu__item primary-menu-submenu__item--220 is-child"><a href="/asset-recovery/cryptocurrency-tracing/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Cryptocurrency Tracing</span></a></li>
</ul></div></div></li>
<li id="menu-item-222" class="primary-menu__item primary-menu__item--222 has-children"><a href="/media/" class="primary-menu__link"><span class='primary-menu__label'>Media</span><svg class="primary-menu__arrow" role="img" focusable="false"><use xlink:href="#svg-chevron--up"></use></svg></a><div class='primary-menu__dropdown  js-primary-menu-dropdown'><div class='primary-menu-submenu primary-menu-submenu--level-1 js-primary-menu-submenu'><ul class='primary-menu-submenu__list  js-primary-menu-list'><li id="menu-item-2051" class="primary-menu-submenu__item primary-menu-submenu__item--2051 is-child"><a href="/media/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>GFCS 2024</span></a></li>
<li id="menu-item-1554" class="primary-menu-submenu__item primary-menu-submenu__item--1554 is-child"><a href="/scam-alert/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Scam Alert</span></a></li>
<li id="menu-item-224" class="primary-menu-submenu__item primary-menu-submenu__item--224 is-child"><a href="/in-the-news/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>In the news</span></a></li>
<li id="menu-item-225" class="primary-menu-submenu__item primary-menu-submenu__item--225 is-child"><a href="/podcasts/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Podcasts</span></a></li>
<li id="menu-item-223" class="primary-menu-submenu__item primary-menu-submenu__item--223 is-child"><a href="/blog/" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Blog</span></a></li>
</ul></div></div></li>
<li id="menu-item-1924" class="primary-menu__item primary-menu__item--1924 has-children"><a href="#" class="primary-menu__link"><span class='primary-menu__label'>Events</span><svg class="primary-menu__arrow" role="img" focusable="false"><use xlink:href="#svg-chevron--up"></use></svg></a><div class='primary-menu__dropdown  js-primary-menu-dropdown'><div class='primary-menu-submenu primary-menu-submenu--level-1 js-primary-menu-submenu'><ul class='primary-menu-submenu__list  js-primary-menu-list'><li id="menu-item-1925" class="primary-menu-submenu__item primary-menu-submenu__item--1925 is-child"><a href="" class="primary-menu-submenu__link"><span class='primary-menu-submenu__label'>Global Financial Crimes Summit 2024</span></a></li>
</ul></div></div></li>
</ul>      </nav>

      <div class="site-header__search search  js-search">
				<a href="/?s=" class="search__toggle  js-search-toggle">Open Search</a>

				<div class="search__form  js-search-box">
					<div class="asp_w_container asp_w_container_1 asp_w_container_1_1" data-id="1"><div class='asp_w asp_m asp_m_1 asp_m_1_1 wpdreams_asp_sc wpdreams_asp_sc-1 ajaxsearchpro asp_main_container asp_non_compact' data-id="1" data-name="wpdreams_ajaxsearchlite" data-instance="1" id='ajaxsearchpro1_1'><div class="probox"><div class='prosettings' style='display:none;' data-opened=0><div class='innericon'><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 512 512"><path d="M170 294c0 33.138-26.862 60-60 60-33.137 0-60-26.862-60-60 0-33.137 26.863-60 60-60 33.138 0 60 26.863 60 60zm-60 90c-6.872 0-13.565-.777-20-2.243V422c0 11.046 8.954 20 20 20s20-8.954 20-20v-40.243c-6.435 1.466-13.128 2.243-20 2.243zm0-180c6.872 0 13.565.777 20 2.243V90c0-11.046-8.954-20-20-20s-20 8.954-20 20v116.243c6.435-1.466 13.128-2.243 20-2.243zm146-7c12.13 0 22 9.87 22 22s-9.87 22-22 22-22-9.87-22-22 9.87-22 22-22zm0-38c-33.137 0-60 26.863-60 60 0 33.138 26.863 60 60 60 33.138 0 60-26.862 60-60 0-33.137-26.862-60-60-60zm0-30c6.872 0 13.565.777 20 2.243V90c0-11.046-8.954-20-20-20s-20 8.954-20 20v41.243c6.435-1.466 13.128-2.243 20-2.243zm0 180c-6.872 0-13.565-.777-20-2.243V422c0 11.046 8.954 20 20 20s20-8.954 20-20V306.757c-6.435 1.466-13.128 2.243-20 2.243zm146-75c-33.137 0-60 26.863-60 60 0 33.138 26.863 60 60 60 33.138 0 60-26.862 60-60 0-33.137-26.862-60-60-60zm0-30c6.872 0 13.565.777 20 2.243V90c0-11.046-8.954-20-20-20s-20 8.954-20 20v116.243c6.435-1.466 13.128-2.243 20-2.243zm0 180c-6.872 0-13.565-.777-20-2.243V422c0 11.046 8.954 20 20 20s20-8.954 20-20v-40.243c-6.435 1.466-13.128 2.243-20 2.243z"/></svg></div></div><div class='proinput'><form role="search" action='#' autocomplete="off" aria-label="Search form"><input type='search' class='orig' placeholder='Search here..' name='phrase' value='' aria-label="Search input" autocomplete="off"/><input type='text' class='autocomplete' name='phrase' value='' aria-label="Search autocomplete input" aria-hidden="true" tabindex="-1" autocomplete="off" disabled/></form></div><button class='promagnifier' aria-label="Search magnifier button"><span class='asp_text_button hiddend'> Search </span><span class='innericon'><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 512 512"><path d="M460.355 421.59l-106.51-106.512c20.04-27.553 31.884-61.437 31.884-98.037C385.73 124.935 310.792 50 218.685 50c-92.106 0-167.04 74.934-167.04 167.04 0 92.107 74.935 167.042 167.04 167.042 34.912 0 67.352-10.773 94.184-29.158L419.945 462l40.41-40.41zM100.63 217.04c0-65.095 52.96-118.055 118.056-118.055 65.098 0 118.057 52.96 118.057 118.056 0 65.097-52.96 118.057-118.057 118.057-65.096 0-118.055-52.96-118.055-118.056z"/></svg></span><span class="asp_clear"></span></button><div class='proloading'><div class="asp_loader"><div class="asp_loader-inner asp_simple-circle"></div></div></div><div class='proclose'><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="512px" height="512px" viewBox="0 0 512 512" enable-background="new 0 0 512 512" xml:space="preserve"><polygon points="438.393,374.595 319.757,255.977 438.378,137.348 374.595,73.607 255.995,192.225 137.375,73.622 73.607,137.352 192.246,255.983 73.622,374.625 137.352,438.393 256.002,319.734 374.652,438.378 "/></svg></div></div></div><div class='asp_data_container' style="display:none !important;"><div class="asp_init_data" style="display:none !important;" id="asp_init_id_1_1" data-asp-id="1" data-asp-instance="1" data-aspdata="eyJob21ldXJsIjoiaHR0cHM6XC9cL2lmd2dsb2JhbC5jb21cLyIsImlzX3Jlc3VsdHNfcGFnZSI6MCwicmVzdWx0c3R5cGUiOiJ2ZXJ0aWNhbCIsInJlc3VsdHNwb3NpdGlvbiI6ImhvdmVyIiwicmVzdWx0c1NuYXBUbyI6ImxlZnQiLCJyZXN1bHRzIjp7IndpZHRoIjoiYXV0byIsIndpZHRoX3RhYmxldCI6ImF1dG8iLCJ3aWR0aF9waG9uZSI6ImF1dG8ifSwiaXRlbXNjb3VudCI6NCwiY2hhcmNvdW50IjowLCJoaWdobGlnaHQiOjAsImhpZ2hsaWdodFdob2xld29yZHMiOjEsInNpbmdsZUhpZ2hsaWdodCI6MCwic2Nyb2xsVG9SZXN1bHRzIjp7ImVuYWJsZWQiOjAsIm9mZnNldCI6MH0sImF1dG9jb21wbGV0ZSI6eyJlbmFibGVkIjoxLCJ0cmlnZ2VyX2NoYXJjb3VudCI6MCwiZ29vZ2xlT25seSI6MSwibGFuZyI6ImVuIiwibW9iaWxlIjoxfSwidHJpZ2dlciI6eyJkZWxheSI6MzAwLCJhdXRvY29tcGxldGVfZGVsYXkiOjMxMCwidXBkYXRlX2hyZWYiOjAsImZhY2V0IjoxLCJ0eXBlIjoxLCJjbGljayI6InJlc3VsdHNfcGFnZSIsImNsaWNrX2xvY2F0aW9uIjoic2FtZSIsInJldHVybiI6InJlc3VsdHNfcGFnZSIsInJldHVybl9sb2NhdGlvbiI6InNhbWUiLCJyZWRpcmVjdF91cmwiOiI/cz17cGhyYXNlfSIsImVsZW1lbnRvcl91cmwiOiJodHRwczpcL1wvaWZ3Z2xvYmFsLmNvbVwvP2FzcF9scz17cGhyYXNlfSJ9LCJvdmVycmlkZXdwZGVmYXVsdCI6MCwib3ZlcnJpZGVfbWV0aG9kIjoiZ2V0Iiwic2V0dGluZ3MiOnsidW5zZWxlY3RDaGlsZHJlbiI6MSwiaGlkZUNoaWxkcmVuIjowfSwic2V0dGluZ3NpbWFnZXBvcyI6InJpZ2h0Iiwic2V0dGluZ3NWaXNpYmxlIjowLCJzZXR0aW5nc0hpZGVPblJlcyI6MCwicHJlc2NvbnRhaW5lcmhlaWdodCI6IjQwMHB4IiwiY2xvc2VPbkRvY0NsaWNrIjoxLCJmb2N1c09uUGFnZWxvYWQiOjAsImlzb3RvcGljIjp7Iml0ZW1XaWR0aCI6IjIwMHB4IiwiaXRlbVdpZHRoVGFibGV0IjoiMjAwcHgiLCJpdGVtV2lkdGhQaG9uZSI6IjIwMHB4IiwiaXRlbUhlaWdodCI6IjIwMHB4IiwiaXRlbUhlaWdodFRhYmxldCI6IjIwMHB4IiwiaXRlbUhlaWdodFBob25lIjoiMjAwcHgiLCJwYWdpbmF0aW9uIjoxLCJyb3dzIjoyLCJndXR0ZXIiOjUsInNob3dPdmVybGF5IjoxLCJibHVyT3ZlcmxheSI6MSwiaGlkZUNvbnRlbnQiOjF9LCJsb2FkZXJMb2NhdGlvbiI6ImF1dG8iLCJzaG93X21vcmUiOnsiZW5hYmxlZCI6MCwidXJsIjoiP3M9e3BocmFzZX0iLCJlbGVtZW50b3JfdXJsIjoiaHR0cHM6XC9cL2lmd2dsb2JhbC5jb21cLz9hc3BfbHM9e3BocmFzZX0iLCJhY3Rpb24iOiJhamF4IiwibG9jYXRpb24iOiJzYW1lIiwiaW5maW5pdGUiOjF9LCJtb2JpbGUiOnsidHJpZ2dlcl9vbl90eXBlIjoxLCJjbGlja19hY3Rpb24iOiJyZXN1bHRzX3BhZ2UiLCJyZXR1cm5fYWN0aW9uIjoicmVzdWx0c19wYWdlIiwiY2xpY2tfYWN0aW9uX2xvY2F0aW9uIjoic2FtZSIsInJldHVybl9hY3Rpb25fbG9jYXRpb24iOiJzYW1lIiwicmVkaXJlY3RfdXJsIjoiP3M9e3BocmFzZX0iLCJlbGVtZW50b3JfdXJsIjoiaHR0cHM6XC9cL2lmd2dsb2JhbC5jb21cLz9hc3BfbHM9e3BocmFzZX0iLCJtZW51X3NlbGVjdG9yIjoiI21lbnUtdG9nZ2xlIiwiaGlkZV9rZXlib2FyZCI6MCwiZm9yY2VfcmVzX2hvdmVyIjowLCJmb3JjZV9zZXR0X2hvdmVyIjowLCJmb3JjZV9zZXR0X3N0YXRlIjoibm9uZSJ9LCJjb21wYWN0Ijp7ImVuYWJsZWQiOjAsImZvY3VzIjoxLCJ3aWR0aCI6IjEwMCUiLCJ3aWR0aF90YWJsZXQiOiI0ODBweCIsIndpZHRoX3Bob25lIjoiMzIwcHgiLCJjbG9zZU9uTWFnbmlmaWVyIjoxLCJjbG9zZU9uRG9jdW1lbnQiOjAsInBvc2l0aW9uIjoic3RhdGljIiwib3ZlcmxheSI6MH0sInNiIjp7InJlZGlyZWN0X2FjdGlvbiI6ImFqYXhfc2VhcmNoIiwicmVkaXJlY3RfbG9jYXRpb24iOiJzYW1lIiwicmVkaXJlY3RfdXJsIjoiP3M9e3BocmFzZX0iLCJlbGVtZW50b3JfdXJsIjoiaHR0cHM6XC9cL2lmd2dsb2JhbC5jb21cLz9hc3BfbHM9e3BocmFzZX0ifSwicmIiOnsiYWN0aW9uIjoibm90aGluZyJ9LCJhbmltYXRpb25zIjp7InBjIjp7InNldHRpbmdzIjp7ImFuaW0iOiJmYWRlZHJvcCIsImR1ciI6MzAwfSwicmVzdWx0cyI6eyJhbmltIjoiZmFkZWRyb3AiLCJkdXIiOjMwMH0sIml0ZW1zIjoiZmFkZUluRG93biJ9LCJtb2IiOnsic2V0dGluZ3MiOnsiYW5pbSI6ImZhZGVkcm9wIiwiZHVyIjozMDB9LCJyZXN1bHRzIjp7ImFuaW0iOiJmYWRlZHJvcCIsImR1ciI6MzAwfSwiaXRlbXMiOiJ2b2lkYW5pbSJ9fSwic2VsZWN0MiI6eyJub3JlcyI6Ik5vIHJlc3VsdHMgbWF0Y2gifSwiZGV0ZWN0VmlzaWJpbGl0eSI6MCwiYXV0b3AiOnsic3RhdGUiOiJkaXNhYmxlZCIsInBocmFzZSI6IiIsImNvdW50IjoxMH0sIndvb1Nob3AiOnsidXNlQWpheCI6MCwic2VsZWN0b3IiOiIjbWFpbiIsInVybCI6IiJ9LCJ0YXhBcmNoaXZlIjp7InVzZUFqYXgiOjAsInNlbGVjdG9yIjoiI21haW4iLCJ1cmwiOiIifSwiY3B0QXJjaGl2ZSI6eyJ1c2VBamF4IjowLCJzZWxlY3RvciI6IiNtYWluIiwidXJsIjoiIn0sInJlc1BhZ2UiOnsidXNlQWpheCI6MCwic2VsZWN0b3IiOiIjbWFpbiIsInRyaWdnZXJfdHlwZSI6MSwidHJpZ2dlcl9mYWNldCI6MSwidHJpZ2dlcl9tYWduaWZpZXIiOjAsInRyaWdnZXJfcmV0dXJuIjowfSwiZnNzX2xheW91dCI6ImZsZXgiLCJzY3JvbGxCYXIiOnsiaG9yaXpvbnRhbCI6eyJlbmFibGVkIjoxfX0sImRpdmkiOnsiYm9keWNvbW1lcmNlIjowfSwicHJldmVudEJvZHlTY3JvbGwiOjAsInN0YXRpc3RpY3MiOjAsInByZXZlbnRFdmVudHMiOjB9"></div><div class='asp_hidden_data' style="display:none !important;"><div class='asp_item_overlay'><div class='asp_item_inner'><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 512 512"><path d="M448.225 394.243l-85.387-85.385c16.55-26.08 26.146-56.986 26.146-90.094 0-92.99-75.652-168.64-168.643-168.64-92.988 0-168.64 75.65-168.64 168.64s75.65 168.64 168.64 168.64c31.466 0 60.94-8.67 86.176-23.734l86.14 86.142c36.755 36.754 92.355-18.783 55.57-55.57zm-344.233-175.48c0-64.155 52.192-116.35 116.35-116.35s116.353 52.194 116.353 116.35S284.5 335.117 220.342 335.117s-116.35-52.196-116.35-116.352zm34.463-30.26c34.057-78.9 148.668-69.75 170.248 12.863-43.482-51.037-119.984-56.532-170.248-12.862z"/></svg></div></div></div></div><div id='__original__ajaxsearchprores1_1' class='asp_w asp_r asp_r_1 asp_r_1_1 vertical ajaxsearchpro wpdreams_asp_sc wpdreams_asp_sc-1' data-id="1" data-instance="1"><div class="results"><div class="resdrg"></div></div><div class="asp_res_loader hiddend"><div class="asp_loader"><div class="asp_loader-inner asp_simple-circle"></div></div></div></div><div id='__original__ajaxsearchprosettings1_1' class="asp_w asp_ss asp_ss_1 asp_s asp_s_1 asp_s_1_1 wpdreams_asp_sc wpdreams_asp_sc-1 ajaxsearchpro searchsettings" data-id="1" data-instance="1"><form name='options' class="asp-fss-flex" aria-label="Search settings form" autocomplete = 'off'><input type="hidden" name="current_page_id" value="119"><input type='hidden' name='qtranslate_lang' value='0'/><input type="hidden" name="filters_changed" value="0"><input type="hidden" name="filters_initial" value="1"><fieldset class="asp_filter_generic asp_filter_id_1 asp_filter_n_0"><legend>Generic filters</legend><div class="asp_option" tabindex="0"><div class="asp_option_inner"><input type="checkbox" value="exact" id="set_exact1_1" aria-label="Exact matches only" name="asp_gen[]" /><div class="asp_option_checkbox"></div></div><div class="asp_option_label"> Exact matches only </div></div><div class="asp_option" tabindex="0"><div class="asp_option_inner"><input type="checkbox" value="title" id="set_title1_1" data-origvalue="1" aria-label="Search in title" name="asp_gen[]" checked="checked"/><div class="asp_option_checkbox"></div></div><div class="asp_option_label"> Search in title </div></div><div class="asp_option" tabindex="0"><div class="asp_option_inner"><input type="checkbox" value="content" id="set_content1_1" data-origvalue="1" aria-label="Search in content" name="asp_gen[]" checked="checked"/><div class="asp_option_checkbox"></div></div><div class="asp_option_label"> Search in content </div></div><div class="asp_option" tabindex="0"><div class="asp_option_inner"><input type="checkbox" value="excerpt" id="set_excerpt1_1" data-origvalue="1" aria-label="Search in excerpt" name="asp_gen[]" checked="checked"/><div class="asp_option_checkbox"></div></div><div class="asp_option_label"> Search in excerpt </div></div></fieldset><div style="clear:both;"></div></form></div></div>				</div>
      </div>

	<!-- Login Button replaced Phone Numbers -->
<div class="phone-headers" style="display:flex; align-items:center;">
  <a href="/login.php" class="btn" style="background-color:transparent; color:#fff; border:2px solid #fecc56; margin-right:15px;">Client Login</a>
</div>
<!-- /end Login Button -->


      <a
        href="#main"
        class="site-header__book btn"
      >
        Submit an enquiry
      </a>

      <button
        class="site-header__toggle  js-site-offcanvas-trigger"
        aria-controls="main-nav"
        aria-expanded="false"
      >
        <span class="site-header__lines"></span>
        <span class="site-header__menu-label  js-site-offcanvas-label">Menu</span>
      </button>
		</div>
	</header>

	<main role="main" id="main" class="main" tabindex="-1">
	<article class="article">
		<style>
	.postid-2061 .fp-halves {
		width: 50%;
		display: flex;
		flex-flow: column;
		justify-content: center;
		height: 700px;
        padding: 20px;
	}
    
    .fp-form{
        display: none;
    }

	.fp-form h2, .fp-form p{
		color: #FFF;
	}

	.fp-form h2{
		font-size: 30px;
	}

	.fp-form p{
		font-size: 17px;
		margin-bottom: 30px;
	}

	#gform_fields_7 .gfield{
		margin-bottom: 5px !important;
	}

	#gform_fields_7 .ginput_container textarea{
		max-height: 100px;
	}

	#gform_fields_7 .gfield_label{
		color: #FFF !important;
	}

	@media(max-width: 575px){
		.postid-2061 .fp-halves{
			width: 100%;
			margin: 60px 0;
			height: auto;
		}
	}
</style>
<section class="intro intro--with-numbers article__intro">
      <div class="intro__numbers u-numbers js-numbers">
      23370079773836394574424186399792537186696190098851392856304130372452827447632527957403608636777979913965911636665099435320005922040864048064891810536496335423859895808556046536032727401153185442253340607128804011458677300489050921127931299923049133898908649885607588055486194507565942192837215084057773549468181564265255565872129627556248777563227146821246998319312997638591190028912647120363083477946857595115145830795125526947369570174611817537706093698180491300713431737245725941521238488114001010350007748161698939947289079753049656363275155995255134881102995878951066209392335801708787517290084895010619652659390873756613409466184196140248372155526784876527673592218943817656812151776776793326505358448359201889140627087767154012394276563104554894107070762762081170381218230837902993858635630030559275400799428722252721584493611414199223722359819880641947278209838074793826324947235045512828758398480909170239126259267396276972245154148342440172043354639981148628924735884934656317051361740646996516498551130755482439898403998863059454441350700241175434675417662636219612315973663710979025334160546210113313519185306312450866747828281286266472753614550669277257151752561867329788219244449396442301646118012301477792803728155364058306831527435127727615334396275244668645018660390021435474151955111974298495482723684436841499854013515904631064639132001259441776946612399455348451095725068420992383234156957341963196817849482101390932396948974946961914311858751671358600414359681249670881251790117744686151719079888452394522642653039466454073445943391561121197627338587691010674465393392009087154835342768087881157601746006192124687316809030104172629257682622185665876524509218587348663644413098841607814825084372905488057637904216494621402804124790973115310445884839035798735635633951025545004295083427970382474056003862432401012353590308259985524720292353070711166066046329519756106696818280227683395239837425984254656046482906895880748921620560412430450230339175063084738884415739997967841942928    </div>
  
	<div class="l-container">
		
		<div class="intro__content fp-halves">
			<h1 class="intro__title">
				Submit an Enquiry			</h1>

							<div class="intro__copy">
					<p>Submit an enquiry with IFW Global&#8217;s Fraud Recovery Team, Australia&#8217;s leading cyber-crime investigation team.</p>
				</div>
					</div>

		<div class="fp-form fp-halves">
			<h2>Get your money back from a scam</h2>
			<p>Leave your details below to start the recovery process within 24 hours or less!</p>
			<p></p>
			
                <div class="custom-dynamic-form" style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
            <form class="dynamicContactForm" method="POST">
                <input type="hidden" name="form_source" value="consultation">
                <?php echo render_dynamic_form($pdo); ?>
                <button type="submit" class="btn btn-warning mt-3 w-100" style="background-color:#fecc56; color:#000; font-weight:bold; border:none; padding:12px;">Submit Details</button>
            </form>
            <div class="formResponse mt-3"></div>
        </div>
        <script>
        (function() {
            var currentScript = document.currentScript;
            document.addEventListener('DOMContentLoaded', function() {
                var container = currentScript.previousElementSibling;
                if (container && container.classList.contains('custom-dynamic-form')) {
                    var form = container.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            var formData = new FormData(form);
                            
                            // Determine path to process_form.php based on depth
                            var pathPrefix = window.location.pathname.split('/').length > 2 ? '../'.repeat(window.location.pathname.split('/').filter(Boolean).length - 1) : '';
                            if (pathPrefix === '') pathPrefix = './';
                            
                            var submitBtn = form.querySelector('button[type="submit"]');
                            if(submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = 'Sending...';
                            }
                            
                            fetch('/process_form.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                var respDiv = container.querySelector('.formResponse');
                                if(data.status === 'success') {
                                    respDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                                    form.reset();
                                } else {
                                    var errs = data.errors ? data.errors.join('<br>') : data.message;
                                    respDiv.innerHTML = '<div class="alert alert-danger">' + errs + '</div>';
                                }
                            })
                            .catch(e => {
                                var respDiv = container.querySelector('.formResponse');
                                respDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                            })
                            .finally(() => {
                                if(submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = 'Submit Details';
                                }
                            });
                        });
                    }
                }
            });
        })();
        </script>
		</div>

	</div>
</section>
		<div class="l-container article__container">
			<div class="l-grid">
				<section class="article__content l-grid__cell l-grid__cell--66-at-lg">
					

					<div
	class="l-section l-section--medium "
	id="page-section-1"
>
	<div class="copy">
		<h2>Understand the process and make an informed decision about engaging IFW Global services.</h2>
<h3></h3>
<h3>What&#8217;s involved with submitting an enquiry</h3>
<p>IFW Global will review and assess your case to propose the best possible actions and make recommendations on how to recover your losses based on decades of experience.</p>
	</div>
</div>
<div
	class="l-section l-section--medium "
	id="page-section-2"
>
	<div class="page-alert page-alert--warning">
		<div class="page-alert__copy copy">
			<p class="lede"><strong>Please note: </strong><strong><span data-teams="true">For fraud-related investigations, IFW Global does not accept cases involving losses below USD 70,000.</span></strong></p>
		</div>
	</div>
</div>

<div
	class="l-section l-section--none l-section--bordered"
	id="page-section-3"
>
	<div class="form" >
    
          <h3 class="form__subtitle">
        Complete our enquiry form and get started with your investigation.      </h3>
    
    <div class="form__container">
      
                <div class="custom-dynamic-form" style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
            <form class="dynamicContactForm" method="POST">
                <input type="hidden" name="form_source" value="consultation_bottom">
                <?php echo render_dynamic_form($pdo); ?>
                <button type="submit" class="btn btn-warning mt-3 w-100" style="background-color:#fecc56; color:#000; font-weight:bold; border:none; padding:12px;">Submit Details</button>
            </form>
            <div class="formResponse mt-3"></div>
        </div>
        <script>
        (function() {
            var currentScript = document.currentScript;
            document.addEventListener('DOMContentLoaded', function() {
                var container = currentScript.previousElementSibling;
                if (container && container.classList.contains('custom-dynamic-form')) {
                    var form = container.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            var formData = new FormData(form);
                            
                            // Determine path to process_form.php based on depth
                            var pathPrefix = window.location.pathname.split('/').length > 2 ? '../'.repeat(window.location.pathname.split('/').filter(Boolean).length - 1) : '';
                            if (pathPrefix === '') pathPrefix = './';
                            
                            var submitBtn = form.querySelector('button[type="submit"]');
                            if(submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = 'Sending...';
                            }
                            
                            fetch('/process_form.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                var respDiv = container.querySelector('.formResponse');
                                if(data.status === 'success') {
                                    respDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                                    form.reset();
                                } else {
                                    var errs = data.errors ? data.errors.join('<br>') : data.message;
                                    respDiv.innerHTML = '<div class="alert alert-danger">' + errs + '</div>';
                                }
                            })
                            .catch(e => {
                                var respDiv = container.querySelector('.formResponse');
                                respDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                            })
                            .finally(() => {
                                if(submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = 'Submit Details';
                                }
                            });
                        });
                    }
                }
            });
        })();
        </script>
    </div>
	</div>
</div>
<div
	class="l-section l-section--medium "
	id="page-section-4"
>
	<div class="page-alert page-alert--warning">
		<div class="page-alert__copy copy">
			<h3>You can also contact us directly on any of our international phone numbers:</h3>
<p><strong>AUS: <a class="phone-number" href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', get_setting($pdo, 'phone_australia', '1300439456'))) ?>"><?= htmlspecialchars(get_setting($pdo, 'phone_australia', '1300 439 456')) ?></a></strong> (within Australia)</p>
<p><strong>AUS: <a class="phone-number" href="tel:+63 2 8789 9127">+61 2 8328 0402</a></strong> (outside Australia)</p>
<p><strong>USA: <a href="tel:<?= htmlspecialchars(get_setting(\, 'phone_usa', '+1 (239) 247 5287')) ?>"><?= htmlspecialchars(get_setting(\, 'phone_usa', '+1 (239) 247 5287')) ?></a></strong></p>
		</div>
	</div>
</div>

<div
	class="l-section l-section--medium "
	id="page-section-5"
>
	<div class="quote quote--with-background">
		<blockquote class="quote__content">
			<p>I engaged IFW to pursue my investment of AUD $1.8 million in a boiler room share market scam.

IFW tracked down the fraudsters involved in my investment scam and had them arrested in the Philippines.

We subsequently were able to freeze all their assets and negotiated a financial settlement which resulted in the return of all of my invested funds and recovery expenses.

This was a life changing experience for me to recover my investment and restore my financial position.

Thanks to IFW for their relentless pursuit of the criminals involved. </p>
		</blockquote>

			</div>
</div>
				</section>

				<aside class="article__sidebar l-grid__cell l-grid__cell--33-at-lg">
					  <nav class="sidebar__menu js-sidebar-menu">
    <ul id="menu-main-menu-1" class="link-list"><li class="link-list__item link-list__item--185 has-children"><a href="/about-us/" class="link-list__link"><span class='link-list__label'>About Us</span></a><div class='link-list-submenu link-list-submenu--1 '><ul class='link-list-submenu__list'><li class="link-list-submenu__item link-list-submenu__item--1556 is-child"><a href="/about-us/" class="link-list-submenu__link"><span class='link-list-submenu__label'>About Us</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--1555 is-child"><a href="/contact/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Contact Us</span></a></li>
</ul></div></li>
<li class="link-list__item link-list__item--1940 has-children"><a href="#" class="link-list__link"><span class='link-list__label'>Services</span></a><div class='link-list-submenu link-list-submenu--1 '><ul class='link-list-submenu__list'><li class="link-list-submenu__item link-list-submenu__item--195 is-child"><a href="/scam/serious-and-organised-fraud/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Investment Scam Investigations</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--203 is-child"><a href="/investigation/due-diligence/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Background Investigations</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--205 is-child"><a href="/investigation/covert-surveillance-investigators/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Covert Surveillance</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--1725 is-child"><a href="/asset-recovery/asset-recovery-scams/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Asset Recovery</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--220 is-child"><a href="/asset-recovery/cryptocurrency-tracing/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Cryptocurrency Tracing</span></a></li>
</ul></div></li>
<li class="link-list__item link-list__item--222 has-children"><a href="/media/" class="link-list__link"><span class='link-list__label'>Media</span></a><div class='link-list-submenu link-list-submenu--1 '><ul class='link-list-submenu__list'><li class="link-list-submenu__item link-list-submenu__item--2051 is-child"><a href="/media/" class="link-list-submenu__link"><span class='link-list-submenu__label'>GFCS 2024</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--1554 is-child"><a href="/scam-alert/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Scam Alert</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--224 is-child"><a href="/in-the-news/" class="link-list-submenu__link"><span class='link-list-submenu__label'>In the news</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--225 is-child"><a href="/podcasts/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Podcasts</span></a></li>
<li class="link-list-submenu__item link-list-submenu__item--223 is-child"><a href="/blog/" class="link-list-submenu__link"><span class='link-list-submenu__label'>Blog</span></a></li>
</ul></div></li>
<li class="link-list__item link-list__item--1924 has-children"><a href="#" class="link-list__link"><span class='link-list__label'>Events</span></a><div class='link-list-submenu link-list-submenu--1 '><ul class='link-list-submenu__list'><li class="link-list-submenu__item link-list-submenu__item--1925 is-child"><a href="" class="link-list-submenu__link"><span class='link-list-submenu__label'>Global Financial Crimes Summit 2024</span></a></li>
</ul></div></li>
</ul>  </nav>


<a
  href="/services/"
  class="sidebar__all"
>
  View all services
</a>

				</aside>
			</div>
		</div>
	</article>

	
	<div class="cards cards--interested">
		<div class="l-container">
			<div class="cards__top top">
				<h2 class="top__title">
											IFW Global has an extensive array of integrated services with one objective									</h2>

        
        <a href="/services/" class="top__link">
					View all

					<svg class="top__chevron  icon icon--chevron-right" role="img" focusable="false">
						<use xlink:href="#svg-chevron-circle--right"/>
					</svg>
				</a>
			</div>

			<div class="cards__list l-grid l-grid--with-margin">
				<div class="l-grid__cell l-grid__cell--50-at-smd l-grid__cell--25-at-lg">
	<article class="card card--has-image">
		<a
			href="/intelligence/"
			class="card__link"
		>
      <div class="card__top">
        <img width="580" height="400" src="/wp-content/uploads/2022/03/city-skyscape-580x400.jpg" class="card__image" alt="" decoding="async" fetchpriority="high" />      </div>

      <div class="card__content">
        <h2 class="card__title">
          Intelligence        </h2>

        
        <ul class="card__tags tags">
          <li class="tags__item">
            <span
              class="tags__span"
            >
              Information            </span>
          </li>
        </ul>
      </div>
		</a>
	</article>
</div><div class="l-grid__cell l-grid__cell--50-at-smd l-grid__cell--25-at-lg">
	<article class="card card--has-image">
		<a
			href="/investigation/"
			class="card__link"
		>
      <div class="card__top">
        <img width="580" height="364" src="/wp-content/uploads/2022/03/map-man-580x364.jpg" class="card__image" alt="" decoding="async" />      </div>

      <div class="card__content">
        <h2 class="card__title">
          Investigation        </h2>

        
        <ul class="card__tags tags">
          <li class="tags__item">
            <span
              class="tags__span"
            >
              Information            </span>
          </li>
        </ul>
      </div>
		</a>
	</article>
</div><div class="l-grid__cell l-grid__cell--50-at-smd l-grid__cell--25-at-lg">
	<article class="card card--has-image">
		<a
			href="/asset-recovery/"
			class="card__link"
		>
      <div class="card__top">
        <img width="580" height="400" src="/wp-content/uploads/2022/03/man-hiding-face-580x400.jpg" class="card__image" alt="" decoding="async" />      </div>

      <div class="card__content">
        <h2 class="card__title">
          Asset Recovery        </h2>

        
        <ul class="card__tags tags">
          <li class="tags__item">
            <span
              class="tags__span"
            >
              Information            </span>
          </li>
        </ul>
      </div>
		</a>
	</article>
</div>			</div>
		</div>
	</div>

	</main>

  

	
<div class="recognition ">
	<div class="l-container">
		<div class="recognition__box recognition__box--content">
			<h4 class="recognition__title">
				Before choosing any other recovery service, ask for a video call and confirm their licencing authority, company registration, and credentials with law enforcement.			</h4>

			<p class="recognition__intro">
				IFW Global expertise and knowledge are trusted and recognised by leading state, federal and international law enforcement agencies. <br />
<b>NSW Police Force Master Investigation Licence: 410843633 - Florida License Number: A1900003 <b />			</p>
		</div>

		<div class="l-grid">
							<div class="l-grid__cell l-grid__cell--50-at-smd l-grid__cell--50-at-ds">
					
					<div class="recognition__box">
							<table>
								<tbody>
									<tr>
										<td>
										<img width="200" height="200" src="/wp-content/uploads/2025/04/pnp-acg-logo-1.png" class="recognition__logo" alt="" decoding="async" loading="lazy" srcset="/wp-content/uploads/2025/04/pnp-acg-logo-1.png 200w, /wp-content/uploads/2025/04/pnp-acg-logo-1-150x150.png 150w, /wp-content/uploads/2025/04/pnp-acg-logo-1-110x110.png 110w" sizes="auto, (max-width: 200px) 100vw, 200px" />										</td>
										<td><h5 class="recognition__box-title">
														Philippine National Police						
											</h5>

											<p class="recognition__copy">
															Awarded multiple Plaques of Appreciation by the Philippine National Police, Anti-Cybercrime Group						
											</p>
										</td>
									</tr>
							</tbody>
						</table>
						

						
					</div>
				</div>
							<div class="l-grid__cell l-grid__cell--50-at-smd l-grid__cell--50-at-ds">
					
					<div class="recognition__box">
							<table>
								<tbody>
									<tr>
										<td>
										<img width="200" height="200" src="/wp-content/uploads/2025/04/Cali-Assoc-Licensed-Inv-200x200.png" class="recognition__logo" alt="" decoding="async" loading="lazy" srcset="/wp-content/uploads/2025/04/Cali-Assoc-Licensed-Inv-200x200.png 200w, /wp-content/uploads/2025/04/Cali-Assoc-Licensed-Inv-150x150.png 150w, /wp-content/uploads/2025/04/Cali-Assoc-Licensed-Inv-110x110.png 110w, /wp-content/uploads/2025/04/Cali-Assoc-Licensed-Inv.png 225w" sizes="auto, (max-width: 200px) 100vw, 200px" />										</td>
										<td><h5 class="recognition__box-title">
														Californian Association of Licensed Investigators						
											</h5>

											<p class="recognition__copy">
															Awarded Certificate of Appreciation from Californian Association of Licensed Investigators						
											</p>
										</td>
									</tr>
							</tbody>
						</table>
						

						
					</div>
				</div>
							<div class="l-grid__cell l-grid__cell--50-at-smd l-grid__cell--50-at-ds">
					
					<div class="recognition__box">
							<table>
								<tbody>
									<tr>
										<td>
										<img width="200" height="200" src="/wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-200x200.png" class="recognition__logo" alt="" decoding="async" loading="lazy" srcset="/wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-200x200.png 200w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-300x300.png 300w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-1024x1024.png 1024w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-150x150.png 150w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-768x768.png 768w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-318x318.png 318w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg-110x110.png 110w, /wp-content/uploads/2022/08/Seal_of_the_Philippine_Securities_and_Exchange_Commission_svg.png 1200w" sizes="auto, (max-width: 200px) 100vw, 200px" />										</td>
										<td><h5 class="recognition__box-title">
														Philippine Securities and Exchange Commission						
											</h5>

											<p class="recognition__copy">
															Certificate of Appreciation as a subject matter expert.						
											</p>
										</td>
									</tr>
							</tbody>
						</table>
						

						
					</div>
				</div>
					</div>
	</div>
</div>

	<div class="site-footer" role="contentinfo">
		<div class="l-container" aria-label="footer">
			<div class="l-grid l-grid--between">
				<div class="site-footer__left l-grid__cell l-grid__cell--41-at-lg">
					<div action="" class="site-footer__form">
						<p class=site-footer__intro>Sign up to receive alerts and e-news.</p>

						<div id="subscribe-for-updates" class="site-footer__newsletter newsletter">
							
                <div class="custom-dynamic-form" style="background:#fff; padding:30px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
            <form class="dynamicContactForm" method="POST">
                <input type="hidden" name="form_source" value="consultation_footer">
                <?php echo render_dynamic_form($pdo); ?>
                <button type="submit" class="btn btn-warning mt-3 w-100" style="background-color:#fecc56; color:#000; font-weight:bold; border:none; padding:12px;">Submit Details</button>
            </form>
            <div class="formResponse mt-3"></div>
        </div>
        <script>
        (function() {
            var currentScript = document.currentScript;
            document.addEventListener('DOMContentLoaded', function() {
                var container = currentScript.previousElementSibling;
                if (container && container.classList.contains('custom-dynamic-form')) {
                    var form = container.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            var formData = new FormData(form);
                            
                            // Determine path to process_form.php based on depth
                            var pathPrefix = window.location.pathname.split('/').length > 2 ? '../'.repeat(window.location.pathname.split('/').filter(Boolean).length - 1) : '';
                            if (pathPrefix === '') pathPrefix = './';
                            
                            var submitBtn = form.querySelector('button[type="submit"]');
                            if(submitBtn) {
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = 'Sending...';
                            }
                            
                            fetch('/process_form.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                var respDiv = container.querySelector('.formResponse');
                                if(data.status === 'success') {
                                    respDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                                    form.reset();
                                } else {
                                    var errs = data.errors ? data.errors.join('<br>') : data.message;
                                    respDiv.innerHTML = '<div class="alert alert-danger">' + errs + '</div>';
                                }
                            })
                            .catch(e => {
                                var respDiv = container.querySelector('.formResponse');
                                respDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
                            })
                            .finally(() => {
                                if(submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = 'Submit Details';
                                }
                            });
                        });
                    }
                }
            });
        })();
        </script>
					</div>

          <div class="site-footer__logos">
					  <img src="/wp-content/themes/rb-council/images/logo.svg" alt="IFW Global" class="site-footer__logo" />

            <img src="/wp-content/themes/rb-council/images/board-of-directors.png" alt="Board of Directors, International Association of Cybercrime Prevention" class="site-footer__logo site-footer__logo--bod" />
          </div>

                      <div class="site-footer__legals site-footer__legals--desktop">
              								
                <small class="site-footer__legal ">
                   New South Wales (Australia) Police Force Master Investigation Licence: 410843633                 </small>
              								
                <small class="site-footer__legal ">
                  US (Florida) License Number: A1900003                 </small>
              								
                <small class="site-footer__legal site-footer__legal--margin-top">
                  IFW GLOBAL PTY LTD                </small>
              								
                <small class="site-footer__legal ">
                  ABN: 83 165 710 743                </small>
              								
                <small class="site-footer__legal ">
                  ACN: 165 710 743                </small>
                          </div>
          				</div>

				<div class="site-footer__right l-grid__cell l-grid__cell--50-at-lg">
					<nav class="site-footer__nav footer-menu">
						<ul id="menu-footer-menu" class="footer-menu__list"><li id="menu-item-464" class="footer-menu__item footer-menu__item--464"><a href="/about-us/" class="footer-menu__link"><span class='footer-menu__label'>About Us</span></a></li>
<li id="menu-item-361" class="footer-menu__item footer-menu__item--361"><a href="/scams/" class="footer-menu__link"><span class='footer-menu__label'>Scams</span></a></li>
<li id="menu-item-358" class="footer-menu__item footer-menu__item--358"><a href="/intelligence/" class="footer-menu__link"><span class='footer-menu__label'>Intelligence</span></a></li>
<li id="menu-item-359" class="footer-menu__item footer-menu__item--359"><a href="/investigation/" class="footer-menu__link"><span class='footer-menu__label'>Investigation</span></a></li>
<li id="menu-item-360" class="footer-menu__item footer-menu__item--360"><a href="/asset-recovery/" class="footer-menu__link"><span class='footer-menu__label'>Asset Recovery</span></a></li>
<li id="menu-item-465" class="footer-menu__item footer-menu__item--465 is-current page_item page-item-119 current_page_item"><a href="/contact/" class="footer-menu__link"><span class='footer-menu__label'>Submit an Enquiry</span></a></li>
</ul>					</nav>

					            <div class="site-footer__legals site-footer__legals--mobile">
              								
                <small class="site-footer__legal ">
                   New South Wales (Australia) Police Force Master Investigation Licence: 410843633                 </small>
              								
                <small class="site-footer__legal ">
                  US (Florida) License Number: A1900003                 </small>
              								
                <small class="site-footer__legal site-footer__legal--margin-top">
                  IFW GLOBAL PTY LTD                </small>
              								
                <small class="site-footer__legal ">
                  ABN: 83 165 710 743                </small>
              								
                <small class="site-footer__legal ">
                  ACN: 165 710 743                </small>
                          </div>
          
					<div class="site-footer__admin">
						<h5 class="site-footer__subtitle">Get in touch</h5>

                          <a
                href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', get_setting($pdo, 'phone_australia', '1300439456'))) ?>"
                class="site-footer__phone"
              >
                <strong class="site-footer__phone-name">
                  Australia (Global HQ)                </strong>

                                  1300 IFW GLO (
                
                <?= htmlspecialchars(get_setting($pdo, 'phone_australia', '1300 439 456')) ?>
                                  )
                              </a>
                          <a
                href="tel:+6183280402"
                class="site-footer__phone"
              >
                <strong class="site-footer__phone-name">
                  Australia                </strong>

                
                <?= htmlspecialchars(get_setting(\, 'phone_australia_secondary', '+61 (02) 8328 0402')) ?>
                              </a>
                          <a
                href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', get_setting(\, 'phone_usa', '+1 (239) 247 5287'))) ?>"
                class="site-footer__phone"
              >
                <strong class="site-footer__phone-name">
                  USA                </strong>

                
                <?= htmlspecialchars(get_setting(\, 'phone_usa', '+1 (239) 247 5287')) ?>
                              </a>
            					</div>

					<div class="site-footer__contact">
													<ul class="site-footer__social social">
																	<li class="social__item">
										<a
											href="#"
											class="social__link"
										>
											<span class="u-visually-hidden">Find us on Facebook</span>

											<svg class="social__icon icon icon--facebook" role="img" focusable="false">
												<use xlink:href="#svg-facebook"/>
											</svg>
										</a>
									</li>
								
																	<li class="social__item">
										<a
											href="#"
											class="social__link"
										>
											<span class="u-visually-hidden">Find us on YouTube</span>

											<svg class="social__icon icon icon--youtube" role="img" focusable="false">
												<use xlink:href="#svg-youtube"/>
											</svg>
										</a>
									</li>
								
																	<li class="social__item">
										<a
											href="https://www.linkedin.com/company/ifw-global/"
											class="social__link"
										>
											<span class="u-visually-hidden">Find us on LinkedIn</span>

											<svg class="social__icon icon icon--linkedin" role="img" focusable="false">
												<use xlink:href="#svg-linkedin"/>
											</svg>
										</a>
									</li>
								
                									<li class="social__item">
										<a
											href="#"
											class="social__link"
										>
											<span class="u-visually-hidden">Follow us on Twitter</span>

											<svg class="social__icon icon icon--twitter" role="img" focusable="false">
												<use xlink:href="#svg-twitter"/>
											</svg>
										</a>
									</li>
															</ul>
											</div>
				</div>
			</div>
		</div>

		<div class="site-footer__bottom">
			<div class="l-container">
				<div class="site-footer__smalls">
					<small class="site-footer__copy">&copy; 2026 IFW Global</small>

					<nav class="site-footer__meta">
						<ul id="menu-legal-menu" class="site-footer__meta__list"><li id="menu-item-1672" class="site-footer__meta__item site-footer__meta__item--1672"><a href="/policies/" class="site-footer__meta__link"><span class='site-footer__meta__label'>Disclaimer</span></a></li>
<li id="menu-item-2045" class="site-footer__meta__item site-footer__meta__item--2045"><a href="/privacy-policy/" class="site-footer__meta__link"><span class='site-footer__meta__label'>Privacy Policy</span></a></li>
</ul>					</nav>
				</div>

				<a
					href="#"
					class="site-footer__top"
					aria-hidden="true"
				>
					Back to top

					<svg class="site-footer__top-icon  icon icon--chevron-circle-up" role="img" focusable="false">
						<use xlink:href="#svg-chevron-circle--up"/>
					</svg>
				</a>

				<p class="site-footer__grecaptcha">This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" class="site-footer__grecaptcha-link">Privacy Policy</a> and <a href="https://policies.google.com/terms" class="site-footer__grecaptcha-link">Terms of Service</a> apply.</p>
			</div>
		</div>
	</div>

	
	
<div class="site-offcanvas  js-site-offcanvas" aria-hidden="true">
  <div class="site-offcanvas__numbers u-numbers js-numbers" data-mobile-only>
    52253767376165375538909749451616501818199291066590228349868639873574501905199819807771926876781533746436575914030175197142652988105424947529275126523410771600198070235527378769099751957147875440711969249992652374598427817861516605789784797172945438710395664495506692657750710686101861969410951625319258291206491322789697780469431021083251046952811691399083106199141565535317786277354854292905814603479430346990849192066814512040645977218500170392708724052242201064437492832915305629798145346825892229776877054360741787992160336693785933466338406784309125031216503533428691569843249664037484637600629695981926749312777454853244265103997509079570538820731673777837534382057202164447354627692369513210735635801506496487795977453698772263991524187191831725198368394386855373129164921054961878541010640853296807975269117545001797875417654825739156086108093409587409318425676690914999028025401462422724652493149424637026691180286505273061007127296490646347766781804623109300838786575127796488998605268694951000790170462745229771150622301296655144219388904620364101671276943297756429797912004259103619369187699752062199794597653902918524516280424736622495377441079999660013537637910716989385966087070802957041658106987617481955366972247381085977231175114804272283997210197019411569757501799281641556700005833528726029986368980786355850570331945588962390185833915819750558321034901651313130726643846839724242054520679601893365429745977304304080843168162343877824455098444068161430238742763152786507542129736510101497728812078439162853636384675711131008372427386618965467920136866700774676129096892006182904266609395708748489075092645239000511402473087603008177895238719295591783316680070477496138500207478451345582634452600720568605223069753022231360487205892508918345051795378657834807330669130488012527021238352554977417694305320281990184217589952807737020281428614267729802174803011118914361569480876108627744579904012287582035762269474003830480276213973781156865247011291088776438323173106778638680311128  </div>

	<div class="site-offcanvas__header">
		<button class="site-offcanvas__control is-active site-offcanvas__control--back  js-site-offcanvas-back">
			<svg class="site-offcanvas__back  icon-chevron--right" role="img" focusable="false">
				<use xlink:href="#svg-chevron--right"/>
			</svg>

			Back
		</button>
	</div>

	<div class="site-offcanvas__content">
		<nav class="offcanvas-menu offcanvas-menu--primary">
      <ul id="menu-main-menu-2" class="offcanvas-menu__list"><li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--185 has-children is-child"><a href="/about-us/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>About Us</span></a><button class="offcanvas-menu-submenu__accessory  js-offcanvas-menu-trigger"><svg class="offcanvas-menu__icon" role="img" focusable="false"><use xlink:href="#svg-chevron--right"/></svg></button><div class='offcanvas-menu-submenu offcanvas-menu-submenu--1 js-offcanvas-menu-submenu'><ul class='offcanvas-menu-submenu__list'><li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1556 is-child"><a href="/about-us/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>About Us</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1555 is-child"><a href="/contact/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Contact Us</span></a></li>
</ul></div></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1940 has-children is-child"><a href="#" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Services</span></a><button class="offcanvas-menu-submenu__accessory  js-offcanvas-menu-trigger"><svg class="offcanvas-menu__icon" role="img" focusable="false"><use xlink:href="#svg-chevron--right"/></svg></button><div class='offcanvas-menu-submenu offcanvas-menu-submenu--1 js-offcanvas-menu-submenu'><ul class='offcanvas-menu-submenu__list'><li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--195 is-child"><a href="/scam/serious-and-organised-fraud/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Investment Scam Investigations</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--203 is-child"><a href="/investigation/due-diligence/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Background Investigations</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--205 is-child"><a href="/investigation/covert-surveillance-investigators/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Covert Surveillance</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1725 is-child"><a href="/asset-recovery/asset-recovery-scams/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Asset Recovery</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--220 is-child"><a href="/asset-recovery/cryptocurrency-tracing/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Cryptocurrency Tracing</span></a></li>
</ul></div></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--222 has-children is-child"><a href="/media/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Media</span></a><button class="offcanvas-menu-submenu__accessory  js-offcanvas-menu-trigger"><svg class="offcanvas-menu__icon" role="img" focusable="false"><use xlink:href="#svg-chevron--right"/></svg></button><div class='offcanvas-menu-submenu offcanvas-menu-submenu--1 js-offcanvas-menu-submenu'><ul class='offcanvas-menu-submenu__list'><li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--2051 is-child"><a href="/media/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>GFCS 2024</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1554 is-child"><a href="/scam-alert/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Scam Alert</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--224 is-child"><a href="/in-the-news/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>In the news</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--225 is-child"><a href="/podcasts/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Podcasts</span></a></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--223 is-child"><a href="/blog/" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Blog</span></a></li>
</ul></div></li>
<li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1924 has-children is-child"><a href="#" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Events</span></a><button class="offcanvas-menu-submenu__accessory  js-offcanvas-menu-trigger"><svg class="offcanvas-menu__icon" role="img" focusable="false"><use xlink:href="#svg-chevron--right"/></svg></button><div class='offcanvas-menu-submenu offcanvas-menu-submenu--1 js-offcanvas-menu-submenu'><ul class='offcanvas-menu-submenu__list'><li class="offcanvas-menu-submenu__item offcanvas-menu-submenu__item--1925 is-child"><a href="" class="offcanvas-menu-submenu__link"><span class='offcanvas-menu-submenu__label'>Global Financial Crimes Summit 2024</span></a></li>
</ul></div></li>
</ul>		</nav>

		<ul class="site-offcanvas__phones phones phones--white">
			<li class="phones__item">
				<svg class="phones__phone  icon icon--phone" role="img" focusable="false">
					<use xlink:href="#svg-phone"/>
				</svg>
			</li>

							<li class="phones__item">
					<a
						href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', get_setting($pdo, 'phone_australia', '1300439456'))) ?>"
						class="phones__link"
						data-title="<?= htmlspecialchars(get_setting($pdo, 'phone_australia', '1300 439 456')) ?>"
					>
						HQ					</a>
				</li>
							<li class="phones__item">
					<a
						href="tel:+6183280402"
						class="phones__link"
						data-title="<?= htmlspecialchars(get_setting(\, 'phone_australia_secondary', '+61 (02) 8328 0402')) ?>"
					>
						AUS					</a>
				</li>
							<li class="phones__item">
					<a
						href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', get_setting(\, 'phone_usa', '+1 (239) 247 5287'))) ?>"
						class="phones__link"
						data-title="<?= htmlspecialchars(get_setting(\, 'phone_usa', '+1 (239) 247 5287')) ?>"
					>
						USA					</a>
				</li>
					</ul>
	</div>

  <a
    href="/contact/"
    class="offcanvas__btn btn"
  >
    Submit an enquiry
  </a>
</div>

	









			<!-- HFCM by 99 Robots - Snippet # 2: FP SCRIPT -->

<!-- /end HFCM by 99 Robots -->
	<div id="wpcp-error-message" class="msgmsg-box-wpcp hideme"><span>error: </span>Content is protected.</div>
	
		<style>
	@media print {
	body * {display: none !important;}
		body:after {
		content: "Print preview is disabled."; }
	}
	</style>
		<style type="text/css">
	#wpcp-error-message {
	    direction: ltr;
	    text-align: center;
	    transition: opacity 900ms ease 0s;
	    z-index: 99999999;
	}
	.hideme {
    	opacity:0;
    	visibility: hidden;
	}
	.showme {
    	opacity:1;
    	visibility: visible;
	}
	.msgmsg-box-wpcp {
		border:1px solid #f5aca6;
		border-radius: 10px;
		color: #555;
		font-family: Tahoma;
		font-size: 11px;
		margin: 10px;
		padding: 10px 36px;
		position: fixed;
		width: 255px;
		top: 50%;
  		left: 50%;
  		margin-top: -10px;
  		margin-left: -130px;
  		-webkit-box-shadow: 0px 0px 34px 2px rgba(242,191,191,1);
		-moz-box-shadow: 0px 0px 34px 2px rgba(242,191,191,1);
		box-shadow: 0px 0px 34px 2px rgba(242,191,191,1);
	}
	.msgmsg-box-wpcp span {
		font-weight:bold;
		text-transform:uppercase;
	}
		.warning-wpcp {
		background:#ffecec url('/wp-content/plugins/wp-content-copy-protector/images/warning.png') no-repeat 10px 50%;
	}
    </style>

<!-- GTM Container placement set to footer -->
<!-- Google Tag Manager (noscript) -->
				<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TDS9K5S" height="0" width="0" style="display:none;visibility:hidden" aria-hidden="true"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->		<div class='asp_hidden_data' id="asp_hidden_data" style="display: none !important;">
			<svg style="position:absolute" height="0" width="0">
				<filter id="aspblur">
					<feGaussianBlur in="SourceGraphic" stdDeviation="4"/>
				</filter>
			</svg>
			<svg style="position:absolute" height="0" width="0">
				<filter id="no_aspblur"></filter>
			</svg>
		</div>
							<!-- Start of Async HubSpot Analytics Code -->
					
					<!-- End of Async HubSpot Analytics Code -->
				


































		
		
		
		</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        if(form.id && form.id.startsWith('gform_')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = form.querySelector('input[type="submit"], button[type="submit"]');
                if(btn) { btn.disabled = true; btn.value = 'Submitting...'; }
                
                var formData = new FormData(form);
                // Map gravity forms inputs to our standardized names for process_form.php
                var data = new URLSearchParams();
                for (var pair of formData.entries()) {
                    var name = pair[0];
                    var val = pair[1];
                    // Map common Gravity form fields based on likely order or IDs
                    if (name.includes('input_1') || name.includes('input_name')) { data.append('name', val); }
                    else if (name.includes('input_2') || name.includes('input_email')) { data.append('email', val); }
                    else if (name.includes('input_3') || name.includes('input_phone')) { data.append('phone', val); }
                    else if (name.includes('input_4') || name.includes('input_service')) { data.append('service_type', val); }
                    else if (name.includes('input_5') || name.includes('input_message')) { data.append('message', val); }
                    else { data.append(name, val); }
                }
                
                fetch('../process_form.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: data.toString()
                })
                .then(response => response.json())
                .then(data => {
                    var container = form.parentElement;
                    container.innerHTML = '<div style=\"padding:20px; background:#1e1e1e; color:#d4af37; border: 1px solid #d4af37; border-radius:5px; text-align:center;\"><h3>Thank you!</h3><p>' + (data.message || 'Your consultation request has been received. Our team will contact you shortly.') + '</p></div>';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('There was an error submitting your request. Please try again or contact us directly.');
                    if(btn) { btn.disabled = false; btn.value = 'Submit'; }
                });
            });
        }
    });
});
</script>