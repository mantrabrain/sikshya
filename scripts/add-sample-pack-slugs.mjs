/**
 * Adds stable `slug` fields to sample-data/sample-lms.json courses and each curriculum item.
 * Run from the plugin root: node scripts/add-sample-pack-slugs.mjs
 */
import { readFileSync, writeFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const samplePath = join(root, 'sample-data', 'sample-lms.json');

function wpLikeSlug(input, maxLen) {
  let s = String(input ?? '')
    .trim()
    .toLowerCase();
  s = s.replace(/<[^>]+>/g, '');
  s = s.replace(/&[^;\s]+;/g, '');
  s = s.replace(/[^a-z0-9]+/g, '-');
  s = s.replace(/-+/g, '-').replace(/^-|-$/g, '');
  if (maxLen && s.length > maxLen) {
    s = s.slice(0, maxLen).replace(/-+$/g, '');
  }
  return s || 'item';
}

function itemSlug(courseIndex, chapterIndex, itemIndex, item) {
  const type = wpLikeSlug(item.type || 'lesson', 20);
  const titlePart = wpLikeSlug(item.title || '', 55);
  let raw = `sample-p${courseIndex}-ch${chapterIndex}-u${itemIndex + 1}-${type}-${titlePart}`;
  raw = wpLikeSlug(raw, 190);
  return raw;
}

const pack = JSON.parse(readFileSync(samplePath, 'utf8'));
if (!pack.courses || !Array.isArray(pack.courses)) {
  throw new Error('Invalid pack: missing courses[]');
}

pack.courses.forEach((course, ci) => {
  const titleStem = wpLikeSlug(String(course.title ?? '').replace(/^Sample:\s*/i, 'sample-'), 70);
  course.slug = wpLikeSlug(`${titleStem}-p${ci}`, 120);

  const chapters = Array.isArray(course.chapters) ? course.chapters : [];
  chapters.forEach((ch, chi) => {
    const contents = Array.isArray(ch.contents) ? ch.contents : [];
    contents.forEach((item, ii) => {
      item.slug = itemSlug(ci, chi, ii, item);
    });
  });
});

writeFileSync(samplePath, `${JSON.stringify(pack, null, 2)}\n`);
