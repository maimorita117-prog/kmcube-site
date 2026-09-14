'use client';

import { useEffect, useRef, useState } from 'react';
import {
  ArrowDown, ArrowRight, BedDouble, CarFront, Check,
  Coffee, ExternalLink, Footprints, Globe2, Heart, Kayak, MapPin,
  MessageCircleHeart, ShieldCheck, Waves,
} from 'lucide-react';
import { ReservationSystem } from './reservation-system';

type Language = 'ja' | 'en';

type WebMcpContext = {
  registerTool: (tool: {
    name: string;
    title: string;
    description: string;
    inputSchema: object;
    annotations: { readOnlyHint: boolean; untrustedContentHint: boolean };
    execute: (input: unknown) => unknown;
  }, options?: { signal?: AbortSignal }) => void | Promise<void>;
};

declare global {
  interface Document { modelContext?: WebMcpContext }
}

const rentalBookingLinkProps = {
  href: '#booking',
} as const;

const serviceMeta = [
  { number: '01', icon: CarFront, kicker: 'RENT A CAR', tone: 'yellow' },
  { number: '02', icon: Coffee, kicker: 'SMALL CAFE', tone: 'coral' },
  { number: '03', icon: BedDouble, kicker: 'ISLAND STAY', tone: 'green' },
];

