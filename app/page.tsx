'use client';

import { FormEvent, useEffect, useState } from 'react';
import {
  ArrowDown, ArrowRight, BedDouble, CalendarDays, CarFront, Check,
  Coffee, ExternalLink, Footprints, Heart, Kayak, Leaf, MapPin,
  MessageCircleHeart, ShieldCheck, Sparkles, Waves,
} from 'lucide-react';
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';

type Booking = {
  start: string;
  end: string;
  arrival: string;
  car: string;
};

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

const services = [
  {
    number: '01', icon: CarFront, kicker: 'RENT A CAR', title: 'レンタカー',
    copy: '到着したら、すぐに島時間へ。旅程に合わせて使いやすい一台をご案内します。',
    note: '予約画面を先行公開中', status: '準備中', tone: 'yellow',
  },
  {
    number: '02', icon: Coffee, kicker: 'SMALL CAFE', title: '簡易カフェ',
    copy: 'ドライブの途中に、ほっとひと息。屋久島の空気と一緒に楽しむ小さな休憩所です。',
    note: 'メニューは近日ご案内', status: 'COMING SOON', tone: 'coral',
  },
  {
    number: '03', icon: BedDouble, kicker: 'ISLAND STAY', title: '民泊',
    copy: 'たくさん遊んだあとは、ゆっくり休む。島で暮らすように泊まれる場所を整えています。',
    note: 'お部屋情報は近日ご案内', status: 'COMING SOON', tone: 'green',
  },
];

const arrivalLabels: Record<string, string> = {
  airport: '屋久島空港周辺', miyanoura: '宮之浦港周辺', anbo: '安房港周辺', consult: '相談して決めたい',
};
const carLabels: Record<string, string> = {
  compact: 'コンパクト', kei: '軽自動車', any: 'おすすめを相談',
};

