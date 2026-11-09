<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Despacho Sarapiquí · Despacho y rastreo de ambulancias</title>
    <meta name="description" content="Demostración de un sistema de despacho y rastreo de ambulancias en tiempo real para Sarapiquí, Costa Rica. Hecho con Laravel y Filament.">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root {
            --ink: #0a0f0e; --card: #141b1a; --card-2: #1b2423; --line: #26302f;
            --paper: #f3f6f5; --white: #ffffff; --muted: #8a9997; --muted-d: #5b6866;
            --accent: #2dd4bf; --accent-2: #14b8a6; --accent-ink: #042f2b;
            --green: #16a34a; --amber: #f59e0b; --red: #dc2626; --blue: #2563eb; --violet: #7c3aed; --gray: #6b7280;
            --radius: 28px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: Inter, system-ui, sans-serif; background: var(--paper); color: var(--ink); line-height: 1.5; -webkit-font-smoothing: antialiased; }
        h1, h2, h3, .display { font-family: "Inter Tight", Inter, system-ui, sans-serif; letter-spacing: -0.03em; line-height: 1.02; }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1240px; margin: 0 auto; padding: 16px; }

        /* Botones */
        .btn { display: inline-flex; align-items: center; gap: .65rem; padding: .8rem 1.25rem; border-radius: 999px; font-weight: 600; font-size: .95rem; transition: transform .15s ease, background .15s ease; white-space: nowrap; }
        .btn:hover { transform: translateY(-1px); }
        .btn-accent { background: var(--accent); color: var(--accent-ink); }
        .btn-accent:hover { background: #5eead4; }
        .btn-dark { background: rgba(255,255,255,.08); color: var(--white); border: 1px solid rgba(255,255,255,.12); backdrop-filter: blur(8px); }
        .btn-light { background: var(--white); color: var(--ink); }
        .btn-outline { border: 1.5px solid var(--ink); color: var(--ink); }
        .arrow { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 999px; background: var(--ink); color: var(--white); font-size: .8rem; }
        .btn-light .arrow, .btn-outline .arrow { background: var(--ink); color: var(--white); }
        .btn-accent .arrow { background: var(--accent-ink); color: var(--accent); }

        /* Hero */
        .hero { position: relative; border-radius: var(--radius); overflow: hidden; background: var(--ink); color: var(--white); min-height: 720px; display: flex; flex-direction: column; }
        .hero-map { position: absolute; inset: 0; z-index: 0; }
        .hero-map .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.75) contrast(.95) saturate(.35); }
        .hero::after { content: ''; position: absolute; inset: 0; z-index: 1; pointer-events: none;
            background: linear-gradient(90deg, rgba(10,15,14,.96) 0%, rgba(10,15,14,.82) 38%, rgba(10,15,14,.25) 70%, rgba(10,15,14,.55) 100%),
                        linear-gradient(0deg, rgba(10,15,14,.95) 0%, rgba(10,15,14,0) 45%); }
        .hero > *:not(.hero-map) { position: relative; z-index: 2; }
        nav { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 22px 28px; }
        nav .logo img { height: 38px; display: block; }
        nav .links { display: flex; gap: 2rem; font-size: .92rem; color: rgba(255,255,255,.8); }
        nav .links a:hover { color: var(--white); }
        .hero-body { flex: 1; padding: 40px 28px 28px; display: flex; flex-direction: column; justify-content: space-between; gap: 48px; }
        .hero h1 { font-size: clamp(2.8rem, 6.4vw, 5.4rem); font-weight: 700; max-width: 11ch; }
        .hero h1 span { color: var(--accent); }
        .hero-lead { margin-top: 1.25rem; max-width: 46ch; color: rgba(255,255,255,.72); font-size: 1.05rem; }
        .hero-ctas { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem; }
        .live-tag { display: inline-flex; align-items: center; gap: .5rem; padding: .4rem .8rem; border-radius: 999px; background: rgba(45,212,191,.12); color: var(--accent); font-size: .8rem; font-weight: 600; margin-bottom: 1.25rem; }
        .live-tag i { width: 8px; height: 8px; border-radius: 999px; background: var(--accent); animation: blink 1.4s infinite; }

        .hero-cards { display: grid; grid-template-columns: 1.1fr 1fr 1fr; gap: 14px; }
        .hcard { border-radius: 22px; padding: 20px 22px; min-height: 150px; display: flex; flex-direction: column; justify-content: space-between; }
        .hcard-white { background: var(--white); color: var(--ink); }
        .hcard-glass { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1); backdrop-filter: blur(12px); }
        .hcard-accent { background: var(--accent); color: var(--accent-ink); position: relative; transition: transform .15s ease; }
        .hcard-accent:hover { transform: translateY(-2px); }
        .hcard-accent .corner { position: absolute; top: 18px; right: 18px; }
        .stat-row { display: flex; align-items: center; gap: 1rem; }
        .dots { display: flex; }
        .dots span { width: 34px; height: 34px; border-radius: 999px; border: 3px solid var(--white); margin-left: -10px; display: grid; place-items: center; color: #fff; font: 700 11px/1 Inter, sans-serif; }
        .dots span:first-child { margin-left: 0; }
        .stat-num { font-family: "Inter Tight", sans-serif; font-size: 1.8rem; font-weight: 700; line-height: 1; }
        .stat-cap { font-size: .75rem; color: var(--muted-d); }
        .hcard-white p { font-size: .82rem; color: var(--muted-d); margin-top: .9rem; }
        .tip { text-align: center; font-size: .95rem; color: rgba(255,255,255,.9); min-height: 3.2em; transition: opacity .25s ease; }
        .tip-nav { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .tip-nav button { width: 30px; height: 30px; border-radius: 999px; border: 0; cursor: pointer; background: rgba(255,255,255,.14); color: #fff; font-size: .9rem; }
        .tip-foot { display: flex; justify-content: space-between; font-size: .78rem; color: rgba(255,255,255,.55); }
        .hcard-accent h3 { font-size: 1.45rem; font-weight: 700; }
        .hcard-accent p { font-size: .8rem; opacity: .75; margin-top: .25rem; }

        /* Sección intro */
        .intro { display: grid; grid-template-columns: 1fr 1.05fr .8fr; gap: 18px; padding: 72px 4px; align-items: stretch; }
        .pill { display: inline-block; padding: .45rem 1rem; border-radius: 999px; border: 1.5px solid var(--ink); font-weight: 600; font-size: .9rem; }
        .intro-text { display: flex; flex-direction: column; justify-content: space-between; gap: 2rem; padding-right: 1rem; }
        .intro-text h2 { font-size: clamp(1.8rem, 3vw, 2.4rem); font-weight: 700; }
        .intro-text p { color: var(--muted-d); max-width: 42ch; margin-top: 1rem; }
        .zone { position: relative; border-radius: var(--radius); overflow: hidden; background: var(--ink); color: var(--white); min-height: 380px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; }
        .zone .tag { align-self: flex-start; background: var(--white); color: var(--ink); padding: .5rem 1rem; border-radius: 999px; font-weight: 600; font-size: .92rem; position: relative; z-index: 2; }
        .zone h3 { font-family: Inter, sans-serif; font-weight: 500; font-size: 1.35rem; letter-spacing: -0.01em; line-height: 1.2; max-width: 16ch; position: relative; z-index: 2; }
        .zone-map { background:
            radial-gradient(circle at 70% 30%, rgba(45,212,191,.18), transparent 45%),
            linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px) 0 0 / 34px 34px,
            linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px) 0 0 / 34px 34px, #0d1413; }
        .zone-map svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .zone-list { background: var(--card); }
        .rows { display: flex; flex-direction: column; gap: 10px; margin: 1.25rem 0; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .7rem .85rem; border-radius: 14px; background: var(--card-2); font-size: .8rem; }
        .row b { font-family: "Inter Tight", sans-serif; font-size: .88rem; }
        .badge { display: inline-flex; align-items: center; padding: .2rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; color: #fff; }

        /* Bento */
        .bento { background: var(--ink); color: var(--white); border-radius: var(--radius); padding: 18px; display: grid; gap: 14px;
            grid-template-columns: repeat(6, 1fr); grid-auto-rows: minmax(150px, auto); }
        .tile { background: var(--card); border-radius: 22px; padding: 22px; display: flex; align-items: center; gap: 1.1rem; }
        .tile .ic { flex-shrink: 0; width: 64px; height: 64px; display: grid; place-items: center; }
        .tile .ic svg { width: 56px; height: 56px; stroke: var(--white); }
        .tile p { font-size: 1.15rem; line-height: 1.3; font-weight: 500; }
        .tile small { display: block; color: var(--muted); font-size: .85rem; font-weight: 400; margin-top: .3rem; }
        .tile-col { flex-direction: column; justify-content: center; text-align: center; }
        .big-num { font-family: "Inter Tight", sans-serif; font-size: 4rem; font-weight: 700; line-height: .9; letter-spacing: -0.05em; }
        .tile-hero { grid-column: span 2; grid-row: span 3; background: var(--accent); color: var(--accent-ink); flex-direction: column; justify-content: space-between; align-items: flex-start; padding: 28px; position: relative; overflow: hidden; }
        .tile-hero h3 { font-size: 2.1rem; font-weight: 700; max-width: 9ch; }
        .tile-hero p { font-size: .95rem; max-width: 28ch; }
        .tile-hero .mark { position: absolute; right: -40px; bottom: -30px; width: 280px; opacity: .95; }
        .s2 { grid-column: span 2; } .s1 { grid-column: span 1; }

        /* Cómo usarlo */
        .how { padding: 80px 4px 40px; }
        .how-head { display: flex; justify-content: space-between; align-items: end; gap: 2rem; flex-wrap: wrap; margin-bottom: 28px; }
        .how-head h2 { font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 700; max-width: 14ch; }
        .how-head p { max-width: 40ch; color: var(--muted-d); }
        .how-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 14px; }
        .how-card { background: var(--white); border-radius: var(--radius); padding: 28px; }
        .how-card.dark { background: var(--ink); color: var(--white); }
        .how-card h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: .6rem; }
        .how-card ol, .how-card ul { list-style: none; display: flex; flex-direction: column; gap: .9rem; }
        .how-card li { display: grid; grid-template-columns: 30px 1fr; gap: .75rem; font-size: .95rem; }
        .how-card li .n { width: 30px; height: 30px; border-radius: 999px; background: var(--accent); color: var(--accent-ink); display: grid; place-items: center; font-weight: 700; font-size: .85rem; }
        .how-card.dark li .n { background: rgba(45,212,191,.15); color: var(--accent); }
        .how-card li .b { width: 30px; height: 30px; border-radius: 10px; background: var(--paper); display: grid; place-items: center; }
        .how-card li .b svg { width: 16px; height: 16px; stroke: var(--ink); }
        .how-card b { font-weight: 600; }

        /* Estados */
        .states { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-top: 14px; }
        .state { background: var(--white); border-radius: 20px; padding: 16px; }
        .state .sw { width: 34px; height: 34px; border-radius: 999px; border: 3px solid #fff; box-shadow: 0 0 0 2px var(--c); background: var(--c); margin-bottom: .75rem; }
        .state b { display: block; font-family: "Inter Tight", sans-serif; font-size: 1rem; }
        .state span { font-size: .8rem; color: var(--muted-d); }

        /* Cierre */
        .cta { margin-top: 80px; background: var(--ink); color: var(--white); border-radius: var(--radius); padding: 56px 28px; display: grid; grid-template-columns: 1.4fr 1fr; gap: 2rem; align-items: center; }
        .cta h2 { font-size: clamp(2rem, 4vw, 3.4rem); font-weight: 700; }
        .cta h2 span { color: var(--accent); }
        .cta-box { background: var(--card); border-radius: 22px; padding: 22px; }
        .cta-box dl { display: grid; grid-template-columns: auto 1fr; gap: .6rem 1rem; margin-bottom: 1.25rem; font-size: .92rem; }
        .cta-box dt { color: var(--muted); }
        .cta-box dd { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 28px 8px 36px; font-size: .85rem; color: var(--muted-d); }
        footer a { text-decoration: underline; text-underline-offset: 3px; }

        @keyframes blink { 50% { opacity: .3; } }
        @keyframes ring { from { transform: scale(.7); opacity: .9; } to { transform: scale(1.8); opacity: 0; } }
        .u { width: 30px; height: 30px; border-radius: 999px; background: var(--c); border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.5); color: #fff; display: grid; place-items: center; font: 700 11px/1 Inter, sans-serif; position: relative; }
        .u.pulse::after { content: ''; position: absolute; inset: -7px; border-radius: 999px; border: 3px solid var(--c); animation: ring 1.6s ease-out infinite; }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-cards { grid-template-columns: 1fr 1fr; }
            .hcard-accent { grid-column: span 2; }
            .intro { grid-template-columns: 1fr 1fr; }
            .intro-text { grid-column: span 2; }
            .bento { grid-template-columns: repeat(4, 1fr); }
            .tile-hero { grid-column: span 4; grid-row: span 1; min-height: 260px; }
            .how-grid { grid-template-columns: 1fr 1fr; }
            .how-grid .how-card:first-child { grid-column: span 2; }
            .states { grid-template-columns: repeat(3, 1fr); }
            .cta { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            nav .links { display: none; }
            nav { padding: 18px; }
            nav .logo img { height: 32px; }
            .hero { min-height: 0; }
            .hero-body { padding: 24px 18px 18px; }
            .hero-cards { grid-template-columns: 1fr; }
            .hcard-accent { grid-column: auto; }
            .intro { grid-template-columns: 1fr; padding: 48px 0; }
            .intro-text { grid-column: auto; padding: 0; }
            .zone { min-height: 320px; }
            .bento { grid-template-columns: 1fr 1fr; padding: 12px; gap: 10px; }
            .tile-hero, .s2 { grid-column: span 2; }
            .tile { padding: 18px; }
            .tile p { font-size: 1rem; }
            .tile .ic { width: 44px; height: 44px; } .tile .ic svg { width: 40px; height: 40px; }
            .big-num { font-size: 3rem; }
            .how-grid { grid-template-columns: 1fr; }
            .how-grid .how-card:first-child { grid-column: auto; }
            .states { grid-template-columns: 1fr 1fr; }
            .cta { padding: 36px 20px; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <!-- ═══ Portada ═══ -->
    <header class="hero">
        <div id="hero-map" class="hero-map" aria-hidden="true"></div>

        <nav>
            <a href="{{ url('/') }}" class="logo"><img src="{{ asset('images/logo-dark.svg') }}" alt="Despacho Sarapiquí"></a>
            <div class="links">
                <a href="#funciones">Funciones</a>
                <a href="#como-usarlo">Cómo usarlo</a>
                <a href="#estados">Estados</a>
                <a href="https://github.com/kvargas-devCruz/despacho-sarapiqui" target="_blank" rel="noopener">Código</a>
            </div>
            <a href="{{ url('/admin') }}" class="btn btn-light">Entrar</a>
        </nav>

        <div class="hero-body">
            <div>
                <div class="live-tag"><i></i> Demostración en vivo · Sarapiquí, Costa Rica</div>
                <h1>Despacha. Rastrea. <span>Responde.</span></h1>
                <p class="hero-lead">Sistema de despacho de ambulancias en tiempo real: registra emergencias y traslados, asigna la unidad más cercana y sigue cada recorrido sobre el mapa.</p>
                <div class="hero-ctas">
                    <a href="{{ url('/admin') }}" class="btn btn-accent">Entrar a la demo <span class="arrow">↗</span></a>
                    <a href="#como-usarlo" class="btn btn-dark">Cómo usarlo</a>
                </div>
            </div>

            <div class="hero-cards">
                <div class="hcard hcard-white">
                    <div class="stat-row">
                        <div class="dots">
                            <span style="background:var(--green)">01</span>
                            <span style="background:var(--amber)">02</span>
                            <span style="background:var(--red)">03</span>
                            <span style="background:var(--blue)">04</span>
                        </div>
                        <div>
                            <div class="stat-num">6 unidades</div>
                            <div class="stat-cap">en servicio simulado</div>
                        </div>
                    </div>
                    <p>Cada unidad recorre las carreteras reales de la zona y pasa por el ciclo completo: en camino, en escena, traslado y regreso a la base.</p>
                </div>

                <div class="hcard hcard-glass">
                    <div class="tip-nav">
                        <button type="button" data-tip="-1" aria-label="Consejo anterior">←</button>
                        <p class="tip" id="tip"></p>
                        <button type="button" data-tip="1" aria-label="Consejo siguiente">→</button>
                    </div>
                    <div class="tip-foot"><span>Consejo de uso</span><span id="tip-count"></span></div>
                </div>

                <a href="{{ url('/admin') }}" class="hcard hcard-accent">
                    <span class="arrow corner">↗</span>
                    <div></div>
                    <div>
                        <h3>Acceso precargado</h3>
                        <p>El usuario de prueba ya viene escrito. Solo presiona «Entrar».</p>
                    </div>
                </a>
            </div>
        </div>
    </header>

    <!-- ═══ Introducción ═══ -->
    <section class="intro">
        <div class="intro-text">
            <span class="pill" style="align-self:flex-start">Central de despacho</span>
            <div>
                <h2>Todo lo que un operador necesita para responder rápido, en una sola pantalla.</h2>
                <p>Pensado para centrales de emergencias médicas: quién está disponible, qué caso espera unidad y cuánto falta para llegar al hospital.</p>
            </div>
            <a href="{{ url('/admin') }}" class="btn btn-outline" style="align-self:flex-start">Ver la demo <span class="arrow">↗</span></a>
        </div>

        <div class="zone zone-map">
            <svg viewBox="0 0 400 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <path d="M-20 300 C 80 260, 120 200, 200 190 S 330 120, 430 70" stroke="rgba(255,255,255,.22)" stroke-width="7" fill="none" stroke-linecap="round"/>
                <path d="M60 -10 C 90 90, 150 140, 200 190 S 260 330, 300 400" stroke="rgba(255,255,255,.14)" stroke-width="4" fill="none" stroke-linecap="round"/>
                <path d="M200 190 S 330 120, 430 70" stroke="#2dd4bf" stroke-width="3" fill="none" stroke-dasharray="8 8" stroke-linecap="round"/>
            </svg>
            <span class="tag">Mapa en vivo</span>
            <div style="position:absolute;left:46%;top:44%"><div class="u pulse" style="--c:var(--amber)">02</div></div>
            <div style="position:absolute;left:78%;top:20%"><div class="u pulse" style="--c:var(--red)">03</div></div>
            <div style="position:absolute;left:18%;top:68%"><div class="u" style="--c:var(--green)">01</div></div>
            <div style="position:absolute;left:62%;top:72%"><div class="u" style="--c:var(--blue)">04</div></div>
            <h3>Unidades y emergencias moviéndose en tiempo real</h3>
        </div>

        <div class="zone zone-list">
            <span class="tag">Servicios</span>
            <div class="rows">
                <div class="row"><b>SRV-0142</b><span class="badge" style="background:var(--red)">Emergencia</span><span class="badge" style="background:var(--blue)">En curso</span></div>
                <div class="row"><b>SRV-0141</b><span class="badge" style="background:var(--red)">Emergencia</span><span class="badge" style="background:var(--amber)">Asignado</span></div>
                <div class="row"><b>SRV-0139</b><span class="badge" style="background:#0e7490">Traslado</span><span class="badge" style="background:var(--green)">Completado</span></div>
            </div>
            <h3>Emergencias y traslados con prioridad y seguimiento</h3>
        </div>
    </section>

    <!-- ═══ Funciones ═══ -->
    <section class="bento" id="funciones">
        <div class="tile s2">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.4-2.7A1 1 0 013 16.4V4.6a1 1 0 011.4-.9L9 6m0 14l6-3m-6 3V6m6 11l4.6 2.3a1 1 0 001.4-.9V7.6a1 1 0 00-.6-.9L15 4m0 13V4m0 0L9 6"/></svg></div>
            <p>Rutas reales por carretera<small>Calculadas con OpenStreetMap y OSRM</small></p>
        </div>

        <div class="tile-hero tile">
            <svg class="mark" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <path d="M20 7c-5.8 0-10.5 4.5-10.5 10.2C9.5 24.6 20 33.5 20 33.5s10.5-8.9 10.5-16.3C30.5 11.5 25.8 7 20 7z" fill="#042f2b"/>
                <polyline points="13,18 16.5,18 18.5,13.5 21.5,22.5 23.5,18 27,18" stroke="#2dd4bf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            </svg>
            <h3>Rastreo + atención médica</h3>
            <p>Del aviso al hospital: cada etapa del servicio queda registrada con su hora.</p>
        </div>

        <div class="tile s1 tile-col"><div class="big-num">6</div><p style="font-size:1rem">estados por unidad</p></div>
        <div class="tile s1 tile-col"><div class="big-num">3′</div><p style="font-size:1rem">entre emergencias automáticas</p></div>

        <div class="tile s2">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg></div>
            <p>Asigna la unidad disponible más cercana<small>Por distancia real al incidente</small></p>
        </div>

        <div class="tile s1 tile-col"><div class="big-num">4</div><p style="font-size:1rem">centros de salud</p></div>
        <div class="tile s1 tile-col">
            <div class="ic" style="margin:0 auto"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg></div>
            <p style="font-size:1rem">Funciona en el teléfono</p>
        </div>

        <div class="tile s2">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg></div>
            <p>Mapa que se actualiza cada 3 segundos<small>Con movimiento suave de cada unidad</small></p>
        </div>
        <div class="tile s2">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/></svg></div>
            <p>Laravel 13 + Filament 5<small>Livewire, Leaflet y Laravel Cloud</small></p>
        </div>
        <div class="tile s2">
            <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/></svg></div>
            <p>Todo en español<small>Con modo claro y oscuro</small></p>
        </div>
    </section>

    <!-- ═══ Cómo usarlo ═══ -->
    <section class="how" id="como-usarlo">
        <div class="how-head">
            <h2>Conócelo en 5 minutos.</h2>
            <p>No hace falta instalar nada. Funciona en el navegador de la computadora o del teléfono.</p>
        </div>

        <div class="how-grid">
            <div class="how-card dark">
                <h3>Recorrido rápido</h3>
                <ol>
                    <li><span class="n">1</span><span>Presiona <b>Entrar a la demo</b>. El usuario de prueba ya viene escrito.</span></li>
                    <li><span class="n">2</span><span>Abre <b>Operaciones → Mapa en vivo</b> y espera unos segundos.</span></li>
                    <li><span class="n">3</span><span>Toca una unidad para ver su estado, paciente y destino.</span></li>
                    <li><span class="n">4</span><span>Espera una emergencia (punto rojo «!»): en unos 20 segundos se asigna la unidad más cercana.</span></li>
                    <li><span class="n">5</span><span>En <b>Servicios</b>, crea tu propio caso y asígnale una unidad verde. Vuelve al mapa y mírala salir.</span></li>
                </ol>
            </div>

            <div class="how-card">
                <h3>En el teléfono</h3>
                <ul>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></span><span>El menú está en el botón <b>☰</b>, arriba a la izquierda.</span></li>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span><span>Mueve el mapa con un dedo y haz zoom con dos.</span></li>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span><span>Desliza las tablas hacia los lados o gira el teléfono.</span></li>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8v8M8 12h8"/></svg></span><span>Agrégalo a la pantalla de inicio desde el menú del navegador.</span></li>
                </ul>
            </div>

            <div class="how-card">
                <h3>En la computadora</h3>
                <ul>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg></span><span>Contrae el menú lateral para agrandar el mapa.</span></li>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4-4"/></svg></span><span>Usa <b>Buscar</b> para encontrar una unidad o servicio, por ejemplo <b>SAR-03</b>.</span></li>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M4 5h16l-6 8v6l-4-2v-4z"/></svg></span><span>Filtra las tablas por tipo, prioridad o estado.</span></li>
                    <li><span class="b"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"><path d="M21 13A9 9 0 1111 3a7 7 0 0010 10z"/></svg></span><span>Cambia a modo claro u oscuro desde tu usuario.</span></li>
                </ul>
            </div>
        </div>

        <div class="states" id="estados">
            <div class="state" style="--c:var(--green)"><div class="sw"></div><b>Disponible</b><span>Libre para un servicio</span></div>
            <div class="state" style="--c:var(--amber)"><div class="sw"></div><b>En camino</b><span>Va hacia el incidente</span></div>
            <div class="state" style="--c:var(--red)"><div class="sw"></div><b>En escena</b><span>Atendiendo al paciente</span></div>
            <div class="state" style="--c:var(--blue)"><div class="sw"></div><b>Trasladando</b><span>Rumbo al hospital</span></div>
            <div class="state" style="--c:var(--violet)"><div class="sw"></div><b>En hospital</b><span>Entregando al paciente</span></div>
            <div class="state" style="--c:var(--gray)"><div class="sw"></div><b>Fuera de servicio</b><span>No disponible</span></div>
        </div>

        <section class="cta">
            <div>
                <h2>Abre el mapa y <span>míralo funcionar.</span></h2>
                <p style="color:rgba(255,255,255,.65);margin-top:1rem;max-width:44ch">Las unidades, pacientes e incidentes son ficticios. Los centros de salud son reales, con ubicación aproximada.</p>
            </div>
            <div class="cta-box">
                <dl>
                    <dt>Correo</dt><dd>demo@despacho.cr</dd>
                    <dt>Contraseña</dt><dd>demo1234</dd>
                </dl>
                <a href="{{ url('/admin') }}" class="btn btn-accent" style="width:100%;justify-content:center">Entrar a la demo <span class="arrow">↗</span></a>
            </div>
        </section>
    </section>

    <footer>
        <span>Desarrollado por <b>Kevin Vargas Cruz</b> · Laravel 13 + Filament 5</span>
        <a href="https://github.com/kvargas-devCruz/despacho-sarapiqui" target="_blank" rel="noopener">Ver el código en GitHub</a>
    </footer>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Consejos rotativos
    const tips = [
        'Toca una unidad en el mapa para ver qué servicio atiende y hacia dónde va.',
        'Las unidades que palpitan van en camino o están atendiendo una emergencia.',
        'El número rojo junto a «Servicios» indica cuántos casos esperan unidad.',
        'Crea un servicio, asígnale una unidad verde y mírala salir en el mapa.',
    ];
    let tipIndex = 0;
    const tipEl = document.getElementById('tip');
    const tipCount = document.getElementById('tip-count');
    const showTip = (i) => {
        tipIndex = (i + tips.length) % tips.length;
        tipEl.style.opacity = 0;
        setTimeout(() => { tipEl.textContent = tips[tipIndex]; tipEl.style.opacity = 1; }, 150);
        tipCount.textContent = `${tipIndex + 1} / ${tips.length}`;
    };
    document.querySelectorAll('[data-tip]').forEach((b) => b.addEventListener('click', () => { showTip(tipIndex + Number(b.dataset.tip)); clearInterval(tipTimer); }));
    const tipTimer = setInterval(() => showTip(tipIndex + 1), 6000);
    showTip(0);

    // Mapa de fondo de la portada (decorativo)
    if (window.L) {
        const map = L.map('hero-map', {
            zoomControl: false, attributionControl: true, dragging: false, scrollWheelZoom: false,
            doubleClickZoom: false, boxZoom: false, keyboard: false, touchZoom: false,
        }).setView([10.405, -84.02], 12);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap', maxZoom: 19,
        }).addTo(map);

        if (window.innerWidth > 900) map.panBy([-320, 0], { animate: false });

        // Unidades ilustrativas recorriendo la Ruta 4
        const route = [[10.4565, -84.0185], [10.4520, -84.0450], [10.4470, -84.0780], [10.4300, -84.0950], [10.4100, -84.1100], [10.3930, -84.1230]];
        const units = [
            { code: '01', color: '#16a34a', t: 0.05, speed: 0.0009, pulse: false },
            { code: '02', color: '#f59e0b', t: 0.40, speed: 0.0016, pulse: true },
            { code: '04', color: '#2563eb', t: 0.75, speed: -0.0012, pulse: false },
        ];
        const at = (t) => {
            const p = ((t % 1) + 1) % 1 * (route.length - 1);
            const i = Math.floor(p), k = p - i, a = route[i], b = route[Math.min(i + 1, route.length - 1)];
            return [a[0] + (b[0] - a[0]) * k, a[1] + (b[1] - a[1]) * k];
        };
        units.forEach((u) => {
            u.marker = L.marker(at(u.t), { icon: L.divIcon({ className: '', iconSize: [30, 30], iconAnchor: [15, 15],
                html: `<div class="u ${u.pulse ? 'pulse' : ''}" style="--c:${u.color}">${u.code}</div>` }) }).addTo(map);
        });
        L.marker([10.4285, -83.9800], { icon: L.divIcon({ className: '', iconSize: [30, 30], iconAnchor: [15, 15],
            html: '<div class="u pulse" style="--c:#dc2626">03</div>' }) }).addTo(map);
        L.marker([10.4568, -84.0175], { icon: L.divIcon({ className: '', iconSize: [24, 24], iconAnchor: [12, 12],
            html: '<div style="width:24px;height:24px;border-radius:6px;background:#fff;color:#dc2626;border:2px solid #dc2626;display:grid;place-items:center;font:800 13px/1 Inter,sans-serif">H</div>' }) }).addTo(map);

        const tick = () => { units.forEach((u) => { u.t += u.speed / 6; u.marker.setLatLng(at(u.t)); }); requestAnimationFrame(tick); };
        requestAnimationFrame(tick);
    }
</script>
</body>
</html>
