<?php

namespace Database\Seeders\Demo\Art;

use Database\Seeders\Demo\Support\Canvas;

/**
 * Illustrations and printables for "On-Call": dark, dense, monospace,
 * signal colours used semantically.
 */
class OnCallArt
{
    public const BG = '#0B0F14';
    public const PANEL = '#121821';
    public const BORDER = '#1E2733';
    public const TEXT = '#E6EDF3';
    public const MUTED = '#8B98A5';
    public const GREEN = '#3FB950';
    public const AMBER = '#D29922';
    public const RED = '#F85149';
    public const BLUE = '#58A6FF';

    public static function cover(string $kicker, string $title, string $subtitle, int $w = 1600, int $h = 900): Canvas
    {
        $c = new Canvas($w, $h, self::BG);
        $s = $w / 1600;
        for ($x = 0; $x < $w; $x += (int) (40 * $s)) {
            $c->line($x, 0, $x, $h, '#10161E', 1);
        }
        for ($y = 0; $y < $h; $y += (int) (40 * $s)) {
            $c->line(0, $y, $w, $y, '#10161E', 1);
        }
        $c->text('● on-call', 80 * $s, 70 * $s, 20 * $s, self::GREEN, 'mono', true);
        $c->text('⌘K', $w - 140 * $s, 70 * $s, 18 * $s, self::MUTED, 'mono');
        $c->text($kicker, 80 * $s, 230 * $s, 20 * $s, self::BLUE, 'mono');
        $y = $c->paragraph($title, 80 * $s, 270 * $s, 760 * $s, 50 * $s, self::TEXT, 'sans', true, 1.1);
        $c->paragraph($subtitle, 80 * $s, $y + 20 * $s, 700 * $s, 20 * $s, self::MUTED, 'mono', false, 1.5);
        self::timelinePanel($c, 900 * $s, 200 * $s, 620 * $s, 480 * $s, $s);
        $c->text('Priya Raman · Marek Lis', 80 * $s, $h - 90 * $s, 16 * $s, self::MUTED, 'mono');

        return $c;
    }

    private static function timelinePanel(Canvas $c, float $x, float $y, float $w, float $h, float $s): void
    {
        $c->rect($x, $y, $w, $h, self::PANEL);
        $c->frame($x, $y, $w, $h, self::BORDER, 2);
        $c->text('INC-0412 · checkout-api', $x + 24 * $s, $y + 22 * $s, 15 * $s, self::TEXT, 'mono', true);
        $c->roundRect($x + $w - 150 * $s, $y + 18 * $s, 120 * $s, 28 * $s, 4, self::RED, 90);
        $c->text('SEV1', $x + $w - 120 * $s, $y + 22 * $s, 15 * $s, self::RED, 'mono', true);
        $rows = [
            ['03:12', 'PagerDuty: 5xx > 5 % on /checkout', self::RED],
            ['03:14', 'SEV1 declared · IC assigned', self::AMBER],
            ['03:15', 'Roles: comms Ben · ops Aiko · scribe Chen', self::BLUE],
            ['03:19', 'Update #1 posted to status page', self::BLUE],
            ['03:24', 'Cause: v2.31 N+1 query, DB pool 100/100', self::AMBER],
            ['03:41', 'Rolled back to v2.30 · 5xx 0.4 %', self::GREEN],
            ['03:56', 'Mitigated · handover · postmortem due', self::GREEN],
        ];
        foreach ($rows as $i => [$time, $text, $color]) {
            $ry = $y + 80 * $s + $i * 54 * $s;
            $c->circle($x + 40 * $s, $ry + 10 * $s, 14 * $s, $color);
            if ($i < count($rows) - 1) {
                $c->line($x + 40 * $s, $ry + 18 * $s, $x + 40 * $s, $ry + 54 * $s, self::BORDER, 2);
            }
            $c->text($time, $x + 64 * $s, $ry, 14 * $s, self::MUTED, 'mono');
            $c->text($text, $x + 130 * $s, $ry, 14 * $s, self::TEXT, 'mono');
        }
    }

