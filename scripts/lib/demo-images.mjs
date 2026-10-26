// SPEC-041 REQ-DCAT-006: dependency-free placeholder pictures (flat shapes, deterministic PNG bytes).
import { deflateSync } from 'node:zlib';
import { crc32 } from './pack-theme.mjs';

function png(width, height, rgb) {
  const raw = Buffer.alloc((width * 3 + 1) * height);
  for (let y = 0; y < height; y++) {
    raw[y * (width * 3 + 1)] = 0;
    rgb.copy(raw, y * (width * 3 + 1) + 1, y * width * 3, (y + 1) * width * 3);
  }
  const chunk = (type, data) => {
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body));
    return Buffer.concat([len, body, crc]);
  };
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0);
  ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8;
  ihdr[9] = 2;
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}

function hsl(h, s, l) {
  s /= 100;
  l /= 100;
  const k = (n) => (n + h / 30) % 12;
  const a = s * Math.min(l, 1 - l);
  const f = (n) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
  return [Math.round(255 * f(0)), Math.round(255 * f(8)), Math.round(255 * f(4))];
}

class Canvas {
  constructor(w, h, bg) {
    this.w = w;
    this.h = h;
    this.buf = Buffer.alloc(w * h * 3);
    this.rect(0, 0, w, h, bg);
  }

  rect(x, y, w, h, [r, g, b], radius = 0) {
    const x0 = Math.max(0, Math.round(x));
    const y0 = Math.max(0, Math.round(y));
    const x1 = Math.min(this.w, Math.round(x + w));
    const y1 = Math.min(this.h, Math.round(y + h));
    for (let j = y0; j < y1; j++) {
      for (let i = x0; i < x1; i++) {
        if (radius) {
          const cx = i < x + radius ? x + radius : i >= x + w - radius ? x + w - radius : i;
          const cy = j < y + radius ? y + radius : j >= y + h - radius ? y + h - radius : j;
          if ((i - cx) ** 2 + (j - cy) ** 2 > radius ** 2) continue;
        }
        const o = (j * this.w + i) * 3;
        this.buf[o] = r;
        this.buf[o + 1] = g;
        this.buf[o + 2] = b;
      }
    }
  }

  circle(cx, cy, rad, col) {
    this.rect(cx - rad, cy - rad, rad * 2, rad * 2, col, rad);
  }

  toPng() {
    return png(this.w, this.h, this.buf);
  }
}

const SIZE = 480;

