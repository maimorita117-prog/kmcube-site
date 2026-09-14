'use client';

import { useEffect, useMemo, useState } from 'react';
import { ArrowRight, Building2, CalendarDays, CarFront, Check, CreditCard, HouseHeart, KeyRound, Mail, Mountain, Plus, Sailboat, Search, ShieldCheck, Users, Waves } from 'lucide-react';

type Language = 'ja' | 'en';
type BillingMode = 'hourly' | 'daily';
type ExtraServiceId = 'stay' | 'hike' | 'activity' | 'boat';
type ServicePrices = Record<ExtraServiceId, number>;
type BookingRates = { insurancePerDay: number; childSeatPerDay: number; services: ServicePrices };
type Availability = { id: string; label: string; model: string; imageUrl?: string; price: number; hourlyPrice: number; inventory: number; booked?: number; blocked?: number; available: number };
type Booking = {
  code: string; status: string; carClass: string; carLabel: string; startDate: string; endDate: string;
  startTime: string; endTime: string; billingMode: BillingMode; pickupLocation: string; people: number; total: number; paymentMethod: string; additionalServices: string[];
  insurance: boolean; childSeats: number;
};

const API_URL = './api/index.php';
const defaultRates: BookingRates = {
  insurancePerDay: 1100,
  childSeatPerDay: 550,
  services: { stay: 6600, hike: 8800, activity: 6600, boat: 8800 },
};
const carDefaults: Availability[] = [
  { id: 'kei', label: '軽自動車', model: 'N-BOXクラス', imageUrl: '/vehicle-kei.webp', price: 6600, hourlyPrice: 1100, inventory: 2, booked: 0, blocked: 0, available: 2 },
  { id: 'compact', label: 'コンパクト', model: 'AQUAクラス', imageUrl: '/vehicle-compact.webp', price: 7700, hourlyPrice: 1300, inventory: 2, booked: 0, blocked: 0, available: 2 },
  { id: 'van', label: 'ミニバン', model: '7人乗りクラス', imageUrl: '/vehicle-minivan.webp', price: 9900, hourlyPrice: 1700, inventory: 1, booked: 0, blocked: 0, available: 1 },
];

