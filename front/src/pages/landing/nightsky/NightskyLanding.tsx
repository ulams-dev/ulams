import React from "react";
import { Helmet } from "react-helmet";
import { Link } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import { useBareLayout } from "@/components/_App/bareLayout";
import { InPageLink, SkipLink } from "../shared/InPageLink";
import { useMenu } from "../shared/useMenu";
import { FormatIcon } from "../shared/FormatIcon";
import type { FormatKey } from "../shared/formats";
import { formatDayMonth, formatMoney } from "../shared/format";
import { useLandingData, webinarHref } from "../shared/useLandingData";
import { MissionActivity, NIGHTSKY } from "./content";
import styles from "./NightskyLanding.module.css";

const NUNITO =
  "https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap";

const ACTIVITY_FORMAT: Record<MissionActivity, FormatKey> = {
  watch: "video",
  look: "image",
  play: "interactive",
  print: "pdf",
  read: "reading",
  listen: "audio",
  explore: "embed",
  game: "scorm",
  challenge: "tracked",
  quiz: "quiz",
  make: "project",
};

const FORMAT_ACTIVITY: Record<FormatKey, MissionActivity> = {
  video: "watch",
  image: "look",
  interactive: "play",
  pdf: "print",
  reading: "read",
  audio: "listen",
  embed: "explore",
  scorm: "game",
  tracked: "challenge",
  quiz: "quiz",
  project: "make",
};

const COLORS = ["yellow", "lilac", "coral", "yellow", "mint", "lilac", "coral"] as const;

/** Orbi, the robot guide, waving from a rocket. */
const OrbiRocket: React.FC = () => (
  <svg className={styles.orbi} viewBox="0 0 320 360" role="img" aria-labelledby="orbi-title">
    <title id="orbi-title">Orbi the robot waving from the window of a small rocket</title>
    <g className={styles.flame}>
      <path d="M135 300 C140 335 160 352 160 352 C160 352 180 335 185 300 Z" fill="#FF6B6B" />
      <path d="M147 300 C150 322 160 334 160 334 C160 334 170 322 173 300 Z" fill="#FFD23F" />
    </g>
    <g stroke="#13153A" strokeWidth="5" strokeLinejoin="round">
      <path d="M160 20 C215 60 230 140 222 260 L98 260 C90 140 105 60 160 20 Z" fill="#F2F0FF" />
      <path d="M98 205 L58 262 L62 300 L100 268 Z" fill="#FF6B6B" />
      <path d="M222 205 L262 262 L258 300 L220 268 Z" fill="#FF6B6B" />
      <path d="M118 258 L202 258 L192 300 L128 300 Z" fill="#9B8CFF" />
      <circle cx="160" cy="140" r="48" fill="#2A2E73" />
    </g>
    {/* Orbi */}
    <g stroke="#13153A" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round">
      <line x1="160" y1="104" x2="160" y2="116" />
      <circle cx="160" cy="100" r="6" fill="#FFD23F" />
      <rect x="132" y="116" width="56" height="44" rx="14" fill="#3DDC97" />
      <circle cx="148" cy="136" r="6" fill="#13153A" />
      <circle cx="172" cy="136" r="6" fill="#13153A" />
      <path d="M150 150 Q160 157 170 150" fill="none" />
      <path className={styles.wave} d="M188 150 L214 118" fill="none" />
      <circle className={styles.waveHand} cx="216" cy="114" r="8" fill="#3DDC97" />
    </g>
    <circle cx="174" cy="132" r="2" fill="#fff" />
    <circle cx="150" cy="132" r="2" fill="#fff" />
  </svg>
);

const BadgeShape: React.FC<{ shape: "moon" | "planet" | "star" | "rocket" }> = ({ shape }) => (
  <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false" className={styles.badgeIcon}>
    <g fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round">
      {shape === "moon" && <path d="M40 12a22 22 0 1 0 12 30A18 18 0 0 1 40 12z" />}
      {shape === "planet" && (
        <>
          <circle cx="32" cy="32" r="14" />
          <path d="M8 40c6 6 42-6 48-18" />
        </>
      )}
      {shape === "star" && <path d="M32 8l7 15 16 2-12 11 3 16-14-8-14 8 3-16L9 25l16-2z" />}
      {shape === "rocket" && (
        <>
          <path d="M32 6c10 8 13 20 11 36H21C19 26 22 14 32 6z" />
          <circle cx="32" cy="24" r="5" />
          <path d="M21 34l-8 10v6l9-6M43 34l8 10v6l-9-6M27 46l5 10 5-10" />
        </>
      )}
    </g>
  </svg>
);