    /**
     * Grafana-style dashboard with an annotated alert.
     */
    public static function dashboard(): Canvas
    {
        $c = new Canvas(1600, 900, self::BG);
        $c->rect(0, 0, 1600, 56, self::PANEL);
        $c->line(0, 56, 1600, 56, self::BORDER, 1);
        $c->text('checkout-api / production', 24, 18, 16, self::TEXT, 'mono', true);
        $c->text('Last 1 hour · UTC · refresh 10s', 1250, 20, 13, self::MUTED, 'mono');
        $panels = [
            [24, 76, 760, 360, 'Request rate (req/s)', 'rate'],
            [808, 76, 768, 360, '5xx error rate (%)', 'errors'],
            [24, 456, 760, 360, 'Latency p50 / p99 (ms)', 'latency'],
            [808, 456, 768, 360, 'DB connection pool (used / 100)', 'pool'],
        ];
        foreach ($panels as [$x, $y, $w, $h, $title, $kind]) {
            self::panel($c, $x, $y, $w, $h, $title, $kind);
        }
        // annotations
        $notes = [
            [1120, 312, 1, 'A', 'Alert threshold: 5 % for 5 min'],
            [1279, 168, 2, 'B', 'Symptom: 18.4 % of checkouts fail'],
            [1010, 540, 3, 'C', 'Cause shows up here first: pool saturated'],
            [390, 545, 4, 'D', 'p99 explodes, p50 barely moves'],
            [300, 215, 5, 'E', 'Traffic is normal: not a load spike'],
        ];
        foreach ($notes as [$x, $y, , $letter, $text]) {
            $c->circle($x, $y, 30, self::AMBER);
            $c->centeredText($letter, $x, $y - 9, 14, self::BG, 'mono', true);
        }
        $c->rect(24, 828, 1552, 56, self::PANEL);
        $legend = 'A alert threshold · B user-facing symptom · C saturation = likely cause · D tail latency first · E rule out a traffic spike';
        $c->text($legend, 40, 846, 14, self::TEXT, 'mono');

        return $c;
    }

    private static function panel(Canvas $c, float $x, float $y, float $w, float $h, string $title, string $kind): void
    {
        $c->rect($x, $y, $w, $h, self::PANEL);
        $c->frame($x, $y, $w, $h, self::BORDER, 1);
        $c->text($title, $x + 16, $y + 12, 13, self::TEXT, 'mono', true);
        $px = $x + 56;
        $py = $y + 48;
        $pw = $w - 76;
        $ph = $h - 84;
        for ($i = 0; $i <= 4; $i++) {
            $c->line($px, $py + $ph * $i / 4, $px + $pw, $py + $ph * $i / 4, self::BORDER, 1);
        }
        foreach (['03:00', '03:10', '03:20', '03:30', '03:40', '03:50'] as $i => $label) {
            $c->text($label, $px + $pw * $i / 5 - 18, $py + $ph + 8, 11, self::MUTED, 'mono');
        }
        $incident = 0.24; // 03:12 as fraction of the panel
        $series = function (callable $f, int $n = 120) use ($px, $py, $pw, $ph) {
            $points = [];
            for ($i = 0; $i <= $n; $i++) {
                $t = $i / $n;
                $points[] = [$px + $pw * $t, $py + $ph * (1 - max(0, min(1, $f($t))))];
            }

            return $points;
        };
        $noise = fn (float $t, int $k = 1) => sin($t * 91 * $k) * 0.02 + sin($t * 37 * $k) * 0.015;
        switch ($kind) {
            case 'rate':
                $c->polyline($series(fn ($t) => 0.55 + $noise($t) + 0.05 * sin($t * 6)), self::GREEN, 2);
                $c->text('1.2k', $x + 12, $py - 6, 11, self::MUTED, 'mono');
                break;
            case 'errors':
                [, $ty] = [0, $py + $ph * (1 - 0.25)];
                $c->dashed($px, $ty, $px + $pw, $ty, self::RED, 6, 1);
                $c->text('threshold 5 %', $px + $pw - 110, $ty - 18, 11, self::RED, 'mono');
                $c->rect($px + $pw * ($incident + 0.04), $py, $pw * 0.38, $ph, self::RED, 115);
                $c->polyline($series(fn ($t) => $t < 0.12 ? 0.02 + abs($noise($t)) : ($t < 0.7 ? min(0.92, 0.02 + ($t - 0.12) * 4) : 0.03)), self::RED, 2);
                break;
            case 'latency':
                $c->polyline($series(fn ($t) => 0.12 + $noise($t, 2)), self::BLUE, 2);
                $c->polyline($series(fn ($t) => $t < 0.12 ? 0.22 + $noise($t) : ($t < 0.7 ? min(0.95, 0.22 + ($t - 0.12) * 3) : 0.2)), self::AMBER, 2);
                $c->text('p50', $px + 8, $py + $ph * 0.82, 11, self::BLUE, 'mono');
                $c->text('p99', $px + 8, $py + $ph * 0.66, 11, self::AMBER, 'mono');
                break;
            default:
                $c->dashed($px, $py, $px + $pw, $py, self::RED, 6, 1);
                $c->polyline($series(fn ($t) => $t < 0.1 ? 0.4 + $noise($t) : ($t < 0.7 ? min(1, 0.4 + ($t - 0.1) * 6) : 0.38)), self::AMBER, 2);
                $c->text('max 100', $px + $pw - 70, $py + 6, 11, self::RED, 'mono');
        }
    }

