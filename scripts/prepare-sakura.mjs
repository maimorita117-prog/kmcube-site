import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const outputDirectory = path.resolve('dist/client');
const portableFiles = ['index.html', '404.html', 'index.rsc'];

for (const relativePath of portableFiles) {
  const filePath = path.join(outputDirectory, relativePath);
  const source = await readFile(filePath, 'utf8');
  const portable = source.replaceAll('"/_next/', '"./_next/');

  if (portable.includes('"/_next/')) {
    throw new Error(`Root-relative asset URL remains in ${relativePath}`);
  }

  await writeFile(filePath, portable, 'utf8');
}

console.log('Sakura package prepared with relative asset URLs.');
