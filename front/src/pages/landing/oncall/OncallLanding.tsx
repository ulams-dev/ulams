import React, { useEffect } from "react";
import { Helmet } from "react-helmet";
import { Link, useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import { useBareLayout } from "@/components/_App/bareLayout";
import { InPageLink, SkipLink } from "../shared/InPageLink";
import { useMenu } from "../shared/useMenu";
import { formatDayMonth, formatTime } from "../shared/format";
import { fullName, useLandingData, webinarHref } from "../shared/useLandingData";
import { ONCALL } from "./content";
import styles from "./OncallLanding.module.css";

const MONO_FONT =
  "https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap";

const isMac = () =>
  typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

const OncallLanding: React.FC = () => {
  useBareLayout();
  const history = useHistory();
  const data = useLandingData(ONCALL.courseTitleHint);
  const menu = useMenu();
  const brand = data.companyName || ONCALL.brand;
  const modKey = isMac() ? "⌘" : "Ctrl";

  // ⌘K / Ctrl+K opens the course search, like the tools engineers already use.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();
        history.push(routeRoutes.courses);
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [history]);

  const modules = data.lessons.length
    ? data.lessons.map((lesson, i) => ({
        title: lesson.title,
        week: ONCALL.modules[i]?.week ?? `Module ${i}`,
        topics: [] as string[],
        formats: lesson.formats.length ? lesson.formats : ONCALL.modules[i]?.formats ?? [],
        topicCount: lesson.topicCount,
      }))
    : ONCALL.modules.map((m) => ({ ...m, topicCount: m.topics.length }));

  const instructors = ONCALL.instructors.map((fallback, i) => {
    const tutor = data.tutors[i];
    return tutor
      ? { ...fallback, name: fullName(tutor) || fallback.name, avatar: tutor.url_avatar }
      : { ...fallback, avatar: null as string | null };
  });

  const webinar = data.webinars[0];
  const webinarStart = (webinar as { active_from?: string } | undefined)?.active_from;
  const webinarWhen = webinarStart
    ? `${formatDayMonth(webinarStart)} · ${formatTime(webinarStart)}`
    : ONCALL.webinar.when;
  const consultation = data.consultations[0];
  const consultationHref = consultation
    ? routeRoutes.consultation.replace(":id", String(consultation.id))
    : routeRoutes.consultations;

  const { taken, seats, date } = ONCALL.hero.cohort;
  const prices = ONCALL.pricing.map((plan, i) =>
    i === 0 && data.coursePrice ? { ...plan, price: data.coursePrice } : plan
  );

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
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
              <path
                d="M2 13h4l2.5-6 4 11 3-8 1.5 3H22"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
            {brand}
          </Link>
          <nav aria-label="Main" className={styles.nav}>
            <button className={styles.menuButton} {...menu.buttonProps}>
              menu
            </button>
            <ul className={styles.navList} {...menu.menuProps}>
              <li>
                <InPageLink to="curriculum" onClick={menu.close}>
                  Curriculum
                </InPageLink>
              </li>
              <li>
                <InPageLink to="cohort" onClick={menu.close}>
                  Cohorts
                </InPageLink>
              </li>
              <li>
                <InPageLink to="gameday" onClick={menu.close}>
                  Drills
                </InPageLink>
              </li>
              <li>
                <InPageLink to="pricing" onClick={menu.close}>
                  Pricing
                </InPageLink>
              </li>
              <li>
                <InPageLink to="pricing" onClick={menu.close}>
                  Teams
                </InPageLink>
              </li>
              <li>
                <Link to={routeRoutes.login}>Sign in</Link>
              </li>
            </ul>
          </nav>
          <Link to={routeRoutes.courses} className={styles.search}>
            <span>Search</span>
            <kbd className={styles.kbd} aria-label={`${modKey === "⌘" ? "Command" : "Control"} K`}>
              {modKey}K
            </kbd>
          </Link>
          <Link to={data.courseHref} className={styles.buttonPrimary}>
            Apply
          </Link>
        </div>
      </header>

      <main id="landing-main" className={styles.main}>
        <section className={styles.hero} aria-labelledby="oncall-hero-title">
          <div className={styles.heroText}>
            <p className={styles.pill} data-level="green">
              <span className={styles.dot} aria-hidden="true" />
              {ONCALL.hero.status}
            </p>
            <h1 id="oncall-hero-title" className={styles.heroTitle}>
              {ONCALL.hero.title}
            </h1>
            <p className={styles.heroSub}>{ONCALL.hero.sub}</p>
            <p className={styles.heroSummary}>{data.course?.summary || ONCALL.hero.summary}</p>
            <div className={styles.actions}>
              <Link to={data.courseHref} className={styles.buttonPrimary}>
                Apply for the November cohort
              </Link>
              <InPageLink to="curriculum" className={styles.buttonSecondary}>
                View curriculum
              </InPageLink>
            </div>
          </div>

          <figure className={styles.panel} aria-labelledby="oncall-incident-caption">
            <div className={styles.panelHead}>
              <span className={styles.mono}>
                {ONCALL.incident.id} · {ONCALL.incident.service}
              </span>
              <span className={styles.pill} data-level="green">
                <span className={styles.dot} aria-hidden="true" />
                resolved
              </span>
            </div>
            <svg className={styles.spark} viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
              <polyline points={ONCALL.metrics[0].points} />
            </svg>
            <ol className={styles.timeline}>
              {ONCALL.incident.events.map((event) => (
                <li key={event.t} className={styles.timelineRow} data-level={event.level}>
                  <time className={styles.mono}>{event.t}</time>
                  <span className={styles.tag} data-level={event.level}>
                    {event.label}
                  </span>
                  <span>{event.text}</span>
                </li>
              ))}
            </ol>
            <figcaption id="oncall-incident-caption" className={styles.caption}>
              Example incident timeline from the game-day simulator: declared at 03:12, mitigated at
              03:41.
            </figcaption>
          </figure>
        </section>

        <section id="cohort" className={styles.status} aria-labelledby="oncall-status-title">
          <h2 id="oncall-status-title" className={styles.visuallyHidden}>
            Cohort status
          </h2>
          <dl className={styles.statusGrid}>
            <div>
              <dt>{ONCALL.hero.cohort.label}</dt>
              <dd>
                <span className={styles.dot} data-level="green" aria-hidden="true" /> {date}
              </dd>
            </div>
            <div>
              <dt>Seats</dt>
              <dd>
                {taken}/{seats} taken
                <span className={styles.meter} aria-hidden="true">
                  <span style={{ width: `${(taken / seats) * 100}%` }} />
                </span>
              </dd>
            </div>
            <div>
              <dt>Live drills</dt>
              <dd>Thursdays 18:00 CET</dd>
            </div>
            <div>
              <dt>Format</dt>
              <dd>4 weeks · 5 modules</dd>
            </div>
          </dl>
        </section>

        <section id="curriculum" className={styles.section} aria-labelledby="oncall-curriculum-title">
          <div className={styles.sectionHead}>
            <p className={styles.eyebrow}>curriculum</p>
            <h2 id="oncall-curriculum-title" className={styles.h2}>
              Five modules, one pager
            </h2>
          </div>
          <ol className={styles.curriculum}>
            {modules.map((module, i) => (
              <li key={module.title} className={styles.module}>
                <span className={styles.node} aria-hidden="true" />
                <p className={styles.mono}>
                  MOD {i} · {module.week}
                </p>
                <h3 className={styles.h3}>{module.title}</h3>
                {module.topics.length > 0 ? (
                  <ul className={styles.topics}>
                    {module.topics.map((topic) => (
                      <li key={topic}>{topic}</li>
                    ))}
                  </ul>
                ) : (
                  <p className={styles.muted}>{module.topicCount} topics</p>
                )}
                <ul className={styles.chips} aria-label="Formats">
                  {module.formats.map((format) => (
                    <li key={format}>{ONCALL.formatLabels[format]}</li>
                  ))}
                </ul>
              </li>
            ))}
          </ol>
        </section>

        <section id="gameday" className={styles.section} aria-labelledby="oncall-gameday-title">
          <div className={styles.split}>
            <div className={styles.sectionHead}>
              <p className={styles.eyebrow}>game day</p>
              <h2 id="oncall-gameday-title" className={styles.h2}>
                Command a cascading failure
              </h2>
              <p className={styles.muted}>
                In week 3 the simulator breaks a payments stack while you run the bridge. Metrics,
                service map and the incident channel update in real time; tutors grade the replay.
              </p>
              <ul className={styles.checks}>
                <li>Declare roles in the first five minutes</li>
                <li>Post stakeholder updates on a cadence</li>
                <li>Mitigate first, investigate later</li>
              </ul>
            </div>
            <figure className={styles.window} aria-labelledby="oncall-sim-caption">
              <div className={styles.windowBar} aria-hidden="true">
                <span />
                <span />
                <span />
                <span className={styles.mono}>outage-simulator — drill #7</span>
              </div>
              <div className={styles.windowBody}>
                <div className={styles.metrics}>
                  {ONCALL.metrics.map((metric) => (
                    <div key={metric.name} className={styles.metric} data-level={metric.level}>
                      <span className={styles.mono}>{metric.name}</span>
                      <strong className={styles.metricValue}>{metric.value}</strong>
                      <svg viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points={metric.points} />
                      </svg>
                    </div>
                  ))}
                  <ul className={styles.services}>
                    {ONCALL.services.map((service) => (
                      <li key={service.name}>
                        <span className={styles.mono}>{service.name}</span>
                        <span className={styles.tag} data-level={service.level}>
                          {service.status}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
                <ol className={styles.chat} aria-label="Incident channel">
                  {ONCALL.chat.map((line) => (
                    <li key={line.t + line.who}>
                      <span className={styles.mono}>
                        [{line.t}] <b>{line.who}</b> ({line.role})
                      </span>
                      <span>{line.text}</span>
                    </li>
                  ))}
                </ol>
              </div>
              <figcaption id="oncall-sim-caption" className={styles.caption}>
                The outage simulator: live metrics, service health and the incident channel.
              </figcaption>
            </figure>
          </div>
        </section>

        <section className={styles.section} aria-labelledby="oncall-instructors-title">
          <div className={styles.sectionHead}>
            <p className={styles.eyebrow}>instructors</p>
            <h2 id="oncall-instructors-title" className={styles.h2}>
              Taught by people who hold the pager
            </h2>
          </div>
          <ul className={styles.instructors}>
            {instructors.map((person) => (
              <li key={person.name} className={styles.card}>
                <div className={styles.person}>
                  {person.avatar ? (
                    <img src={person.avatar} alt="" className={styles.avatar} />
                  ) : (
                    <span className={styles.avatar} aria-hidden="true">
                      {person.name
                        .split(" ")
                        .map((p) => p[0])
                        .join("")}
                    </span>
                  )}
                  <div>
                    <h3 className={styles.h3}>{person.name}</h3>
                    <p className={styles.muted}>{person.role}</p>
                  </div>
                </div>
                <dl className={styles.stats}>
                  {person.stats.map(([label, value]) => (
                    <div key={label}>
                      <dt>{label}</dt>
                      <dd>{value}</dd>
                    </div>
                  ))}
                </dl>
              </li>
            ))}
          </ul>
        </section>

        <section id="pricing" className={styles.section} aria-labelledby="oncall-pricing-title">
          <div className={styles.sectionHead}>
            <p className={styles.eyebrow}>pricing · teams</p>
            <h2 id="oncall-pricing-title" className={styles.h2}>
              Seats, teams and vouchers
            </h2>
          </div>
          <ul className={styles.plans}>
            {prices.map((plan, i) => (
              <li key={plan.name} className={styles.card} data-featured={i === 0 ? "true" : undefined}>
                <h3 className={styles.h3}>{plan.name}</h3>
                <p className={styles.price}>
                  {plan.price} <span className={styles.muted}>{plan.unit}</span>
                </p>
                <ul className={styles.checks}>
                  {plan.items.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
                <Link
                  to={i === 0 ? data.courseHref : routeRoutes.register}
                  className={i === 0 ? styles.buttonPrimary : styles.buttonSecondary}
                >
                  {plan.cta}
                  <span className={styles.visuallyHidden}> — {plan.name}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>

        <section className={styles.section} aria-labelledby="oncall-book-title">
          <div className={styles.twoUp}>
            <div className={styles.card}>
              <p className={styles.eyebrow}>1:1 consultations</p>
              <h2 id="oncall-book-title" className={styles.h3}>
                Book an on-call review
              </h2>
              <p className={styles.muted}>
                45 minutes with a tutor on your rotation, alerts and runbooks.
              </p>
              <ul className={styles.slots} aria-label="Next free slots">
                {ONCALL.slots.map((slot) => (
                  <li key={slot}>
                    <Link to={consultationHref} className={styles.slot}>
                      {slot}
                      <span className={styles.visuallyHidden}> — book this slot</span>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
            <div className={styles.card}>
              <p className={styles.eyebrow}>
                <span className={styles.dot} data-level="red" aria-hidden="true" /> live webinar
              </p>
              <h2 className={styles.h3}>{webinar?.name || ONCALL.webinar.title}</h2>
              <p className={styles.mono}>{webinarWhen}</p>
              <p className={styles.muted}>
                A real postmortem, taken apart line by line. Bring questions.
              </p>
              <Link to={webinarHref(webinar)} className={styles.buttonSecondary}>
                Save a seat
              </Link>
            </div>
          </div>
        </section>

        <section className={styles.section} aria-labelledby="oncall-quotes-title">
          <div className={styles.sectionHead}>
            <p className={styles.eyebrow}>tail -f testimonials.log</p>
            <h2 id="oncall-quotes-title" className={styles.h2}>
              From engineering leads
            </h2>
          </div>
          <ol className={styles.log}>
            {ONCALL.testimonials.map((line) => (
              <li key={line.ts}>
                <figure className={styles.logLine}>
                  <figcaption className={styles.mono}>
                    <time dateTime={line.ts}>{line.ts}</time> <span className={styles.logWho}>[{line.who}]</span>
                  </figcaption>
                  <blockquote>
                    <p>&ldquo;{line.text}&rdquo;</p>
                  </blockquote>
                </figure>
              </li>
            ))}
          </ol>
        </section>

        <section className={styles.section} aria-labelledby="oncall-faq-title">
          <div className={styles.sectionHead}>
            <p className={styles.eyebrow}>faq</p>
            <h2 id="oncall-faq-title" className={styles.h2}>
              Questions
            </h2>
          </div>
          <div className={styles.faq}>
            {ONCALL.faq.map((item) => (
              <details key={item.q} className={styles.faqItem}>
                <summary>{item.q}</summary>
                <p>{item.a}</p>
              </details>
            ))}
          </div>
        </section>
      </main>

      <footer className={styles.footer}>
        <p className={styles.mono}>
          <span className={styles.dot} data-level="green" aria-hidden="true" /> All systems
          operational · {brand}
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
      </footer>
    </div>
  );
};

export default OncallLanding;
