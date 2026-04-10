import { useCallback, useEffect, useState } from 'react';
import { getWpApi } from '../../api';

export type WpPostRest = {
  id?: number;
  title?: { raw?: string; rendered?: string };
  content?: { raw?: string; rendered?: string };
  excerpt?: { raw?: string; rendered?: string };
  status?: string;
  featured_media?: number;
  meta?: Record<string, unknown>;
};

function stripTags(html: string): string {
  return html.replace(/<[^>]*>/g, '').trim();
}

export function titleFromPost(p: WpPostRest): string {
  const raw = p.title?.raw;
  if (raw !== undefined && raw !== '') {
    return raw;
  }
  const r = p.title?.rendered;
  return r ? stripTags(r) : '';
}

export function contentFromPost(p: WpPostRest): string {
  return p.content?.raw ?? stripTags(p.content?.rendered || '') ?? '';
}

export function excerptFromPost(p: WpPostRest): string {
  return p.excerpt?.raw ?? stripTags(p.excerpt?.rendered || '') ?? '';
}

/** Read meta whether REST uses leading underscore or not. */
export function readMeta(meta: Record<string, unknown> | undefined, dbKey: string): unknown {
  if (!meta) {
    return undefined;
  }
  const noUnderscore = dbKey.startsWith('_') ? dbKey.slice(1) : dbKey;
  return meta[dbKey] ?? meta[noUnderscore] ?? meta[`_${noUnderscore}`];
}

export function useWpContentPost(restBase: string, postId: number) {
  const isNew = postId <= 0;
  const path = `/${restBase.replace(/^\//, '')}`;

  const [post, setPost] = useState<WpPostRest | null>(isNew ? {} : null);
  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(async () => {
    if (isNew) {
      setPost({});
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const p = await getWpApi().get<WpPostRest>(`${path}/${postId}?context=edit`);
      setPost(p);
    } catch (e) {
      setError(e);
      setPost(null);
    } finally {
      setLoading(false);
    }
  }, [path, postId, isNew]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = useCallback(
    async (body: Record<string, unknown>) => {
      setSaving(true);
      setError(null);
      try {
        if (isNew) {
          const created = await getWpApi().post<WpPostRest & { id: number }>(`${path}`, body);
          return created;
        }
        return await getWpApi().put<WpPostRest>(`${path}/${postId}`, body);
      } catch (e) {
        setError(e);
        throw e;
      } finally {
        setSaving(false);
      }
    },
    [path, postId, isNew]
  );

  const remove = useCallback(async () => {
    if (isNew || !postId) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await getWpApi().delete(`${path}/${postId}?force=false`);
    } catch (e) {
      setError(e);
      throw e;
    } finally {
      setSaving(false);
    }
  }, [path, postId, isNew]);

  return {
    post,
    setPost,
    loading,
    saving,
    error,
    setError,
    load,
    save,
    remove,
    isNew,
    path,
  };
}