    /** 960x540 frame of a terminal-style film. */
    public static function filmFrame(string $kicker, string $title, array $lines = [], string $accent = self::BLUE): Canvas
    {
        $c = new Canvas(960, 540, self::BG);
        $c->rect(0, 0, 960, 34, self::PANEL);
        foreach ([self::RED, self::AMBER, self::GREEN] as $i => $dot) {
            $c->circle(20 + $i * 20, 17, 11, $dot);
        }
        $c->text('on-call — ' . mb_strtolower($kicker), 90, 9, 12, self::MUTED, 'mono');
        $c->text('$ ' . $kicker, 48, 70, 15, $accent, 'mono', true);
        $y = $c->paragraph($title, 48, 104, 860, 32, self::TEXT, 'sans', true, 1.15);
        $y += 10;
        foreach ($lines as $line) {
            [$color, $text] = is_array($line) ? $line : [self::MUTED, $line];
            $y = $c->paragraph($text, 48, $y, 860, 15, $color, 'mono', false, 1.35);
        }
        $c->text('▌', 48, $y + 4, 15, self::GREEN, 'mono');

        return $c;
    }

    public static function postmortemTimeline(): Canvas
    {
        $c = new Canvas(1400, 520, self::BG);
        $c->text('Game-day outage · timeline', 40, 30, 26, self::TEXT, 'sans', true);
        $c->text('detection → mitigation is what customers feel; everything after is learning', 40, 72, 14, self::MUTED, 'mono');
        $events = [
            [0, '03:05', 'v2.31 deployed', self::MUTED],
            [7, '03:12', 'alert fires', self::RED],
            [9, '03:14', 'SEV1 declared', self::AMBER],
            [14, '03:19', 'first update', self::BLUE],
            [19, '03:24', 'cause found', self::AMBER],
            [36, '03:41', 'rollback done', self::GREEN],
            [51, '03:56', 'all-clear', self::GREEN],
        ];
        $x0 = 80;
        $scale = 1220 / 51;
        $y = 300;
        $c->rect($x0 + 7 * $scale, $y - 60, 29 * $scale, 120, self::RED, 110);
        $c->centeredText('time to mitigate: 29 min (alert → rollback)', $x0 + 21.5 * $scale, $y - 150, 15, self::RED, 'mono', true);
        $c->line($x0, $y, $x0 + 1220, $y, self::BORDER, 3);
        foreach ($events as $i => [$m, $time, $label, $color]) {
            $x = $x0 + $m * $scale;
            $c->circle($x, $y, 22, $color);
            $up = $i % 2 === 0;
            $c->line($x, $y, $x, $up ? $y + 50 : $y - 50, self::BORDER, 1);
            $c->centeredText($time, $x, $up ? $y + 58 : $y - 82, 14, self::TEXT, 'mono', true);
            $c->centeredText($label, $x, $up ? $y + 80 : $y - 60, 13, self::MUTED, 'mono');
        }
        $c->text('TTD 7 min (deploy → alert)   ·   TTA 2 min   ·   TTM 29 min   ·   ≈ 15 % of the monthly error budget (SLO 99.9 %)', 40, 470, 14, self::TEXT, 'mono');

        return $c;
    }

    // ------------------------------------------------------------- printables

