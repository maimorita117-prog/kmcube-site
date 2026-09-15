import { copyFile, mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const outputDirectory = path.resolve('dist/client');
const portableFiles = ['index.html', '404.html', 'index.rsc'];
const cssDirectory = path.join(outputDirectory, '_next', 'static', 'css');
const cssFiles = (await readdir(cssDirectory)).filter((fileName) => fileName.endsWith('.css'));

if (cssFiles.length !== 1) {
  throw new Error(`Expected one generated stylesheet, found ${cssFiles.length}`);
}

const generatedCssPath = `_next/static/css/${cssFiles[0]}`;
await copyFile(path.join(outputDirectory, generatedCssPath), path.join(outputDirectory, 'site.css'));

for (const relativePath of portableFiles) {
  const filePath = path.join(outputDirectory, relativePath);
  const source = await readFile(filePath, 'utf8');
  const portable = source
    .replaceAll('"/_next/', '"./_next/')
    .replaceAll(`./${generatedCssPath}`, './site.css')
    .replaceAll(`/${generatedCssPath}`, '/site.css');

  if (portable.includes('"/_next/')) {
    throw new Error(`Root-relative asset URL remains in ${relativePath}`);
  }

  if (portable.includes(generatedCssPath)) {
    throw new Error(`Hashed stylesheet URL remains in ${relativePath}`);
  }

  await writeFile(filePath, portable, 'utf8');
}

await mkdir(path.join(outputDirectory, 'api', 'storage'), { recursive: true });
await copyFile(path.resolve('public/api/.htaccess'), path.join(outputDirectory, 'api', '.htaccess'));
await copyFile(path.resolve('public/api/storage/.htaccess'), path.join(outputDirectory, 'api', 'storage', '.htaccess'));
await copyFile(path.resolve('SAKURA-BOOKING-SETUP.txt'), path.join(outputDirectory, 'SAKURA-BOOKING-SETUP.txt'));

console.log('Sakura package prepared with relative asset URLs and stable site.css.');
