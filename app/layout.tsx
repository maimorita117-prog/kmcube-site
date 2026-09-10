import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'KMCUBE｜屋久島の旅をひとつに',
  description: 'レンタカー、民泊、登山、アクティビティ、漁船遊覧を組み合わせて、あなたらしい屋久島の旅をつなぐKMCUBEの公式サイト。',
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="ja"><body>{children}</body></html>;
}