const translations = {
  ja: {
    metaTitle: 'KMCUBE｜屋久島の旅をひとつに',
    metaDescription: 'レンタカー、民泊、登山、アクティビティ、漁船遊覧を組み合わせて、あなたらしい屋久島の旅をつなぐKMCUBEの公式サイト。',
    homeLabel: 'KMCUBE ホーム', navLabel: 'メインナビゲーション', switchLabel: 'Switch to English',
    nav: ['旅のサービス', '旅のかたち', '私たちについて', '会社案内'], navBooking: 'レンタカー予約', navBookingShort: '予約',
    driveCaption: 'ゆっくり安全運転中',
    eyebrow: '屋久島の旅を、ひとつにつなぐ', heroLine1: 'めぐる。', heroLine2: 'くつろぐ。', heroLine3: '屋久島を好きになる。',
    heroPhotoAlt: '青い海に囲まれ、深い森と霧をまとう山々が連なる屋久島を上空から望む風景',
    heroLead: 'レンタカーで島をめぐり、ほっとひと息ついて、心地よく泊まる。KMCUBEが、あなたらしい屋久島時間をそっとお手伝いします。',
    checkCars: 'レンタカーの空きを見る', seeServices: 'サービスを見る',
    trust: '屋久島に根ざす観光サービス。関連会社はキューブ株式会社です。',
    mapAlt: '森、山、滝、川、温泉、港を描いた手描きの屋久島マップ', deerAlt: '手描きのヤクシカ', monkeyAlt: '手描きのヤクシマザル', wildlifeLabel: '永田周辺のヤクシカとヤクシマザル',
    places: ['永田', '宮之浦', '安房', '尾之間'], mapBadge1: '車でぐるり、島めぐり', mapBadge2: '旅の楽しさをまるごと',
    servicesTitle1: '島の旅を、', servicesTitle2: 'やさしくつなぐ。', servicesIntro: '移動、休憩、宿泊を別々に探す手間を少なく。KMCUBEなら、屋久島で過ごす時間をひと続きに相談できます。',
    services: [
      { title: 'レンタカー', copy: '到着したら、すぐに島時間へ。旅程に合わせて使いやすい一台をご案内します。', note: 'オンラインで空車確認・予約', status: '予約受付中' },
      { title: '簡易カフェ', copy: 'ドライブの途中に、ほっとひと息。屋久島の空気と一緒に楽しむ小さな休憩所です。', note: 'メニューは近日ご案内', status: 'COMING SOON' },
      { title: '民泊', copy: 'たくさん遊んだあとは、ゆっくり休む。島で暮らすように泊まれる場所を整えています。', note: 'お部屋情報は近日ご案内', status: 'COMING SOON' },
    ],
    journeyTitle1: '「借りる」だけで終わらない、', journeyTitle2: '旅の入口へ。', journeyCopy: '朝、到着口で車を受け取る。好きな景色に立ち寄り、カフェでひと休み。夜は島の暮らしを感じる宿へ。ひとつの窓口だから、旅程の相談もすっきり。', journeyCta: '旅程からレンタカーを探す',
    route: [
      { title: '車を受け取る', note: '島めぐりのスタート' },
      { title: 'カフェでひと息', note: '深呼吸したくなる休憩' },
      { title: '民泊でくつろぐ', note: '島の夜をゆっくり味わう' },
    ],
    futureTitle1: 'もっと深く、', futureTitle2: '屋久島と出会う。', futureCopy: 'これから、自然を安心して楽しむための体験案内も少しずつ。島での一日が、もっと豊かにつながっていきます。',
    hikeTitle: '登山案内', hikeCopy: '森の歩き方やコース選びを、旅程に合わせてご案内する計画です。', kayakTitle: 'カヤック案内', kayakCopy: '水の上から島の自然を味わう、小さな冒険を準備していきます。',
    aboutNote1: '旅の前から、', aboutNote2: 'おかえりまで。', aboutTitle1: '屋久島を楽しむ人に、', aboutTitle2: '近くて頼れる存在でありたい。',
    aboutCopy: 'KMCUBEは、屋久島で観光事業を始める会社です。まずはレンタカー、簡易カフェ、民泊から。島を訪れる方が迷わず、安心して、自分らしい旅を楽しめるように、サービス同士をやさしくつないでいきます。',
    promises: [
      { title: '分かりやすく', copy: '旅の情報と予約をひとつに' },
      { title: '相談しやすく', copy: '顔の見える温かなご案内' },
      { title: '島を大切に', copy: '自然への敬意を忘れない' },
    ],
    companyTitle: '会社案内', companyIntro: '小さく始めて、屋久島の旅に必要なものを丁寧に育てていきます。',
    companyNameLabel: '会社名', companyName: 'ケーエムキューブ（KMCUBE）', businessLabel: '事業内容', business: '屋久島における観光事業', businessDetail: 'レンタカー・民泊・登山案内・アクティビティ・漁船遊覧（計画中を含む）', futureLabel: '今後の展開', futureBusiness: '登山案内・カヤック等の自然体験・漁船遊覧', affiliateLabel: '関連会社', affiliate: 'キューブ株式会社',
    trustTitle: '安心して使える予約体験へ', trustCopy: 'システム開発を基盤とするキューブ株式会社の関連会社として、その知見も活かしながら、分かりやすく使いやすい予約サービスを目指します。',
    closingTitle1: '屋久島で、', closingTitle2: 'お待ちしています。', closingCopy: 'まずはレンタカーから。あなたの旅程に合う一台を一緒に考えます。',
    footerTagline: '屋久島の旅を、ひとつにつなぐ。', footerLinks: ['事業案内', '会社案内', '関連会社'],
  },
  en: {
    metaTitle: 'KMCUBE | Your Yakushima Journey, All in One Place',
    metaDescription: 'KMCUBE connects rental cars, stays, hiking, nature activities, and fishing boat cruises for a personal journey around Yakushima.',
    homeLabel: 'KMCUBE home', navLabel: 'Main navigation', switchLabel: '日本語に切り替える',
    nav: ['Services', 'Your island day', 'About us', 'Company'], navBooking: 'Book a rental car', navBookingShort: 'Book',
    driveCaption: 'Enjoying a safe, easy drive',
    eyebrow: 'Connecting every part of your Yakushima trip', heroLine1: 'Roam.', heroLine2: 'Unwind.', heroLine3: 'Fall in love with Yakushima.',
    heroPhotoAlt: 'An aerial view of Yakushima rising from the blue ocean, covered in deep forest and misty mountain ridges',
    heroLead: 'Explore the island by rental car, pause for a relaxing break, and settle into a comfortable stay. KMCUBE is here to help you enjoy Yakushima your own way.',
    checkCars: 'Check rental car availability', seeServices: 'Explore our services',
    trust: 'A locally rooted travel company on Yakushima, affiliated with Cube Inc.',
    mapAlt: 'Hand-painted map of Yakushima featuring forests, mountains, waterfalls, rivers, hot springs, and ports', deerAlt: 'Hand-painted Yakushika deer', monkeyAlt: 'Hand-painted Yakushima macaque', wildlifeLabel: 'Yakushika deer and Yakushima macaque near Nagata',
    places: ['Nagata', 'Miyanoura', 'Anbo', 'Onoaida'], mapBadge1: 'Drive around the island', mapBadge2: 'Enjoy the whole journey',
    servicesTitle1: 'One gentle connection', servicesTitle2: 'for your island journey.', servicesIntro: 'Spend less time arranging transport, breaks, and accommodation separately. KMCUBE helps connect your time on Yakushima through one friendly point of contact.',
    services: [
      { title: 'Rental cars', copy: 'Start enjoying island time as soon as you arrive. We will help you find a practical car that fits your itinerary.', note: 'Check availability and book online', status: 'BOOKING OPEN' },
      { title: 'Small café', copy: 'Pause and recharge during your drive with a small, welcoming place to enjoy the fresh island atmosphere.', note: 'Menu details coming soon', status: 'COMING SOON' },
      { title: 'Guesthouse stays', copy: 'After a full day of exploring, slow down and rest in a place that feels closer to everyday island life.', note: 'Room details coming soon', status: 'COMING SOON' },
    ],
    journeyTitle1: 'More than a rental car—', journeyTitle2: 'your gateway to the island.', journeyCopy: 'Pick up your car in the morning, stop wherever the scenery calls, take a café break, then settle into a stay that feels connected to island life. With one point of contact, planning stays simple.', journeyCta: 'Find a car for your itinerary',
    route: [
      { title: 'Pick up your car', note: 'Your island journey begins' },
      { title: 'Take a café break', note: 'Pause, relax, and breathe' },
      { title: 'Unwind at your stay', note: 'Enjoy a quiet island evening' },
    ],
    futureTitle1: 'Meet Yakushima', futureTitle2: 'on a deeper level.', futureCopy: 'We are gradually preparing guided experiences that help visitors enjoy the island’s nature with care and confidence.',
    hikeTitle: 'Hiking guidance', hikeCopy: 'We plan to help guests choose forest routes and walking styles that fit their itinerary.', kayakTitle: 'Kayak guidance', kayakCopy: 'A small adventure that lets you experience Yakushima’s nature from the water is also in the works.',
    aboutNote1: 'From before your trip', aboutNote2: 'until welcome home.', aboutTitle1: 'A friendly, reliable partner', aboutTitle2: 'for everyone who enjoys Yakushima.',
    aboutCopy: 'KMCUBE is a travel company beginning its journey on Yakushima with rental cars, a small café, and guesthouse stays. We gently connect these services so visitors can travel with confidence and enjoy the island in their own way.',
    promises: [
      { title: 'Simple', copy: 'Travel information and booking in one place' },
      { title: 'Approachable', copy: 'Warm guidance from people you can talk to' },
      { title: 'Island-minded', copy: 'Respect for Yakushima’s natural environment' },
    ],
    companyTitle: 'Company', companyIntro: 'Starting small and carefully growing the services a Yakushima journey needs.',
    companyNameLabel: 'Company', companyName: 'KMCUBE', businessLabel: 'Business', business: 'Tourism services on Yakushima', businessDetail: 'Rental cars, stays, hiking, activities, and fishing boat cruises (including planned services)', futureLabel: 'Future plans', futureBusiness: 'Hiking, kayaking and other nature activities, plus fishing boat cruises', affiliateLabel: 'Affiliated company', affiliate: 'Cube Inc.',
    trustTitle: 'Building a booking experience you can trust', trustCopy: 'As an affiliate of Cube Inc., a company grounded in software development, we aim to use that expertise to create a clear and easy-to-use booking service.',
    closingTitle1: 'We look forward to', closingTitle2: 'welcoming you to Yakushima.', closingCopy: 'We are starting with rental cars and will help you choose one that fits your itinerary.',
    footerTagline: 'Connecting every part of your Yakushima journey.', footerLinks: ['Services', 'Company', 'Affiliate'],
  },
} as const;

