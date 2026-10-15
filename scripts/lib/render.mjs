// Template rendering (SPEC-003 REQ-BLD-035): only {{KEY}} tokens, PHP single-quote escaping, fail on leftovers.
export class TemplateError extends Error {}

export const phpEscape = (v) => String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'");

/** Values are escaped for placement inside PHP single-quoted literals. */
export function render(template, values, name = 'template') {
  const out = template.replace(/\{\{([A-Z0-9_]+)\}\}/g, (_, key) => {
    if (!(key in values)) throw new TemplateError(`${name}: unknown token {{${key}}}`);
    return phpEscape(values[key]);
  });
  if (out.includes('{{')) throw new TemplateError(`${name}: leftover "{{" after rendering`);
  return out;
}
