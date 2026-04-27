import type { SikshyaReactConfig } from '../types';

/** Tailwind `brand-*` stops used across the React admin. */
const BRAND_SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const;

type Rgb = { r: number; g: number; b: number };

const DEFAULT_ACCENT_HEX = '#2563eb';

function clamp255(n: number): number {
  return Math.max(0, Math.min(255, Math.round(n)));
}

function normalizeHex(input?: string | null): string | null {
  if (!input) {
    return null;
  }
  let h = String(input).trim();
  if (h === '') {
    return null;
  }
  if (h.startsWith('#')) {
    h = h.slice(1);
  }
  if (h.length === 3) {
    h = h
      .split('')
      .map((c) => c + c)
      .join('');
  }
  if (h.length !== 6 || !/^[0-9a-fA-F]{6}$/.test(h)) {
    return null;
  }
  return `#${h.toLowerCase()}`;
}

function parseRgb(hex: string): Rgb {
  const h = hex.startsWith('#') ? hex.slice(1) : hex;
  return {
    r: parseInt(h.slice(0, 2), 16),
    g: parseInt(h.slice(2, 4), 16),
    b: parseInt(h.slice(4, 6), 16),
  };
}

function mixRgb(a: Rgb, b: Rgb, t: number): Rgb {
  const u = Math.max(0, Math.min(1, t));
  return {
    r: clamp255(a.r + (b.r - a.r) * u),
    g: clamp255(a.g + (b.g - a.g) * u),
    b: clamp255(a.b + (b.b - a.b) * u),
  };
}

function relLuminance(hex: string): number {
  const { r, g, b } = parseRgb(hex);
  const lin = (c: number) => {
    const x = c / 255;
    return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4);
  };
  const R = lin(r);
  const G = lin(g);
  const B = lin(b);
  return 0.2126 * R + 0.7152 * G + 0.0722 * B;
}

function rgbTriplet(rgb: Rgb): string {
  return `${rgb.r} ${rgb.g} ${rgb.b}`;
}

/**
 * Build a Tailwind-like brand ramp from a single accent (sidebar or saved brand hex).
 * Returns space-separated RGB triplets for `rgb(var(--token) / <alpha-value>)` usage.
 */
export function buildBrandRgbScaleFromAccent(accentHex: string): Record<(typeof BRAND_SHADES)[number], string> {
  const base = parseRgb(accentHex);
  const white: Rgb = { r: 255, g: 255, b: 255 };
  const black: Rgb = { r: 0, g: 0, b: 0 };

  const out = {} as Record<(typeof BRAND_SHADES)[number], string>;
  out[50] = rgbTriplet(mixRgb(base, white, 0.92));
  out[100] = rgbTriplet(mixRgb(base, white, 0.78));
  out[200] = rgbTriplet(mixRgb(base, white, 0.62));
  out[300] = rgbTriplet(mixRgb(base, white, 0.45));
  out[400] = rgbTriplet(mixRgb(base, white, 0.28));
  out[500] = rgbTriplet(base);
  out[600] = rgbTriplet(mixRgb(base, black, 0.14));
  out[700] = rgbTriplet(mixRgb(base, black, 0.26));
  out[800] = rgbTriplet(mixRgb(base, black, 0.38));
  out[900] = rgbTriplet(mixRgb(base, black, 0.5));
  out[950] = rgbTriplet(mixRgb(base, black, 0.62));
  return out;
}

/**
 * Pick one hex to drive admin `brand-*` tokens. Prefer sidebar (school chrome), then the
 * saved “top header / accent” colour from white-label settings — not used to paint the top bar.
 */
export function pickAdminAccentHex(branding?: SikshyaReactConfig['branding']): string {
  const top = normalizeHex(branding?.topbarBg);
  const side = normalizeHex(branding?.sidebarBg);

  const tryPick = (hex: string | null): string | null => {
    if (!hex) {
      return null;
    }
    const lum = relLuminance(hex);
    if (lum < 0.93) {
      return hex;
    }
    return null;
  }

  const fromSide = tryPick(side);
  if (fromSide) {
    return fromSide;
  }
  const fromTop = tryPick(top);
  if (fromTop) {
    return fromTop;
  }
  if (side) {
    return side;
  }
  if (top) {
    return top;
  }
  return DEFAULT_ACCENT_HEX;
}

export function applyAdminBrandThemeToRoot(root: HTMLElement, branding?: SikshyaReactConfig['branding']): void {
  const accent = pickAdminAccentHex(branding);
  const scale = buildBrandRgbScaleFromAccent(accent);
  for (const shade of BRAND_SHADES) {
    root.style.setProperty(`--sikshya-brand-${shade}-rgb`, scale[shade]);
  }
}

export function clearAdminBrandThemeFromRoot(root: HTMLElement): void {
  for (const shade of BRAND_SHADES) {
    root.style.removeProperty(`--sikshya-brand-${shade}-rgb`);
  }
}

/** `rgba(r,g,b,a)` from `#rrggbb` (or `rrggbb`). */
export function rgbaFromHex(hex: string, alpha: number): string | null {
  const n = normalizeHex(hex);
  if (!n) {
    return null;
  }
  const { r, g, b } = parseRgb(n);
  const a = Math.max(0, Math.min(1, alpha));
  return `rgba(${r}, ${g}, ${b}, ${a})`;
}
