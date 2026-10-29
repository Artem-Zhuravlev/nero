// SPEC-041: node scripts/gen-demo-catalogue.mjs  ->  custom/catalog/demo/catalogue.json
// Deterministic source of the demo catalogue. The JSON is committed and human-editable; re-run this script to regenerate it.
// Brands are fictional on purpose (no trademarks, no real product data).
import { writeFileSync, mkdirSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { addCustomFields } from './lib/demo-custom-fields.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const L = (uk, ru, en) => ({ uk, ru, en });
const same = (s) => L(s, s, s);

const attributeGroups = [{ code: 'main', name: L('Основні', 'Основные', 'Main'), sort: 1 }];
const attributes = [
  { code: 'screen', group: 'main', name: L('Екран', 'Экран', 'Screen') },
  { code: 'ram', group: 'main', name: L("Оперативна пам'ять", 'Оперативная память', 'RAM') },
  { code: 'battery', group: 'main', name: L('Акумулятор', 'Аккумулятор', 'Battery') },
  { code: 'os', group: 'main', name: L('Операційна система', 'Операционная система', 'Operating system') },
  { code: 'camera', group: 'main', name: L('Камера', 'Камера', 'Camera') },
  { code: 'weight', group: 'main', name: L('Вага', 'Вес', 'Weight') },
  { code: 'warranty', group: 'main', name: L('Гарантія', 'Гарантия', 'Warranty') },
];

const options = [
  {
    code: 'color', type: 'select', name: L('Колір', 'Цвет', 'Colour'),
    values: [
      { code: 'black', name: L('Чорний', 'Чёрный', 'Black'), price: 0 },
      { code: 'silver', name: L('Сріблястий', 'Серебристый', 'Silver'), price: 0 },
      { code: 'blue', name: L('Синій', 'Синий', 'Blue'), price: 0 },
    ],
  },
  {
    code: 'memory', type: 'radio', name: L("Пам'ять", 'Память', 'Storage'),
    values: [
      { code: '128', name: same('128 GB'), price: 0 },
      { code: '256', name: same('256 GB'), price: 1500 },
      { code: '512', name: same('512 GB'), price: 4000 },
    ],
  },
];

const brands = ['Nordix', 'Lumio', 'Voltra', 'Kiro', 'Aurex', 'Zenit', 'Pixora', 'Tonic'].map((name, i) => ({ code: name.toLowerCase(), name, hue: (i * 45 + 20) % 360 }));

// kind drives placeholder artwork and description templates
const categories = [
  { code: 'phones', parent: null, kind: 'phone', icon: 'fa-mobile', popular: 1, name: L('Смартфони', 'Смартфоны', 'Smartphones') },
  { code: 'phones-flagship', parent: 'phones', kind: 'phone', name: L('Флагмани', 'Флагманы', 'Flagships') },
  { code: 'phones-mid', parent: 'phones', kind: 'phone', name: L('Середній клас', 'Средний класс', 'Mid-range') },
  { code: 'phones-budget', parent: 'phones', kind: 'phone', name: L('Бюджетні', 'Бюджетные', 'Budget') },
  { code: 'laptops', parent: null, kind: 'laptop', icon: 'fa-laptop', popular: 1, name: L('Ноутбуки', 'Ноутбуки', 'Laptops') },
  { code: 'laptops-gaming', parent: 'laptops', kind: 'laptop', name: L('Ігрові', 'Игровые', 'Gaming') },
  { code: 'laptops-gaming-15', parent: 'laptops-gaming', kind: 'laptop', name: L('Ігрові 15"', 'Игровые 15"', 'Gaming 15"') },
  { code: 'laptops-ultra', parent: 'laptops', kind: 'laptop', name: L('Ультрабуки', 'Ультрабуки', 'Ultrabooks') },
  { code: 'laptops-office', parent: 'laptops', kind: 'laptop', name: L('Для роботи', 'Для работы', 'For work') },
  { code: 'tablets', parent: null, kind: 'tablet', icon: 'fa-tablet', name: L('Планшети', 'Планшеты', 'Tablets') },
  { code: 'audio', parent: null, kind: 'headphones', icon: 'fa-headphones', name: L('Аудіо', 'Аудио', 'Audio') },
  { code: 'headphones', parent: 'audio', kind: 'headphones', name: L('Навушники', 'Наушники', 'Headphones') },
  { code: 'speakers', parent: 'audio', kind: 'speaker', name: L('Колонки', 'Колонки', 'Speakers') },
  { code: 'tv', parent: null, kind: 'tv', icon: 'fa-desktop', name: L('Телевізори та монітори', 'Телевизоры и мониторы', 'TVs and monitors') },
  { code: 'tvs', parent: 'tv', kind: 'tv', name: L('Телевізори', 'Телевизоры', 'TVs') },
  { code: 'monitors', parent: 'tv', kind: 'tv', name: L('Монітори', 'Мониторы', 'Monitors') },
  { code: 'accessories', parent: null, kind: 'accessory', icon: 'fa-plug', name: L('Аксесуари', 'Аксессуары', 'Accessories') },
  { code: 'chargers', parent: 'accessories', kind: 'accessory', name: L('Зарядні пристрої', 'Зарядные устройства', 'Chargers') },
  { code: 'cases', parent: 'accessories', kind: 'accessory', name: L('Чохли', 'Чехлы', 'Cases') },
];

const DESC = {
  phone: (n, b, a) => L(
    `<p><b>${n}</b> — смартфон від ${b} з екраном ${a.screen} та акумулятором ${a.battery}. Швидкий процесор, якісна камера ${a.camera} і фірмова гарантія.</p><ul><li>Екран ${a.screen}</li><li>Пам'ять ${a.ram}</li><li>Камера ${a.camera}</li></ul>`,
    `<p><b>${n}</b> — смартфон от ${b} с экраном ${a.screen} и аккумулятором ${a.battery}. Быстрый процессор, качественная камера ${a.camera} и фирменная гарантия.</p><ul><li>Экран ${a.screen}</li><li>Память ${a.ram}</li><li>Камера ${a.camera}</li></ul>`,
    `<p><b>${n}</b> is a smartphone from ${b} with a ${a.screen} display and a ${a.battery} battery. Fast processor, great ${a.camera} camera and a brand warranty.</p><ul><li>Display ${a.screen}</li><li>Memory ${a.ram}</li><li>Camera ${a.camera}</li></ul>`),
  laptop: (n, b, a) => L(
    `<p><b>${n}</b> — ноутбук від ${b} з екраном ${a.screen}, ${a.ram} оперативної пам'яті та швидким SSD. Тихе охолодження, якісна клавіатура.</p>`,
    `<p><b>${n}</b> — ноутбук от ${b} с экраном ${a.screen}, ${a.ram} оперативной памяти и быстрым SSD. Тихое охлаждение, качественная клавиатура.</p>`,
    `<p><b>${n}</b> is a laptop from ${b} with a ${a.screen} display, ${a.ram} of RAM and a fast SSD. Quiet cooling and a comfortable keyboard.</p>`),
  tablet: (n, b, a) => L(
    `<p><b>${n}</b> — планшет від ${b} з екраном ${a.screen} для читання, відео та роботи в дорозі.</p>`,
    `<p><b>${n}</b> — планшет от ${b} с экраном ${a.screen} для чтения, видео и работы в дороге.</p>`,
    `<p><b>${n}</b> is a tablet from ${b} with a ${a.screen} display for reading, video and work on the go.</p>`),
  headphones: (n, b) => L(
    `<p><b>${n}</b> — навушники ${b} з чистим звуком і довгим часом роботи.</p>`,
    `<p><b>${n}</b> — наушники ${b} с чистым звуком и длительным временем работы.</p>`,
    `<p><b>${n}</b> — headphones by ${b} with clear sound and long battery life.</p>`),
  speaker: (n, b) => L(
    `<p><b>${n}</b> — портативна колонка ${b} з об'ємним звуком і захистом від води.</p>`,
    `<p><b>${n}</b> — портативная колонка ${b} с объёмным звуком и защитой от воды.</p>`,
    `<p><b>${n}</b> — a portable speaker by ${b} with full sound and water resistance.</p>`),
  tv: (n, b, a) => L(
    `<p><b>${n}</b> від ${b} — екран ${a.screen}, яскрава картинка й зручний Smart-інтерфейс.</p>`,
    `<p><b>${n}</b> от ${b} — экран ${a.screen}, яркая картинка и удобный Smart-интерфейс.</p>`,
    `<p><b>${n}</b> by ${b} — a ${a.screen} screen, bright picture and a handy Smart interface.</p>`),
  accessory: (n, b) => L(
    `<p><b>${n}</b> — аксесуар ${b}, перевірена якість і сумісність.</p>`,
    `<p><b>${n}</b> — аксессуар ${b}, проверенное качество и совместимость.</p>`,
    `<p><b>${n}</b> — an accessory by ${b}, proven quality and compatibility.</p>`),
};

let seed = 7;
const rnd = () => { seed = (seed * 1664525 + 1013904223) % 4294967296; return seed / 4294967296; };
const pick = (arr) => arr[Math.floor(rnd() * arr.length)];
const round = (n, step) => Math.round(n / step) * step;

const lines = {
  phone: ['Aero', 'Pulse', 'Nova', 'Edge', 'Flow', 'Spark'],
  laptop: ['Book', 'Station', 'Slim', 'Forge'],
  tablet: ['Tab', 'Pad'],
  headphones: ['Beat', 'Air', 'Wave'],
  speaker: ['Boom', 'Roll'],
  tv: ['Vision', 'Frame', 'View'],
  accessory: ['Link', 'Shield', 'Charge', 'Cable'],
};

// [subcategory, count, price range, parent categories, accessory targets, options]
const plan = [
  ['phones-flagship', 6, [28000, 48000], ['phones'], ['color', 'memory']],
  ['phones-mid', 6, [12000, 20000], ['phones'], ['color']],
  ['phones-budget', 6, [4000, 8000], ['phones'], []],
  ['laptops-gaming-15', 2, [52000, 80000], ['laptops-gaming', 'laptops'], ['memory']],
  ['laptops-gaming', 1, [45000, 60000], ['laptops'], ['memory']],
  ['laptops-ultra', 3, [35000, 60000], ['laptops'], ['memory']],
  ['laptops-office', 2, [18000, 30000], ['laptops'], []],
  ['tablets', 3, [9000, 22000], [], ['color']],
  ['headphones', 3, [1200, 9000], ['audio'], ['color']],
  ['speakers', 2, [1500, 6000], ['audio'], []],
  ['tvs', 2, [14000, 40000], ['tv'], []],
  ['monitors', 1, [7000, 11000], ['tv'], []],
  ['chargers', 2, [400, 1500], ['accessories'], []],
  ['cases', 2, [300, 900], ['accessories'], ['color']],
  ['accessories', 1, [200, 400], [], []],
];

const catByCode = Object.fromEntries(categories.map((c) => [c.code, c]));
const products = [];
let n = 0;
for (const [sub, count, [lo, hi], parents, opts] of plan) {
  const cat = catByCode[sub];
  for (let i = 0; i < count; i++) {
    n++;
    const brand = brands[(n * 3) % brands.length];
    const line = pick(lines[cat.kind]);
    const gen = 10 + Math.floor(rnd() * 5);
    const suffix = ['', ' Pro', ' Max', ' Lite'][Math.floor(rnd() * 4)];
    const name = `${brand.name} ${line} ${gen}${suffix}`;
    const price = round(lo + rnd() * (hi - lo), lo > 5000 ? 100 : 10);
    const kind = cat.kind;
    const a = {
      screen: kind === 'phone' ? `${(6 + rnd() * 0.9).toFixed(1)}"` : kind === 'laptop' ? pick(['13.3"', '14"', '15.6"', '16"']) : kind === 'tablet' ? pick(['10.1"', '11"', '12.4"']) : kind === 'tv' ? pick(['27"', '32"', '43"', '55"', '65"']) : '',
      ram: kind === 'laptop' ? pick(['16 GB', '32 GB']) : `${pick([4, 6, 8, 12])} GB`,
      battery: `${4000 + Math.floor(rnd() * 12) * 100} mAh`,
      camera: `${pick([12, 48, 50, 64, 108])} MP`,
      os: kind === 'laptop' ? 'Windows 11' : 'Android 14',
      weight: `${kind === 'laptop' ? (1.2 + rnd()).toFixed(1) + ' kg' : Math.floor(150 + rnd() * 100) + ' g'}`,
    };
    const attrs = {};
    if (['phone', 'laptop', 'tablet', 'tv'].includes(kind)) attrs.screen = a.screen;
    if (['phone', 'laptop', 'tablet'].includes(kind)) { attrs.ram = a.ram; attrs.os = a.os; attrs.weight = a.weight; }
    if (['phone', 'tablet'].includes(kind)) attrs.battery = a.battery;
    if (kind === 'phone') attrs.camera = a.camera;
    attrs.warranty = L('12 міс.', '12 мес.', '12 months');

    // states: out of stock, pre-order, specials, hits, old items
    const state = n % 9 === 0 ? 'out' : n % 13 === 0 ? 'preorder' : 'in';
    const special = n % 3 === 0 && state === 'in' ? round(price * 0.88, 10) : 0;
    products.push({
      code: String(n).padStart(3, '0'),
      category: sub,
      categories: parents,
      brand: brand.code,
      name: same(name),
      description: DESC[kind](name, brand.name, a),
      price,
      special,
      quantity: state === 'in' ? 5 + Math.floor(rnd() * 40) : 0,
      state,
      sticker: n % 5 === 0 ? 'hit' : '',
      isNew: n <= 4,
      attrs,
      options: opts,
      kind,
      hue: (brand.hue + n * 7) % 360,
      images: kind === 'accessory' || kind === 'speaker' ? 2 : 4,
      reviews: n % 2 === 0 ? 1 + (n % 3) : 0,
    });
  }
}

// related products: the next two of the same subcategory; accessories: phones -> chargers/cases
const bySub = {};
products.forEach((p) => (bySub[p.category] = [...(bySub[p.category] || []), p]));
const accessories = [...(bySub.chargers || []), ...(bySub.cases || [])];
for (const p of products) {
  const peers = bySub[p.category];
  const i = peers.indexOf(p);
  p.related = peers.filter((x) => x !== p).slice(Math.max(0, i - 1), i + 2).map((x) => x.code).slice(0, 3);
  p.accessories = ['phone', 'tablet', 'laptop'].includes(p.kind) ? accessories.slice(0, 3).map((x) => x.code) : [];
}

const REVIEWS = [
  { author: 'Олена', rating: 5, text: L('Дуже задоволена покупкою, усе працює швидко.', 'Очень довольна покупкой, всё работает быстро.', 'Very happy with the purchase, everything is fast.') },
  { author: 'Андрій', rating: 4, text: L('Хороший товар за свої гроші. Доставили вчасно.', 'Хороший товар за свои деньги. Доставили вовремя.', 'Good value for the money. Delivered on time.') },
  { author: 'Ігор', rating: 5, text: L('Рекомендую, якість на висоті.', 'Рекомендую, качество на высоте.', 'Recommended, the quality is great.') },
  { author: 'Марія', rating: 3, text: L('Непогано, але хотілося б довший час роботи.', 'Неплохо, но хотелось бы больше времени работы.', 'Not bad, but I would like longer battery life.') },
];

const information = [
  { code: 'about', title: L('Про нас', 'О нас', 'About us'), text: L('<p>Демонстраційний магазин електроніки для перегляду теми OCS.</p>', '<p>Демонстрационный магазин электроники для просмотра темы OCS.</p>', '<p>A demo electronics store for previewing the OCS theme.</p>'), bottom: 1 },
  { code: 'delivery', title: L('Доставка і оплата', 'Доставка и оплата', 'Delivery and payment'), text: L('<p>Самовивіз, пошта та кур\'єр. Оплата при отриманні або онлайн.</p>', '<p>Самовывоз, почта и курьер. Оплата при получении или онлайн.</p>', '<p>Pick-up, post and courier. Pay on delivery or online.</p>'), bottom: 1 },
  { code: 'warranty', title: L('Гарантія', 'Гарантия', 'Warranty'), text: L('<p>На всі товари діє гарантія виробника 12 місяців.</p>', '<p>На все товары действует гарантия производителя 12 месяцев.</p>', '<p>All goods carry a 12-month manufacturer warranty.</p>'), bottom: 1 },
  { code: 'contacts', title: L('Контакти', 'Контакты', 'Contacts'), text: L('<p>Телефон: +380 44 000 00 00. Щодня 9:00–21:00.</p>', '<p>Телефон: +380 44 000 00 00. Ежедневно 9:00–21:00.</p>', '<p>Phone: +380 44 000 00 00. Daily 9:00–21:00.</p>'), bottom: 1 },
];

const data = {
  version: 2,
  note: 'Built by scripts/gen-demo-catalogue.mjs; fictional brands, placeholder pictures.',
  attributeGroups, attributes, options, brands, categories, products, reviews: REVIEWS, information,
  banners: [
    { code: 'b1', link: 'phones', hue: 215, title: L('Смартфони', 'Смартфоны', 'Smartphones'), text: L('Нові моделі', 'Новые модели', 'New models') },
    { code: 'b2', link: 'laptops', hue: 160, title: L('Ноутбуки', 'Ноутбуки', 'Laptops'), text: L('Знижки до 15%', 'Скидки до 15%', 'Up to 15% off') },
    { code: 'b3', link: 'audio', hue: 20, title: L('Аудіо', 'Аудио', 'Audio'), text: L('Хіти продажів', 'Хиты продаж', 'Best sellers') },
  ],
};

addCustomFields(data);

const out = join(ROOT, 'custom', 'catalog', 'demo', 'catalogue.json');
mkdirSync(dirname(out), { recursive: true });
writeFileSync(out, `${JSON.stringify(data, null, 1)}\n`);
console.log(`wrote ${out}: ${products.length} products, ${categories.length} categories, ${brands.length} brands`);
