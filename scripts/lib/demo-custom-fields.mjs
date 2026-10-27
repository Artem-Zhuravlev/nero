// Custom-field demo data (SPEC-068): videos, questions, review pros/cons, stickers, discounts, contacts, module settings, admin-list records.
// Mutates the catalogue object built by gen-demo-catalogue.mjs. Deterministic.

export function addCustomFields(j) {
  const t = (uk, ru, en) => ({ uk, ru, en });
  const videos = ['aqz-KE-bpKQ', 'eRsGyueVLvQ', 'R6MlUcmOul8'];
  const qa = [
    [t('Чи є гарантія?', 'Есть ли гарантия?', 'Is there a warranty?'), t('Так, 12 місяців від виробника.', 'Да, 12 месяцев от производителя.', 'Yes, 12 months from the manufacturer.')],
    [t('Коли буде доставка?', 'Когда будет доставка?', 'When will it be delivered?'), t('Відправляємо в день замовлення, доставка 1-2 дні.', 'Отправляем в день заказа, доставка 1-2 дня.', 'We ship the same day, delivery takes 1-2 days.')],
    [t('Чи можна оплатити частинами?', 'Можно ли оплатить частями?', 'Can I pay in installments?'), t('Так, доступні плани від 3 до 24 місяців.', 'Да, доступны планы от 3 до 24 месяцев.', 'Yes, plans from 3 to 24 months are available.')],
    [t('Чи є в комплекті зарядка?', 'Есть ли в комплекте зарядка?', 'Is a charger included?'), null],
  ];
  const askers = ['Андрій', 'Марія', 'Ігор', 'Софія'];

  j.products.forEach((p, i) => {
    const n = parseInt(p.code, 10);
    p.sku = 'SKU-' + p.code;
    p.mpn = 'MPN-' + (7000 + n * 13);
    p.upc = String(100000000000 + n * 7919).slice(0, 12);
    p.ean = String(4000000000000 + n * 104729).slice(0, 13);
    p.location = 'A' + (1 + (n % 4)) + '-' + (10 + n);
    p.weight = Math.round((0.15 + (n % 9) * 0.35) * 100) / 100;
    p.dims = [60 + (n % 5) * 20, 40 + (n % 4) * 15, 8 + (n % 6) * 6];
    p.minimum = 1;
    p.points = (n % 5) * 10;
    if (n % 4 === 1) p.video = videos[n % 3];
    if (n % 3 !== 0) {
      const k = n % 4;
      p.questions = [{ name: askers[k], q: qa[k][0], a: qa[k][1], status: 1 }];
      if (n % 6 === 1) p.questions.push({ name: askers[(k + 1) % 4], q: qa[3][0], a: null, status: 1 });
      if (n % 9 === 1) p.questions.push({ name: askers[(k + 2) % 4], q: t('Чи є інші кольори?', 'Есть ли другие цвета?', 'Are other colours available?'), a: null, status: 0 });
    }
    if (n % 5 === 2 && p.state === 'in') p.discounts = [{ qty: 2, price: Math.round(p.price * 0.97) }, { qty: 5, price: Math.round(p.price * 0.93) }];
    const st = [];
    if (p.sticker) st.push(p.sticker);
    if (p.state === 'preorder') st.push('preorder');
    if (n % 7 === 3 && p.state === 'in') st.push('top');
    p.stickers = st;
  });
  // make sure a quantity discount exists on at least 5 products
  let withDisc = j.products.filter(p => p.discounts).length;
  for (const p of j.products) { if (withDisc >= 5) break; if (!p.discounts && p.state === 'in') { p.discounts = [{ qty: 3, price: Math.round(p.price * 0.95) }]; withDisc++; } }
  // stickers coverage guarantees
  if (!j.products.some(p => p.stickers.includes('top'))) j.products.find(p => p.state === 'in').stickers.push('top');
  if (!j.products.some(p => p.stickers.includes('preorder'))) j.products.find(p => p.state === 'preorder').stickers.push('preorder');

  const pros = [t('Швидка робота', 'Быстрая работа', 'Fast performance'), t('Гарна якість збірки', 'Хорошее качество сборки', 'Great build quality'), t('Вдала ціна', 'Удачная цена', 'Good price'), t('Довго тримає заряд', 'Долго держит заряд', 'Long battery life')];
  const cons = [t('Немає мінусів', 'Минусов нет', 'No downsides'), t('Трохи важкий', 'Немного тяжёлый', 'A bit heavy'), t('Комплектація скромна', 'Скромная комплектация', 'Modest bundle'), t('Дорогі аксесуари', 'Дорогие аксессуары', 'Pricey accessories')];
  j.reviews.forEach((r, i) => { r.pros = pros[i % 4]; r.cons = cons[i % 4]; });
  while (j.reviews.length < 8) {
    const i = j.reviews.length;
    const base = j.reviews[i % 4];
    j.reviews.push(Object.assign({}, base, { author: ['Тарас', 'Ірина', 'Богдан', 'Катерина'][i % 4], rating: 4 + (i % 2), pros: pros[(i + 1) % 4], cons: cons[(i + 2) % 4] }));
  }
  j.products.forEach((p, i) => { if (p.reviews === 0 && i % 5 === 0) p.reviews = 2; });

  // category / brand text and menu banners
  const bannerCodes = (j.banners || []).map(b => b.code);
  let tops = 0;
  j.categories.forEach(c => {
    const name = c.name;
    c.description = t('<p>' + name.uk + ' — добірка популярних моделей з гарантією.</p>', '<p>' + name.ru + ' — подборка популярных моделей с гарантией.</p>', '<p>' + name.en + ' — a selection of popular models with warranty.</p>');
    c.meta = { title: name, description: t('Купити: ' + name.uk, 'Купить: ' + name.ru, 'Buy: ' + name.en) };
    if (!c.parent) { c.banner = { image: bannerCodes[tops % bannerCodes.length], link: c.code }; tops++; }
  });
  const firstChildSeen = new Set();
  j.categories.forEach(c => { if (c.parent && !firstChildSeen.has(c.parent)) { firstChildSeen.add(c.parent); c.banner = { image: bannerCodes[firstChildSeen.size % bannerCodes.length], link: c.code }; } });

  const L = (uk, ru, en) => ({ uk, ru, en });
  j.settings = [
    { code: 'theme_ocs', key: 'theme_ocs_contact', value: { '@l': {
      address: L('м. Київ, вул. Хрещатик, 1', 'г. Киев, ул. Крещатик, 1', 'Kyiv, Khreshchatyk St. 1'),
      hours: L('Пн-Пт 9:00-19:00, Сб 10:00-16:00', 'Пн-Пт 9:00-19:00, Сб 10:00-16:00', 'Mon-Fri 9:00-19:00, Sat 10:00-16:00'),
      phones: L("+380 44 000 00 01 | Відділ продажів\n+380 44 000 00 02 | Підтримка", "+380 44 000 00 01 | Отдел продаж\n+380 44 000 00 02 | Поддержка", "+380 44 000 00 01 | Sales\n+380 44 000 00 02 | Support") } } },
    { code: 'theme_ocs', key: 'theme_ocs_contact_links', value: [
      { icon: 'telegram', kind: 'messenger', title: { '@lt': L('Telegram', 'Telegram', 'Telegram') }, url: 'https://t.me/example_demo', sort: 1 },
      { icon: 'viber', kind: 'messenger', title: { '@lt': L('Viber', 'Viber', 'Viber') }, url: 'viber://chat?number=%2B380440000001', sort: 2 },
      { icon: 'whatsapp', kind: 'messenger', title: { '@lt': L('WhatsApp', 'WhatsApp', 'WhatsApp') }, url: 'https://wa.me/380440000001', sort: 3 },
      { icon: 'messenger', kind: 'messenger', title: { '@lt': L('Messenger', 'Messenger', 'Messenger') }, url: 'https://m.me/example.demo', sort: 4 },
      { icon: 'instagram', kind: 'social', title: { '@lt': L('Instagram', 'Instagram', 'Instagram') }, url: 'https://instagram.com/example.demo', sort: 5 },
      { icon: 'facebook', kind: 'social', title: { '@lt': L('Facebook', 'Facebook', 'Facebook') }, url: 'https://facebook.com/example.demo', sort: 6 },
      { icon: 'youtube', kind: 'social', title: { '@lt': L('YouTube', 'YouTube', 'YouTube') }, url: 'https://youtube.com/@example-demo', sort: 7 },
      { icon: 'tiktok', kind: 'social', title: { '@lt': L('TikTok', 'TikTok', 'TikTok') }, url: 'https://tiktok.com/@example.demo', sort: 8 },
      { icon: 'email', kind: 'social', title: { '@lt': L('E-mail', 'E-mail', 'E-mail') }, url: 'mailto:shop@example.test', sort: 9 },
    ] },
    { code: 'theme_ocs', key: 'theme_ocs_info_blocks', value: [
      { icon: 'truck', title: { '@lt': L('Доставка', 'Доставка', 'Delivery') }, text: { '@lt': L('Нова Пошта та кур\'єр по Україні, від 1 дня.', 'Новая Почта и курьер по Украине, от 1 дня.', 'Courier and post across the country, from 1 day.') }, url: '/index.php?route=information/information&information_id=@info:delivery', sort: 1 },
      { icon: 'return', title: { '@lt': L('Повернення', 'Возврат', 'Returns') }, text: { '@lt': L('14 днів на повернення без пояснень.', '14 дней на возврат без объяснений.', '14 days to return, no questions asked.') }, url: '', sort: 2 },
      { icon: 'card', title: { '@lt': L('Оплата', 'Оплата', 'Payment') }, text: { '@lt': L('Карткою, готівкою або частинами.', 'Картой, наличными или частями.', 'Card, cash or installments.') }, url: '', sort: 3 },
      { icon: 'shield', title: { '@lt': L('Гарантія', 'Гарантия', 'Warranty') }, text: { '@lt': L('Офіційна гарантія від 12 місяців.', 'Официальная гарантия от 12 месяцев.', 'Official warranty from 12 months.') }, url: '', sort: 4 },
      { icon: 'gift', title: { '@lt': L('Бонуси', 'Бонусы', 'Bonuses') }, text: { '@lt': L('Бонусні бали за кожну покупку.', 'Бонусные баллы за каждую покупку.', 'Bonus points for every purchase.') }, url: '', sort: 5 },
      { icon: 'clock', title: { '@lt': L('Швидко', 'Быстро', 'Fast') }, text: { '@lt': L('Відправка в день замовлення до 16:00.', 'Отправка в день заказа до 16:00.', 'Same-day dispatch before 4 pm.') }, url: '', sort: 6 },
    ] },
    { code: 'theme_ocs', key: 'theme_ocs_top_menu', value: { enabled: 1, contacts: 1, blog: 1, sale: 1, title: { '@lt': L('Інформація', 'Информация', 'Information') }, pages: ['@info:about', '@info:delivery', '@info:warranty', '@info:contacts'] } },
    { code: 'module_ocs_benefits', key: 'module_ocs_benefits_status', value: 1 },
    { code: 'module_ocs_benefits', key: 'module_ocs_benefits_items', value: [
      { icon: 'truck', l: { '@l': { title: L('Швидка доставка', 'Быстрая доставка', 'Fast delivery'), text: L('По всій Україні від 1 дня', 'По всей Украине от 1 дня', 'Nationwide from 1 day') } } },
      { icon: 'shield', l: { '@l': { title: L('Гарантія', 'Гарантия', 'Warranty'), text: L('Від 12 місяців', 'От 12 месяцев', 'From 12 months') } } },
      { icon: 'undo', l: { '@l': { title: L('Повернення 14 днів', 'Возврат 14 дней', '14-day returns'), text: L('Без зайвих питань', 'Без лишних вопросов', 'No questions asked') } } },
      { icon: 'headphones', l: { '@l': { title: L('Підтримка', 'Поддержка', 'Support'), text: L('Щодня 9:00-19:00', 'Ежедневно 9:00-19:00', 'Daily 9:00-19:00') } } },
    ] },
    { code: 'module_ocs_credit', key: 'module_ocs_credit_status', value: 1 },
    { code: 'module_ocs_credit', key: 'module_ocs_credit_plans', value: [
      { bank: 'Demo Bank', months: 3, rate: 0, fee: 0, min: 1000, max: 0 },
      { bank: 'Demo Bank', months: 12, rate: 1.9, fee: 1, min: 3000, max: 150000 },
      { bank: 'Example Credit', months: 24, rate: 2.5, fee: 2, min: 5000, max: 300000 },
    ] },
    { code: 'module_ocs_credit', key: 'module_ocs_credit_l', value: { '@l': {
      disclaimer: L('Розрахунок орієнтовний і не є пропозицією.', 'Расчёт ориентировочный и не является предложением.', 'The calculation is indicative and not an offer.'),
      terms: L('Умови кредитування уточнюйте у менеджера.', 'Условия кредитования уточняйте у менеджера.', 'Ask the manager for the credit terms.') } } },
    { code: 'module_ocs_tradein', key: 'module_ocs_tradein_status', value: 1 },
    { code: 'module_ocs_tradein', key: 'module_ocs_tradein_categories', value: '@cats:phones,laptops' },
    { code: 'module_ocs_tradein', key: 'module_ocs_tradein_email', value: 'tradein@example.test' },
    { code: 'module_ocs_tradein', key: 'module_ocs_tradein_l', value: { '@l': {
      title: L('Обміняйте старий пристрій', 'Обменяйте старое устройство', 'Trade in your old device'),
      text: L('Оцінимо ваш гаджет і зарахуємо суму у вартість нового.', 'Оценим ваш гаджет и зачтём сумму в стоимость нового.', 'We appraise your gadget and deduct it from the new price.'),
      button: L('Оцінити пристрій', 'Оценить устройство', 'Appraise my device') } } },
    { code: 'module_ocs_preorder', key: 'module_ocs_preorder_status', value: 1 },
    { code: 'module_ocs_preorder', key: 'module_ocs_preorder_l', value: { '@l': {
      button: L('Передзамовлення', 'Предзаказ', 'Pre-order'),
      message: L('Дякуємо! Ми зв\'яжемося, щойно товар надійде.', 'Спасибо! Мы свяжемся, как только товар поступит.', 'Thank you! We will contact you as soon as it arrives.') } } },
    { code: 'module_ocs_newsletter', key: 'module_ocs_newsletter_status', value: 1 },
    { code: 'module_ocs_newsletter', key: 'module_ocs_newsletter_l', value: { '@l': {
      title: L('Знижки та новинки', 'Скидки и новинки', 'Deals and news'),
      text: L('Підпишіться і отримайте промокод на першу покупку.', 'Подпишитесь и получите промокод на первую покупку.', 'Subscribe and get a promo code for your first order.'),
      consent: L('Погоджуюсь на отримання розсилки', 'Согласен на получение рассылки', 'I agree to receive the newsletter') } } },
    { code: 'module_ocs_ajax_search', key: 'module_ocs_ajax_search_status', value: 1 },
    { code: 'module_ocs_ajax_search', key: 'module_ocs_ajax_search_l', value: { '@l': { popular: L("Смартфони\nНавушники\nНоутбуки\nЗарядка", "Смартфоны\nНаушники\nНоутбуки\nЗарядка", "Smartphones\nHeadphones\nLaptops\nCharger") } } },
    { code: 'module_ocs_seo_text', key: 'module_ocs_seo_text_status', value: 1 },
    { code: 'module_ocs_seo_text', key: 'module_ocs_seo_text_l', value: { '@l': {
      title: L('Інтернет-магазин електроніки', 'Интернет-магазин электроники', 'Electronics online store'),
      text: L('<p>Демонстраційний магазин: смартфони, ноутбуки, аудіо та аксесуари з гарантією й доставкою по всій Україні.</p>', '<p>Демонстрационный магазин: смартфоны, ноутбуки, аудио и аксессуары с гарантией и доставкой по всей Украине.</p>', '<p>A demo store: smartphones, laptops, audio and accessories with warranty and nationwide delivery.</p>') } } },
    { code: 'module_ocs_shell', key: 'module_ocs_shell_status', value: 1 },
    { code: 'module_ocs_shell', key: 'module_ocs_shell_announcement_from', value: '@date:-1' },
    { code: 'module_ocs_shell', key: 'module_ocs_shell_announcement_to', value: '@date:+90' },
    { code: 'module_ocs_shell', key: 'module_ocs_shell_l', value: { '@l': {
      announcement_text: L('Безкоштовна доставка від 2000 грн', 'Бесплатная доставка от 2000 грн', 'Free delivery over 2000 UAH'),
      announcement_url: L('/index.php?route=product/special', '/index.php?route=product/special', '/index.php?route=product/special'),
      footer_groups: L("Покупцям\nДоставка|/index.php?route=information/information&information_id=@info:delivery\nГарантія|/index.php?route=information/information&information_id=@info:warranty\n\nКомпанія\nПро нас|/index.php?route=information/information&information_id=@info:about\nКонтакти|/index.php?route=information/information&information_id=@info:contacts",
        "Покупателям\nДоставка|/index.php?route=information/information&information_id=@info:delivery\nГарантия|/index.php?route=information/information&information_id=@info:warranty\n\nКомпания\nО нас|/index.php?route=information/information&information_id=@info:about\nКонтакты|/index.php?route=information/information&information_id=@info:contacts",
        "Customers\nDelivery|/index.php?route=information/information&information_id=@info:delivery\nWarranty|/index.php?route=information/information&information_id=@info:warranty\n\nCompany\nAbout us|/index.php?route=information/information&information_id=@info:about\nContacts|/index.php?route=information/information&information_id=@info:contacts") } } },
    { code: 'module_ocs_mega_menu', key: 'module_ocs_mega_menu_status', value: 1 },
    { code: 'module_ocs_mega_menu', key: 'module_ocs_mega_menu_brands', value: 1 },
    { code: 'module_ocs_category_wall', key: 'module_ocs_category_wall_status', value: 1 },
    { code: 'module_ocs_viewed', key: 'module_ocs_viewed_status', value: 1 },
    { code: 'module_ocs_quick_order', key: 'module_ocs_quick_order_status', value: 1 },
    { code: 'module_ocs_back_in_stock', key: 'module_ocs_back_in_stock_status', value: 1 },
    { code: 'module_ocs_product_extras', key: 'module_ocs_product_extras_status', value: 1 },
    { code: 'module_ocs_stickers', key: 'module_ocs_stickers_status', value: 1 },
  ];

  j.records = {
    subscribers: [
      { email: 'anna@example.test', confirmed: 1 }, { email: 'oleg@example.test', confirmed: 1 }, { email: 'irina@example.test', confirmed: 0 },
      { email: 'maks@example.test', confirmed: 1 }, { email: 'lena@example.test', confirmed: 0 }, { email: 'vlad@example.test', confirmed: 1 },
    ],
    backInStock: [
      { product: null, email: 'wait1@example.test', status: 0 }, { product: null, email: 'wait2@example.test', status: 0 }, { product: null, email: 'wait3@example.test', status: 1 },
    ],
    preorders: [
      { name: 'Олена К.', phone: '+380000000101', email: 'preorder1@example.test', comment: 'Зателефонуйте після 18:00', status: 0, days: 14 },
      { name: 'Максим Д.', phone: '+380000000102', email: '', comment: '', status: 1, days: 30 },
      { name: 'Наталя П.', phone: '+380000000103', email: 'preorder3@example.test', comment: 'Потрібен чорний колір', status: 0, days: 21 },
    ],
    leads: [
      { name: 'Сергій М.', phone: '+380000000201', email: 'lead1@example.test', device: 'Kiro Pulse 10, 128 GB, стан гарний, без подряпин', status: 0 },
      { name: 'Юлія Т.', phone: '+380000000202', email: 'lead2@example.test', device: 'Ноутбук 2019 року, 8 GB RAM, працює справно', status: 1 },
      { name: 'Денис Л.', phone: '+380000000203', email: '', device: 'Планшет 10", є тріщина на екрані', status: 0 },
    ],
  };

  return j;
}
