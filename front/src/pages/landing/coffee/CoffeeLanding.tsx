import React, { useState } from "react";
import { Helmet } from "react-helmet";
import { Link, useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import { useBareLayout } from "@/components/_App/bareLayout";
import { InPageLink, SkipLink } from "../shared/InPageLink";
import { useMenu } from "../shared/useMenu";
import { FormatIcon } from "../shared/FormatIcon";
import { FORMAT_LABELS } from "../shared/formats";
import { formatDayMonth, formatMinutes, formatMoney, formatTime, toRoman } from "../shared/format";
import { fullName, productHref, useLandingData, webinarHref } from "../shared/useLandingData";
import { COFFEE } from "./content";
import styles from "./CoffeeLanding.module.css";

/** Line drawing of a coffee branch with ripe cherries (hero plate when the course has no image). */
const CoffeeBranch: React.FC = () => (
  <svg
    className={styles.branch}
    viewBox="0 0 400 480"
    role="img"
    aria-labelledby="coffee-branch-title"
  >
    <title id="coffee-branch-title">
      Line drawing of a coffee branch with clusters of ripe red cherries
    </title>
    <g fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
      <path d="M40 460 C120 380 170 300 210 220 S300 70 360 30" />
      <path d="M150 330 C120 300 70 300 40 320 C80 340 120 345 150 330 Z" />
      <path d="M150 330 C110 322 75 318 40 320" />
      <path d="M190 260 C200 220 240 190 285 190 C265 225 230 255 190 260 Z" />
      <path d="M190 260 C222 236 255 210 285 190" />
      <path d="M232 170 C200 150 190 110 205 70 C232 100 240 135 232 170 Z" />
      <path d="M232 170 C220 135 210 100 205 70" />
      <path d="M300 92 C320 70 352 70 380 86 C352 104 322 106 300 92 Z" />
      <path d="M300 92 C325 86 352 84 380 86" />
      <path d="M95 400 C60 380 40 350 42 315" opacity="0.5" />
    </g>
    <g className={styles.cherries} strokeWidth="1.4">
      <circle cx="168" cy="300" r="13" />
      <circle cx="186" cy="312" r="12" />
      <circle cx="160" cy="322" r="11" />
      <circle cx="232" cy="212" r="12" />
      <circle cx="250" cy="224" r="13" />
      <circle cx="226" cy="232" r="10" />
      <circle cx="276" cy="136" r="11" />
      <circle cx="292" cy="148" r="12" />
      <circle cx="120" cy="392" r="10" />
    </g>
    <g fill="none" stroke="currentColor" strokeWidth="1" opacity="0.55">
      <path d="M163 293 a6 6 0 0 1 7 -3" />
      <path d="M245 217 a6 6 0 0 1 7 -3" />
      <path d="M287 141 a6 6 0 0 1 7 -3" />
    </g>
  </svg>
);

const CoffeeLanding: React.FC = () => {
  useBareLayout();
  const history = useHistory();
  const data = useLandingData(COFFEE.courseTitleHint);
  const menu = useMenu();
  const [email, setEmail] = useState("");

  const brand = data.companyName || COFFEE.brand;
  const course = data.course;

  const chapters = data.lessons.length
    ? data.lessons.map((lesson, i) => ({
        title: lesson.title,
        blurb: lesson.summary || COFFEE.chapters[i]?.blurb || "",
        formats: lesson.formats.length ? lesson.formats : COFFEE.chapters[i]?.formats ?? [],
        minutes: lesson.minutes || COFFEE.chapters[i]?.minutes || 0,
      }))
    : COFFEE.chapters;

  const tutors = data.tutors.length
    ? data.tutors.slice(0, 2).map((tutor, i) => {
        const name = fullName(tutor);
        return {
          name,
          role: COFFEE.tutors.find((t) => t.name === name)?.role ?? COFFEE.tutors[i]?.role ?? "Tutor",
          avatar: tutor.url_avatar,
        };
      })
    : COFFEE.tutors.map((t) => ({ ...t, avatar: null as string | null }));

  const webinar = data.webinars[0];
  const webinarStart = (webinar as { active_from?: string } | undefined)?.active_from;
  const live = webinar
    ? {
        title: webinar.name,
        date: formatDayMonth(webinarStart) ?? COFFEE.live.date,
        time: formatTime(webinarStart) ?? "",
        text: (webinar as { short_desc?: string }).short_desc || COFFEE.live.text,
      }
    : COFFEE.live;

  const bundle = data.products.find((p) => /taste|bundle|package/i.test(p.name ?? ""));
  const bundlePrice = bundle ? formatMoney(bundle.gross_price ?? bundle.price, data.currency) : null;
  const bundleLink = productHref(bundle) ?? data.courseHref;

  const onNewsletter = (event: React.FormEvent) => {
    event.preventDefault();
    history.push({ pathname: routeRoutes.register, state: { email } });
  };

  const navItems = (
    <>
      <li>
        <Link to={routeRoutes.courses}>Courses</Link>
      </li>
      <li>
        <InPageLink to="tutors" onClick={menu.close}>
          Tutors
        </InPageLink>
      </li>
      <li>
        <InPageLink to="live" onClick={menu.close}>
          Live sessions
        </InPageLink>
      </li>
      <li>
        <InPageLink to="field-notes" onClick={menu.close}>
          Journal
        </InPageLink>
      </li>
      <li>
        <Link to={routeRoutes.login}>Sign in</Link>
      </li>
    </>
  );

  return (
    <div className={styles.page}>
      <Helmet>
        <html lang="en" />
        <title>{`${brand} — ${COFFEE.hero.title}`}</title>
        <meta name="description" content={COFFEE.hero.summary} />
        <meta name="theme-color" content="#F6F1E9" />
      </Helmet>
      <SkipLink className={styles.skip} />

      <header className={styles.masthead}>
        <div className={styles.folio}>
          <span>{COFFEE.hero.kicker}</span>
          <span className={styles.folioRight}>Self-paced · Long-form · Slow</span>
        </div>
        <div className={styles.mastheadRow}>
          <Link to={routeRoutes.home} className={styles.wordmark}>
            {brand}
          </Link>
          <nav aria-label="Main" className={styles.nav}>
            <button className={styles.menuButton} {...menu.buttonProps}>
              <span aria-hidden="true" className={styles.menuGlyph} />
              Menu
            </button>
            <ul className={styles.navList} {...menu.menuProps}>
              {navItems}
            </ul>
          </nav>
          <Link to={data.courseHref} className={styles.buttonPrimary}>
            Start free chapter
          </Link>
        </div>
      </header>

      <main id="landing-main" className={styles.main}>
        {/* Hero */}
        <section className={styles.hero} aria-labelledby="coffee-hero-title">
          <div className={styles.heroText}>
            <p className={styles.chapterLabel}>Chapter I — Origins</p>
            <h1 id="coffee-hero-title" className={styles.heroTitle}>
              {COFFEE.hero.title}
            </h1>
            <p className={styles.heroSub}>{COFFEE.hero.sub}</p>
            <p className={styles.heroSummary}>{course?.summary || COFFEE.hero.summary}</p>
            <div className={styles.actions}>
              <Link to={data.courseHref} className={styles.buttonPrimary}>
                Start the free chapter
              </Link>
              <InPageLink to="syllabus" className={styles.buttonGhost}>
                See the syllabus
              </InPageLink>
            </div>
            <dl className={styles.facts}>
              {COFFEE.hero.facts.map(([term, value]) => (
                <div key={term}>
                  <dt>{term}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
            </dl>
          </div>
          <figure className={styles.plate}>
            <div className={styles.plateInner}>
              {course?.image_url ? (
                <img src={course.image_url} alt={`Cover of ${course.title}`} />
              ) : (
                <CoffeeBranch />
              )}
            </div>
            <figcaption className={styles.marginalia}>{COFFEE.hero.caption}</figcaption>
          </figure>
        </section>

        {/* Table of contents */}
        <section id="syllabus" className={styles.section} aria-labelledby="coffee-toc-title">
          <header className={styles.sectionHead}>
            <p className={styles.labelCaps}>In this volume</p>
            <h2 id="coffee-toc-title" className={styles.h2}>
              Contents
            </h2>
            <p className={styles.sectionIntro}>
              {course?.title || COFFEE.courseTitle}. {course?.subtitle || COFFEE.courseSubtitle}.
            </p>
          </header>
          <ol className={styles.toc}>
            {chapters.map((chapter, i) => (
              <li key={chapter.title} className={styles.tocRow}>
                <span className={styles.numeral} aria-hidden="true">
                  {toRoman(i + 1)}
                </span>
                <div className={styles.tocBody}>
                  <h3 className={styles.tocTitle}>
                    <span className={styles.visuallyHidden}>Chapter {i + 1}: </span>
                    {chapter.title}
                  </h3>
                  {chapter.blurb && <p className={styles.tocBlurb}>{chapter.blurb}</p>}
                  <ul className={styles.badges} aria-label="Formats">
                    {chapter.formats.map((format) => (
                      <li key={format} className={styles.badge} data-format={format}>
                        {FORMAT_LABELS[format]}
                      </li>
                    ))}
                  </ul>
                </div>
                {chapter.minutes > 0 && (
                  <span className={styles.tocPage}>{formatMinutes(chapter.minutes)}</span>
                )}
              </li>
            ))}
          </ol>
        </section>

        {/* Letter from the tutors */}
        <section id="tutors" className={styles.letterSection} aria-labelledby="coffee-letter-title">
          <div className={styles.letter}>
            <p className={styles.labelCaps}>Correspondence</p>
            <h2 id="coffee-letter-title" className={styles.h2}>
              A letter from your tutors
            </h2>
            <p className={styles.salutation}>Dear reader,</p>
            {COFFEE.letter.map((paragraph, i) => (
              <p key={i} className={i === 0 ? styles.dropCap : styles.letterText}>
                {paragraph}
              </p>
            ))}
            <ul className={styles.signatures}>
              {tutors.map((tutor) => (
                <li key={tutor.name} className={styles.signature}>
                  {tutor.avatar ? (
                    <img className={styles.portrait} src={tutor.avatar} alt="" />
                  ) : (
                    <span className={styles.portrait} aria-hidden="true">
                      {tutor.name
                        .split(" ")
                        .map((part) => part[0])
                        .join("")}
                    </span>
                  )}
                  <span>
                    <span className={styles.signatureName}>{tutor.name}</span>
                    <span className={styles.signatureRole}>{tutor.role}</span>
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* What's inside */}
        <section className={styles.section} aria-labelledby="coffee-inside-title">
          <header className={styles.sectionHead}>
            <p className={styles.labelCaps}>Between the covers</p>
            <h2 id="coffee-inside-title" className={styles.h2}>
              What&rsquo;s inside
            </h2>
          </header>
          <ul className={styles.strip}>
            {COFFEE.formats.map((item) => (
              <li key={item.name} className={styles.stripItem}>
                <FormatIcon format={item.format} size={28} className={styles.stripIcon} />
                <h3 className={styles.stripTitle}>{item.name}</h3>
                <p className={styles.stripExample}>{item.example}</p>
              </li>
            ))}
          </ul>
        </section>

        {/* Pull quotes */}
        <section className={styles.quotes} aria-labelledby="coffee-quotes-title">
          <h2 id="coffee-quotes-title" className={styles.visuallyHidden}>
            What readers say
          </h2>
          <figure className={styles.pullQuote}>
            <blockquote>
              <p>&ldquo;{COFFEE.quotes[0].text}&rdquo;</p>
            </blockquote>
            <figcaption>— {COFFEE.quotes[0].who}</figcaption>
          </figure>
          <div className={styles.minorQuotes}>
            {COFFEE.quotes.slice(1).map((quote) => (
              <figure key={quote.who} className={styles.minorQuote}>
                <blockquote>
                  <p>&ldquo;{quote.text}&rdquo;</p>
                </blockquote>
                <figcaption>— {quote.who}</figcaption>
              </figure>
            ))}
          </div>
        </section>

        {/* Live session */}
        <section id="live" className={styles.section} aria-labelledby="coffee-live-title">
          <div className={styles.notice}>
            <div className={styles.noticeDate} aria-hidden="true">
              <span className={styles.noticeDay}>{live.date}</span>
              {live.time && <span className={styles.noticeTime}>{live.time}</span>}
            </div>
            <div>
              <p className={styles.labelCaps}>{COFFEE.live.label}</p>
              <h2 id="coffee-live-title" className={styles.h3}>
                {live.title}
                <span className={styles.visuallyHidden}>
                  , {live.date} {live.time}
                </span>
              </h2>
              <p className={styles.bodyText}>{live.text}</p>
              <Link to={webinarHref(webinar)} className={styles.textLink}>
                Reserve a seat
              </Link>
            </div>
          </div>
        </section>

        {/* Pricing */}
        <section className={styles.section} aria-labelledby="coffee-pricing-title">
          <header className={styles.sectionHead}>
            <p className={styles.labelCaps}>Subscriptions &amp; single issues</p>
            <h2 id="coffee-pricing-title" className={styles.h2}>
              Pricing
            </h2>
          </header>
          <div className={styles.pricing}>
            <article className={styles.priceCard} aria-labelledby="coffee-price-single">
              <h3 id="coffee-price-single" className={styles.h3}>
                {COFFEE.pricing.single.name}
              </h3>
              <p className={styles.price}>{data.coursePrice ?? COFFEE.pricing.single.price}</p>
              <ul className={styles.priceList}>
                {COFFEE.pricing.single.items.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
              <Link to={data.courseHref} className={styles.buttonPrimary}>
                Enrol in the course
              </Link>
            </article>
            <article className={styles.priceCard} aria-labelledby="coffee-price-bundle">
              <p className={styles.stamp}>Package</p>
              <h3 id="coffee-price-bundle" className={styles.h3}>
                {bundle?.name || COFFEE.pricing.bundle.name}
              </h3>
              <p className={bundlePrice ? styles.price : styles.priceNote}>
                {bundlePrice ?? "Course + live masterclass, one price"}
              </p>
              <ul className={styles.priceList}>
                {COFFEE.pricing.bundle.items.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
              <Link to={bundleLink} className={styles.buttonGhost}>
                See the package
              </Link>
            </article>
          </div>
        </section>

        {/* FAQ + newsletter */}
        <div className={styles.twoUp}>
          <section aria-labelledby="coffee-faq-title">
            <h2 id="coffee-faq-title" className={styles.h2}>
              Questions
            </h2>
            <div className={styles.faq}>
              {COFFEE.faq.map((item) => (
                <details key={item.q} className={styles.faqItem}>
                  <summary>{item.q}</summary>
                  <p>{item.a}</p>
                </details>
              ))}
            </div>
          </section>
          <section id="field-notes" className={styles.newsletter} aria-labelledby="coffee-news-title">
            <p className={styles.labelCaps}>Monthly, by post-office standards</p>
            <h2 id="coffee-news-title" className={styles.h2}>
              Field notes
            </h2>
            <p className={styles.bodyText}>
              One letter a month: a coffee we are drinking, a technique worth trying and a note from
              the farm. Create a free account to subscribe.
            </p>
            <form className={styles.form} onSubmit={onNewsletter}>
              <label htmlFor="coffee-email">Email address</label>
              <div className={styles.formRow}>
                <input
                  id="coffee-email"
                  type="email"
                  autoComplete="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
                <button type="submit" className={styles.buttonPrimary}>
                  Subscribe
                </button>
              </div>
            </form>
          </section>
        </div>
      </main>

      <footer className={styles.footer}>
        <p className={styles.footerMark}>{brand}</p>
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
        <p className={styles.colophon}>Set in Playfair Display and Plus Jakarta Sans.</p>
      </footer>
    </div>
  );
};

export default CoffeeLanding;