    private static function pdfStyle(): string
    {
        return '<style>
            @page { margin: 18mm 16mm; }
            body { font-family: "DejaVu Sans", sans-serif; color: #111820; font-size: 9.5pt; line-height: 1.45; }
            h1 { font-size: 24pt; margin: 0 0 2pt; } h2 { font-size: 13pt; margin: 14pt 0 6pt; border-bottom: 1.5pt solid #111820; padding-bottom: 3pt; }
            .mono, code, pre { font-family: "DejaVu Sans Mono", monospace; }
            pre { background: #F1F4F7; border-left: 3pt solid ' . self::BLUE . '; padding: 8pt; font-size: 8.5pt; white-space: pre-wrap; }
            .kicker { font-family: "DejaVu Sans Mono"; font-size: 8pt; color: #5A6672; letter-spacing: 1pt; }
            .page { page-break-after: always; }
            table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
            th { background: #111820; color: #fff; text-align: left; padding: 4pt 5pt; }
            td { border-bottom: 0.5pt solid #C9D1D9; padding: 5pt; vertical-align: top; }
            .sev1 { color: #B62324; font-weight: bold; } .sev2 { color: #9A6700; font-weight: bold; } .sev3 { color: #0969DA; font-weight: bold; } .sev4 { color: #57606A; font-weight: bold; }
            .box { border: 0.8pt solid #C9D1D9; padding: 6pt 8pt; margin: 4pt 0; }
            .check { font-family: "DejaVu Sans"; }
        </style>';
    }

    public static function runbookHtml(): string
    {
        $html = '<html><head>' . self::pdfStyle() . '</head><body>';
        $html .= '<div class="page"><div class="kicker">ON-CALL · MODULE 2 · PRINT AND KEEP NEXT TO YOUR LAPTOP</div><h1>Incident runbook</h1>
            <p>Version 3.2 · owner: platform reliability · review every quarter</p>
            <h2>Severity matrix</h2>
            <table><tr><th>Level</th><th>Definition</th><th>Examples</th><th>Response</th></tr>
            <tr><td class="sev1">SEV1</td><td>Customer-facing outage or data loss</td><td>Checkout down, login failing for &gt;10 % of users, data corruption</td><td>Page IC + on-call now, 24/7. Updates every 30 min. Exec notified.</td></tr>
            <tr><td class="sev2">SEV2</td><td>Major degradation, no full outage</td><td>p99 latency 5×, one region down with failover, payments delayed</td><td>Page on-call 24/7. Updates every 60 min.</td></tr>
            <tr><td class="sev3">SEV3</td><td>Minor impact, workaround exists</td><td>Export job failing, one feature degraded for some users</td><td>Business hours. Ticket + channel.</td></tr>
            <tr><td class="sev4">SEV4</td><td>Cosmetic, no user impact</td><td>Typo in an email, a noisy but harmless alert</td><td>Backlog.</td></tr></table>
            <p><b>When in doubt, declare higher.</b> Downgrading is cheap; a late SEV1 is not.</p>
            <h2>Roles</h2>
            <table><tr><th>Role</th><th>Owns</th><th>Does not</th></tr>
            <tr><td><b>Incident commander (IC)</b></td><td>Decisions, priorities, roles, the clock</td><td>Debug or type commands</td></tr>
            <tr><td><b>Communications lead</b></td><td>Status page, stakeholder updates, customer support</td><td>Speculate about causes in public</td></tr>
            <tr><td><b>Operations lead</b></td><td>Investigation and mitigation, directs responders</td><td>Talk to executives</td></tr>
            <tr><td><b>Scribe</b></td><td>Timeline with timestamps, decisions, open questions</td><td>Edit history later</td></tr></table></div>';
        $html .= '<div class="page"><div class="kicker">THE FIRST FIVE MINUTES</div><h1>Take command</h1>
            <table><tr><th style="width:8%">✓</th><th style="width:16%">Minute</th><th>Action</th></tr>
            <tr><td>☐</td><td>0–1</td><td>Acknowledge the page. Say on the bridge: <i>"I am the incident commander."</i></td></tr>
            <tr><td>☐</td><td>1–2</td><td>Declare the severity. Open <code>#inc-&lt;id&gt;</code> and the bridge. Pin the dashboard.</td></tr>
            <tr><td>☐</td><td>2–3</td><td>Assign comms, ops lead and scribe by name. Confirm each one out loud.</td></tr>
            <tr><td>☐</td><td>3–4</td><td>Ask: <i>What is the impact? Since when? What changed?</i> (deploys, flags, config, traffic)</td></tr>
            <tr><td>☐</td><td>4–5</td><td>Set the next checkpoint: <i>"We regroup at 03:30."</i> Comms posts the first update.</td></tr></table>
            <h2>Radio discipline</h2>
            <ul><li>Address people by name and role: <i>"Aiko, ops: please check the deploy log."</i></li>
            <li>Close the loop: <i>"Done, deploy log shows v2.31 at 03:05."</i></li>
            <li>One conversation on the bridge. Side investigations go to threads.</li>
            <li>No blame, no guessing in the channel. Facts, actions, owners, times.</li>
            <li>The IC can say <i>"Hold. Let\'s reset."</i> at any time.</li></ul>
            <h2>Mitigate before you debug</h2>
            <p>Prefer reversible, well-known actions: roll back, fail over, disable a feature flag, shed load, scale out. Only then look for the root cause.</p></div>';
        $html .= '<div class="page"><div class="kicker">COMMUNICATIONS TEMPLATES</div><h1>What to say</h1>
            <h2>Status page: investigating</h2><pre>[Investigating] Checkout errors
We are seeing errors for some customers completing a purchase since 03:07 UTC.
Our engineers are investigating. Next update by 03:45 UTC.</pre>
            <h2>Stakeholder update (internal)</h2><pre>SEV1 · INC-0412 · update #2 · 03:41 UTC
Impact:   ~18 % of checkouts failing since 03:07 (≈ 2,300 orders)
Current:  rolled back checkout-api to v2.30; errors back to 0.4 %
Next:     watching for 15 min, then all-clear
IC: Priya · Comms: Ben · Next update: 04:00 UTC</pre>
            <h2>Status page: resolved</h2><pre>[Resolved] Checkout errors
Between 03:07 and 03:41 UTC some customers could not complete a purchase.
We reverted a change and the service is working normally.
We will publish a summary of what happened within 5 working days.</pre>
            <h2>Handover note</h2><pre>What happened:
Current state:
Open risks:
Follow-ups (owner, due):
Postmortem owner and date:</pre></div>';
        $html .= '<div><div class="kicker">CONTACTS AND LINKS · FILL IN FOR YOUR TEAM</div><h1>Your rotation</h1>
            <table><tr><th>What</th><th>Where</th></tr>';
        foreach (['Paging tool / escalation policy', 'Incident channel naming', 'Bridge link', 'Status page admin', 'Main dashboards', 'Deploy log', 'Feature flags', 'Database on-call', 'Security on-call', 'Vendor support (cloud, CDN, payments)', 'Executive on-call'] as $row) {
            $html .= "<tr><td>$row</td><td style=\"height:16pt\"></td></tr>";
        }
        $html .= '</table></div></body></html>';

        return $html;
    }

    public static function postmortemTemplateHtml(): string
    {
        return '<html><head>' . self::pdfStyle() . '</head><body>
            <div class="kicker">ON-CALL · MODULE 4 · BLAMELESS POSTMORTEM TEMPLATE</div><h1>Postmortem: &lt;title&gt;</h1>
            <table><tr><td style="width:30%"><b>Incident</b></td><td>INC-____ · SEV_</td></tr><tr><td><b>Date / duration</b></td><td></td></tr>
            <tr><td><b>Authors</b></td><td></td></tr><tr><td><b>Status</b></td><td>draft / in review / final</td></tr></table>
            <h2>Summary</h2><div class="box" style="height:50pt">Two or three sentences a customer could understand.</div>
            <h2>Impact</h2><div class="box" style="height:40pt">Who was affected, how many, for how long, what it cost (orders, SLO budget).</div>
            <h2>Timeline (UTC)</h2><table><tr><th style="width:18%">Time</th><th>Event</th></tr><tr><td></td><td></td></tr><tr><td></td><td></td></tr><tr><td></td><td></td></tr><tr><td></td><td></td></tr><tr><td></td><td></td></tr></table>
            <h2>Contributing factors</h2><div class="box" style="height:60pt">Technical, process and organisational conditions that made the incident possible or worse. No names.</div>
            <h2>What went well · where we got lucky</h2><div class="box" style="height:40pt"></div>
            <h2>Action items</h2><table><tr><th>Action</th><th>Type</th><th>Owner</th><th>Due</th></tr><tr><td></td><td>prevent / detect / mitigate</td><td></td><td></td></tr><tr><td></td><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td><td></td></tr></table>
        </body></html>';
    }
}