export default function Home() {
  const [booking, setBooking] = useState<Booking>({ start: '', end: '', arrival: 'airport', car: 'any' });
  const [dialogOpen, setDialogOpen] = useState(false);

  useEffect(() => {
    const context = document.modelContext;
    if (!context?.registerTool) return;
    const lifecycle = new AbortController();
    const arrivals = Object.keys(arrivalLabels);
    const cars = Object.keys(carLabels);

    const registration = context.registerTool({
      name: 'stage_rental_car_search',
      title: 'レンタカー検索条件を設定',
      description: 'KMCUBEの画面に利用日・到着口・希望車種を設定し、予約準備状況の確認画面を開きます。実際の予約確定は行いません。',
      inputSchema: {
        type: 'object',
        properties: {
          start: { type: 'string', pattern: '^\\d{4}-\\d{2}-\\d{2}$', description: '出発日（YYYY-MM-DD）' },
          end: { type: 'string', pattern: '^\\d{4}-\\d{2}-\\d{2}$', description: '返却日（YYYY-MM-DD）' },
          arrival: { type: 'string', enum: arrivals },
          car: { type: 'string', enum: cars },
        },
        required: ['start', 'end', 'arrival', 'car'],
        additionalProperties: false,
      },
      annotations: { readOnlyHint: false, untrustedContentHint: false },
      execute(input) {
        if (!input || typeof input !== 'object') throw new Error('検索条件が必要です。');
        const values = input as Record<string, unknown>;
        const datePattern = /^\d{4}-\d{2}-\d{2}$/;
        if (typeof values.start !== 'string' || !datePattern.test(values.start)) throw new Error('出発日をYYYY-MM-DDで指定してください。');
        if (typeof values.end !== 'string' || !datePattern.test(values.end)) throw new Error('返却日をYYYY-MM-DDで指定してください。');
        if (values.end < values.start) throw new Error('返却日は出発日以降を指定してください。');
        if (typeof values.arrival !== 'string' || !arrivals.includes(values.arrival)) throw new Error('到着口の指定が正しくありません。');
        if (typeof values.car !== 'string' || !cars.includes(values.car)) throw new Error('希望車種の指定が正しくありません。');
        const next = { start: values.start, end: values.end, arrival: values.arrival, car: values.car };
        setBooking(next);
        setDialogOpen(true);
        return { status: 'staged', ...next, reservationConfirmed: false };
      },
    }, { signal: lifecycle.signal });
    void Promise.resolve(registration).catch(() => undefined);
    return () => lifecycle.abort();
  }, []);

  function checkBooking(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setDialogOpen(true);
  }

  return (
    <main>
      <header className="site-header">
        <a className="brand" href="#top" aria-label="KMCUBE ホーム">
          <span className="brand-mark"><Leaf aria-hidden="true" /></span>
          <span><strong>KMCUBE</strong><small>YAKUSHIMA TRAVEL</small></span>
        </a>
        <nav aria-label="メインナビゲーション">
          <a href="#services">旅のサービス</a>
          <a href="#story">旅のかたち</a>
          <a href="#about">私たちについて</a>
          <a href="#company">会社案内</a>
        </nav>
        <a className="nav-cta" href="#reserve">レンタカー予約 <ArrowRight aria-hidden="true" /></a>
      </header>

      <section className="hero" id="top">
        <div className="hero-copy">
          <p className="eyebrow"><MapPin aria-hidden="true" /> 屋久島の旅を、ひとつにつなぐ</p>
          <h1>めぐる。<br />くつろぐ。<br /><em>屋久島を好きになる。</em></h1>
          <p className="hero-lead">レンタカーで島をめぐり、ほっとひと息ついて、心地よく泊まる。KMCUBEが、あなたらしい屋久島時間をそっとお手伝いします。</p>
          <div className="hero-actions">
            <a className="button button-primary" href="#reserve">レンタカーの空きを見る <ArrowRight aria-hidden="true" /></a>
            <a className="text-link" href="#services">サービスを見る <ArrowDown aria-hidden="true" /></a>
          </div>
          <p className="trust-note"><ShieldCheck aria-hidden="true" /> 屋久島に根ざす観光サービス。関連会社はキューブ株式会社です。</p>
        </div>

        <div className="hero-visual">
          <img src="/kmcube-journey.png" alt="森の道を走る黄色い車と、カフェ、宿を描いた屋久島の手描き風景" />
          <div className="visual-badge"><Leaf aria-hidden="true" /><span>めぐる・ひと息・泊まる<br /><strong>島の旅をまるごと</strong></span></div>
          <p className="pencil-note">One happy island trip!</p>
        </div>
      </section>

      <form className="booking-bar" id="reserve" onSubmit={checkBooking} aria-labelledby="booking-title">
        <div className="booking-intro">
          <span>01</span>
          <div><p>RENT A CAR</p><h2 id="booking-title">旅程から空きを確認</h2></div>
        </div>
        <label>出発日<input required type="date" value={booking.start} onChange={(e) => setBooking({ ...booking, start: e.target.value })} /></label>
        <label>返却日<input required type="date" value={booking.end} onChange={(e) => setBooking({ ...booking, end: e.target.value })} /></label>
        <label>到着口<select value={booking.arrival} onChange={(e) => setBooking({ ...booking, arrival: e.target.value })}><option value="airport">屋久島空港周辺</option><option value="miyanoura">宮之浦港周辺</option><option value="anbo">安房港周辺</option><option value="consult">相談して決めたい</option></select></label>
        <label>車の希望<select value={booking.car} onChange={(e) => setBooking({ ...booking, car: e.target.value })}><option value="any">おすすめを相談</option><option value="compact">コンパクト</option><option value="kei">軽自動車</option></select></label>
        <button className="button button-accent" type="submit">確認する <ArrowRight aria-hidden="true" /></button>
      </form>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="booking-dialog">
          <DialogHeader>
            <span className="dialog-icon"><CalendarDays aria-hidden="true" /></span>
            <DialogTitle>旅程を確認しました</DialogTitle>
            <DialogDescription>予約受付の開始後、この条件で空き状況の確認からお申し込みまで進めます。</DialogDescription>
          </DialogHeader>
          <dl className="booking-summary">
            <div><dt>ご利用日</dt><dd>{booking.start} 〜 {booking.end}</dd></div>
            <div><dt>到着口</dt><dd>{arrivalLabels[booking.arrival]}</dd></div>
            <div><dt>車の希望</dt><dd>{carLabels[booking.car]}</dd></div>
          </dl>
          <div className="system-notice"><Sparkles aria-hidden="true" /><p><strong>予約システムはただいま準備中です</strong><br />お客さま情報・車両在庫・料金が確定次第、オンライン予約を開始します。</p></div>
          <button className="button button-primary dialog-button" type="button" onClick={() => setDialogOpen(false)}>サイトに戻る</button>
        </DialogContent>
      </Dialog>

      <section className="section services" id="services">
        <div className="section-heading">
          <div><p className="section-kicker">OUR SERVICES</p><h2>島の旅を、<br />やさしくつなぐ。</h2></div>
          <p>移動、休憩、宿泊を別々に探す手間を少なく。KMCUBEなら、屋久島で過ごす時間をひと続きに相談できます。</p>
        </div>
        <div className="service-grid">
          {services.map((service) => {
            const Icon = service.icon;
            return (
              <article className={`service-card ${service.tone}`} key={service.title}>
                <div className="service-top"><span>{service.number}</span><b>{service.status}</b></div>
                <Icon aria-hidden="true" />
                <p className="service-kicker">{service.kicker}</p>
                <h3>{service.title}</h3>
                <p className="service-copy">{service.copy}</p>
                <p className="service-note"><Check aria-hidden="true" /> {service.note}</p>
              </article>
            );
          })}
        </div>
      </section>

      <section className="journey-section" id="story">
        <div className="journey-copy">
          <p className="section-kicker light">ONE STOP JOURNEY</p>
          <h2>「借りる」だけで終わらない、<br />旅の入口へ。</h2>
          <p>朝、到着口で車を受け取る。好きな景色に立ち寄り、カフェでひと休み。夜は島の暮らしを感じる宿へ。ひとつの窓口だから、旅程の相談もすっきり。</p>
          <a className="button button-sun" href="#reserve">旅程からレンタカーを探す <ArrowRight aria-hidden="true" /></a>
        </div>
        <ol className="day-route">
          <li><span>10:00</span><div><CarFront aria-hidden="true" /><b>車を受け取る</b><small>島めぐりのスタート</small></div></li>
          <li><span>14:30</span><div><Coffee aria-hidden="true" /><b>カフェでひと息</b><small>深呼吸したくなる休憩</small></div></li>
          <li><span>18:00</span><div><BedDouble aria-hidden="true" /><b>民泊でくつろぐ</b><small>島の夜をゆっくり味わう</small></div></li>
        </ol>
      </section>

      <section className="section future">
        <div className="future-intro">
          <span className="mini-label">NEXT ADVENTURE</span>
          <h2>もっと深く、<br />屋久島と出会う。</h2>
          <p>これから、自然を安心して楽しむための体験案内も少しずつ。島での一日が、もっと豊かにつながっていきます。</p>
        </div>
        <article className="future-card hike">
          <div className="future-icon"><Footprints aria-hidden="true" /></div><span>PLANNING</span><h3>登山案内</h3><p>森の歩き方やコース選びを、旅程に合わせてご案内する計画です。</p>
        </article>
        <article className="future-card kayak">
          <div className="future-icon"><Kayak aria-hidden="true" /></div><span>PLANNING</span><h3>カヤック案内</h3><p>水の上から島の自然を味わう、小さな冒険を準備していきます。</p>
        </article>
      </section>

      <section className="about" id="about">
        <div className="about-note"><Heart aria-hidden="true" /><p>旅の前から、<br /><strong>おかえりまで。</strong></p></div>
        <div className="about-copy">
          <p className="section-kicker">ABOUT KMCUBE</p>
          <h2>屋久島を楽しむ人に、<br />近くて頼れる存在でありたい。</h2>
          <p>KMCUBEは、屋久島で観光事業を始める会社です。まずはレンタカー、簡易カフェ、民泊から。島を訪れる方が迷わず、安心して、自分らしい旅を楽しめるように、サービス同士をやさしくつないでいきます。</p>
          <div className="promise-grid">
            <div><ShieldCheck aria-hidden="true" /><b>分かりやすく</b><span>旅の情報と予約をひとつに</span></div>
            <div><MessageCircleHeart aria-hidden="true" /><b>相談しやすく</b><span>顔の見える温かなご案内</span></div>
            <div><Waves aria-hidden="true" /><b>島を大切に</b><span>自然への敬意を忘れない</span></div>
          </div>
        </div>
      </section>

      <section className="company-section" id="company">
        <div className="company-title"><p className="section-kicker light">COMPANY</p><h2>会社案内</h2><p>小さく始めて、屋久島の旅に必要なものを丁寧に育てていきます。</p></div>
        <dl className="company-list">
          <div><dt>会社名</dt><dd>ケーエムキューブ（KMCUBE）</dd></div>
          <div><dt>事業内容</dt><dd>屋久島における観光事業<br /><span>レンタカー事業・簡易カフェ・民泊事業</span></dd></div>
          <div><dt>今後の展開</dt><dd>登山案内・カヤック案内などの体験事業</dd></div>
          <div><dt>関連会社</dt><dd><a href="https://nn-cube.com" target="_blank" rel="noreferrer">キューブ株式会社 <ExternalLink aria-hidden="true" /></a></dd></div>
        </dl>
        <div className="company-trust">
          <span><ShieldCheck aria-hidden="true" /></span>
          <p><strong>安心して使える予約体験へ</strong><br />システム開発を基盤とするキューブ株式会社の関連会社として、その知見も活かしながら、分かりやすく使いやすい予約サービスを目指します。</p>
        </div>
      </section>

      <section className="closing">
        <div><p className="pencil-note dark-note">See you in Yakushima!</p><h2>屋久島で、<br />お待ちしています。</h2><p>まずはレンタカーから。あなたの旅程に合う一台を一緒に考えます。</p></div>
        <a className="button button-accent" href="#reserve">レンタカーの空きを見る <ArrowRight aria-hidden="true" /></a>
      </section>

      <footer>
        <a className="brand footer-brand" href="#top"><span className="brand-mark"><Leaf aria-hidden="true" /></span><span><strong>KMCUBE</strong><small>YAKUSHIMA TRAVEL</small></span></a>
        <p>屋久島の旅を、ひとつにつなぐ。</p>
        <div><a href="#services">事業案内</a><a href="#company">会社案内</a><a href="https://nn-cube.com" target="_blank" rel="noreferrer">関連会社</a></div>
        <small>© {new Date().getFullYear()} KMCUBE</small>
      </footer>
    </main>
  );
}
