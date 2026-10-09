import React, { useEffect, useState } from "react";
import { Helmet } from "react-helmet";
import { Link, useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import { useBareLayout } from "@/components/_App/bareLayout";
import { InPageLink, SkipLink } from "../shared/InPageLink";
import { useMenu } from "../shared/useMenu";
import { FormatIcon } from "../shared/FormatIcon";
import type { FormatKey } from "../shared/formats";
import { formatDayMonth, formatTime } from "../shared/format";
import { fullName, useLandingData, webinarHref } from "../shared/useLandingData";
import { ONCALL } from "./content";
import styles from "./OncallLanding.module.css";

const MONO_FONT =
  "https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap";

const isMac = () =>
  typeof navigator !== "undefined" &&
  /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

/** "0. Briefing" → "Briefing": the API numbers lessons, the design shows MOD 00 instead. */
const stripNumber = (title: string) => title.replace(/^\s*\d+\s*[.)]\s*/, "");

const pad2 = (n: number) => String(n).padStart(2, "0");

const initials = (name: string) =>
  name
    .split(" ")
    .map((part) => part[0])
    .join("");

/** Logo mark: a heartbeat line in a square, as in the Stitch top bar. */
const LogoMark: React.FC = () => (
  <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
    <rect x="1.5" y="1.5" width="21" height="21" rx="4" fill="none" stroke="currentColor" strokeWidth="1.5" />
    <path
      d="M4 13h3.5l2-5 3.5 9 2.5-6 1.5 2H20"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

/** p99 latency: flat baseline, spike at 03:12, recovery by 03:41. */
const LatencyChart: React.FC = () => (
  <svg className={styles.chart} viewBox="0 0 400 90" preserveAspectRatio="none" aria-hidden="true" focusable="false">
    <line x1="0" y1="70" x2="400" y2="70" className={styles.chartGrid} />
    <line x1="0" y1="40" x2="400" y2="40" className={styles.chartGrid} />
    <path
      className={styles.chartArea}
      d="M0 72 L90 71 L130 70 L170 30 L205 14 L240 22 L275 18 L300 44 L320 70 L400 71 L400 90 L0 90 Z"
    />
    <path
      className={styles.chartLine}
      d="M0 72 L90 71 L130 70 L170 30 L205 14 L240 22 L275 18 L300 44 L320 70 L400 71"
    />
    <circle cx="320" cy="70" r="4" className={styles.chartDot} />
  </svg>
);

const OncallLanding: React.FC = () => {
  useBareLayout();
  const history = useHistory();
  const data = useLandingData(ONCALL.courseTitleHint);
  const menu = useMenu();
  const brand = data.companyName || ONCALL.brand;
  const [modKey, setModKey] = useState("Ctrl");
  const [voucher, setVoucher] = useState("");

  useEffect(() => setModKey(isMac() ? "⌘" : "Ctrl"), []);

  // ⌘K / Ctrl+K opens the course search; ⌘↵ / Ctrl+Enter opens the application.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (!(event.metaKey || event.ctrlKey)) return;
      if (event.key.toLowerCase() === "k") {
        event.preventDefault();
        history.push(routeRoutes.courses);
      } else if (event.key === "Enter") {
        event.preventDefault();
        history.push(data.courseHref);
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [history, data.courseHref]);

  const modules = data.lessons.length
    ? data.lessons.map((lesson, i) => {
        const fallback = ONCALL.modules[i];
        const topics = lesson.topics.length
          ? lesson.topics.map((topic) => ({ title: topic.title, format: topic.format }))
          : fallback?.topics ?? [];
        return {
          title: stripNumber(lesson.title),
          week: fallback?.week ?? `Week ${Math.min(4, i + 1)}`,
          blurb: lesson.summary || fallback?.blurb || "",
          formula: fallback?.formula,
          topics,
          tags: lesson.formats.length ? lesson.formats : fallback?.tags ?? [],
        };
      })
    : ONCALL.modules;
  const topicTotal = modules.reduce((sum, m) => sum + m.topics.length, 0);

  // Real tutor names (and avatars) from the API; the Stitch portraits for the seeded instructors.
  const instructors = ONCALL.instructors.map((fallback, i) => {
    const tutor =
      data.tutors.find((t) => fullName(t) === fallback.name) ??
      data.tutors.filter((t) => !ONCALL.instructors.some((f) => f.name === fullName(t)))[i];
    if (!tutor) return { ...fallback, photo: fallback.photo as string | null };
    const name = fullName(tutor) || fallback.name;
    return {
      ...fallback,
      name,
      photo: (name === fallback.name ? fallback.photo : tutor.url_avatar || null) as string | null,
    };
  });

  const webinar = data.webinars[0];
  const webinarStart = (webinar as { active_from?: string } | undefined)?.active_from;
  const webinarDate = formatDayMonth(webinarStart) ?? ONCALL.webinar.date;
  const webinarTime = webinarStart ? `${formatTime(webinarStart)}` : ONCALL.webinar.time;

  const consultation = data.consultations[0];
  const consultationHref = consultation
    ? routeRoutes.consultation.replace(":id", String(consultation.id))
    : routeRoutes.consultations;

  const { taken, seats, date } = ONCALL.hero.cohort;
  const seatsLeft = seats - taken;
  const individualPrice = data.coursePrice ?? ONCALL.pricing.individual.price;

  const onVoucher = (event: React.FormEvent) => {
    event.preventDefault();
    history.push({ pathname: routeRoutes.cart, state: { voucher: voucher.trim() } });
  };

  const navLinks: Array<[string, string]> = [
    ["curriculum", "Curriculum"],
    ["cohort", "Cohorts"],
    ["simulator", "Live drills"],
    ["instructors", "Instructors"],
    ["pricing", "Pricing & teams"],
  ];

  return (
    <div className={styles.page}>
      <Helmet>
        <html lang="en" />
        <title>{`${brand} — ${ONCALL.hero.title}`}</title>
        <meta name="description" content={ONCALL.hero.sub} />
        <meta name="theme-color" content="#0B0F14" />
        <link rel="stylesheet" href={MONO_FONT} />
      </Helmet>
      <SkipLink className={styles.skip} />

      <header className={styles.topbar}>
        <div className={styles.topbarInner}>
          <Link to={routeRoutes.home} className={styles.logo}>
            <LogoMark />
            <span>{brand}</span>
          </Link>
          <p className={styles.statusPill}>
            <span className={styles.dot} data-level="green" aria-hidden="true" />
            {ONCALL.status}
          </p>
          <nav aria-label="Main" className={styles.nav}>
            <button className={styles.menuButton} {...menu.buttonProps}>
              Menu
            </button>
            <ul className={styles.navList} {...menu.menuProps}>
              {navLinks.map(([id, label]) => (
                <li key={id}>
                  <InPageLink to={id} onClick={menu.close}>
                    {label}
                  </InPageLink>
                </li>
              ))}
              <li className={styles.navSignIn}>
                <Link to={routeRoutes.login}>Sign in</Link>
              </li>
            </ul>
          </nav>
          <Link to={routeRoutes.courses} className={styles.search}>
            <span>Search courses</span>
            <kbd className={styles.kbd} aria-hidden="true">
              {modKey}K
            </kbd>
          </Link>
          <Link to={routeRoutes.login} className={styles.signIn}>
            Sign in
          </Link>
          <Link to={data.courseHref} className={styles.buttonPrimary}>
            Apply
          </Link>
        </div>
      </header>

      <div id="cohort" className={styles.ticker}>
        <div className={styles.tickerInner}>
          <p className={styles.tickerLeft}>
            <span className={styles.tag} data-level="green">
              {ONCALL.ticker.tag}
            </span>
            <span className={styles.tickerText}>{ONCALL.ticker.text}</span>
          </p>
          <p className={styles.tickerRight}>
            <span>
              {ONCALL.hero.cohort.label}: {date}
            </span>
            <span>
              {taken}/{seats} seats committed
            </span>
            <span className={styles.tag} data-level="blue">
              {ONCALL.ticker.drill}
            </span>
          </p>
        </div>
      </div>

      <main id="landing-main" className={styles.main}>
        {/* Hero */}
        <section className={styles.hero} aria-labelledby="oncall-hero-title">
          <div className={styles.heroText}>
            <p className={styles.kicker}>
              <FormatIcon format="scorm" size={14} />
              {ONCALL.hero.kicker}
            </p>
            <h1 id="oncall-hero-title" className={styles.heroTitle}>
              {ONCALL.hero.title}
            </h1>
            <p className={styles.heroSub}>{ONCALL.hero.sub}</p>
            <p className={styles.heroSummary}>{data.course?.summary || ONCALL.hero.summary}</p>
            <div className={styles.actions}>
              <Link to={data.courseHref} className={styles.buttonPrimary}>
                Apply for the November cohort
                <kbd className={styles.kbdInline} aria-hidden="true">
                  {modKey}↵
                </kbd>
              </Link>
              <InPageLink to="curriculum" className={styles.buttonSecondary}>
                Explore the curriculum
              </InPageLink>
            </div>
            <dl className={styles.stats}>
              {ONCALL.hero.stats.map((stat) => (
                <div key={stat.label} className={styles.stat}>
                  <dt>{stat.label}</dt>
                  <dd className={styles.statValue} data-level={stat.level}>
                    {stat.value}
                  </dd>
                  <dd className={styles.statNote}>{stat.note}</dd>
                </div>
              ))}
              <div className={styles.stat}>
                <dt>Next cohort limit</dt>
                <dd className={styles.statValue} data-level="amber">
                  {seatsLeft} seats left
                </dd>
                <dd className={styles.statNote}>Capped at {seats} total</dd>
              </div>
            </dl>
            <figure className={styles.heroImage}>
              <img
                src={ONCALL.hero.image.src}
                alt={ONCALL.hero.image.alt}
                width={1408}
                height={768}
                loading="eager"
              />
              <figcaption>
                <span>{ONCALL.hero.image.caption}</span>
                <span className={styles.green}>{ONCALL.hero.image.status}</span>
              </figcaption>
            </figure>
          </div>

          <figure className={styles.incident} aria-labelledby="oncall-incident-caption">
            <div className={styles.incidentHead}>
              <p className={styles.incidentTitle}>
                <span className={styles.dot} data-level="red" aria-hidden="true" />
                <span className={styles.red}>{ONCALL.incident.title}</span>
                <span className={styles.muted}>{ONCALL.incident.service}</span>
              </p>
              <time className={styles.mono}>{ONCALL.incident.time}</time>
            </div>
            <dl className={styles.roles}>
              {ONCALL.incident.roles.map(([role, who]) => (
                <div key={role}>
                  <dt>{role}:</dt>
                  <dd>{who}</dd>
                </div>
              ))}
            </dl>
            <div className={styles.telemetry}>
              <p className={styles.telemetryHead}>
                <span>{ONCALL.incident.telemetry.label}</span>
                <span className={styles.green}>{ONCALL.incident.telemetry.status}</span>
              </p>
              <LatencyChart />
              <p className={styles.telemetryFoot}>
                <span>{ONCALL.incident.telemetry.baseline}</span>
                <span className={styles.red}>{ONCALL.incident.telemetry.peak}</span>
                <span className={styles.green}>{ONCALL.incident.telemetry.mitigated}</span>
              </p>
            </div>
            <ol className={styles.timeline}>
              {ONCALL.incident.events.map((event) => (
                <li key={event.t} className={styles.timelineRow}>
                  <time className={styles.timelineTime}>{event.t}</time>
                  <span className={styles.timelineLabel} data-level={event.level}>
                    [{event.label}]
                  </span>
                  <span>{event.text}</span>
                </li>
              ))}
            </ol>
            <p className={styles.commandLine}>
              <span className={styles.green} aria-hidden="true">
                $
              </span>
              <code>
                <span className={styles.blue}>{ONCALL.incident.command.cmd}</span>{" "}
                {ONCALL.incident.command.args}
              </code>
            </p>
            <figcaption id="oncall-incident-caption" className={styles.visuallyHidden}>
              Example incident timeline from the game-day simulator: SEV-1 declared at 03:12,
              mitigated at 03:41.
            </figcaption>
          </figure>
        </section>

        {/* Curriculum */}
        <section id="curriculum" className={styles.section} aria-labelledby="oncall-curriculum-title">
          <div className={styles.sectionHeadRow}>
            <div>
              <p className={styles.eyebrow}>{ONCALL.curriculum.kicker}</p>
              <h2 id="oncall-curriculum-title" className={styles.h2}>
                {ONCALL.curriculum.title}
              </h2>
              <p className={styles.sectionSub}>
                {modules.length} modules, {topicTotal} topics with real artifacts — and a live game
                day in week 3.
              </p>
            </div>
            <p className={styles.metaRight}>{ONCALL.curriculum.meta}</p>
          </div>
          <ol className={styles.modules}>
            {modules.map((module, i) => (
              <li key={module.title} className={styles.module}>
                <p className={styles.moduleHead}>
                  <span className={styles.blue}>MOD {pad2(i)}</span>
                  <span>{module.week}</span>
                </p>
                <h3 className={styles.h3}>{module.title}</h3>
                {module.blurb && <p className={styles.moduleBlurb}>{module.blurb}</p>}
                {module.formula && <code className={styles.formula}>{module.formula}</code>}
                <ul className={styles.topicList} aria-label="Topics">
                  {module.topics.map((topic) => (
                    <li key={topic.title}>
                      {topic.format && (
                        <FormatIcon format={topic.format as FormatKey} size={15} className={styles.topicIcon} />
                      )}
                      {topic.title}
                    </li>
                  ))}
                </ul>
                <ul className={styles.tags} aria-label="Formats">
                  {module.tags.map((format) => (
                    <li key={format}>{ONCALL.formatLabels[format]}</li>
                  ))}
                </ul>
              </li>
            ))}
          </ol>
        </section>

        {/* Outage simulator */}
        <section id="simulator" className={styles.section} aria-labelledby="oncall-sim-title">
          <p className={styles.eyebrow}>{ONCALL.simulator.kicker}</p>
          <h2 id="oncall-sim-title" className={styles.h2}>
            {ONCALL.simulator.title}
          </h2>
          <p className={styles.sectionSub}>{ONCALL.simulator.sub}</p>

          <figure className={styles.sim} aria-labelledby="oncall-sim-caption">
            <div className={styles.simBar}>
              <p>
                <span className={styles.blue}>{ONCALL.simulator.session}</span>
                <span className={styles.muted}>{ONCALL.simulator.target}</span>
              </p>
              <p className={styles.tag} data-level="red">
                {ONCALL.simulator.injected}
              </p>
            </div>
            <div className={styles.simGrid}>
              <div className={styles.simPanel}>
                <p className={styles.simPanelHead}>
                  <span>{ONCALL.simulator.pods.label}</span>
                  <span className={styles.red}>{ONCALL.simulator.pods.status}</span>
                </p>
                <ul className={styles.pods}>
                  {ONCALL.simulator.pods.rows.map((pod) => (
                    <li key={pod.name}>
                      <span>{pod.name}</span>
                      <span data-level={pod.level} className={styles.podStatus}>
                        {pod.status}
                      </span>
                    </li>
                  ))}
                </ul>
                <p className={styles.gaugeLabel}>{ONCALL.simulator.pods.gauge.label}</p>
                <div
                  className={styles.gauge}
                  role="meter"
                  aria-label={ONCALL.simulator.pods.gauge.label}
                  aria-valuemin={0}
                  aria-valuemax={100}
                  aria-valuenow={ONCALL.simulator.pods.gauge.value}
                >
                  <span style={{ width: `${ONCALL.simulator.pods.gauge.value}%` }} />
                </div>
                <p className={styles.gaugeFoot}>
                  <span>{ONCALL.simulator.pods.gauge.value}% core saturation</span>
                  <span className={styles.red}>{ONCALL.simulator.pods.gauge.note}</span>
                </p>
              </div>

              <div className={styles.simPanel}>
                <p className={styles.simPanelHead}>
                  <span>{ONCALL.simulator.chat.channel}</span>
                  <span className={styles.green}>{ONCALL.simulator.chat.responders}</span>
                </p>
                <ol className={styles.chat} aria-label="Incident channel">
                  {ONCALL.simulator.chat.lines.map((line, i) => (
                    <li key={i}>
                      <b className={styles.blue}>{line.who}:</b> {line.text}
                    </li>
                  ))}
                </ol>
                <p className={styles.prompt}>
                  <span className={styles.green} aria-hidden="true">
                    $
                  </span>
                  <code>{ONCALL.simulator.chat.prompt}</code>
                </p>
              </div>

              <div className={styles.simPanel}>
                <p className={styles.simPanelHead}>
                  <span>Benchmark performance</span>
                </p>
                <dl className={styles.bench}>
                  {ONCALL.simulator.bench.map((b) => (
                    <div key={b.label}>
                      <dt>{b.label}</dt>
                      <dd className={styles.benchValue} data-level={b.level}>
                        {b.value}
                      </dd>
                      <dd className={styles.statNote}>{b.note}</dd>
                    </div>
                  ))}
                </dl>
                <p className={styles.benchNote}>{ONCALL.simulator.footnote}</p>
              </div>
            </div>
            <figcaption id="oncall-sim-caption" className={styles.visuallyHidden}>
              The outage simulator: pod telemetry, the war-room channel and drill benchmarks.
            </figcaption>
          </figure>
        </section>

        {/* Instructors */}
        <section id="instructors" className={styles.section} aria-labelledby="oncall-instructors-title">
          <p className={styles.eyebrow}>{ONCALL.instructorsIntro.kicker}</p>
          <h2 id="oncall-instructors-title" className={styles.h2}>
            {ONCALL.instructorsIntro.title}
          </h2>
          <p className={styles.sectionSub}>{ONCALL.instructorsIntro.sub}</p>
          <ul className={styles.instructors}>
            {instructors.map((person) => (
              <li key={person.name} className={styles.instructor}>
                {person.photo ? (
                  <img
                    src={person.photo}
                    alt={`Portrait of ${person.name}`}
                    className={styles.portrait}
                    loading="lazy"
                    width={112}
                    height={112}
                  />
                ) : (
                  <span className={styles.portrait} aria-hidden="true">
                    {initials(person.name)}
                  </span>
                )}
                <div className={styles.instructorBody}>
                  <div className={styles.instructorHead}>
                    <h3 className={styles.h3}>{person.name}</h3>
                    <span className={styles.roleTag}>{person.role}</span>
                  </div>
                  <p className={styles.affiliation}>{person.affiliation}</p>
                  <p className={styles.bio}>&ldquo;{person.bio}&rdquo;</p>
                  <p className={styles.instructorStats}>{person.stats.join(" · ")}</p>
                </div>
              </li>
            ))}
          </ul>
        </section>

        {/* Consultations + webinar */}
        <section className={styles.bookRow} aria-label="Live sessions">
          <div className={styles.panel}>
            <p className={styles.eyebrow}>{ONCALL.review.kicker}</p>
            <h2 className={styles.h3Large}>{ONCALL.review.title}</h2>
            <p className={styles.sectionSub}>{ONCALL.review.text}</p>
            <p className={styles.slotsLabel} id="oncall-slots-label">
              {ONCALL.review.slotsLabel}
            </p>
            <ul className={styles.slots} aria-labelledby="oncall-slots-label">
              {ONCALL.review.slots.map((slot) => (
                <li key={slot.when}>
                  <Link to={consultationHref} className={styles.slot}>
                    <span className={styles.slotWhen}>{slot.when}</span>
                    <span className={styles.slotWho}>{slot.who}</span>
                  </Link>
                </li>
              ))}
            </ul>
            <div className={styles.inlineActions}>
              <Link to={consultationHref} className={styles.buttonSecondary}>
                <FormatIcon format="tracked" size={15} />
                {ONCALL.review.cta}
              </Link>
              <span className={styles.muted}>{ONCALL.review.note}</span>
            </div>
          </div>
          <div className={styles.panel}>
            <p className={styles.webinarHead}>
              <span className={styles.tag} data-level="red">
                {ONCALL.webinar.tag}
              </span>
              <span className={styles.muted}>{ONCALL.webinar.label}</span>
            </p>
            <h2 className={styles.h3}>{webinar?.name || ONCALL.webinar.title}</h2>
            <p className={styles.sectionSub}>{ONCALL.webinar.text}</p>
            <p className={styles.webinarDate}>
              <span>Date: {webinarDate}</span>
              <span className={styles.blue}>{webinarTime}</span>
            </p>
            <Link to={webinarHref(webinar)} className={`${styles.buttonPrimary} ${styles.block}`}>
              {ONCALL.webinar.cta} <span aria-hidden="true">→</span>
            </Link>
          </div>
        </section>

        {/* Reviews */}
        <section className={styles.section} aria-labelledby="oncall-reviews-title">
          <p className={styles.eyebrow}>{ONCALL.reviews.kicker}</p>
          <h2 id="oncall-reviews-title" className={styles.h2}>
            {ONCALL.reviews.title}
          </h2>
          <p className={styles.sectionSub}>{ONCALL.reviews.sub}</p>
          <ol className={styles.log}>
            {ONCALL.reviews.lines.map((line) => (
              <li key={line.ts}>
                <figure className={styles.logLine}>
                  <span className={styles.logTs}>
                    [<time dateTime={line.ts}>{line.ts}</time>]
                  </span>
                  <span className={styles.green}>[REVIEW]</span>
                  <blockquote className={styles.logQuote}>
                    <p>&ldquo;{line.text}&rdquo;</p>
                  </blockquote>
                  <figcaption className={styles.logWho}>— {line.who}</figcaption>
                </figure>
              </li>
            ))}
          </ol>
        </section>

        {/* Pricing */}
        <section id="pricing" className={styles.sectionCentered} aria-labelledby="oncall-pricing-title">
          <p className={styles.eyebrow}>{ONCALL.pricing.kicker}</p>
          <h2 id="oncall-pricing-title" className={styles.h2}>
            {ONCALL.pricing.title}
          </h2>
          <p className={styles.sectionSub}>{ONCALL.pricing.sub}</p>
          <ul className={styles.plans}>
            {[
              { plan: ONCALL.pricing.individual, price: individualPrice, featured: false, href: data.courseHref },
              { plan: ONCALL.pricing.team, price: ONCALL.pricing.team.price, featured: true, href: routeRoutes.register },
            ].map(({ plan, price, featured, href }) => (
              <li key={plan.name} className={styles.plan} data-featured={featured ? "true" : undefined}>
                {"badge" in plan && <p className={styles.planBadge}>{plan.badge as string}</p>}
                <p className={styles.planHead}>
                  <span className={featured ? styles.blue : undefined}>{plan.name}</span>
                  <span>{plan.meta}</span>
                </p>
                <h3 className={styles.visuallyHidden}>{plan.name}</h3>
                <p className={styles.price}>
                  <span className={featured ? styles.blue : undefined}>{price}</span>{" "}
                  <span className={styles.priceUnit}>{plan.unit}</span>
                </p>
                <p className={styles.planText}>{plan.text}</p>
                <ul className={styles.checks}>
                  {plan.items.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
                <Link to={href} className={featured ? styles.buttonPrimary : styles.buttonSecondary}>
                  {plan.cta}
                </Link>
              </li>
            ))}
          </ul>
          <form className={styles.voucher} onSubmit={onVoucher}>
            <label htmlFor="oncall-voucher">{ONCALL.pricing.voucher.label}</label>
            <div className={styles.voucherRow}>
              <input
                id="oncall-voucher"
                type="text"
                autoComplete="off"
                spellCheck={false}
                placeholder={ONCALL.pricing.voucher.placeholder}
                value={voucher}
                onChange={(e) => setVoucher(e.target.value)}
              />
              <button type="submit" className={styles.buttonSecondary}>
                {ONCALL.pricing.voucher.cta}
              </button>
            </div>
          </form>
        </section>

        {/* FAQ */}
        <section className={styles.faqSection} aria-labelledby="oncall-faq-title">
          <p className={styles.eyebrow}>{ONCALL.faq.kicker}</p>
          <h2 id="oncall-faq-title" className={styles.h2}>
            {ONCALL.faq.title}
          </h2>
          <div className={styles.faq}>
            {ONCALL.faq.items.map((item, i) => (
              <details key={item.q} className={styles.faqItem} open={i === 0}>
                <summary>{item.q}</summary>
                <p>{item.a}</p>
              </details>
            ))}
          </div>
        </section>

        {/* Final CTA */}
        <section className={styles.ctaBand} aria-labelledby="oncall-cta-title">
          <div>
            <p className={styles.eyebrowGreen}>{ONCALL.cta.kicker}</p>
            <h2 id="oncall-cta-title" className={styles.h3Large}>
              {ONCALL.cta.title}
            </h2>
            <p className={styles.sectionSub}>{ONCALL.cta.text}</p>
          </div>
          <div className={styles.inlineActions}>
            <Link to={data.courseHref} className={styles.buttonPrimary}>
              Claim a cohort seat ({individualPrice})
            </Link>
            <InPageLink to="curriculum" className={styles.buttonSecondary}>
              {ONCALL.cta.secondary}
            </InPageLink>
          </div>
        </section>
      </main>

      <footer className={styles.footer}>
        <div className={styles.footerTop}>
          <p className={styles.footerPills}>
            <span className={styles.footerPill}>
              <span className={styles.dot} data-level="green" aria-hidden="true" />
              {ONCALL.footer.mesh}
            </span>
            <span className={styles.footerPill}>{ONCALL.footer.sla}</span>
          </p>
          <p className={styles.footerCurl}>
            <code>$ curl -s /api/courses | jq .</code>
          </p>
        </div>
        <div className={styles.footerBottom}>
          <p className={styles.copy}>
            © {new Date().getFullYear()} {brand}. {ONCALL.footer.copy}
          </p>
          <nav aria-label="Footer">
            <ul className={styles.footerLinks}>
              <li>
                <Link to={routeRoutes.courses}>Courses</Link>
              </li>
              <li>
                <Link to={routeRoutes.register}>Create account</Link>
              </li>
              <li>
                <Link to={routeRoutes.login}>Sign in</Link>
              </li>
              <li>
                <Link to={routeRoutes.privacyPolicy}>Privacy</Link>
              </li>
            </ul>
          </nav>
        </div>
      </footer>
    </div>
  );
};

export default OncallLanding;
