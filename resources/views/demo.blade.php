<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portfolio Pricing Layer</title>
    <style>
        :root {
            --bg: #f6f5f1;
            --panel: #ffffff;
            --ink: #1d1d1b;
            --muted: #6b6a64;
            --line: #e3e1da;
            --accent: #2a5bd7;
            --fill: #9a4a07;
            --fill-soft: #fdf1e2;
            --hold: #4b5563;
            --hold-soft: #eef0f2;
            --protect: #166534;
            --protect-soft: #e6f4ea;
            --booked-soft: #f0efea;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #151513;
                --panel: #1e1e1b;
                --ink: #ecebe6;
                --muted: #a3a29b;
                --line: #33332e;
                --accent: #7aa2ff;
                --fill: #f59e0b;
                --fill-soft: #35270f;
                --hold: #9ca3af;
                --hold-soft: #262a2f;
                --protect: #4ade80;
                --protect-soft: #173222;
                --booked-soft: #262623;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 15px/1.5 ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        main, footer { max-width: 1080px; margin: 0 auto; padding-inline: 16px; }
        main { padding-block: 32px 0; }
        h1 { font-size: 28px; margin: 0 0 6px; letter-spacing: -0.01em; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin: 0 0 8px; }
        p { margin: 0; }
        .lede { color: var(--muted); max-width: 720px; }

        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; }
        .stack { display: grid; gap: 16px; margin-top: 24px; }
        .controls { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end; }
        .control > label { display: flex; justify-content: space-between; font-weight: 600; margin-bottom: 6px; }
        fieldset.control { border: 0; padding: 0; margin: 0; min-width: 0; }
        .control legend { font-weight: 600; margin-bottom: 6px; padding: 0; }
        .control output { font-weight: 400; color: var(--muted); }
        input[type="range"] { width: 100%; accent-color: var(--accent); }
        .day-type { display: flex; gap: 8px; }
        .day-type label { flex: 1; display: block; border: 1px solid var(--line); border-radius: 8px; padding: 6px 10px; text-align: center; cursor: pointer; }
        .day-type input { position: absolute; opacity: 0; }
        .day-type label:has(input:checked) { border-color: var(--accent); color: var(--accent); font-weight: 600; }
        .day-type label:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }

        .answers { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .answer p { font-size: 17px; }
        .badge { display: inline-block; font-size: 12px; font-weight: 600; border-radius: 999px; padding: 2px 10px; margin-top: 8px; }
        .badge.fill { color: var(--fill); background: var(--fill-soft); }
        .badge.hold, .badge.pass_through { color: var(--hold); background: var(--hold-soft); }
        .badge.protect { color: var(--protect); background: var(--protect-soft); }
        .figures { color: var(--muted); font-size: 13px; margin-top: 6px; }

        .comparison { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .comparison .after { border-color: var(--accent); }

        .units { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
        .unit {
            font: inherit; color: inherit; text-align: left; cursor: pointer;
            background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px;
        }
        .unit.booked { background: var(--booked-soft); color: var(--muted); }
        .unit.leader { border-color: var(--fill); background: var(--fill-soft); }
        .unit[aria-pressed="true"] { outline: 2px solid var(--accent); outline-offset: 1px; }
        .unit .name { font-weight: 600; display: block; }
        .unit .state { display: block; font-size: 12px; color: var(--muted); }
        .unit.leader .state { color: var(--fill); font-weight: 600; }
        .unit .prices { display: block; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .unit .was { color: var(--muted); text-decoration: line-through; margin-right: 6px; }
        .unit .off { color: var(--fill); font-size: 12px; font-weight: 600; margin-left: 4px; }
        .strength { display: block; height: 4px; background: var(--line); border-radius: 2px; margin-top: 8px; overflow: hidden; }
        .strength span { display: block; height: 100%; background: var(--accent); }
        .reason { margin-top: 12px; }
        .reason strong { display: block; margin-bottom: 2px; }

        .curve svg { width: 100%; max-width: 520px; height: auto; display: block; }
        .curve text { font-size: 10px; fill: currentColor; fill-opacity: 0.7; }
        .curve .caption { color: var(--muted); font-size: 13px; margin-top: 6px; }
        .error { color: var(--fill); font-weight: 600; }
        footer { padding-block: 28px 48px; color: var(--muted); font-size: 13px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        footer a { color: var(--accent); }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.17.2/dist/cdn.min.js" integrity="sha384-lcaMFHdvRVsEXVuhit4fTnbxq6eTLm5HPdNzO7vXNZjr8HOCMouPmv4hSGF3PCJV" crossorigin="anonymous"></script>
</head>
<body>
<main x-data="pricingDemo($el.dataset)" x-init="load()" data-endpoint="{{ route('demo.scenario', absolute: false) }}" data-max-lead-time="{{ $maxLeadTimeDays }}" data-pace-curve="{{ json_encode($paceCurve) }}">
    <header>
        <h1>Portfolio Pricing Layer</h1>
        <p class="lede">An unofficial prototype of a read-only portfolio layer on top of per-unit dynamic prices. Twelve comparable apartments, one night: instead of discounting every empty unit, the layer picks a few price leaders and holds the rest.</p>
    </header>

    <section class="panel stack" aria-label="Scenario">
        <div class="controls">
            <div class="control">
                <label for="booked">Booked units <output x-text="`${booked} of {{ $unitCount }}`"></output></label>
                <input id="booked" type="range" min="0" max="{{ $unitCount }}" x-model.number="booked" x-on:input="load()">
            </div>
            <div class="control">
                <label for="lead-time">Lead time <output x-text="leadTime === 0 ? 'today' : `${leadTime} ${leadTime === 1 ? 'day' : 'days'} out`"></output></label>
                <input id="lead-time" type="range" min="0" max="{{ $maxLeadTimeDays }}" x-model.number="leadTime" x-on:input="load()">
            </div>
            <fieldset class="control">
                <legend>Night</legend>
                <div class="day-type">
                    <label><input type="radio" name="day-type" value="weekend" x-model="dayType" x-on:change="load()">Saturday</label>
                    <label><input type="radio" name="day-type" value="weekday" x-model="dayType" x-on:change="load()">Sunday</label>
                </div>
            </fieldset>
        </div>
    </section>

    <p class="error" x-show="error" x-text="error" role="alert"></p>

    <template x-if="scenario">
        <div class="stack">
            <section class="answers">
                <article class="panel answer">
                    <h2>Where are we?</h2>
                    <p x-text="scenario.answers.where" aria-live="polite"></p>
                    <span class="badge" :class="scenario.assessment.mode" x-text="scenario.assessment.mode_label"></span>
                    <p class="figures" x-text="`Occupancy ${percent(scenario.assessment.occupancy)}, target ${percent(scenario.assessment.target)}`"></p>
                </article>
                <article class="panel answer">
                    <h2>What are we doing?</h2>
                    <p x-text="scenario.answers.what"></p>
                    <p class="figures" x-show="scenario.assessment.mode === 'fill'" x-text="`Leader budget ${scenario.assessment.leader_budget}, group discount rate ${percent(scenario.assessment.discount_rate)}`"></p>
                </article>
                <article class="panel answer">
                    <h2>Why these units?</h2>
                    <p x-text="scenario.answers.why"></p>
                </article>
            </section>

            <section class="comparison" aria-label="Before and after">
                <article class="panel">
                    <h2>Without the portfolio layer</h2>
                    <p x-text="scenario.comparison.without_layer"></p>
                </article>
                <article class="panel after">
                    <h2>With the portfolio layer</h2>
                    <p x-text="scenario.comparison.with_layer"></p>
                </article>
            </section>

            <section class="panel" aria-label="Units">
                <h2>Units</h2>
                <div class="units">
                    <template x-for="unit in scenario.units" :key="unit.id">
                        <button type="button" class="unit" :class="{ booked: unit.status === 'booked', leader: unit.rule === 'price_leader' }" :aria-pressed="selected === unit.id" x-on:click="selected = unit.id">
                            <span class="name" x-text="unit.name"></span>
                            <span class="state" x-text="unitState(unit)"></span>
                            <span class="prices">
                                <span class="was" x-show="unit.recommended_price_cents !== unit.base_price_cents" x-text="money(unit.base_price_cents)"></span>
                                <span x-text="money(unit.recommended_price_cents)"></span>
                                <span class="off" x-show="unit.recommended_price_cents < unit.base_price_cents" x-text="discountLabel(unit)"></span>
                            </span>
                            <span class="strength" aria-hidden="true"><span :style="`width: ${Math.min(100, unit.booking_strength * 60)}%`"></span></span>
                        </button>
                    </template>
                </div>
                <div class="reason" x-show="selectedUnit" aria-live="polite">
                    <strong x-text="selectedUnit ? `${selectedUnit.name}, booking strength ${selectedUnit.booking_strength.toFixed(2)}` : ''"></strong>
                    <p x-text="selectedUnit ? selectedUnit.reason : ''"></p>
                </div>
            </section>

            <section class="panel curve" aria-label="Pace curve">
                <h2>Pace target</h2>
                <svg viewBox="0 0 360 170" role="img" :aria-label="`Pace curve, target ${percent(scenario.assessment.target)} at ${scenario.lead_time_days} days out`">
                    <line x1="44" y1="140" x2="340" y2="140" stroke="currentColor" stroke-opacity="0.2"></line>
                    <line x1="44" y1="20" x2="44" y2="140" stroke="currentColor" stroke-opacity="0.2"></line>
                    <text x="44" y="158">{{ $maxLeadTimeDays }} days out</text>
                    <text x="340" y="158" text-anchor="end">today</text>
                    <text x="38" y="24" text-anchor="end">100%</text>
                    <text x="38" y="143" text-anchor="end">0%</text>
                    <polyline fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-dasharray="4 3" :points="curvePoints('weekday')"></polyline>
                    <polyline fill="none" stroke="var(--accent)" stroke-width="2" :points="curvePoints('weekend')"></polyline>
                    <circle r="5" fill="var(--fill)" :cx="curveX(scenario.lead_time_days)" :cy="curveY(scenario.assessment.target)"></circle>
                    <circle r="4" fill="none" stroke="var(--ink)" stroke-width="1.5" :cx="curveX(scenario.lead_time_days)" :cy="curveY(scenario.assessment.occupancy)"></circle>
                </svg>
                <p class="caption">Solid line: Saturday target. Dashed line: Sunday target. Filled dot: target for this night. Ring: current occupancy. Synthetic configuration for this prototype, not real booking data.</p>
            </section>
        </div>
    </template>
</main>

<footer>
    <span>Built with Laravel 13. Ten invariants are tested over 2,000 seeded random portfolios.</span>
    <a href="https://github.com/mateusgetulio/portfolio-pricing-layer">Source on GitHub</a>
    <a href="https://github.com/mateusgetulio/portfolio-pricing-layer/actions/workflows/ci.yml"><img src="https://github.com/mateusgetulio/portfolio-pricing-layer/actions/workflows/ci.yml/badge.svg" alt="CI status" height="20"></a>
</footer>

<script>
    function pricingDemo(settings) {
        const endpoint = settings.endpoint;
        const maxLeadTimeDays = Number(settings.maxLeadTime);
        const paceCurve = JSON.parse(settings.paceCurve);
        let pending = null;

        return {
            booked: 8,
            leadTime: 8,
            dayType: 'weekend',
            scenario: null,
            selected: null,
            error: null,

            async load() {
                pending?.abort();
                const request = pending = new AbortController();
                const query = new URLSearchParams({ booked: this.booked, lead_time: this.leadTime, day_type: this.dayType });

                try {
                    const response = await fetch(`${endpoint}?${query}`, { headers: { Accept: 'application/json' }, signal: request.signal });
                    const payload = response.ok ? await response.json() : null;

                    if (request !== pending) {
                        return;
                    }

                    if (payload === null) {
                        this.error = 'This scenario could not be priced.';

                        return;
                    }

                    this.error = null;
                    this.scenario = payload.data;
                    this.selected = this.selectedUnit?.id ?? this.defaultUnit()?.id ?? null;
                } catch {
                    if (request === pending) {
                        this.error = 'The pricing service is not reachable.';
                    }
                }
            },

            get selectedUnit() {
                return this.scenario?.units.find((unit) => unit.id === this.selected) ?? null;
            },

            defaultUnit() {
                const units = this.scenario.units;

                return units.find((unit) => unit.leader_rank === 1) ?? units.find((unit) => unit.status === 'available') ?? units[0];
            },

            unitState(unit) {
                if (unit.status !== 'available') {
                    return unit.status === 'booked' ? 'Booked' : 'Blocked';
                }

                return unit.rule === 'price_leader' ? `Leader ${unit.leader_rank}` : 'Held';
            },

            discountLabel(unit) {
                const percent = Math.round(unit.discount * 100);

                return percent > 0 ? `-${percent}%` : 'under 1% off';
            },

            money(cents) {
                return '$' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: cents % 100 ? 2 : 0, maximumFractionDigits: 2 });
            },

            percent(fraction) {
                return `${Math.round(fraction * 100)}%`;
            },

            curveX(leadTimeDays) {
                return 44 + (1 - Math.min(leadTimeDays, maxLeadTimeDays) / maxLeadTimeDays) * 296;
            },

            curveY(share) {
                return 140 - share * 120;
            },

            curvePoints(dayType) {
                const first = paceCurve[0];
                const last = paceCurve[paceCurve.length - 1];

                return [{ ...first, lead_time_days: 0 }, ...paceCurve, { ...last, lead_time_days: maxLeadTimeDays }]
                    .map((point) => `${this.curveX(point.lead_time_days)},${this.curveY(point[dayType])}`)
                    .join(' ');
            },
        };
    }
</script>
</body>
</html>