const NightskyLanding: React.FC = () => {
  useBareLayout();
  const data = useLandingData(NIGHTSKY.courseTitleHint);
  const menu = useMenu();
  const brand = data.companyName || NIGHTSKY.brand;

  const missions = data.lessons.length
    ? data.lessons.map((lesson, i) => ({
        title: lesson.title.replace(/^mission\s*\d+[:.\s—-]*/i, ""),
        blurb: lesson.summary || NIGHTSKY.missions[i]?.blurb || "",
        activities: lesson.formats.length
          ? Array.from(new Set(lesson.formats.map((f) => FORMAT_ACTIVITY[f])))
          : NIGHTSKY.missions[i]?.activities ?? [],
        color: COLORS[i % COLORS.length],
      }))
    : NIGHTSKY.missions;

  const family = data.products.find((p) => /family|subscription/i.test(p.name ?? ""));
  const familyPrice = family ? formatMoney(family.gross_price ?? family.price, data.currency) : null;
  const parents = NIGHTSKY.parents.map((item, i) =>
    i === 3 && familyPrice ? { ...item, title: `${familyPrice} a month` } : item
  );

  const event = data.events[0];
  const eventStart = (event as { started_at?: string } | undefined)?.started_at;
  const party = event
    ? {
        ...NIGHTSKY.events.party,
        title: event.name || event.title || NIGHTSKY.events.party.title,
        date: formatDayMonth(eventStart) ?? NIGHTSKY.events.party.date,
      }
    : NIGHTSKY.events.party;
  // The events pages are not routed in this front; sign-up is the way in.
  const partyHref = routeRoutes.register;

  const webinar = data.webinars[0];
  const webinarStart = (webinar as { active_from?: string } | undefined)?.active_from;
  const live = webinar
    ? {
        ...NIGHTSKY.events.webinar,
        title: webinar.name,
        date: formatDayMonth(webinarStart) ?? NIGHTSKY.events.webinar.date,
      }
    : NIGHTSKY.events.webinar;

  return (
    <div className={styles.page}>
      <Helmet>
        <html lang="en" />
        <title>{`${brand} — ${NIGHTSKY.hero.title}`}</title>
        <meta name="description" content={NIGHTSKY.hero.sub} />
        <meta name="theme-color" content="#13153A" />
        <link rel="stylesheet" href={NUNITO} />
      </Helmet>
      <div className={styles.sky} aria-hidden="true" />
      <SkipLink className={styles.skip} />

      <header className={styles.header}>
        <Link to={routeRoutes.home} className={styles.logo}>
          <svg viewBox="0 0 32 32" width="32" height="32" aria-hidden="true" focusable="false">
            <path
              d="M16 3l3.5 8 8.5.8-6.4 5.6 1.9 8.4L16 21.5 8.5 25.8l1.9-8.4L4 11.8l8.5-.8z"
              fill="#FFD23F"
              stroke="#13153A"
              strokeWidth="2"
              strokeLinejoin="round"
            />
          </svg>
          <span>{brand}</span>
        </Link>
        <nav aria-label="Main" className={styles.nav}>
          <button className={styles.menuButton} {...menu.buttonProps}>
            <span className={styles.burger} aria-hidden="true" />
            Menu
          </button>
          <ul className={styles.navList} {...menu.menuProps}>
            <li>
              <InPageLink to="missions" onClick={menu.close}>
                Missions
              </InPageLink>
            </li>
            <li>
              <InPageLink to="parents" onClick={menu.close}>
                For parents
              </InPageLink>
            </li>
            <li>
              <InPageLink to="teachers" onClick={menu.close}>
                For teachers
              </InPageLink>
            </li>
            <li>
              <Link to={routeRoutes.login}>Log in</Link>
            </li>
          </ul>
        </nav>
        <Link to={data.courseHref} className={`${styles.buttonYellow} ${styles.headerCta}`}>
          {NIGHTSKY.hero.cta}
        </Link>
      </header>

      <main id="landing-main" className={styles.main}>
        <section className={styles.hero} aria-labelledby="ns-hero-title">
          <div className={styles.heroText}>
            <p className={styles.kicker}>7 missions · 10–15 min each</p>
            <h1 id="ns-hero-title" className={styles.heroTitle}>
              Your adventure to the stars <span className={styles.highlight}>starts tonight.</span>
            </h1>
            <p className={styles.heroSub}>{NIGHTSKY.hero.sub}</p>
            <div className={styles.actions}>
              <Link to={data.courseHref} className={styles.buttonYellow}>
                {NIGHTSKY.hero.cta}
              </Link>
              <InPageLink to="missions" className={styles.buttonOutline}>
                See the mission map
              </InPageLink>
            </div>
          </div>
          <div className={styles.heroArt}>
            <span className={styles.planetA} aria-hidden="true" />
            <span className={styles.planetB} aria-hidden="true" />
            <OrbiRocket />
          </div>
        </section>

        <section id="missions" className={styles.section} aria-labelledby="ns-missions-title">
          <h2 id="ns-missions-title" className={styles.h2}>
            The mission map
          </h2>
          <p className={styles.lead}>Follow the path, one mission a night. Mission 1 is free.</p>
          <ol className={styles.path}>
            {missions.map((mission, i) => (
              <li key={mission.title} className={styles.stop} data-side={i % 2 ? "right" : "left"}>
                <span className={styles.planet} data-color={mission.color} aria-hidden="true">
                  {i + 1}
                </span>
                <div className={styles.missionCard}>
                  <p className={styles.missionNo}>
                    Mission {i + 1}
                    {i === 0 && <span className={styles.free}>Free</span>}
                  </p>
                  <h3 className={styles.h3}>{mission.title}</h3>
                  <p className={styles.missionBlurb}>{mission.blurb}</p>
                  <ul className={styles.activities} aria-label="What you will do">
                    {mission.activities.map((activity) => (
                      <li key={activity}>
                        <FormatIcon format={ACTIVITY_FORMAT[activity]} size={18} strokeWidth={2} />
                        {NIGHTSKY.activityLabels[activity]}
                      </li>
                    ))}
                  </ul>
                </div>
              </li>
            ))}
          </ol>
          <div className={styles.center}>
            <Link to={data.courseHref} className={styles.buttonYellow}>
              {NIGHTSKY.hero.cta}
            </Link>
          </div>
        </section>

        <section className={styles.section} aria-labelledby="ns-badges-title">
          <h2 id="ns-badges-title" className={styles.h2}>
            Badges to collect
          </h2>
          <ul className={styles.badges}>
            {NIGHTSKY.badges.map((badge) => (
              <li key={badge.name} className={styles.badge} data-color={badge.color}>
                <span className={styles.badgeMedal}>
                  <BadgeShape shape={badge.shape} />
                </span>
                <h3 className={styles.badgeName}>{badge.name}</h3>
                <p className={styles.badgeHow}>{badge.how}</p>
              </li>
            ))}
          </ul>
        </section>

        <section id="parents" className={styles.section} aria-labelledby="ns-parents-title">
          <div className={styles.panel}>
            <h2 id="ns-parents-title" className={styles.h2}>
              For parents
            </h2>
            <ul className={styles.tiles}>
              {parents.map((item) => (
                <li key={item.title} className={styles.tile}>
                  <h3 className={styles.h3}>{item.title}</h3>
                  <p>{item.text}</p>
                </li>
              ))}
            </ul>
            <Link to={routeRoutes.register} className={styles.buttonYellow}>
              Create a family account
            </Link>
          </div>
        </section>

        <section id="teachers" className={styles.section} aria-labelledby="ns-teachers-title">
          <div className={`${styles.panel} ${styles.panelLilac}`}>
            <h2 id="ns-teachers-title" className={styles.h2}>
              For teachers: {NIGHTSKY.teachers.title.toLowerCase()}
            </h2>
            <p className={styles.lead}>{NIGHTSKY.teachers.text}</p>
            <ul className={styles.ticks}>
              {NIGHTSKY.teachers.points.map((point) => (
                <li key={point}>{point}</li>
              ))}
            </ul>
            <Link to={routeRoutes.register} className={styles.buttonOutline}>
              Set up a classroom
            </Link>
          </div>
        </section>

        <section className={styles.section} aria-labelledby="ns-events-title">
          <h2 id="ns-events-title" className={styles.h2}>
            Look up together
          </h2>
          <ul className={styles.events}>
            {[
              { ...party, href: partyHref, cta: "Join the star party" },
              { ...live, href: webinarHref(webinar), cta: "Send a question" },
            ].map((item) => (
              <li key={item.title} className={styles.event}>
                <span className={styles.eventDate}>{item.date}</span>
                <div>
                  <p className={styles.eventKind}>{item.kind}</p>
                  <h3 className={styles.h3}>{item.title}</h3>
                  <p>{item.text}</p>
                  <Link to={item.href} className={styles.textLink}>
                    {item.cta}
                    <span className={styles.visuallyHidden}>: {item.title}</span>
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        </section>

        <section className={styles.section} aria-labelledby="ns-quotes-title">
          <h2 id="ns-quotes-title" className={styles.h2}>
            Explorers say
          </h2>
          <ul className={styles.bubbles}>
            {NIGHTSKY.testimonials.map((quote) => (
              <li key={quote.who}>
                <figure className={styles.bubble} data-kind={quote.kind}>
                  <blockquote>
                    <p>&ldquo;{quote.text}&rdquo;</p>
                  </blockquote>
                  <figcaption>{quote.who}</figcaption>
                </figure>
              </li>
            ))}
          </ul>
        </section>

        <section className={styles.section} aria-labelledby="ns-faq-title">
          <h2 id="ns-faq-title" className={styles.h2}>
            Questions from grown-ups
          </h2>
          <div className={styles.faq}>
            {NIGHTSKY.faq.map((item) => (
              <details key={item.q} className={styles.faqItem}>
                <summary>{item.q}</summary>
                <p>{item.a}</p>
              </details>
            ))}
          </div>
        </section>
      </main>

      <footer className={styles.footer}>
        <p className={styles.footerBrand}>{brand}</p>
        <nav aria-label="Footer">
          <ul className={styles.footerLinks}>
            <li>
              <Link to={routeRoutes.courses}>All missions</Link>
            </li>
            <li>
              <Link to={routeRoutes.register}>Create account</Link>
            </li>
            <li>
              <Link to={routeRoutes.login}>Log in</Link>
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

export default NightskyLanding;
