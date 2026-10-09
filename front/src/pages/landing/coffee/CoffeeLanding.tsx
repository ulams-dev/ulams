import React, { useState } from "react";
import { Helmet } from "react-helmet";
import { Link, useHistory } from "react-router-dom";
import routeRoutes from "@/components/Routes/routes";
import { useBareLayout } from "@/components/_App/bareLayout";
import { InPageLink, SkipLink } from "../shared/InPageLink";
import { useMenu } from "../shared/useMenu";
import { FormatIcon } from "../shared/FormatIcon";
import { FORMAT_LABELS, type FormatKey } from "../shared/formats";
import { formatDayMonth, formatMinutes, formatMoney, formatTime, toRoman } from "../shared/format";
import { fullName, productHref, useLandingData, webinarHref } from "../shared/useLandingData";
import { COFFEE } from "./content";
import styles from "./CoffeeLanding.module.css";

type IconName = "arrow" | "check" | "minus" | "seal" | "star" | "quote" | "chevron" | "leaf";

/** Small line icons used next to visible text; always decorative. */
const Icon: React.FC<{ name: IconName; className?: string }> = ({ name, className }) => (
  <svg
    className={className}
    viewBox="0 0 24 24"
    width="1em"
    height="1em"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.6"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    {name === "arrow" && <path d="M5 12h14M13 6l6 6-6 6" />}
    {name === "check" && <path d="M5 12.5l4.5 4.5L19 7.5" />}
    {name === "minus" && <path d="M6 12h12" />}
    {name === "seal" && (
      <>
        <path d="M12 2.8l2.3 1.7 2.8-.2 1 2.7 2.4 1.5-.8 2.7.8 2.7-2.4 1.5-1 2.7-2.8-.2L12 21.2l-2.3-1.7-2.8.2-1-2.7-2.4-1.5.8-2.7-.8-2.7 2.4-1.5 1-2.7 2.8.2z" />
        <path d="M8.5 12l2.4 2.4 4.6-4.8" />
      </>
    )}
    {name === "star" && (
      <path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8L3.5 9.7l5.9-.8z" />
    )}
    {name === "quote" && (
      <path
        d="M9.5 7C6.6 7.9 5 10.2 5 13.2V17h4.6v-4.4H7.4c.1-1.7 1-2.9 2.6-3.6zm9 0c-2.9.9-4.5 3.2-4.5 6.2V17h4.6v-4.4h-2.2c.1-1.7 1-2.9 2.6-3.6z"
        fill="currentColor"
        stroke="none"
      />
    )}
    {name === "chevron" && <path d="M6 9l6 6 6-6" />}
    {name === "leaf" && <path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14zM5 19l7-7" />}
  </svg>
);

/** Hand-drawn style signature flourish. */
const Flourish: React.FC<{ variant: 0 | 1 }> = ({ variant }) => (
  <svg className={styles.flourish} viewBox="0 0 120 24" aria-hidden="true" focusable="false">
    {variant === 0 ? (
      <path d="M2 18c10-2 14-14 22-12 7 2-4 14 3 13 9-1 12-9 22-9 12 0 22 4 40 2s20-4 29-6" />
    ) : (
      <path d="M2 14c8-8 12-8 16 0s9 8 13 0 9-9 14-1 9 9 14 1 9-9 14-1 9 8 14 0 9-8 13-1 8 7 18 1" />
    )}
  </svg>
);

const pad2 = (n: number) => String(n).padStart(2, "0");

const CoffeeLanding: React.FC = () => {
  useBareLayout();
  const history = useHistory();
  const data = useLandingData(COFFEE.courseTitleHint);
  const menu = useMenu();
  const [email, setEmail] = useState("");

  const brand = data.companyName || COFFEE.brand;
  const course = data.course;

  const chapters = data.lessons.length
    ? data.lessons.map((lesson, i) => {
        const brief = COFFEE.chapters[i];
        return {
          // API titles may carry their own numeral ("I. Origins"); the list prints its own.
          title: lesson.title.replace(/^[IVXLC]+\.\s+/, ""),
          kicker: brief?.kicker ?? "",
          blurb: lesson.summary || brief?.blurb || "",
          minutes: lesson.minutes || brief?.minutes || 0,
          topics: lesson.topics.length
            ? lesson.topics.map((t) => ({
                title: t.title,
                format: t.format,
                minutes: t.minutes,
                preview: t.preview,
              }))
            : (brief?.topics ?? []),
        };
      })
    : COFFEE.chapters;

  const topicTotal = chapters.reduce((sum, c) => sum + c.topics.length, 0);
  const minuteTotal = chapters.reduce((sum, c) => sum + c.minutes, 0);
  const hours = Math.max(1, Math.round(minuteTotal / 60));
  const facts: Array<[string, string, string]> = COFFEE.hero.facts.map(([term, value, note]) =>
    term === "Length" ? [term, `~${hours} hours`, `${chapters.length} chapters · ${topicTotal} topics`] : [term, value, note]
  );
  // Folio page numbers for the contents list: four pages per topic, starting on page 8.
  let page = 8;
  const pages = chapters.map((c) => {
    const at = page;
    page += Math.max(c.topics.length, 1) * 4;
    return at;
  });

  const tutors = COFFEE.tutors.map((brief, i) => {
    const fromApi =
      data.tutors.find((t) => fullName(t) === brief.name) ?? data.tutors[i] ?? undefined;
    return { ...brief, name: fromApi ? fullName(fromApi) || brief.name : brief.name };
  });

  const webinar = data.webinars[0];
  const webinarStart = (webinar as { active_from?: string } | undefined)?.active_from;
  const live = {
    title: webinar?.name || COFFEE.live.title,
    date: formatDayMonth(webinarStart) ?? COFFEE.live.date,
    time: webinar ? (formatTime(webinarStart) ?? "") : COFFEE.live.time,
  };

  const bundle = data.products.find((p) => /taste|bundle|package/i.test(p.name ?? ""));
  const bundlePrice = bundle ? formatMoney(bundle.gross_price ?? bundle.price, data.currency) : null;
  const bundleLink = productHref(bundle) ?? data.courseHref;
  const coursePrice = data.coursePrice ?? COFFEE.pricing.single.price;

  const onNewsletter = (event: React.FormEvent) => {
    event.preventDefault();
    history.push({ pathname: routeRoutes.register, state: { email } });
  };

  const navItems = (
    <>
      <li>
        <InPageLink to="syllabus" onClick={menu.close}>
          Chapters
        </InPageLink>
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
        <InPageLink to="pricing" onClick={menu.close}>
          Pricing
        </InPageLink>
      </li>
      <li>
        <InPageLink to="field-notes" onClick={menu.close}>
          Field notes
        </InPageLink>
      </li>
      <li>
        <Link to={routeRoutes.courses}>All courses</Link>
      </li>
      <li className={styles.navSignIn}>
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
        <meta name="theme-color" content="#FFF8F5" />
      </Helmet>
      <SkipLink className={styles.skip} />

      <header className={styles.masthead}>
        <div className={styles.topStrip}>
          <div className={styles.wrap}>
            <p className={styles.topStripText}>
              <span className={styles.dot} aria-hidden="true" />
              {COFFEE.hero.volume} · {COFFEE.tagline} · Next live cupping: {live.date}
            </p>
            <p className={styles.topStripMeta}>
              <span>{data.currency}</span>
              <span aria-hidden="true">/</span>
              <span>EN</span>
            </p>
          </div>
        </div>
        <div className={`${styles.wrap} ${styles.mastheadRow}`}>
          <Link to={routeRoutes.home} className={styles.wordmark}>
            <span className={styles.wordmarkName}>{brand}</span>
            <span className={styles.wordmarkTag}>{COFFEE.tagline}</span>
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
          <div className={styles.mastheadActions}>
            <Link to={routeRoutes.login} className={styles.signIn}>
              Sign in
            </Link>
            <Link to={data.courseHref} className={styles.buttonPrimary}>
              Start free chapter
            </Link>
          </div>
        </div>
      </header>

      <main id="landing-main" className={styles.main}>
        <div className={styles.ribbon}>
          <div className={styles.wrap}>
            <p>
              <span className={styles.dotAccent} aria-hidden="true" />
              {COFFEE.ribbon.left}
            </p>
            <p>{COFFEE.ribbon.right}</p>
          </div>
        </div>

        {/* Hero */}
        <section className={styles.hero} aria-labelledby="coffee-hero-title">
          <div className={styles.wrap}>
            <div className={styles.heroMeta}>
              <p className={styles.metaChip}>
                <strong>{COFFEE.hero.volume}</strong>
                <span aria-hidden="true">·</span>
                {chapters.length} chapters
                <span aria-hidden="true">·</span>
                {topicTotal} topics
                <span aria-hidden="true">·</span>~{hours} hours
              </p>
              <p className={styles.curated}>{COFFEE.hero.curated}</p>
            </div>
            <div className={styles.heroGrid}>
              <div className={styles.heroText}>
                <p className={styles.kicker}>{COFFEE.hero.kicker}</p>
                <h1 id="coffee-hero-title" className={styles.heroTitle}>
                  {COFFEE.hero.title}
                </h1>
                <p className={styles.heroSub}>{COFFEE.hero.sub}</p>
                <p className={styles.heroSummary}>{course?.summary || COFFEE.hero.summary}</p>
                <div className={styles.actions}>
                  <Link to={data.courseHref} className={styles.buttonPrimary}>
                    Start the free chapter
                    <Icon name="arrow" className={styles.buttonIcon} />
                  </Link>
                  <InPageLink to="syllabus" className={styles.italicLink}>
                    See the complete syllabus <span aria-hidden="true">↓</span>
                  </InPageLink>
                </div>
                <dl className={styles.facts}>
                  {facts.map(([term, value, note]) => (
                    <div key={term} className={styles.fact}>
                      <dt>{term}</dt>
                      <dd>
                        <span className={styles.factValue}>{value}</span>
                        <span className={styles.factNote}>{note}</span>
                      </dd>
                    </div>
                  ))}
                </dl>
              </div>
              <figure className={styles.plate}>
                <div className={styles.plateMat}>
                  <img
                    className={styles.plateImage}
                    src={COFFEE.hero.image}
                    alt={COFFEE.hero.imageAlt}
                    width={1408}
                    height={768}
                  />
                </div>
                <figcaption className={styles.plateCaption}>
                  <span className={styles.plateQuote}>“{COFFEE.hero.caption}”</span>
                  <span className={styles.plateMeta}>
                    <span>{COFFEE.hero.plate}</span>
                    <span className={styles.plateMetaAccent}>{COFFEE.hero.plateNote}</span>
                  </span>
                </figcaption>
                <div className={styles.stamp}>
                  <Icon name="seal" className={styles.stampIcon} />
                  <p>
                    <span className={styles.stampTitle}>{COFFEE.hero.stamp.title}</span>
                    <span className={styles.stampText}>{COFFEE.hero.stamp.text}</span>
                  </p>
                </div>
              </figure>
            </div>
          </div>
        </section>

        {/* Table of contents */}
        <section id="syllabus" className={styles.syllabus} aria-labelledby="coffee-toc-title">
          <div className={styles.wrap}>
            <header className={styles.splitHead}>
              <div>
                <p className={styles.kicker}>{COFFEE.syllabus.label}</p>
                <h2 id="coffee-toc-title" className={styles.h2}>
                  {COFFEE.syllabus.title}
                </h2>
              </div>
              <p className={styles.headNote}>
                {course?.title || COFFEE.courseTitle}. {COFFEE.syllabus.note}
              </p>
            </header>
            <ol className={styles.toc}>
              {chapters.map((chapter, i) => {
                const hasPreview = chapter.topics.some((t) => t.preview);
                return (
                  <li key={`${i}-${chapter.title}`} className={styles.tocRow}>
                    <span className={styles.numeral} aria-hidden="true">
                      {toRoman(i + 1)}
                    </span>
                    <div className={styles.tocBody}>
                      <p className={styles.tocChips}>
                        {hasPreview && <span className={styles.chipPreview}>Free preview</span>}
                        {chapter.kicker && <span className={styles.tocKicker}>{chapter.kicker}</span>}
                      </p>
                      <h3 className={styles.tocTitle}>
                        <span className={styles.visuallyHidden}>Chapter {i + 1}: </span>
                        {chapter.title}
                      </h3>
                      {chapter.blurb && <p className={styles.tocBlurb}>{chapter.blurb}</p>}
                      {chapter.topics.length > 0 && (
                        <ul className={styles.topics} aria-label={`Topics in ${chapter.title}`}>
                          {chapter.topics.map((topic, t) => (
                            <li key={`${t}-${topic.title}`} className={styles.topic}>
                              {topic.format && (
                                <FormatIcon
                                  format={topic.format as FormatKey}
                                  size={16}
                                  className={styles.topicIcon}
                                />
                              )}
                              <span>
                                {topic.title}
                                {(topic.format || topic.minutes > 0) && (
                                  <span className={styles.topicMeta}>
                                    {" "}
                                    (
                                    {[
                                      topic.format ? FORMAT_LABELS[topic.format as FormatKey] : null,
                                      topic.minutes > 0 ? `${topic.minutes} min` : null,
                                    ]
                                      .filter(Boolean)
                                      .join(" · ")}
                                    )
                                  </span>
                                )}
                              </span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                    <div className={styles.tocAside}>
                      <span className={styles.tocPage}>p. {pad2(pages[i])}</span>
                      {chapter.minutes > 0 && (
                        <span className={styles.tocMinutes}>{formatMinutes(chapter.minutes)}</span>
                      )}
                      <span className={hasPreview ? styles.statusOpen : styles.status}>
                        {hasPreview ? "Free to start" : i === chapters.length - 1 ? "Capstone" : "Included"}
                      </span>
                    </div>
                  </li>
                );
              })}
            </ol>
          </div>
        </section>

        {/* Letter from the tutors */}
        <section id="tutors" className={styles.letterSection} aria-labelledby="coffee-letter-title">
          <div className={styles.wrap}>
            <div className={styles.letterCard}>
              <ul className={styles.portraits} aria-label="Your tutors">
                {tutors.map((tutor) => (
                  <li key={tutor.name} className={styles.portraitCard}>
                    <img
                      className={styles.portraitImage}
                      src={tutor.photo}
                      alt={tutor.photoAlt}
                      width={1408}
                      height={768}
                      loading="lazy"
                    />
                    <p className={styles.portraitRole}>{tutor.role}</p>
                    <p className={styles.portraitName}>{tutor.name}</p>
                    <p className={styles.portraitPlace}>{tutor.place}</p>
                  </li>
                ))}
              </ul>
              <div className={styles.letter}>
                <p className={styles.kicker}>{COFFEE.letter.label}</p>
                <h2 id="coffee-letter-title" className={styles.letterTitle}>
                  {COFFEE.letter.title}
                </h2>
                {COFFEE.letter.paragraphs.map((paragraph, i) => (
                  <p key={i} className={i === 0 ? styles.dropCap : styles.letterText}>
                    {paragraph}
                  </p>
                ))}
                <blockquote className={styles.letterQuote}>
                  <p>“{COFFEE.letter.quote}”</p>
                </blockquote>
                <ul className={styles.signatures} aria-label="Signed">
                  {tutors.map((tutor, i) => (
                    <li key={tutor.name}>
                      <Flourish variant={i === 0 ? 0 : 1} />
                      <span className={styles.signatureName}>
                        {tutor.name} · {tutor.signedFrom}
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </section>

        {/* What's inside */}
        <section className={styles.inside} aria-labelledby="coffee-inside-title">
          <div className={styles.wrap}>
            <header className={styles.centerHead}>
              <p className={styles.kicker}>{COFFEE.inside.label}</p>
              <h2 id="coffee-inside-title" className={styles.h2}>
                {COFFEE.inside.title}
              </h2>
              <p className={styles.centerIntro}>{COFFEE.inside.intro}</p>
            </header>
            <ul className={styles.formatGrid}>
              {COFFEE.formats.map((item) => (
                <li key={item.name} className={styles.formatCard} data-format={item.format}>
                  <p className={styles.formatTag}>{item.tag}</p>
                  <h3 className={styles.formatTitle}>
                    <FormatIcon format={item.format} size={18} className={styles.formatIcon} />
                    {item.name}
                  </h3>
                  <p className={styles.formatText}>{item.text}</p>
                  <p className={styles.formatFoot}>{item.foot}</p>
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* Testimonials */}
        <section className={styles.quotes} aria-labelledby="coffee-quotes-title">
          <div className={styles.wrap}>
            <h2 id="coffee-quotes-title" className={styles.visuallyHidden}>
              What readers say
            </h2>
            <figure className={styles.pullQuote}>
              <Icon name="quote" className={styles.quoteMark} />
              <blockquote>
                <p>“{COFFEE.quotes[0].text}”</p>
              </blockquote>
              <figcaption>
                <span className={styles.quoteName}>{COFFEE.quotes[0].who}</span>
                <span className={styles.quoteWhere}>{COFFEE.quotes[0].where}</span>
              </figcaption>
            </figure>
            <div className={styles.minorQuotes}>
              {COFFEE.quotes.slice(1).map((quote) => (
                <figure key={quote.who} className={styles.minorQuote}>
                  <p className={styles.stars}>
                    {[0, 1, 2, 3, 4].map((n) => (
                      <Icon key={n} name="star" />
                    ))}
                    <span className={styles.visuallyHidden}>Five out of five</span>
                  </p>
                  <blockquote>
                    <p>“{quote.text}”</p>
                  </blockquote>
                  <figcaption>
                    <span className={styles.minorName}>{quote.who}</span>
                    <span className={styles.minorWhere}>{quote.where}</span>
                  </figcaption>
                </figure>
              ))}
            </div>
          </div>
        </section>

        {/* Live session */}
        <section id="live" className={styles.liveSection} aria-labelledby="coffee-live-title">
          <div className={styles.wrap}>
            <div className={styles.liveCard}>
              <div className={styles.liveText}>
                <p className={styles.liveChip}>{COFFEE.live.label}</p>
                <h2 id="coffee-live-title" className={styles.liveTitle}>
                  {live.title} — {live.date}
                  {live.time && `, ${live.time}`}
                </h2>
                <p className={styles.bodyText}>{COFFEE.live.text}</p>
                <dl className={styles.liveFacts}>
                  {COFFEE.live.facts.map(([term, value, note]) => (
                    <div key={term}>
                      <dt>{term}</dt>
                      <dd>
                        <span className={styles.liveFactValue}>{value}</span>
                        <span className={styles.liveFactNote}>{note}</span>
                      </dd>
                    </div>
                  ))}
                </dl>
                <p className={styles.liveActions}>
                  <Link to={webinarHref(webinar)} className={styles.buttonPrimary}>
                    Reserve a seat
                  </Link>
                  <span className={styles.liveNote}>{COFFEE.live.note}</span>
                </p>
              </div>
              <figure className={styles.livePlate}>
                <img
                  src={COFFEE.live.image}
                  alt={COFFEE.live.imageAlt}
                  width={1408}
                  height={768}
                  loading="lazy"
                />
                <figcaption>{COFFEE.live.caption}</figcaption>
              </figure>
            </div>
          </div>
        </section>

        {/* Pricing */}
        <section id="pricing" className={styles.pricingSection} aria-labelledby="coffee-pricing-title">
          <div className={styles.wrap}>
            <header className={styles.centerHead}>
              <p className={styles.kicker}>{COFFEE.pricing.label}</p>
              <h2 id="coffee-pricing-title" className={styles.h2}>
                {COFFEE.pricing.title}
              </h2>
              <p className={styles.centerIntro}>{COFFEE.pricing.intro}</p>
            </header>
            <div className={styles.pricing}>
              <article className={styles.priceCard} aria-labelledby="coffee-price-single">
                <p className={styles.priceHead}>
                  <span>{COFFEE.pricing.single.label}</span>
                  <span className={styles.priceChip}>{COFFEE.pricing.single.chip}</span>
                </p>
                <h3 id="coffee-price-single" className={styles.priceName}>
                  {COFFEE.pricing.single.name}
                </h3>
                <p className={styles.priceText}>{COFFEE.pricing.single.text}</p>
                <p className={styles.price}>
                  {coursePrice}
                  <span className={styles.priceUnit}>one-time payment</span>
                </p>
                <ul className={styles.priceList}>
                  {COFFEE.pricing.single.items.map((item) => (
                    <li key={item}>
                      <Icon name="check" className={styles.priceIcon} />
                      {item}
                    </li>
                  ))}
                  {COFFEE.pricing.single.excluded.map((item) => (
                    <li key={item} className={styles.priceExcluded}>
                      <Icon name="minus" className={styles.priceIcon} />
                      <span className={styles.visuallyHidden}>Not included: </span>
                      {item}
                    </li>
                  ))}
                </ul>
                <Link to={data.courseHref} className={styles.buttonInk}>
                  {COFFEE.pricing.single.cta}
                </Link>
                <p className={styles.priceFoot}>{COFFEE.pricing.single.foot}</p>
              </article>
              <article
                className={`${styles.priceCard} ${styles.priceCardFeatured}`}
                aria-labelledby="coffee-price-bundle"
              >
                <p className={styles.ribbonTag}>{COFFEE.pricing.bundle.ribbon}</p>
                <p className={styles.priceHead}>
                  <span className={styles.priceHeadAccent}>{COFFEE.pricing.bundle.label}</span>
                  <span className={styles.priceChipAccent}>{COFFEE.pricing.bundle.chip}</span>
                </p>
                <h3 id="coffee-price-bundle" className={styles.priceName}>
                  {bundle?.name || COFFEE.pricing.bundle.name}
                </h3>
                <p className={styles.priceText}>{COFFEE.pricing.bundle.text}</p>
                {bundlePrice ? (
                  <p className={`${styles.price} ${styles.priceAccent}`}>
                    {bundlePrice}
                    <span className={styles.priceUnit}>one-time payment</span>
                  </p>
                ) : (
                  <p className={styles.priceNote}>Course + live masterclass, one price</p>
                )}
                <ul className={styles.priceList}>
                  {COFFEE.pricing.bundle.items.map((item, i) => (
                    <li key={item} className={i === 0 ? styles.priceStrong : undefined}>
                      <Icon name="check" className={styles.priceIcon} />
                      {item}
                    </li>
                  ))}
                </ul>
                <Link to={bundleLink} className={styles.buttonPrimary}>
                  {COFFEE.pricing.bundle.cta}
                </Link>
                <p className={styles.priceFoot}>{COFFEE.pricing.bundle.foot}</p>
              </article>
            </div>
          </div>
        </section>

        {/* FAQ + newsletter */}
        <section className={styles.faqSection} aria-labelledby="coffee-faq-title">
          <div className={styles.narrow}>
            <header className={styles.centerHead}>
              <p className={styles.kicker}>Before you begin</p>
              <h2 id="coffee-faq-title" className={styles.h2}>
                Frequently asked questions
              </h2>
            </header>
            <div className={styles.faq}>
              {COFFEE.faq.map((item) => (
                <details key={item.q} className={styles.faqItem}>
                  <summary>
                    <span>{item.q}</span>
                    <Icon name="chevron" className={styles.faqIcon} />
                  </summary>
                  <p>{item.a}</p>
                </details>
              ))}
            </div>

            <section
              id="field-notes"
              className={styles.newsletter}
              aria-labelledby="coffee-news-title"
            >
              <div>
                <p className={styles.kicker}>{COFFEE.newsletter.label}</p>
                <h2 id="coffee-news-title" className={styles.newsTitle}>
                  {COFFEE.newsletter.title}
                </h2>
                <p className={styles.newsText}>{COFFEE.newsletter.text}</p>
              </div>
              <form className={styles.form} onSubmit={onNewsletter}>
                <label htmlFor="coffee-email" className={styles.visuallyHidden}>
                  Email address
                </label>
                <input
                  id="coffee-email"
                  type="email"
                  autoComplete="email"
                  placeholder="you@example.com"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
                <button type="submit" className={styles.buttonPrimary}>
                  Subscribe
                </button>
              </form>
            </section>
          </div>
        </section>
      </main>

      <footer className={styles.footer}>
        <div className={`${styles.wrap} ${styles.footerGrid}`}>
          <div>
            <p className={styles.kicker}>Colophon</p>
            <p className={styles.footerMark}>{brand}</p>
            <p className={styles.footerQuote}>“{COFFEE.colophon.quote}”</p>
            <p className={styles.footerSmall}>{COFFEE.colophon.text}</p>
          </div>
          <nav aria-labelledby="coffee-footer-index">
            <h2 id="coffee-footer-index" className={styles.footerHead}>
              Contents
            </h2>
            <ol className={styles.footerIndex}>
              {chapters.map((chapter, i) => (
                <li key={`${i}-${chapter.title}`}>
                  <span className={styles.footerNumeral} aria-hidden="true">
                    {toRoman(i + 1)}.
                  </span>
                  <InPageLink to="syllabus">{chapter.title}</InPageLink>
                </li>
              ))}
            </ol>
          </nav>
          <nav aria-labelledby="coffee-footer-visit">
            <h2 id="coffee-footer-visit" className={styles.footerHead}>
              Visit
            </h2>
            <ul className={styles.footerLinks}>
              <li>
                <Link to={routeRoutes.courses}>All courses</Link>
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
            <p className={styles.footerNote}>
              <Icon name="leaf" className={styles.footerLeaf} />
              One letter a month, never more.
            </p>
          </nav>
        </div>
        <div className={`${styles.wrap} ${styles.footerBar}`}>
          <p>
            © {new Date().getFullYear()} {brand}
          </p>
          <p className={styles.footerVolume}>
            <span className={styles.dot} aria-hidden="true" />
            {COFFEE.hero.volume} · {COFFEE.tagline}
          </p>
        </div>
      </footer>
    </div>
  );
};

export default CoffeeLanding;