const copy = {
  ja: {
    kicker: 'BOOKING & PRICE', title: '空き情報から予約までノンストップ！',
    intro: '日程を入力すると、空いている車両と概算料金を確認できます。民泊や体験の相談も同時に送れます。',
    bookTab: '新しく予約する', manageTab: '予約の照会・変更', hourly: '時間制', daily: '日数制', hourlyNote: '1～24時間。日額を上限に計算', dailyNote: '24時間単位で計算', start: '出発日', end: '返却日', startTime: '出発時刻', endTime: '返却時刻', pickup: '受取・返却場所', people: '利用人数',
    search: '空車と料金を確認', searching: '確認中…', choose: '車両クラスを選択', available: '空車', unavailable: '満車', perDay: '1日・税込',
    options: '補償・オプション', insurance: '安心補償パック', insuranceNote: '休業補償などをカバー（仮）', childSeat: 'チャイルドシート',
    services: '一緒に相談するサービス', servicesIntro: '気になる体験をタップすると概算料金にすぐ反映されます。複数選択できます。', stay: '民泊', stayNote: '島で暮らすように泊まる、素泊まりプラン', hike: '登山案内', hikeNote: '初心者も安心。半日ガイドの目安', activity: 'アクティビティ', activityNote: 'カヤックなど自然体験1メニュー', boat: '漁船遊覧', boatNote: '屋久島の海を楽しむ約2時間コース', provisional: '参考料金・税込', selectService: '選択する', selectedService: '選択中', perPersonNight: '1名・1泊', perPersonUse: '1名・1回',
    customer: 'お客様情報', name: 'お名前', email: 'メールアドレス', phone: '電話番号', arrival: '到着便・船便（任意）', notes: 'ご要望（任意）',
    payment: 'お支払い方法', onsite: '現地払い', online: 'オンライン決済', onlineSoon: '決済会社接続後に利用可能',
    agree: '料金・キャンセル規定、利用規約、個人情報保護方針に同意します。', submit: 'この内容で予約する', submitting: '予約を登録中…',
    summary: '予約内容・概算料金', days: '日', hours: '時間', base: '車両基本料金', insurancePrice: '安心補償', childPrice: 'チャイルドシート', total: '概算合計',
    tentative: '表示料金は仮料金（税込）です。追加サービスも概算に含まれます。内容確認後に正式料金をご案内します。',
    success: '予約リクエストを受け付けました', successCopy: 'ご入力のメールアドレスへ予約内容を送りました。担当者が確認後、予約確定をご案内します。', mailFailed: '予約は登録されていますが、確認メールの送信を確認できませんでした。予約番号と管理キーを必ず控えてください。', code: '予約番号',
    customerMail: '予約者への確認メール', adminMail: '管理会社への予約通知', mailDelivered: '送信手続き済み', mailNeedsCheck: '送信確認が必要', nextTitle: 'このあとの流れ', nextSteps: ['メールで受付内容を確認', 'KMCUBE担当者が空車と内容を確認', '担当者から予約確定のご連絡'], keepKey: '予約番号と管理キーは、照会・変更の際に必要です。大切に保管してください。', manageNow: '予約内容を照会・変更する', requestDetails: 'お申し込み内容', requestedServices: '追加サービス', none: 'なし',
    lookupIntro: '確認メールに記載された予約番号と管理キーを入力すると、予約内容の確認・変更依頼ができます。', accessKey: '管理キー', lookup: '予約を表示', cancel: '予約をキャンセル', change: '日程・車種の変更を依頼', changeSend: '変更依頼を送信',
    status: '現在の状態', pending: '確認待ち', confirmed: '予約確定', cancelled: 'キャンセル済み', change_requested: '変更確認中',
    apiUnavailable: '現在、予約サーバーへ接続できません。さくらサーバーへ設置後、または設定完了後に利用できます。',
    required: '日時を入力してください。', invalidTime: '返却日時は出発日時より後にしてください。時間制は24時間以内で選択できます。', selectCar: '空いている車両を選択してください。', genericError: '処理できませんでした。入力内容をご確認ください。',
    policyTitle: '料金・キャンセル・ご利用条件', policyText: '表示料金は税込の基本料金です。キャンセル料は利用日の7日前から30％、前日50％、当日・無連絡100％を仮規定としています。貸渡しには有効な運転免許証が必要です。外国免許の方は、日本で有効な国際運転免許証または認定翻訳文をご準備ください。',
    privacyTitle: '個人情報保護方針', privacyText: '取得した氏名、連絡先、旅程情報は、予約管理、本人確認、ご連絡、事故対応のために利用します。法令に基づく場合を除き、本人の同意なく第三者へ提供しません。',
  },
  en: {
    kicker: 'BOOKING & PRICE', title: 'From availability to booking—nonstop!',
    intro: 'Enter your dates to see available vehicles and an estimated price. You can also request accommodation and activities.',
    bookTab: 'New booking', manageTab: 'Manage booking', hourly: 'Hourly', daily: 'Daily', hourlyNote: '1–24 hours, capped at the daily rate', dailyNote: 'Calculated in 24-hour units', start: 'Pick-up date', end: 'Return date', startTime: 'Pick-up time', endTime: 'Return time', pickup: 'Pick-up / return location', people: 'Travelers',
    search: 'Check availability', searching: 'Checking…', choose: 'Choose a vehicle class', available: 'available', unavailable: 'Full', perDay: 'per day, tax included',
    options: 'Coverage & options', insurance: 'Peace-of-mind coverage', insuranceNote: 'Includes loss-of-use coverage (provisional)', childSeat: 'Child seat',
    services: 'Services to request together', servicesIntro: 'Tap any experience to add its estimated price. You can select more than one.', stay: 'Guesthouse', stayNote: 'Simple room-only island stay', hike: 'Hiking guide', hikeNote: 'Estimated half-day beginner-friendly guide', activity: 'Activities', activityNote: 'One nature experience such as kayaking', boat: 'Boat cruise', boatNote: 'Approx. two-hour cruise around Yakushima', provisional: 'Estimated, tax included', selectService: 'Select', selectedService: 'Selected', perPersonNight: 'per guest / night', perPersonUse: 'per guest / activity',
    customer: 'Guest details', name: 'Name', email: 'Email', phone: 'Phone', arrival: 'Flight or ferry (optional)', notes: 'Requests (optional)',
    payment: 'Payment', onsite: 'Pay on arrival', online: 'Online payment', onlineSoon: 'Available after payment setup',
    agree: 'I agree to the rates, cancellation policy, terms, and privacy policy.', submit: 'Send booking request', submitting: 'Submitting…',
    summary: 'Booking summary', days: 'day(s)', hours: 'hour(s)', base: 'Vehicle', insurancePrice: 'Coverage', childPrice: 'Child seat', total: 'Estimated total',
    tentative: 'All displayed prices are provisional and tax-inclusive. Selected services are included in the estimate; we will confirm the final price after reviewing your request.',
    success: 'Your booking request has been received', successCopy: 'We sent the booking details to your email address. Our team will review your request and contact you with confirmation.', mailFailed: 'Your booking was saved, but we could not confirm email delivery. Please keep your booking reference and access key.', code: 'Booking reference',
    customerMail: 'Guest confirmation email', adminMail: 'KMCUBE booking notification', mailDelivered: 'Sent', mailNeedsCheck: 'Delivery needs checking', nextTitle: 'What happens next', nextSteps: ['Check the request email', 'KMCUBE reviews availability and details', 'We contact you with final confirmation'], keepKey: 'Keep your booking reference and access key safe. You will need both to view or request changes.', manageNow: 'View or change this booking', requestDetails: 'Request details', requestedServices: 'Additional services', none: 'None',
    lookupIntro: 'Enter the booking reference and access key from your email to view the booking or request changes.', accessKey: 'Access key', lookup: 'View booking', cancel: 'Cancel booking', change: 'Request date / vehicle change', changeSend: 'Send change request',
    status: 'Status', pending: 'Pending', confirmed: 'Confirmed', cancelled: 'Cancelled', change_requested: 'Change requested',
    apiUnavailable: 'The booking server is unavailable. This feature will work after the Sakura server setup is complete.',
    required: 'Please enter your dates and times.', invalidTime: 'Return must be after pick-up. Hourly bookings are limited to 24 hours.', selectCar: 'Please choose an available vehicle.', genericError: 'We could not complete the request. Please check your details.',
    policyTitle: 'Rates, cancellation & rental terms', policyText: 'Displayed rates are provisional, tax-inclusive base rates. The provisional cancellation fee is 30% from 7 days before, 50% on the previous day, and 100% on the day or for no-shows. A valid driving licence is required. Overseas guests must bring an international driving permit or an authorised Japanese translation valid in Japan.',
    privacyTitle: 'Privacy policy', privacyText: 'We use your name, contact details, and itinerary only to manage your booking, verify identity, contact you, and respond to incidents. We do not disclose your data without consent unless required by law.',
  },
};

