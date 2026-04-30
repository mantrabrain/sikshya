import { useEffect, useMemo, useState } from 'react';
import type { WpPost } from '../../../types';
import { getWpApi } from '../../../api';
import { wpFeaturedThumbnailUrl } from '../../../lib/wpFeaturedMedia';
import { NavIcon } from '../../NavIcon';

type MediaResponse = {
  source_url?: string;
  media_details?: { sizes?: Record<string, { source_url?: string }> };
};

/** Pick the first usable image URL from REST media `sizes`. */
function firstSizeUrl(sizes: unknown): string | null {
  if (!sizes || typeof sizes !== 'object') {
    return null;
  }
  const preferred = ['medium', 'large', 'thumbnail', 'full'];
  const s = sizes as Record<string, { source_url?: string } | undefined>;
  for (const key of preferred) {
    const u = s[key]?.source_url;
    if (typeof u === 'string' && u.trim() !== '') {
      return u;
    }
  }
  for (const entry of Object.values(s)) {
    const u = entry?.source_url;
    if (typeof u === 'string' && u.trim() !== '') {
      return u;
    }
  }
  return null;
}

const mediaUrlCache = new Map<number, string | null>();

export function FeaturedThumb(props: { post: WpPost; emptyIcon?: 'photoImage' | 'badge' | 'image' }) {
  const { post } = props;

  const embedded = useMemo(() => wpFeaturedThumbnailUrl(post), [post]);
  const mediaId = useMemo(() => {
    const id = (post as unknown as { featured_media?: unknown }).featured_media;
    const n = typeof id === 'number' ? id : parseInt(String(id || '0'), 10);
    return Number.isFinite(n) ? n : 0;
  }, [post]);

  const [fetchedUrl, setFetchedUrl] = useState<string | null>(null);

  useEffect(() => {
    if (embedded) {
      setFetchedUrl(null);
      return;
    }
    if (!mediaId || mediaId <= 0) {
      setFetchedUrl(null);
      return;
    }
    if (mediaUrlCache.has(mediaId)) {
      setFetchedUrl(mediaUrlCache.get(mediaId) ?? null);
      return;
    }

    let alive = true;
    void getWpApi()
      .get<MediaResponse>(`/media/${mediaId}?_fields=source_url,media_details`)
      .then((m) => {
        const top = typeof m?.source_url === 'string' && m.source_url.trim() !== '' ? m.source_url : null;
        const fromSizes = firstSizeUrl(m?.media_details?.sizes);
        const url = top || fromSizes || null;
        mediaUrlCache.set(mediaId, url);
        if (alive) {
          setFetchedUrl(url);
        }
      })
      .catch(() => {
        mediaUrlCache.set(mediaId, null);
        if (alive) {
          setFetchedUrl(null);
        }
      });

    return () => {
      alive = false;
    };
  }, [embedded, mediaId]);

  const src = embedded || fetchedUrl;
  const raw = props.emptyIcon || 'photoImage';
  const icon = raw === 'badge' || raw === 'image' ? 'photoImage' : raw;

  return (
    <div className="flex h-12 w-14 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800">
      {src ? (
        <img src={src} alt="" className="h-full w-full object-cover" loading="lazy" />
      ) : (
        <NavIcon name={icon} className="h-5 w-5 text-slate-300 dark:text-slate-600" />
      )}
    </div>
  );
}