/** Product picture; `variant` (0..3) shifts the "viewing angle" so a gallery has distinct frames. */
export function productImage(kind, hue, variant = 0) {
  const bg = hsl(hue, 25, 94);
  const body = hsl(hue, 45, 42);
  const dark = hsl(hue, 40, 20);
  const light = hsl(hue, 60, 78);
  const c = new Canvas(SIZE, SIZE, bg);
  const v = variant;
  const squeeze = [1, 0.7, 0.45, 1][v];
  const cx = SIZE / 2;
  const cy = SIZE / 2;
  const w = (n) => n * squeeze;
  switch (kind) {
    case 'phone':
      c.rect(cx - w(95), cy - 170, w(190), 340, body, 26 * squeeze);
      c.rect(cx - w(80), cy - 150, w(160), 300, v === 1 ? dark : light, 14 * squeeze);
      c.circle(cx, cy - 158, 5, dark);
      if (v === 3) { c.rect(cx - 40, cy - 130, 80, 80, body, 18); c.circle(cx - 14, cy - 104, 14, dark); c.circle(cx + 14, cy - 76, 14, dark); }
      break;
    case 'laptop':
      c.rect(cx - w(170), cy - 120, w(340), 200, body, 12);
      c.rect(cx - w(152), cy - 104, w(304), 168, v === 1 ? dark : light, 6);
      c.rect(cx - w(205), cy + 82, w(410), 22, dark, 10);
      break;
    case 'tablet':
      c.rect(cx - w(150), cy - 190, w(300), 380, body, 24);
      c.rect(cx - w(134), cy - 172, w(268), 344, v === 1 ? dark : light, 10);
      break;
    case 'headphones':
      c.circle(cx, cy - 10, 120 * Math.max(squeeze, 0.6), body);
      c.circle(cx, cy - 10, 96 * Math.max(squeeze, 0.6), bg);
      c.rect(cx - w(150), cy + 20, w(70), 130, dark, 26);
      c.rect(cx + w(80), cy + 20, w(70), 130, dark, 26);
      break;
    case 'speaker':
      c.rect(cx - w(80), cy - 160, w(160), 320, body, 40);
      c.circle(cx, cy - 60, 44 * Math.max(squeeze, 0.6), light);
      c.circle(cx, cy + 70, 56 * Math.max(squeeze, 0.6), dark);
      break;
    case 'tv':
      c.rect(cx - w(200), cy - 130, w(400), 230, dark, 8);
      c.rect(cx - w(188), cy - 118, w(376), 206, v === 1 ? body : light, 4);
      c.rect(cx - w(60), cy + 100, w(120), 14, dark);
      c.rect(cx - w(100), cy + 114, w(200), 10, dark, 5);
      break;
    default:
      c.rect(cx - w(70), cy - 50, w(140), 100, body, 20);
      c.circle(cx + w(48), cy + 70, 24, dark);
      c.rect(cx - w(10), cy - 120, w(20), 70, dark, 8);
  }
  return c.toPng();
}

export function categoryImage(kind, hue) {
  return productImage(kind, hue, 0);
}

export function brandImage(hue) {
  const c = new Canvas(240, 120, hsl(hue, 25, 96));
  c.circle(120, 60, 36, hsl(hue, 55, 45));
  c.circle(120, 60, 16, hsl(hue, 25, 96));
  return c.toPng();
}

export function bannerImage(hue, index) {
  const c = new Canvas(1200, 400, hsl(hue, 35, 92));
  c.circle(900 + index * 20, 200, 170, hsl(hue, 55, 62));
  c.circle(900 + index * 20, 200, 110, hsl(hue, 55, 50));
  c.rect(120, 130, 320, 18, hsl(hue, 45, 30), 9);
  c.rect(120, 170, 220, 12, hsl(hue, 30, 55), 6);
  return c.toPng();
}

/** @returns {Map<string, Buffer>} path relative to image/ (e.g. catalog/ocs-demo/products/001-1.png) -> bytes */
export function demoImages(data) {
  const out = new Map();
  const base = 'catalog/ocs-demo/';
  for (const p of data.products) {
    for (let i = 0; i < p.images; i++) out.set(`${base}products/${p.code}-${i + 1}.png`, productImage(p.kind, p.hue, i));
  }
  for (const c of data.categories) {
    if (!c.parent) out.set(`${base}categories/${c.code}.png`, categoryImage(c.kind, (c.code.length * 47) % 360));
  }
  for (const b of data.brands) out.set(`${base}brands/${b.code}.png`, brandImage(b.hue));
  data.banners.forEach((b, i) => out.set(`${base}banners/${b.code}.png`, bannerImage(b.hue, i)));
  return out;
}

/** Image paths (relative to image/) the DemoCatalogue PHP code will reference for this data (REQ-DCAT-009). */
export function referencedImages(data) {
  const base = 'catalog/ocs-demo/';
  const out = [];
  for (const p of data.products) for (let i = 1; i <= p.images; i++) out.push(`${base}products/${p.code}-${i}.png`);
  for (const c of data.categories) if (!c.parent) out.push(`${base}categories/${c.code}.png`);
  for (const b of data.brands) out.push(`${base}brands/${b.code}.png`);
  for (const b of data.banners) out.push(`${base}banners/${b.code}.png`);
  return out;
}
