import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'KMCUBE｜屋久島の旅をひとつに',
  description: 'レンタカー、簡易カフェ、民泊で、あなたらしい屋久島の旅をつなぐKMCUBEの公式サイト。',
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="ja"><body>{children}</body></html>;
}
