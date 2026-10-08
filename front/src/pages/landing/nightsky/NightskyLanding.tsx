import React, { useState } from "react";
import { Helmet } from "react-helmet";
import { Link, useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import { useBareLayout } from "@/components/_App/bareLayout";
import { InPageLink, SkipLink } from "../shared/InPageLink";
import { useMenu } from "../shared/useMenu";
import { formatDayMonth, formatMinutes, formatMoney } from "../shared/format";
import { useLandingData, webinarHref } from "../shared/useLandingData";
import { FORMAT_KID, NIGHTSKY } from "./content";
import styles from "./NightskyLanding.module.css";

const FONTS =
  "https://fonts.googleapis.com/css2?family=Comfortaa:wght@600;700&family=Quicksand:wght@500;600;700&display=swap";

/** Decorative emoji: hidden from assistive technology, the text next to it carries the meaning. */
const Emoji: React.FC<{ children: string; className?: string }> = ({ children, className }) => (
  <span className={className ?? styles.emoji} aria-hidden="true">
    {children}
  </span>
);

/** Orbi, the robot guide, riding a small rocket. */
const OrbiRocket: React.FC = () => (
  <svg className={styles.orbi} viewBox="0 0 240 300" role="img" aria-labelledby="orbi-title">
    <title id="orbi-title">Orbi the robot smiling from the top of a small purple rocket</title>
    <g className={styles.flame}>
      <path d="M100 250 C104 280 120 296 120 296 C120 296 136 280 140 250 Z" fill="#FF6B6B" />
      <path d="M109 250 C111 268 120 280 120 280 C120 280 129 268 131 250 Z" fill="#FFD23F" />
    </g>
    {/* rocket */}
    <path d="M120 70 C150 100 160 160 152 240 L88 240 C80 160 90 100 120 70 Z" fill="#2F3157" />
    <path d="M88 180 L50 240 L92 236 Z" fill="#4533A3" />
    <path d="M152 180 L190 240 L148 236 Z" fill="#4533A3" />
    <path d="M96 236 L144 236 L136 256 L104 256 Z" fill="#9B8CFF" />
    {/* antenna */}
    <line x1="120" y1="38" x2="120" y2="64" stroke="#FFD23F" strokeWidth="5" strokeLinecap="round" />
    <circle cx="120" cy="34" r="9" fill="#FFD23F" />
    {/* head */}
    <rect x="72" y="62" width="96" height="78" rx="36" fill="#C8BFFF" />
    <rect x="84" y="78" width="72" height="44" rx="22" fill="#13153A" />
    <path d="M96 102 q8 -10 16 0" fill="none" stroke="#58F1AA" strokeWidth="5" strokeLinecap="round" />
    <path d="M128 102 q8 -10 16 0" fill="none" stroke="#58F1AA" strokeWidth="5" strokeLinecap="round" />
    <circle cx="66" cy="102" r="10" fill="#FFD23F" />
    <circle cx="174" cy="102" r="10" fill="#FFD23F" />
    {/* body + scarf */}
    <rect x="86" y="140" width="68" height="44" rx="20" fill="#C8BFFF" />
    <path d="M84 146 Q120 162 156 146 L156 156 Q120 172 84 156 Z" fill="#FFD23F" />
    <g className={styles.wave}>
      <path d="M154 160 L182 128" stroke="#C8BFFF" strokeWidth="12" strokeLinecap="round" />
      <circle cx="184" cy="124" r="9" fill="#FFD23F" />
    </g>
  </svg>
);

const NightskyLanding: React.FC = () => {
  useBareLayout();
  const history = useHistory();
  const data = useLandingData(NIGHTSKY.courseTitleHint);
  const menu = useMenu();
  const [email, setEmail] = useState("");
  const brand = data.companyName || NIGHTSKY.brand;

  const missions = NIGHTSKY.missions.map((copy, i) => {
    const lesson = data.lessons[i];
    if (!lesson) return copy;
    const topics = lesson.topics.length
      ? lesson.topics
          .filter((topic) => topic.format)
          .slice(0, 3)
          .map((topic) => ({ title: topic.title, format: topic.format! }))
      : copy.topics;
    return {
      ...copy,
      title: lesson.title.replace(/^mission\s*\d+\s*[:.·—–-]?\s*/i, "") || copy.title,
      blurb: lesson.summary || copy.blurb,
      minutes: lesson.minutes ? formatMinutes(lesson.minutes) : copy.minutes,
      topics,
    };
  });
  const regular = missions.slice(0, 6);
  const capstone = missions[6];

  const topicTotal = data.lessons.reduce((sum, lesson) => sum + lesson.topicCount, 0);

  const family = data.products.find((p) => /family|subscription/i.test(p.name ?? ""));
  const familyPrice = family ? formatMoney(family.gross_price ?? family.price, data.currency) : null;
  const familyLabel = `${familyPrice ?? "€6"}/month`;

  const event = data.events[0];
  const eventStart = (event as { started_at?: string } | undefined)?.started_at;
  const party = event
    ? {
        ...NIGHTSKY.events.party,
        title: event.name || event.title || NIGHTSKY.events.party.title,
        date: formatDayMonth(eventStart) ?? NIGHTSKY.events.party.date,
      }
    : NIGHTSKY.events.party;

  const webinar = data.webinars[0];
  const webinarStart = (webinar as { active_from?: string } | undefined)?.active_from;
  const live = webinar
    ? {
        ...NIGHTSKY.events.webinar,
        title: webinar.name,
        date: formatDayMonth(webinarStart) ?? NIGHTSKY.events.webinar.date,
      }
    : NIGHTSKY.events.webinar;

  const stats = [
    { value: "7", label: "missions on the adventure map", tone: "mint" },
    { value: topicTotal ? String(topicTotal) : "21", label: "hands-on topics to explore", tone: "yellow" },
    { value: "10–15", label: "minutes per mission", tone: "lilac" },
    { value: familyLabel, label: "family plan, Mission 1 free", tone: "coral" },
  ];

  const onSignup = (event: React.FormEvent) => {
    event.preventDefault();
    history.push({ pathname: routeRoutes.register, state: { email } });
  };

  const navLinks = [
    { to: "missions", label: "Missions" },
    { to: "parents", label: "For parents" },
    { to: "teachers", label: "For teachers" },
    { to: "events", label: "Events & star parties" },
    { to: "badges", label: "Badges" },
  ];

  return (
    <div className={styles.page}>
      <Helmet>
        <html lang="en" />
        <title>{`${brand} — ${NIGHTSKY.hero.titleStart} ${NIGHTSKY.hero.titleHighlight}`}</title>
        <meta name="description" content={NIGHTSKY.hero.sub} />
        <meta name="theme-color" content="#0D0F34" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
        <link rel="stylesheet" href={FONTS} />
      </Helmet>
      <div className={styles.sky} aria-hidden="true" />
      <SkipLink className={styles.skip} />

      <header className={styles.header}>
        <div className={styles.headerInner}>
          <Link to={routeRoutes.home} className={styles.logo}>
            <span className={styles.logoMark} aria-hidden="true">
              <svg viewBox="0 0 32 32" width="22" height="22" focusable="false">
                <path
                  d="M16 3l3.5 8 8.5.8-6.4 5.6 1.9 8.4L16 21.5 8.5 25.8l1.9-8.4L4 11.8l8.5-.8z"
                  fill="#FFD23F"
                />
              </svg>
            </span>
            <span>{brand}</span>
          </Link>
          <nav aria-label="Main" className={styles.nav}>
            <button className={styles.menuButton} {...menu.buttonProps}>
              <span className={styles.burger} aria-hidden="true" />
              Menu
            </button>
            <ul className={styles.navList} {...menu.menuProps}>
              {navLinks.map((item) => (
                <li key={item.to}>
                  <InPageLink to={item.to} onClick={menu.close}>
                    {item.label}
                  </InPageLink>
                </li>
              ))}
              <li className={styles.navLogin}>
                <Link to={routeRoutes.login}>Log in</Link>
              </li>
            </ul>
          </nav>
          <div className={styles.headerActions}>
            <Link to={routeRoutes.login} className={styles.loginLink}>
              Log in
            </Link>
            <Link to={data.courseHref} className={`${styles.btnYellow} ${styles.btnSmall}`}>
              {NIGHTSKY.hero.cta} <Emoji>🚀</Emoji>
            </Link>
          </div>
        </div>
      </header>

      <main id="landing-main" className={styles.main}>
        {/* Hero */}
        <section className={styles.hero} aria-labelledby="ns-hero-title">
          <div className={styles.heroText}>
            <p className={styles.heroBadge}>
              <span className={styles.pulse} aria-hidden="true" />
              {NIGHTSKY.hero.badge} <Emoji>✨</Emoji>
            </p>
            <h1 id="ns-hero-title" className={styles.heroTitle}>
              {NIGHTSKY.hero.titleStart}{" "}
              <span className={styles.highlight}>{NIGHTSKY.hero.titleHighlight}</span>.
            </h1>
            <p className={styles.heroSub}>{NIGHTSKY.hero.sub}</p>
            <div className={styles.heroActions}>
              <Link to={data.courseHref} className={`${styles.btnYellow} ${styles.btnBig}`}>
                {NIGHTSKY.hero.cta} <Emoji>🚀</Emoji>
              </Link>
              <InPageLink to="missions" className={`${styles.btnDark} ${styles.btnBig}`}>
                {NIGHTSKY.hero.secondary} <Emoji>🗺️</Emoji>
              </InPageLink>
            </div>
            <ul className={styles.chips} aria-label="At a glance">
              {NIGHTSKY.hero.chips.map((chip) => (
                <li key={chip.text} className={styles.chip}>
                  <Emoji>{chip.emoji}</Emoji>
                  {topicTotal ? chip.text.replace(/^21 /, `${topicTotal} `) : chip.text}
                </li>
              ))}
            </ul>
          </div>
          <div className={styles.heroArt}>
            <div className={styles.orbitOuter} aria-hidden="true" />
            <div className={styles.orbitInner} aria-hidden="true" />
            <div className={styles.orbitCore} aria-hidden="true" />
            <span className={`${styles.floatChip} ${styles.floatSaturn}`} aria-hidden="true">
              <span>🪐</span> Saturn rings
            </span>
            <span className={`${styles.floatChip} ${styles.floatPoints}`} aria-hidden="true">
              <span>⭐</span> +50 star pts
            </span>
            <span className={`${styles.floatChip} ${styles.floatMoon}`} aria-hidden="true">
              <span>🌙</span>
            </span>
            <div className={styles.orbiWrap}>
              <p className={styles.bubble}>
                &ldquo;{NIGHTSKY.hero.bubble}&rdquo; <Emoji>🚀</Emoji>
              </p>
              <OrbiRocket />
            </div>
          </div>
        </section>

        {/* Stats band */}
        <section className={styles.stats} aria-label="Night Sky Explorers in numbers">
          <ul className={styles.statsList}>
            {stats.map((stat) => (
              <li key={stat.label} className={styles.stat}>
                <span className={styles.statDot} data-tone={stat.tone} aria-hidden="true" />
                <strong>{stat.value}</strong> {stat.label}
              </li>
            ))}
          </ul>
        </section>

        {/* Mission map */}
        <section id="missions" className={styles.section} aria-labelledby="ns-missions-title">
          <header className={styles.sectionHead}>
            <p className={styles.pill} data-tone="lilac">
              <Emoji>🧭</Emoji> Cosmic quest pathway
            </p>
            <h2 id="ns-missions-title" className={styles.h2}>
              The 7-Mission Adventure Map
            </h2>
            <p className={styles.lead}>
              Travel through the solar system step by step. Each mission unlocks new cosmic secrets,
              games and collector badges!
            </p>
          </header>
          <ol className={styles.missionGrid}>
            {regular.map((mission, i) => (
              <li key={mission.title} className={styles.missionCard}>
                <div className={styles.missionTop}>
                  <span className={styles.tag} data-tone={i === 0 ? "yellow" : "dim"}>
                    {mission.label}
                  </span>
                  <span className={styles.minutes}>
                    <Emoji>⏱️</Emoji>
                    {mission.minutes}
                  </span>
                </div>
                <div className={styles.missionHead}>
                  <span className={styles.missionIcon} data-index={i} aria-hidden="true">
                    {mission.emoji}
                  </span>
                  <div>
                    <p className={styles.missionNo}>Mission {String(i + 1).padStart(2, "0")}</p>
                    <h3 className={styles.missionTitle}>{mission.title}</h3>
                  </div>
                </div>
                <p className={styles.missionBlurb}>{mission.blurb}</p>
                <ul className={styles.topicList} aria-label={`Inside mission ${i + 1}`}>
                  {mission.topics.map((topic) => (
                    <li key={topic.title} className={styles.topic}>
                      <Emoji>{FORMAT_KID[topic.format].emoji}</Emoji>
                      <span className={styles.topicTitle}>{topic.title}</span>
                      <span className={styles.topicTag} data-format={topic.format}>
                        {FORMAT_KID[topic.format].label}
                      </span>
                    </li>
                  ))}
                </ul>
                {i === 0 ? (
                  <Link to={data.courseHref} className={`${styles.btnYellow} ${styles.btnBlock}`}>
                    Launch Mission 1 <Emoji>🚀</Emoji>
                  </Link>
                ) : (
                  <Link to={data.courseHref} className={`${styles.btnLocked} ${styles.btnBlock}`}>
                    <Emoji>🔒</Emoji> Unlock Mission {i + 1}
                  </Link>
                )}
              </li>
            ))}
            {capstone && (
              <li className={styles.capstone}>
                <div className={styles.capstoneText}>
                  <div className={styles.missionTop}>
                    <span className={styles.tag} data-tone="yellow">
                      {capstone.label}
                    </span>
                    <span className={styles.capstoneNote}>
                      <Emoji>⭐</Emoji> Junior Astronomer badge unlock
                    </span>
                  </div>
                  <h3 className={styles.capstoneTitle}>
                    <span className={styles.visuallyHidden}>Mission 7: </span>
                    {capstone.title}
                  </h3>
                  <p className={styles.missionBlurb}>{capstone.blurb}</p>
                  <ul className={styles.capstoneTopics} aria-label="Inside mission 7">
                    {capstone.topics.map((topic) => (
                      <li key={topic.title}>
                        <Emoji>{FORMAT_KID[topic.format].emoji}</Emoji> {topic.title}
                      </li>
                    ))}
                  </ul>
                </div>
                <div className={styles.capstoneSide}>
                  <span className={styles.capstoneIcon} aria-hidden="true">
                    {capstone.emoji}
                  </span>
                  <p className={styles.capstoneLock}>
                    <Emoji>🔒</Emoji> Complete missions 1–6 first
                  </p>
                </div>
              </li>
            )}
          </ol>
        </section>

        {/* Badges */}
        <section id="badges" className={styles.band} aria-labelledby="ns-badges-title">
          <div className={styles.bandInner}>
            <header className={styles.sectionHead}>
              <p className={styles.pill} data-tone="yellow">
                Badges to collect
              </p>
              <h2 id="ns-badges-title" className={styles.h2}>
                Earn your Junior Astronomer badges
              </h2>
              <p className={styles.lead}>
                Complete observations to collect star points (★), level up from Stargazer to Cosmic
                Explorer, and print your own diploma!
              </p>
            </header>
            <ul className={styles.badgeGrid}>
              {NIGHTSKY.badges.map((badge) => (
                <li key={badge.name} className={styles.badgeCard}>
                  <span className={styles.medal} data-tone={badge.tone} aria-hidden="true">
                    {badge.emoji}
                  </span>
                  <p className={styles.tier} data-tone={badge.tone}>
                    {badge.tier}
                  </p>
                  <h3 className={styles.badgeName}>{badge.name}</h3>
                  <p className={styles.small}>{badge.how}</p>
                  <p className={styles.points} data-tone={badge.tone}>
                    <Emoji>{badge.tone === "capstone" ? "🏅" : "☆"}</Emoji>
                    {badge.points}
                  </p>
                </li>
              ))}
            </ul>
            <div className={styles.pointsCard}>
              <span className={styles.pointsIcon} aria-hidden="true">
                ⭐
              </span>
              <div className={styles.pointsText}>
                <h3 className={styles.h3}>How star points work</h3>
                <p className={styles.small}>
                  Earn points with every finished mini-challenge, then trade them for telescope filters
                  and real stickers!
                </p>
              </div>
              <div className={styles.rank}>
                <p className={styles.rankRow}>
                  <span>Example rank: Cadet Stargazer</span>
                  <span className={styles.rankValue}>650 / 1000 ★</span>
                </p>
                <span
                  className={styles.rankBar}
                  role="img"
                  aria-label="Progress bar: 650 of 1000 star points"
                >
                  <span />
                </span>
              </div>
            </div>
          </div>
        </section>

        {/* Formats */}
        <section className={styles.section} aria-labelledby="ns-formats-title">
          <header className={styles.sectionHead}>
            <p className={styles.pill} data-tone="lilac">
              No boring textbooks here
            </p>
            <h2 id="ns-formats-title" className={styles.h2}>
              Formats you explore with
            </h2>
            <p className={styles.lead}>
              Eleven kinds of activities, so every brain finds its favourite way to learn about the
              deep cosmos.
            </p>
          </header>
          <ul className={styles.formatGrid}>
            {NIGHTSKY.formats.map((item, i) => (
              <li key={item.title} className={styles.formatCard}>
                <span className={styles.formatIcon} data-index={i % 3} aria-hidden="true">
                  {item.emoji}
                </span>
                <div>
                  <h3 className={styles.h3}>{item.title}</h3>
                  <p className={styles.small}>{item.text}</p>
                </div>
              </li>
            ))}
          </ul>
        </section>

        {/* Parents */}
        <section id="parents" className={styles.band} aria-labelledby="ns-parents-title">
          <div className={styles.bandInner}>
            <div className={styles.parentsTop}>
              <div>
                <p className={styles.pill} data-tone="mint">
                  {NIGHTSKY.parents.label}
                </p>
                <h2 id="ns-parents-title" className={`${styles.h2} ${styles.left}`}>
                  {NIGHTSKY.parents.title}
                </h2>
                <p className={`${styles.lead} ${styles.left}`}>{NIGHTSKY.parents.text}</p>
              </div>
              <figure className={styles.parentQuote}>
                <p className={styles.stars} aria-label="Rated 5 out of 5">
                  ★★★★★
                </p>
                <blockquote>
                  <p>&ldquo;{NIGHTSKY.parents.quote.text}&rdquo;</p>
                </blockquote>
                <figcaption className={styles.person}>
                  <span className={styles.avatar} data-tone="lilac" aria-hidden="true">
                    {NIGHTSKY.parents.quote.who[0]}
                  </span>
                  <span>
                    <span className={styles.personName}>{NIGHTSKY.parents.quote.who}</span>
                    <span className={styles.personNote}>{NIGHTSKY.parents.quote.role}</span>
                  </span>
                </figcaption>
              </figure>
            </div>
            <ul className={styles.featureGrid}>
              {NIGHTSKY.parents.items.map((item, i) => (
                <li key={item.title} className={styles.featureCard}>
                  <span className={styles.featureIcon} data-tone={item.tone} aria-hidden="true">
                    {item.emoji}
                  </span>
                  <h3 className={styles.h3}>{i === 3 ? `${item.title} (${familyLabel})` : item.title}</h3>
                  <p className={styles.small}>{item.text}</p>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* Teachers */}
        <section id="teachers" className={styles.section} aria-labelledby="ns-teachers-title">
          <div className={styles.classroom}>
            <div className={styles.classroomText}>
              <p className={styles.pill} data-tone="lilac">
                <Emoji>🎓</Emoji> {NIGHTSKY.teachers.label}
              </p>
              <h2 id="ns-teachers-title" className={`${styles.h2} ${styles.left}`}>
                {NIGHTSKY.teachers.title}
              </h2>
              <p className={`${styles.lead} ${styles.left}`}>{NIGHTSKY.teachers.text}</p>
              <ul className={styles.ticks}>
                {NIGHTSKY.teachers.points.map((point) => (
                  <li key={point}>{point}</li>
                ))}
              </ul>
              <div className={styles.classroomCta}>
                <Link to={routeRoutes.register} className={styles.btnLilac}>
                  {NIGHTSKY.teachers.cta}
                </Link>
                <span className={styles.small}>{NIGHTSKY.teachers.note}</span>
              </div>
            </div>
            <figure className={styles.teacherCard}>
              <span className={styles.teacherIcon} aria-hidden="true">
                🔭
              </span>
              <blockquote>
                <p>&ldquo;{NIGHTSKY.teachers.quote.text}&rdquo;</p>
              </blockquote>
              <figcaption>
                <span className={styles.personName}>{NIGHTSKY.teachers.quote.who}</span>
                <span className={styles.personNote}>{NIGHTSKY.teachers.quote.role}</span>
              </figcaption>
            </figure>
          </div>
        </section>

        {/* Events */}
        <section id="events" className={styles.band} aria-labelledby="ns-events-title">
          <div className={styles.bandInner}>
            <header className={styles.eventsHead}>
              <div>
                <p className={styles.pill} data-tone="yellow">
                  Meet real scientists
                </p>
                <h2 id="ns-events-title" className={`${styles.h2} ${styles.left}`}>
                  Events &amp; star parties
                </h2>
              </div>
              <Link to={routeRoutes.webinars} className={styles.textLink}>
                See all events <span aria-hidden="true">→</span>
              </Link>
            </header>
            <ul className={styles.eventGrid}>
              {[
                { ...party, tone: "mint", href: routeRoutes.register, primary: true, a: "📍", b: "👥" },
                { ...live, tone: "lilac", href: webinarHref(webinar), primary: false, a: "🎥", b: "💬" },
              ].map((item) => (
                <li key={item.title} className={styles.eventCard}>
                  <div className={styles.missionTop}>
                    <span className={styles.tag} data-tone={item.tone}>
                      {item.kind}
                    </span>
                    <span className={styles.eventDate}>{item.date}</span>
                  </div>
                  <h3 className={styles.eventTitle}>{item.title}</h3>
                  <p className={styles.body}>{item.text}</p>
                  <ul className={styles.eventFacts}>
                    <li>
                      <Emoji>{item.a}</Emoji> {item.place}
                    </li>
                    <li>
                      <Emoji>{item.b}</Emoji> {item.extra}
                    </li>
                  </ul>
                  <Link
                    to={item.href}
                    className={`${item.primary ? styles.btnYellow : styles.btnDark} ${styles.btnBlock}`}
                  >
                    {item.cta}
                    <span className={styles.visuallyHidden}>: {item.title}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* Testimonials */}
        <section className={styles.section} aria-labelledby="ns-quotes-title">
          <header className={styles.sectionHead}>
            <p className={styles.pill} data-tone="mint">
              Voices from the observation deck
            </p>
            <h2 id="ns-quotes-title" className={styles.h2}>
              Hear from fellow stargazers
            </h2>
            <p className={styles.lead}>
              What kids and parents discover once they switch off the lights and join the quest.
            </p>
          </header>
          <ul className={styles.quoteGrid}>
            {NIGHTSKY.testimonials.map((quote) => (
              <li key={quote.who}>
                <figure className={styles.quoteCard}>
                  <span className={styles.quoteMark} data-tone={quote.tone} aria-hidden="true">
                    &ldquo;
                  </span>
                  <blockquote>
                    <p>{quote.text}</p>
                  </blockquote>
                  <figcaption className={styles.person}>
                    <span className={styles.avatar} data-tone={quote.tone} aria-hidden="true">
                      {quote.who[0]}
                    </span>
                    <span>
                      <span className={styles.personName}>{quote.who}</span>
                      <span className={styles.personNote} data-tone={quote.tone}>
                        {quote.note}
                      </span>
                    </span>
                  </figcaption>
                </figure>
              </li>
            ))}
          </ul>
        </section>

        {/* FAQ + signup */}
        <section className={styles.band} aria-labelledby="ns-faq-title">
          <div className={styles.faqWrap}>
            <header className={styles.sectionHead}>
              <h2 id="ns-faq-title" className={styles.h2}>
                Frequently asked questions
              </h2>
              <p className={styles.lead}>
                Everything parents and curious stargazers want to know before liftoff.
              </p>
            </header>
            <div className={styles.faq}>
              {NIGHTSKY.faq.map((item) => (
                <details key={item.q} className={styles.faqItem}>
                  <summary>{item.q}</summary>
                  <p>{item.a}</p>
                </details>
              ))}
            </div>
            <div className={styles.signup}>
              <span className={styles.signupIcon} aria-hidden="true">
                🌟
              </span>
              <h2 id="ns-signup-title" className={styles.h2}>
                {NIGHTSKY.signup.title}
              </h2>
              <p className={styles.lead}>{NIGHTSKY.signup.text}</p>
              <form className={styles.form} onSubmit={onSignup} aria-labelledby="ns-signup-title">
                <label htmlFor="ns-email" className={styles.visuallyHidden}>
                  Grown-up’s email address
                </label>
                <input
                  id="ns-email"
                  type="email"
                  autoComplete="email"
                  required
                  placeholder="Grown-up’s email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
                <button type="submit" className={styles.btnYellow}>
                  {NIGHTSKY.signup.cta} <Emoji>🚀</Emoji>
                </button>
              </form>
              <p className={styles.fine}>{NIGHTSKY.signup.note}</p>
            </div>
          </div>
        </section>
      </main>

      <footer className={styles.footer}>
        <div className={styles.footerTop}>
          <div>
            <p className={styles.footerBrand}>
              <Emoji>🚀</Emoji> {brand}
            </p>
            <p className={styles.footerTag}>Exploring the cosmos one mission at a time</p>
          </div>
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
                <Link to={routeRoutes.privacyPolicy}>Safety &amp; privacy</Link>
              </li>
            </ul>
          </nav>
        </div>
        <p className={styles.copyright}>
          © {new Date().getFullYear()} {brand}. Built for cosmic discoverers everywhere.
        </p>
      </footer>
    </div>
  );
};

export default NightskyLanding;