const yen = (value: number) => new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 }).format(value);
const today = () => new Date().toISOString().slice(0, 10);
const countHours = (startDate: string, startTime: string, endDate: string, endTime: string) => {
  if (!startDate || !endDate || !startTime || !endTime) return 0;
  const value = (new Date(`${endDate}T${endTime}:00`).getTime() - new Date(`${startDate}T${startTime}:00`).getTime()) / 3600000;
  return Math.max(0, Math.ceil(value));
};

async function callApi<T>(action: string, init?: RequestInit): Promise<T> {
  const join = action.includes('?') ? '&' : '?';
  const response = await fetch(`${API_URL}?action=${action}${join}_=${Date.now()}`, { ...init, headers: { 'Content-Type': 'application/json', ...(init?.headers || {}) } });
  const payload = await response.json() as T & { ok?: boolean; message?: string };
  if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed');
  return payload;
}

export function ReservationSystem({ language }: { language: Language }) {
  const t = copy[language];
  const [tab, setTab] = useState<'book' | 'manage'>('book');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [startTime, setStartTime] = useState('09:00');
  const [endTime, setEndTime] = useState('17:00');
  const [billingMode, setBillingMode] = useState<BillingMode>('daily');
  const [pickupLocation, setPickupLocation] = useState(language === 'ja' ? '屋久島空港' : 'Yakushima Airport');
  const [people, setPeople] = useState(2);
  const [cars, setCars] = useState(carDefaults);
  const [selectedCar, setSelectedCar] = useState('');
  const [searched, setSearched] = useState(false);
  const [loading, setLoading] = useState(false);
  const [apiReady, setApiReady] = useState(true);
  const [insurance, setInsurance] = useState(true);
  const [childSeats, setChildSeats] = useState(0);
  const [rates, setRates] = useState<BookingRates>(defaultRates);
  const [extras, setExtras] = useState<ExtraServiceId[]>([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState<{ code: string; accessToken: string; mailSent: boolean; adminMailSent: boolean } | null>(null);
  const [lookupCode, setLookupCode] = useState('');
  const [lookupToken, setLookupToken] = useState('');
  const [booking, setBooking] = useState<Booking | null>(null);
  const [changeOpen, setChangeOpen] = useState(false);

  useEffect(() => {
    const code = new URLSearchParams(window.location.search).get('manage');
    if (code) { setLookupCode(code.toUpperCase()); setTab('manage'); }
  }, []);

  useEffect(() => {
    let active = true;
    callApi<{ cars: Availability[]; rates?: BookingRates }>('catalog')
      .then((payload) => {
        if (!active) return;
        setCars(payload.cars);
        if (payload.rates) setRates(payload.rates);
      })
      .catch(() => { /* Local static preview keeps the initial sample catalog. */ });
    return () => { active = false; };
  }, []);

  const hours = countHours(startDate, startTime, endDate, endTime);
  const billingUnits = billingMode === 'hourly' ? Math.max(1, hours) : Math.max(1, Math.ceil(hours / 24));
  const currentCar = cars.find((car) => car.id === selectedCar);
  const costs = useMemo(() => {
    const base = billingMode === 'hourly' ? Math.min(currentCar?.price || 0, (currentCar?.hourlyPrice || 0) * billingUnits) : (currentCar?.price || 0) * billingUnits;
    const optionUnits = billingMode === 'hourly' ? 1 : billingUnits;
    const coverage = insurance ? rates.insurancePerDay * optionUnits : 0;
    const seat = childSeats * rates.childSeatPerDay * optionUnits;
    const stayUnits = Math.max(1, Math.ceil(hours / 24));
    const services = extras.map((id) => ({
      id,
      amount: rates.services[id] * people * (id === 'stay' ? stayUnits : 1),
      quantity: people * (id === 'stay' ? stayUnits : 1),
    }));
    const servicesTotal = services.reduce((sum, service) => sum + service.amount, 0);
    return { base, coverage, seat, services, servicesTotal, total: base + coverage + seat + servicesTotal };
  }, [currentCar, billingMode, billingUnits, insurance, childSeats, rates, extras, people, hours]);

  async function searchAvailability(event: React.FormEvent) {
    event.preventDefault();
    setError('');
    if (!startDate || !endDate || !startTime || !endTime) return setError(t.required);
    if (hours <= 0 || (billingMode === 'hourly' && hours > 24)) return setError(t.invalidTime);
    setLoading(true);
    try {
      const payload = await callApi<{ cars: Availability[] }>(`availability&start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}&startTime=${encodeURIComponent(startTime)}&endTime=${encodeURIComponent(endTime)}&billingMode=${billingMode}`);
      setCars((currentCars) => payload.cars.map((car) => ({
        ...car,
        imageUrl: car.imageUrl || currentCars.find((currentCar) => currentCar.id === car.id)?.imageUrl,
      })));
      setApiReady(true);
      setSearched(true);
      if (selectedCar && !payload.cars.find((car) => car.id === selectedCar && car.available > 0)) setSelectedCar('');
    } catch {
      setCars(carDefaults.map((car) => ({ ...car, available: 0 })));
      setApiReady(false);
      setSearched(true);
      setSelectedCar('');
      setError(t.apiUnavailable);
    } finally { setLoading(false); }
  }

  function toggleExtra(id: ExtraServiceId) { setExtras((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]); }

  async function submitBooking(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedCar) return setError(t.selectCar);
    setLoading(true); setError('');
    const data = new FormData(event.currentTarget);
    try {
      const result = await callApi<{ booking: Booking; accessToken: string; mailSent: boolean; adminMailSent?: boolean }>('reserve', {
        method: 'POST', body: JSON.stringify({
          startDate, endDate, startTime, endTime, billingMode, pickupLocation, people, carClass: selectedCar, insurance, childSeats,
          additionalServices: extras, paymentMethod: 'onsite', name: data.get('name'), email: data.get('email'),
          phone: data.get('phone'), arrival: data.get('arrival'), notes: data.get('notes'), language,
        }),
      });
      setSuccess({ code: result.booking.code, accessToken: result.accessToken, mailSent: result.mailSent, adminMailSent: result.adminMailSent !== false });
      setLookupCode(result.booking.code); setLookupToken(result.accessToken); setBooking(result.booking);
    } catch (caught) { setError(caught instanceof Error ? caught.message : t.genericError); }
    finally { setLoading(false); }
  }

  async function lookupBooking(event: React.FormEvent) {
    event.preventDefault(); setLoading(true); setError('');
    try {
      const result = await callApi<{ booking: Booking }>(`reservation&code=${encodeURIComponent(lookupCode)}&token=${encodeURIComponent(lookupToken)}`);
      setBooking(result.booking);
    } catch (caught) { setBooking(null); setError(caught instanceof Error ? caught.message : t.genericError); }
    finally { setLoading(false); }
  }

  async function cancelBooking() {
    if (!booking || !window.confirm(t.cancel + '?')) return;
    setLoading(true); setError('');
    try {
      const result = await callApi<{ booking: Booking }>('cancel', { method: 'POST', body: JSON.stringify({ code: booking.code, token: lookupToken }) });
      setBooking(result.booking);
    } catch (caught) { setError(caught instanceof Error ? caught.message : t.genericError); }
    finally { setLoading(false); }
  }

  async function requestChange(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!booking) return;
    const data = new FormData(event.currentTarget); setLoading(true); setError('');
    try {
      const result = await callApi<{ booking: Booking }>('change', { method: 'POST', body: JSON.stringify({
        code: booking.code, token: lookupToken, requestedStart: data.get('requestedStart'), requestedEnd: data.get('requestedEnd'), requestedStartTime: data.get('requestedStartTime'), requestedEndTime: data.get('requestedEndTime'), requestedBillingMode: data.get('requestedBillingMode'), requestedCar: data.get('requestedCar'), message: data.get('changeMessage'),
      }) });
      setBooking(result.booking); setChangeOpen(false);
    } catch (caught) { setError(caught instanceof Error ? caught.message : t.genericError); }
    finally { setLoading(false); }
  }

  const statusLabel = (status: string) => t[status as keyof typeof t] || status;
  const extraLabels = [
    { id: 'stay' as const, label: t.stay, note: t.stayNote, unit: t.perPersonNight, icon: HouseHeart },
    { id: 'hike' as const, label: t.hike, note: t.hikeNote, unit: t.perPersonUse, icon: Mountain },
    { id: 'activity' as const, label: t.activity, note: t.activityNote, unit: t.perPersonUse, icon: Waves },
    { id: 'boat' as const, label: t.boat, note: t.boatNote, unit: t.perPersonUse, icon: Sailboat },
  ];

  return (
    <section className="reservation-system" id="booking" aria-labelledby="booking-system-title">
      <div className="reservation-heading">
        <div><p className="section-kicker">{t.kicker}</p><h2 id="booking-system-title">{t.title}</h2></div>
        <p>{t.intro}</p>
      </div>

      <div className="reservation-tabs" role="tablist">
        <button type="button" role="tab" aria-selected={tab === 'book'} onClick={() => { setTab('book'); setError(''); }}>{t.bookTab}</button>
        <button type="button" role="tab" aria-selected={tab === 'manage'} onClick={() => { setTab('manage'); setError(''); setSuccess(null); }}>{t.manageTab}</button>
      </div>
      <div className="reservation-policies"><details><summary>{t.policyTitle}</summary><p>{t.policyText}</p></details><details><summary>{t.privacyTitle}</summary><p>{t.privacyText}</p></details></div>

      {tab === 'book' ? <div className="reservation-workspace">
        {success ? <div className="reservation-success">
          <div className="success-heading"><span><Check aria-hidden="true" /></span><div><p>REQUEST RECEIVED</p><h3>{t.success}</h3><p>{success.mailSent ? t.successCopy : t.mailFailed}</p></div></div>
          <div className="success-mail-status"><div className={success.mailSent ? 'sent' : 'pending'}><Mail aria-hidden="true" /><span><small>{t.customerMail}</small><strong>{success.mailSent ? t.mailDelivered : t.mailNeedsCheck}</strong></span></div><div className={success.adminMailSent ? 'sent' : 'pending'}><Building2 aria-hidden="true" /><span><small>{t.adminMail}</small><strong>{success.adminMailSent ? t.mailDelivered : t.mailNeedsCheck}</strong></span></div></div>
          <div className="success-content"><div className="success-next"><h4>{t.nextTitle}</h4><ol>{t.nextSteps.map((step, index) => <li key={step}><span>{index + 1}</span>{step}</li>)}</ol></div>{booking && <div className="success-booking-card"><h4>{t.requestDetails}</h4><dl><div><dt>{t.choose}</dt><dd>{booking.carLabel}</dd></div><div><dt>{t.start} – {t.end}</dt><dd>{booking.startDate} {booking.startTime}<br />{booking.endDate} {booking.endTime}</dd></div><div><dt>{t.total}</dt><dd>{yen(booking.total)}</dd></div></dl></div>}</div>
          <dl className="success-keys"><div><dt>{t.code}</dt><dd>{success.code}</dd></div><div><dt>{t.accessKey}</dt><dd>{success.accessToken}</dd></div></dl>
          <p className="success-security-note"><KeyRound aria-hidden="true" />{t.keepKey}</p>
          <button type="button" className="button button-primary" onClick={() => { setTab('manage'); setSuccess(null); }}>{t.manageNow} <ArrowRight aria-hidden="true" /></button>
        </div> : <>
          <div className="billing-mode" role="radiogroup" aria-label={language === 'ja' ? '料金体系' : 'Rate type'}>
            <label className={billingMode === 'hourly' ? 'selected' : ''}><input type="radio" name="billingMode" value="hourly" checked={billingMode === 'hourly'} onChange={() => setBillingMode('hourly')} /><span><strong>{t.hourly}</strong><small>{t.hourlyNote}</small></span></label>
            <label className={billingMode === 'daily' ? 'selected' : ''}><input type="radio" name="billingMode" value="daily" checked={billingMode === 'daily'} onChange={() => setBillingMode('daily')} /><span><strong>{t.daily}</strong><small>{t.dailyNote}</small></span></label>
          </div>
          <form className="availability-search" onSubmit={searchAvailability}>
            <label><span><CalendarDays aria-hidden="true" />{t.start}</span><input type="date" min={today()} value={startDate} onChange={(event) => { setStartDate(event.target.value); if (endDate && endDate < event.target.value) setEndDate(event.target.value); }} required /></label>
            <label><span>{t.startTime}</span><input type="time" min="07:00" max="20:00" step="1800" value={startTime} onChange={(event) => setStartTime(event.target.value)} required /></label>
            <label><span><CalendarDays aria-hidden="true" />{t.end}</span><input type="date" min={startDate || today()} value={endDate} onChange={(event) => setEndDate(event.target.value)} required /></label>
            <label><span>{t.endTime}</span><input type="time" min="07:00" max="20:00" step="1800" value={endTime} onChange={(event) => setEndTime(event.target.value)} required /></label>
            <label><span><CarFront aria-hidden="true" />{t.pickup}</span><select value={pickupLocation} onChange={(event) => setPickupLocation(event.target.value)}><option>{language === 'ja' ? '屋久島空港' : 'Yakushima Airport'}</option><option>{language === 'ja' ? '宮之浦港' : 'Miyanoura Port'}</option><option>{language === 'ja' ? '安房港' : 'Anbo Port'}</option></select></label>
            <label><span><Users aria-hidden="true" />{t.people}</span><input type="number" min="1" max="8" value={people} onChange={(event) => setPeople(Number(event.target.value))} /></label>
            <button className="button button-primary" disabled={loading}>{loading ? t.searching : t.search} <Search aria-hidden="true" /></button>
          </form>

          <div className="booking-step"><div className="booking-step-title"><span>01</span><h3>{t.choose}</h3></div>
            <div className="availability-cars">{cars.length === 0 && <p className="booking-error">{language === 'ja' ? '現在受付中の車両はありません。' : 'No vehicles are currently accepting bookings.'}</p>}{cars.map((car) => {
              const canSelect = searched && apiReady && car.available > 0;
              return <button key={car.id} type="button" className={selectedCar === car.id ? 'selected' : ''} disabled={!canSelect} onClick={() => setSelectedCar(car.id)}>
                <span className="car-availability">{searched ? (car.available > 0 ? `${t.available} ${car.available}` : t.unavailable) : t.search}</span>
                <span className="car-photo-frame">{car.imageUrl ? <img src={car.imageUrl} alt={language === 'ja' ? `${car.label}（${car.model}）の代表車両` : `Representative ${car.label} vehicle (${car.model})`} loading="lazy" /> : <CarFront aria-hidden="true" />}</span><small>{car.model}</small><strong>{car.label}</strong><b>{yen(billingMode === 'hourly' ? car.hourlyPrice : car.price)}<em> / {billingMode === 'hourly' ? t.hours : t.perDay}</em></b>
              </button>;
            })}</div>
          </div>

          <form className="booking-detail-form" onSubmit={submitBooking}>
            <div className="booking-form-main">
              <div className="booking-step"><div className="booking-step-title"><span>02</span><h3>{t.options}</h3></div>
                <div className="option-grid">
                  <label className={insurance ? 'checked' : ''}><input type="checkbox" checked={insurance} onChange={(event) => setInsurance(event.target.checked)} /><ShieldCheck aria-hidden="true" /><span><strong>{t.insurance}</strong><small>{t.insuranceNote}<br />{yen(rates.insurancePerDay)} / {t.perDay}</small></span></label>
                  <label><input type="number" min="0" max="3" value={childSeats} onChange={(event) => setChildSeats(Number(event.target.value))} /><Users aria-hidden="true" /><span><strong>{t.childSeat}</strong><small>{yen(rates.childSeatPerDay)} / {t.perDay}</small></span></label>
                </div>
              </div>
              <div className="booking-step"><div className="booking-step-title"><span>03</span><h3>{t.services}</h3></div>
                <p className="extra-service-intro">{t.servicesIntro}</p>
                <div className="extra-service-grid">{extraLabels.map((service) => {
                  const selected = extras.includes(service.id);
                  const Icon = service.icon;
                  return <label key={service.id} data-service={service.id} className={selected ? 'checked' : ''}>
                    <input type="checkbox" checked={selected} onChange={() => toggleExtra(service.id)} />
                    <span className="extra-service-icon"><Icon aria-hidden="true" /></span>
                    <span className="extra-service-copy"><small className="extra-service-eyebrow">{t.provisional}</small><strong>{service.label}</strong><small>{service.note}</small></span>
                    <span className="extra-service-price"><b>{yen(rates.services[service.id])}</b><small>{service.unit}</small></span>
                    <span className="extra-service-choice">{selected ? <Check aria-hidden="true" /> : <Plus aria-hidden="true" />}{selected ? t.selectedService : t.selectService}</span>
                  </label>;
                })}</div>
              </div>
              <div className="booking-step"><div className="booking-step-title"><span>04</span><h3>{t.customer}</h3></div>
                <div className="customer-grid"><label>{t.name}<input name="name" required /></label><label>{t.email}<input name="email" type="email" required /></label><label>{t.phone}<input name="phone" type="tel" required /></label><label>{t.arrival}<input name="arrival" /></label><label className="wide">{t.notes}<textarea name="notes" rows={3} /></label></div>
              </div>
            </div>
            <aside className="reservation-summary">
              <p>YOUR BOOKING</p><h3>{t.summary}</h3>
              <dl><div><dt>{currentCar?.label || t.choose}<small>{startDate || '—'} {startTime} → {endDate || '—'} {endTime}・{billingUnits}{billingMode === 'hourly' ? t.hours : t.days}</small></dt><dd>{yen(costs.base)}</dd></div>
                <div><dt>{t.insurancePrice}</dt><dd>{yen(costs.coverage)}</dd></div><div><dt>{t.childPrice} × {childSeats}</dt><dd>{yen(costs.seat)}</dd></div>
                {costs.services.map((service) => <div key={service.id} className="summary-service"><dt>{extraLabels.find((item) => item.id === service.id)?.label}<small>{service.quantity} × {yen(rates.services[service.id])}</small></dt><dd>{yen(service.amount)}</dd></div>)}
                <div className="summary-total"><dt>{t.total}</dt><dd>{yen(costs.total)}</dd></div></dl>
              <p className="summary-note">{t.tentative}</p>
              <fieldset><legend>{t.payment}</legend><label className="payment-active"><input type="radio" checked readOnly /><CreditCard aria-hidden="true" />{t.onsite}</label><label className="payment-disabled"><input type="radio" disabled />{t.online}<small>{t.onlineSoon}</small></label></fieldset>
              <label className="terms-check"><input type="checkbox" required />{t.agree}</label>
              {error && <p className="booking-error" role="alert">{error}</p>}
              <button className="button button-accent" disabled={loading || !apiReady || !selectedCar}>{loading ? t.submitting : t.submit} <ArrowRight aria-hidden="true" /></button>
            </aside>
          </form>
        </>}
      </div> : <div className="manage-booking">
        <div className="manage-intro"><Mail aria-hidden="true" /><div><h3>{t.manageTab}</h3><p>{t.lookupIntro}</p></div></div>
        <form className="lookup-form" onSubmit={lookupBooking}><label>{t.code}<input value={lookupCode} onChange={(event) => setLookupCode(event.target.value.toUpperCase())} required /></label><label>{t.accessKey}<input value={lookupToken} onChange={(event) => setLookupToken(event.target.value)} required /></label><button className="button button-primary" disabled={loading}>{t.lookup}</button></form>
        {error && <p className="booking-error" role="alert">{error}</p>}
        {booking && <article className="booking-record"><div><p>{t.status}</p><strong className={`status-${booking.status}`}>{statusLabel(booking.status)}</strong></div><h3>{booking.code}</h3><dl><div><dt>{t.start} – {t.end}</dt><dd>{booking.startDate} {booking.startTime} → {booking.endDate} {booking.endTime}<br />{booking.billingMode === 'hourly' ? t.hourly : t.daily}</dd></div><div><dt>{t.choose}</dt><dd>{booking.carLabel}</dd></div><div><dt>{t.pickup}</dt><dd>{booking.pickupLocation}</dd></div><div><dt>{t.people}</dt><dd>{booking.people}</dd></div><div><dt>{t.options}</dt><dd>{booking.insurance ? t.insurance : t.none}{booking.childSeats > 0 ? ` / ${t.childSeat} × ${booking.childSeats}` : ''}</dd></div><div><dt>{t.requestedServices}</dt><dd>{booking.additionalServices.length ? booking.additionalServices.map((id) => extraLabels.find((service) => service.id === id)?.label || id).join('、') : t.none}</dd></div><div><dt>{t.total}</dt><dd>{yen(booking.total)}</dd></div></dl>
          {booking.status !== 'cancelled' && <div className="booking-record-actions"><button type="button" onClick={() => setChangeOpen((value) => !value)}>{t.change}</button><button type="button" className="danger" onClick={cancelBooking}>{t.cancel}</button></div>}
          {changeOpen && booking.status !== 'cancelled' && <form className="change-request" onSubmit={requestChange}><label>{t.start}<input name="requestedStart" type="date" min={today()} defaultValue={booking.startDate} required /></label><label>{t.startTime}<input name="requestedStartTime" type="time" defaultValue={booking.startTime} required /></label><label>{t.end}<input name="requestedEnd" type="date" min={today()} defaultValue={booking.endDate} required /></label><label>{t.endTime}<input name="requestedEndTime" type="time" defaultValue={booking.endTime} required /></label><label>{language === 'ja' ? '料金体系' : 'Rate type'}<select name="requestedBillingMode" defaultValue={booking.billingMode}><option value="hourly">{t.hourly}</option><option value="daily">{t.daily}</option></select></label><label>{t.choose}<select name="requestedCar" defaultValue={booking.carClass}>{cars.map((car) => <option key={car.id} value={car.id}>{car.label}</option>)}</select></label><label className="wide">{t.notes}<textarea name="changeMessage" rows={3} /></label><button className="button button-primary" disabled={loading}>{t.changeSend}</button></form>}
        </article>}
      </div>}
    </section>
  );
}