const desktopDriveRoute = [
  { x: .78, y: .59 }, { x: .87, y: .76 }, { x: .13, y: .79 },
  { x: .12, y: .70 }, { x: .88, y: .76 }, { x: .84, y: .69 },
  { x: .11, y: .78 }, { x: .14, y: .70 }, { x: .87, y: .78 },
  { x: .76, y: .70 },
];

const mobileDriveRoute = [
  { x: .74, y: .76 }, { x: .82, y: .82 }, { x: .12, y: .82 },
  { x: .16, y: .76 }, { x: .82, y: .81 }, { x: .76, y: .75 },
  { x: .10, y: .82 }, { x: .16, y: .76 }, { x: .82, y: .82 },
  { x: .72, y: .76 },
];

function ScrollDriveCar({ caption }: { caption: string }) {
  const carRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const car = carRef.current;
    if (!car) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let frame = 0;
    let stopTimer = 0;

    const renderPosition = () => {
      frame = 0;
      if (reducedMotion.matches) {
        car.style.setProperty('--drive-opacity', '0');
        return;
      }

      const scrollRange = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      const progress = Math.min(1, Math.max(0, window.scrollY / scrollRange));
      const route = window.innerWidth <= 620 ? mobileDriveRoute : desktopDriveRoute;
      const routePosition = progress * (route.length - 1);
      const segment = Math.min(route.length - 2, Math.floor(routePosition));
      const localProgress = routePosition - segment;
      const eased = localProgress * localProgress * (3 - 2 * localProgress);
      const from = route[segment];
      const to = route[segment + 1];
      const x = from.x + (to.x - from.x) * eased;
      const y = from.y + (to.y - from.y) * eased;
      const facing = to.x >= from.x ? 1 : -1;
      const tilt = Math.max(-4, Math.min(4, (to.y - from.y) * 22));
      const bob = Math.sin(progress * Math.PI * 48) * 2;
      const fadeIn = Math.min(1, Math.max(0, (progress - .002) / .018));
      const fadeOut = Math.min(1, Math.max(0, (1 - progress) / .035));

      car.style.setProperty('--drive-x', `${x * window.innerWidth}px`);
      car.style.setProperty('--drive-y', `${y * window.innerHeight}px`);
      car.style.setProperty('--drive-facing', String(facing));
      car.style.setProperty('--drive-tilt', `${tilt}deg`);
      car.style.setProperty('--drive-bob', `${bob}px`);
      car.style.setProperty('--drive-opacity', String(fadeIn * fadeOut));
    };

    const scheduleRender = () => {
      car.classList.add('is-moving');
      window.clearTimeout(stopTimer);
      stopTimer = window.setTimeout(() => car.classList.remove('is-moving'), 180);
      if (!frame) frame = window.requestAnimationFrame(renderPosition);
    };

    const handlePreferenceChange = () => renderPosition();
    window.addEventListener('scroll', scheduleRender, { passive: true });
    window.addEventListener('resize', scheduleRender);
    reducedMotion.addEventListener('change', handlePreferenceChange);
    renderPosition();

    return () => {
      window.removeEventListener('scroll', scheduleRender);
      window.removeEventListener('resize', scheduleRender);
      reducedMotion.removeEventListener('change', handlePreferenceChange);
      window.clearTimeout(stopTimer);
      if (frame) window.cancelAnimationFrame(frame);
    };
  }, []);

  return (
    <div className="scroll-drive-car" ref={carRef} aria-hidden="true">
      <span className="scroll-drive-caption">{caption}</span>
      <span className="scroll-car-dust"><i /><i /><i /></span>
      <span className="scroll-car-vehicle"><img src="./yellow-car-transparent.png" alt="" /></span>
    </div>
  );
}

