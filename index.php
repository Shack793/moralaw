<?php
/* Public website. Everything editable comes from the dashboard (/admin/). */
require __DIR__ . '/inc/bootstrap.php';
$S = ms_settings();
$TT = ms_texts_for_site($S);
$C = $S['contact'];
$brand = ms_brand($S);
$a = trim($S['brand']['a']);
$b = trim($S['brand']['b']);
$logo_html = h($a) . ($a !== '' && $b !== '' ? '<span>&amp;</span>' : '') . h($b);
$addr_html = h($C['address1']) . ($C['address1'] !== '' && $C['address2'] !== '' ? '<br>' : '') . h($C['address2']);
$tel = ms_tel($C['phone']);
$li = ms_url($S['social']['linkedin']);
$tg = ms_url($S['social']['telegram'], 'telegram');
$LG = ms_legal_effective($S);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache');
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($brand) ?> — Abogados, Costa Rica</title>
<meta name="description" content="<?= h($TT['es']['meta_desc']) ?>">
<meta name="theme-color" content="#3D5A4C">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#EFEAE0; --paper-raised:#E6E0D3; --ink:#23282B; --ink-soft:#3A4045;
    --forest:#3D5A4C; --forest-dark:#2C4238; --brass:#A9803F; --brass-bright:#C29650;
    --muted:#666A6D; --border:#D8D1C0;
    --gutter:clamp(18px,5vw,28px);
    --section-y:clamp(52px,9vw,80px);
    --header-h:68px;
  }
  *,*::before,*::after{ box-sizing:border-box; margin:0; padding:0; }
  html{ scroll-behavior:smooth; scroll-padding-top:calc(var(--header-h) + 8px); -webkit-text-size-adjust:100%; }
  body{ background:var(--paper); color:var(--ink); font-family:'Work Sans',system-ui,sans-serif; line-height:1.55; overflow-x:hidden; }
  h1,h2,h3{ font-family:'Newsreader',Georgia,serif; font-weight:500; color:var(--ink); line-height:1.2; }
  a{ color:inherit; }
  img,svg{ max-width:100%; }
  button{ font-family:inherit; }
  :focus-visible{ outline:2px solid var(--brass); outline-offset:3px; border-radius:2px; }
  .container{ max-width:1080px; margin:0 auto; padding-left:max(var(--gutter),env(safe-area-inset-left)); padding-right:max(var(--gutter),env(safe-area-inset-right)); }

  .skip{ position:absolute; left:-9999px; top:8px; background:var(--forest); color:var(--paper); padding:10px 16px; border-radius:3px; z-index:200; font-size:14px; }
  .skip:focus{ left:8px; }

  /* ---------- logo ---------- */
  .logo{ display:flex; align-items:center; gap:11px; text-decoration:none; color:var(--ink); min-width:0; }
  .logo-mark{ width:36px; height:36px; flex-shrink:0; display:block; }
  .logo-text{ display:flex; flex-direction:column; line-height:1.05; min-width:0; }
  .logo-name{ font-family:'Newsreader',Georgia,serif; font-weight:600; font-size:clamp(18px,4.8vw,21px); letter-spacing:.2px; white-space:nowrap; }
  .logo-name span{ color:var(--brass); }
  .logo-sub{ font-size:10.5px; letter-spacing:2.2px; color:var(--muted); font-weight:500; margin-top:3px; text-transform:uppercase; }
  @media (max-width:360px){ .logo-sub{ display:none; } .logo-mark{ width:32px; height:32px; } }

  /* ---------- header ---------- */
  header{ position:sticky; top:0; z-index:50; background:var(--paper); border-bottom:1px solid var(--border); padding-top:env(safe-area-inset-top); }
  .header-inner{ display:flex; align-items:center; justify-content:space-between; gap:14px; min-height:var(--header-h); }
  nav{ display:flex; gap:clamp(18px,2.6vw,30px); }
  nav a{ text-decoration:none; font-size:14px; color:var(--ink-soft); font-weight:500; padding:6px 0; border-bottom:2px solid transparent; }
  .header-right{ display:flex; align-items:center; gap:10px; }
  .lang-switch{ display:flex; border:1px solid var(--border); border-radius:3px; overflow:hidden; }
  .lang-switch button{ background:none; border:none; min-width:40px; min-height:36px; padding:0 10px; font-size:12.5px; font-weight:600; color:var(--muted); cursor:pointer; }
  .lang-switch button.active{ background:var(--forest); color:var(--paper); }
  .nav-toggle{ display:none; background:none; border:1px solid var(--border); border-radius:3px; width:40px; height:36px; cursor:pointer; align-items:center; justify-content:center; color:var(--ink); }
  .nav-toggle svg{ width:20px; height:20px; }
  @media (max-width:880px){
    nav{ display:none; position:absolute; top:100%; left:0; right:0; background:var(--paper); flex-direction:column; gap:0; padding:8px var(--gutter) 16px; border-bottom:1px solid var(--border); box-shadow:0 12px 20px rgba(35,40,43,.08); }
    nav.open{ display:flex; }
    nav a{ padding:14px 0; border-bottom:1px solid var(--border); font-size:15.5px; }
    nav a:last-child{ border-bottom:none; }
    .nav-toggle{ display:inline-flex; }
  }

  /* ---------- buttons ---------- */
  .btn{ display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:0 24px; border-radius:3px; text-decoration:none; font-size:14px; font-weight:600; cursor:pointer; border:1px solid transparent; }
  .btn-primary{ background:var(--forest); color:var(--paper); }
  .btn-outline{ border-color:var(--ink); color:var(--ink); background:none; }

  /* ---------- hero ---------- */
  .hero{ padding:clamp(48px,10vw,90px) 0 clamp(52px,11vw,100px); border-bottom:1px solid var(--border); }
  .hero .container{ display:grid; grid-template-columns:1.2fr 1fr; gap:clamp(28px,5vw,50px); align-items:center; }
  .hero h1{ font-size:clamp(30px,5.6vw,44px); max-width:520px; }
  .hero p.lead{ margin-top:18px; max-width:460px; color:var(--ink-soft); font-size:clamp(15px,2vw,16px); }
  .cta-row{ margin-top:28px; display:flex; gap:12px; flex-wrap:wrap; }
  .hero-figure{ background:var(--forest); border-radius:4px; aspect-ratio:4/3; display:flex; flex-direction:column; justify-content:space-between; gap:12px; padding:clamp(18px,3vw,24px); }
  .hero-figure .gavel{ width:clamp(96px,42%,170px); max-height:60%; margin:auto; display:block; }
  .hero-figure .cite{ color:rgba(239,234,224,.85); font-family:'Newsreader',Georgia,serif; font-style:italic; font-size:clamp(14.5px,2vw,15.5px); line-height:1.5; }
  @media (max-width:760px){
    .hero .container{ grid-template-columns:1fr; }
    .hero-figure{ aspect-ratio:auto; min-height:150px; }
    .cta-row .btn{ flex:1 1 180px; }
  }

  /* ---------- sections ---------- */
  section{ padding:var(--section-y) 0; }
  .section-label{ font-size:13px; color:var(--brass); font-weight:600; margin-bottom:10px; letter-spacing:.3px; }
  .about .container{ display:grid; grid-template-columns:1fr 1.3fr; gap:clamp(24px,5vw,60px); }
  .about h2,.services-header h2,.team-header h2,.contact h2{ font-size:clamp(25px,4vw,30px); }
  .about p{ color:var(--ink-soft); margin-bottom:14px; max-width:560px; }
  @media (max-width:760px){ .about .container{ grid-template-columns:1fr; } }

  .services{ background:var(--paper-raised); border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
  .services-header,.team-header{ max-width:560px; margin-bottom:clamp(28px,5vw,44px); }
  .service-grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:1px; background:var(--border); border:1px solid var(--border); }
  @media (max-width:900px){ .service-grid{ grid-template-columns:repeat(2,1fr); } }
  @media (min-width:601px) and (max-width:900px){ .service-card:last-child:nth-child(odd){ grid-column:1 / -1; } }
  @media (max-width:600px){ .service-grid{ grid-template-columns:1fr; } }
  .service-card{ background:var(--paper-raised); padding:26px 22px; cursor:pointer; }
  .service-card h3{ font-size:18px; margin-bottom:8px; }
  .service-card .teaser{ font-size:14px; color:var(--muted); }
  .service-card .more{ font-size:13px; color:var(--brass); margin-top:10px; display:inline-block; font-weight:500; }
  .service-card p{ color:var(--ink-soft); font-size:14px; max-height:0; overflow:hidden; transition:max-height .25s ease; }
  .service-card.open p{ max-height:380px; margin-top:10px; }

  .team-header{ margin-left:auto; margin-right:auto; text-align:center; }
  .team-grid{ display:flex; flex-wrap:wrap; justify-content:center; gap:clamp(18px,4vw,40px); }
  .team-card{ text-align:center; flex:1 1 140px; max-width:250px; }
  .team-card .photo{ overflow:hidden; background:var(--forest); aspect-ratio:3/4; border-radius:3px; margin-bottom:12px; display:flex; align-items:center; justify-content:center; color:rgba(239,234,224,.4); font-family:'Newsreader',Georgia,serif; font-size:40px; }
  .team-card .photo img{ width:100%; height:100%; object-fit:cover; display:block; }
  .team-card h4{ font-size:15.5px; font-weight:600; margin-bottom:2px; }
  .team-card .role{ font-size:13px; color:var(--brass); }

  /* ---------- contact ---------- */
  .contact{ background:var(--forest-dark); color:var(--paper); }
  .contact .container{ display:grid; grid-template-columns:1fr 1.2fr; gap:clamp(32px,6vw,60px); }
  .contact h2{ color:var(--paper); margin-bottom:16px; }
  .contact-detail{ margin-bottom:18px; font-size:14px; color:rgba(239,234,224,.88); }
  .contact-detail strong{ display:block; color:var(--paper); margin-bottom:3px; font-size:13px; }
  .contact-detail a{ text-decoration:none; }
  form{ display:flex; flex-direction:column; gap:14px; }
  .field label{ display:block; font-size:12.5px; color:rgba(239,234,224,.75); margin-bottom:6px; }
  .field input,.field textarea,.field select{ width:100%; background:rgba(239,234,224,.07); border:1px solid rgba(239,234,224,.3); border-radius:3px; padding:12px; min-height:46px; color:var(--paper); font-family:inherit; font-size:16px; outline:none; -webkit-appearance:none; appearance:none; }
  .field select{ background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%23EFEAE0' stroke-width='1.6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; padding-right:38px; }
  .field select option{ color:#23282B; }
  .field input:focus,.field textarea:focus,.field select:focus{ border-color:var(--brass-bright); }
  .field textarea{ resize:vertical; min-height:110px; }
  .hp{ position:absolute; left:-9999px; }
  .human-check{ display:flex; align-items:center; gap:10px; font-size:13.5px; color:rgba(239,234,224,.85); min-height:36px; cursor:pointer; }
  .human-check input{ width:20px; height:20px; accent-color:var(--brass); flex-shrink:0; }
  .consent{ font-size:12.5px; line-height:1.55; color:rgba(239,234,224,.72); }
  .consent a{ color:var(--brass-bright); }
  .submit-btn{ align-self:flex-start; background:var(--brass-bright); color:#241A0B; border:none; min-height:48px; padding:0 28px; border-radius:3px; font-weight:600; font-size:14px; cursor:pointer; }
  .submit-btn:disabled{ opacity:.6; cursor:default; }
  .form-msg{ font-size:13.5px; min-height:20px; }
  .form-msg.success{ color:#A9D4B2; }
  .form-msg.error{ color:#F0B3A6; }
  @media (max-width:760px){ .contact .container{ grid-template-columns:1fr; } .submit-btn{ align-self:stretch; } }

  /* ---------- footer ---------- */
  footer{ background:var(--ink); color:rgba(239,234,224,.75); border-top:3px solid var(--brass); padding-bottom:env(safe-area-inset-bottom); }
  .footer-main{ display:grid; grid-template-columns:1.5fr 1fr 1.2fr 1.3fr; gap:clamp(28px,4vw,48px); padding-top:clamp(40px,7vw,60px); padding-bottom:clamp(32px,5vw,44px); }
  footer .logo{ color:var(--paper); margin-bottom:16px; }
  footer .logo-sub{ color:rgba(239,234,224,.55); }
  .footer-about{ font-size:13.5px; line-height:1.65; max-width:320px; margin-bottom:20px; }
  footer h4{ font-family:'Work Sans',sans-serif; font-size:12px; letter-spacing:1.6px; text-transform:uppercase; color:var(--brass-bright); font-weight:600; margin-bottom:16px; }
  footer ul{ list-style:none; }
  footer li{ margin-bottom:10px; font-size:13.5px; }
  footer a{ text-decoration:none; color:rgba(239,234,224,.8); }
  .footer-contact li{ line-height:1.5; }
  .social{ display:flex; gap:10px; }
  .social a{ height:40px; padding:0 18px; border:1px solid rgba(239,234,224,.28); border-radius:20px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:600; color:var(--paper); }
  .footer-bottom{ border-top:1px solid rgba(239,234,224,.14); padding-top:20px; padding-bottom:26px; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px 24px; font-size:12.5px; color:rgba(239,234,224,.6); }
  .footer-legal{ display:flex; flex-wrap:wrap; gap:8px 22px; align-items:center; }
  .footer-disclaimer{ flex-basis:100%; font-size:12px; line-height:1.6; color:rgba(239,234,224,.5); max-width:760px; }
  @media (max-width:960px){ .footer-main{ grid-template-columns:1fr 1fr; } .footer-brand{ grid-column:1 / -1; } }
  @media (max-width:540px){ .footer-main{ grid-template-columns:1fr; } .footer-bottom{ flex-direction:column; align-items:flex-start; } }

  /* hover only on devices that can hover (avoids sticky hover on touch) */
  @media (hover:hover){
    nav a:hover{ color:var(--forest); border-bottom-color:var(--brass); }
    .btn-primary:hover{ background:var(--forest-dark); }
    .btn-outline:hover{ background:var(--ink); color:var(--paper); }
    .service-card:hover h3{ color:var(--forest); }
    .submit-btn:hover{ background:#D2A85F; }
    footer a:hover{ color:var(--brass-bright); }
    .social a:hover{ background:var(--brass); border-color:var(--brass); color:#241A0B; }
  }
  @media (prefers-reduced-motion:reduce){
    html{ scroll-behavior:auto; }
    .service-card p{ transition:none; }
  }
</style>
</head>
<body id="top">

<!-- logo mark sprite -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <symbol id="hc-mark" viewBox="0 0 40 40">
    <rect width="40" height="40" rx="8" fill="#3D5A4C"/>
    <path d="M20 7l13 7H7z" fill="#C29650"/>
    <rect x="8" y="16" width="24" height="2.5" fill="#C29650"/>
    <rect x="10.5" y="20" width="4" height="10" fill="#EFEAE0"/>
    <rect x="18" y="20" width="4" height="10" fill="#EFEAE0"/>
    <rect x="25.5" y="20" width="4" height="10" fill="#EFEAE0"/>
    <rect x="8" y="31" width="24" height="2.5" fill="#C29650"/>
  </symbol>
</svg>

<a class="skip" href="#main" data-i18n="skip"></a>

<header>
  <div class="container header-inner">
    <a class="logo" href="#top" aria-label="<?= h($brand) ?>">
      <svg class="logo-mark" aria-hidden="true"><use href="#hc-mark"/></svg>
      <span class="logo-text">
        <span class="logo-name"><?= $logo_html ?></span>
        <span class="logo-sub" data-i18n="logo_sub"></span>
      </span>
    </a>
    <nav id="mainNav" aria-label="Main">
      <a href="#about" data-i18n="nav_about"></a>
      <a href="#services" data-i18n="nav_services"></a>
      <?php if ($S['team']) : ?><a href="#team" data-i18n="nav_team"></a><?php endif; ?>
      <a href="#contact" data-i18n="nav_contact"></a>
    </nav>
    <div class="header-right">
      <div class="lang-switch" role="group" aria-label="Language / Idioma">
        <button id="langES" class="active" type="button" aria-pressed="true">ES</button>
        <button id="langEN" type="button" aria-pressed="false">EN</button>
      </div>
      <button class="nav-toggle" id="navToggle" type="button" aria-label="Menu" aria-expanded="false" aria-controls="mainNav">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 5.5h14M3 10h14M3 14.5h14"/></svg>
      </button>
    </div>
  </div>
</header>

<main id="main">
<section class="hero">
  <div class="container">
    <div>
      <h1 data-i18n="hero_title"></h1>
      <p class="lead" data-i18n="hero_lead"></p>
      <div class="cta-row">
        <a href="#contact" class="btn btn-primary" data-i18n="hero_cta1"></a>
        <a href="#services" class="btn btn-outline" data-i18n="hero_cta2"></a>
      </div>
    </div>
    <div class="hero-figure">
      <svg class="gavel" viewBox="0 0 220 170" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id="gvWood" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2a170a"/><stop offset=".22" stop-color="#7b4a25"/><stop offset=".4" stop-color="#b57a45"/><stop offset=".6" stop-color="#8a5429"/><stop offset="1" stop-color="#2a170a"/></linearGradient>
          <linearGradient id="gvHandle" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2a170a"/><stop offset=".35" stop-color="#a8703c"/><stop offset=".55" stop-color="#8a5429"/><stop offset="1" stop-color="#2a170a"/></linearGradient>
          <linearGradient id="gvBrass" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6e4f1c"/><stop offset=".3" stop-color="#f0d08a"/><stop offset=".55" stop-color="#b98c43"/><stop offset="1" stop-color="#5e4216"/></linearGradient>
          <linearGradient id="gvBlock" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2a170a"/><stop offset=".3" stop-color="#9a6334"/><stop offset=".55" stop-color="#6f4220"/><stop offset="1" stop-color="#22120a"/></linearGradient>
          <radialGradient id="gvTop" cx=".4" cy=".4" r=".7"><stop offset="0" stop-color="#c58d55"/><stop offset="1" stop-color="#6b3f20"/></radialGradient>
          <radialGradient id="gvShadow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#000" stop-opacity=".55"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
        </defs>
        <ellipse cx="96" cy="152" rx="82" ry="11" fill="url(#gvShadow)"/>
        <path d="M30 128v14c0 8 29 14 66 14s66-6 66-14v-14z" fill="url(#gvBlock)"/>
        <ellipse cx="96" cy="128" rx="66" ry="14" fill="url(#gvTop)"/>
        <ellipse cx="96" cy="128" rx="66" ry="14" fill="none" stroke="#e0b07a" stroke-opacity=".35"/>
        <g transform="rotate(-24 120 60)">
          <path d="M113 74h14l-2 82q-5 7-10 0z" fill="url(#gvHandle)"/>
          <rect x="62" y="30" width="116" height="46" rx="8" fill="url(#gvWood)"/>
          <ellipse cx="62" cy="53" rx="7" ry="23" fill="#4a2a14"/>
          <ellipse cx="178" cy="53" rx="7" ry="23" fill="#8f5a2f"/>
          <rect x="78" y="30" width="9" height="46" fill="url(#gvBrass)"/>
          <rect x="153" y="30" width="9" height="46" fill="url(#gvBrass)"/>
          <path d="M92 38h56M92 46h56M92 54h56M92 62h56" stroke="#2a170a" stroke-opacity=".12"/>
          <rect x="66" y="33" width="108" height="5" rx="2.5" fill="#fff" fill-opacity=".16"/>
        </g>
      </svg>
      <div class="cite" data-i18n="hero_quote"></div>
    </div>
  </div>
</section>

<section class="about" id="about">
  <div class="container">
    <div>
      <div class="section-label" data-i18n="about_label"></div>
      <h2 data-i18n="about_title"></h2>
    </div>
    <div>
      <p data-i18n="about_p1"></p>
      <p data-i18n="about_p2"></p>
      <p data-i18n="about_p3"></p>
    </div>
  </div>
</section>

<section class="services" id="services">
  <div class="container">
    <div class="services-header">
      <div class="section-label" data-i18n="services_label"></div>
      <h2 data-i18n="services_title"></h2>
    </div>
    <div class="service-grid">
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s1_t"></h3><div class="teaser" data-i18n="s1_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s1_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s2_t"></h3><div class="teaser" data-i18n="s2_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s2_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s3_t"></h3><div class="teaser" data-i18n="s3_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s3_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s4_t"></h3><div class="teaser" data-i18n="s4_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s4_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s5_t"></h3><div class="teaser" data-i18n="s5_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s5_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s6_t"></h3><div class="teaser" data-i18n="s6_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s6_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s7_t"></h3><div class="teaser" data-i18n="s7_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s7_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s8_t"></h3><div class="teaser" data-i18n="s8_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s8_body"></p></div>
      <div class="service-card" role="button" tabindex="0" aria-expanded="false"><h3 data-i18n="s9_t"></h3><div class="teaser" data-i18n="s9_teaser"></div><span class="more" data-i18n="read_more"></span><p data-i18n="s9_body"></p></div>
    </div>
  </div>
</section>

<?php if ($S['team']) : ?>
<section class="team" id="team">
  <div class="container">
    <div class="team-header">
      <div class="section-label" data-i18n="team_label"></div>
      <h2 data-i18n="team_title"></h2>
    </div>
    <div class="team-grid">
<?php foreach ($S['team'] as $i => $m) :
    $has_photo = $m['photo'] !== '' && preg_match('/^t_[0-9a-f]{16}\.jpg$/', $m['photo']) && is_file(MS_PHOTOS . '/' . $m['photo']); ?>
      <div class="team-card"><div class="photo"<?= $has_photo ? '' : ' aria-hidden="true"' ?>><?php if ($has_photo) : ?><img src="uploads/team/<?= h($m['photo']) ?>" alt="<?= h($m['name']) ?>" loading="lazy"><?php else : ?><?= h(mb_strtoupper(mb_substr($m['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?><?php endif; ?></div><h4><?= h($m['name']) ?></h4><div class="role" data-i18n="team_role_<?= (int) $i ?>"></div></div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php endif; ?>

<section class="contact" id="contact">
  <div class="container">
    <div>
      <div class="section-label" style="color:var(--brass-bright);" data-i18n="contact_label"></div>
      <h2 data-i18n="contact_title"></h2>
      <div class="contact-detail"><strong data-i18n="office"></strong><?= $addr_html ?></div>
      <div class="contact-detail"><strong data-i18n="phone"></strong><?php if ($tel !== '') : ?><a href="tel:<?= h($tel) ?>"><?= h($C['phone']) ?></a><?php endif; ?></div>
      <div class="contact-detail"><strong>Email</strong><?php if ($C['email'] !== '') : ?><a href="mailto:<?= h($C['email']) ?>"><?= h($C['email']) ?></a><?php endif; ?></div>
      <div class="contact-detail"><strong data-i18n="hours"></strong><span data-i18n="hours_val"></span></div>
    </div>
    <form id="contactForm" method="POST" action="contact.php" novalidate>
      <div class="field"><label for="fName" data-i18n="f_name"></label><input type="text" id="fName" name="name" autocomplete="name" required></div>
      <div class="field"><label for="fEmail" data-i18n="f_email"></label><input type="email" id="fEmail" name="email" autocomplete="email" inputmode="email" required></div>
      <div class="field">
        <label for="fTopic" data-i18n="f_topic"></label>
        <select id="fTopic" name="topic">
          <option value="general" data-i18n="t_general"></option>
          <option value="corporate" data-i18n="s1_t"></option>
          <option value="realestate" data-i18n="s2_t"></option>
          <option value="immigration" data-i18n="s3_t"></option>
          <option value="contracts" data-i18n="s4_t"></option>
          <option value="employment" data-i18n="s5_t"></option>
          <option value="estate" data-i18n="s6_t"></option>
          <option value="igaming" data-i18n="s7_t"></option>
          <option value="licensing" data-i18n="s8_t"></option>
          <option value="fintech" data-i18n="s9_t"></option>
        </select>
      </div>
      <div class="field"><label for="fMsg" data-i18n="f_msg"></label><textarea id="fMsg" name="message" required></textarea></div>
      <input class="hp" type="text" id="fHoney" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="human-check"><input type="checkbox" id="fHuman" required><span data-i18n="f_human"></span></label>
      <?php if ($LG['privacy']['show']) : ?><p class="consent"><span data-i18n="f_consent"></span> <a href="/<?= h(ms_legal_pages()['privacy']['slug']) ?>" data-i18n="footer_privacy"></a>.</p><?php endif; ?>
      <button type="submit" class="submit-btn" id="submitBtn" data-i18n="f_send"></button>
      <div class="form-msg" id="formMsg" role="status" aria-live="polite"></div>
    </form>
  </div>
</section>
</main>

<footer>
  <div class="container">
    <div class="footer-main">
      <div class="footer-brand">
        <a class="logo" href="#top" aria-label="<?= h($brand) ?>">
          <svg class="logo-mark" aria-hidden="true"><use href="#hc-mark"/></svg>
          <span class="logo-text">
            <span class="logo-name"><?= $logo_html ?></span>
            <span class="logo-sub" data-i18n="logo_sub"></span>
          </span>
        </a>
        <p class="footer-about" data-i18n="f_tag"></p>
        <?php if ($li !== '' || $tg !== '') : ?><div class="social">
          <?php if ($li !== '') : ?><a href="<?= h($li) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a><?php endif; ?>
          <?php if ($tg !== '') : ?><a href="<?= h($tg) ?>" target="_blank" rel="noopener noreferrer">Telegram</a><?php endif; ?>
        </div><?php endif; ?>
      </div>
      <nav aria-label="Footer">
        <h4 data-i18n="f_firm"></h4>
        <ul>
          <li><a href="#about" data-i18n="nav_about"></a></li>
          <li><a href="#services" data-i18n="nav_services"></a></li>
          <?php if ($S['team']) : ?><li><a href="#team" data-i18n="nav_team"></a></li><?php endif; ?>
          <li><a href="#contact" data-i18n="nav_contact"></a></li>
        </ul>
      </nav>
      <div>
        <h4 data-i18n="services_title"></h4>
        <ul>
          <li><a href="#services" data-i18n="s1_t"></a></li>
          <li><a href="#services" data-i18n="s2_t"></a></li>
          <li><a href="#services" data-i18n="s3_t"></a></li>
          <li><a href="#services" data-i18n="s4_t"></a></li>
          <li><a href="#services" data-i18n="s5_t"></a></li>
          <li><a href="#services" data-i18n="s6_t"></a></li>
          <li><a href="#services" data-i18n="s7_t"></a></li>
          <li><a href="#services" data-i18n="s8_t"></a></li>
          <li><a href="#services" data-i18n="s9_t"></a></li>
        </ul>
      </div>
      <div>
        <h4 data-i18n="contact_title"></h4>
        <ul class="footer-contact">
          <li><?= $addr_html ?></li>
          <li><?php if ($tel !== '') : ?><a href="tel:<?= h($tel) ?>"><?= h($C['phone']) ?></a><?php endif; ?></li>
          <li><?php if ($C['email'] !== '') : ?><a href="mailto:<?= h($C['email']) ?>"><?= h($C['email']) ?></a><?php endif; ?></li>
          <li data-i18n="hours_val"></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span data-i18n="footer_rights"></span>
      <div class="footer-legal">
        <?php foreach (ms_legal_pages() as $lk => $lp) : if ($LG[$lk]['show']) : ?><a href="/<?= h($lp['slug']) ?>" data-i18n="<?= h($lp['foot']) ?>"></a>
        <?php endif; endforeach; ?>
        <a href="#top" data-i18n="back_top"></a>
      </div>
      <p class="footer-disclaimer" data-i18n="footer_disclaimer"></p>
    </div>
  </div>
</footer>

<script>
  const BRAND = <?= ms_json_for_script($brand) ?>;
  const FALLBACK_EMAIL = <?= ms_json_for_script($C['email']) ?>;

  const T = <?= ms_json_for_script($TT) ?>;

  let lang = "es"; // Spanish is the default; a visitor's choice is remembered
  try{
    const saved = localStorage.getItem("hc_lang");
    if(saved==="es"||saved==="en") lang = saved;
  }catch(e){}

  function applyLang(l){
    lang = l;
    document.documentElement.lang = l;
    document.querySelectorAll("[data-i18n]").forEach(el=>{
      const v = T[l][el.dataset.i18n]; if(v!==undefined) el.textContent = v;
    });
    const en = document.getElementById("langEN"), es = document.getElementById("langES");
    en.classList.toggle("active", l==="en"); es.classList.toggle("active", l==="es");
    en.setAttribute("aria-pressed", l==="en"); es.setAttribute("aria-pressed", l==="es");
    document.title = BRAND + (l==="es" ? " — Abogados, Costa Rica" : " — Attorneys, Costa Rica");
    const md = document.querySelector('meta[name="description"]'); if(md) md.setAttribute("content", T[l].meta_desc);
    try{ localStorage.setItem("hc_lang", l); }catch(e){}
  }
  document.getElementById("langEN").addEventListener("click", ()=>applyLang("en"));
  document.getElementById("langES").addEventListener("click", ()=>applyLang("es"));

  /* mobile nav */
  const nav = document.getElementById("mainNav"), toggle = document.getElementById("navToggle");
  function setNav(open){ nav.classList.toggle("open", open); toggle.setAttribute("aria-expanded", open); }
  toggle.addEventListener("click", ()=>setNav(!nav.classList.contains("open")));
  nav.querySelectorAll("a").forEach(a=>a.addEventListener("click", ()=>setNav(false)));
  document.addEventListener("keydown", e=>{ if(e.key==="Escape") setNav(false); });
  window.addEventListener("resize", ()=>{ if(window.innerWidth>880) setNav(false); });

  /* service cards: click / Enter / Space */
  const cards = document.querySelectorAll(".service-card");
  function toggleCard(card){
    const was = card.classList.contains("open");
    cards.forEach(c=>{ c.classList.remove("open"); c.setAttribute("aria-expanded","false"); });
    if(!was){ card.classList.add("open"); card.setAttribute("aria-expanded","true"); }
  }
  cards.forEach(card=>{
    card.addEventListener("click", ()=>toggleCard(card));
    card.addEventListener("keydown", e=>{ if(e.key==="Enter"||e.key===" "){ e.preventDefault(); toggleCard(card); } });
  });

  /* contact form */
  const form = document.getElementById("contactForm");
  const msg = document.getElementById("formMsg");
  const btn = document.getElementById("submitBtn");
  function say(key, type){ msg.textContent = T[lang][key]; msg.className = "form-msg " + type; }

  form.addEventListener("submit", async (e)=>{
    e.preventDefault();
    if(document.getElementById("fHoney").value) return; // spam trap
    const name = document.getElementById("fName").value.trim();
    const email = document.getElementById("fEmail").value.trim();
    const body = document.getElementById("fMsg").value.trim();
    const topic = document.getElementById("fTopic").selectedOptions[0].textContent;
    if(!name || !email || !body) return say("m_fill","error");
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return say("m_email","error");
    if(!document.getElementById("fHuman").checked) return say("m_human","error");

    if(location.protocol === "file:"){
      say("m_mailto","success");
      const subject = encodeURIComponent(`[${topic}] ${name}`);
      const mailBody = encodeURIComponent(`${body}\n\n— ${name} (${email})`);
      window.location.href = `mailto:${FALLBACK_EMAIL}?subject=${subject}&body=${mailBody}`;
      return;
    }
    btn.disabled = true; say("m_sending","success");
    try{
      const res = await fetch("contact.php", {
        method:"POST",
        headers:{ "Content-Type":"application/x-www-form-urlencoded", "Accept":"application/json" },
        body: new URLSearchParams({ name, email, topic, message: body, language: lang, website_url: "" }).toString()
      });
      let data = null;
      try{ data = await res.json(); }catch(_){}
      if(res.ok && data && data.ok){ say("m_ok","success"); form.reset(); }
      else if(data && data.error === "rate"){ say("m_rate","error"); }
      else if(data && data.error === "invalid"){ say("m_fill","error"); }
      else { say("m_fail","error"); }
    }catch(err){ say("m_fail","error"); }
    btn.disabled = false;
  });

  applyLang(lang);
</script>
</body>
</html>
<?php
echo ms_prefill_i18n(ob_get_clean(), $TT['es']);
