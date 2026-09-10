import sharp from 'sharp';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const input = process.argv[2];
const output = process.argv[3];

if (!input || !output) {
  throw new Error('Usage: node scripts/remove-logo-background.mjs <input> <output>');
}

const { data, info } = await sharp(input)
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

const pixelCount = info.width * info.height;
const visited = new Uint8Array(pixelCount);
const queue = new Uint32Array(pixelCount);
let head = 0;
let tail = 0;

const isCanvasBackground = (index) => {
  const offset = index * 4;
  const r = data[offset];
  const g = data[offset + 1];
  const b = data[offset + 2];
  const min = Math.min(r, g, b);
  const max = Math.max(r, g, b);
  return min >= 220 && max - min <= 20;
};

const enqueue = (index) => {
  if (visited[index] || !isCanvasBackground(index)) return;
  visited[index] = 1;
  queue[tail++] = index;
};

for (let x = 0; x < info.width; x += 1) {
  enqueue(x);
  enqueue((info.height - 1) * info.width + x);
}
for (let y = 0; y < info.height; y += 1) {
  enqueue(y * info.width);
  enqueue(y * info.width + info.width - 1);
}

while (head < tail) {
  const index = queue[head++];
  const x = index % info.width;
  const y = Math.floor(index / info.width);

  if (x > 0) enqueue(index - 1);
  if (x + 1 < info.width) enqueue(index + 1);
  if (y > 0) enqueue(index - info.width);
  if (y + 1 < info.height) enqueue(index + info.width);
}

for (let index = 0; index < pixelCount; index += 1) {
  if (visited[index]) data[index * 4 + 3] = 0;
}

await mkdir(path.dirname(output), { recursive: true });
await sharp(data, { raw: info }).png({ compressionLevel: 9 }).toFile(output);

console.log(`Removed ${tail} connected background pixels from ${info.width}x${info.height} logo.`);