export default function Home() {
  const [language, setLanguage] = useState<Language>('ja');
  const t = translations[language];

  useEffect(() => {
    let preferred: Language = 'ja';
    try {
      const saved = window.localStorage.getItem('kmcube-language');
      if (saved === 'ja' || saved === 'en') preferred = saved;
      else if (window.navigator.language.toLowerCase().startsWith('en')) preferred = 'en';
    } catch {
      preferred = window.navigator.language.toLowerCase().startsWith('en') ? 'en' : 'ja';
    }
    setLanguage(preferred);
  }, []);

  useEffect(() => {
    document.documentElement.lang = language;
    document.title = t.metaTitle;
    document.querySelector('meta[name="description"]')?.setAttribute('content', t.metaDescription);
  }, [language, t.metaDescription, t.metaTitle]);

  function switchLanguage() {
    const next: Language = language === 'ja' ? 'en' : 'ja';
    setLanguage(next);
    try { window.localStorage.setItem('kmcube-language', next); } catch { /* Language still switches for this visit. */ }
  }

  useEffect(() => {
    const context = document.modelContext;
    if (!context?.registerTool) return;
    const lifecycle = new AbortController();

    const registration = context.registerTool({
      name: 'open_rental_car_booking',
      title: 'レンタカー予約サイトを開く',
      description: 'KMCUBEの専用レンタカー予約サイトを開きます。',
      inputSchema: {
        type: 'object',
        properties: {},
        additionalProperties: false,
      },
      annotations: { readOnlyHint: false, untrustedContentHint: false },
      execute() {
        window.location.hash = 'booking';
        return { status: 'opened', location: '#booking' };
      },
    }, { signal: lifecycle.signal });
    void Promise.resolve(registration).catch(() => undefined);
    return () => lifecycle.abort();
  }, []);

  return (
    <main>
      <ScrollDriveCar caption={t.driveCaption} />
      <header className="site-header">
        <a className="brand brand-logo" href="#top" aria-label={t.homeLabel}>
          <img src="./kmcube-logo-transparent.png" alt="KMCUBE YAKUSHIMA・ADVENTURES" width="610" height="603" />
        </a>
        <nav aria-label={t.navLabel}>
          <a href="#services">{t.nav[0]}</a>
          <a href="#story">{t.nav[1]}</a>
          <a href="#about">{t.nav[2]}</a>
          <a href="#company">{t.nav[3]}</a>
        </nav>
        <div className="header-actions">
          <button className="language-toggle" type="button" onClick={switchLanguage} aria-label={t.switchLabel}>
            <Globe2 aria-hidden="true" /><span>{language === 'ja' ? 'EN' : '日本語'}</span>
          </button>
          <a className="nav-cta" {...rentalBookingLinkProps}><span className="full-label">{t.navBooking}</span><span className="short-label">{t.navBookingShort}</span> <ArrowRight aria-hidden="true" /></a>
        </div>
      </header>

      <section className="hero" id="top">
        <img className="hero-photo" src="./hero-yakushima-aerial-v3.jpg" alt={t.heroPhotoAlt} width="1536" height="1024" fetchPriority="high" />
        <div className="hero-photo-shade" aria-hidden="true" />
        <div className="hero-copy">
          <p className="eyebrow"><MapPin aria-hidden="true" /> {t.eyebrow}</p>
          <h1>{t.heroLine1}<br />{t.heroLine2}<br /><em>{t.heroLine3}</em></h1>
          <p className="hero-lead">{t.heroLead}</p>
          <div className="hero-actions">
            <a className="button button-primary" {...rentalBookingLinkProps}>{t.checkCars} <ArrowRight aria-hidden="true" /></a>
            <a className="text-link" href="#services">{t.seeServices} <ArrowDown aria-hidden="true" /></a>
          </div>
          <p className="trust-note"><ShieldCheck aria-hidden="true" /> {t.trust}</p>
        </div>
        <p className="hero-photo-note">Wild island, warm journey.</p>
        <div className="hero-photo-caption"><span>WORLD NATURAL HERITAGE</span><strong>YAKUSHIMA, JAPAN</strong></div>
      </section>

      <ReservationSystem language={language} />

      <section className="section services" id="services">
        <div className="section-heading">
          <div><p className="section-kicker">OUR SERVICES</p><h2>{t.servicesTitle1}<br />{t.servicesTitle2}</h2></div>
          <p>{t.servicesIntro}</p>
        </div>
        <div className="service-grid">
          {serviceMeta.map((service, index) => {
            const Icon = service.icon;
            const localized = t.services[index];
            return (
              <article className={`service-card ${service.tone}`} key={service.number}>
                <div className="service-top"><span>{service.number}</span><b>{localized.status}</b></div>
                <Icon aria-hidden="true" />
                <p className="service-kicker">{service.kicker}</p>
                <h3>{localized.title}</h3>
                <p className="service-copy">{localized.copy}</p>
                <p className="service-note"><Check aria-hidden="true" /> {localized.note}</p>
              </article>
            );
          })}
        </div>
      </section>

      <section className="journey-section" id="story">
        <div className="journey-copy">
          <p className="section-kicker light">ONE STOP JOURNEY</p>
          <h2>{t.journeyTitle1}<br />{t.journeyTitle2}</h2>
          <p>{t.journeyCopy}</p>
          <a className="button button-sun" {...rentalBookingLinkProps}>{t.journeyCta} <ArrowRight aria-hidden="true" /></a>
        </div>
        <ol className="day-route">
          <li><span>10:00</span><div><CarFront aria-hidden="true" /><b>{t.route[0].title}</b><small>{t.route[0].note}</small></div></li>
          <li><span>14:30</span><div><Coffee aria-hidden="true" /><b>{t.route[1].title}</b><small>{t.route[1].note}</small></div></li>
          <li><span>18:00</span><div><BedDouble aria-hidden="true" /><b>{t.route[2].title}</b><small>{t.route[2].note}</small></div></li>
        </ol>
      </section>

      <section className="section future">
        <div className="future-intro">
          <span className="mini-label">NEXT ADVENTURE</span>
          <h2>{t.futureTitle1}<br />{t.futureTitle2}</h2>
          <p>{t.futureCopy}</p>
        </div>
        <article className="future-card hike">
          <div className="future-icon"><Footprints aria-hidden="true" /></div><span>PLANNING</span><h3>{t.hikeTitle}</h3><p>{t.hikeCopy}</p>
        </article>
        <article className="future-card kayak">
          <div className="future-icon"><Kayak aria-hidden="true" /></div><span>PLANNING</span><h3>{t.kayakTitle}</h3><p>{t.kayakCopy}</p>
        </article>
      </section>

      <section className="about" id="about">
        <div className="about-note"><Heart aria-hidden="true" /><p>{t.aboutNote1}<br /><strong>{t.aboutNote2}</strong></p></div>
        <div className="about-copy">
          <p className="section-kicker">ABOUT KMCUBE</p>
          <h2>{t.aboutTitle1}<br />{t.aboutTitle2}</h2>
          <p>{t.aboutCopy}</p>
          <div className="promise-grid">
            <div><ShieldCheck aria-hidden="true" /><b>{t.promises[0].title}</b><span>{t.promises[0].copy}</span></div>
            <div><MessageCircleHeart aria-hidden="true" /><b>{t.promises[1].title}</b><span>{t.promises[1].copy}</span></div>
            <div><Waves aria-hidden="true" /><b>{t.promises[2].title}</b><span>{t.promises[2].copy}</span></div>
          </div>
        </div>
      </section>

      <section className="company-section" id="company">
        <div className="company-title"><p className="section-kicker light">COMPANY</p><h2>{t.companyTitle}</h2><p>{t.companyIntro}</p></div>
        <dl className="company-list">
          <div><dt>{t.companyNameLabel}</dt><dd>{t.companyName}</dd></div>
          <div><dt>{t.businessLabel}</dt><dd>{t.business}<br /><span>{t.businessDetail}</span></dd></div>
          <div><dt>{t.futureLabel}</dt><dd>{t.futureBusiness}</dd></div>
          <div><dt>{t.affiliateLabel}</dt><dd><a href="https://nn-cube.com" target="_blank" rel="noreferrer">{t.affiliate} <ExternalLink aria-hidden="true" /></a></dd></div>
        </dl>
        <div className="company-trust">
          <span><ShieldCheck aria-hidden="true" /></span>
          <p><strong>{t.trustTitle}</strong><br />{t.trustCopy}</p>
        </div>
      </section>

      <section className="closing">
        <div><p className="pencil-note dark-note">See you in Yakushima!</p><h2>{t.closingTitle1}<br />{t.closingTitle2}</h2><p>{t.closingCopy}</p></div>
        <a className="button button-accent" {...rentalBookingLinkProps}>{t.checkCars} <ArrowRight aria-hidden="true" /></a>
      </section>

      <footer>
        <a className="brand brand-logo footer-brand" href="#top" aria-label={t.homeLabel}><img src="./kmcube-logo-transparent.png" alt="KMCUBE YAKUSHIMA・ADVENTURES" width="610" height="603" /></a>
        <p>{t.footerTagline}</p>
        <div><a href="#services">{t.footerLinks[0]}</a><a href="#company">{t.footerLinks[1]}</a><a href="https://nn-cube.com" target="_blank" rel="noreferrer">{t.footerLinks[2]}</a></div>
        <small>© {new Date().getFullYear()} KMCUBE</small>
      </footer>
    </main>
  );
}
